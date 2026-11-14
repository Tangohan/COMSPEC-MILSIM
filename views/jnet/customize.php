<?php
declare(strict_types=1);

/**
 * JNET — personnalisation de l'espace d'une unité (JnetPortalController::customizeSpace). Formulaire classique, qui
 * marche sans JavaScript ; jnet_unit_space.js met l'aperçu à jour pendant la saisie.
 *
 * @var int $customizeUnitId
 * @var string $customizeUnit
 * @var array<string, mixed> $customizeSettings
 * @var array<string, mixed> $customizeLook
 * @var array{accent: string, motto: string, badge: mixed} $customizeOrbat
 * @var bool $customizeReady
 */
use App\Services\Jnet\JnetUnitSpaceSettings as S;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$set = is_array($customizeSettings ?? null) ? $customizeSettings : S::defaults();
$look = is_array($customizeLook ?? null) ? $customizeLook : S::look($set, []);
$orbat = is_array($customizeOrbat ?? null) ? $customizeOrbat : ['accent' => '', 'motto' => '', 'badge' => null];
$unit = (string) ($customizeUnit ?? 'Unité');
$accentCustom = (string) ($set['accent_color'] ?? '') !== '';
$emblemUrl = S::publicUrl((string) ($set['emblem_path'] ?? ''));
$bannerUrl = S::publicUrl((string) ($set['banner_path'] ?? ''));
$defaultAccent = (string) $orbat['accent'] !== '' ? (string) $orbat['accent'] : '';
?>
<div class="jn-space jn-space--<?= $h($look['theme']) ?>" style="<?= $h(S::cssVars($look)) ?>" data-jn-custom
     data-themes="<?= $h((string) json_encode(S::THEMES, JSON_UNESCAPED_UNICODE)) ?>"
     data-orbat-accent="<?= $h($defaultAccent) ?>"
     data-orbat-motto="<?= $h((string) $orbat['motto']) ?>">
<div class="jn-custom">
    <header class="jn-custom__head">
        <div>
            <h1>Personnaliser l’espace de <?= $h($unit) ?></h1>
            <p>Thème, couleur, insigne et mot d’accueil : les membres les voient à l’entrée de l’espace et en haut de sa page.</p>
        </div>
        <a class="jn-btn" href="<?= $h((string) ($customizeBack ?? url('jnet'))) ?>">Retour à l’espace</a>
    </header>

    <?php if (empty($customizeReady)): ?>
        <p class="jnet-flash jnet-flash--err" role="status" style="grid-column: 1 / -1">La personnalisation arrive avec la prochaine mise à jour du site (étape jnet_unit_spaces à lancer dans run-migrations).</p>
    <?php endif; ?>

    <form class="jn-custom__form" method="post" action="<?= $h((string) ($customizeAction ?? '')) ?>" enctype="multipart/form-data">
        <?= \App\Core\Csrf::field() ?>

        <fieldset class="jn-custom__group">
            <legend>Thème</legend>
            <div class="jn-themes">
                <?php foreach (S::THEMES as $key => $t): ?>
                    <label class="jn-theme" style="--sw-bg: <?= $h($t['bg']) ?>; --sw-panel: <?= $h($t['panel']) ?>; --sw-accent: <?= $h($t['accent']) ?>">
                        <input type="radio" name="theme" value="<?= $h($key) ?>"<?= $look['theme'] === $key ? ' checked' : '' ?>>
                        <span class="jn-theme__swatch" aria-hidden="true"></span>
                        <span class="jn-theme__name"><?= $h($t['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="jn-custom__field">
                <span class="jn-custom__label">Couleur de l’unité</span>
                <div class="jn-custom__color">
                    <label class="jn-check">
                        <input type="checkbox" name="accent_custom" value="1"<?= $accentCustom ? ' checked' : '' ?> data-jn-accent-toggle>
                        <span>Choisir une couleur</span>
                    </label>
                    <label class="jn-check">
                        <span class="sr-only">Couleur</span>
                        <input type="color" name="accent_color" value="<?= $h($accentCustom ? (string) $set['accent_color'] : (string) $look['accent']) ?>" data-jn-accent>
                    </label>
                </div>
                <p class="jn-custom__hint">Sans couleur choisie : <?= $defaultAccent !== '' ? 'celle de l’unité dans l’ORBAT' : 'celle du thème' ?>.</p>
            </div>
        </fieldset>

        <fieldset class="jn-custom__group">
            <legend>Textes</legend>
            <div class="jn-custom__field">
                <label for="jn-tagline">Devise</label>
                <input type="text" id="jn-tagline" name="tagline" maxlength="<?= S::TAGLINE_MAX ?>" value="<?= $h((string) ($set['tagline'] ?? '')) ?>" placeholder="<?= $h((string) $orbat['motto'] !== '' ? (string) $orbat['motto'] : 'Ex. : Toujours prêts') ?>" data-jn-tagline>
                <p class="jn-custom__hint">Vide : <?= (string) $orbat['motto'] !== '' ? 'la devise de l’ORBAT' : 'pas de devise' ?>.</p>
            </div>
            <div class="jn-custom__field">
                <label for="jn-welcome">Mot d’accueil</label>
                <textarea id="jn-welcome" name="welcome_text" maxlength="<?= S::WELCOME_MAX ?>" placeholder="Consignes du moment, rappel des horaires, lien utile…"><?= $h((string) ($set['welcome_text'] ?? '')) ?></textarea>
                <p class="jn-custom__hint">Affiché à l’entrée et en haut de l’espace. <?= S::WELCOME_MAX ?> caractères au plus.</p>
            </div>
            <div class="jn-custom__field">
                <label for="jn-access">Mention d’accès</label>
                <input type="text" id="jn-access" name="access_label" maxlength="<?= S::ACCESS_LABEL_MAX ?>" value="<?= $h((string) ($set['access_label'] ?? '')) ?>" placeholder="<?= $h(S::DEFAULT_ACCESS_LABEL) ?>" data-jn-access>
                <p class="jn-custom__hint">Le bandeau en haut de l’écran d’entrée, par exemple « Diffusion restreinte ».</p>
            </div>
        </fieldset>

        <fieldset class="jn-custom__group">
            <legend>Images</legend>
            <div class="jn-custom__field">
                <label for="jn-emblem">Insigne</label>
                <div class="jn-custom__image">
                    <?php if ($emblemUrl !== null): ?>
                        <span class="jn-custom__thumb" style="background-image: url('<?= $h($emblemUrl) ?>')" aria-hidden="true"></span>
                    <?php endif; ?>
                    <input type="file" id="jn-emblem" name="emblem" accept="image/png,image/jpeg,image/webp" data-jn-emblem>
                    <?php if ($emblemUrl !== null): ?>
                        <label class="jn-check"><input type="checkbox" name="remove_emblem" value="1"><span>Retirer</span></label>
                    <?php endif; ?>
                </div>
                <p class="jn-custom__hint">PNG transparent de préférence. Sans insigne : <?= !empty($orbat['badge']) ? 'celui de l’ORBAT' : 'les initiales de l’unité' ?>.</p>
            </div>
            <div class="jn-custom__field">
                <label for="jn-banner">Bannière</label>
                <div class="jn-custom__image">
                    <?php if ($bannerUrl !== null): ?>
                        <span class="jn-custom__thumb jn-custom__thumb--wide" style="background-image: url('<?= $h($bannerUrl) ?>')" aria-hidden="true"></span>
                    <?php endif; ?>
                    <input type="file" id="jn-banner" name="banner" accept="image/png,image/jpeg,image/webp" data-jn-banner>
                    <?php if ($bannerUrl !== null): ?>
                        <label class="jn-check"><input type="checkbox" name="remove_banner" value="1"><span>Retirer</span></label>
                    <?php endif; ?>
                </div>
                <p class="jn-custom__hint">Photo de fond de l’écran d’entrée et du haut de l’espace, en paysage. 8 Mo au plus.</p>
            </div>
        </fieldset>

        <fieldset class="jn-custom__group">
            <legend>Entrée</legend>
            <label class="jn-check">
                <input type="checkbox" name="entry_screen" value="1"<?= !empty($set['entry_screen']) ? ' checked' : '' ?>>
                <span>Montrer l’écran d’entrée à la première visite de la session</span>
            </label>
        </fieldset>

        <?php if (!empty($customizeModulesReady)): ?>
        <fieldset class="jn-custom__group">
            <legend>Rubriques affichées</legend>
            <input type="hidden" name="modules_present" value="1">
            <?php $hiddenModules = is_array($set['hidden_modules'] ?? null) ? $set['hidden_modules'] : []; ?>
            <?php foreach (\App\Services\Jnet\JnetUnitWorkspaceService::HIDEABLE as $moduleKey => $moduleLabel): ?>
                <label class="jn-check">
                    <input type="checkbox" name="modules_shown[]" value="<?= $h($moduleKey) ?>"<?= in_array($moduleKey, $hiddenModules, true) ? '' : ' checked' ?>>
                    <span><?= $h($moduleLabel) ?></span>
                </label>
            <?php endforeach; ?>
            <p class="jn-custom__hint">Une rubrique masquée disparaît des onglets de l’espace ; son contenu est conservé.</p>
        </fieldset>
        <?php endif; ?>

        <div class="jn-custom__actions">
            <button type="submit" name="action" value="save" class="jn-btn jn-btn--primary">Enregistrer</button>
            <?php if (!empty($set['custom'])): ?>
                <button type="submit" name="action" value="reset" class="jn-btn">Revenir à l’apparence par défaut</button>
            <?php endif; ?>
        </div>
    </form>

    <aside class="jn-preview" aria-label="Aperçu">
        <div class="jn-preview__frame<?= is_string($look['banner'] ?? null) ? ' has-banner' : '' ?>"<?= is_string($look['banner'] ?? null) ? ' style="--pv-banner: url(\'' . $h($look['banner']) . '\')"' : '' ?> data-jn-preview>
            <span class="jn-preview__access" data-jn-pv-access><?= $h((string) $look['access_label']) ?></span>
            <span class="jn-preview__emblem<?= is_string($look['emblem'] ?? null) ? ' has-img' : '' ?>"<?= is_string($look['emblem'] ?? null) ? ' style="background-image: url(\'' . $h($look['emblem']) . '\')"' : '' ?> data-jn-pv-emblem><?= $h((string) $look['monogram']) ?></span>
            <p class="jn-preview__name"><?= $h($unit) ?></p>
            <p class="jn-preview__tagline" data-jn-pv-tagline<?= (string) $look['tagline'] === '' ? ' hidden' : '' ?>>« <?= $h((string) $look['tagline']) ?> »</p>
            <span class="jn-preview__bar" aria-hidden="true"></span>
        </div>
        <p class="jn-preview__note">Aperçu de l’écran d’entrée. <a class="jn-link" href="<?= $h((string) ($customizePreview ?? '')) ?>">Voir l’écran réel</a> (après enregistrement).</p>
    </aside>
</div>
</div>
<script src="<?= $h(asset_url('assets/js/jnet_unit_space.js?r=202611141800')) ?>" defer></script>
