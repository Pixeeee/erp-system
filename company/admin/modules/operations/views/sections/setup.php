<?php
declare(strict_types=1);
?>
<div class="grid gap-4">
    <div class="flex items-start justify-between gap-4">
        <div><h2 class="text-base font-semibold">Operations setup</h2><p class="mt-1 text-sm text-muted-foreground">Review execution controls, worker health, and release evidence.</p></div>
        <button type="button" data-operations-tour-start class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-medium"><span class="material-symbols-rounded text-base" aria-hidden="true">explore</span>Show Tour</button>
    </div>
    <ol class="grid gap-2 text-sm" data-operations-tour>
        <li class="flex gap-2 rounded-md bg-muted/50 px-3 py-2"><strong class="shrink-0">1. Schedule</strong><span class="text-muted-foreground">Create a reviewed, idempotent run request.</span></li>
        <li class="flex gap-2 rounded-md bg-muted/50 px-3 py-2"><strong class="shrink-0">2. Observe</strong><span class="text-muted-foreground">Track leases, attempts, conflicts, and alerts.</span></li>
        <li class="flex gap-2 rounded-md bg-muted/50 px-3 py-2"><strong class="shrink-0">3. Verify</strong><span class="text-muted-foreground">Record release evidence before promotion.</span></li>
        <li class="flex gap-2 rounded-md bg-muted/50 px-3 py-2"><strong class="shrink-0">4. Improve</strong><span class="text-muted-foreground">Adapt forms while protected system fields remain stable.</span></li>
    </ol>
</div>
