<?php
declare(strict_types=1);

$tgGroups = $tgGroups ?? [];
$tgCourses = $tgCourses ?? [];
$tgCsrfToken = (string) ($tgCsrfToken ?? '');
$baseGroupsUrl = training_lms_admin_url('groupes');

require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Cohortes</p>
                    <h1 class="tc-hero-title mb-3">Groupes de formation</h1>
                    <p class="text-slate-600 text-sm">Regroupez des membres suivant un même parcours (promotion, session de tutorat groupée…) pour suivre leur progression et communiquer plus facilement.</p>
                </header>

                <section class="tc-panel p-6 md:p-8 space-y-4">
                    <h2 class="text-base font-semibold text-slate-900">Créer un groupe</h2>
                    <form method="post" action="<?= htmlspecialchars($baseGroupsUrl, ENT_QUOTES, 'UTF-8') ?>" class="grid gap-3 md:grid-cols-[1fr_1fr_auto] items-end">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($tgCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1" for="tg-name">Nom du groupe</label>
                            <input type="text" id="tg-name" name="name" required maxlength="150" placeholder="Ex. Promotion Été 2026" class="h-9 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1" for="tg-course">Formation liée (facultatif)</label>
                            <select id="tg-course" name="course_id" class="h-9 w-full rounded-lg border border-slate-300 px-2 text-sm">
                                <option value="">Aucune</option>
                                <?php foreach ($tgCourses as $c): ?>
                                <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="tc-btn-primary tc-btn-emerald text-sm">Créer</button>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-semibold text-slate-500 mb-1" for="tg-desc">Description (facultatif)</label>
                            <textarea id="tg-desc" name="description" rows="2" maxlength="2000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </div>
                    </form>
                </section>

                <?php if (empty($tgGroups)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucun groupe pour l’instant.</div>
                <?php else: ?>
                <div class="tc-table-wrap overflow-x-auto">
                    <table class="text-sm min-w-[720px]">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Formation liée</th>
                                <th>Membres</th>
                                <th>Créé le</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tgGroups as $g):
                                $gid = (int) ($g['id'] ?? 0);
                                $gname = (string) ($g['name'] ?? '');
                                $courseTitle = trim((string) ($g['course_title'] ?? ''));
                                ?>
                            <tr class="align-top">
                                <td class="font-medium text-slate-900">
                                    <a href="<?= htmlspecialchars($baseGroupsUrl . '/' . $gid, ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 hover:underline"><?= htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') ?></a>
                                </td>
                                <td class="text-slate-600"><?= $courseTitle !== '' ? htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                <td class="text-slate-600"><?= (int) ($g['member_count'] ?? 0) ?></td>
                                <td class="text-slate-500 whitespace-nowrap"><?= !empty($g['created_at']) ? date('d/m/Y', strtotime((string) $g['created_at'])) : '—' ?></td>
                                <td class="text-slate-600">
                                    <div class="flex flex-wrap gap-3">
                                        <a href="<?= htmlspecialchars($baseGroupsUrl . '/' . $gid, ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-800 font-semibold hover:underline">Gérer</a>
                                        <form method="post" action="<?= htmlspecialchars($baseGroupsUrl . '/' . $gid . '/supprimer', ENT_QUOTES, 'UTF-8') ?>" class="inline" onsubmit="return confirm('Supprimer le groupe « <?= htmlspecialchars(addslashes($gname), ENT_QUOTES, 'UTF-8') ?> » ? Cette action est irréversible.');">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($tgCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="text-rose-700 font-semibold bg-transparent border-0 p-0">Supprimer</button>
                                        </form>
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
