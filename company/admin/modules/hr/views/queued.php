<?php
/** HR view variables are prepared by bootstrap/controller.php. */
?>
                                        <section class="rounded-lg border bg-card">
                                            <div class="border-b px-5 py-4">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <h3 class="text-base font-semibold tracking-normal"><?= bx_h((string) ($activeHrMeta['label'] ?? 'HR Section')) ?></h3>
                                                    <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">Queued</span>
                                                </div>
                                                <p class="mt-1 text-sm leading-6 text-muted-foreground"><?= bx_h((string) ($activeHrMeta['description'] ?? 'HR Department section.')) ?></p>
                                            </div>
                                            <div class="p-5">
                                                <div class="rounded-md bg-muted/40 p-4">
                                                    <p class="text-sm font-semibold">Employee profiles is the active build slice.</p>
                                                    <p class="mt-1 text-xs leading-5 text-muted-foreground">This HR section remains in the Phase Manager queue.</p>
                                                </div>
                                                <div class="mt-4">
                                                    <?php yovel_admin_render_hr_form_builder($activeHrSection, $activeHrFormFields); ?>
                                                </div>
                                            </div>
                                        </section>
