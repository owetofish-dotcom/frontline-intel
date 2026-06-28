<?php
/**
 * Layout panelu — nagłówek z kontekstem (Firma › rola), nawigacja wg roli, stopka.
 * Wymaga zalogowania (Auth) i dołączenia bootstrapu przez stronę wywołującą.
 */
declare(strict_types=1);

/** Nazwa firmy zalogowanego (lub etykieta dla super-admina). */
function current_context_label(): string
{
    $role = Auth::role();
    if ($role === 'super_admin') {
        return 'Wszystkie firmy (SaaS)';
    }
    $tid = Auth::tenantId();
    if ($tid === null) {
        return '';
    }
    $t = Database::one("SELECT name FROM tenants WHERE id = :id", [':id' => $tid]);
    $name = $t['name'] ?? '—';

    if ($role === 'location_manager') {
        $locs = Database::all(
            "SELECT l.name FROM panel_user_locations pul
             JOIN locations l ON l.id = pul.location_id
             WHERE pul.panel_user_id = :u ORDER BY l.name",
            [':u' => (int) Auth::user()['id']]
        );
        $names = array_map(static fn($r) => $r['name'], $locs);
        $suffix = $names ? ' › ' . implode(', ', $names) : '';
        return $name . $suffix;
    }
    return $name;
}

/** Pozycje nawigacji zależne od roli. Tylko zaimplementowane strony. */
function nav_items(): array
{
    return match (Auth::role()) {
        'super_admin' => [
            'index.php'   => 'Pulpit',
            'tenants.php' => 'Firmy',
        ],
        'admin' => [
            'index.php'     => 'Pulpit',
            'locations.php' => 'Zakłady',
            'employees.php' => 'Pracownicy',
            'managers.php'  => 'Kierownicy',
            'kiosks.php'    => 'Kioski',
            'reports.php'   => 'Raporty',
            'settings.php'  => 'Ustawienia',
        ],
        'location_manager' => [
            'index.php'     => 'Pulpit',
            'employees.php' => 'Pracownicy',
            'kiosks.php'    => 'Kioski',
            'reports.php'   => 'Raporty',
        ],
        default => ['index.php' => 'Pulpit'],
    };
}

function role_label(?string $role): string
{
    return match ($role) {
        'super_admin'      => 'Super-admin',
        'admin'            => 'Admin firmy',
        'location_manager' => 'Kierownik zakładu',
        default            => '—',
    };
}

function layout_header(string $title, string $active = 'index.php'): void
{
    $user = Auth::user();
    ?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — CzaseoPraceo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="app-header">
  <div class="bar">
    <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">⏱</span>CzaseoPraceo</a>
    <span class="context"><?= h(current_context_label()) ?></span>
    <span class="spacer"></span>
    <span class="who"><?= h($user['full_name'] ?: $user['email']) ?> · <?= h(role_label(Auth::role())) ?></span>
    <a class="btn btn-secondary" href="logout.php">Wyloguj</a>
  </div>
</header>
<nav class="main">
  <div class="bar">
    <?php foreach (nav_items() as $file => $label): ?>
      <a href="<?= h($file) ?>"<?= $active === $file ? ' class="active"' : '' ?>><?= h($label) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<main class="wrap">
  <h1><?= h($title) ?></h1>
<?php
    flash_render();
}

function layout_footer(): void
{
    ?>
</main>
</body>
</html>
<?php
}
