<?php
/** Admin firmy: ustawienia (zaokrąglanie, praca przez północ, stawka) — T-6.3, FR-12. */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/_layout.php';
Auth::startSession();
Auth::requireRole('admin');

$tid = Auth::tenantId();

$ROUNDING = [
    'exact'     => 'Czas dokładny (bez zaokrąglania)',
    'full_hour' => 'Do pełnych godzin (domyślnie)',
    'min15'     => 'Do 15 minut',
    'min5'      => 'Do 5 minut',
];

$LOGO_TYPES = [
    'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
    'image/svg+xml' => 'svg', 'image/gif' => 'gif',
];
const LOGO_MAX = 768 * 1024; // 768 KB

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'upload_logo') {
        $f = $_FILES['logo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_set('error', 'Nie udało się wgrać pliku (sprawdź rozmiar i spróbuj ponownie).');
        } elseif ($f['size'] > LOGO_MAX) {
            flash_set('error', 'Logo jest za duże (maks. 768 KB).');
        } else {
            $mime = function_exists('finfo_open')
                ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])
                : (string) ($f['type'] ?? '');
            if (!isset($LOGO_TYPES[$mime])) {
                flash_set('error', 'Dozwolone formaty: PNG, JPG, WEBP, SVG, GIF.');
            } else {
                $data = base64_encode((string) file_get_contents($f['tmp_name']));
                Database::run("UPDATE tenants SET logo_mime = :m, logo_data = :d WHERE id = :t",
                    [':m' => $mime, ':d' => $data, ':t' => $tid]);
                flash_set('success', 'Zapisano logo firmy.');
            }
        }
    } elseif ($action === 'remove_logo') {
        Database::run("UPDATE tenants SET logo_mime = NULL, logo_data = NULL WHERE id = :t", [':t' => $tid]);
        flash_set('success', 'Usunięto logo firmy.');
    } else {
        $rounding = (string) ($_POST['rounding_mode'] ?? 'full_hour');
        if (!isset($ROUNDING[$rounding])) {
            $rounding = 'full_hour';
        }
        $overnight = isset($_POST['allow_overnight']) ? 1 : 0;
        $rateRaw   = trim((string) ($_POST['hourly_rate'] ?? ''));
        $rate      = $rateRaw === '' ? null : (float) str_replace(',', '.', $rateRaw);
        if ($rate !== null && $rate < 0) {
            flash_set('error', 'Stawka nie może być ujemna.');
        } else {
            Database::run(
                "UPDATE tenants SET rounding_mode = :r, allow_overnight = :o, hourly_rate = :h WHERE id = :t",
                [':r' => $rounding, ':o' => $overnight, ':h' => $rate, ':t' => $tid]
            );
            flash_set('success', 'Zapisano ustawienia.');
        }
    }
    redirect('settings.php');
}

$t = Database::one("SELECT * FROM tenants WHERE id = :t", [':t' => $tid]);

layout_header('Ustawienia', 'settings.php');
?>

<div class="card" style="max-width:560px">
  <form method="post" action="settings.php">
    <?= csrf_field() ?>

    <div class="field">
      <label for="rounding_mode">Zaokrąglanie godzin w raporcie</label>
      <select id="rounding_mode" name="rounding_mode">
        <?php foreach ($ROUNDING as $val => $label): ?>
          <option value="<?= h($val) ?>" <?= $t['rounding_mode'] === $val ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="hint">Czas dokładny odbić jest zawsze widoczny niezależnie od tego ustawienia.</div>
    </div>

    <div class="field">
      <label style="font-weight:400;display:flex;gap:.5rem;align-items:center">
        <input type="checkbox" name="allow_overnight" value="1" style="width:auto" <?= $t['allow_overnight'] ? 'checked' : '' ?>>
        Zezwól na pracę przez północ (doba rozliczeniowa przechodzi na kolejny dzień)
      </label>
      <div class="hint">Domyślnie wyłączone — doba nie przechodzi przez północ (D-5).</div>
    </div>

    <div class="field">
      <label for="hourly_rate">Stawka godzinowa (opcjonalnie)</label>
      <input type="text" id="hourly_rate" name="hourly_rate" inputmode="decimal"
             value="<?= $t['hourly_rate'] !== null ? h(number_format((float) $t['hourly_rate'], 2, '.', '')) : '' ?>"
             placeholder="np. 28.50">
      <div class="hint">Gdy ustawiona, raport pokaże też kwotę. Zostaw puste, aby wyłączyć.</div>
    </div>

    <button class="btn" type="submit">Zapisz ustawienia</button>
  </form>
</div>

<div class="card" style="max-width:560px;margin-top:1rem">
  <h2 style="margin-top:0">Logo firmy</h2>
  <p class="muted">Wyświetla się w panelu oraz na tablicy kiosku. Formaty: PNG, JPG, WEBP, SVG, GIF. Maks. 768 KB.</p>
  <?php if (!empty($t['logo_data'])): ?>
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
      <img src="data:<?= h($t['logo_mime']) ?>;base64,<?= h($t['logo_data']) ?>" alt="Logo firmy"
           style="max-height:64px;max-width:220px;background:#fff;border:1px solid var(--border);border-radius:10px;padding:.4rem">
      <form method="post" action="settings.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove_logo">
        <button class="btn btn-secondary" type="submit" onclick="return confirm('Usunąć logo firmy?')">Usuń logo</button>
      </form>
    </div>
  <?php else: ?>
    <p class="muted">Brak logo.</p>
  <?php endif; ?>
  <form method="post" action="settings.php" enctype="multipart/form-data" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload_logo">
    <div class="field" style="margin:0;flex:1;min-width:220px">
      <label for="logo">Wybierz plik logo</label>
      <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" required>
    </div>
    <button class="btn" type="submit">Wgraj logo</button>
  </form>
</div>

<?php
layout_footer();
