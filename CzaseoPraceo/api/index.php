<?php
/**
 * API kiosku (JSON). Autoryzacja nagłówkiem X-Kiosk-Token.
 * Trasy (PATH_INFO lub ?r=):
 *   GET  employees   — lista pracowników zakładu kiosku + karty + stan
 *   POST punches     — sync paczki odbić (idempotentnie po device_punch_id)
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

// Trasa
$route = trim((string) ($_SERVER['PATH_INFO'] ?? ($_GET['r'] ?? '')), '/');

// Autoryzacja urządzenia
$kiosk = Kiosk::authenticate();
if ($kiosk === null) {
    json_error('Nieautoryzowany kiosk.', 401);
}
$tenantId   = (int) $kiosk['tenant_id'];
$locationId = (int) $kiosk['location_id'];
$method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($route === 'employees' && $method === 'GET') {
    handle_employees($tenantId, $locationId);
} elseif ($route === 'punches' && $method === 'POST') {
    handle_punch_sync($kiosk, $tenantId, $locationId);
} else {
    json_error('Nieznana trasa lub metoda.', 404);
}

/** Lista pracowników zakładu z kartami i ostatnim stanem (do lokalnej kopii kiosku). */
function handle_employees(int $tenantId, int $locationId): void
{
    $employees = Database::all(
        "SELECT e.id, e.first_name, e.last_name
         FROM employees e
         JOIN employee_locations el ON el.employee_id = e.id AND el.location_id = :loc
         WHERE e.tenant_id = :t AND e.status = 'active'
         ORDER BY e.last_name, e.first_name",
        [':loc' => $locationId, ':t' => $tenantId]
    );
    if ($employees === []) {
        json_out(['ok' => true, 'location_id' => $locationId, 'server_time' => date('c'), 'employees' => []]);
    }

    $ids = array_map(static fn($e) => (int) $e['id'], $employees);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    // Karty aktywne/zapasowe
    $cards = Database::all(
        "SELECT employee_id, card_number, status FROM rfid_cards
         WHERE employee_id IN ($in) AND status IN ('active','backup')", $ids
    );
    $cardMap = [];
    foreach ($cards as $c) {
        $cardMap[(int) $c['employee_id']][] = ['number' => $c['card_number'], 'status' => $c['status']];
    }

    // Ostatni stan (in/out) z odbić w tym zakładzie
    $last = Database::all(
        "SELECT p.employee_id, p.punch_type, p.device_time
         FROM punches p
         JOIN (SELECT employee_id, MAX(device_time) mt FROM punches
               WHERE employee_id IN ($in) AND location_id = ? GROUP BY employee_id) m
           ON m.employee_id = p.employee_id AND m.mt = p.device_time
         WHERE p.location_id = ?",
        array_merge($ids, [$locationId, $locationId])
    );
    $stateMap = [];
    foreach ($last as $l) {
        $stateMap[(int) $l['employee_id']] = ['state' => $l['punch_type'], 'time' => $l['device_time']];
    }

    $out = [];
    foreach ($employees as $e) {
        $id = (int) $e['id'];
        $out[] = [
            'id'         => $id,
            'first_name' => $e['first_name'],
            'last_name'  => $e['last_name'],
            'initials'   => initials_3_3($e['first_name'], $e['last_name']),
            'cards'      => $cardMap[$id] ?? [],
            'state'      => $stateMap[$id]['state'] ?? 'out', // brak odbić → traktuj jak „poza pracą"
            'last_time'  => $stateMap[$id]['time'] ?? null,
        ];
    }
    json_out(['ok' => true, 'location_id' => $locationId, 'server_time' => date('c'), 'employees' => $out]);
}

/** Sync paczki odbić: idempotencja po (kiosk_id, device_punch_id) + dedup 15 min (FR-4a). */
function handle_punch_sync(array $kiosk, int $tenantId, int $locationId): void
{
    $raw  = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body) || !isset($body['punches']) || !is_array($body['punches'])) {
        json_error('Oczekiwano JSON: { "punches": [...] }', 422);
    }
    $kioskId = (int) $kiosk['id'];
    $results = [];

    foreach ($body['punches'] as $i => $p) {
        $devId  = trim((string) ($p['device_punch_id'] ?? ''));
        $number = trim((string) ($p['card_number'] ?? ''));
        $type   = (string) ($p['punch_type'] ?? '');
        $timeIn = (string) ($p['device_time'] ?? '');

        $key = $devId !== '' ? $devId : "idx{$i}";

        if ($devId === '' || !in_array($type, ['in', 'out'], true) || !preg_match('/^\d{1,64}$/', $number)) {
            $results[$key] = 'rejected:bad_request';
            continue;
        }
        $ts = strtotime($timeIn);
        if ($ts === false) {
            $results[$key] = 'rejected:bad_time';
            continue;
        }
        $deviceTime = date('Y-m-d H:i:s', $ts);

        // Karta → pracownik, w obrębie zakładu kiosku
        $card = Database::one(
            "SELECT c.employee_id FROM rfid_cards c
             JOIN employee_locations el ON el.employee_id = c.employee_id AND el.location_id = :loc
             JOIN employees e ON e.id = c.employee_id AND e.status='active'
             WHERE c.tenant_id = :t AND c.card_number = :n AND c.status IN ('active','backup') LIMIT 1",
            [':loc' => $locationId, ':t' => $tenantId, ':n' => $number]
        );
        if ($card === null) {
            $results[$key] = 'rejected:unknown_card';
            continue;
        }
        $empId = (int) $card['employee_id'];

        // Idempotencja: jeśli to odbicie już jest, potwierdź bez duplikatu
        $exists = Database::one(
            "SELECT id FROM punches WHERE kiosk_id = :k AND device_punch_id = :d",
            [':k' => $kioskId, ':d' => $devId]
        );
        if ($exists) {
            $results[$key] = 'duplicate';
            continue;
        }

        // Dedup 15 min (FR-4a): inne odbicie tego pracownika w ±15 min
        $near = Database::one(
            "SELECT id FROM punches
             WHERE employee_id = :e AND ABS(TIMESTAMPDIFF(SECOND, device_time, :dt)) < 900 LIMIT 1",
            [':e' => $empId, ':dt' => $deviceTime]
        );
        if ($near) {
            $results[$key] = 'deduped';
            continue;
        }

        Database::run(
            "INSERT INTO punches (tenant_id, location_id, employee_id, punch_type, device_time, kiosk_id, device_punch_id, source, synced_at)
             VALUES (:t,:loc,:e,:ty,:dt,:k,:d,'kiosk',NOW())",
            [':t' => $tenantId, ':loc' => $locationId, ':e' => $empId, ':ty' => $type, ':dt' => $deviceTime, ':k' => $kioskId, ':d' => $devId]
        );
        $results[$key] = 'accepted';
    }

    json_out(['ok' => true, 'server_time' => date('c'), 'results' => $results]);
}
