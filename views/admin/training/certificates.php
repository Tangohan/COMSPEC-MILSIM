<?php
$certificates = $certificates ?? [];
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Attestations</p>
                    <h1 class="tc-hero-title mb-3">Certificats délivrés</h1>
                    <p class="text-slate-600 text-sm">Dernières émissions (limite affichage 200).</p>
                </header>

                <?php if (empty($certificates)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucun certificat délivré.</div>
                <?php else: ?>
                <div class="tc-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Formation</th>
                                <th>Délivré le</th>
                                <th>Expire</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certificates as $c): ?>
                            <tr>
                                <td class="font-mono text-sm"><?= htmlspecialchars($c['certificate_number'] ?? '') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($c['course_title'] ?? '') ?></td>
                                <td class="text-slate-600 text-sm"><?= !empty($c['issued_at']) ? date('d/m/Y', strtotime($c['issued_at'])) : '—' ?></td>
                                <td class="text-slate-600 text-sm"><?= !empty($c['expires_at']) ? date('d/m/Y', strtotime($c['expires_at'])) : '—' ?></td>
                                <td>
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase rounded-full <?= ($c['status'] ?? '') === 'valid' ? 'bg-emerald-100 text-emerald-800' : (($c['status'] ?? '') === 'revoked' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700') ?>"><?= htmlspecialchars($c['status'] ?? '') ?></span>
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
