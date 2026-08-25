<?php
declare(strict_types=1);

$started = microtime(true);
$root = dirname(__DIR__);
require_once $root . '/app/foundation.php';
require_once $root . '/company/admin/core/functions.php';
require_once $root . '/company/admin/modules/inventory-warehouse/functions.php';

try {
    $payload = json_decode(base64_decode((string) ($argv[1] ?? ''), true) ?: '', true, 512, JSON_THROW_ON_ERROR);
    $result = yovel_admin_inventory_in_transaction(static fn (ADOConnection $db): array =>
        yovel_admin_inventory_post_serial_batch_bundle($db, $payload['company'], $payload['admin'], $payload['voucher'], $payload['bundle'])
    );
    echo json_encode(['elapsed' => microtime(true) - $started, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(2);
}
