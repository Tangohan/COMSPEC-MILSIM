<?php
$courses = $courses ?? [];
$trainingCanExportFull = !empty($trainingCanExportFull);
$trainingCanEditShowcaseOrCatalog = !empty($trainingCanEditShowcaseOrCatalog);
$trainingCanDeleteCourse = !empty($trainingCanDeleteCourse);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <?php if ($flashOk): ?>
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950" role="status"><?= htmlspecialchars((string) $flashOk) ?></div>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950" role="alert"><?= htmlspecialchars((string) $flashErr) ?></div>
                <?php endif; ?>
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
                                <th class="w-[min(22rem,32vw)]">Accès &amp; gestion</th>
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
                                <td class="align-top py-4">
                                    <div class="tc-course-actions">
                                        <div class="tc-course-actions__chips" role="group" aria-label="Raccourcis pour cette formation">
                                            <a href="<?= url('formations/' . rawurlencode((string) $c['slug'])) ?>" class="tc-course-actions__chip tc-course-actions__chip--ext" target="_blank" rel="noopener" title="Ouvre la page publique de la formation">Fiche publique</a>
                                            <?php if ($trainingCanEditShowcaseOrCatalog): ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/showcase')) ?>" class="tc-course-actions__chip" title="Carte et textes sur la page des formations">Vitrine</a>
                                            <?php endif; ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . (int) $cid) ?>" class="tc-course-actions__chip">Inscriptions</a>
                                            <a href="<?= training_studio_url((string) $cid) ?>" class="tc-course-actions__chip" title="Modifier le contenu pédagogique">Studio LMS</a>
                                        </div>
                                        <?php if ($trainingCanExportFull): ?>
                                        <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/export')) ?>" class="tc-course-actions__btn tc-course-actions__btn--download">Télécharger le dossier</a>
                                        <?php endif; ?>
                                        <?php if (($trainingCanEditShowcaseOrCatalog && $vis === 'published') || $trainingCanDeleteCourse): ?>
                                        <div class="tc-course-actions__risky" role="group" aria-label="Actions sensibles">
                                            <?php if ($trainingCanEditShowcaseOrCatalog && $vis === 'published'): ?>
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/unpublish')) ?>" class="tc-course-actions__form" onsubmit="return confirm('Retirer cette formation du catalogue public ? Elle restera modifiable dans le studio.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="tc-course-actions__btn tc-course-actions__btn--warn">Retirer du catalogue</button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($trainingCanDeleteCourse): ?>
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/delete')) ?>" class="tc-course-actions__form" onsubmit="return confirm('Supprimer définitivement ce parcours, les inscriptions et la progression associées ? Cette action est irréversible.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="tc-course-actions__btn tc-course-actions__btn--danger">Supprimer le parcours</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
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
