<?php
/** Rejestracja i zarządzanie kioskami (T-4.1, FR-15).
 *  Dostęp: admin (cała firma) i kierownik (jego zakłady). */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireRole('admin', 'location_manager');

$tid     = Auth::tenantId();
$allowed = Scope::allowedLocationIds();

/** Zakłady, dla których użytkownik może rejestrować kioski. */
function kiosk_locations(?int $tid, ?array $allowed): array
{
    if ($allowed === null) {
        return Database::all("SELECT id,name FROM locations WHERE tenant_id=:t AND status='active' ORDER BY name", [':t' => $tid]);
    }
    if ($allowed === []) return [];
    $in = implode(',', array_fill(0, count($allowed), '?'));
    return Database::all("SELECT id,name FROM locations WHERE id IN ($in) ORDER BY name", $allowed);
}

$locations    = kiosk_locations($tid, $allowed);
$locationIds  = array_map(static fn($r) => (int) $r['id'], $locations);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_kiosk') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $lid  = (int) ($_POST['location_id'] ?? 0);
        if ($name === '') {
            flash_set('error', 'Podaj nazwę kiosku.');
        } elseif (!in_array($lid, $locationIds, true)) {
            flash_set('error', 'Wybierz zakład z listy.');
        } else {
            $token = Kiosk::generateToken();
            Database::run(
                "INSERT INTO kiosks (tenant_id, location_id, name, token_hash) VALUES (:t,:l,:n,:h)",
                [':t' => $tid, ':l' => $lid, ':n' => $name, ':h' => Kiosk::hashToken($token)]
            );
            // Token jawny pokazujemy jednorazowo przez flash sesyjny.
            $_SESSION['new_kiosk_token'] = ['name' => $name, 'token' => $token];
            flash_set('success', 'Zarejestrowano kiosk: ' . $name);
        }
    } elseif ($action === 'toggle_kiosk') {
        $id   = (int) ($_POST['kiosk_id'] ?? 0);
        $k    = Database::one("SELECT location_id FROM kiosks WHERE id=:id AND tenant_id=:t", [':id' => $id, ':t' => $tid]);
        if ($k && in_array((int) $k['location_id'], $locationIds, true)) {
            $new = ($_POST['to'] ?? '') === 'inactive' ? 'inactive' : 'active';
            Database::run("UPDATE kiosks SET status=:s WHERE id=:id", [':s' => $new, ':id' => $id]);
            flash_set('success', $new === 'inactive' ? 'Kiosk dezaktywowany.' : 'Kiosk aktywny.');
        } else {
            flash_set('error', 'Brak dostępu do tego kiosku.');
        }
    }
    redirect('kiosks.php');
}

// Lista kiosków w zakresie
if ($locationIds === []) {
    $kiosks = [];
} else {
    $in = implode(',', array_fill(0, count($locationIds), '?'));
    $kiosks = Database::all(
        "SELECT k.*, l.name AS location_name FROM kiosks k JOIN locations l ON l.id=k.location_id
         WHERE k.location_id IN ($in) ORDER BY l.name, k.name", $locationIds
    );
}

$newToken = $_SESSION['new_kiosk_token'] ?? null;
unset($_SESSION['new_kiosk_token']);

layout_header('Kioski', 'kiosks.php');

if ($newToken): ?>
  <div class="card" style="border-color:var(--primary);background:var(--primary-l)">
    <h2 style="margin-top:0">Token kiosku „<?= h($newToken['name']) ?>"</h2>
    <p>Zapisz ten token teraz — <strong>pokazujemy go tylko raz</strong>. Wprowadzisz go w aplikacji kiosku na tablecie.</p>
    <code style="display:block;padding:.8rem;background:#fff;border:1px solid var(--border);border-radius:8px;font-size:1.1rem;word-break:break-all"><?= h($newToken['token']) ?></code>
  </div>
<?php endif;

if ($locations === []): ?>
  <div class="alert alert-warning" role="status">Brak zakładów w Twoim zakresie. Dodaj najpierw zakład.</div>
<?php else: ?>
<div class="card">
  <h2 style="margin-top:0">Zarejestruj kiosk</h2>
  <form method="post" action="kiosks.php" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_kiosk">
    <div class="field" style="flex:1;min-width:200px;margin:0"><label>Nazwa kiosku</label><input type="text" name="name" required maxlength="190" placeholder="np. Wejście główne"></div>
    <div class="field" style="min-width:200px;margin:0">
      <label>Zakład</label>
      <select name="location_id" required>
        <option value="">— wybierz —</option>
        <?php foreach ($locations as $l): ?><option value="<?= (int) $l['id'] ?>"><?= h($l['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">Zarejestruj</button>
  </form>
</div>
<?php endif; ?>

<h2>Lista kiosków</h2>
<?php if (count($kiosks) === 0): ?>
  <div class="card"><div class="empty"><div class="big">Brak kiosków</div><p>Zarejestruj kiosk dla zakładu, aby pracownicy mogli odbijać karty.</p></div></div>
<?php else: ?>
  <table class="data">
    <tr><th>Kiosk</th><th>Zakład</th><th>Status</th><th>Ostatnio aktywny</th><th></th></tr>
    <?php foreach ($kiosks as $k): ?>
      <tr>
        <td><?= h($k['name']) ?></td>
        <td><?= h($k['location_name']) ?></td>
        <td><span class="badge <?= $k['status']==='active'?'ok':'off' ?>"><?= $k['status']==='active'?'aktywny':'nieaktywny' ?></span></td>
        <td class="muted"><?= $k['last_seen_at'] ? h($k['last_seen_at']) : '—' ?></td>
        <td class="num">
          <form method="post" action="kiosks.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_kiosk">
            <input type="hidden" name="kiosk_id" value="<?= (int) $k['id'] ?>">
            <input type="hidden" name="to" value="<?= $k['status']==='active'?'inactive':'active' ?>">
            <button class="btn btn-secondary" type="submit"><?= $k['status']==='active'?'Dezaktywuj':'Aktywuj' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php
layout_footer();
