<?php
declare(strict_types=1);

function yovel_admin_operations_dependency_contracts(): array
{
    $contracts = [
        'accounting-finance.bank-sync.v1' => 'command',
        'accounting-finance.company-defaults.v1' => 'read',
        'accounting-finance.currency-directory.v1' => 'read',
        'accounting-finance.currency-exchange.v1' => 'read',
        'accounting-finance.party-account-directory.v1' => 'read',
        'accounting-finance.planning-directory.v1' => 'read',
        'accounting-finance.tax-directory.v1' => 'read',
        'assets-maintenance.company-defaults.v1' => 'read',
        'assets-maintenance.fleet-directory.v1' => 'read',
        'buying-procurement.supplier-directory.v1' => 'read',
        'buying-procurement.supplier-masters.v1' => 'read',
        'buying-procurement.trade-terms.v1' => 'read',
        'hr.calendar-directory.v1' => 'read',
        'hr.employee-group-directory.v1' => 'read',
        'hr.workforce-directory.v1' => 'read',
        'inventory-warehouse.catalog-directory.v1' => 'read',
        'inventory-warehouse.company-defaults.v1' => 'read',
        'inventory-warehouse.unit-directory.v1' => 'read',
        'operations.edi-code-directory.v1' => 'internal',
        'operations.job-runner.v1' => 'internal',
        'owners.bulk-command.v1' => 'command',
        'owners.digest-metrics.v1' => 'read',
        'owners.document-lifecycle-catalog.v1' => 'read',
        'owners.governed-delete.v1' => 'command',
        'owners.governed-rename.v1' => 'command',
        'owners.workspace-directory.v1' => 'read',
        'platform.company-branch-directory.v1' => 'read',
        'platform.company-directory.v1' => 'read',
        'platform.company-scope.v1' => 'read',
        'sales-crm.commercial-masters.v1' => 'read',
        'sales-crm.trade-terms.v1' => 'read',
        'shared.attachment-service.v1' => 'read',
        'shared.audit-log.v1' => 'ui',
        'shared.external-provider-secrets.v1' => 'read',
        'shared.form-builder.v1' => 'ui',
        'shared.identity-address-directory.v1' => 'read',
        'shared.identity-directory.v1' => 'read',
        'shared.identity-permission-directory.v1' => 'read',
        'shared.modal-confirmation.v1' => 'ui',
        'shared.module-routing.v1' => 'ui',
        'shared.portal-publication.v1' => 'command',
        'shared.record-type-directory.v1' => 'read',
        'shared.secret-store.v1' => 'command',
    ];

    return array_map(
        static fn (string $mode): array => ['mode' => $mode, 'version' => 1],
        $contracts
    );
}

function yovel_admin_operations_contract_meta(string $contractId): array
{
    $contractId = strtolower(trim($contractId));
    $contract = yovel_admin_operations_dependency_contracts()[$contractId] ?? null;
    if (!is_array($contract)) {
        throw new InvalidArgumentException('Operations dependency contract is not allow-listed: ' . $contractId);
    }
    return $contract;
}

function yovel_admin_operations_contract_scope(array $company, ?array $admin = null): array
{
    $companyKey = trim((string) ($company['company_key'] ?? ''));
    $companyKeyHash = trim((string) ($company['company_key_hash'] ?? ''));
    if ($companyKey === '' || strlen($companyKey) > 64 || preg_match('/^[a-f0-9]{64}$/i', $companyKeyHash) !== 1) {
        throw new InvalidArgumentException('Operations company scope is invalid.');
    }

    $adminKey = null;
    if ($admin !== null) {
        $adminKey = trim((string) ($admin['admin_key'] ?? ''));
        if (!function_exists('yovel_admin_is_uuid') || !yovel_admin_is_uuid($adminKey)) {
            throw new InvalidArgumentException('Operations administrator scope is invalid.');
        }
    }

    return [$companyKey, strtolower($companyKeyHash), $adminKey];
}

function yovel_admin_operations_provider(string $contractId, array $providers): callable
{
    $provider = $providers[$contractId] ?? null;
    if (!is_callable($provider)) {
        throw new LogicException('Operations dependency provider is unavailable: ' . $contractId);
    }
    return $provider;
}

function yovel_admin_operations_call_read_contract(
    string $contractId,
    array $company,
    array $query,
    array $providers = []
): array {
    $contractId = strtolower(trim($contractId));
    $meta = yovel_admin_operations_contract_meta($contractId);
    if (($meta['mode'] ?? '') !== 'read') {
        throw new InvalidArgumentException('Operations dependency contract is not readable: ' . $contractId);
    }
    [, $companyKeyHash] = yovel_admin_operations_contract_scope($company);
    $result = yovel_admin_operations_provider($contractId, $providers)($company, $query);
    if (!is_array($result)
        || ($result['ok'] ?? false) !== true
        || strtolower(trim((string) ($result['company_key_hash'] ?? ''))) !== $companyKeyHash
        || !is_array($result['records'] ?? null)
        || !is_array($result['errors'] ?? null)
        || ($result['errors'] ?? []) !== []) {
        throw new RuntimeException('Operations dependency read failed company scope or response validation: ' . $contractId);
    }

    return $result;
}

function yovel_admin_operations_call_command_contract(
    string $contractId,
    ADOConnection $db,
    array $company,
    array $admin,
    array $command,
    array $providers = []
): array {
    $contractId = strtolower(trim($contractId));
    $meta = yovel_admin_operations_contract_meta($contractId);
    if (($meta['mode'] ?? '') !== 'command') {
        throw new InvalidArgumentException('Operations dependency contract is not command-capable: ' . $contractId);
    }
    yovel_admin_operations_contract_scope($company, $admin);
    $idempotencyKey = trim((string) ($command['idempotency_key'] ?? ''));
    if ($idempotencyKey === '' || strlen($idempotencyKey) > 160) {
        throw new InvalidArgumentException('Operations owner command requires a bounded idempotency key.');
    }

    $result = yovel_admin_operations_provider($contractId, $providers)($db, $company, $admin, $command);
    $ownerRecordKey = trim((string) ($result['owner_record_key'] ?? ''));
    $readBack = $result['read_back'] ?? null;
    if (!is_array($result)
        || ($result['ok'] ?? false) !== true
        || $ownerRecordKey === ''
        || trim((string) ($result['idempotency_key'] ?? '')) !== $idempotencyKey
        || !is_array($readBack)
        || trim((string) ($readBack['record_key'] ?? $readBack['owner_record_key'] ?? '')) !== $ownerRecordKey
        || trim((string) ($result['audit_key'] ?? '')) === ''
        || !is_array($result['errors'] ?? null)
        || ($result['errors'] ?? []) !== []) {
        throw new RuntimeException('Operations owner command failed read-back or idempotency validation: ' . $contractId);
    }

    return $result;
}

