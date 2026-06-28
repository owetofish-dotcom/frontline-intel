<?php
/**
 * Uwierzytelnianie i autoryzacja kont panelu (FR-9, FR-17).
 * Role: super_admin / admin / location_manager.
 */
declare(strict_types=1);

final class Auth
{
    /** Bezpieczne uruchomienie sesji + kontrola bezczynności. */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Wygaśnięcie po bezczynności (T-1.3).
        $idle = (int) cfg('app.session_idle', 3600);
        $now  = time();
        if (isset($_SESSION['uid'], $_SESSION['last_seen']) && ($now - (int) $_SESSION['last_seen']) > $idle) {
            self::logout();
        }
        $_SESSION['last_seen'] = $now;
    }

    /**
     * Próba logowania. Zwraca true przy sukcesie.
     * Komunikat błędu celowo ogólny (anty-enumeracja kont) — T-1.2.
     */
    public static function attempt(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $user  = Database::one(
            "SELECT * FROM panel_users WHERE email = :e AND status = 'active' LIMIT 1",
            [':e' => $email]
        );
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        // Odśwież ID sesji po zalogowaniu (ochrona przed session fixation).
        session_regenerate_id(true);
        $_SESSION['uid']       = (int) $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['tenant_id'] = $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null;
        $_SESSION['last_seen'] = time();
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['uid']);
    }

    /** Aktualny użytkownik panelu (pełny wiersz) lub null. */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = Database::one(
            "SELECT * FROM panel_users WHERE id = :id LIMIT 1",
            [':id' => (int) $_SESSION['uid']]
        );
        return $cached;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    /** ID firmy zalogowanego (null dla super_admina). */
    public static function tenantId(): ?int
    {
        return $_SESSION['tenant_id'] ?? null;
    }

    /** Wymuś zalogowanie — inaczej przekieruj do logowania. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login.php');
        }
    }

    /** Wymuś jedną z ról — inaczej 403. */
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            exit('Brak uprawnień do tej operacji.');
        }
    }
}
