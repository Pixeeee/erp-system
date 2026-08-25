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
            if ($ledgerPaths === []) {
                throw new RuntimeException('No ERPNext parity ledgers were found.');
            }

            $errors = [];
            foreach ($ledgerPaths as $ledgerPath) {
                $ledger = erpnext_parity_read_json($ledgerPath);
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

        fwrite(STDERR, "Usage: php tools/erpnext-parity.php validate-baseline|validate-ledger\n");
        return 2;
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        return 1;
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(erpnext_parity_cli($argv));
}
