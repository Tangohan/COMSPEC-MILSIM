<?php
declare(strict_types=1);

/**
 * Fenêtre « Fiche du niveau » (création ou modification) : cinq étapes, aperçu en direct de la carte.
 * Variables : $rolMode ('new'|'edit'), $rolCard (carte du niveau en modification), $h, $csrf, $rolReady, $rolTagsReady,
 * $rolQualifications, $rolJobs, $rolTags, $rolGradeOptions, $rolOld, $colorPresets, $templates, $roles, $rolCards.
 * Sans JavaScript, toutes les étapes s'affichent l'une sous l'autre et le formulaire s'envoie normalement.
 */

use App\Services\Rbac\AccessRoleLogoStore;
use App\Services\Rbac\AccessRoleProfileService;
use App\Support\JobRoleIconLibrary;

$isNew = $rolMode === 'new';
$dlgId = $isNew ? 'rol-new' : 'rol-edit';
$card = $isNew ? null : $rolCard;
$p = $card['profile'] ?? AccessRoleProfileService::emptyProfile();
$old = is_array($rolOld ?? null) && (($isNew && !isset($rolOld['role_id'])) || (!$isNew && (int) ($rolOld['role_id'] ?? 0) === (int) ($card['id'] ?? 0))) ? $rolOld : null;
$val = static function (string $key, mixed $current) use ($old): mixed {
    return $old !== null && array_key_exists($key, $old) ? $old[$key] : $current;
};
$qualOf = [];
foreach ($p['qualifications'] as $kind => $ids) {
    foreach ($ids as $qid) {
        $qualOf[(int) $qid] = (string) $kind;
    }
}
$qualOf = is_array($val('qualifications', null)) ? array_map('strval', (array) $val('qualifications', [])) : $qualOf;
$jobSel = array_fill_keys(array_map('intval', is_array($val('jobs', null)) ? (array) $val('jobs', []) : $p['jobs']), true);
$name = (string) $val('name', $card['name'] ?? '');
$description = (string) $val('description', $card['description'] ?? '');
$abbr = (string) $val('abbreviation', (string) ($p['abbreviation'] ?? ''));
$details = (string) $val('details', (string) $p['details']);
$icon = (string) $val('icon', (string) ($p['icon'] ?? ''));
$tagValue = (string) $val('tags', implode(', ', array_column($p['tags'], 'name')));
$sort = (string) $val('sort_order', $card !== null ? (string) (int) $p['sort_order'] : '');
$gmin = (string) $val('grade_min', $p['grade_min'] !== null ? (string) $p['grade_min'] : '');
$gmax = (string) $val('grade_max', $p['grade_max'] !== null ? (string) $p['grade_max'] : '');
$color = (string) $val('role_color', (string) ($card['color_input'] ?? ''));
$logoUrl = $card['logo_url'] ?? null;
$visual = $logoUrl !== null ? 'logo' : 'icon';
$iconLibrary = JobRoleIconLibrary::all();
$steps = [1 => 'Identité', 2 => 'Apparence', 3 => 'Étiquettes', 4 => 'Qualifications', 5 => 'Emplois et grades'];
$sid = static fn (int $n): string => $dlgId . '-s' . $n;
$help = static function (string $id, string $label, string $text) use ($h): string {
    return '<button type="button" class="rol-help" aria-expanded="false" aria-controls="' . $h($id) . '" data-rol-help>'
        . '<span aria-hidden="true">?</span><span class="rol-sr">Aide : ' . $h($label) . '</span></button>'
        . '<p class="rol-help__text" id="' . $h($id) . '" hidden>' . $h($text) . '</p>';
};
$title = $isNew ? 'Créer un niveau' : str_replace('%1', $name, 'Fiche du niveau « %1 »');
$action = $isNew ? effectifs_workspace_url('roles/nouveau') : effectifs_workspace_url('roles/fiche');
?>
<dialog class="bo-dialog rol-dialog" id="<?= $h($dlgId) ?>" aria-labelledby="<?= $h($dlgId) ?>-title" aria-describedby="<?= $h($dlgId) ?>-sub" data-rol-dialog<?= $rolOpen === ($isNew ? 'new' : 'edit') ? ' data-rol-autoopen' : '' ?>>
<form method="post" action="<?= $h($action) ?>" enctype="multipart/form-data" class="bo-dialog__form rol-form" data-rol-form novalidate>
    <div hidden data-rol-msgs>
        <span data-k="name">Indiquez un nom pour le niveau.</span>
        <span data-k="abbr">L’abrégé doit compter 2 à 8 lettres ou chiffres (ex. CDG, MED, JTAC).</span>
        <span data-k="grades">Le grade minimum doit être inférieur ou égal au grade maximum.</span>
        <span data-k="logo">Cochez la confirmation : le logo appartient à votre communauté ou est une création originale.</span>
        <span data-k="size">Logo refusé : 1 Mo au plus.</span>
        <span data-k="fields">champs renseignés</span>
        <span data-k="nodesc">Sans description</span>
    </div>
    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
    <?php if (!$isNew): ?><input type="hidden" name="role_id" value="<?= (int) $card['id'] ?>"><?php endif; ?>
    <?php if ($rolReady): ?><input type="hidden" name="profile_form" value="1"><?php endif; ?>
    <header class="bo-dialog__head">
        <div class="bo-dialog__titles">
            <h2 class="bo-dialog__title" id="<?= $h($dlgId) ?>-title"><?= $h($title) ?></h2>
            <p class="bo-dialog__sub" id="<?= $h($dlgId) ?>-sub"><?= $isNew ? 'Nommez le niveau, décrivez-le, puis réglez ses droits dans la page.' : 'La fiche décrit le niveau ; ses droits et ses membres se règlent dans la page.' ?></p>
        </div>
        <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    </header>
    <nav class="rol-steps" aria-label="Étapes de la fiche" data-rol-steps hidden>
        <ol>
            <?php foreach ($steps as $n => $label): ?>
            <li><button type="button" class="rol-steps__btn" data-rol-step="<?= $n ?>" aria-controls="<?= $h($sid($n)) ?>"<?= $n === 1 ? ' aria-current="step"' : '' ?>><span class="rol-steps__n" aria-hidden="true"><?= $n ?></span><span><?= $h($label) ?></span></button></li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <div class="bo-dialog__body rol-dialog__body">
        <div class="rol-dialog__main">
            <?php if (!$rolReady): ?>
            <p class="rol-note rol-note--warn" role="status"><?= $h(AccessRoleProfileService::NOT_READY) ?></p>
            <?php endif; ?>

            <fieldset class="rol-step" id="<?= $h($sid(1)) ?>" data-rol-panel="1">
                <legend class="rol-step__title">Identité</legend>
                <p class="rol-step__desc">Le nom et la description courte apparaissent partout où le niveau est proposé.</p>
                <div class="rol-grid">
                    <label class="rol-field rol-field--wide">
                        <span class="rol-field__label">Nom du niveau <span class="rol-req">obligatoire</span></span>
                        <input type="text" name="name" required maxlength="160" value="<?= $h($name) ?>" placeholder="Ex. Recrutement" data-rol-name autocomplete="off">
                    </label>
                    <?php if ($rolReady): ?>
                    <div class="rol-field">
                        <label class="rol-field__label" for="<?= $h($dlgId) ?>-abbr">Abrégé</label>
                        <div class="rol-field__row">
                            <input type="text" id="<?= $h($dlgId) ?>-abbr" name="abbreviation" maxlength="8" value="<?= $h($abbr) ?>" placeholder="RECR" class="rol-abbr-input" data-rol-abbr autocomplete="off" translate="no" aria-describedby="<?= $h($dlgId) ?>-abbr-help">
                            <?= $help($dlgId . '-abbr-help', 'Abrégé', '2 à 8 lettres ou chiffres, unique dans la communauté. Affiché sur la carte du niveau à côté de son nom.') ?>
                        </div>
                    </div>
                    <label class="rol-field">
                        <span class="rol-field__label">Ordre d’affichage</span>
                        <input type="number" name="sort_order" min="-999" max="999" step="1" value="<?= $h($sort) ?>" placeholder="0" inputmode="numeric">
                    </label>
                    <?php endif; ?>
                    <label class="rol-field rol-field--wide">
                        <span class="rol-field__label">Description courte</span>
                        <textarea name="description" rows="2" maxlength="500" placeholder="Ex. Accès lecture aux documents pour les nouveaux intégrés." data-rol-desc data-rol-counter="500"><?= $h($description) ?></textarea>
                        <span class="rol-counter" data-rol-counter-out aria-live="polite"></span>
                    </label>
                    <?php if ($rolReady): ?>
                    <div class="rol-field rol-field--wide">
                        <div class="rol-field__row">
                            <label class="rol-field__label" for="<?= $h($dlgId) ?>-details">Description détaillée</label>
                            <?= $help($dlgId . '-details-help', 'Description détaillée', 'Texte simple mis en forme à l’affichage : **gras**, *italique*, une ligne commençant par « - » pour une liste, une ligne vide pour un nouveau paragraphe. Aucun HTML n’est gardé.') ?>
                        </div>
                        <textarea id="<?= $h($dlgId) ?>-details" name="details" rows="5" maxlength="<?= AccessRoleProfileService::DETAILS_MAX ?>" placeholder="Ce que ce niveau permet, à qui il s’adresse, comment l’obtenir." data-rol-counter="<?= AccessRoleProfileService::DETAILS_MAX ?>"><?= $h($details) ?></textarea>
                        <span class="rol-counter" data-rol-counter-out aria-live="polite"></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($isNew): ?>
                    <label class="rol-field">
                        <span class="rol-field__label">Modèle prêt à l’emploi</span>
                        <select name="template">
                            <option value="">Aucun : copier un niveau existant</option>
                            <?php foreach ($templates as $tpl): ?>
                            <option value="<?= $h($tpl['key']) ?>" <?= (string) $val('template', '') === (string) $tpl['key'] ? 'selected' : '' ?>><?= $h($tpl['name']) ?> (Droits : <?= count($tpl['rights']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="rol-field">
                        <span class="rol-field__label">Ou copier les droits de</span>
                        <select name="copy_from">
                            <?php foreach ($roles as $row): ?>
                            <option value="<?= (int) ($row['id'] ?? 0) ?>" <?= (int) $val('copy_from', 0) === (int) ($row['id'] ?? 0) ? 'selected' : '' ?>><?= $h($row['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="rol-hint rol-field--wide">Un modèle remplace la copie. Vous ne pouvez créer que des niveaux dont vous détenez déjà tous les droits.</p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="rol-step" id="<?= $h($sid(2)) ?>" data-rol-panel="2">
                <legend class="rol-step__title">Apparence</legend>
                <p class="rol-step__desc">Une icône de la bibliothèque ou votre propre logo, et une couleur d’accent.</p>
                <?php if ($rolReady): ?>
                <div class="rol-choice" role="radiogroup" aria-label="Repère visuel">
                    <label class="rol-choice__opt"><input type="radio" name="visual" value="icon" <?= $visual === 'icon' ? 'checked' : '' ?> data-rol-visual> Icône de la bibliothèque</label>
                    <label class="rol-choice__opt"><input type="radio" name="visual" value="logo" <?= $visual === 'logo' ? 'checked' : '' ?> data-rol-visual> Logo téléversé</label>
                </div>
                <div class="rol-icons" data-rol-icons>
                    <label class="rol-field">
                        <span class="rol-field__label">Chercher une icône</span>
                        <input type="search" placeholder="Ex. radio, santé, pilote" data-rol-icon-search autocomplete="off">
                    </label>
                    <div class="rol-icons__grid" role="radiogroup" aria-label="Icône du niveau">
                        <label class="rol-icon" data-rol-icon data-search="aucune none"><input type="radio" name="icon" value="" <?= $icon === '' ? 'checked' : '' ?>><span class="rol-icon__none" aria-hidden="true">∅</span><span class="rol-icon__label">Aucune</span></label>
                        <?php foreach ($iconLibrary as $key => $meta): ?>
                        <label class="rol-icon" data-rol-icon data-search="<?= $h(mb_strtolower($key . ' ' . $meta['label'] . ' ' . $meta['group'])) ?>"><input type="radio" name="icon" value="<?= $h($key) ?>" <?= $icon === $key ? 'checked' : '' ?>><?= JobRoleIconLibrary::svg($key, 'rol-icon__svg') ?><span class="rol-icon__label"><?= $h($meta['label']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="rol-hint" data-rol-icon-empty hidden>Aucune icône ne correspond.</p>
                </div>
                <div class="rol-logo" data-rol-logo>
                    <?php if ($logoUrl !== null): ?>
                    <div class="rol-logo__current">
                        <img src="<?= $h($logoUrl) ?>" alt="Logo actuel du niveau" width="64" height="64" loading="lazy">
                        <label class="rol-check"><input type="checkbox" name="logo_remove" value="1" data-rol-logo-remove> Retirer ce logo</label>
                    </div>
                    <?php endif; ?>
                    <div class="rol-field">
                        <div class="rol-field__row">
                            <label class="rol-field__label" for="<?= $h($dlgId) ?>-logo"><?= $logoUrl !== null ? 'Remplacer le logo' : 'Logo' ?></label>
                            <?= $help($dlgId . '-logo-help', 'Logo', 'PNG, JPEG, WebP ou SVG simple, 1 Mo au plus. Le type est vérifié dans le fichier ; les métadonnées (EXIF) sont retirées ; un SVG avec script, lien ou image externe est refusé.') ?>
                        </div>
                        <input type="file" id="<?= $h($dlgId) ?>-logo" name="logo_file" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml" data-rol-logo-file data-max="<?= AccessRoleLogoStore::MAX_BYTES ?>">
                    </div>
                    <label class="rol-check"><input type="checkbox" name="logo_confirm" value="1" data-rol-logo-confirm <?= (string) $val('logo_confirm', '') === '1' ? 'checked' : '' ?>> <?= $h(AccessRoleLogoStore::CONFIRM_LABEL) ?></label>
                </div>
                <?php endif; ?>
                <fieldset class="rol-colors" data-rol-colors>
                    <legend class="rol-field__label">Couleur d’accent</legend>
                    <input type="hidden" name="role_color" value="<?= $h($color) ?>" data-rol-color-value>
                    <div class="rol-swatches">
                        <button type="button" class="rol-swatch rol-swatch--none" data-color="" aria-pressed="<?= $color === '' ? 'true' : 'false' ?>" aria-label="Aucune couleur" title="Aucune couleur"></button>
                        <?php foreach ($colorPresets as $cname => $chex): ?>
                        <button type="button" class="rol-swatch" style="--rol-dot:<?= $h($chex) ?>" data-color="<?= $h($cname) ?>" data-hex="<?= $h($chex) ?>" aria-pressed="<?= $color === $cname ? 'true' : 'false' ?>" aria-label="<?= $h(ucfirst((string) $cname)) ?>" title="<?= $h(ucfirst((string) $cname)) ?>"></button>
                        <?php endforeach; ?>
                        <label class="rol-swatch rol-swatch--free" title="Autre couleur">
                            <span class="rol-sr">Autre couleur</span>
                            <input type="color" value="<?= preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $h($color) : '#64748b' ?>" data-rol-color-free>
                        </label>
                    </div>
                </fieldset>
            </fieldset>

            <fieldset class="rol-step" id="<?= $h($sid(3)) ?>" data-rol-panel="3">
                <legend class="rol-step__title">Étiquettes</legend>
                <p class="rol-step__desc">Des mots-clés libres pour retrouver et filtrer les niveaux, partagés avec les fonctions.</p>
                <?php if ($rolTagsReady): ?>
                <label class="rol-field">
                    <span class="rol-field__label">Étiquettes</span>
                    <input type="text" name="tags" maxlength="400" value="<?= $h($tagValue) ?>" placeholder="Ex. Encadrement, Formation, Lecture seule" autocomplete="off" translate="no" data-rol-tags aria-describedby="<?= $h($dlgId) ?>-tags-hint">
                    <span class="rol-hint" id="<?= $h($dlgId) ?>-tags-hint">Séparées par des virgules, 8 au plus. Une nouvelle étiquette rejoint le catalogue partagé.</span>
                </label>
                <?php if ($rolTags !== []): ?>
                <div class="rol-suggest" data-rol-tag-suggest>
                    <span class="rol-hint">Catalogue :</span>
                    <?php foreach ($rolTags as $tag): ?>
                    <button type="button" class="rol-suggest__btn job-tag job-tag--<?= $h($tag['color'] ?? 'slate') ?>" data-rol-tag-add="<?= $h($tag['name']) ?>" translate="no"><?= $h($tag['name']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php elseif ($rolReady): ?>
                <p class="rol-note">Les étiquettes reposent sur le catalogue des fonctions, qui n’est pas encore installé sur ce serveur.</p>
                <?php endif; ?>
            </fieldset>

            <fieldset class="rol-step" id="<?= $h($sid(4)) ?>" data-rol-panel="4">
                <legend class="rol-step__title">Qualifications</legend>
                <p class="rol-step__desc">Qualifications du référentiel attendues, conseillées ou délivrées par ce niveau ; rien n’est bloqué.</p>
                <?php if ($rolReady && $rolQualifications === []): ?>
                <p class="rol-empty">Aucune qualification dans le référentiel de la communauté. <a href="<?= $h(effectifs_workspace_url('qualifications')) ?>">Ouvrir les qualifications</a></p>
                <?php elseif ($rolReady): ?>
                <label class="rol-field">
                    <span class="rol-field__label">Chercher une qualification</span>
                    <input type="search" placeholder="Ex. secourisme, pilote" data-rol-list-search="quals" autocomplete="off">
                </label>
                <ul class="rol-picklist" data-rol-list="quals">
                    <?php foreach ($rolQualifications as $qid => $q): $cur = $qualOf[(int) $qid] ?? ''; ?>
                    <li class="rol-picklist__row<?= $cur !== '' ? ' is-on' : '' ?>" data-text="<?= $h(mb_strtolower($q['name'])) ?>">
                        <label for="<?= $h($dlgId) ?>-q<?= (int) $qid ?>" class="rol-picklist__name"><span translate="no"><?= $h($q['name']) ?></span><?php if ($q['archived']): ?> <span class="rol-muted">(archivée)</span><?php endif; ?></label>
                        <select id="<?= $h($dlgId) ?>-q<?= (int) $qid ?>" name="qualifications[<?= (int) $qid ?>]" data-rol-qual>
                            <option value="">Non liée</option>
                            <?php foreach (AccessRoleProfileService::KINDS as $kind => $klabel): ?>
                            <option value="<?= $h($kind) ?>" <?= $cur === $kind ? 'selected' : '' ?>><?= $h($klabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="rol-hint" data-rol-list-empty="quals" hidden>Aucune qualification ne correspond.</p>
                <?php endif; ?>
            </fieldset>

            <fieldset class="rol-step" id="<?= $h($sid(5)) ?>" data-rol-panel="5">
                <legend class="rol-step__title">Emplois et grades</legend>
                <p class="rol-step__desc">Les emplois qui portent habituellement ce niveau et la plage de grades attendue, à titre de repère.</p>
                <?php if ($rolReady): ?>
                <div class="rol-grid">
                    <label class="rol-field">
                        <span class="rol-field__label">Grade minimum</span>
                        <select name="grade_min" data-rol-gmin>
                            <option value="">Aucun</option>
                            <?php foreach ($rolGradeOptions as $lvl => $glabel): ?><option value="<?= (int) $lvl ?>" <?= $gmin === (string) $lvl ? 'selected' : '' ?>><?= $h($glabel) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <div class="rol-field">
                        <div class="rol-field__row">
                            <label class="rol-field__label" for="<?= $h($dlgId) ?>-gmax">Grade maximum</label>
                            <?= $help($dlgId . '-grade-help', 'Grades', 'Mêmes niveaux que l’adéquation de l’organisation (codes OTAN, libellés de vos grades). Repère affiché sur la carte : aucun membre n’est bloqué.') ?>
                        </div>
                        <select id="<?= $h($dlgId) ?>-gmax" name="grade_max" data-rol-gmax>
                            <option value="">Aucun</option>
                            <?php foreach ($rolGradeOptions as $lvl => $glabel): ?><option value="<?= (int) $lvl ?>" <?= $gmax === (string) $lvl ? 'selected' : '' ?>><?= $h($glabel) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if ($rolJobs === []): ?>
                <p class="rol-empty">Aucun emploi dans la communauté. <a href="<?= $h(effectifs_workspace_url('fonctions')) ?>">Ouvrir les emplois et fonctions</a></p>
                <?php else: ?>
                <label class="rol-field">
                    <span class="rol-field__label">Chercher un emploi</span>
                    <input type="search" placeholder="Ex. infirmier, chef d’équipe" data-rol-list-search="jobs" autocomplete="off">
                </label>
                <ul class="rol-picklist rol-picklist--checks" data-rol-list="jobs">
                    <?php foreach ($rolJobs as $jid => $jname): ?>
                    <li class="rol-picklist__row<?= isset($jobSel[(int) $jid]) ? ' is-on' : '' ?>" data-text="<?= $h(mb_strtolower($jname)) ?>">
                        <label class="rol-check" translate="no"><input type="checkbox" name="jobs[]" value="<?= (int) $jid ?>" <?= isset($jobSel[(int) $jid]) ? 'checked' : '' ?> data-rol-job> <?= $h($jname) ?></label>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="rol-hint" data-rol-list-empty="jobs" hidden>Aucun emploi ne correspond.</p>
                <?php endif; ?>
                <?php endif; ?>
            </fieldset>
        </div>

        <aside class="rol-dialog__preview" aria-label="Aperçu de la carte" data-rol-preview>
            <p class="rol-preview__label">Aperçu</p>
            <div class="rol-card rol-card--preview" style="<?= ($card['color'] ?? null) !== null ? '--rol-accent:' . $h($card['color']) : '' ?>" data-rol-preview-card>
                <div class="rol-card__head">
                    <span class="rol-mark" data-rol-preview-mark><?php if ($logoUrl !== null): ?><img src="<?= $h($logoUrl) ?>" alt="" width="44" height="44"><?php elseif ($icon !== ''): ?><?= JobRoleIconLibrary::svg($icon, 'rol-mark__svg') ?><?php else: ?><span class="rol-mark__initial"><?= $h(mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1))) ?></span><?php endif; ?></span>
                    <div class="rol-card__titles">
                        <p class="rol-card__name"><span data-rol-preview-name translate="no"><?= $h($name !== '' ? $name : 'Nouveau niveau') ?></span> <span class="rol-abbr" data-rol-preview-abbr translate="no"<?= $abbr === '' ? ' hidden' : '' ?>><?= $h($abbr) ?></span></p>
                    </div>
                </div>
                <p class="rol-card__desc" data-rol-preview-desc><?php if ($description !== ''): ?><span translate="no"><?= $h($description) ?></span><?php else: ?><span class="rol-muted">Sans description</span><?php endif; ?></p>
                <p class="rol-card__tags" data-rol-preview-tags translate="no"></p>
                <div class="rol-meter" data-rol-preview-meter><span class="rol-meter__bar"><span class="rol-meter__fill" style="--rol-p:0%"></span></span><span class="rol-meter__txt" data-rol-preview-meter-txt></span></div>
            </div>
            <p class="rol-hint">La carte se met à jour pendant la saisie.</p>
        </aside>
    </div>
    <footer class="bo-dialog__foot rol-dialog__foot">
        <p class="rol-dialog__err" role="alert" data-rol-error hidden></p>
        <button type="button" class="bo-dialog__btn rol-nav" data-rol-prev hidden>Précédent</button>
        <button type="button" class="bo-dialog__btn rol-nav" data-rol-next hidden>Suivant</button>
        <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
        <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary"><?= $isNew ? 'Créer le niveau' : 'Enregistrer la fiche' ?></button>
    </footer>
</form>
</dialog>
