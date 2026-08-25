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

    $operations = manufacturing_test_render($scope, 'operations');
    foreach (['manufacturing-operation-modal', 'manufacturing-routing-modal'] as $modalId) {
        manufacturing_test_assert(str_contains($operations, 'data-record-modal-open="' . $modalId . '"'), 'Operations Add command does not open ' . $modalId . '.');
        manufacturing_test_assert(str_contains($operations, 'id="' . $modalId . '"'), 'Operations shared modal is missing ' . $modalId . '.');
    }
    manufacturing_test_assert(substr_count($operations, 'data-confirm-submit') >= 2, 'Every Operations write must adopt shared confirmation.');
    manufacturing_test_assert(str_contains($operations, 'name="sub_operations"') && str_contains($operations, 'name="operations"'), 'Operations and Routing ordered child inputs are missing.');

    $workstations = manufacturing_test_render($scope, 'workstations');
    foreach (['manufacturing-workstation-type-modal', 'manufacturing-plant-floor-modal', 'manufacturing-workstation-modal', 'manufacturing-downtime-modal'] as $modalId) {
        manufacturing_test_assert(str_contains($workstations, 'data-record-modal-open="' . $modalId . '"'), 'Workstations Add command does not open ' . $modalId . '.');
        manufacturing_test_assert(str_contains($workstations, 'id="' . $modalId . '"'), 'Workstations shared modal is missing ' . $modalId . '.');
    }
    manufacturing_test_assert(substr_count($workstations, 'data-confirm-submit') >= 4, 'Every Workstations write must adopt shared confirmation.');
    manufacturing_test_assert(str_contains($workstations, 'name="working_hours"') && str_contains($workstations, 'name="costs"') && str_contains($workstations, 'name="operating_components"'), 'Workstation calendar and cost child inputs are missing.');
    manufacturing_test_assert(str_contains($workstations, 'data-manufacturing-plant-floor'), 'Read-only visual Plant Floor projection is missing.');
    manufacturing_test_assert(strpos($workstations, 'data-record-modal') < strpos($workstations, 'data-confirm-dialog'), 'WP-03 confirmation dialog must remain a sibling after record modals.');

    $operationState = [
        'section' => 'operations', 'action' => 'save_manufacturing_operation',
        'input' => ['operation_code' => 'RETAIN', 'operation_name' => 'Retained operation', 'hourly_rate' => '45.000000'],
        'error' => 'Retained operation validation error.',
    ];
    $operationRehydrated = manufacturing_test_render($scope, 'operations', $operationState);
    manufacturing_test_assert(str_contains($operationRehydrated, 'data-record-modal-open-on-load') && str_contains($operationRehydrated, 'value="RETAIN"'), 'Failed Operation write is not rehydrated into its modal.');
    manufacturing_test_assert(str_contains($operationRehydrated, 'Retained operation validation error.'), 'Failed Operation feedback is not rendered in its modal.');
} finally {
    manufacturing_test_cleanup_scope($scope);
}

echo "Manufacturing modal confirmation tests passed.\n";
