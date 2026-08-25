<?php
declare(strict_types=1);

$confirmCompanyName = htmlspecialchars((string) ($companyName ?? 'this company'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="yovel-confirm-dialog" data-confirm-dialog class="yovel-confirm-dialog fixed inset-0 z-[100] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="yovel-confirm-title" aria-describedby="yovel-confirm-description" hidden>
    <section class="w-full max-w-md rounded-lg border bg-card shadow-lg" role="document">
        <header class="border-b px-5 py-4">
            <h2 id="yovel-confirm-title" class="text-base font-semibold">Confirm Submission</h2>
            <p id="yovel-confirm-description" data-confirm-description class="mt-1 text-sm leading-6 text-muted-foreground">Review and confirm this change for <?= $confirmCompanyName ?>.</p>
        </header>
        <footer class="flex justify-end gap-2 p-4">
            <button type="button" id="yovel-confirm-cancel" data-confirm-cancel class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium hover:bg-muted">Cancel</button>
            <button type="button" id="yovel-confirm-action" data-confirm-action class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">Confirm</button>
        </footer>
    </section>
</div>
