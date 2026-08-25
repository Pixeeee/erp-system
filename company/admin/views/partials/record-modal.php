<?php
declare(strict_types=1);

$modal = is_array($recordModal ?? null) ? $recordModal : [];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$modalId = trim((string) ($modal['id'] ?? 'record-modal'));
$title = trim((string) ($modal['title'] ?? 'Add Record'));
$description = trim((string) ($modal['description'] ?? 'Enter the record details.'));
$openLabel = trim((string) ($modal['open_label'] ?? 'Add Record'));
$submitLabel = trim((string) ($modal['submit_label'] ?? 'Submit'));
$confirmMessage = trim((string) ($modal['confirm_message'] ?? 'Confirm this record before saving.'));
?>
<button type="button" data-record-modal-open="<?= $escape($modalId) ?>" class="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">
    <span class="material-symbols-rounded text-base" aria-hidden="true">add</span><?= $escape($openLabel) ?>
</button>
<div id="<?= $escape($modalId) ?>" data-record-modal hidden class="yovel-record-modal fixed inset-0 z-[70] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="<?= $escape($modalId) ?>-title">
    <section class="flex max-h-[calc(100dvh-2rem)] w-full max-w-3xl flex-col overflow-hidden rounded-lg border bg-card shadow-lg" role="document">
        <header class="flex items-start justify-between gap-4 border-b bg-card px-5 py-4">
            <div class="min-w-0">
                <h2 id="<?= $escape($modalId) ?>-title" class="text-base font-semibold"><?= $escape($title) ?></h2>
                <p class="mt-1 text-sm leading-5 text-muted-foreground"><?= $escape($description) ?></p>
            </div>
            <button type="button" data-record-modal-close class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border" aria-label="Close">
                <span class="material-symbols-rounded text-base" aria-hidden="true">close</span>
            </button>
        </header>
        <form method="post" data-record-modal-form data-confirm-submit data-confirm-message="<?= $escape($confirmMessage) ?>" class="contents">
            <?= (string) ($modal['hidden_html'] ?? '') ?>
            <div class="yovel-record-modal-body grid gap-4 overflow-y-auto p-5"><?= (string) ($modal['body_html'] ?? '') ?></div>
            <footer class="flex justify-end gap-2 border-t bg-card px-5 py-4">
                <button type="button" data-record-modal-close class="h-9 rounded-md border px-3 text-sm">Cancel</button>
                <button type="submit" data-confirm-submit-action class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"><?= $escape($submitLabel) ?></button>
            </footer>
        </form>
    </section>
</div>
