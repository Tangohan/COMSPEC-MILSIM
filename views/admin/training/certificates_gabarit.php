<?php
declare(strict_types=1);
$tpl = is_array($tpl ?? null) ? $tpl : [];
$trainingCertificatePdfAvailable = (bool) ($trainingCertificatePdfAvailable ?? false);
$certLayoutShowFinalScore = (bool) ($certLayoutShowFinalScore ?? true);
$certLayoutShowValidUntil = (bool) ($certLayoutShowValidUntil ?? true);
require base_path('views/admin/training/partials/command_shell_open.php');

$name = (string) ($tpl['name'] ?? 'Modèle par défaut');
$headline = (string) ($tpl['headline'] ?? 'Attestation de formation');
$subtitle = (string) ($tpl['subtitle'] ?? '');
$footer = (string) ($tpl['footer_legal'] ?? '');
$primary = (string) ($tpl['primary_hex'] ?? '#0f172a');
$accent = (string) ($tpl['accent_hex'] ?? '#059669');
$hasLogo = !empty($tpl['logo_relative_path']);
$hasBg = !empty($tpl['background_relative_path']);
$exemplePdfUrl = training_lms_admin_url('certificates/gabarit/exemple-pdf');
$fichierLogoUrl = training_lms_admin_url('certificates/gabarit/fichier') . '?type=logo';
$fichierFondUrl = training_lms_admin_url('certificates/gabarit/fichier') . '?type=fond';
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Personnalisation</p>
                    <h1 class="tc-hero-title mb-3">Gabarit des attestations PDF</h1>
                    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
                        Textes, couleurs et visuels utilisés pour produire automatiquement le document après validation d’un parcours certifiant.
                        Les images sont enregistrées de façon sécurisée pour votre communauté (JPEG, PNG ou WebP, jusqu’à 4&nbsp;Mo).
                    </p>
                    <?php if ($trainingCertificatePdfAvailable): ?>
                    <p class="mt-4 inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-950">
                        <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-600" aria-hidden="true"></span>
                        La génération des documents PDF est disponible sur ce serveur.
                    </p>
                    <?php else: ?>
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        La génération des documents PDF n’est pas disponible sur ce serveur. Vous pouvez enregistrer le gabarit, mais les fichiers ne seront pas produits tant que l’environnement n’est pas corrigé.
                    </p>
                    <?php endif; ?>
                </header>

                <div class="tc-panel p-6 md:p-8">
                    <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)] xl:items-start">
                        <form id="cert-gabarit-form" method="post" action="<?= htmlspecialchars(training_lms_admin_url('certificates/gabarit')) ?>" enctype="multipart/form-data" class="space-y-8 min-w-0">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Nom du modèle (usage interne)</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" maxlength="120" data-preview-ignore="1">
                                <p class="mt-1 text-xs text-slate-500">Visible uniquement dans l’équipe ; n’apparaît pas sur le document remis à l’apprenant.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Titre principal</label>
                                <input type="text" name="headline" id="fld-headline" value="<?= htmlspecialchars($headline) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" maxlength="255">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Sous-titre (optionnel)</label>
                                <input type="text" name="subtitle" id="fld-subtitle" value="<?= htmlspecialchars($subtitle) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" maxlength="255">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Mentions en pied de page (optionnel)</label>
                                <textarea name="footer_legal" id="fld-footer" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Coordonnées, rappel de vérification…"><?= htmlspecialchars($footer) ?></textarea>
                            </div>

                            <fieldset class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                                <legend class="px-1 text-sm font-semibold text-slate-800">Détails affichés sur le document</legend>
                                <input type="hidden" name="layout_show_final_score" value="0">
                                <label class="flex items-start gap-3 text-sm text-slate-700 cursor-pointer">
                                    <input type="checkbox" name="layout_show_final_score" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" <?= $certLayoutShowFinalScore ? 'checked' : '' ?>>
                                    <span>Afficher le score final (pourcentage réussi sur le parcours)</span>
                                </label>
                                <input type="hidden" name="layout_show_valid_until" value="0">
                                <label class="flex items-start gap-3 text-sm text-slate-700 cursor-pointer">
                                    <input type="checkbox" name="layout_show_valid_until" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" <?= $certLayoutShowValidUntil ? 'checked' : '' ?>>
                                    <span>Afficher la date de fin de validité lorsqu’elle est définie pour le parcours</span>
                                </label>
                            </fieldset>

                            <div class="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-800 mb-1">Couleur principale</label>
                                    <input type="color" name="primary_hex" id="fld-primary" value="<?= htmlspecialchars(strlen($primary) === 7 ? $primary : '#0f172a') ?>" class="h-10 w-full max-w-[120px] cursor-pointer rounded border border-slate-200">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-800 mb-1">Couleur d’accent</label>
                                    <input type="color" name="accent_hex" id="fld-accent" value="<?= htmlspecialchars(strlen($accent) === 7 ? $accent : '#059669') ?>" class="h-10 w-full max-w-[120px] cursor-pointer rounded border border-slate-200">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Logo (optionnel)</label>
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600">
                                <?php if ($hasLogo): ?>
                                <div class="mt-3 flex flex-wrap items-center gap-4">
                                    <img src="<?= htmlspecialchars($fichierLogoUrl) ?>" alt="" class="max-h-16 max-w-[200px] rounded border border-slate-200 bg-white object-contain p-1">
                                    <label class="flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" name="remove_logo" value="1"> Retirer le logo actuel
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Image de fond (optionnel)</label>
                                <input type="file" name="background" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600">
                                <?php if ($hasBg): ?>
                                <div class="mt-3 flex flex-wrap items-center gap-4">
                                    <img src="<?= htmlspecialchars($fichierFondUrl) ?>" alt="" class="h-20 w-32 rounded border border-slate-200 object-cover">
                                    <label class="flex items-center gap-2 text-sm text-slate-600">
                                        <input type="checkbox" name="remove_background" value="1"> Retirer l’image de fond actuelle
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                <p class="text-sm font-semibold text-slate-800 mb-2">Contenu inséré automatiquement</p>
                                <p class="text-xs text-slate-600 leading-relaxed">Sur chaque attestation réelle, le système ajoute&nbsp;: le nom (ou l’identifiant affiché) de l’apprenant, l’intitulé du parcours, une référence unique, la date de délivrance, et selon le parcours la date limite de validité et le score final. Ces éléments ne se configurent pas ici&nbsp;: ils proviennent de la formation et du dossier de la personne.</p>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="tc-btn-primary tc-btn-emerald">Enregistrer</button>
                                <?php if ($trainingCertificatePdfAvailable): ?>
                                <a href="<?= htmlspecialchars($exemplePdfUrl) ?>" class="tc-btn-primary tc-btn-ghost">Télécharger un PDF d’exemple</a>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars(training_lms_admin_url('certificates')) ?>" class="tc-btn-primary tc-btn-ghost">Liste des attestations</a>
                            </div>
                        </form>

                        <aside class="min-w-0 space-y-3 xl:sticky xl:top-6">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aperçu (écran)</p>
                            <div id="cert-preview-wrap" class="rounded-2xl border border-slate-200 bg-slate-100/80 p-3 shadow-inner">
                                <div id="cert-preview" class="relative overflow-hidden rounded-xl border-[3px] shadow-sm" style="border-color: <?= htmlspecialchars(strlen($accent) === 7 ? $accent : '#059669') ?>; min-height: 280px;">
                                    <?php if ($hasBg): ?>
                                    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('<?= htmlspecialchars($fichierFondUrl) ?>');"></div>
                                    <div class="absolute inset-0 bg-white/90"></div>
                                    <?php else: ?>
                                    <div class="absolute inset-0 bg-white"></div>
                                    <?php endif; ?>
                                    <div class="relative p-5 text-left">
                                        <?php if ($hasLogo): ?>
                                        <img src="<?= htmlspecialchars($fichierLogoUrl) ?>" alt="" class="mb-3 max-h-14 w-auto object-contain" id="cert-preview-logo" data-has-logo="1">
                                        <?php else: ?>
                                        <div class="mb-3 h-10 w-24 rounded bg-slate-200/80 text-[10px] text-slate-500 flex items-center justify-center" id="cert-preview-logo-placeholder">Logo</div>
                                        <?php endif; ?>
                                        <h2 class="text-lg font-bold leading-tight mb-1" id="cert-preview-headline" style="color: <?= htmlspecialchars(strlen($primary) === 7 ? $primary : '#0f172a') ?>;"><?= htmlspecialchars($headline) ?></h2>
                                        <p class="text-xs text-slate-600 mb-4 <?= $subtitle === '' ? 'hidden' : '' ?>" id="cert-preview-sub"><?= htmlspecialchars($subtitle) ?></p>
                                        <p class="text-sm mb-3" id="cert-preview-learner-row" style="color: <?= htmlspecialchars(strlen($primary) === 7 ? $primary : '#0f172a') ?>;"><span class="font-normal">Décernée à </span><strong id="cert-preview-learner">Exemple de participant</strong></p>
                                        <p class="text-base font-bold mb-3" id="cert-preview-course" style="color: <?= htmlspecialchars(strlen($accent) === 7 ? $accent : '#059669') ?>;">Exemple de parcours certifiant</p>
                                        <div class="space-y-1 text-xs text-slate-500">
                                            <p>Référence : DEMO-0001</p>
                                            <p>Délivrée le <?= htmlspecialchars(date('d/m/Y')) ?></p>
                                            <p id="cert-preview-expires" class="<?= $certLayoutShowValidUntil ? '' : 'hidden' ?>">Valide jusqu’au <?= htmlspecialchars(date('d/m/Y', strtotime('+1 year'))) ?></p>
                                            <p id="cert-preview-score" class="<?= $certLayoutShowFinalScore ? '' : 'hidden' ?>">Score final : 88,5 %</p>
                                        </div>
                                        <div class="mt-4 border-t border-slate-200 pt-3 text-[10px] leading-snug text-slate-400 <?= $footer === '' ? 'hidden' : '' ?>" id="cert-preview-footer"><?= nl2br(htmlspecialchars($footer)) ?></div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed">L’aperçu reflète textes et couleurs. La mise en page du fichier PDF peut légèrement différer (marges, césures).</p>
                        </aside>
                    </div>
                </div>

                <p class="text-sm text-slate-500">
                    <a href="<?= htmlspecialchars(training_lms_admin_url()) ?>" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-emerald-800">← Vue d’ensemble</a>
                </p>
<script>
(function () {
  var form = document.getElementById('cert-gabarit-form');
  if (!form) return;
  var headline = document.getElementById('fld-headline');
  var subtitle = document.getElementById('fld-subtitle');
  var footer = document.getElementById('fld-footer');
  var primary = document.getElementById('fld-primary');
  var accent = document.getElementById('fld-accent');
  var ph = document.getElementById('cert-preview-headline');
  var ps = document.getElementById('cert-preview-sub');
  var pf = document.getElementById('cert-preview-footer');
  var wrap = document.getElementById('cert-preview');
  var courseEl = document.getElementById('cert-preview-course');
  var expEl = document.getElementById('cert-preview-expires');
  var scoreEl = document.getElementById('cert-preview-score');
  var chkExp = form.querySelector('input[name="layout_show_valid_until"][type="checkbox"]');
  var chkScore = form.querySelector('input[name="layout_show_final_score"][type="checkbox"]');

  function sync() {
    if (ph && headline) ph.textContent = headline.value || '—';
    if (ps && subtitle) {
      ps.textContent = subtitle.value || '';
      ps.classList.toggle('hidden', !(subtitle.value && subtitle.value.trim()));
    }
    if (pf && footer) {
      pf.innerHTML = (footer.value || '').replace(/\n/g, '<br>');
      pf.classList.toggle('hidden', !(footer.value && footer.value.trim()));
    }
    if (primary && ph) ph.style.color = primary.value;
    var learnerRow = document.getElementById('cert-preview-learner-row');
    if (primary && learnerRow) learnerRow.style.color = primary.value;
    if (accent && wrap) wrap.style.borderColor = accent.value;
    if (accent && courseEl) courseEl.style.color = accent.value;
    if (expEl && chkExp) expEl.classList.toggle('hidden', !chkExp.checked);
    if (scoreEl && chkScore) scoreEl.classList.toggle('hidden', !chkScore.checked);
  }
  ['input', 'change'].forEach(function (ev) {
    form.addEventListener(ev, function (e) {
      if (e.target && e.target.getAttribute('data-preview-ignore') === '1') return;
      sync();
    });
  });
  sync();
})();
</script>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
