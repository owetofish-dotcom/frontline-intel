<?php
/**
 * Funkcje pomocnicze: bezpieczeństwo, wyjście, CSRF, format.
 */
declare(strict_types=1);

/** Escape HTML (XSS). Używać przy KAŻDYM wyjściu danych do widoku. */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Przekierowanie i koniec. */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Odpowiedź JSON (API) i koniec. */
function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Błąd API w jednym formacie. */
function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_out(['ok' => false, 'error' => $message] + $extra, $status);
}

/** Token CSRF (na sesję). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Ukryte pole formularza z tokenem CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/** Weryfikacja tokenu CSRF dla żądań POST. Przerywa przy błędzie. */
function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Sesja wygasła lub nieprawidłowy token. Odśwież stronę i spróbuj ponownie.');
    }
}

/** Czy żądanie jest po HTTPS. */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Inicjały 3+3 dla listy obecnych na kiosku (FR-4b, RODO). */
function initials_3_3(string $first, string $last): string
{
    $cut = static fn(string $s): string => mb_substr(trim($s), 0, 3, 'UTF-8');
    return mb_strtoupper($cut($first) . ' ' . $cut($last), 'UTF-8');
}
