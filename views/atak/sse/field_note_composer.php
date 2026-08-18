<?php
declare(strict_types=1);
/**
 * Rédacteur plein écran d'une fiche de renseignement simplifiée.
 *
 * Volontairement hors coque du portail : la surface de rédaction occupe tout
 * l'écran, comme le rédacteur de l'ATAK. Les deux se ressemblent trait pour
 * trait pour que l'opérateur ne réapprenne rien en passant de l'un à l'autre.
 *
 * @var array<string, array{label:string,hint:string}> $kinds
 * @var array<string, array{label:string,tone:string}> $themes
 * @var array<string, array{label:string,hint:string,tone:string}> $urgencies
 * @var int $bodyMaxLength
 * @var int $attachmentsMax
 * @var int $themesMax
 * @var string $defaultKind
 * @var string $authorLabel
 * @var string $observedInputValue
 * @var list<string> $errors
 * @var array<string, mixed> $draft
 * @var string $cancelUrl
 * @var string $submitUrl
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$kinds = is_array($kinds ?? null) ? $kinds : [];
$themes = is_array($themes ?? null) ? $themes : [];
$urgencies = is_array($urgencies ?? null) ? $urgencies : [];
$errors = is_array($errors ?? null) ? $errors : [];
$draft = is_array($draft ?? null) ? $draft : [];
$bodyMaxLength = (int) ($bodyMaxLength ?? 1000);
$attachmentsMax = (int) ($attachmentsMax ?? 4);
$themesMax = (int) ($themesMax ?? 4);
$defaultKind = (string) ($defaultKind ?? 'FRM');

$draftKind = (string) ($draft['note_kind'] ?? $defaultKind);
if (!isset($kinds[$draftKind])) {
    $draftKind = $defaultKind;
}
$draftThemes = is_array($draft['themes'] ?? null) ? $draft['themes'] : [];
$draftUrgency = (string) ($draft['urgency'] ?? 'routine');
if (!isset($urgencies[$draftUrgency])) {
    $draftUrgency = 'routine';
}
$draftBody = (string) ($draft['body'] ?? '');
$draftPlace = (string) ($draft['place_label'] ?? '');
$draftObserved = trim((string) ($draft['observed_at'] ?? ''));
$observedValue = $draftObserved !== ''
    ? str_replace(' ', 'T', substr($draftObserved, 0, 16))
    : (string) ($observedInputValue ?? date('Y-m-d\TH:i'));
$observedTs = strtotime(str_replace('T', ' ', $observedValue)) ?: time();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Nouvelle fiche de renseignement — SSE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_field_note.css')) ?>">
</head>
<body class="fn-body">

<form class="fn-app" method="post" action="<?= $h((string) ($submitUrl ?? url('atak/sse/fiches'))) ?>"
      enctype="multipart/form-data" id="fn-form"
      data-body-max="<?= $bodyMaxLength ?>"
      data-attachments-max="<?= $attachmentsMax ?>"
      data-themes-max="<?= $themesMax ?>">
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="lat" id="fn-lat" value="">
    <input type="hidden" name="lng" id="fn-lng" value="">

    <!-- Bandeau d'entête : date à gauche, lieu à droite. -->
    <header class="fn-topbar">
        <button type="button" class="fn-topbar-slot fn-topbar-slot--left" data-fn-open="contexte"
                aria-label="Modifier la date de l’événement">
            <span id="fn-date-label"><?= $h(date('d/m/Y', $observedTs)) ?></span>
            <small id="fn-time-label"><?= $h(date('H:i', $observedTs)) ?></small>
        </button>
        <button type="button" class="fn-topbar-slot fn-topbar-slot--right" data-fn-open="contexte"
                aria-label="Modifier le lieu de l’événement">
            <span id="fn-place-label"><?= $h($draftPlace !== '' ? mb_strtoupper($draftPlace) : 'LIEU À PRÉCISER') ?></span>
        </button>
    </header>

    <!-- Étiquettes : thèmes (couleur) puis type de fiche (bleu). -->
    <div class="fn-tagbar">
        <button type="button" class="fn-tagbar-open" data-fn-open="contexte" aria-label="Modifier les thèmes et le type de fiche">
            <span class="fn-tags" id="fn-tags-preview"></span>
            <span class="fn-tagbar-hint">Modifier</span>
        </button>
    </div>

    <?php if ($errors !== []): ?>
        <div class="fn-alert" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= $h($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Deux volets : rédaction et pièces jointes. -->
    <div class="fn-stage" id="fn-stage" data-pane="redaction">
        <button type="button" class="fn-edge fn-edge--left" data-fn-pane="redaction">
            <span>Fiche</span>
        </button>
        <button type="button" class="fn-edge fn-edge--right" data-fn-pane="pieces">
            <span id="fn-edge-attachments">Pièce(s) jointe(s) (0/<?= $attachmentsMax ?>)</span>
        </button>

        <section class="fn-pane fn-pane--redaction" aria-label="Rédaction du renseignement">
            <label class="fn-sr" for="fn-body">Renseignement</label>
            <textarea class="fn-editor" id="fn-body" name="body" maxlength="<?= $bodyMaxLength ?>"
                      placeholder="Veuillez inscrire vos informations dans ce cadre. N’hésitez pas à modifier la date, les tags ou le lieu en fonction de l’événement."
                      spellcheck="true"><?= $h($draftBody) ?></textarea>
            <div class="fn-counter" id="fn-counter">0/<?= $bodyMaxLength ?></div>
            <button type="button" class="fn-fab fn-fab--clip" data-fn-pane="pieces"
                    aria-label="Ouvrir les pièces jointes">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.5 8.5 9.7 16.3a3 3 0 0 0 4.2 4.2l7.1-7.1a5 5 0 0 0-7-7L6 13.4a7 7 0 0 0 9.9 9.9"/></svg>
            </button>
        </section>

        <section class="fn-pane fn-pane--pieces" aria-label="Pièces jointes">
            <h2 class="fn-pane-title">Pièce(s) jointe(s) <span id="fn-attachments-title">(0/<?= $attachmentsMax ?>)</span></h2>
            <p class="fn-pane-help">
                Photographies, captures d’écran ou documents qui appuient le renseignement.
                <?= $attachmentsMax ?> pièces au maximum, 5 Mo par photo et 8 Mo par document.
            </p>

            <ul class="fn-attachments" id="fn-attachments" aria-live="polite"></ul>
            <p class="fn-attachments-empty" id="fn-attachments-empty">
                Aucune pièce jointe pour l’instant. Utilisez les boutons ci-dessous.
            </p>

            <input class="fn-sr" type="file" id="fn-file-camera" name="pieces[]" accept="image/*" capture="environment" multiple>
            <input class="fn-sr" type="file" id="fn-file-gallery" name="pieces[]" accept="image/*" multiple>
            <input class="fn-sr" type="file" id="fn-file-document" name="pieces[]" accept="application/pdf,text/plain" multiple>

            <div class="fn-fab-cluster">
                <button type="button" class="fn-fab fn-fab--sm fn-fab--folder" data-fn-file="fn-file-document"
                        aria-label="Joindre un document">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l1.6 2h8.9A1.5 1.5 0 0 1 21 9.5v8A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>
                </button>
                <button type="button" class="fn-fab fn-fab--gallery" data-fn-file="fn-file-gallery"
                        aria-label="Joindre une image existante">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="m6.5 16 3.4-4 2.6 3 2.2-2.6L18.5 16z"/><circle cx="9" cy="9.2" r="1.2"/></svg>
                </button>
                <button type="button" class="fn-fab fn-fab--sm fn-fab--camera" data-fn-file="fn-file-camera"
                        aria-label="Prendre une photo">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8.5h3l1.3-2h7.4L17 8.5h3v10H4z"/><circle cx="12" cy="13" r="3"/></svg>
                </button>
                <button type="button" class="fn-fab fn-fab--sm fn-fab--collapse" data-fn-pane="redaction"
                        aria-label="Revenir à la rédaction">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12"/></svg>
                </button>
            </div>
        </section>
    </div>

    <!-- Barre d'action basse : quitter, plein écran, valider. -->
    <footer class="fn-bottombar">
        <svg class="fn-bottombar-wave" viewBox="0 0 1440 40" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 22c180-26 360 14 540 12s360-30 540-26 240 24 360 20V40H0z"/>
        </svg>
        <div class="fn-bottombar-inner">
            <a class="fn-bottom-btn" href="<?= $h((string) ($cancelUrl ?? url('atak/sse/fiches'))) ?>"
               aria-label="Quitter sans transmettre">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11 12 4l8 7"/><path d="M6.5 9.6V20h11V9.6"/></svg>
            </a>
            <button type="button" class="fn-bottom-btn" id="fn-fullscreen" aria-label="Basculer en plein écran">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
            </button>
            <button type="submit" class="fn-bottom-btn fn-bottom-btn--validate" id="fn-submit"
                    aria-label="Transmettre la fiche">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            </button>
        </div>
    </footer>

    <!-- Feuille de contexte : date, lieu, thèmes, type, urgence. -->
    <div class="fn-sheet" id="fn-sheet" hidden>
        <div class="fn-sheet-backdrop" data-fn-close="contexte"></div>
        <div class="fn-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="fn-sheet-title">
            <div class="fn-sheet-head">
                <h2 id="fn-sheet-title">Contexte de la fiche</h2>
                <button type="button" class="fn-sheet-close" data-fn-close="contexte">Fermer</button>
            </div>

            <div class="fn-sheet-body">
                <div class="fn-field">
                    <label for="fn-observed">Date et heure de l’événement</label>
                    <input type="datetime-local" id="fn-observed" name="observed_at" value="<?= $h($observedValue) ?>">
                    <p class="fn-field-help">Par défaut, l’instant de rédaction. Corrigez si vous rédigez après coup.</p>
                </div>

                <div class="fn-field">
                    <label for="fn-place">Lieu</label>
                    <input type="text" id="fn-place" name="place_label" maxlength="180"
                           value="<?= $h($draftPlace) ?>" placeholder="Commune, secteur, axe ou point de repère">
                </div>

                <div class="fn-field">
                    <label for="fn-grid">Coordonnées</label>
                    <div class="fn-field-inline">
                        <input type="text" id="fn-grid" name="grid_reference" maxlength="32"
                               placeholder="Carroyage ou repère (ex. 034 128)">
                        <button type="button" class="fn-btn" id="fn-locate">Utiliser ma position</button>
                    </div>
                    <p class="fn-field-help" id="fn-locate-status">
                        Le relevé de position est facultatif : il aide l’analyste à situer le renseignement sur la carte.
                    </p>
                </div>

                <fieldset class="fn-field">
                    <legend>Type de fiche</legend>
                    <div class="fn-choice-grid">
                        <?php foreach ($kinds as $code => $kind): ?>
                            <label class="fn-choice">
                                <input type="radio" name="note_kind" value="<?= $h($code) ?>"
                                       data-fn-kind-label="<?= $h($code) ?>"
                                    <?= $code === $draftKind ? 'checked' : '' ?>>
                                <span class="fn-choice-body">
                                    <strong><?= $h($kind['label']) ?></strong>
                                    <em><?= $h($kind['hint']) ?></em>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="fn-field">
                    <legend>Thèmes <span class="fn-legend-hint">(<?= $themesMax ?> au maximum)</span></legend>
                    <div class="fn-theme-grid" id="fn-theme-grid">
                        <?php foreach ($themes as $code => $theme): ?>
                            <label class="fn-theme fn-tone-<?= $h($theme['tone']) ?>">
                                <input type="checkbox" name="themes[]" value="<?= $h($code) ?>"
                                       data-fn-theme-label="<?= $h(mb_strtoupper($theme['label'])) ?>"
                                       data-fn-theme-tone="<?= $h($theme['tone']) ?>"
                                    <?= in_array($code, $draftThemes, true) ? 'checked' : '' ?>>
                                <span><?= $h($theme['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="fn-field">
                    <legend>Degré d’urgence</legend>
                    <div class="fn-choice-grid fn-choice-grid--tight">
                        <?php foreach ($urgencies as $code => $urgency): ?>
                            <label class="fn-choice">
                                <input type="radio" name="urgency" value="<?= $h($code) ?>"
                                    <?= $code === $draftUrgency ? 'checked' : '' ?>>
                                <span class="fn-choice-body">
                                    <strong><?= $h($urgency['label']) ?></strong>
                                    <em><?= $h($urgency['hint']) ?></em>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="fn-field">
                    <label for="fn-case-code">Rattacher à un dossier</label>
                    <input type="text" id="fn-case-code" name="case_code" maxlength="32"
                           placeholder="Référence du dossier, si vous la connaissez">
                    <p class="fn-field-help">Laissez vide si vous ne savez pas : le bureau classera la fiche.</p>
                </div>

                <p class="fn-sheet-signature">Fiche rédigée par <strong><?= $h((string) ($authorLabel ?? 'Analyste')) ?></strong>.</p>
            </div>

            <div class="fn-sheet-foot">
                <button type="button" class="fn-btn fn-btn--solid" data-fn-close="contexte">Revenir à la rédaction</button>
            </div>
        </div>
    </div>
</form>

<script src="<?= $h(asset_url('assets/js/sse-field-note-composer.js')) ?>" defer></script>
</body>
</html>
