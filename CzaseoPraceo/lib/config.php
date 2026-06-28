<?php
/**
 * Ładowanie konfiguracji i ustawienia globalne.
 * Wczytaj ten plik na początku każdego punktu wejścia (api/admin).
 */
declare(strict_types=1);

date_default_timezone_set('UTC'); // czas urządzenia (D-2) zapisujemy jako podany; UTC jako baza serwera

const APP_ROOT = __DIR__ . '/..';

$configFile = APP_ROOT . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Brak pliku config.local.php — skopiuj config.local.example.php i uzupełnij.');
}

$GLOBALS['__cfg'] = require $configFile;

/** Pobierz wartość konfiguracji: cfg('db.host'), cfg('app.name'). */
function cfg(string $path, mixed $default = null): mixed
{
    $node = $GLOBALS['__cfg'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}
