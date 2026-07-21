<?php
$courses = $courses ?? [];
$courseReports = $courseReports ?? [];
$successRateTenant = is_array($successRateTenant ?? null) ? $successRateTenant : [];
$successRatePlatform = is_array($successRatePlatform ?? null) ? $successRatePlatform : [];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Conformité</p>
                    <h1 class="tc-hero-title mb-3">Rapports</h1>
                    <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                        Suivez la conformité et l’avancement des parcours : taux de complétion, durée moyenne et échéances à venir, formation par formation.
                    </p>
                </header>

                <?php
                $successRatePanelClass = 'tc-panel p-6 md:p-8';
                require base_path('views/admin/training/partials/success_rate_panel.php');
                ?>

                <?php if (empty($courseReports)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucune formation à afficher pour le moment.</div>
                <?php else: ?>
                <div class="tc-table-wrap overflow-x-auto">
                    <table class="min-w-[860px]">
                        <thead>
                            <tr>
                                <th>Formation</th>
                                <th>Inscrits</th>
                                <th>Terminés</th>
                                <th>Taux de complétion</th>
                                <th>En cours</th>
                                <th>Retirés</th>
                                <th>Durée moyenne</th>
                                <th>Échéances ≤ 30 j</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseReports as $r):
                                $c = $r['course'];
                                $rate = $r['completion_rate'];
                            ?>
                            <tr>
                                <td class="font-medium">
                                    <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . (int) $c['id']) ?>" class="text-emerald-700 hover:underline"><?= htmlspecialchars($c['title']) ?></a>
                                </td>
                                <td class="text-slate-700 text-sm"><?= (int) $r['total'] ?></td>
                                <td class="text-slate-700 text-sm"><?= (int) $r['completed'] ?></td>
                                <td class="text-sm">
                                    <?php if ($rate === null): ?>
                                    <span class="text-slate-400">—</span>
                                    <?php else: ?>
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase rounded-full <?= $rate >= 80 ? 'bg-emerald-100 text-emerald-800' : ($rate >= 40 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') ?>"><?= $rate == floor($rate) ? (int) $rate : $rate ?> %</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-slate-700 text-sm"><?= (int) $r['active'] ?></td>
                                <td class="text-slate-700 text-sm"><?= (int) $r['revoked'] ?></td>
                                <td class="text-slate-600 text-sm"><?= $r['avg_completion_label'] !== null ? htmlspecialchars($r['avg_completion_label']) : '—' ?></td>
                                <td class="text-sm">
                                    <?php if ($r['expiring_soon'] > 0): ?>
                                    <span class="font-semibold text-amber-700"><?= (int) $r['expiring_soon'] ?></span>
                                    <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
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
