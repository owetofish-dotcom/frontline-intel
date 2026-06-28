<?php
/** Pracownicy + przypisania do zakładów + karty RFID (T-3.2/3.3/3.4, FR-5/13/16).
 *  Dostęp: admin (cała firma) oraz kierownik (tylko jego zakłady). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireRole('admin', 'location_manager');

$tid     = Auth::tenantId();
$allowed = Scope::allowedLocationIds(); // null = wszystkie zakłady firmy (admin)

/** Zakłady, do których bieżący użytkownik może przypisywać. */
function assignable_locations(?int $tid, ?array $allowed): array
{
    if ($allowed === null) {
        return Database::all("SELECT id, name FROM locations WHERE tenant_id = :t AND status='active' ORDER BY name", [':t' => $tid]);
    }
    if ($allowed === []) {
        return [];
    }
    $in = implode(',', array_fill(0, count($allowed), '?'));
    return Database::all("SELECT id, name FROM locations WHERE id IN ($in) ORDER BY name", $allowed);
}

/** Czy użytkownik ma dostęp do danego pracownika (przez wspólny zakład). */
function can_touch_employee(int $empId, ?int $tid, ?array $allowed): bool
{
    $emp = Database::one("SELECT id FROM employees WHERE id = :id AND tenant_id = :t", [':id' => $empId, ':t' => $tid]);
    if (!$emp) {
        return false;
    }
    if ($allowed === null) {
        return true; // admin
    }
    if ($allowed === []) {
        return false;
    }
    $in   = implode(',', array_fill(0, count($allowed), '?'));
    $args = array_merge([$empId], $allowed);
    $row  = Database::one(
        "SELECT 1 FROM employee_locations WHERE employee_id = ? AND location_id IN ($in) LIMIT 1",
        $args
    );
    return $row !== null;
}

$assignable = assignable_locations($tid, $allowed);
$assignableIds = array_map(static fn($r) => (int) $r['id'], $assignable);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_employee') {
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last  = trim((string) ($_POST['last_name'] ?? ''));
        $locs  = array_map('intval', (array) ($_POST['locations'] ?? []));
        $locs  = array_values(array_intersect($locs, $assignableIds)); // tylko dozwolone
        if ($first === '' || $last === '') {
            flash_set('error', 'Podaj imię i nazwisko.');
        } elseif ($locs === []) {
            flash_set('error', 'Przypisz pracownika do co najmniej jednego zakładu.');
        } else {
            Database::run(
                "INSERT INTO employees (tenant_id, first_name, last_name) VALUES (:t, :f, :l)",
                [':t' => $tid, ':f' => $first, ':l' => $last]
            );
            $eid = Database::lastId();
            foreach ($locs as $lid) {
                Database::run(
                    "INSERT INTO employee_locations (tenant_id, employee_id, location_id) VALUES (:t, :e, :l)",
                    [':t' => $tid, ':e' => $eid, ':l' => $lid]
                );
            }
            flash_set('success', 'Dodano pracownika: ' . $first . ' ' . $last);
        }
    } elseif ($action === 'toggle_employee') {
        $eid = (int) ($_POST['employee_id'] ?? 0);
        if (can_touch_employee($eid, $tid, $allowed)) {
            $new = ($_POST['to'] ?? '') === 'inactive' ? 'inactive' : 'active';
            Database::run("UPDATE employees SET status = :s WHERE id = :e AND tenant_id = :t", [':s' => $new, ':e' => $eid, ':t' => $tid]);
            flash_set('success', $new === 'inactive' ? 'Pracownik dezaktywowany.' : 'Pracownik aktywny.');
        } else {
            flash_set('error', 'Brak dostępu do tego pracownika.');
        }
    } elseif ($action === 'set_locations') {
        $eid  = (int) ($_POST['employee_id'] ?? 0);
        $locs = array_map('intval', (array) ($_POST['locations'] ?? []));
        $locs = array_values(array_intersect($locs, $assignableIds));
        if (can_touch_employee($eid, $tid, $allowed)) {
            // Usuń przypisania w zakresie użytkownika, potem wstaw wybrane (przypisania spoza zakresu zostają nietknięte).
            if ($assignableIds !== []) {
                $in   = implode(',', array_fill(0, count($assignableIds), '?'));
                $args = array_merge([$eid], $assignableIds);
                Database::run("DELETE FROM employee_locations WHERE employee_id = ? AND location_id IN ($in)", $args);
            }
            foreach ($locs as $lid) {
                Database::run(
                    "INSERT INTO employee_locations (tenant_id, employee_id, location_id) VALUES (:t, :e, :l)",
                    [':t' => $tid, ':e' => $eid, ':l' => $lid]
                );
            }
            flash_set('success', 'Zaktualizowano przypisanie do zakładów.');
        } else {
            flash_set('error', 'Brak dostępu do tego pracownika.');
        }
    } elseif ($action === 'add_card') {
        $eid    = (int) ($_POST['employee_id'] ?? 0);
        $number = trim((string) ($_POST['card_number'] ?? ''));
        $status = ($_POST['card_status'] ?? 'active') === 'backup' ? 'backup' : 'active';
        if (!can_touch_employee($eid, $tid, $allowed)) {
            flash_set('error', 'Brak dostępu do tego pracownika.');
        } elseif (!preg_match('/^\d{1,64}$/', $number)) {
            flash_set('error', 'Numer karty musi składać się wyłącznie z cyfr.'); // D-1
        } elseif (Database::one("SELECT id FROM rfid_cards WHERE tenant_id = :t AND card_number = :n", [':t' => $tid, ':n' => $number])) {
            flash_set('error', 'Karta o tym numerze już istnieje w firmie.');
        } else {
            // Jeśli dodajemy kartę aktywną, poprzednią aktywną tego pracownika dezaktywujemy (wymiana, FR-13).
            if ($status === 'active') {
                Database::run(
                    "UPDATE rfid_cards SET status='inactive', deactivated_at = NOW()
                     WHERE employee_id = :e AND status='active'",
                    [':e' => $eid]
                );
            }
            Database::run(
                "INSERT INTO rfid_cards (tenant_id, employee_id, card_number, status) VALUES (:t, :e, :n, :s)",
                [':t' => $tid, ':e' => $eid, ':n' => $number, ':s' => $status]
            );
            flash_set('success', 'Przypisano kartę ' . $number . ($status === 'backup' ? ' (zapasowa).' : '.'));
        }
    } elseif ($action === 'deactivate_card') {
        $cardId = (int) ($_POST['card_id'] ?? 0);
        $card   = Database::one("SELECT employee_id FROM rfid_cards WHERE id = :c AND tenant_id = :t", [':c' => $cardId, ':t' => $tid]);
        if ($card && can_touch_employee((int) $card['employee_id'], $tid, $allowed)) {
            Database::run("UPDATE rfid_cards SET status='inactive', deactivated_at = NOW() WHERE id = :c", [':c' => $cardId]);
            flash_set('success', 'Karta dezaktywowana.');
        } else {
            flash_set('error', 'Brak dostępu do tej karty.');
        }
    }
    redirect('employees.php');
}

// --- Lista pracowników w zakresie użytkownika ---
if ($allowed === null) {
    $employees = Database::all(
        "SELECT * FROM employees WHERE tenant_id = :t ORDER BY last_name, first_name",
        [':t' => $tid]
    );
} elseif ($allowed === []) {
    $employees = [];
} else {
    $in = implode(',', array_fill(0, count($allowed), '?'));
    $employees = Database::all(
        "SELECT DISTINCT e.* FROM employees e
         JOIN employee_locations el ON el.employee_id = e.id
         WHERE el.location_id IN ($in) ORDER BY e.last_name, e.first_name",
        $allowed
    );
}

// Pomocnicze: zakłady i karty pracownika
function emp_location_ids(int $eid): array
{
    return array_map(static fn($r) => (int) $r['location_id'],
        Database::all("SELECT location_id FROM employee_locations WHERE employee_id = :e", [':e' => $eid]));
}
function emp_cards(int $eid): array
{
    return Database::all("SELECT * FROM rfid_cards WHERE employee_id = :e ORDER BY FIELD(status,'active','backup','inactive'), id DESC", [':e' => $eid]);
}

layout_header('Pracownicy', 'employees.php');

if ($assignable === []): ?>
  <div class="alert alert-warning" role="status">Nie masz dostępu do żadnego aktywnego zakładu. Poproś administratora o przypisanie.</div>
<?php else: ?>
<div class="card">
  <h2 style="margin-top:0">Dodaj pracownika</h2>
  <form method="post" action="employees.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_employee">
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:1;min-width:180px"><label>Imię</label><input type="text" name="first_name" required maxlength="120"></div>
      <div class="field" style="flex:1;min-width:180px"><label>Nazwisko</label><input type="text" name="last_name" required maxlength="120"></div>
    </div>
    <div class="field">
      <label>Zakłady</label>
      <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <?php foreach ($assignable as $l): ?>
          <label style="font-weight:400;display:flex;gap:.4rem;align-items:center">
            <input type="checkbox" name="locations[]" value="<?= (int) $l['id'] ?>" style="width:auto"> <?= h($l['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn" type="submit">Dodaj pracownika</button>
  </form>
</div>
<?php endif; ?>

<h2>Lista pracowników</h2>
<?php if (count($employees) === 0): ?>
  <div class="card"><div class="empty">
    <div class="big">Brak pracowników</div>
    <p>Dodaj pierwszego pracownika i przypisz mu kartę RFID.</p>
  </div></div>
<?php else: ?>
  <?php foreach ($employees as $e):
        $eid = (int) $e['id'];
        $eLocs = emp_location_ids($eid);
        $cards = emp_cards($eid); ?>
    <div class="card" style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <strong style="font-size:1.05rem"><?= h($e['first_name'] . ' ' . $e['last_name']) ?></strong>
        <span class="badge <?= $e['status'] === 'active' ? 'ok' : 'off' ?>"><?= $e['status'] === 'active' ? 'aktywny' : 'nieaktywny' ?></span>
        <?php
          $active = array_filter($cards, static fn($c) => $c['status'] === 'active');
          $ac = $active ? reset($active) : null;
        ?>
        <span class="muted"><?= $ac ? 'karta: ' . h($ac['card_number']) : 'brak aktywnej karty' ?></span>
        <span class="spacer" style="flex:1"></span>
        <form method="post" action="employees.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_employee">
          <input type="hidden" name="employee_id" value="<?= $eid ?>">
          <input type="hidden" name="to" value="<?= $e['status'] === 'active' ? 'inactive' : 'active' ?>">
          <button class="btn btn-secondary" type="submit"
            onclick="return confirm('<?= $e['status'] === 'active' ? 'Dezaktywować pracownika?' : 'Aktywować pracownika?' ?>')">
            <?= $e['status'] === 'active' ? 'Dezaktywuj' : 'Aktywuj' ?>
          </button>
        </form>
      </div>

      <details style="margin-top:.7rem">
        <summary>Karty i zakłady</summary>
        <div style="margin-top:.8rem;display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">

          <div>
            <strong>Karty RFID</strong>
            <table class="data" style="margin:.5rem 0">
              <?php if ($cards): foreach ($cards as $c): ?>
                <tr>
                  <td style="font-variant-numeric:tabular-nums"><?= h($c['card_number']) ?></td>
                  <td><span class="badge <?= $c['status']==='active'?'ok':($c['status']==='backup'?'beta':'off') ?>"><?= h($c['status']) ?></span></td>
                  <td class="num">
                    <?php if ($c['status'] !== 'inactive'): ?>
                    <form method="post" action="employees.php" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="deactivate_card">
                      <input type="hidden" name="card_id" value="<?= (int) $c['id'] ?>">
                      <button class="btn btn-secondary" type="submit" onclick="return confirm('Dezaktywować kartę?')">Dezaktywuj</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td class="muted">Brak kart</td></tr>
              <?php endif; ?>
            </table>
            <form method="post" action="employees.php" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_card">
              <input type="hidden" name="employee_id" value="<?= $eid ?>">
              <div style="flex:1;min-width:140px">
                <label style="font-size:.85rem">Numer karty (cyfry)</label>
                <input type="text" name="card_number" inputmode="numeric" pattern="\d+" required placeholder="np. 0008675309">
              </div>
              <div>
                <label style="font-size:.85rem">Typ</label>
                <select name="card_status" style="width:auto"><option value="active">aktywna (wymiana)</option><option value="backup">zapasowa</option></select>
              </div>
              <button class="btn btn-outline" type="submit">Dodaj kartę</button>
            </form>
          </div>

          <div>
            <strong>Zakłady</strong>
            <form method="post" action="employees.php" style="margin-top:.5rem">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="set_locations">
              <input type="hidden" name="employee_id" value="<?= $eid ?>">
              <div style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:.6rem">
                <?php foreach ($assignable as $l): ?>
                  <label style="font-weight:400;display:flex;gap:.4rem;align-items:center">
                    <input type="checkbox" name="locations[]" value="<?= (int) $l['id'] ?>" style="width:auto"
                      <?= in_array((int) $l['id'], $eLocs, true) ? 'checked' : '' ?>> <?= h($l['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <button class="btn btn-outline" type="submit">Zapisz zakłady</button>
            </form>
          </div>

        </div>
      </details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
layout_footer();
