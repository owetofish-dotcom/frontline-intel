<?php
/** Czas pracy: podgląd dnia + korekty (T-6.1 manualny wpis, T-6.2 nadpisanie godzin).
 *  Oryginalne odbicia są niezmienne; korekty zapisują autora, czas i powód (FR-6). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireRole('admin', 'location_manager');

$tid     = Auth::tenantId();
$allowed = Scope::allowedLocationIds();
$me      = (int) Auth::user()['id'];

/** Pracownicy w zakresie użytkownika. */
function ts_employees(?int $tid, ?array $allowed): array
{
    if ($allowed === null) {
        return Database::all("SELECT id,first_name,last_name FROM employees WHERE tenant_id=:t AND status='active' ORDER BY last_name,first_name", [':t' => $tid]);
    }
    if ($allowed === []) return [];
    $in = implode(',', array_fill(0, count($allowed), '?'));
    return Database::all(
        "SELECT DISTINCT e.id,e.first_name,e.last_name FROM employees e
         JOIN employee_locations el ON el.employee_id=e.id
         WHERE el.location_id IN ($in) AND e.status='active' ORDER BY e.last_name,e.first_name", $allowed);
}
function ts_can_touch(int $eid, ?int $tid, ?array $allowed): bool
{
    if (!Database::one("SELECT id FROM employees WHERE id=:e AND tenant_id=:t", [':e' => $eid, ':t' => $tid])) return false;
    if ($allowed === null) return true;
    if ($allowed === []) return false;
    $in = implode(',', array_fill(0, count($allowed), '?'));
    return Database::one("SELECT 1 FROM employee_locations WHERE employee_id=? AND location_id IN ($in) LIMIT 1", array_merge([$eid], $allowed)) !== null;
}
/** Pierwszy zakład pracownika dostępny dla użytkownika (na potrzeby wpisu manualnego). */
function ts_emp_location(int $eid, ?array $allowed): ?int
{
    if ($allowed === null) {
        $r = Database::one("SELECT location_id FROM employee_locations WHERE employee_id=:e ORDER BY location_id LIMIT 1", [':e' => $eid]);
    } else {
        if ($allowed === []) return null;
        $in = implode(',', array_fill(0, count($allowed), '?'));
        $r = Database::one("SELECT location_id FROM employee_locations WHERE employee_id=? AND location_id IN ($in) ORDER BY location_id LIMIT 1", array_merge([$eid], $allowed));
    }
    return $r ? (int) $r['location_id'] : null;
}

$employees = ts_employees($tid, $allowed);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $eid    = (int) ($_POST['employee_id'] ?? 0);
    $date   = (string) ($_POST['date'] ?? '');
    $back   = 'timesheet.php?employee_id=' . $eid . '&date=' . urlencode($date);

    if (!ts_can_touch($eid, $tid, $allowed)) {
        flash_set('error', 'Brak dostępu do tego pracownika.');
        redirect('timesheet.php');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        flash_set('error', 'Nieprawidłowa data.');
        redirect($back);
    }

    if ($action === 'manual_punch') {
        $type = ($_POST['punch_type'] ?? '') === 'out' ? 'out' : 'in';
        $time = (string) ($_POST['time'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $loc = ts_emp_location($eid, $allowed);
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            flash_set('error', 'Podaj godzinę w formacie HH:MM.');
        } elseif ($reason === '') {
            flash_set('error', 'Podaj powód korekty.');
        } elseif ($loc === null) {
            flash_set('error', 'Pracownik nie ma przypisanego zakładu w Twoim zakresie.');
        } else {
            $dt = $date . ' ' . $time . ':00';
            Database::run(
                "INSERT INTO punches (tenant_id,location_id,employee_id,punch_type,device_time,source,synced_at)
                 VALUES (:t,:l,:e,:ty,:dt,'manual',NOW())",
                [':t' => $tid, ':l' => $loc, ':e' => $eid, ':ty' => $type, ':dt' => $dt]
            );
            $pid = Database::lastId();
            Database::run(
                "INSERT INTO punch_corrections (tenant_id,employee_id,work_date,kind,punch_id,new_value,reason,author_user_id)
                 VALUES (:t,:e,:d,'manual_entry',:p,:v,:r,:a)",
                [':t' => $tid, ':e' => $eid, ':d' => $date, ':p' => $pid, ':v' => $type . ' ' . $time, ':r' => $reason, ':a' => $me]
            );
            flash_set('success', 'Dodano wpis manualny (' . ($type === 'in' ? 'wejście' : 'wyjście') . ' ' . $time . ').');
        }
    } elseif ($action === 'set_day_hours') {
        $hoursRaw = trim((string) ($_POST['hours'] ?? ''));
        $reason   = trim((string) ($_POST['reason'] ?? ''));
        $minutes  = parse_hours_to_minutes($hoursRaw);
        if ($minutes === null) {
            flash_set('error', 'Podaj liczbę godzin (np. 8, 7.5 lub 7:30).');
        } elseif ($reason === '') {
            flash_set('error', 'Podaj powód korekty.');
        } else {
            Database::run(
                "INSERT INTO punch_corrections (tenant_id,employee_id,work_date,kind,new_value,reason,author_user_id)
                 VALUES (:t,:e,:d,'set_day_hours',:v,:r,:a)",
                [':t' => $tid, ':e' => $eid, ':d' => $date, ':v' => (string) $minutes, ':r' => $reason, ':a' => $me]
            );
            flash_set('success', 'Ustawiono godziny dnia: ' . Timesheet::hm($minutes) . '.');
        }
    }
    redirect($back);
}

function parse_hours_to_minutes(string $s): ?int
{
    $s = str_replace(',', '.', trim($s));
    if ($s === '') return null;
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m)) {
        return (int) $m[1] * 60 + (int) $m[2];
    }
    if (is_numeric($s)) {
        $h = (float) $s;
        if ($h < 0 || $h > 24) return null;
        return (int) round($h * 60);
    }
    return null;
}

// --- Widok ---
$selEid = (int) ($_GET['employee_id'] ?? 0);
$selDate = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate)) $selDate = date('Y-m-d');

$dayPunches = [];
$pair = null; $override = null; $rounding = 'full_hour'; $corrections = [];
if ($selEid && ts_can_touch($selEid, $tid, $allowed)) {
    $rounding = Database::one("SELECT rounding_mode FROM tenants WHERE id=:t", [':t' => $tid])['rounding_mode'] ?? 'full_hour';
    $dayPunches = Database::all(
        "SELECT id,punch_type,device_time,source FROM punches
         WHERE employee_id=:e AND DATE(device_time)=:d ORDER BY device_time",
        [':e' => $selEid, ':d' => $selDate]
    );
    $pair = Timesheet::pairDay($dayPunches);
    $override = Timesheet::overrideMinutes($tid, $selEid, $selDate);
    $corrections = Database::all(
        "SELECT pc.*, u.email AS author FROM punch_corrections pc
         JOIN panel_users u ON u.id=pc.author_user_id
         WHERE pc.employee_id=:e AND pc.work_date=:d ORDER BY pc.id DESC",
        [':e' => $selEid, ':d' => $selDate]
    );
}

layout_header('Czas pracy', 'timesheet.php');
?>

<form method="get" action="timesheet.php" class="card" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
  <div class="field" style="margin:0;min-width:240px">
    <label>Pracownik</label>
    <select name="employee_id" required>
      <option value="">— wybierz —</option>
      <?php foreach ($employees as $e): ?>
        <option value="<?= (int) $e['id'] ?>" <?= $selEid === (int) $e['id'] ? 'selected' : '' ?>><?= h($e['last_name'].' '.$e['first_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field" style="margin:0">
    <label>Dzień</label>
    <input type="date" name="date" value="<?= h($selDate) ?>">
  </div>
  <button class="btn" type="submit">Pokaż</button>
</form>

<?php if ($selEid && $pair !== null): ?>
  <?php
    $rawMin = $pair['minutes'];
    $effMin = $override ?? $rawMin;
    $roundedMin = Timesheet::round($effMin, $rounding);
  ?>
  <div class="cards" style="margin-top:1rem">
    <div class="card"><div class="muted">Czas dokładny (suma sesji)</div><div style="font-size:1.6rem;font-weight:700"><?= Timesheet::hm($rawMin) ?><?= $pair['open'] ? ' ⚠' : '' ?></div></div>
    <div class="card"><div class="muted">Zaliczone w raporcie<?= $override !== null ? ' (nadpisane)' : '' ?></div><div style="font-size:1.6rem;font-weight:700"><?= Timesheet::hm($roundedMin) ?></div></div>
  </div>
  <?php if ($pair['open']): ?>
    <div class="alert alert-warning" role="status">Dzień ma niesparowane wejście (brak wyjścia) — rozważ wpis manualny lub nadpisanie godzin.</div>
  <?php endif; ?>

  <h2>Odbicia dnia <?= h($selDate) ?></h2>
  <table class="data">
    <tr><th>Godzina</th><th>Typ</th><th>Źródło</th></tr>
    <?php if ($dayPunches): foreach ($dayPunches as $p): ?>
      <tr>
        <td style="font-variant-numeric:tabular-nums"><?= h(substr($p['device_time'], 11, 5)) ?></td>
        <td><?= $p['punch_type'] === 'in' ? 'Wejście' : 'Wyjście' ?></td>
        <td><span class="badge <?= $p['source']==='manual'?'beta':'off' ?>"><?= $p['source']==='manual'?'manualne':'kiosk' ?></span></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="3" class="muted">Brak odbić tego dnia.</td></tr>
    <?php endif; ?>
  </table>

  <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));margin-top:1rem">
    <div class="card">
      <h2 style="margin-top:0">Dodaj wpis manualny</h2>
      <form method="post" action="timesheet.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="manual_punch">
        <input type="hidden" name="employee_id" value="<?= $selEid ?>">
        <input type="hidden" name="date" value="<?= h($selDate) ?>">
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
          <div class="field"><label>Typ</label><select name="punch_type" style="width:auto"><option value="in">Wejście</option><option value="out">Wyjście</option></select></div>
          <div class="field"><label>Godzina</label><input type="time" name="time" required></div>
        </div>
        <div class="field"><label>Powód korekty</label><input type="text" name="reason" required maxlength="255" placeholder="np. pracownik zapomniał odbić"></div>
        <button class="btn btn-outline" type="submit">Dodaj wpis</button>
      </form>
    </div>

    <div class="card">
      <h2 style="margin-top:0">Ustaw godziny dnia</h2>
      <p class="muted">Ręczna decyzja o liczbie zaliczonych godzin (np. dzień niejednoznaczny). Nadpisuje wyliczenie z odbić.</p>
      <form method="post" action="timesheet.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_day_hours">
        <input type="hidden" name="employee_id" value="<?= $selEid ?>">
        <input type="hidden" name="date" value="<?= h($selDate) ?>">
        <div class="field"><label>Godziny</label><input type="text" name="hours" required placeholder="np. 8, 7.5 lub 7:30"></div>
        <div class="field"><label>Powód korekty</label><input type="text" name="reason" required maxlength="255"></div>
        <button class="btn btn-outline" type="submit">Ustaw godziny</button>
      </form>
    </div>
  </div>

  <?php if ($corrections): ?>
    <h2>Historia korekt (dzień)</h2>
    <table class="data">
      <tr><th>Kiedy</th><th>Rodzaj</th><th>Wartość</th><th>Powód</th><th>Autor</th></tr>
      <?php foreach ($corrections as $c): ?>
        <tr>
          <td class="muted"><?= h($c['created_at']) ?></td>
          <td><?= $c['kind']==='manual_entry'?'Wpis manualny':($c['kind']==='set_day_hours'?'Nadpisanie godzin':'Korekta') ?></td>
          <td><?= $c['kind']==='set_day_hours' && $c['new_value']!==null ? h(Timesheet::hm((int) $c['new_value'])) : h((string) $c['new_value']) ?></td>
          <td><?= h($c['reason']) ?></td>
          <td class="muted"><?= h($c['author']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php endif; ?>

<?php
layout_footer();
