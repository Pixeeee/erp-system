<?php
/** Workspace variables are prepared by bootstrap/controller.php. */
?>
                                <style>
                                    .yovel-hr-workspace .yovel-hr-approved-layout > .yovel-hr-panel {
                                        min-width: 0;
                                    }
                                    .yovel-hr-workspace .yovel-hr-mobile-table-scroll {
                                        max-width: 100%;
                                        min-width: 0;
                                        overflow-x: auto;
                                        overscroll-behavior-inline: contain;
                                        position: relative;
                                    }
                                    @media (max-width: 1279px) {
                                        .yovel-hr-workspace .yovel-hr-approved-layout {
                                            grid-template-columns: minmax(0, 1fr);
                                        }
                                    }
                                    @media (min-width: 1280px) {
                                        .yovel-hr-workspace .yovel-hr-approved-layout {
                                            grid-template-columns: minmax(0, 12fr) minmax(16rem, 8fr);
                                        }
                                        .yovel-hr-workspace .yovel-hr-approved-layout > .yovel-hr-panel {
                                            grid-column: auto;
                                        }
                                    }
                                </style>
                                <div class="yovel-hr-workspace grid min-h-0 gap-3">
                                    <?php if ($activeHrSection === 'dashboard'): ?>
                                        <?php $hrSetupPostRehydration = isset($activeModuleFormState['action']) && str_starts_with((string) $activeModuleFormState['action'], 'hr_setup_'); ?>
                                        <?php if ((string) ($_GET['workspace'] ?? '') === 'setup' || $hrSetupPostRehydration): ?>
                                            <?php require __DIR__ . '/setup.php'; ?>
                                        <?php else: ?>
                                            <?php require __DIR__ . '/dashboard.php'; ?>
                                        <?php endif; ?>
                                    <?php elseif ($activeHrSection === 'departments'): ?>
                                        <?php require __DIR__ . '/departments.php'; ?>
                                    <?php elseif ($activeHrSection === 'job-positions'): ?>
                                        <?php require __DIR__ . '/job-positions.php'; ?>
                                    <?php elseif ($activeHrSection === 'teams'): ?>
                                        <?php require __DIR__ . '/teams.php'; ?>
                                    <?php elseif ($activeHrSection === 'employee-profiles'): ?>
                                        <?php require __DIR__ . '/employee-profiles.php'; ?>
                                    <?php elseif ($activeHrSection === 'attendance'): ?>
                                        <?php require __DIR__ . '/attendance.php'; ?>
                                    <?php else: ?>
                                        <?php require __DIR__ . '/queued.php'; ?>
                                    <?php endif; ?>
                                </div>
