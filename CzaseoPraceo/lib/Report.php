<?php
/**
 * Budowanie raportu godzin za okres (FR-7). Łączy odbicia w sesje (Timesheet),
 * stosuje nadpisania dnia (FR-7b) i zaokrąglanie firmy (D-3), liczy kwoty.
 */
declare(strict_types=1);

final class Report
{
    /**
     * @param int[]      $empIds      pracownicy do raportu (już w zakresie użytkownika)
     * @param int[]|null $allowedLocs zakłady kierownika (null = bez ograniczenia)
     * @param int|null   $locationFilter dodatkowy filtr zakładu
     * @return array{rows: array, totals: array}
     */
    public static function build(
        int $tenantId, string $from, string $to, array $empIds,
        string $rounding, ?float $rate, ?array $allowedLocs, ?int $locationFilter
    ): array {
        if ($empIds === []) {
            return ['rows' => [], 'totals' => ['exact' => 0, 'report' => 0, 'amount' => $rate !== null ? 0.0 : null]];
        }
        $empIn = implode(',', array_fill(0, count($empIds), '?'));

        // Warunki zakładu (kierownik + ewentualny filtr)
        $locWhere = ''; $locArgs = [];
        if (is_array($allowedLocs)) {
            if ($allowedLocs === []) { return ['rows' => [], 'totals' => ['exact'=>0,'report'=>0,'amount'=>$rate!==null?0.0:null]]; }
            $locWhere .= ' AND p.location_id IN (' . implode(',', array_fill(0, count($allowedLocs), '?')) . ')';
            $locArgs = array_merge($locArgs, $allowedLocs);
        }
        if ($locationFilter !== null) {
            $locWhere .= ' AND p.location_id = ?';
            $locArgs[] = $locationFilter;
        }

        // Odbicia w okresie
        $args = array_merge($empIds, [$tenantId, $from, $to], $locArgs);
        $punches = Database::all(
            "SELECT p.employee_id, p.punch_type, p.device_time, DATE(p.device_time) AS d
             FROM punches p
             WHERE p.employee_id IN ($empIn) AND p.tenant_id = ? AND DATE(p.device_time) BETWEEN ? AND ? {$locWhere}
             ORDER BY p.employee_id, p.device_time", $args
        );

        // Nadpisania dnia (najnowsze per emp+dzień)
        $ovr = Database::all(
            "SELECT employee_id, work_date, new_value FROM punch_corrections
             WHERE tenant_id = ? AND kind='set_day_hours' AND work_date BETWEEN ? AND ? AND employee_id IN ($empIn)
             ORDER BY id ASC", array_merge([$tenantId, $from, $to], $empIds)
        );
        $overrideMap = [];
        foreach ($ovr as $o) { $overrideMap[$o['employee_id'].'|'.$o['work_date']] = (int) $o['new_value']; }

        // Nazwy pracowników
        $emps = Database::all("SELECT id, first_name, last_name FROM employees WHERE id IN ($empIn) ORDER BY last_name, first_name", $empIds);
        $name = [];
        foreach ($emps as $e) { $name[(int) $e['id']] = $e; }

        // Grupowanie odbić: emp -> date -> rows
        $byEmpDay = [];
        foreach ($punches as $p) {
            $byEmpDay[(int) $p['employee_id']][$p['d']][] = $p;
        }
        // Dni tylko z nadpisaniem (bez odbić) też liczymy
        foreach ($overrideMap as $key => $_) {
            [$eid, $d] = explode('|', $key);
            if (!isset($byEmpDay[(int) $eid][$d])) { $byEmpDay[(int) $eid][$d] = []; }
        }

        $rows = [];
        $tExact = 0; $tReport = 0;
        foreach ($emps as $e) {
            $eid = (int) $e['id'];
            $days = $byEmpDay[$eid] ?? [];
            ksort($days);
            $detail = []; $sumExact = 0; $sumReport = 0; $worked = 0;
            foreach ($days as $d => $rowsDay) {
                $pair = Timesheet::pairDay($rowsDay);
                $ovrKey = $eid.'|'.$d;
                $exact = $overrideMap[$ovrKey] ?? $pair['minutes'];
                $report = Timesheet::round($exact, $rounding);
                if ($exact > 0 || isset($overrideMap[$ovrKey])) { $worked++; }
                $sumExact += $exact; $sumReport += $report;
                $detail[] = [
                    'date' => $d, 'exact' => $exact, 'report' => $report,
                    'overridden' => isset($overrideMap[$ovrKey]), 'open' => $pair['open'],
                ];
            }
            $amount = $rate !== null ? round(Timesheet::decimalHours($sumReport) * $rate, 2) : null;
            $rows[] = [
                'employee_id' => $eid,
                'name' => $e['last_name'].' '.$e['first_name'],
                'days' => $worked,
                'exact' => $sumExact, 'report' => $sumReport, 'amount' => $amount,
                'detail' => $detail,
            ];
            $tExact += $sumExact; $tReport += $sumReport;
        }
        $totals = [
            'exact' => $tExact, 'report' => $tReport,
            'amount' => $rate !== null ? round(Timesheet::decimalHours($tReport) * $rate, 2) : null,
        ];
        return ['rows' => $rows, 'totals' => $totals];
    }
}
