<?php
/**
 * Obliczenia czasu pracy: parowanie odbić w sesje, sumy dzienne,
 * zaokrąglanie wg ustawień firmy i nadpisania dnia (FR-7a/7b, D-3).
 */
declare(strict_types=1);

final class Timesheet
{
    /**
     * Sparuj odbicia jednego dnia w sesje (in→out).
     * $rows: lista ['punch_type'=>'in|out','device_time'=>'Y-m-d H:i:s'] posortowana rosnąco.
     * Zwraca ['sessions'=>[['in'=>t,'out'=>t,'minutes'=>n], ...], 'minutes'=>suma, 'open'=>bool].
     */
    public static function pairDay(array $rows): array
    {
        usort($rows, static fn($a, $b) => strcmp($a['device_time'], $b['device_time']));
        $sessions = [];
        $open = null;
        $total = 0;
        foreach ($rows as $r) {
            if ($r['punch_type'] === 'in') {
                if ($open === null) {
                    $open = $r['device_time'];
                }
                // kolejne 'in' bez 'out' — ignorujemy duplikat wejścia
            } else { // out
                if ($open !== null) {
                    $mins = (int) round((strtotime($r['device_time']) - strtotime($open)) / 60);
                    if ($mins < 0) { $mins = 0; }
                    $sessions[] = ['in' => $open, 'out' => $r['device_time'], 'minutes' => $mins];
                    $total += $mins;
                    $open = null;
                }
                // 'out' bez 'in' — ignorujemy sierotę
            }
        }
        return ['sessions' => $sessions, 'minutes' => $total, 'open' => $open !== null];
    }

    /** Zaokrąglij minuty wg trybu firmy. */
    public static function round(int $minutes, string $mode): int
    {
        return match ($mode) {
            'full_hour' => (int) (round($minutes / 60) * 60),
            'min15'     => (int) (round($minutes / 15) * 15),
            'min5'      => (int) (round($minutes / 5) * 5),
            default     => $minutes, // 'exact'
        };
    }

    /** Sformatuj minuty jako H:MM. */
    public static function hm(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }

    /** Liczba godzin dziesiętnie (do CSV/kwoty). */
    public static function decimalHours(int $minutes): float
    {
        return round($minutes / 60, 2);
    }

    /**
     * Najnowsze nadpisanie liczby minut dla (pracownik, dzień), jeśli istnieje (FR-7b).
     * Zwraca int minut lub null.
     */
    public static function overrideMinutes(int $tenantId, int $employeeId, string $date): ?int
    {
        $row = Database::one(
            "SELECT new_value FROM punch_corrections
             WHERE tenant_id = :t AND employee_id = :e AND work_date = :d AND kind = 'set_day_hours'
             ORDER BY id DESC LIMIT 1",
            [':t' => $tenantId, ':e' => $employeeId, ':d' => $date]
        );
        if ($row === null || $row['new_value'] === null || $row['new_value'] === '') {
            return null;
        }
        return (int) $row['new_value'];
    }
}
