<?php
declare(strict_types=1);

require __DIR__ . '/manufacturing-test-helper.php';

$scope = manufacturing_test_create_scope('modal');
try {
    $settings = manufacturing_test_render($scope, 'settings');
    manufacturing_test_assert(str_contains($settings, 'data-record-modal-open="manufacturing-settings-modal"'), 'Settings create/edit command does not open the record modal.');
    manufacturing_test_assert(str_contains($settings, 'data-record-modal-form'), 'Manufacturing record modal form marker is missing.');
    manufacturing_test_assert(str_contains($settings, 'data-confirm-submit'), 'Manufacturing modal does not adopt shared confirmation.');
    manufacturing_test_assert(str_contains($settings, 'name="module_view" value="manufacturing"'), 'Manufacturing modal does not identify its module route.');
    manufacturing_test_assert(str_contains($settings, 'name="action" value="save_manufacturing_settings"'), 'Manufacturing settings action is missing.');
    manufacturing_test_assert(str_contains($settings, 'data-confirm-dialog'), 'Shared sibling confirmation dialog is missing.');
    manufacturing_test_assert(strpos($settings, 'data-record-modal') < strpos($settings, 'data-confirm-dialog'), 'Confirmation dialog must be a sibling after the record modal.');

    $state = [
        'section' => 'settings',
        'action' => 'save_manufacturing_settings',
        'input' => [
            'allow_overproduction_percent' => '17.5000',
            'capacity_planning_enabled' => '1',
            'notes' => 'Retained after validation',
        ],
        'error' => 'Controller retained validation error.',
    ];
    $rehydrated = manufacturing_test_render($scope, 'settings', $state);
    manufacturing_test_assert(str_contains($rehydrated, 'data-record-modal-open-on-load'), 'Failed Manufacturing form does not reopen from server state.');
    manufacturing_test_assert(str_contains($rehydrated, 'Controller retained validation error.'), 'Manufacturing failure feedback was not rehydrated.');
    manufacturing_test_assert(str_contains($rehydrated, 'value="17.5000"'), 'Manufacturing valid input was not retained after failure.');
    manufacturing_test_assert(str_contains($rehydrated, 'Retained after validation'), 'Manufacturing notes were not retained after failure.');

    $script = (string) file_get_contents(dirname(__DIR__) . '/company/admin/assets/js/admin-modal.js');
    manufacturing_test_assert(str_contains($script, 'event.preventDefault()'), 'Shared confirmation does not stop the first submit.');
    manufacturing_test_assert(str_contains($script, 'form.requestSubmit()'), 'Shared confirmation does not replay native submit.');
    manufacturing_test_assert(str_contains($script, 'form.dataset.confirmed'), 'Shared confirmation one-shot guard is missing.');
    manufacturing_test_assert(str_contains($script, 'pendingSubmitter?.focus()'), 'Shared confirmation does not restore focus on cancel.');
    manufacturing_test_assert(str_contains($script, "event.key === 'Escape'"), 'Shared confirmation does not support Escape.');
} finally {
    manufacturing_test_cleanup_scope($scope);
}

echo "Manufacturing modal confirmation tests passed.\n";
