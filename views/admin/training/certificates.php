<?php
$certificates = $certificates ?? [];
require base_path('views/admin/training/partials/command_shell_open.php');

$statusFr = static function (string $s): string {
    return match ($s) {
        'valid' => 'Valide',
        'expired' => 'Expiré',
        'revoked' => 'Retiré',
        default => $s !== '' ? $s : '—',
    };
};
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Attestations</p>
                    <h1 class="tc-hero-title mb-3">Certificats délivrés</h1>
                    <p class="text-slate-600 text-sm">Dernières émissions (affichage limité à 200). <a href="<?= htmlspecialchars(training_lms_admin_url('certificates/gabarit')) ?>" class="font-semibold text-emerald-700 hover:underline">Personnaliser le gabarit PDF</a></p>
                </header>

                <?php if (empty($certificates)): ?>
                <div class="tc-panel p-10 text-center text-slate-600">Aucun certificat délivré.</div>
                <?php else: ?>
                <div class="tc-table-wrap overflow-x-auto">
                    <table class="min-w-[720px]">
                        <thead>
                            <tr>
                                <th>Référence</th>
                                <th>Formation</th>
                                <th>Bénéficiaire</th>
                                <th>Délivré par</th>
                                <th>Délivré le</th>
                                <th>Expire</th>
                                <th>Document</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certificates as $c):
                                $learner = trim((string) ($c['learner_display_name'] ?? ''));
                                if ($learner === '') {
                                    $learner = trim((string) ($c['learner_email'] ?? ''));
                                }
                                if ($learner === '') {
                                    $learner = '—';
                                }
                                $issuer = trim((string) ($c['issued_by_display_name'] ?? ''));
                                if ($issuer === '') {
                                    $issuer = trim((string) ($c['issued_by_email'] ?? ''));
                                }
                                if ($issuer === '') {
                                    $issuer = 'Complétion automatique';
                                }
                                $hasPdf = !empty($c['pdf_path']);
                                $st = (string) ($c['status'] ?? '');
                            ?>
                            <tr>
                                <td class="font-mono text-sm"><?= htmlspecialchars($c['certificate_number'] ?? '') ?></td>
                                <td class="font-medium"><?= htmlspecialchars($c['course_title'] ?? '') ?></td>
                                <td class="text-slate-700 text-sm"><?= htmlspecialchars($learner) ?></td>
                                <td class="text-slate-600 text-sm"><?= htmlspecialchars($issuer) ?></td>
                                <td class="text-slate-600 text-sm"><?= !empty($c['issued_at']) ? date('d/m/Y', strtotime((string) $c['issued_at'])) : '—' ?></td>
                                <td class="text-slate-600 text-sm"><?= !empty($c['expires_at']) ? date('d/m/Y', strtotime((string) $c['expires_at'])) : '—' ?></td>
                                <td class="text-sm"><?= $hasPdf ? '<span class="text-emerald-700 font-semibold">Disponible</span>' : '<span class="text-slate-500">En attente</span>' ?></td>
                                <td>
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase rounded-full <?= $st === 'valid' ? 'bg-emerald-100 text-emerald-800' : ($st === 'revoked' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700') ?>"><?= htmlspecialchars($statusFr($st)) ?></span>
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
