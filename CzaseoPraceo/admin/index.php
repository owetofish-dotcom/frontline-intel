<?php
/** Pulpit panelu — widok zależny od roli. */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireLogin();

$role = Auth::role();

layout_header('Pulpit', 'index.php');

if ($role === 'super_admin') {
    $tenants = (int) (Database::one("SELECT COUNT(*) c FROM tenants")['c'] ?? 0);
    $active  = (int) (Database::one("SELECT COUNT(*) c FROM tenants WHERE status='active'")['c'] ?? 0);
    ?>
    <div class="cards">
      <div class="card"><div class="muted">Firmy łącznie</div><div style="font-size:2rem;font-weight:700"><?= $tenants ?></div></div>
      <div class="card"><div class="muted">Aktywne firmy</div><div style="font-size:2rem;font-weight:700"><?= $active ?></div></div>
    </div>
    <?php if ($tenants === 0): ?>
      <div class="card" style="margin-top:1rem">
        <div class="empty">
          <div class="big">Brak firm</div>
          <p>Zacznij od dodania pierwszej firmy (najemcy) i konta jej administratora.</p>
          <a class="btn" href="tenants.php">Dodaj firmę</a>
        </div>
      </div>
    <?php else: ?>
      <p style="margin-top:1rem"><a class="btn" href="tenants.php">Zarządzaj firmami</a></p>
    <?php endif; ?>
    <?php
} else {
    // admin / kierownik — statystyki w obrębie firmy (i zakładu dla kierownika)
    $tid = Auth::tenantId();

    // Zakłady widoczne dla użytkownika
    $allowed = Scope::allowedLocationIds();
    if ($allowed === null) {
        $locations = Database::all("SELECT * FROM locations WHERE tenant_id = :t AND status='active' ORDER BY name", [':t' => $tid]);
    } elseif ($allowed === []) {
        $locations = [];
    } else {
        $in = implode(',', array_fill(0, count($allowed), '?'));
        $locations = Database::all("SELECT * FROM locations WHERE id IN ($in) ORDER BY name", $allowed);
    }

    $empCount = (int) (Database::one("SELECT COUNT(*) c FROM employees WHERE tenant_id = :t AND status='active'", [':t' => $tid])['c'] ?? 0);
    ?>
    <div class="cards">
      <div class="card"><div class="muted">Zakłady (widoczne)</div><div style="font-size:2rem;font-weight:700"><?= count($locations) ?></div></div>
      <?php if ($role === 'admin'): ?>
        <div class="card"><div class="muted">Pracownicy (aktywni)</div><div style="font-size:2rem;font-weight:700"><?= $empCount ?></div></div>
      <?php endif; ?>
    </div>

    <?php if ($role === 'admin' && count($locations) === 0): ?>
      <div class="card" style="margin-top:1rem">
        <div class="empty">
          <div class="big">Brak zakładów</div>
          <p>Dodaj pierwszy zakład (fabrykę/lokalizację), aby móc przypisać pracowników i kioski.</p>
          <a class="btn" href="locations.php">Dodaj zakład</a>
        </div>
      </div>
    <?php endif; ?>
    <?php
}

layout_footer();
