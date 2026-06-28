<?php
/** Raporty godzin: widok + eksport CSV i PDF (T-7.1/7.2/7.3, FR-7/8). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
Auth::startSession();
Auth::requireRole('admin', 'location_manager');

$tid     = Auth::tenantId();
$allowed = Scope::allowedLocationIds();
$tenant  = Database::one("SELECT * FROM tenants WHERE id=:t", [':t' => $tid]);
$rounding = $tenant['rounding_mode'] ?? 'full_hour';
$rate     = $tenant['hourly_rate'] !== null ? (float) $tenant['hourly_rate'] : null;

/* Zakłady w zakresie (do filtra) */
function rep_locations(?int $tid, ?array $allowed): array
{
    if ($allowed === null) return Database::all("SELECT id,name FROM locations WHERE tenant_id=:t ORDER BY name", [':t' => $tid]);
    if ($allowed === []) return [];
    $in = implode(',', array_fill(0, count($allowed), '?'));
    return Database::all("SELECT id,name FROM locations WHERE id IN ($in) ORDER BY name", $allowed);
}
/* Pracownicy w zakresie (opcjonalnie w danym zakładzie) */
function rep_employee_ids(?int $tid, ?array $allowed, ?int $locFilter): array
{
    if ($allowed === null && $locFilter === null) {
        $rows = Database::all("SELECT id FROM employees WHERE tenant_id=:t AND status='active'", [':t' => $tid]);
    } else {
        $locs = $allowed;
        if ($locFilter !== null) { $locs = $allowed === null ? [$locFilter] : array_values(array_intersect($allowed, [$locFilter])); }
        if ($locs === null) { // admin bez filtra obsłużony wyżej
            $rows = Database::all("SELECT id FROM employees WHERE tenant_id=:t AND status='active'", [':t' => $tid]);
        } elseif ($locs === []) {
            $rows = [];
        } else {
            $in = implode(',', array_fill(0, count($locs), '?'));
            $rows = Database::all("SELECT DISTINCT e.id FROM employees e JOIN employee_locations el ON el.employee_id=e.id
                                   WHERE el.location_id IN ($in) AND e.status='active'", $locs);
        }
    }
    return array_map(static fn($r) => (int) $r['id'], $rows);
}

$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to   = (string) ($_GET['to']   ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');
$locFilter = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int) $_GET['location_id'] : null;

$locations = rep_locations($tid, $allowed);
$locationIds = array_map(static fn($r) => (int) $r['id'], $locations);
if ($locFilter !== null && !in_array($locFilter, $locationIds, true)) $locFilter = null;

$empIds = rep_employee_ids($tid, $allowed, $locFilter);
$report = Report::build($tid, $from, $to, $empIds, $rounding, $rate, $allowed, $locFilter);
$format = (string) ($_GET['format'] ?? 'html');

/* ---------- Eksport CSV ---------- */
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="raport_' . $from . '_' . $to . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM dla Excela
    $out = fopen('php://output', 'w');
    $head = ['Pracownik', 'Data', 'Godziny (dokładnie)', 'Godziny (raport)', 'Godziny dziesiętnie', 'Nadpisane'];
    if ($rate !== null) $head[] = 'Kwota';
    fputcsv($out, $head, ';');
    foreach ($report['rows'] as $r) {
        foreach ($r['detail'] as $d) {
            $line = [$r['name'], $d['date'], Timesheet::hm($d['exact']), Timesheet::hm($d['report']),
                     number_format(Timesheet::decimalHours($d['report']), 2, ',', ''), $d['overridden'] ? 'tak' : ''];
            if ($rate !== null) $line[] = number_format(Timesheet::decimalHours($d['report']) * $rate, 2, ',', '');
            fputcsv($out, $line, ';');
        }
        $sum = [$r['name'].' — RAZEM', '', Timesheet::hm($r['exact']), Timesheet::hm($r['report']),
                number_format(Timesheet::decimalHours($r['report']), 2, ',', ''), ''];
        if ($rate !== null) $sum[] = number_format($r['amount'] ?? 0, 2, ',', '');
        fputcsv($out, $sum, ';');
    }
    fclose($out);
    exit;
}

/* ---------- Eksport PDF ---------- */
if ($format === 'pdf') {
    $pdf = new Pdf();
    $rowsPerPage = 34; $i = 0;
    $startPage = function () use ($pdf, $tenant, $from, $to, $rate) {
        $pdf->addPage();
        $pdf->text(40, 50, 'Raport czasu pracy', 16, true);
        $pdf->text(40, 72, ($tenant['name'] ?? '') . '   Okres: ' . $from . ' – ' . $to, 10);
        $y = 100;
        $pdf->text(40, $y, 'Pracownik', 10, true);
        $pdf->text(300, $y, 'Dni', 10, true);
        $pdf->text(350, $y, 'Dokladnie', 10, true);
        $pdf->text(430, $y, 'Raport', 10, true);
        if ($rate !== null) $pdf->text(500, $y, 'Kwota', 10, true);
        $pdf->line(40, $y + 6, 555, $y + 6);
        return $y + 22;
    };
    $y = $startPage();
    foreach ($report['rows'] as $r) {
        if ($i > 0 && $i % $rowsPerPage === 0) { $y = $startPage(); }
        $pdf->text(40, $y, $r['name'], 10);
        $pdf->text(300, $y, (string) $r['days'], 10);
        $pdf->text(350, $y, Timesheet::hm($r['exact']), 10);
        $pdf->text(430, $y, Timesheet::hm($r['report']), 10);
        if ($rate !== null) $pdf->text(500, $y, number_format($r['amount'] ?? 0, 2, '.', ' '), 10);
        $y += 18; $i++;
    }
    $pdf->line(40, $y, 555, $y);
    $y += 16;
    $pdf->text(40, $y, 'RAZEM', 10, true);
    $pdf->text(350, $y, Timesheet::hm($report['totals']['exact']), 10, true);
    $pdf->text(430, $y, Timesheet::hm($report['totals']['report']), 10, true);
    if ($rate !== null) $pdf->text(500, $y, number_format($report['totals']['amount'] ?? 0, 2, '.', ' '), 10, true);

    $body = $pdf->output();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="raport_' . $from . '_' . $to . '.pdf"');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

/* ---------- Widok HTML ---------- */
require __DIR__ . '/_layout.php';
$qs = 'from=' . urlencode($from) . '&to=' . urlencode($to) . ($locFilter !== null ? '&location_id=' . $locFilter : '');
layout_header('Raporty', 'reports.php');
?>

<form method="get" action="reports.php" class="card" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
  <div class="field" style="margin:0"><label>Od</label><input type="date" name="from" value="<?= h($from) ?>"></div>
  <div class="field" style="margin:0"><label>Do</label><input type="date" name="to" value="<?= h($to) ?>"></div>
  <?php if ($locations): ?>
  <div class="field" style="margin:0;min-width:200px">
    <label>Zakład</label>
    <select name="location_id">
      <option value="">Wszystkie</option>
      <?php foreach ($locations as $l): ?><option value="<?= (int) $l['id'] ?>" <?= $locFilter === (int) $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <button class="btn" type="submit">Pokaż</button>
  <a class="btn btn-outline" href="reports.php?<?= h($qs) ?>&format=csv">Eksport CSV</a>
  <a class="btn btn-outline" href="reports.php?<?= h($qs) ?>&format=pdf">Eksport PDF</a>
</form>

<p class="muted">Okres <?= h($from) ?> – <?= h($to) ?>. Zaokrąglanie: <?= h($rounding) ?>. Czas dokładny zawsze widoczny.</p>

<?php if (count($report['rows']) === 0): ?>
  <div class="card"><div class="empty"><div class="big">Brak danych</div><p>Brak odbić dla wybranych filtrów i okresu.</p></div></div>
<?php else: ?>
  <table class="data">
    <tr>
      <th>Pracownik</th><th class="num">Dni</th><th class="num">Dokładnie</th><th class="num">W raporcie</th>
      <?php if ($rate !== null): ?><th class="num">Kwota</th><?php endif; ?>
    </tr>
    <?php foreach ($report['rows'] as $r): ?>
      <tr>
        <td><?= h($r['name']) ?></td>
        <td class="num"><?= (int) $r['days'] ?></td>
        <td class="num"><?= Timesheet::hm($r['exact']) ?></td>
        <td class="num"><?= Timesheet::hm($r['report']) ?></td>
        <?php if ($rate !== null): ?><td class="num"><?= number_format($r['amount'] ?? 0, 2, ',', ' ') ?> zł</td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <tr style="font-weight:700">
      <td>RAZEM</td>
      <td class="num"></td>
      <td class="num"><?= Timesheet::hm($report['totals']['exact']) ?></td>
      <td class="num"><?= Timesheet::hm($report['totals']['report']) ?></td>
      <?php if ($rate !== null): ?><td class="num"><?= number_format($report['totals']['amount'] ?? 0, 2, ',', ' ') ?> zł</td><?php endif; ?>
    </tr>
  </table>
<?php endif; ?>

<?php
layout_footer();
