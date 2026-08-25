<?php
declare(strict_types=1);

$operationsModal = is_array($recordModal ?? null) ? $recordModal : [];
ob_start();
require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
$operationsModalMarkup = (string) ob_get_clean();
if (!empty($operationsModal['open_on_load'])) {
    $operationsModalMarkup = preg_replace(
        '/data-record-modal hidden/',
        'data-record-modal data-record-modal-open-on-load hidden',
        $operationsModalMarkup,
        1
    ) ?? $operationsModalMarkup;
}
echo $operationsModalMarkup;
