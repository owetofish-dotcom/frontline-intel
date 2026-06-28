<?php
/**
 * Przykładowa konfiguracja CzaseoPraceo.
 * Skopiuj ten plik jako `config.local.php` (obok) i uzupełnij danymi z home.pl.
 * `config.local.php` NIE trafia do gita (patrz .gitignore).
 */
return [
    'db' => [
        'host'     => 'localhost',          // host MySQL z panelu home.pl
        'name'     => 'nazwa_bazy',
        'user'     => 'uzytkownik_bazy',
        'password' => 'haslo_bazy',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        // Wymuś HTTPS (wymagane dla PWA/Service Workera). Wyłącz tylko lokalnie.
        'force_https'  => true,
        // Czas bezczynności sesji panelu (sekundy) zanim wyloguje.
        'session_idle' => 3600,
        // Nazwa wyświetlana aplikacji.
        'name'         => 'CzaseoPraceo',
    ],
];
