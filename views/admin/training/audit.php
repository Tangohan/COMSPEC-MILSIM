<?php
$logs = $logs ?? [];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Traçabilité</p>
                    <h1 class="tc-hero-title mb-3">Audit formations</h1>
                    <p class="text-slate-600 text-sm">Journal des actions sensibles (création, publication, assignation…), avec le contexte de la formation et les personnes concernées.</p>
                </header>

                <?php if (empty($logs)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucune entrée d’audit.</div>
                <?php else: ?>
                <div class="tc-table-wrap overflow-x-auto">
                    <table class="text-sm min-w-[880px]">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Acteur</th>
                                <th>Action</th>
                                <th>Objet</th>
                                <th>Référent pédagogique</th>
                                <th>Détail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l):
                                $actionSlug = (string) ($l['action'] ?? '');
                                $actionLabel = function_exists('training_audit_action_label_fr')
                                    ? training_audit_action_label_fr($actionSlug)
                                    : $actionSlug;
                                $actorLabel = function_exists('training_audit_actor_label_fr')
                                    ? training_audit_actor_label_fr(
                                        isset($l['actor_display_name']) ? (string) $l['actor_display_name'] : null,
                                        isset($l['actor_email']) ? (string) $l['actor_email'] : null
                                    )
                                    : '—';
                                $authorLabel = function_exists('training_audit_course_author_label_fr')
                                    ? training_audit_course_author_label_fr(
                                        isset($l['course_author_display_name']) ? (string) $l['course_author_display_name'] : null,
                                        isset($l['course_author_email']) ? (string) $l['course_author_email'] : null
                                    )
                                    : '—';
                                $objectLabel = function_exists('training_audit_object_label_fr')
                                    ? training_audit_object_label_fr($l)
                                    : '';
                                $detail = function_exists('training_audit_detail_summary_fr')
                                    ? training_audit_detail_summary_fr($l)
                                    : '—';
                            ?>
                            <tr class="align-top">
                                <td class="text-slate-500 whitespace-nowrap"><?= !empty($l['created_at']) ? date('d/m/Y H:i', strtotime((string) $l['created_at'])) : '—' ?></td>
                                <td class="text-slate-900 font-medium"><?= htmlspecialchars($actorLabel) ?></td>
                                <td class="text-slate-800"><?= htmlspecialchars($actionLabel) ?></td>
                                <td class="text-slate-700 max-w-[240px]"><?= htmlspecialchars($objectLabel) ?></td>
                                <td class="text-slate-600 max-w-[200px]"><?= htmlspecialchars($authorLabel) ?></td>
                                <td class="text-slate-600 max-w-[280px]"><?= htmlspecialchars($detail) ?></td>
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
