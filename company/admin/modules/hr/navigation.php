<?php
declare(strict_types=1);

function yovel_admin_hr_sections(): array
{
    return [
        'dashboard' => ['label' => 'HR Dashboard', 'icon' => '▦', 'description' => 'Manage HR setup, shortcuts, reports, and custom form builder workspace.'],
        'employee-profiles' => ['label' => 'Employee profiles', 'icon' => '♙', 'description' => 'Create and maintain Yovel East employee master records.'],
        'departments' => ['label' => 'Departments', 'icon' => '▧', 'description' => 'Use company branch departments as HR assignment units.'],
        'job-positions' => ['label' => 'Job positions', 'icon' => '◇', 'description' => 'Define employee designations and position records.'],
        'teams' => ['label' => 'Teams', 'icon' => '☷', 'description' => 'Group employees into operational HR teams.'],
        'attendance' => ['label' => 'Attendance', 'icon' => '◷', 'description' => 'Track daily employee attendance records.'],
        'leave-requests' => ['label' => 'Leave requests', 'icon' => '◴', 'description' => 'Capture employee leave applications.'],
        'leave-approvals' => ['label' => 'Leave approvals', 'icon' => '✓', 'description' => 'Review and approve leave requests.'],
        'payroll-access' => ['label' => 'Payroll access', 'icon' => '◈', 'description' => 'Control who can view payroll-adjacent HR data.'],
        'recruitment' => ['label' => 'Recruitment', 'icon' => '＋', 'description' => 'Manage openings and applicants.'],
        'onboarding' => ['label' => 'Onboarding', 'icon' => '↳', 'description' => 'Prepare new hires for work.'],
        'employee-documents' => ['label' => 'Employee documents', 'icon' => '▤', 'description' => 'Track employee records and file requirements.'],
        'hr-reports' => ['label' => 'HR reports', 'icon' => '▦', 'description' => 'Review HR operational reports.'],
    ];
}

function yovel_admin_hr_section(): string
{
    $section = yovel_admin_slug((string) ($_GET['section'] ?? 'dashboard'));
    return array_key_exists($section, yovel_admin_hr_sections()) ? $section : 'dashboard';
}

function yovel_admin_hr_feature_href(array $group, string $feature): string
{
    return yovel_admin_erp_feature_href($group, $feature);
}
