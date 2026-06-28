<?php
/**
 * Jednorazowy seed: tworzy konto super-admina SaaS.
 * Uruchom z CLI:  php db/seed.php email@example.com "TwojeHaslo"
 * Po użyciu USUŃ ten plik z serwera (lub trzymaj poza katalogiem WWW).
 */
declare(strict_types=1);

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/Database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Seed uruchamiaj tylko z linii poleceń.\n");
}

$email = $argv[1] ?? null;
$pass  = $argv[2] ?? null;
if (!$email || !$pass) {
    fwrite(STDERR, "Użycie: php db/seed.php <email> <haslo>\n");
    exit(1);
}

$email = mb_strtolower(trim($email), 'UTF-8');
$exists = Database::one("SELECT id FROM panel_users WHERE email = :e", [':e' => $email]);
if ($exists) {
    fwrite(STDERR, "Konto o tym e-mailu już istnieje.\n");
    exit(1);
}

Database::run(
    "INSERT INTO panel_users (tenant_id, role, email, password_hash, full_name, status)
     VALUES (NULL, 'super_admin', :e, :h, 'Super Admin', 'active')",
    [':e' => $email, ':h' => password_hash($pass, PASSWORD_DEFAULT)]
);

echo "Utworzono super-admina: {$email}\n";
