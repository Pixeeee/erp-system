<?php
declare(strict_types=1);

function yovel_admin_compliance_dashboard_href(string $section, array $query = []): string
{
    $parameters = ['view' => 'compliance-localization', 'section' => $section] + $query;
    return './?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

function yovel_admin_compliance_dashboard_count(ADOConnection $db, string $sql, array $parameters): int
{
    $value = $db->GetOne($sql, $parameters);
    if ($value === false) {
        throw new RuntimeException('Compliance dashboard count is unavailable.');
    }
    return (int) $value;
}

function yovel_admin_compliance_dashboard_rows(ADOConnection $db, string $sql, array $parameters): array
{
    $rows = $db->GetAll($sql, $parameters);
    if ($rows === false) {
        throw new RuntimeException('Compliance dashboard records are unavailable.');
    }
    return is_array($rows) ? $rows : [];
}

function yovel_admin_compliance_dashboard_evidence_valid(array $row): bool
{
    $metadata = null;
    try {
        $decoded = json_decode((string) ($row['metadata_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        $metadata = is_array($decoded) ? $decoded : null;
    } catch (JsonException) {
        $metadata = null;
    }
    if ($metadata === null
        || !hash_equals((string) ($row['metadata_sha256'] ?? ''), yovel_admin_compliance_checksum($metadata))) {
        return false;
    }

    $root = yovel_admin_compliance_evidence_storage_root();
    $path = (string) ($row['storage_path'] ?? '');
    $expectedHash = (string) ($row['sha256'] ?? '');
    $actualHash = yovel_admin_compliance_generated_path($path, $root) && is_file($path)
        ? hash_file('sha256', $path)
        : false;
    return is_string($actualHash) && preg_match('/^[a-f0-9]{64}$/', $expectedHash) === 1 && hash_equals($expectedHash, $actualHash);
}

function yovel_admin_compliance_dashboard_audit_label(string $module): string
{
    return match ($module) {
        'project_company_compliance_rule_set' => 'Rule set',
        'project_company_compliance_tax_account_map' => 'Finance mapping',
        'project_company_compliance_evidence' => 'Evidence',
        'project_company_compliance_retention_hold' => 'Legal hold',
        'project_company_compliance_approval' => 'Approval',
        default => 'Compliance record',
    };
}

function yovel_admin_compliance_localization_dashboard_data(array $company, array $admin): array
{
    [, $companyHash, $adminKey] = yovel_admin_compliance_scope($company, $admin);
    $db = bx_db();
    $today = new DateTimeImmutable('today');
    $retentionHorizon = $today->modify('+90 days')->format('Y-m-d');
    $providers = is_array($GLOBALS['yovel_admin_compliance_dependency_providers'] ?? null)
        ? $GLOBALS['yovel_admin_compliance_dependency_providers']
        : [];
    $financeAvailable = is_callable($providers['accounting-finance.account-reference.v1'] ?? null);
    $queryErrors = [];

    $metric = static function (
        string $key,
        string $label,
        string $href,
        callable $read
    ) use (&$queryErrors): array {
        try {
            return [
                'key' => $key,
                'label' => $label,
                'value' => (int) $read(),
                'availability' => 'AVAILABLE',
                'href' => $href,
            ];
        } catch (Throwable) {
            $queryErrors[$key] = $label;
            return [
                'key' => $key,
                'label' => $label,
                'value' => null,
                'availability' => 'ERROR',
                'href' => $href,
            ];
        }
    };

    $summary = [];
    $summary[] = $metric(
        'active-rule-sets',
        'Active rule sets',
        yovel_admin_compliance_dashboard_href('tax-rules'),
        static fn (): int => yovel_admin_compliance_dashboard_count(
            $db,
            "SELECT COUNT(*) FROM project_company_compliance_rule_set
             WHERE company_key_hash = ? AND jurisdiction_code = 'PH' AND version_status = 'APPROVED'
               AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)",
            [$companyHash, $today->format('Y-m-d'), $today->format('Y-m-d')]
        )
    );
    $summary[] = $metric(
        'pending-approvals',
        'Pending approvals',
        yovel_admin_compliance_dashboard_href('tax-rules'),
        static fn (): int => yovel_admin_compliance_dashboard_count(
            $db,
            "SELECT COUNT(*) FROM project_company_compliance_approval
             WHERE company_key_hash = ? AND decision_status = 'PENDING'",
            [$companyHash]
        )
    );
    $summary[] = $metric(
        'evidence-retention-due',
        'Evidence retention due',
        yovel_admin_compliance_dashboard_href('audit-evidence'),
        static fn (): int => yovel_admin_compliance_dashboard_count(
            $db,
            "SELECT COUNT(*) FROM project_company_compliance_evidence
             WHERE company_key_hash = ? AND evidence_status IN ('ACTIVE','HELD')
               AND retention_until IS NOT NULL AND retention_until <= ?",
            [$companyHash, $retentionHorizon]
        )
    );

    $evidenceRows = [];
    $invalidEvidenceRows = [];
    try {
        $evidenceRows = yovel_admin_compliance_dashboard_rows(
            $db,
            "SELECT evidence_key, original_file_name, storage_path, sha256, metadata_json,
                    metadata_sha256, evidence_status, retention_until, created_at
             FROM project_company_compliance_evidence
             WHERE company_key_hash = ? AND evidence_status IN ('ACTIVE','HELD')
             ORDER BY created_at DESC, evidence_key DESC LIMIT 501",
            [$companyHash]
        );
        if (count($evidenceRows) > 500) {
            throw new OverflowException('Evidence integrity scan exceeded its bounded dashboard window.');
        }
        foreach ($evidenceRows as $row) {
            if (!yovel_admin_compliance_dashboard_evidence_valid($row)) {
                $invalidEvidenceRows[] = $row;
            }
        }
        $summary[] = [
            'key' => 'invalid-evidence',
            'label' => 'Invalid evidence',
            'value' => count($invalidEvidenceRows),
            'availability' => 'AVAILABLE',
            'href' => yovel_admin_compliance_dashboard_href('audit-evidence'),
        ];
    } catch (Throwable) {
        $queryErrors['invalid-evidence'] = 'Invalid evidence';
        $evidenceRows = [];
        $invalidEvidenceRows = [];
        $summary[] = [
            'key' => 'invalid-evidence',
            'label' => 'Invalid evidence',
            'value' => null,
            'availability' => 'ERROR',
            'href' => yovel_admin_compliance_dashboard_href('audit-evidence'),
        ];
    }

    $summary[] = $metric(
        'active-legal-holds',
        'Active legal holds',
        yovel_admin_compliance_dashboard_href('audit-evidence'),
        static fn (): int => yovel_admin_compliance_dashboard_count(
            $db,
            "SELECT COUNT(*) FROM project_company_compliance_retention_hold
             WHERE company_key_hash = ? AND hold_status = 'ACTIVE'",
            [$companyHash]
        )
    );

    $mappingGapSql = "FROM project_company_compliance_rule_set rule_record
        LEFT JOIN project_company_compliance_tax_account_map map_record
          ON map_record.company_key_hash = rule_record.company_key_hash
         AND map_record.rule_set_key = rule_record.rule_set_key
         AND map_record.mapping_status = 'ACTIVE'
       WHERE rule_record.company_key_hash = ?
         AND rule_record.jurisdiction_code = 'PH'
         AND rule_record.rule_type = 'VAT_SETTINGS'
         AND rule_record.version_status IN ('DRAFT','APPROVED')
       GROUP BY rule_record.rule_set_key, rule_record.rule_name, rule_record.version_status
      HAVING SUM(CASE WHEN map_record.tax_role = 'OUTPUT_VAT' THEN 1 ELSE 0 END) = 0
          OR SUM(CASE WHEN map_record.tax_role = 'INPUT_VAT' THEN 1 ELSE 0 END) = 0";
    $summary[] = $metric(
        'mapping-gaps',
        'Finance mapping gaps',
        yovel_admin_compliance_dashboard_href('vat-settings'),
        static function () use ($db, $companyHash, $mappingGapSql): int {
            return yovel_admin_compliance_dashboard_count(
                $db,
                'SELECT COUNT(*) FROM (SELECT rule_record.rule_set_key ' . $mappingGapSql . ') mapping_gap_rows',
                [$companyHash]
            );
        }
    );

    $pendingRows = [];
    try {
        $pendingRows = yovel_admin_compliance_dashboard_rows(
            $db,
            "SELECT approval.approval_key, approval.record_key, approval.approval_action,
                    approval.requested_at, rule_record.rule_name
             FROM project_company_compliance_approval approval
             LEFT JOIN project_company_compliance_rule_set rule_record
               ON rule_record.company_key_hash = approval.company_key_hash
              AND rule_record.rule_set_key = approval.record_key
             WHERE approval.company_key_hash = ? AND approval.decision_status = 'PENDING'
             ORDER BY approval.requested_at, approval.approval_key LIMIT 12",
            [$companyHash]
        );
    } catch (Throwable) {
        $queryErrors['pending-approval-queue'] = 'Pending approval queue';
    }

    $gapRows = [];
    try {
        $gapRows = yovel_admin_compliance_dashboard_rows(
            $db,
            'SELECT rule_record.rule_set_key, rule_record.rule_name, rule_record.version_status ' . $mappingGapSql .
            ' ORDER BY rule_record.rule_name, rule_record.rule_set_key LIMIT 12',
            [$companyHash]
        );
    } catch (Throwable) {
        $queryErrors['mapping-gap-queue'] = 'Mapping gap queue';
    }

    $holdRows = [];
    try {
        $holdRows = yovel_admin_compliance_dashboard_rows(
            $db,
            "SELECT hold_record.retention_hold_key, hold_record.evidence_key, hold_record.hold_reference,
                    hold_record.placed_at, evidence.original_file_name
             FROM project_company_compliance_retention_hold hold_record
             LEFT JOIN project_company_compliance_evidence evidence
               ON evidence.company_key_hash = hold_record.company_key_hash
              AND evidence.evidence_key = hold_record.evidence_key
             WHERE hold_record.company_key_hash = ? AND hold_record.hold_status = 'ACTIVE'
             ORDER BY hold_record.placed_at, hold_record.retention_hold_key LIMIT 12",
            [$companyHash]
        );
    } catch (Throwable) {
        $queryErrors['legal-hold-queue'] = 'Legal hold queue';
    }

    $queue = [];
    foreach ($pendingRows as $row) {
        $queue[] = [
            'key' => 'approval-' . (string) $row['approval_key'],
            'label' => 'Review ' . ((string) ($row['rule_name'] ?? '') !== '' ? (string) $row['rule_name'] : 'Compliance rule'),
            'status' => 'PENDING_APPROVAL',
            'href' => yovel_admin_compliance_dashboard_href('tax-rules', ['rule' => (string) $row['record_key']]),
        ];
    }
    foreach (array_slice($invalidEvidenceRows, 0, 12) as $row) {
        $queue[] = [
            'key' => 'evidence-' . (string) $row['evidence_key'],
            'label' => 'Verify ' . ((string) ($row['original_file_name'] ?? '') !== '' ? (string) $row['original_file_name'] : 'retained evidence'),
            'status' => 'INVALID_EVIDENCE',
            'href' => yovel_admin_compliance_dashboard_href('audit-evidence', ['evidence' => (string) $row['evidence_key']]),
        ];
    }
    foreach ($gapRows as $row) {
        $queue[] = [
            'key' => 'mapping-' . (string) $row['rule_set_key'],
            'label' => 'Complete mappings for ' . (string) $row['rule_name'],
            'status' => 'MAPPING_GAP',
            'href' => yovel_admin_compliance_dashboard_href('vat-settings', ['rule' => (string) $row['rule_set_key']]),
        ];
    }
    foreach ($holdRows as $row) {
        $queue[] = [
            'key' => 'hold-' . (string) $row['retention_hold_key'],
            'label' => 'Review hold ' . (string) $row['hold_reference'],
            'status' => 'LEGAL_HOLD',
            'href' => yovel_admin_compliance_dashboard_href('audit-evidence', ['evidence' => (string) $row['evidence_key']]),
        ];
    }
    $queue = array_slice($queue, 0, 30);

    $activity = [];
    try {
        $auditRows = yovel_admin_compliance_dashboard_rows(
            $db,
            "SELECT x_id, action, module, record_key, new_values, created_at
             FROM builder_audit_log
             WHERE module LIKE 'project_company_compliance_%' AND new_values LIKE ?
             ORDER BY x_id DESC LIMIT 24",
            ['%' . $companyHash . '%']
        );
        foreach ($auditRows as $row) {
            $values = json_decode((string) ($row['new_values'] ?? ''), true);
            if (!is_array($values) || !hash_equals($companyHash, (string) ($values['company_key_hash'] ?? ''))) {
                continue;
            }
            $actor = '';
            foreach (['admin_key', 'placed_by_admin_key', 'released_by_admin_key'] as $actorField) {
                if (isset($values[$actorField]) && is_string($values[$actorField])) {
                    $actor = $values[$actorField];
                    break;
                }
            }
            $recordKey = (string) ($row['record_key'] ?? '');
            $activity[] = [
                'key' => 'audit-' . (string) $row['x_id'],
                'action' => strtoupper((string) $row['action']),
                'record_label' => yovel_admin_compliance_dashboard_audit_label((string) $row['module']) . ($recordKey !== '' ? ' ' . substr($recordKey, 0, 8) : ''),
                'actor_label' => $actor !== '' && hash_equals($adminKey, $actor) ? 'Current administrator' : 'Company administrator',
                'status' => 'RECORDED',
                'occurred_at' => (string) $row['created_at'],
            ];
            if (count($activity) === 12) {
                break;
            }
        }
    } catch (Throwable) {
        $queryErrors['recent-activity'] = 'Recent activity';
    }

    $summaryByKey = array_column($summary, null, 'key');
    $availableValue = static function (string $key) use ($summaryByKey): ?int {
        $item = $summaryByKey[$key] ?? null;
        return is_array($item) && ($item['availability'] ?? '') === 'AVAILABLE' ? (int) $item['value'] : null;
    };
    $evidenceCount = null;
    try {
        $evidenceCount = yovel_admin_compliance_dashboard_count(
            $db,
            "SELECT COUNT(*) FROM project_company_compliance_evidence
             WHERE company_key_hash = ? AND evidence_status IN ('ACTIVE','HELD')",
            [$companyHash]
        );
    } catch (Throwable) {
        $queryErrors['evidence-setup'] = 'Evidence setup';
    }
    $activeRules = $availableValue('active-rule-sets');
    $pendingApprovals = $availableValue('pending-approvals');
    $mappingGaps = $availableValue('mapping-gaps');
    $invalidEvidence = $availableValue('invalid-evidence');
    $setup = [
        ['key' => 'approved-rules', 'label' => 'Approve an effective Philippine rule set', 'complete' => $activeRules !== null && $activeRules > 0, 'href' => yovel_admin_compliance_dashboard_href('tax-rules')],
        ['key' => 'maker-checker', 'label' => 'Clear maker-checker decisions', 'complete' => $pendingApprovals === 0, 'href' => yovel_admin_compliance_dashboard_href('tax-rules')],
        ['key' => 'finance-mappings', 'label' => 'Complete VAT account mappings', 'complete' => $activeRules !== null && $activeRules > 0 && $mappingGaps === 0, 'href' => yovel_admin_compliance_dashboard_href('vat-settings')],
        ['key' => 'verified-evidence', 'label' => 'Retain checksum-valid evidence', 'complete' => $evidenceCount !== null && $evidenceCount > 0 && $invalidEvidence === 0, 'href' => yovel_admin_compliance_dashboard_href('audit-evidence')],
        ['key' => 'finance-contract', 'label' => 'Connect the Finance account-reference service', 'complete' => $financeAvailable, 'href' => yovel_admin_compliance_dashboard_href('vat-settings')],
    ];

    $alerts = [];
    if (!$financeAvailable) {
        $alerts[] = ['key' => 'finance-unavailable', 'severity' => 'WARNING', 'label' => 'Finance account-reference service is unavailable.', 'href' => yovel_admin_compliance_dashboard_href('vat-settings')];
    }
    if ($invalidEvidence !== null && $invalidEvidence > 0) {
        $alerts[] = ['key' => 'invalid-evidence', 'severity' => 'CRITICAL', 'label' => $invalidEvidence . ' retained evidence item(s) failed integrity verification.', 'href' => yovel_admin_compliance_dashboard_href('audit-evidence')];
    }
    $retentionDue = $availableValue('evidence-retention-due');
    if ($retentionDue !== null && $retentionDue > 0) {
        $alerts[] = ['key' => 'retention-due', 'severity' => 'WARNING', 'label' => $retentionDue . ' evidence item(s) are at or within the 90-day retention horizon.', 'href' => yovel_admin_compliance_dashboard_href('audit-evidence')];
    }
    $activeHolds = $availableValue('active-legal-holds');
    if ($activeHolds !== null && $activeHolds > 0) {
        $alerts[] = ['key' => 'legal-holds', 'severity' => 'INFO', 'label' => $activeHolds . ' legal hold(s) suspend evidence disposal.', 'href' => yovel_admin_compliance_dashboard_href('audit-evidence')];
    }
    foreach ($queryErrors as $key => $label) {
        $alerts[] = ['key' => 'query-' . $key, 'severity' => 'ERROR', 'label' => $label . ' is temporarily unavailable.', 'href' => yovel_admin_compliance_dashboard_href('dashboard')];
    }

    return [
        'summary' => $summary,
        'queue' => $queue,
        'activity' => $activity,
        'setup' => $setup,
        'alerts' => array_slice($alerts, 0, 12),
        'shortcuts' => [
            ['key' => 'tax-rules', 'label' => 'Tax Rules', 'href' => yovel_admin_compliance_dashboard_href('tax-rules'), 'available' => true],
            ['key' => 'vat-settings', 'label' => 'VAT Settings', 'href' => yovel_admin_compliance_dashboard_href('vat-settings'), 'available' => true],
            ['key' => 'audit-evidence', 'label' => 'Audit Evidence', 'href' => yovel_admin_compliance_dashboard_href('audit-evidence'), 'available' => true],
            ['key' => 'form-builder', 'label' => 'Form Builder', 'href' => yovel_admin_compliance_dashboard_href('form-builder'), 'available' => true],
        ],
        'directories' => [
            [
                'group' => 'Controls',
                'items' => [
                    ['key' => 'rules-directory', 'label' => 'Effective rule versions', 'href' => yovel_admin_compliance_dashboard_href('tax-rules'), 'available' => true],
                    ['key' => 'mappings-directory', 'label' => 'Finance account mappings', 'href' => yovel_admin_compliance_dashboard_href('vat-settings'), 'available' => true],
                    ['key' => 'evidence-directory', 'label' => 'Evidence and legal holds', 'href' => yovel_admin_compliance_dashboard_href('audit-evidence'), 'available' => true],
                ],
            ],
            [
                'group' => 'Registers and filings',
                'items' => [
                    ['key' => 'einvoice-register', 'label' => 'E-Invoice Register', 'href' => null, 'available' => false, 'status' => 'NOT_IMPLEMENTED'],
                    ['key' => 'vat-returns', 'label' => 'VAT Returns', 'href' => null, 'available' => false, 'status' => 'NOT_IMPLEMENTED'],
                    ['key' => 'regulatory-exports', 'label' => 'Regulatory Exports', 'href' => null, 'available' => false, 'status' => 'NOT_IMPLEMENTED'],
                ],
            ],
        ],
        'dependencies' => [[
            'contract' => 'accounting-finance.account-reference.v1',
            'status' => $financeAvailable ? 'AVAILABLE' : 'UNAVAILABLE_DEPENDENCY',
        ]],
    ];
}
