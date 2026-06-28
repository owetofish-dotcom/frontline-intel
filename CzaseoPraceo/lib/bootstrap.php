<?php
/**
 * Wspólny bootstrap — dołącz na początku każdego punktu wejścia (api/admin).
 */
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Scope.php';

// Wymuś HTTPS (wymagane dla PWA i RODO) — poza środowiskiem lokalnym.
if (cfg('app.force_https', true) && !is_https()
    && ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost'
    && !str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1')) {
    $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    header('Location: ' . $url, true, 301);
    exit;
}

// Nagłówki bezpieczeństwa.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
