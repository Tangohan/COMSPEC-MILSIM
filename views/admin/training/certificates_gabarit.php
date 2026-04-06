<?php
declare(strict_types=1);
$tpl = is_array($tpl ?? null) ? $tpl : [];
require base_path('views/admin/training/partials/command_shell_open.php');

$name = (string) ($tpl['name'] ?? 'Modèle par défaut');
$headline = (string) ($tpl['headline'] ?? 'Attestation de formation');
$subtitle = (string) ($tpl['subtitle'] ?? '');
$footer = (string) ($tpl['footer_legal'] ?? '');
$primary = (string) ($tpl['primary_hex'] ?? '#0f172a');
$accent = (string) ($tpl['accent_hex'] ?? '#059669');
$hasLogo = !empty($tpl['logo_relative_path']);
$hasBg = !empty($tpl['background_relative_path']);
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Personnalisation</p>
                    <h1 class="tc-hero-title mb-3">Gabarit des attestations PDF</h1>
                    <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                        Textes, couleurs et visuels utilisés lors de la génération automatique du document après validation d’un parcours certifiant.
                        Les fichiers sont stockés de façon sécurisée pour votre communauté (images JPEG, PNG ou WebP, jusqu’à 4&nbsp;Mo).
                    </p>
                </header>

                <div class="tc-panel p-6 md:p-8">
                    <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('certificates/gabarit')) ?>" enctype="multipart/form-data" class="space-y-8 max-w-2xl">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-1">Nom interne du modèle</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" maxlength="120">
                            <p class="mt-1 text-xs text-slate-500">Réservé à votre équipe (n’apparaît pas sur le document).</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-1">Titre principal</label>
                            <input type="text" name="headline" value="<?= htmlspecialchars($headline) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" maxlength="255">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-1">Sous-titre (optionnel)</label>
                            <input type="text" name="subtitle" value="<?= htmlspecialchars($subtitle) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" maxlength="255">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-1">Mentions en pied de page (optionnel)</label>
                            <textarea name="footer_legal" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. coordonnées, clause de vérification…"><?= htmlspecialchars($footer) ?></textarea>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Couleur principale</label>
                                <input type="color" name="primary_hex" value="<?= htmlspecialchars(strlen($primary) === 7 ? $primary : '#0f172a') ?>" class="h-10 w-full max-w-[120px] cursor-pointer rounded border border-slate-200">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Couleur d’accent</label>
                                <input type="color" name="accent_hex" value="<?= htmlspecialchars(strlen($accent) === 7 ? $accent : '#059669') ?>" class="h-10 w-full max-w-[120px] cursor-pointer rounded border border-slate-200">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-1">Logo (optionnel)</label>
                            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600">
                            <?php if ($hasLogo): ?>
                                <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" name="remove_logo" value="1"> Retirer le logo actuel
                                </label>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-1">Image de fond (optionnel)</label>
                            <input type="file" name="background" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600">
                            <?php if ($hasBg): ?>
                                <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" name="remove_background" value="1"> Retirer l’image de fond actuelle
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="tc-btn-primary tc-btn-emerald">Enregistrer</button>
                            <a href="<?= htmlspecialchars(training_lms_admin_url('certificates')) ?>" class="tc-btn-primary tc-btn-ghost">Liste des attestations</a>
                        </div>
                    </form>
                </div>

                <p class="text-sm text-slate-500">
                    <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Vue d’ensemble</a>
                </p>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
