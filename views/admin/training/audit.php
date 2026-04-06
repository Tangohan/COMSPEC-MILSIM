<?php
$logs = $logs ?? [];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Traçabilité</p>
                    <h1 class="tc-hero-title mb-3">Audit formations</h1>
                    <p class="text-slate-600 text-sm">Journal des actions sensibles (création, publication, assignation…).</p>
                </header>

                <?php if (empty($logs)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucune entrée d’audit.</div>
                <?php else: ?>
                <div class="tc-table-wrap">
                    <table class="text-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Cible</th>
                                <th>ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l):
                                $actionSlug = (string) ($l['action'] ?? '');
                                $targetSlug = (string) ($l['target_type'] ?? '');
                                $actionLabel = function_exists('training_audit_action_label_fr')
                                    ? training_audit_action_label_fr($actionSlug)
                                    : $actionSlug;
                                $targetLabel = function_exists('training_audit_target_type_label_fr')
                                    ? training_audit_target_type_label_fr($targetSlug)
                                    : $targetSlug;
                            ?>
                            <tr>
                                <td class="text-slate-500 whitespace-nowrap"><?= !empty($l['created_at']) ? date('d/m/Y H:i', strtotime($l['created_at'])) : '—' ?></td>
                                <td class="font-medium text-slate-900"><?= htmlspecialchars($actionLabel) ?></td>
                                <td><?= htmlspecialchars($targetLabel) ?></td>
                                <td class="font-mono"><?= (int) ($l['target_id'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <p class="text-sm text-slate-500">
                    <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Vue d’ensemble</a>
                </p>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
