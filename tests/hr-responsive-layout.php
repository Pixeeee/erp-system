<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function hr_responsive_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hr_responsive_source(string $path): string
{
    hr_responsive_assert(is_file($path), 'Required responsive contract file is missing: ' . $path);
    $source = file_get_contents($path);
    hr_responsive_assert(is_string($source), 'Unable to read responsive contract file: ' . $path);
    return $source;
}

$approvedDesktopGrid = 'xl:grid-cols-[minmax(0,12fr)_minmax(16rem,8fr)]';
$workspaceViews = [
    'employee-profiles.php',
    'departments.php',
    'job-positions.php',
    'teams.php',
];

$layoutViolations = [];
foreach ($workspaceViews as $view) {
    $source = hr_responsive_source($root . '/company/admin/modules/hr/views/' . $view);
    hr_responsive_assert(str_contains($source, 'yovel-hr-two-panel'), $view . ' no longer uses the shared two-panel shell.');
    hr_responsive_assert(str_contains($source, 'yovel-hr-approved-layout'), $view . ' does not opt into the HR-owned responsive layout contract.');
    if (!str_contains($source, $approvedDesktopGrid)) {
        $layoutViolations[] = $view;
    }
    hr_responsive_assert(strpos($source, '<section') < strpos($source, '<aside'), $view . ' does not keep the main panel before the tools panel for mobile stacking.');
}
hr_responsive_assert($layoutViolations === [], 'HR views missing the approved 12/20 main and 8/20 tools grid: ' . implode(', ', $layoutViolations));

foreach (['employee-profiles.php', 'departments.php'] as $view) {
    $source = hr_responsive_source($root . '/company/admin/modules/hr/views/' . $view);
    hr_responsive_assert(str_contains($source, 'yovel-hr-mobile-table-scroll'), $view . ' does not contain its wide table on mobile.');
}

$workspace = hr_responsive_source($root . '/company/admin/modules/hr/views/workspace.php');
hr_responsive_assert(str_contains($workspace, '.yovel-hr-approved-layout'), 'The HR-owned responsive layout override is missing.');
hr_responsive_assert(str_contains($workspace, 'minmax(0, 12fr) minmax(16rem, 8fr)'), 'The HR-owned desktop override is not the approved 12/8 composition.');
hr_responsive_assert(str_contains($workspace, 'grid-column: auto'), 'Legacy 8/4 child spans are not neutralized for the approved HR grid.');
hr_responsive_assert(str_contains($workspace, 'min-width: 0'), 'HR grid items can still expand the mobile viewport.');
hr_responsive_assert(str_contains($workspace, 'position: relative'), 'Accessible table labels are not anchored to the mobile scroll container.');

$attendance = hr_responsive_source($root . '/company/admin/modules/hr/views/attendance.php');
hr_responsive_assert(str_contains($attendance, 'yovel-hr-attendance-layout'), 'Shift & Attendance does not use its module-local responsive shell.');
hr_responsive_assert(str_contains($attendance, 'grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr)'), 'Shift & Attendance is not the approved 12/8 desktop composition.');
hr_responsive_assert(str_contains($attendance, 'grid-template-columns: minmax(0, 1fr)'), 'Shift & Attendance does not stack into one bounded mobile column.');
hr_responsive_assert(str_contains($attendance, 'yovel-hr-attendance-scroll'), 'Shift & Attendance does not contain its wide list table on mobile.');
hr_responsive_assert(strpos($attendance, '<section') < strpos($attendance, '<aside'), 'Shift & Attendance does not keep the main panel first when stacked.');

$css = hr_responsive_source($root . '/company/admin/assets/css/admin.css');
hr_responsive_assert(str_contains($css, '@media (max-width: 1279px)'), 'The HR responsive breakpoint is missing.');
hr_responsive_assert(str_contains($css, '.yovel-hr-two-panel'), 'The HR two-panel responsive selector is missing.');
hr_responsive_assert(str_contains($css, 'min-height: auto;'), 'The stacked HR shell does not release its desktop height.');
hr_responsive_assert(str_contains($css, 'max-height: none;'), 'Stacked HR panels can remain clipped at mobile widths.');

echo "HR responsive 12/8 layout checks passed.\n";
