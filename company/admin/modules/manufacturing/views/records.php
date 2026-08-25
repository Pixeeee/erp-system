<?php
declare(strict_types=1);

$manufacturingEscape = $manufacturingEscape ?? static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$state = is_array($manufacturingData['state'] ?? null) ? $manufacturingData['state'] : [];
?>
<div class="grid min-h-[24rem] place-items-center text-center" data-manufacturing-state="<?= $manufacturingEscape((string) ($state['kind'] ?? 'empty')) ?>">
    <div class="max-w-md">
        <span class="material-symbols-rounded text-3xl text-muted-foreground" aria-hidden="true"><?= ($state['kind'] ?? '') === 'dependency' ? 'hourglass_top' : 'precision_manufacturing' ?></span>
        <h2 class="mt-3 text-base font-semibold"><?= $manufacturingEscape((string) ($state['title'] ?? 'Manufacturing')) ?></h2>
        <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= $manufacturingEscape((string) ($state['message'] ?? '')) ?></p>
        <?php if (($state['dependencies'] ?? []) !== []): ?><div class="mt-4 flex justify-center gap-2"><?php foreach ($state['dependencies'] as $dependency): ?><span class="rounded-sm bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground"><?= $manufacturingEscape((string) $dependency) ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
</div>
