<?php
$courses = $courses ?? [];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Catalogue</p>
                    <h1 class="tc-hero-title mb-3">Formations publiées &amp; brouillons</h1>
                    <p class="text-slate-600 text-sm max-w-2xl">Accès vitrine, assignations et Studio pour chaque parcours.</p>
                </header>

                <?php if (empty($courses)): ?>
                <div class="tc-panel p-10 text-center">
                    <p class="text-slate-600 font-medium">Aucune formation pour cette communauté.</p>
                    <a href="<?= url('admin/training/studio') ?>" class="inline-block mt-6 tc-btn-primary tc-btn-emerald">Ouvrir le Studio LMS</a>
                </div>
                <?php else: ?>
                <div class="tc-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Slug</th>
                                <th>Visibilité</th>
                                <th>Catégorie</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                            <tr>
                                <td class="font-semibold text-slate-900"><?= htmlspecialchars($c['title']) ?></td>
                                <td class="font-mono text-xs text-slate-500"><?= htmlspecialchars($c['slug']) ?></td>
                                <td>
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-full <?= ($c['visibility'] ?? '') === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>"><?= htmlspecialchars($c['visibility'] ?? '') ?></span>
                                </td>
                                <td class="text-slate-600"><?= htmlspecialchars($c['category'] ?? '—') ?></td>
                                <td>
                                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                                        <a href="<?= url('formations/' . rawurlencode((string) $c['slug'])) ?>" class="text-emerald-700 hover:underline" target="_blank" rel="noopener">Voir</a>
                                        <a href="<?= url('admin/training/courses/' . (int) $c['id'] . '/showcase') ?>" class="text-slate-700 hover:underline">Vitrine</a>
                                        <a href="<?= url('admin/training/enrollments?course_id=' . (int) $c['id']) ?>" class="text-slate-700 hover:underline">Assign.</a>
                                        <a href="<?= url('admin/training/studio/' . (int) $c['id']) ?>" class="text-violet-700 hover:underline">Studio</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <p class="text-sm text-slate-500">
                    <a href="<?= url('admin/training') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Vue d’ensemble</a>
                </p>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
