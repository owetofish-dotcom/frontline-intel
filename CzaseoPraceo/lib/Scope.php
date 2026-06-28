<?php
/**
 * Warstwa zakresu danych (T-0.4) — centralne egzekwowanie izolacji:
 *   tenant_id (firma)  oraz  location_id (zakład, dla kierownika).
 *
 * Zasada: KAŻDE zapytanie o dane firmy przechodzi przez ograniczenia stąd,
 * by nie dało się pominąć filtra (konstytucja, zasada IV).
 */
declare(strict_types=1);

final class Scope
{
    /**
     * Lista zakładów, do których ma dostęp zalogowany użytkownik:
     *  - super_admin → null (brak ograniczenia — działa ponad firmami osobno),
     *  - admin       → null (wszystkie zakłady własnej firmy),
     *  - location_manager → tablica location_id z panel_user_locations.
     * Pusta tablica = brak dostępu do żadnego zakładu.
     */
    public static function allowedLocationIds(): ?array
    {
        if (Auth::role() === 'location_manager') {
            $rows = Database::all(
                "SELECT location_id FROM panel_user_locations WHERE panel_user_id = :u",
                [':u' => (int) Auth::user()['id']]
            );
            return array_map(static fn($r) => (int) $r['location_id'], $rows);
        }
        return null; // brak ograniczenia po zakładzie
    }

    /**
     * Zwraca fragment SQL i parametry ograniczające do firmy (i zakładu).
     * $alias — alias tabeli z kolumnami tenant_id / location_id.
     * $withLocation — czy filtrować też po location_id (tabele, które ją mają).
     *
     * Przykład:
     *   [$where, $params] = Scope::sql('e');
     *   "SELECT * FROM employees e WHERE 1=1 {$where}"
     */
    public static function sql(string $alias, bool $withLocation = false): array
    {
        $where  = '';
        $params = [];

        // 1) Firma. super_admin nie ma tenant_id — musi podać firmę jawnie gdzie indziej.
        $tenantId = Auth::tenantId();
        if ($tenantId !== null) {
            $where .= " AND {$alias}.tenant_id = :__tenant";
            $params[':__tenant'] = $tenantId;
        }

        // 2) Zakład (tylko kierownik, tylko tabele z location_id).
        if ($withLocation) {
            $locs = self::allowedLocationIds();
            if (is_array($locs)) {
                if ($locs === []) {
                    $where .= " AND 1=0"; // brak dostępu do żadnego zakładu
                } else {
                    $in = [];
                    foreach (array_values($locs) as $i => $id) {
                        $key = ":__loc{$i}";
                        $in[] = $key;
                        $params[$key] = $id;
                    }
                    $where .= " AND {$alias}.location_id IN (" . implode(',', $in) . ")";
                }
            }
        }

        return [$where, $params];
    }

    /** Czy bieżący użytkownik ma dostęp do danego zakładu. */
    public static function canAccessLocation(int $locationId): bool
    {
        $locs = self::allowedLocationIds();
        return $locs === null || in_array($locationId, $locs, true);
    }
}
