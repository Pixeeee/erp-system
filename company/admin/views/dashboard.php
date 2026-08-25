<?php
/** Workspace variables are prepared by bootstrap/controller.php. */
?>
                                <div class="grid gap-4">
                                    <div>
                                        <span class="inline-flex w-fit items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">Dashboard</span>
                                        <h2 class="mt-3 text-2xl font-semibold tracking-normal"><?= bx_h($companyName) ?> Dashboard</h2>
                                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground"><?= bx_h($viewDescription) ?></p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <?php foreach ($dashboardMetrics as $metric): ?>
                                            <section class="rounded-lg border bg-card p-4">
                                                <p class="text-xs font-medium text-muted-foreground"><?= bx_h((string) $metric['label']) ?></p>
                                                <p class="mt-2 text-2xl font-semibold tracking-normal"><?= bx_h((string) $metric['value']) ?></p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= bx_h((string) $metric['description']) ?></p>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>

                                    <section class="rounded-lg border bg-card">
                                        <div class="border-b px-5 py-4">
                                            <h3 class="text-base font-semibold tracking-normal">Platform Access Summary</h3>
                                            <p class="mt-1 text-sm text-muted-foreground"><?= bx_h($companyName) ?> Admin can see every ERP workspace. Department users will start limited to their department until Platform grants more access.</p>
                                        </div>
                                        <div class="grid gap-3 p-5 md:grid-cols-4">
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Admin</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Full company access.</p>
                                            </div>
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Department Users</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Department-only by default.</p>
                                            </div>
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Extra Access</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Selected modules by grant.</p>
                                            </div>
                                            <div class="rounded-md bg-muted/40 p-3">
                                                <p class="text-sm font-semibold">Audit</p>
                                                <p class="mt-1 text-xs leading-5 text-muted-foreground">Every access change is audited.</p>
                                            </div>
                                        </div>
                                    </section>

                                    <div>
                                        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                                            <div>
                                                <h3 class="text-lg font-semibold tracking-normal">ERP System Features</h3>
                                                <p class="mt-1 text-sm text-muted-foreground">Department workspaces for <?= bx_h($companyName) ?> operations.</p>
                                            </div>
                                            <span class="inline-flex rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($erpGroups) ?> ERP groups</span>
                                        </div>
                                        <div class="grid gap-4 xl:grid-cols-2">
                                            <?php foreach ($erpGroups as $group): ?>
                                                <section id="erp-<?= bx_h(yovel_admin_slug((string) $group['label'])) ?>" class="rounded-lg border bg-card">
                                                    <div class="border-b px-5 py-4">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <h4 class="text-base font-semibold tracking-normal"><?= bx_h((string) $group['label']) ?></h4>
                                                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"><?= count($group['features']) ?> features</span>
                                                        </div>
                                                        <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) $group['description']) ?></p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2 p-5">
                                                        <?php foreach ($group['features'] as $feature): ?>
                                                            <a id="<?= bx_h(yovel_admin_feature_anchor($group, (string) $feature)) ?>" class="rounded-md bg-muted/50 px-2.5 py-1.5 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground" href="#<?= bx_h(yovel_admin_feature_anchor($group, (string) $feature)) ?>">
                                                                <?= bx_h((string) $feature) ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </section>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
