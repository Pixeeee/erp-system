<?php
declare(strict_types=1);

function yovel_admin_support_service_assert_csrf(array $input): void
{
    $submitted = (string) ($input['csrf'] ?? '');
    if ($submitted === '' || !hash_equals(bx_csrf_token(), $submitted)) {
        throw new InvalidArgumentException('Invalid request token.');
    }
}

function yovel_admin_support_service_issue_action(
    array $company,
    array $admin,
    string $action,
    array $input
): array {
    if ($action === 'support_save_issue_priority') {
        $saved = yovel_admin_save_support_issue_priority($company, $admin, $input);

        return [
            'message' => 'Issue Priority was saved.',
            'section' => 'issues-tickets',
            'query' => ['priority' => (string) $saved['issue_priority_key']],
            'rehydration' => yovel_admin_support_service_rehydration('support-issue-priority', $saved),
        ];
    }
    if ($action === 'support_save_issue_type') {
        $saved = yovel_admin_save_support_issue_type($company, $admin, $input);

        return [
            'message' => 'Issue Type was saved.',
            'section' => 'issues-tickets',
            'query' => ['issue_type' => (string) $saved['issue_type_key']],
            'rehydration' => yovel_admin_support_service_rehydration('support-issue-type', $saved),
        ];
    }
    if ($action === 'support_save_issue') {
        $saved = yovel_admin_save_support_issue($company, $admin, $input);

        return [
            'message' => 'Issue was saved.',
            'section' => 'issues-tickets',
            'query' => ['issue' => (string) $saved['issue_key']],
            'rehydration' => yovel_admin_support_service_rehydration('support-issue', $saved),
        ];
    }

    $issueKey = trim((string) ($input['issue_key'] ?? ''));
    $nextStatus = trim((string) ($input['next_status'] ?? ''));
    $saved = yovel_admin_transition_support_issue($company, $admin, $issueKey, $nextStatus, $input);

    return [
        'message' => 'Issue status was updated.',
        'section' => 'issues-tickets',
        'query' => ['issue' => (string) $saved['issue_key']],
        'rehydration' => yovel_admin_support_service_rehydration('support-issue-transition', $saved),
    ];
}
