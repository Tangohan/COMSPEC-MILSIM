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
use App\Services\Jnet\JnetSpaceRules as R;
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

        <?php if (!empty($customizeRulesReady)): ?>
        <?php
        $rules = is_array($customizeRules ?? null) ? $customizeRules : R::defaults();
        $unitsList = is_array($customizeUnits ?? null) ? $customizeUnits : [];
        $unitLabel = [];
        foreach ($unitsList as $u) {
            $unitLabel[(int) $u['id']] = (string) $u['label'];
        }
        $accessRows = is_array($rules['access'] ?? null) ? $rules['access'] : [];
        $managersList = is_array($customizeManagers ?? null) ? $customizeManagers : [];
        ?>
        <input type="hidden" name="rules_present" value="1">
        <fieldset class="jn-custom__group">
            <legend>Ordres et relais</legend>
            <label class="jn-check">
                <input type="checkbox" name="require_ack" value="1"<?= !empty($rules['require_ack']) ? ' checked' : '' ?>>
                <span><b>Prise en compte obligatoire</b><small>Un ordre que le membre n’a pas encore accusé s’affiche dans une fenêtre bloquante à l’entrée de l’espace, jusqu’à « J’ai pris connaissance ».</small></span>
            </label>
            <label class="jn-check">
                <input type="checkbox" name="auto_relay" value="1"<?= !empty($rules['auto_relay']) ? ' checked' : '' ?>>
                <span><b>Relais automatique aux sous-unités</b><small>Un ordre adressé à cette unité repart tout seul, une fois, vers ses sous-unités directes, tel quel et avec la mention de son origine.</small></span>
            </label>
            <div class="jn-custom__field">
                <label for="jn-postmin">Publication réservée à</label>
                <select id="jn-postmin" name="post_min">
                    <?php foreach (R::POST_MIN as $key => $name): ?>
                        <option value="<?= $h($key) ?>"<?= (string) ($rules['post_min'] ?? 'member') === $key ? ' selected' : '' ?>><?= $h($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="jn-custom__hint">Qui peut publier un échange au nom de l’unité. Ce réglage resserre la règle de l’ORBAT, il ne l’élargit jamais.</p>
            </div>
        </fieldset>

        <fieldset class="jn-custom__group">
            <legend>Accès</legend>
            <p class="jn-custom__hint">Tant que la liste est vide, tous ceux qui voient l’unité dans l’ORBAT ouvrent l’espace, comme aujourd’hui. Dès qu’une unité y figure, l’espace n’est ouvert qu’à l’unité elle-même, à son encadrement, à ses sous-unités et aux unités de la liste, chacune avec son droit. Un droit ne s’applique qu’aux outils de cet espace (tâches, pages, commentaires, réponses) ; il ne donne jamais le droit de publier un ordre.</p>
            <?php if ($accessRows !== []): ?>
                <ul class="jn-access">
                    <?php foreach ($accessRows as $unitId => $level): ?>
                        <li>
                            <span><?= $unitId === 0 ? 'Toute l’organisation' : $h($unitLabel[$unitId] ?? 'Unité ' . $unitId) ?></span>
                            <label class="ja-sr" for="jn-acc-<?= (int) $unitId ?>">Droit de <?= $unitId === 0 ? 'toute l’organisation' : $h($unitLabel[$unitId] ?? 'cette unité') ?></label>
                            <select id="jn-acc-<?= (int) $unitId ?>" name="access[<?= (int) $unitId ?>]">
                                <?php foreach (R::ACCESS_LEVELS as $key => $name): ?>
                                    <option value="<?= $h($key) ?>"<?= $level === $key ? ' selected' : '' ?>><?= $h($name) ?></option>
                                <?php endforeach; ?>
                                <option value="remove">Retirer de la liste</option>
                            </select>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="jn-access__add">
                <label class="ja-sr" for="jn-acc-add-unit">Unité à autoriser</label>
                <select id="jn-acc-add-unit" name="access_add_unit">
                    <option value="none">Ajouter une unité…</option>
                    <?php if (!isset($accessRows[0])): ?><option value="0">Toute l’organisation</option><?php endif; ?>
                    <?php foreach ($unitsList as $u): ?>
                        <?php if (isset($accessRows[(int) $u['id']])) { continue; } ?>
                        <option value="<?= (int) $u['id'] ?>"><?= str_repeat('— ', max(0, min(4, (int) $u['depth'] - 1))) . $h((string) $u['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="ja-sr" for="jn-acc-add-level">Droit à lui donner</label>
                <select id="jn-acc-add-level" name="access_add_level">
                    <?php foreach (R::ACCESS_LEVELS as $key => $name): ?>
                        <option value="<?= $h($key) ?>"><?= $h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <fieldset class="jn-custom__group">
            <legend>Gestionnaires</legend>
            <p class="jn-custom__hint">Les « chefs de module » modèrent les tâches et les pages de l’espace (retirer ce qu’un autre a écrit) et y écrivent. Ils sont choisis parmi les membres de l’unité, de ses sous-unités et de son encadrement.</p>
            <?php if ($managersList !== []): ?>
                <ul class="jn-access">
                    <?php foreach ($managersList as $m): ?>
                        <li>
                            <label class="jn-check"><input type="checkbox" name="managers_keep[]" value="<?= (int) $m['id'] ?>" checked><span><?= $h((string) $m['label']) ?></span></label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="jn-custom__hint">Décochez pour retirer.</p>
            <?php endif; ?>
            <div class="jn-custom__field">
                <label for="jn-managers-add">Ajouter par indicatif</label>
                <input type="text" id="jn-managers-add" name="managers_add" maxlength="200" autocomplete="off" placeholder="Un ou plusieurs indicatifs, séparés par une virgule">
            </div>
        </fieldset>
        <?php elseif (!empty($customizeReady)): ?>
        <p class="jn-custom__hint">Les réglages d’ordres, d’accès et de gestionnaires arrivent avec la prochaine mise à jour du site (étape jnet_unit_space_rules).</p>
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
