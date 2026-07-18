<?php
declare(strict_types=1);
$tpl = is_array($tpl ?? null) ? $tpl : [];
$trainingCertificatePdfAvailable = (bool) ($trainingCertificatePdfAvailable ?? false);
$trainingCertificatePdfHint = trim((string) ($trainingCertificatePdfHint ?? ''));
$certGabaritLogoReadable = (bool) ($certGabaritLogoReadable ?? false);
$certGabaritFondReadable = (bool) ($certGabaritFondReadable ?? false);
$certLayoutShowFinalScore = (bool) ($certLayoutShowFinalScore ?? true);
$certLayoutShowValidUntil = (bool) ($certLayoutShowValidUntil ?? true);
require base_path('views/admin/training/partials/command_shell_open.php');

$name = (string) ($tpl['name'] ?? 'Modèle par défaut');
$headline = (string) ($tpl['headline'] ?? 'Attestation de formation');
$subtitle = (string) ($tpl['subtitle'] ?? '');
$footer = (string) ($tpl['footer_legal'] ?? '');
$primary = (string) ($tpl['primary_hex'] ?? '#0f172a');
$accent = (string) ($tpl['accent_hex'] ?? '#059669');
$primarySafe = strlen($primary) === 7 ? $primary : '#0f172a';
$accentSafe = strlen($accent) === 7 ? $accent : '#059669';
$hasLogoPath = !empty($tpl['logo_relative_path']);
$hasFondPath = !empty($tpl['background_relative_path']);
$hasLogo = $hasLogoPath && $certGabaritLogoReadable;
$hasBg = $hasFondPath && $certGabaritFondReadable;
$orphanLogo = $hasLogoPath && !$certGabaritLogoReadable;
$orphanFond = $hasFondPath && !$certGabaritFondReadable;
$exemplePdfUrl = training_lms_admin_url('certificates/gabarit/exemple-pdf');
$fichierLogoUrl = training_lms_admin_url('certificates/gabarit/fichier') . '?type=logo';
$fichierFondUrl = training_lms_admin_url('certificates/gabarit/fichier') . '?type=fond';
?>
                <header class="tc-panel tc-gabarit-hero p-6 md:p-8">
                    <div class="tc-gabarit-hero__grid">
                        <div class="min-w-0">
                            <p class="tc-kicker">Personnalisation</p>
                            <h1 class="tc-hero-title mb-3">Gabarit des attestations</h1>
                            <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                                Textes, couleurs et visuels utilisés pour produire automatiquement le document
                                remis après validation d’un parcours certifiant. Formats acceptés : JPEG, PNG ou WebP
                                (jusqu’à 4&nbsp;Mo).
                            </p>
                        </div>
                        <div class="tc-gabarit-hero__actions">
                            <?php if ($trainingCertificatePdfAvailable): ?>
                            <a href="<?= htmlspecialchars($exemplePdfUrl) ?>" class="tc-btn-primary tc-btn-emerald">Télécharger un PDF d’exemple</a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars(training_lms_admin_url('certificates')) ?>" class="tc-btn-primary tc-btn-ghost">Liste des attestations</a>
                        </div>
                    </div>

                    <?php if ($trainingCertificatePdfAvailable): ?>
                    <p class="tc-gabarit-status tc-gabarit-status--ok mt-6" role="status">
                        <span class="tc-gabarit-status__dot" aria-hidden="true"></span>
                        La génération des documents PDF est disponible sur ce serveur.
                    </p>
                    <?php else: ?>
                    <p class="tc-gabarit-status tc-gabarit-status--warn mt-6" role="alert">
                        <?= htmlspecialchars(
                            $trainingCertificatePdfHint !== ''
                                ? $trainingCertificatePdfHint
                                : 'La génération des documents PDF n’est pas prête sur ce serveur. Vous pouvez enregistrer le gabarit, mais aucun PDF ne sera produit tant que l’environnement n’est pas corrigé.'
                        ) ?>
                    </p>
                    <?php endif; ?>

                    <?php if ($orphanLogo || $orphanFond): ?>
                    <p class="tc-gabarit-status tc-gabarit-status--danger mt-4" role="alert">
                        <strong class="font-semibold">Image manquante sur le serveur.</strong>
                        <?php if ($orphanLogo): ?>Le logo est encore associé au gabarit mais n’est plus accessible (réimportez-le ou cochez « Retirer »). <?php endif; ?>
                        <?php if ($orphanFond): ?>L’image de fond est dans le même cas. <?php endif; ?>
                        Tant que ce n’est pas corrigé, l’aperçu peut rester incomplet.
                    </p>
                    <?php endif; ?>
                </header>

                <div class="tc-panel tc-gabarit-workspace p-6 md:p-8">
                    <div class="tc-gabarit-layout">
                        <form id="cert-gabarit-form" method="post" action="<?= htmlspecialchars(training_lms_admin_url('certificates/gabarit')) ?>" enctype="multipart/form-data" class="tc-gabarit-form min-w-0">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                            <section class="tc-gabarit-section">
                                <h2 class="tc-gabarit-section__title">Identification</h2>
                                <div class="tc-gabarit-field">
                                    <label class="tc-gabarit-label" for="fld-name">Nom du modèle (usage interne)</label>
                                    <input type="text" name="name" id="fld-name" value="<?= htmlspecialchars($name) ?>" class="tc-gabarit-input" maxlength="120" data-preview-ignore="1">
                                    <p class="tc-gabarit-help">Visible uniquement dans l’équipe ; n’apparaît pas sur le document remis à l’apprenant.</p>
                                </div>
                            </section>

                            <section class="tc-gabarit-section">
                                <h2 class="tc-gabarit-section__title">Textes du document</h2>
                                <div class="tc-gabarit-field">
                                    <label class="tc-gabarit-label" for="fld-headline">Titre principal</label>
                                    <input type="text" name="headline" id="fld-headline" value="<?= htmlspecialchars($headline) ?>" class="tc-gabarit-input" maxlength="255">
                                </div>
                                <div class="tc-gabarit-field">
                                    <label class="tc-gabarit-label" for="fld-subtitle">Sous-titre (optionnel)</label>
                                    <input type="text" name="subtitle" id="fld-subtitle" value="<?= htmlspecialchars($subtitle) ?>" class="tc-gabarit-input" maxlength="255">
                                </div>
                                <div class="tc-gabarit-field">
                                    <label class="tc-gabarit-label" for="fld-footer">Mentions en pied de page (optionnel)</label>
                                    <textarea name="footer_legal" id="fld-footer" rows="4" class="tc-gabarit-input tc-gabarit-input--area" placeholder="Coordonnées, rappel de vérification…"><?= htmlspecialchars($footer) ?></textarea>
                                </div>
                            </section>

                            <section class="tc-gabarit-section">
                                <h2 class="tc-gabarit-section__title">Détails affichés</h2>
                                <fieldset class="tc-gabarit-checks">
                                    <legend class="sr-only">Options d’affichage</legend>
                                    <input type="hidden" name="layout_show_final_score" value="0">
                                    <label class="tc-gabarit-check">
                                        <input type="checkbox" name="layout_show_final_score" value="1" <?= $certLayoutShowFinalScore ? 'checked' : '' ?>>
                                        <span>Afficher le score final (pourcentage réussi sur le parcours)</span>
                                    </label>
                                    <input type="hidden" name="layout_show_valid_until" value="0">
                                    <label class="tc-gabarit-check">
                                        <input type="checkbox" name="layout_show_valid_until" value="1" <?= $certLayoutShowValidUntil ? 'checked' : '' ?>>
                                        <span>Afficher la date de fin de validité lorsqu’elle est définie pour le parcours</span>
                                    </label>
                                </fieldset>
                            </section>

                            <section class="tc-gabarit-section">
                                <h2 class="tc-gabarit-section__title">Couleurs</h2>
                                <div class="tc-gabarit-colors">
                                    <div class="tc-gabarit-field">
                                        <label class="tc-gabarit-label" for="fld-primary">Couleur principale</label>
                                        <div class="tc-gabarit-color-row">
                                            <input type="color" name="primary_hex" id="fld-primary" value="<?= htmlspecialchars($primarySafe) ?>" class="tc-gabarit-color">
                                            <span class="tc-gabarit-color-swatch" id="swatch-primary" style="background:<?= htmlspecialchars($primarySafe) ?>" aria-hidden="true"></span>
                                        </div>
                                    </div>
                                    <div class="tc-gabarit-field">
                                        <label class="tc-gabarit-label" for="fld-accent">Couleur d’accent</label>
                                        <div class="tc-gabarit-color-row">
                                            <input type="color" name="accent_hex" id="fld-accent" value="<?= htmlspecialchars($accentSafe) ?>" class="tc-gabarit-color">
                                            <span class="tc-gabarit-color-swatch" id="swatch-accent" style="background:<?= htmlspecialchars($accentSafe) ?>" aria-hidden="true"></span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="tc-gabarit-section">
                                <h2 class="tc-gabarit-section__title">Visuels</h2>
                                <div class="tc-gabarit-field">
                                    <label class="tc-gabarit-label" for="fld-logo">Logo (optionnel)</label>
                                    <input type="file" name="logo" id="fld-logo" accept="image/jpeg,image/png,image/webp" class="tc-gabarit-file">
                                    <?php if ($hasLogoPath): ?>
                                    <div class="tc-gabarit-asset mt-3">
                                        <?php if ($hasLogo): ?>
                                        <img src="<?= htmlspecialchars($fichierLogoUrl) ?>" alt="Logo actuel du gabarit" class="tc-gabarit-asset__thumb tc-gabarit-asset__thumb--logo">
                                        <?php else: ?>
                                        <span class="tc-gabarit-asset__missing">Aperçu indisponible — choisissez une nouvelle image ou retirez la référence.</span>
                                        <?php endif; ?>
                                        <label class="tc-gabarit-check tc-gabarit-check--inline">
                                            <input type="checkbox" name="remove_logo" value="1"> Retirer le logo actuel
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="tc-gabarit-field">
                                    <label class="tc-gabarit-label" for="fld-bg">Image de fond (optionnel)</label>
                                    <input type="file" name="background" id="fld-bg" accept="image/jpeg,image/png,image/webp" class="tc-gabarit-file">
                                    <?php if ($hasFondPath): ?>
                                    <div class="tc-gabarit-asset mt-3">
                                        <?php if ($hasBg): ?>
                                        <img src="<?= htmlspecialchars($fichierFondUrl) ?>" alt="Fond actuel du gabarit" class="tc-gabarit-asset__thumb tc-gabarit-asset__thumb--bg">
                                        <?php else: ?>
                                        <span class="tc-gabarit-asset__missing">Aperçu indisponible — importez à nouveau ou retirez la référence.</span>
                                        <?php endif; ?>
                                        <label class="tc-gabarit-check tc-gabarit-check--inline">
                                            <input type="checkbox" name="remove_background" value="1"> Retirer l’image de fond actuelle
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </section>

                            <aside class="tc-gabarit-note" aria-label="Informations automatiques">
                                <p class="tc-gabarit-note__title">Contenu inséré automatiquement</p>
                                <p class="tc-gabarit-note__body">
                                    Sur chaque attestation réelle, le système ajoute le nom (ou l’identifiant affiché) de l’apprenant,
                                    l’intitulé du parcours, une référence unique, la date de délivrance, et selon le parcours
                                    la date limite de validité et le score final. Ces éléments ne se configurent pas ici :
                                    ils proviennent de la formation et du dossier de la personne.
                                </p>
                            </aside>

                            <div class="tc-gabarit-form-actions">
                                <button type="submit" class="tc-btn-primary tc-btn-emerald">Enregistrer</button>
                                <?php if ($trainingCertificatePdfAvailable): ?>
                                <a href="<?= htmlspecialchars($exemplePdfUrl) ?>" class="tc-btn-primary tc-btn-ghost">Télécharger un PDF d’exemple</a>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars(training_lms_admin_url('certificates')) ?>" class="tc-btn-primary tc-btn-ghost">Liste des attestations</a>
                            </div>
                        </form>

                        <aside class="tc-gabarit-preview-pane" aria-label="Aperçu du document">
                            <div class="tc-gabarit-preview-pane__head">
                                <p class="tc-gabarit-preview-pane__kicker">Aperçu à l’écran</p>
                                <p class="tc-gabarit-preview-pane__hint">Textes et couleurs mis à jour en direct. La mise en page du PDF peut légèrement différer.</p>
                            </div>
                            <div id="cert-preview-wrap" class="tc-gabarit-preview-frame">
                                <div id="cert-preview" class="tc-gabarit-preview-sheet" style="border-color: <?= htmlspecialchars($accentSafe) ?>;">
                                    <?php if ($hasBg): ?>
                                    <div class="tc-gabarit-preview-sheet__bg" style="background-image: url('<?= htmlspecialchars($fichierFondUrl) ?>');"></div>
                                    <div class="tc-gabarit-preview-sheet__veil"></div>
                                    <?php else: ?>
                                    <div class="tc-gabarit-preview-sheet__plain"></div>
                                    <?php endif; ?>
                                    <div class="tc-gabarit-preview-sheet__body">
                                        <?php if ($hasLogo): ?>
                                        <img src="<?= htmlspecialchars($fichierLogoUrl) ?>" alt="" class="tc-gabarit-preview-logo" id="cert-preview-logo" data-has-logo="1">
                                        <?php else: ?>
                                        <div class="tc-gabarit-preview-logo-ph" id="cert-preview-logo-placeholder">Logo</div>
                                        <?php endif; ?>
                                        <h2 class="tc-gabarit-preview-headline" id="cert-preview-headline" style="color: <?= htmlspecialchars($primarySafe) ?>;"><?= htmlspecialchars($headline) ?></h2>
                                        <p class="tc-gabarit-preview-sub <?= $subtitle === '' ? 'hidden' : '' ?>" id="cert-preview-sub"><?= htmlspecialchars($subtitle) ?></p>
                                        <p class="tc-gabarit-preview-learner" id="cert-preview-learner-row" style="color: <?= htmlspecialchars($primarySafe) ?>;">
                                            <span class="font-normal">Décernée à </span><strong id="cert-preview-learner">Exemple de participant</strong>
                                        </p>
                                        <p class="tc-gabarit-preview-course" id="cert-preview-course" style="color: <?= htmlspecialchars($accentSafe) ?>;">Exemple de parcours certifiant</p>
                                        <div class="tc-gabarit-preview-meta">
                                            <p>Référence : DEMO-0001</p>
                                            <p>Délivrée le <?= htmlspecialchars(date('d/m/Y')) ?></p>
                                            <p id="cert-preview-expires" class="<?= $certLayoutShowValidUntil ? '' : 'hidden' ?>">Valide jusqu’au <?= htmlspecialchars(date('d/m/Y', strtotime('+1 year'))) ?></p>
                                            <p id="cert-preview-score" class="<?= $certLayoutShowFinalScore ? '' : 'hidden' ?>">Score final : 88,5 %</p>
                                        </div>
                                        <div class="tc-gabarit-preview-footer <?= $footer === '' ? 'hidden' : '' ?>" id="cert-preview-footer"><?= nl2br(htmlspecialchars($footer)) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php if (!$hasLogo && !$hasBg): ?>
                            <p class="tc-gabarit-preview-empty">Aucun logo ni fond pour l’instant — le document restera lisible avec les couleurs et textes ci-dessus.</p>
                            <?php endif; ?>
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
  var swP = document.getElementById('swatch-primary');
  var swA = document.getElementById('swatch-accent');
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
    if (primary && swP) swP.style.background = primary.value;
    if (accent && wrap) wrap.style.borderColor = accent.value;
    if (accent && courseEl) courseEl.style.color = accent.value;
    if (accent && swA) swA.style.background = accent.value;
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
