<?php
/** Super-admin: zarządzanie firmami (najemcami) + konta adminów (T-2.1, T-2.2). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireRole('super_admin');

// --- Obsługa akcji (POST → Redirect → GET) ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_tenant') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash_set('error', 'Podaj nazwę firmy.');
        } else {
            Database::run("INSERT INTO tenants (name) VALUES (:n)", [':n' => $name]);
            flash_set('success', 'Dodano firmę: ' . $name);
        }
    } elseif ($action === 'toggle_status') {
        $id  = (int) ($_POST['tenant_id'] ?? 0);
        $new = ($_POST['to'] ?? '') === 'blocked' ? 'blocked' : 'active';
        Database::run("UPDATE tenants SET status = :s WHERE id = :id", [':s' => $new, ':id' => $id]);
        flash_set('success', $new === 'blocked' ? 'Firma zablokowana.' : 'Firma odblokowana.');
    } elseif ($action === 'add_admin') {
        $tid   = (int) ($_POST['tenant_id'] ?? 0);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $name  = trim((string) ($_POST['full_name'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');

        $tenant = Database::one("SELECT id FROM tenants WHERE id = :id", [':id' => $tid]);
        if (!$tenant) {
            flash_set('error', 'Nie znaleziono firmy.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Nieprawidłowy adres e-mail.');
        } elseif (mb_strlen($pass) < 8) {
            flash_set('error', 'Hasło musi mieć co najmniej 8 znaków.');
        } elseif (Database::one("SELECT id FROM panel_users WHERE email = :e", [':e' => $email])) {
            flash_set('error', 'Konto z tym e-mailem już istnieje.');
        } else {
            Database::run(
                "INSERT INTO panel_users (tenant_id, role, email, password_hash, full_name, status)
                 VALUES (:t, 'admin', :e, :h, :n, 'active')",
                [':t' => $tid, ':e' => $email, ':h' => password_hash($pass, PASSWORD_DEFAULT), ':n' => $name]
            );
            flash_set('success', 'Dodano admina firmy: ' . $email);
        }
    }
    redirect('tenants.php');
}

// --- Dane do widoku ---
$tenants = Database::all(
    "SELECT t.*,
            (SELECT COUNT(*) FROM locations l WHERE l.tenant_id = t.id) AS loc_count,
            (SELECT COUNT(*) FROM employees e WHERE e.tenant_id = t.id) AS emp_count,
            (SELECT COUNT(*) FROM panel_users u WHERE u.tenant_id = t.id AND u.role='admin') AS admin_count
     FROM tenants t ORDER BY t.name"
);

layout_header('Firmy', 'tenants.php');
?>

<div class="card">
  <h2 style="margin-top:0">Dodaj firmę</h2>
  <form method="post" action="tenants.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_tenant">
    <div class="field">
      <label for="name">Nazwa firmy</label>
      <input type="text" id="name" name="name" required maxlength="190">
    </div>
    <button class="btn" type="submit">Dodaj firmę</button>
  </form>
</div>

<h2>Lista firm</h2>
<?php if (count($tenants) === 0): ?>
  <div class="card"><div class="empty"><div class="big">Brak firm</div><p>Dodaj pierwszą firmę powyżej.</p></div></div>
<?php else: ?>
  <?php foreach ($tenants as $t): ?>
    <div class="card" style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <strong style="font-size:1.1rem"><?= h($t['name']) ?></strong>
        <?php if ($t['status'] === 'active'): ?>
          <span class="badge ok">aktywna</span>
        <?php else: ?>
          <span class="badge off">zablokowana</span>
        <?php endif; ?>
        <span class="muted"><?= (int) $t['loc_count'] ?> zakł. · <?= (int) $t['emp_count'] ?> prac. · <?= (int) $t['admin_count'] ?> admin.</span>
        <span class="spacer" style="flex:1"></span>
        <form method="post" action="tenants.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="tenant_id" value="<?= (int) $t['id'] ?>">
          <input type="hidden" name="to" value="<?= $t['status'] === 'active' ? 'blocked' : 'active' ?>">
          <button class="btn btn-secondary" type="submit"
                  onclick="return confirm('<?= $t['status'] === 'active' ? 'Zablokować firmę?' : 'Odblokować firmę?' ?>')">
            <?= $t['status'] === 'active' ? 'Zablokuj' : 'Odblokuj' ?>
          </button>
        </form>
      </div>

      <details style="margin-top:.75rem">
        <summary>Dodaj konto admina firmy</summary>
        <form method="post" action="tenants.php" style="margin-top:.75rem;max-width:420px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_admin">
          <input type="hidden" name="tenant_id" value="<?= (int) $t['id'] ?>">
          <div class="field">
            <label>Imię i nazwisko</label>
            <input type="text" name="full_name" maxlength="190">
          </div>
          <div class="field">
            <label>E-mail</label>
            <input type="email" name="email" required>
          </div>
          <div class="field">
            <label>Hasło</label>
            <input type="password" name="password" required minlength="8">
            <div class="hint">Min. 8 znaków.</div>
          </div>
          <button class="btn" type="submit">Utwórz admina</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
layout_footer();
