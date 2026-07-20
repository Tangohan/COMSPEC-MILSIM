<?php
declare(strict_types=1);
$courses = $courses ?? [];
$coursesSearch = trim((string) ($coursesSearch ?? ''));
$coursesTagSlug = trim((string) ($coursesTagSlug ?? ''));
$coursesAllTagsList = is_array($coursesAllTags ?? null) ? $coursesAllTags : [];
$coursesTagsByCourseId = is_array($coursesTagsByCourseId ?? null) ? $coursesTagsByCourseId : [];
$trainingCanExportFull = !empty($trainingCanExportFull);
$trainingCanEditShowcaseOrCatalog = !empty($trainingCanEditShowcaseOrCatalog);
$trainingCanDeleteCourse = !empty($trainingCanDeleteCourse);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$courseCount = count($courses);
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <?php if ($flashOk): ?>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-950" role="status"><?= htmlspecialchars((string) $flashOk) ?></div>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-950" role="alert"><?= htmlspecialchars((string) $flashErr) ?></div>
                <?php endif; ?>

                <header class="tc-panel p-6 md:p-8">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="min-w-0">
                            <p class="tc-kicker">Catalogue d’édition</p>
                            <h1 class="tc-hero-title mb-2">Toutes les formations</h1>
                            <p class="text-slate-600 text-sm">
                                <?= $courseCount === 0
                                    ? 'Aucune formation pour cette communauté.'
                                    : ($courseCount === 1
                                        ? '1 formation — brouillons et parcours publiés.'
                                        : $courseCount . ' formations — brouillons et parcours publiés.') ?>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= training_studio_url() ?>" class="tc-btn-primary">Créer dans le studio</a>
                            <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="tc-btn-primary tc-btn-ghost">Vue d’ensemble</a>
                        </div>
                    </div>
                    <form method="get" action="<?= htmlspecialchars(training_lms_admin_url('courses'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 flex max-w-xl flex-wrap gap-1.5">
                        <input type="search" name="q" value="<?= htmlspecialchars($coursesSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Rechercher un titre ou une description…" class="h-9 flex-1 rounded-lg border border-slate-300 px-3 text-sm">
                        <?php if ($coursesAllTagsList !== []): ?>
                        <select name="tag" class="h-9 rounded-lg border border-slate-300 px-2 text-sm">
                            <option value="">Tous tags</option>
                            <?php foreach ($coursesAllTagsList as $tg): ?>
                            <option value="<?= htmlspecialchars((string) $tg['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= $coursesTagSlug === $tg['slug'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $tg['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <button type="submit" class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Rechercher</button>
                        <?php if ($coursesSearch !== '' || $coursesTagSlug !== ''): ?>
                        <a href="<?= htmlspecialchars(training_lms_admin_url('courses'), ENT_QUOTES, 'UTF-8') ?>" class="h-9 inline-flex items-center rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-500 hover:bg-slate-50">Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                </header>

                <?php if ($courseCount === 0 && $coursesSearch !== ''): ?>
                <div class="tc-panel p-10 text-center text-slate-600">
                    <p class="text-sm font-semibold text-slate-800">Aucune formation ne correspond à « <?= htmlspecialchars($coursesSearch, ENT_QUOTES, 'UTF-8') ?> ».</p>
                    <a href="<?= htmlspecialchars(training_lms_admin_url('courses'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost mt-4 inline-flex">Réinitialiser la recherche</a>
                </div>
                <?php elseif ($courseCount === 0): ?>
                <div class="tc-panel p-10 text-center text-slate-600">
                    <p class="text-sm font-semibold text-slate-800">Aucune formation pour cette communauté.</p>
                    <a href="<?= training_studio_url() ?>" class="tc-btn-primary tc-btn-emerald mt-4 inline-flex">Créer dans le studio</a>
                </div>
                <?php else: ?>
                <div class="tc-table-wrap overflow-x-auto">
                    <table class="min-w-[860px]">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Code</th>
                                <th>Thème</th>
                                <th>Publication</th>
                                <th>Portée</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                            <?php
                            $cid = (int) $c['id'];
                            $vis = (string) ($c['visibility'] ?? '');
                            $visFr = match ($vis) {
                                'published' => 'Publié',
                                'private' => 'Privé',
                                'archived' => 'Archivé',
                                'draft' => 'Brouillon',
                                default => $vis !== '' ? ucfirst($vis) : '—',
                            };
                            $visBadge = match ($vis) {
                                'published' => 'bg-emerald-100 text-emerald-800',
                                'draft' => 'bg-amber-100 text-amber-800',
                                default => 'bg-slate-200 text-slate-700',
                            };
                            $scopeRaw = (string) ($c['lms_scope'] ?? 'tenant');
                            $scopeFr = $scopeRaw === 'platform' ? 'Toute la plateforme' : 'Cette communauté';
                            $scopeBadge = $scopeRaw === 'platform' ? 'bg-violet-100 text-violet-800' : 'bg-slate-200 text-slate-700';
                            $code = trim((string) ($c['course_code'] ?? ''));
                            $theme = trim((string) ($c['category'] ?? ''));
                            $slug = trim((string) ($c['slug'] ?? ''));
                            ?>
                            <tr>
                                <td class="font-semibold text-slate-900">
                                    <?= htmlspecialchars((string) $c['title']) ?>
                                    <?php $cTags = $coursesTagsByCourseId[$cid] ?? []; if ($cTags !== []): ?>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <?php foreach ($cTags as $ct): ?>
                                        <span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"><?= htmlspecialchars((string) $ct['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap font-mono text-xs text-slate-700"><?= $code !== '' ? htmlspecialchars($code) : '—' ?></td>
                                <td class="text-sm text-slate-700"><?= $theme !== '' ? htmlspecialchars($theme) : '—' ?></td>
                                <td>
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase rounded-full <?= $visBadge ?>"><?= htmlspecialchars($visFr) ?></span>
                                </td>
                                <td>
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase rounded-full <?= $scopeBadge ?>"><?= htmlspecialchars($scopeFr) ?></span>
                                </td>
                                <td>
                                    <div class="tc-course-actions" role="group" aria-label="Actions pour cette formation">
                                        <div class="tc-course-actions__chips">
                                            <a href="<?= url('formations/' . rawurlencode($slug !== '' ? $slug : (string) $cid)) ?>" class="tc-course-actions__chip tc-course-actions__chip--ext" target="_blank" rel="noopener" title="Ouvre la fiche visible par les membres">Ouvrir</a>
                                            <a href="<?= training_studio_url((string) $cid) ?>" class="tc-course-actions__chip" title="Modifier le contenu pédagogique">Éditer</a>
                                            <?php if ($trainingCanEditShowcaseOrCatalog): ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/showcase')) ?>" class="tc-course-actions__chip" title="Carte et textes sur la page des formations">Vitrine</a>
                                            <?php endif; ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('enrollments') . '?course_id=' . $cid) ?>" class="tc-course-actions__chip">Inscriptions</a>
                                            <?php if ($trainingCanExportFull): ?>
                                            <a href="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/export')) ?>" class="tc-course-actions__btn tc-course-actions__btn--download" title="Télécharger une sauvegarde complète">Dossier</a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (($trainingCanEditShowcaseOrCatalog && $vis === 'published') || $trainingCanDeleteCourse): ?>
                                        <div class="tc-course-actions__risky">
                                            <?php if ($trainingCanEditShowcaseOrCatalog && $vis === 'published'): ?>
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/unpublish')) ?>" class="tc-course-actions__form" onsubmit="return confirm('Retirer cette formation du catalogue public ? Elle restera modifiable dans le studio.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="tc-course-actions__btn tc-course-actions__btn--warn">Retirer du catalogue</button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($trainingCanDeleteCourse): ?>
                                            <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('courses/' . $cid . '/delete')) ?>" class="tc-course-actions__form" onsubmit="return confirm('Supprimer définitivement ce parcours, les inscriptions et la progression associées ? Cette action est irréversible.');">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="tc-course-actions__btn tc-course-actions__btn--danger">Supprimer</button>
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
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
