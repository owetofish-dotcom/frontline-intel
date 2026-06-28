<?php
/**
 * Autoryzacja urządzenia-kiosku tokenem (T-4.1, FR-15).
 * Token jawny widzi tylko admin przy rejestracji; w bazie trzymamy SHA-256.
 */
declare(strict_types=1);

final class Kiosk
{
    /** Wygeneruj nowy token urządzenia (jawny — pokazywany raz). */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(24)); // 48 znaków hex
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Uwierzytelnij żądanie kiosku po nagłówku X-Kiosk-Token.
     * Zwraca wiersz kiosku (z tenant_id, location_id) lub null.
     * Aktualizuje last_seen_at przy sukcesie.
     */
    public static function authenticate(): ?array
    {
        $token = self::tokenFromRequest();
        if ($token === '') {
            return null;
        }
        $kiosk = Database::one(
            "SELECT * FROM kiosks WHERE token_hash = :h AND status='active' LIMIT 1",
            [':h' => self::hashToken($token)]
        );
        if ($kiosk === null) {
            return null;
        }
        Database::run("UPDATE kiosks SET last_seen_at = NOW() WHERE id = :id", [':id' => (int) $kiosk['id']]);
        return $kiosk;
    }

    private static function tokenFromRequest(): string
    {
        $h = $_SERVER['HTTP_X_KIOSK_TOKEN'] ?? '';
        if ($h === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'X-Kiosk-Token') === 0) { $h = $v; break; }
            }
        }
        return trim((string) $h);
    }
}
