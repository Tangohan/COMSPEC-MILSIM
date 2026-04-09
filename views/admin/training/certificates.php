<?php
$certificates = $certificates ?? [];
$trainingCertificatesPdfReady = $trainingCertificatesPdfReady ?? class_exists(\Dompdf\Dompdf::class);
$trainingCertificatesPendingPdf = (int) ($trainingCertificatesPendingPdf ?? 0);
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
                    <div class="text-slate-600 text-sm space-y-3 max-w-3xl leading-relaxed">
                        <p>Les attestations des parcours <strong class="font-semibold text-slate-800">certifiants</strong> sont <strong class="font-semibold text-slate-800">créées automatiquement</strong> dès que l’apprenant termine le parcours (leçons et validations requises). La colonne « Délivré par » affiche <strong class="font-semibold text-slate-800">Complétion automatique</strong> lorsque personne du staff n’a déclenché l’émission à la main.</p>
                        <p>Le <strong class="font-semibold text-slate-800">fichier PDF</strong> est généré sur le serveur à partir du <a href="<?= htmlspecialchars(training_lms_admin_url('certificates/gabarit')) ?>" class="font-semibold text-emerald-700 hover:underline">gabarit de la communauté</a>. Tant que le PDF n’est pas prêt, la colonne « Document » indique « En attente ».</p>
                        <?php if (!$trainingCertificatesPdfReady): ?>
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950 text-sm">La génération PDF n’est pas disponible sur cette installation (composant serveur manquant). Les attestations restent enregistrées, mais les fichiers ne pourront pas être produits tant que l’environnement n’est pas complété.</p>
                        <?php elseif ($trainingCertificatesPendingPdf > 0): ?>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/ressources/training/certificates/generer-documents')) ?>" class="flex flex-wrap items-center gap-3">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Générer les PDF en attente (<?= (int) $trainingCertificatesPendingPdf ?>)</button>
                            <span class="text-xs text-slate-500">Jusqu’à 80 fichiers par action, les plus anciens en premier.</span>
                        </form>
                        <?php endif; ?>
                        <p class="text-xs text-slate-500">Liste limitée aux 200 dernières émissions.<?php if (!empty($trainingCmdCanEditContent)): ?> · <a href="<?= htmlspecialchars(training_lms_admin_url('certificates/gabarit')) ?>" class="font-semibold text-emerald-700 hover:underline">Personnaliser le gabarit PDF</a><?php endif; ?></p>
                    </div>
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
                                $pdfRel = trim((string) ($c['pdf_path'] ?? ''));
                                $hasPdf = $pdfRel !== '' && is_file(base_path($pdfRel));
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
