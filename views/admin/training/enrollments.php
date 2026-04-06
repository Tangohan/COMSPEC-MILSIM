<?php
$enrollments = $enrollments ?? [];
$courses = $courses ?? [];
$selectedCourseId = $selectedCourseId ?? 0;
$trainingEnrollmentApprovalRights = $trainingEnrollmentApprovalRights ?? [];
$enrollmentStatusLabels = [
    'assigned' => 'Assigné',
    'in_progress' => 'En cours',
    'completed' => 'Terminé',
    'failed' => 'Non validé',
    'expired' => 'Expiré',
    'revoked' => 'Révoqué',
    'pending_approval' => 'En attente de validation',
];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Effectifs</p>
                    <h1 class="tc-hero-title mb-3">Assignations &amp; progression</h1>
                    <p class="text-slate-600 text-sm">Sélectionnez une formation pour afficher les inscriptions.</p>
                </header>

                <div class="tc-panel p-5 md:p-6">
                    <form method="get" action="<?= url('admin/training/enrollments') ?>" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-2">Formation</label>
                            <select name="course_id" class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium bg-white shadow-sm min-w-[240px]" onchange="this.form.submit()">
                                <option value="0">— Choisir —</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $selectedCourseId === (int) $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if ($selectedCourseId && empty($enrollments)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucune inscription pour cette formation.</div>
                <?php elseif ($selectedCourseId): ?>
                <div class="tc-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Statut</th>
                                <th>Type</th>
                                <th>Motivation</th>
                                <th>Assigné le</th>
                                <th>Expire</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $e): ?>
                            <tr>
                                <td class="font-medium text-slate-900"><?= htmlspecialchars($e['display_name'] ?? $e['email'] ?? '') ?></td>
                                <td>
                                    <?php $st = (string) ($e['status'] ?? ''); $stLab = $enrollmentStatusLabels[$st] ?? $st; ?>
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase rounded-full <?= $st === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($st === 'revoked' || $st === 'expired' ? 'bg-slate-200 text-slate-700' : ($st === 'pending_approval' ? 'bg-violet-100 text-violet-900' : 'bg-amber-100 text-amber-900')) ?>"><?= htmlspecialchars($stLab) ?></span>
                                </td>
                                <td class="text-slate-600"><?php
                                    $at = (string) ($e['assignment_type'] ?? '');
                                $atLab = match ($at) {
                                    'manual' => 'Manuel',
                                    'self_enroll' => 'Auto-inscription',
                                    'role' => 'Par rôle',
                                    'unit' => 'Par unité',
                                    'campaign' => 'Campagne',
                                    default => $at,
                                };
                                echo htmlspecialchars($atLab);
                                ?></td>
                                <td class="text-slate-600 text-sm max-w-xs">
                                    <?php
                                    $mot = trim((string) ($e['motivation_text'] ?? ''));
                                    if ($mot === '') {
                                        echo '—';
                                    } else {
                                        $ex = mb_strlen($mot) > 120 ? mb_substr($mot, 0, 117) . '…' : $mot;
                                        ?>
                                    <span title="<?= htmlspecialchars($mot) ?>"><?= htmlspecialchars($ex) ?></span>
                                    <?php } ?>
                                </td>
                                <td class="text-slate-500 text-sm"><?= !empty($e['assigned_at']) ? date('d/m/Y', strtotime($e['assigned_at'])) : '—' ?></td>
                                <td class="text-slate-500 text-sm"><?= !empty($e['expires_at']) ? date('d/m/Y', strtotime($e['expires_at'])) : '—' ?></td>
                                <td class="text-slate-600 text-sm">
                                    <?php
                                    $eid = (int) ($e['id'] ?? 0);
                                    $canAct = $st === 'pending_approval' && $eid > 0 && !empty($trainingEnrollmentApprovalRights[$eid]);
                                    ?>
                                    <?php if ($canAct): ?>
                                    <div class="flex flex-wrap gap-2">
                                        <form method="post" action="<?= url('admin/training/enrollments/' . $eid . '/approve') ?>" class="inline">
                                            <?= \App\Core\Csrf::field() ?>
                                            <button type="submit" class="px-2 py-1 text-[10px] font-black uppercase rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Valider</button>
                                        </form>
                                        <form method="post" action="<?= url('admin/training/enrollments/' . $eid . '/decline') ?>" class="inline" onsubmit="return confirm('Refuser cette inscription ?');">
                                            <?= \App\Core\Csrf::field() ?>
                                            <button type="submit" class="px-2 py-1 text-[10px] font-black uppercase rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Refuser</button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-slate-500 text-sm">Choisissez une formation ci-dessus.</p>
                <?php endif; ?>

                <p class="text-sm text-slate-500">
                    <a href="<?= url('admin/training') ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Vue d’ensemble</a>
                </p>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
