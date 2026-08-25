<?php
declare(strict_types=1);

function erpnext_parity_read_json(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Parity JSON file was not found: ' . $path);
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Parity JSON file could not be read: ' . $path);
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Parity JSON root must be an object: ' . $path);
    }

    return $decoded;
}

function erpnext_parity_load_baseline(string $path): array
{
    $baseline = erpnext_parity_read_json($path);
    if (($baseline['schema_version'] ?? '') !== 'builderx.erpnext-parity-baseline.v1') {
        throw new RuntimeException('Unsupported ERPNext parity baseline schema.');
    }

    foreach (['erpnext', 'hrms'] as $repositoryKey) {
        $repository = $baseline[$repositoryKey] ?? null;
        if (!is_array($repository)) {
            throw new RuntimeException('Parity baseline is missing repository: ' . $repositoryKey);
        }
        foreach (['repository', 'branch', 'commit', 'license'] as $field) {
            if (trim((string) ($repository[$field] ?? '')) === '') {
                throw new RuntimeException("Parity baseline {$repositoryKey} is missing {$field}.");
            }
        }
        if (preg_match('/^[0-9a-f]{40}$/', (string) $repository['commit']) !== 1) {
            throw new RuntimeException("Parity baseline {$repositoryKey} commit is not a full Git hash.");
        }
    }

    return $baseline;
}

function erpnext_parity_load_owners(string $path): array
{
    $owners = erpnext_parity_read_json($path);
    if (($owners['schema_version'] ?? '') !== 'builderx.erpnext-parity-owners.v1') {
        throw new RuntimeException('Unsupported ERPNext parity owner schema.');
    }
    if (!isset($owners['owners']) || !is_array($owners['owners'])) {
        throw new RuntimeException('Parity owner manifest does not contain owners.');
    }

    foreach ($owners['owners'] as $ownerKey => $owner) {
        if (!is_string($ownerKey) || trim($ownerKey) === '' || !is_array($owner)) {
            throw new RuntimeException('Parity owner manifest contains an invalid owner entry.');
        }
        if (preg_match('/^[0-9a-f-]{36}$/', (string) ($owner['thread_id'] ?? '')) !== 1) {
            throw new RuntimeException("Parity owner {$ownerKey} has an invalid task identifier.");
        }
        if (!isset($owner['source_modules'], $owner['write_paths']) || !is_array($owner['source_modules']) || !is_array($owner['write_paths'])) {
            throw new RuntimeException("Parity owner {$ownerKey} is missing source modules or write paths.");
        }
    }

    return $owners;
}

function erpnext_parity_validate_ledger(array $rows, array $owners): array
{
    $errors = [];
    $allowedStatuses = ['MISSING', 'PARTIAL', 'COMPLETE', 'NOT_APPLICABLE', 'DEFERRED'];
    $requiredFields = [
        'source_id',
        'source_repository',
        'source_commit',
        'source_path',
        'source_kind',
        'module_owner',
        'builderx_target',
        'dependencies',
        'status',
        'evidence',
        'reason',
    ];
    $knownOwners = is_array($owners['owners'] ?? null) ? $owners['owners'] : [];
    $sourceIds = [];

    foreach (array_values($rows) as $index => $row) {
        $label = 'Ledger row ' . ($index + 1);
        if (!is_array($row)) {
            $errors[] = $label . ' must be an object.';
            continue;
        }

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $row)) {
                $errors[] = "{$label} is missing {$field}.";
            }
        }

        $sourceId = trim((string) ($row['source_id'] ?? ''));
        if ($sourceId === '') {
            $errors[] = $label . ' has an empty source_id.';
        } elseif (isset($sourceIds[$sourceId])) {
            $errors[] = $label . ' has duplicate source_id ' . $sourceId . '.';
        } else {
            $sourceIds[$sourceId] = true;
        }

        foreach (['source_repository', 'source_commit', 'source_path', 'source_kind', 'module_owner', 'builderx_target'] as $field) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                $errors[] = "{$label} has an empty {$field}.";
            }
        }
        if (preg_match('/^[0-9a-f]{40}$/', (string) ($row['source_commit'] ?? '')) !== 1) {
            $errors[] = $label . ' source_commit is not a full Git hash.';
        }

        $moduleOwner = (string) ($row['module_owner'] ?? '');
        if ($moduleOwner !== '' && !array_key_exists($moduleOwner, $knownOwners)) {
            $errors[] = $label . ' references unknown module owner ' . $moduleOwner . '.';
        }
        if (!is_array($row['dependencies'] ?? null)) {
            $errors[] = $label . ' dependencies must be an array.';
        }
        if (!is_array($row['evidence'] ?? null)) {
            $errors[] = $label . ' evidence must be an array.';
        }

        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = $label . ' has unsupported status ' . ($status !== '' ? $status : '(empty)') . '.';
        }
        if ($status === 'COMPLETE' && ($row['evidence'] ?? []) === []) {
            $errors[] = $label . ' cannot be COMPLETE without evidence.';
        }
        if ($status === 'NOT_APPLICABLE' && trim((string) ($row['reason'] ?? '')) === '') {
            $errors[] = $label . ' cannot be NOT_APPLICABLE without a reason.';
        }
        if ($status === 'DEFERRED') {
            $reason = trim((string) ($row['reason'] ?? ''));
            $androidSource = preg_match('/(?:android|mobile)/i', $sourceId . ' ' . (string) ($row['source_path'] ?? '')) === 1;
            if ($reason !== 'Approved Android exclusion' || !$androidSource) {
                $errors[] = $label . ' may be DEFERRED only with reason Approved Android exclusion for Android or mobile work.';
            }
        }
    }

    return $errors;
}

function erpnext_parity_artifact_kind(string $relativePath): ?string
{
    $path = '/' . strtolower(str_replace('\\', '/', $relativePath));
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($extension === 'py' && str_contains($path, '/report/') && basename($path) !== '__init__.py') {
        return 'report_code';
    }
    if ($extension !== 'json') {
        return null;
    }

    $kinds = [
        '/doctype/' => 'doctype',
        '/workspace/' => 'workspace',
        '/report/' => 'report',
        '/page/' => 'page',
        '/print_format/' => 'print_format',
        '/dashboard/' => 'dashboard',
        '/notification/' => 'notification',
        '/workflow/' => 'workflow',
    ];
    foreach ($kinds as $needle => $kind) {
        if (str_contains($path, $needle)) {
            return $kind;
        }
    }

    return null;
}

function erpnext_parity_title_from_path(string $relativePath): string
{
    $filename = pathinfo($relativePath, PATHINFO_FILENAME);
    return ucwords(str_replace(['_', '-'], ' ', $filename));
}

function erpnext_parity_module_from_path(string $relativePath): string
{
    $parts = explode('/', trim(str_replace('\\', '/', $relativePath), '/'));
    if (($parts[0] ?? '') === 'erpnext' || ($parts[0] ?? '') === 'hrms') {
        array_shift($parts);
    }
    $moduleSlug = strtolower((string) ($parts[0] ?? ''));
    $modules = [
        'accounts' => 'Accounts',
        'crm' => 'CRM',
        'buying' => 'Buying',
        'projects' => 'Projects',
        'selling' => 'Selling',
        'setup' => 'Setup',
        'manufacturing' => 'Manufacturing',
        'stock' => 'Stock',
        'support' => 'Support',
        'utilities' => 'Utilities',
        'assets' => 'Assets',
        'portal' => 'Portal',
        'maintenance' => 'Maintenance',
        'regional' => 'Regional',
        'integrations' => 'ERPNext Integrations',
        'quality_management' => 'Quality Management',
        'communication' => 'Communication',
        'telephony' => 'Telephony',
        'bulk_transaction' => 'Bulk Transaction',
        'subcontracting' => 'Subcontracting',
        'edi' => 'EDI',
        'hr' => 'HR',
        'payroll' => 'Payroll',
    ];

    return $modules[$moduleSlug] ?? ucwords(str_replace('_', ' ', $moduleSlug));
}

function erpnext_parity_owner_for_artifact(string $repository, string $module, string $path, string $name): string
{
    if ($repository === 'hrms') {
        return 'hr';
    }

    if (preg_match('/(?:^|\b)(?:pos|point of sale)(?:\b|$)/i', $name . ' ' . $path) === 1) {
        return 'sales-crm';
    }

    $owners = [
        'Accounts' => 'accounting-finance',
        'CRM' => 'sales-crm',
        'Selling' => 'sales-crm',
        'Buying' => 'buying-procurement',
        'Stock' => 'inventory-warehouse',
        'Manufacturing' => 'manufacturing',
        'Quality Management' => 'manufacturing',
        'Subcontracting' => 'manufacturing',
        'Projects' => 'projects',
        'Support' => 'support-service',
        'Telephony' => 'support-service',
        'Assets' => 'assets-maintenance',
        'Maintenance' => 'assets-maintenance',
        'Regional' => 'compliance-localization',
        'Setup' => 'operations',
        'Utilities' => 'operations',
        'Portal' => 'operations',
        'ERPNext Integrations' => 'operations',
        'Communication' => 'operations',
        'Bulk Transaction' => 'operations',
        'EDI' => 'operations',
    ];

    if (!isset($owners[$module])) {
        throw new RuntimeException('No BuilderX parity owner is mapped for source module ' . ($module !== '' ? $module : '(empty)') . ' at ' . $path . '.');
    }

    return $owners[$module];
}

function erpnext_parity_discover(string $erpnextRoot, string $hrmsRoot, array $baseline): array
{
    $artifacts = [];
    $repositories = [
        'erpnext' => ['root' => $erpnextRoot, 'commit' => (string) ($baseline['erpnext']['commit'] ?? '')],
        'hrms' => ['root' => $hrmsRoot, 'commit' => (string) ($baseline['hrms']['commit'] ?? '')],
    ];

    foreach ($repositories as $repositoryKey => $repository) {
        $root = rtrim((string) $repository['root'], DIRECTORY_SEPARATOR);
        if (!is_dir($root)) {
            throw new RuntimeException('Parity source checkout was not found: ' . $root);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
            $kind = erpnext_parity_artifact_kind($relativePath);
            if ($kind === null) {
                continue;
            }

            $metadata = [];
            if (strtolower($entry->getExtension()) === 'json') {
                try {
                    $decoded = json_decode((string) file_get_contents($entry->getPathname()), true, 512, JSON_THROW_ON_ERROR);
                    $metadata = is_array($decoded) ? $decoded : [];
                } catch (JsonException) {
                    continue;
                }
            }

            $name = trim((string) ($metadata['name'] ?? $metadata['label'] ?? $metadata['title'] ?? ''));
            if ($name === '') {
                $name = erpnext_parity_title_from_path($relativePath);
            }
            $module = trim((string) ($metadata['module'] ?? ''));
            if ($module === '') {
                $module = erpnext_parity_module_from_path($relativePath);
            }
            $moduleOwner = erpnext_parity_owner_for_artifact($repositoryKey, $module, $relativePath, $name);
            $sourcePath = $relativePath;
            $sourceId = $repositoryKey . ':' . $sourcePath . ':' . $kind . ':' . $name;

            $artifacts[] = [
                'source_id' => $sourceId,
                'source_repository' => $repositoryKey,
                'source_commit' => (string) $repository['commit'],
                'source_path' => $sourcePath,
                'source_kind' => $kind,
                'source_module' => $module,
                'source_name' => $name,
                'module_owner' => $moduleOwner,
                'builderx_target' => $moduleOwner,
                'dependencies' => [],
                'status' => 'MISSING',
                'evidence' => [],
                'reason' => '',
            ];
        }
    }

    usort($artifacts, static fn (array $left, array $right): int => strcmp((string) $left['source_id'], (string) $right['source_id']));
    return $artifacts;
}

function erpnext_parity_partition(array $artifacts, array $owners): array
{
    $partitions = [];
    foreach (array_keys($owners['owners'] ?? []) as $ownerKey) {
        $partitions[(string) $ownerKey] = [];
    }

    foreach ($artifacts as $artifact) {
        $ownerKey = (string) ($artifact['module_owner'] ?? '');
        if (!array_key_exists($ownerKey, $partitions)) {
            throw new RuntimeException('Discovered parity artifact references unknown owner ' . $ownerKey . '.');
        }
        $partitions[$ownerKey][] = $artifact;
    }

    return $partitions;
}

function erpnext_parity_write_ledgers(string $directory, array $partitions, array $owners, array $baseline): void
{
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create parity ledger directory: ' . $directory);
    }

    foreach ($partitions as $ownerKey => $rows) {
        $owner = $owners['owners'][$ownerKey] ?? null;
        if (!is_array($owner)) {
            throw new RuntimeException('Cannot write ledger for unknown owner ' . $ownerKey . '.');
        }
        $payload = [
            'schema_version' => 'builderx.erpnext-parity-ledger.v1',
            'baseline_captured_on' => (string) ($baseline['captured_on'] ?? ''),
            'owner' => $ownerKey,
            'thread_id' => (string) $owner['thread_id'],
            'rows' => array_values($rows),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $target = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $ownerKey . '.json';
        $temporary = $target . '.tmp';
        if (file_put_contents($temporary, $json) === false || !rename($temporary, $target)) {
            throw new RuntimeException('Could not write parity ledger: ' . $target);
        }
    }
}

function erpnext_parity_cli(array $arguments): int
{
    $projectRoot = dirname(__DIR__);
    $command = (string) ($arguments[1] ?? '');

    try {
        $baseline = erpnext_parity_load_baseline($projectRoot . '/docs/erpnext-parity/baseline.json');
        $owners = erpnext_parity_load_owners($projectRoot . '/docs/erpnext-parity/owners.json');

        if ($command === 'validate-baseline') {
            echo 'ERPNext parity baseline is valid for ' . count($owners['owners']) . " owners.\n";
            return 0;
        }

        if ($command === 'validate-ledger') {
            $ledgerPaths = glob($projectRoot . '/docs/erpnext-parity/ledgers/*.json') ?: [];
            if (count($ledgerPaths) !== count($owners['owners'])) {
                throw new RuntimeException('ERPNext parity ledgers must contain exactly one file for each owner.');
            }

            $errors = [];
            $seenOwners = [];
            foreach ($ledgerPaths as $ledgerPath) {
                $ledger = erpnext_parity_read_json($ledgerPath);
                if (($ledger['schema_version'] ?? '') !== 'builderx.erpnext-parity-ledger.v1') {
                    $errors[] = basename($ledgerPath) . ' has an unsupported ledger schema.';
                    continue;
                }
                $ledgerOwner = (string) ($ledger['owner'] ?? '');
                if (!isset($owners['owners'][$ledgerOwner])) {
                    $errors[] = basename($ledgerPath) . ' references an unknown owner.';
                    continue;
                }
                if (isset($seenOwners[$ledgerOwner])) {
                    $errors[] = basename($ledgerPath) . ' duplicates owner ' . $ledgerOwner . '.';
                }
                $seenOwners[$ledgerOwner] = true;
                if (basename($ledgerPath, '.json') !== $ledgerOwner) {
                    $errors[] = basename($ledgerPath) . ' does not match owner ' . $ledgerOwner . '.';
                }
                $rows = $ledger['rows'] ?? null;
                if (!is_array($rows)) {
                    $errors[] = basename($ledgerPath) . ' does not contain a rows array.';
                    continue;
                }
                foreach (erpnext_parity_validate_ledger($rows, $owners) as $error) {
                    $errors[] = basename($ledgerPath) . ': ' . $error;
                }
            }

            if ($errors !== []) {
                throw new RuntimeException(implode("\n", $errors));
            }
            echo 'ERPNext parity ledgers are valid against ' . (string) $baseline['captured_on'] . ".\n";
            return 0;
        }

        if ($command === 'discover') {
            $erpnextRoot = (string) ($arguments[2] ?? '');
            $hrmsRoot = (string) ($arguments[3] ?? '');
            if ($erpnextRoot === '' || $hrmsRoot === '') {
                throw new RuntimeException('Discover requires ERPNext and Frappe HR checkout paths.');
            }
            $artifacts = erpnext_parity_discover($erpnextRoot, $hrmsRoot, $baseline);
            $partitions = erpnext_parity_partition($artifacts, $owners);
            erpnext_parity_write_ledgers($projectRoot . '/docs/erpnext-parity/ledgers', $partitions, $owners, $baseline);
            echo 'Discovered ' . count($artifacts) . " ERPNext parity artifacts across eleven owners.\n";
            return 0;
        }

        fwrite(STDERR, "Usage: php tools/erpnext-parity.php validate-baseline|validate-ledger|discover <erpnext-root> <hrms-root>\n");
        return 2;
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        return 1;
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(erpnext_parity_cli($argv));
}
