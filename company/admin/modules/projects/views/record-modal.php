<?php
declare(strict_types=1);

$projectsModal = is_array($projectsRecordModal ?? null) ? $projectsRecordModal : [];
$recordModal = $projectsModal;
ob_start();
require dirname(__DIR__, 3) . '/views/partials/record-modal.php';
$projectsModalMarkup = (string) ob_get_clean();
$projectsModalId = (string) ($projectsModal['id'] ?? 'projects-record-modal');
$projectsDescriptionId = $projectsModalId . '-description';
$projectsModalMarkup = preg_replace(
    '/(role="dialog" aria-modal="true" aria-labelledby="[^"]+")/',
    '$1 aria-describedby="' . bx_h($projectsDescriptionId) . '"',
    $projectsModalMarkup,
    1
) ?? $projectsModalMarkup;
$projectsModalMarkup = preg_replace(
    '/<p class="mt-1 text-sm leading-5 text-muted-foreground">/',
    '<p id="' . bx_h($projectsDescriptionId) . '" class="mt-1 text-sm leading-5 text-muted-foreground">',
    $projectsModalMarkup,
    1
) ?? $projectsModalMarkup;
if (!empty($projectsModalOpen)) {
    $projectsModalMarkup = preg_replace(
        '/data-record-modal hidden/',
        'data-record-modal data-record-modal-open-on-load hidden',
        $projectsModalMarkup,
        1
    ) ?? $projectsModalMarkup;
}
echo $projectsModalMarkup;
