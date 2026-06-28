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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
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

<?php
layout_footer();
