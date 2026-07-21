<?php
declare(strict_types=1);

$tgGroup = $tgGroup ?? [];
$tgMembers = $tgMembers ?? [];
$tgAvailableUsers = $tgAvailableUsers ?? [];
$tgCsrfToken = (string) ($tgCsrfToken ?? '');
$groupId = (int) ($tgGroup['id'] ?? 0);
$groupName = (string) ($tgGroup['name'] ?? '');
$groupDesc = trim((string) ($tgGroup['description'] ?? ''));
$courseTitle = trim((string) ($tgGroup['course_title'] ?? ''));
$groupUrl = training_lms_admin_url('groupes/' . $groupId);

require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Cohorte</p>
                    <h1 class="tc-hero-title mb-2"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if ($courseTitle !== ''): ?>
                    <p class="text-slate-600 text-sm">Formation liée : <strong><?= htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <?php endif; ?>
                    <?php if ($groupDesc !== ''): ?>
                    <p class="text-slate-600 text-sm mt-2"><?= nl2br(htmlspecialchars($groupDesc, ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php endif; ?>
                </header>

                <section class="tc-panel p-6 md:p-8 space-y-4">
                    <h2 class="text-base font-semibold text-slate-900">Ajouter un membre</h2>
                    <?php if (empty($tgAvailableUsers)): ?>
                    <p class="text-sm text-slate-500">Aucun membre disponible à ajouter (déjà tous dans le groupe, ou aucun compte actif).</p>
                    <?php else: ?>
                    <form method="post" action="<?= htmlspecialchars($groupUrl . '/membres', ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($tgCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1" for="tg-add-user">Membre</label>
                            <select id="tg-add-user" name="user_id" class="h-9 rounded-lg border border-slate-300 px-2 text-sm min-w-[240px]">
                                <?php foreach ($tgAvailableUsers as $u): ?>
                                <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['callsign'] ?? $u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="tc-btn-primary tc-btn-emerald text-sm">Ajouter</button>
                    </form>
                    <?php endif; ?>
                </section>

                <?php if (empty($tgMembers)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucun membre dans ce groupe.</div>
                <?php else: ?>
                <div class="tc-table-wrap overflow-x-auto">
                    <table class="text-sm min-w-[560px]">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th>Depuis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tgMembers as $m):
                                $uid = (int) ($m['user_id'] ?? 0);
                                $mname = (string) ($m['display_name'] ?? $m['callsign'] ?? $m['email'] ?? '');
                                ?>
                            <tr class="align-top">
                                <td class="font-medium text-slate-900"><?= htmlspecialchars($mname, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-slate-500 whitespace-nowrap"><?= !empty($m['joined_at']) ? date('d/m/Y', strtotime((string) $m['joined_at'])) : '—' ?></td>
                                <td>
                                    <form method="post" action="<?= htmlspecialchars($groupUrl . '/membres/' . $uid . '/retirer', ENT_QUOTES, 'UTF-8') ?>" class="inline" onsubmit="return confirm('Retirer « <?= htmlspecialchars(addslashes($mname), ENT_QUOTES, 'UTF-8') ?> » du groupe ?');">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($tgCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="text-rose-700 font-semibold bg-transparent border-0 p-0">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <p class="text-sm text-slate-500">
                    <a href="<?= htmlspecialchars(training_lms_admin_url('groupes')) ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Groupes de formation</a>
                </p>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
