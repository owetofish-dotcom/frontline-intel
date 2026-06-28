<?php
/**
 * Cron CzaseoPraceo (T-8.1). Bezpieczne, nieinwazyjne porządki + diagnostyka.
 * Uruchamiany z CLI/crona, np. raz na godzinę:
 *   /usr/bin/php /sciezka/CzaseoPraceo/cron/maintenance.php >> /sciezka/CzaseoPraceo/cron/maintenance.log 2>&1
 *
 * Co robi (tylko odczyt + log, nie zmienia odbić):
 *  - heartbeat (znacznik ostatniego uruchomienia),
 *  - wykrywa otwarte zmiany > 18h (pracownik odbił wejście, brak wyjścia),
 *  - wykrywa kioski nieaktywne > 24h.
 * Wynik trafia na stdout (przekierowany do logu), do wglądu administratora.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Cron uruchamiaj tylko z linii poleceń.\n");
}

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/Database.php';

$now = date('Y-m-d H:i:s');
echo "[{$now}] === maintenance start ===\n";

/* Otwarte zmiany > 18h: ostatnie odbicie pracownika to 'in' starsze niż 18h. */
$openShifts = Database::all(
    "SELECT p.tenant_id, p.employee_id, e.first_name, e.last_name, p.device_time
     FROM punches p
     JOIN (SELECT employee_id, MAX(device_time) mt FROM punches GROUP BY employee_id) m
       ON m.employee_id = p.employee_id AND m.mt = p.device_time
     JOIN employees e ON e.id = p.employee_id
     WHERE p.punch_type = 'in' AND p.device_time < (NOW() - INTERVAL 18 HOUR)"
);
if ($openShifts) {
    echo "UWAGA: otwarte zmiany > 18h (możliwy brak wyjścia):\n";
    foreach ($openShifts as $s) {
        echo "  - firma {$s['tenant_id']} | {$s['first_name']} {$s['last_name']} | wejście {$s['device_time']}\n";
    }
} else {
    echo "OK: brak długo otwartych zmian.\n";
}

/* Kioski nieaktywne > 24h (lub nigdy nieaktywne). */
$staleKiosks = Database::all(
    "SELECT id, tenant_id, name, last_seen_at FROM kiosks
     WHERE status='active' AND (last_seen_at IS NULL OR last_seen_at < (NOW() - INTERVAL 24 HOUR))"
);
if ($staleKiosks) {
    echo "UWAGA: kioski nieaktywne > 24h:\n";
    foreach ($staleKiosks as $k) {
        echo "  - firma {$k['tenant_id']} | {$k['name']} | ostatnio: " . ($k['last_seen_at'] ?? 'nigdy') . "\n";
    }
} else {
    echo "OK: wszystkie aktywne kioski widziane w ciągu 24h.\n";
}

echo "[{$now}] === maintenance koniec ===\n";
