<?php
/** Workspace variables are prepared by bootstrap/controller.php. */
?>
                                <div class="yovel-hr-workspace grid min-h-0 gap-3">
                                    <?php if ($activeHrSection === 'dashboard'): ?>
                                        <?php require __DIR__ . '/dashboard.php'; ?>
                                    <?php elseif ($activeHrSection === 'departments'): ?>
                                        <?php require __DIR__ . '/departments.php'; ?>
                                    <?php elseif ($activeHrSection === 'job-positions'): ?>
                                        <?php require __DIR__ . '/job-positions.php'; ?>
                                    <?php elseif ($activeHrSection === 'teams'): ?>
                                        <?php require __DIR__ . '/teams.php'; ?>
                                    <?php elseif ($activeHrSection === 'employee-profiles'): ?>
                                        <?php require __DIR__ . '/employee-profiles.php'; ?>
                                    <?php else: ?>
                                        <?php require __DIR__ . '/queued.php'; ?>
                                    <?php endif; ?>
                                </div>
