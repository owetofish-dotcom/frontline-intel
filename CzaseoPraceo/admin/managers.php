<?php
/** Admin firmy: konta kierowników zakładów + przypisanie do zakładów (T-3.5, FR-17). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireRole('admin');

$tid = Auth::tenantId();

/** Zakłady firmy (do przypisania kierownikowi). */
$tenantLocations = Database::all(
    "SELECT id, name FROM locations WHERE tenant_id = :t AND status='active' ORDER BY name",
    [':t' => $tid]
);
$tenantLocationIds = array_map(static fn($r) => (int) $r['id'], $tenantLocations);

/** Czy dany użytkownik to kierownik tej firmy. */
function manager_in_tenant(int $uid, ?int $tid): bool
{
    return Database::one(
        "SELECT id FROM panel_users WHERE id = :u AND tenant_id = :t AND role='location_manager'",
        [':u' => $uid, ':t' => $tid]
    ) !== null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_manager') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $name  = trim((string) ($_POST['full_name'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');
        $locs  = array_values(array_intersect(array_map('intval', (array) ($_POST['locations'] ?? [])), $tenantLocationIds));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Nieprawidłowy adres e-mail.');
        } elseif (mb_strlen($pass) < 8) {
            flash_set('error', 'Hasło musi mieć co najmniej 8 znaków.');
        } elseif ($locs === []) {
            flash_set('error', 'Przypisz kierownika do co najmniej jednego zakładu.');
        } elseif (Database::one("SELECT id FROM panel_users WHERE email = :e", [':e' => $email])) {
            flash_set('error', 'Konto z tym e-mailem już istnieje.');
        } else {
            Database::run(
                "INSERT INTO panel_users (tenant_id, role, email, password_hash, full_name, status)
                 VALUES (:t, 'location_manager', :e, :h, :n, 'active')",
                [':t' => $tid, ':e' => $email, ':h' => password_hash($pass, PASSWORD_DEFAULT), ':n' => $name]
            );
            $uid = Database::lastId();
            foreach ($locs as $lid) {
                Database::run("INSERT INTO panel_user_locations (panel_user_id, location_id) VALUES (:u, :l)", [':u' => $uid, ':l' => $lid]);
            }
            flash_set('success', 'Dodano kierownika: ' . $email);
        }
    } elseif ($action === 'set_locations') {
        $uid  = (int) ($_POST['user_id'] ?? 0);
        $locs = array_values(array_intersect(array_map('intval', (array) ($_POST['locations'] ?? [])), $tenantLocationIds));
        if (manager_in_tenant($uid, $tid)) {
            Database::run("DELETE FROM panel_user_locations WHERE panel_user_id = :u", [':u' => $uid]);
            foreach ($locs as $lid) {
                Database::run("INSERT INTO panel_user_locations (panel_user_id, location_id) VALUES (:u, :l)", [':u' => $uid, ':l' => $lid]);
            }
            flash_set('success', 'Zaktualizowano zakłady kierownika.');
        } else {
            flash_set('error', 'Brak dostępu do tego konta.');
        }
    } elseif ($action === 'toggle_manager') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if (manager_in_tenant($uid, $tid)) {
            $new = ($_POST['to'] ?? '') === 'inactive' ? 'inactive' : 'active';
            Database::run("UPDATE panel_users SET status = :s WHERE id = :u", [':s' => $new, ':u' => $uid]);
            flash_set('success', $new === 'inactive' ? 'Konto kierownika dezaktywowane.' : 'Konto kierownika aktywne.');
        } else {
            flash_set('error', 'Brak dostępu do tego konta.');
        }
    }
    redirect('managers.php');
}

$managers = Database::all(
    "SELECT * FROM panel_users WHERE tenant_id = :t AND role='location_manager' ORDER BY full_name, email",
    [':t' => $tid]
);

function manager_location_ids(int $uid): array
{
    return array_map(static fn($r) => (int) $r['location_id'],
        Database::all("SELECT location_id FROM panel_user_locations WHERE panel_user_id = :u", [':u' => $uid]));
}

layout_header('Kierownicy', 'managers.php');

if ($tenantLocations === []): ?>
  <div class="alert alert-warning" role="status">Najpierw dodaj zakłady, aby móc przypisać do nich kierowników. <a href="locations.php">Przejdź do zakładów</a>.</div>
<?php else: ?>
<div class="card">
  <h2 style="margin-top:0">Dodaj kierownika</h2>
  <form method="post" action="managers.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_manager">
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
      <div class="field" style="flex:1;min-width:200px"><label>Imię i nazwisko</label><input type="text" name="full_name" maxlength="190"></div>
      <div class="field" style="flex:1;min-width:200px"><label>E-mail</label><input type="email" name="email" required></div>
      <div class="field" style="flex:1;min-width:160px"><label>Hasło</label><input type="password" name="password" required minlength="8"><div class="hint">Min. 8 znaków.</div></div>
    </div>
    <div class="field">
      <label>Zakłady kierownika</label>
      <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <?php foreach ($tenantLocations as $l): ?>
          <label style="font-weight:400;display:flex;gap:.4rem;align-items:center">
            <input type="checkbox" name="locations[]" value="<?= (int) $l['id'] ?>" style="width:auto"> <?= h($l['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn" type="submit">Utwórz kierownika</button>
  </form>
</div>
<?php endif; ?>

<h2>Lista kierowników</h2>
<?php if (count($managers) === 0): ?>
  <div class="card"><div class="empty">
    <div class="big">Brak kierowników</div>
    <p>Kierownik widzi i rozlicza wyłącznie przypisane mu zakłady.</p>
  </div></div>
<?php else: ?>
  <?php foreach ($managers as $m):
        $uid = (int) $m['id'];
        $mLocs = manager_location_ids($uid); ?>
    <div class="card" style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <strong><?= h($m['full_name'] ?: $m['email']) ?></strong>
        <span class="muted"><?= h($m['email']) ?></span>
        <span class="badge <?= $m['status'] === 'active' ? 'ok' : 'off' ?>"><?= $m['status'] === 'active' ? 'aktywny' : 'nieaktywny' ?></span>
        <span class="spacer" style="flex:1"></span>
        <form method="post" action="managers.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_manager">
          <input type="hidden" name="user_id" value="<?= $uid ?>">
          <input type="hidden" name="to" value="<?= $m['status'] === 'active' ? 'inactive' : 'active' ?>">
          <button class="btn btn-secondary" type="submit"><?= $m['status'] === 'active' ? 'Dezaktywuj' : 'Aktywuj' ?></button>
        </form>
      </div>
      <details style="margin-top:.6rem"<?= $tenantLocations === [] ? ' hidden' : '' ?>>
        <summary>Zakłady (<?= count($mLocs) ?>)</summary>
        <form method="post" action="managers.php" style="margin-top:.6rem">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_locations">
          <input type="hidden" name="user_id" value="<?= $uid ?>">
          <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:.6rem">
            <?php foreach ($tenantLocations as $l): ?>
              <label style="font-weight:400;display:flex;gap:.4rem;align-items:center">
                <input type="checkbox" name="locations[]" value="<?= (int) $l['id'] ?>" style="width:auto" <?= in_array((int) $l['id'], $mLocs, true) ? 'checked' : '' ?>> <?= h($l['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <button class="btn btn-outline" type="submit">Zapisz zakłady</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
layout_footer();
