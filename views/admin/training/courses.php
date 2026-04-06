<?php
$courses = $courses ?? [];
$trainingCanExportFull = !empty($trainingCanExportFull);
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Catalogue</p>
                    <h1 class="tc-hero-title mb-3">Toutes les formations</h1>
                    <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                        Brouillons et parcours publiés : vitrine, inscriptions, studio et<?= $trainingCanExportFull ? ', si vous en avez le droit,' : '' ?> sauvegarde complète du contenu.
                    </p>
                </header>

                <?php if (empty($courses)): ?>
                <div class="tc-panel p-10 text-center">
                    <p class="text-slate-600 font-medium">Aucune formation pour cette communauté.</p>
                    <a href="<?= training_studio_url() ?>" class="inline-block mt-6 tc-btn-primary tc-btn-emerald">Créer dans le Studio LMS</a>
                </div>
                <?php else: ?>
                <div class="tc-table-wrap overflow-x-auto">
                    <table class="min-w-[720px]">
                        <thead>
                            <tr>
                                <th>Formation</th>
                                <th class="hidden md:table-cell">Adresse courte</th>
                                <th>Statut</th>
                                <th class="hidden lg:table-cell">Catégorie</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                            <?php
                            $cid = (int) $c['id'];
                            $vis = (string) ($c['visibility'] ?? '');
                            $visClass = $vis === 'published'
                                ? 'bg-emerald-100 text-emerald-800'
                                : ($vis === 'archived' ? 'bg-slate-300 text-slate-800' : 'bg-slate-200 text-slate-700');
                            $visFr = match ($vis) {
                                'published' => 'Publié',
                                'private' => 'Privé',
                                'archived' => 'Archivé',
                                'draft' => 'Brouillon',
                                default => $vis !== '' ? ucfirst($vis) : '—',
                            };
                            ?>
                            <tr>
                                <td class="font-semibold text-slate-900 align-top">
                                    <span class="block"><?= htmlspecialchars((string) $c['title']) ?></span>
                                    <span class="md:hidden mt-1 block font-mono text-[11px] font-normal text-slate-500"><?= htmlspecialchars((string) $c['slug']) ?></span>
                                </td>
                                <td class="hidden md:table-cell font-mono text-xs text-slate-500 align-top"><?= htmlspecialchars((string) $c['slug']) ?></td>
                                <td class="align-top">
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-full <?= $visClass ?>"><?= htmlspecialchars($visFr) ?></span>
                                </td>
                                <td class="hidden lg:table-cell text-slate-600 align-top"><?= htmlspecialchars((string) ($c['category'] ?? '—')) ?></td>
                                <td class="align-top">
                                    <div class="flex flex-col gap-1.5 text-xs font-bold">
                                        <div class="flex flex-wrap gap-x-2 gap-y-1">
                                            <a href="<?= url('formations/' . rawurlencode((string) $c['slug'])) ?>" class="text-emerald-700 hover:underline" target="_blank" rel="noopener">Voir</a>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/showcase')) ?>" class="text-slate-700 hover:underline">Vitrine</a>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . (int) $cid) ?>" class="text-slate-700 hover:underline">Inscriptions</a>
                                            <a href="<?= training_studio_url((string) $cid) ?>" class="text-violet-700 hover:underline">Studio</a>
                                        </div>
                                        <?php if ($trainingCanExportFull): ?>
                                        <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/export')) ?>" class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider text-slate-500 hover:text-emerald-700 w-fit border border-slate-200 rounded-lg px-2 py-1 bg-white hover:border-emerald-200">
                                            Télécharger le dossier
                                        </a>
                                        <?php endif; ?>
                                    </div>
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
