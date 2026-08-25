<?php
declare(strict_types=1);

function yovel_admin_manufacturing_report_definitions(): array
{
    return [
        'downtime-analysis' => [
            'key' => 'downtime-analysis',
            'label' => 'Downtime Analysis',
            'record_type' => 'DOWNTIME_ENTRY',
            'source' => 'project_company_manufacturing_downtime',
            'read_only' => true,
        ],
    ];
}

function yovel_admin_manufacturing_report_date(mixed $value, string $label): DateTimeImmutable
{
    $text = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    if (!$date || $date->format('Y-m-d') !== $text) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $date;
}

function yovel_admin_manufacturing_working_intervals(array $station, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
{
    $intervals = [];
    for ($date = $from->setTime(0, 0); $date < $toExclusive; $date = $date->modify('+1 day')) {
        if (in_array($date->format('Y-m-d'), $station['holiday_dates'], true)) {
            continue;
        }
        $weekday = (int) $date->format('N');
        foreach ($station['working_hours'] as $hours) {
            if ((int) $hours['weekday'] !== $weekday) {
                continue;
            }
            $start = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $hours['start_time']);
            $end = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $hours['end_time']);
            $intervals[] = ['start' => max($from->getTimestamp(), $start->getTimestamp()), 'end' => min($toExclusive->getTimestamp(), $end->getTimestamp())];
        }
    }
    return array_values(array_filter($intervals, static fn (array $interval): bool => $interval['end'] > $interval['start']));
}

function yovel_admin_manufacturing_report(array $company, string $reportKey, array $filters = []): array
{
    $reportKey = strtolower(trim($reportKey));
    if (!isset(yovel_admin_manufacturing_report_definitions()[$reportKey])) {
        throw new InvalidArgumentException('Manufacturing report is not registered.');
    }
    [, $companyKeyHash] = yovel_admin_manufacturing_read_scope($company);
    yovel_admin_manufacturing_schema();
    $from = yovel_admin_manufacturing_report_date($filters['date_from'] ?? date('Y-m-01'), 'Downtime Analysis start date');
    $to = yovel_admin_manufacturing_report_date($filters['date_to'] ?? date('Y-m-d'), 'Downtime Analysis end date');
    if ($to < $from || $to->diff($from)->days > 731) {
        throw new InvalidArgumentException('Downtime Analysis date range is invalid.');
    }
    $toExclusive = $to->modify('+1 day');
    $stations = yovel_admin_manufacturing_workstations($company);
    $rows = [];
    $totalWorking = 0;
    $totalDowntime = 0;
    foreach ($stations as $station) {
        if ((string) $station['record_status'] !== 'ACTIVE') {
            continue;
        }
        $workingIntervals = yovel_admin_manufacturing_working_intervals($station, $from, $toExclusive);
        $workingSeconds = array_sum(array_map(static fn (array $interval): int => $interval['end'] - $interval['start'], $workingIntervals));
        $downtimes = bx_db()->GetAll(
            "SELECT downtime_key, starts_at, ends_at, reason FROM project_company_manufacturing_downtime WHERE company_key_hash=? AND workstation_key=? AND record_status='ACTIVE' AND starts_at<? AND ends_at>? ORDER BY starts_at, x_id",
            [$companyKeyHash, $station['workstation_key'], $toExclusive->format('Y-m-d H:i:s'), $from->format('Y-m-d H:i:s')]
        );
        $downtimeSeconds = 0;
        $entries = [];
        foreach (is_array($downtimes) ? $downtimes : [] as $downtime) {
            $downStart = strtotime((string) $downtime['starts_at']);
            $downEnd = strtotime((string) $downtime['ends_at']);
            $entrySeconds = 0;
            foreach ($workingIntervals as $interval) {
                $entrySeconds += max(0, min($downEnd, $interval['end']) - max($downStart, $interval['start']));
            }
            if ($entrySeconds > 0) {
                $entries[] = ['downtime_key' => (string) $downtime['downtime_key'], 'starts_at' => (string) $downtime['starts_at'], 'ends_at' => (string) $downtime['ends_at'], 'reason' => (string) $downtime['reason'], 'duration_minutes' => intdiv($entrySeconds, 60)];
                $downtimeSeconds += $entrySeconds;
            }
        }
        $workingMinutes = intdiv($workingSeconds, 60);
        $downtimeMinutes = min($workingMinutes, intdiv($downtimeSeconds, 60));
        $utilization = $workingMinutes > 0 ? number_format((($workingMinutes - $downtimeMinutes) / $workingMinutes) * 100, 4, '.', '') : '0.0000';
        $rows[] = [
            'workstation_key' => (string) $station['workstation_key'], 'workstation_code' => (string) $station['workstation_code'],
            'workstation_name' => (string) $station['workstation_name'], 'plant_floor_name' => (string) $station['plant_floor_name'],
            'working_minutes' => $workingMinutes, 'downtime_minutes' => $downtimeMinutes,
            'available_minutes' => max(0, $workingMinutes - $downtimeMinutes), 'utilization_percent' => $utilization,
            'entries' => $entries,
        ];
        $totalWorking += $workingMinutes;
        $totalDowntime += $downtimeMinutes;
    }
    return [
        'report_key' => $reportKey, 'date_from' => $from->format('Y-m-d'), 'date_to' => $to->format('Y-m-d'),
        'rows' => $rows, 'total_working_minutes' => $totalWorking, 'total_downtime_minutes' => $totalDowntime,
        'utilization_percent' => $totalWorking > 0 ? number_format((($totalWorking - $totalDowntime) / $totalWorking) * 100, 4, '.', '') : '0.0000',
    ];
}
