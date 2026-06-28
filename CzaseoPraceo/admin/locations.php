<?php
/** Admin firmy: zarządzanie zakładami (T-3.1, FR-14). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireRole('admin');

$tid = Auth::tenantId();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash_set('error', 'Podaj nazwę zakładu.');
        } else {
            Database::run(
                "INSERT INTO locations (tenant_id, name) VALUES (:t, :n)",
                [':t' => $tid, ':n' => $name]
            );
            flash_set('success', 'Dodano zakład: ' . $name);
        }
    } elseif ($action === 'rename') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            Database::run(
                "UPDATE locations SET name = :n WHERE id = :id AND tenant_id = :t",
                [':n' => $name, ':id' => $id, ':t' => $tid]
            );
            flash_set('success', 'Zmieniono nazwę zakładu.');
        }
    } elseif ($action === 'toggle') {
        $id  = (int) ($_POST['id'] ?? 0);
        $new = ($_POST['to'] ?? '') === 'inactive' ? 'inactive' : 'active';
        Database::run(
            "UPDATE locations SET status = :s WHERE id = :id AND tenant_id = :t",
            [':s' => $new, ':id' => $id, ':t' => $tid]
        );
        flash_set('success', $new === 'inactive' ? 'Zakład dezaktywowany.' : 'Zakład aktywny.');
    }
    redirect('locations.php');
}

$locations = Database::all(
    "SELECT l.*,
            (SELECT COUNT(*) FROM employee_locations el WHERE el.location_id = l.id) AS emp_count,
            (SELECT COUNT(*) FROM kiosks k WHERE k.location_id = l.id) AS kiosk_count
     FROM locations l WHERE l.tenant_id = :t ORDER BY l.name",
    [':t' => $tid]
);

layout_header('Zakłady', 'locations.php');
?>

<div class="card">
  <h2 style="margin-top:0">Dodaj zakład</h2>
  <form method="post" action="locations.php" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field" style="flex:1;min-width:240px;margin:0">
      <label for="name">Nazwa zakładu / fabryki</label>
      <input type="text" id="name" name="name" required maxlength="190" placeholder="np. Zakład Tuszyma">
    </div>
    <button class="btn" type="submit">Dodaj zakład</button>
  </form>
</div>

<h2>Lista zakładów</h2>
<?php if (count($locations) === 0): ?>
  <div class="card"><div class="empty">
    <div class="big">Brak zakładów</div>
    <p>Dodaj pierwszy zakład, aby przypisywać do niego pracowników i kioski.</p>
  </div></div>
<?php else: ?>
  <?php foreach ($locations as $l): ?>
    <div class="card" style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <strong style="font-size:1.05rem"><?= h($l['name']) ?></strong>
        <span class="badge <?= $l['status'] === 'active' ? 'ok' : 'off' ?>"><?= $l['status'] === 'active' ? 'aktywny' : 'nieaktywny' ?></span>
        <span class="muted"><?= (int) $l['emp_count'] ?> prac. · <?= (int) $l['kiosk_count'] ?> kiosk.</span>
        <span class="spacer" style="flex:1"></span>
        <form method="post" action="locations.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
          <input type="hidden" name="to" value="<?= $l['status'] === 'active' ? 'inactive' : 'active' ?>">
          <button class="btn btn-secondary" type="submit">
            <?= $l['status'] === 'active' ? 'Dezaktywuj' : 'Aktywuj' ?>
          </button>
        </form>
      </div>
      <details style="margin-top:.6rem">
        <summary>Zmień nazwę</summary>
        <form method="post" action="locations.php" style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap;max-width:480px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="rename">
          <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
          <input type="text" name="name" value="<?= h($l['name']) ?>" required maxlength="190" style="flex:1;min-width:200px">
          <button class="btn btn-outline" type="submit">Zapisz</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
layout_footer();
