<?php
declare(strict_types=1);

/**
 * Effectifs › Positions administratives (EffectifsWorkspaceController::positions, données AdministrativePositionsController).
 * Les quatre positions du site, en lecture, puis celles de la communauté : création, modification, archivage.
 *
 * @var list<array<string, mixed>> $posBuiltIn
 * @var list<array<string, mixed>> $posCustom
 * @var array<string, int> $posHolders
 * @var array<string, mixed>|null $posEditing
 */
use App\Controllers\Admin\AdministrativePositionsController as P;
use App\Support\AdministrativePositionCatalog as Catalog;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = (string) ($csrfToken ?? '');
$canManage = !empty($posCanManage) && !empty($posReady);
$editing = is_array($posEditing ?? null) ? $posEditing : null;
$holders = is_array($posHolders ?? null) ? $posHolders : [];
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$tones = ['ok' => 'Vert', 'info' => 'Bleu', 'warn' => 'Orange', 'bad' => 'Rouge', 'muted' => 'Gris'];
$card = static function (array $p, bool $custom) use ($h, $holders, $canManage, $csrf): string {
    $n = (int) ($holders[$p['slug']] ?? 0);
    $facts = '';
    foreach (['mission' => 'Engagé en mission', 'service' => 'Tient le service', 'strength' => 'Effectif opérationnel'] as $k => $label) {
        $facts .= '<div><dt>' . $h($label) . '</dt><dd>' . ((string) $p[$k] !== '' ? $h($p[$k]) : '<span class="pos-empty">Non précisé</span>') . '</dd></div>';
    }
    $actions = '';
    if ($custom && $canManage) {
        $actions = '<div class="pos-card__actions"><a class="eff-rh-btn" href="' . $h(P::url() . '?modifier=' . (int) $p['id']) . '#position-form">Modifier</a>'
            . '<form method="post" action="' . $h(effectifs_workspace_url('positions/archiver')) . '"><input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">'
            . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><button type="submit" class="eff-rh-btn"' . ($n > 0 ? ' disabled title="Changez d’abord la position des membres qui la portent."' : '') . '>Archiver</button></form></div>';
    }

    return '<article class="pos-card"><header class="pos-card__head"><span class="apg__pill apg__pill--' . $h($p['tone']) . '">' . $h($p['label']) . '</span>'
        . '<span class="pos-card__count">' . $n . ' membre' . ($n > 1 ? 's' : '') . '</span></header>'
        . ((string) $p['summary'] !== '' ? '<p class="pos-card__summary">' . $h($p['summary']) . '</p>' : '')
        . ((string) $p['detail'] !== '' ? '<p class="pos-card__detail">' . $h($p['detail']) . '</p>' : '')
        . '<dl class="pos-card__facts">' . $facts . '</dl>' . $actions . '</article>';
};
?>
<?php if (empty($GLOBALS['__apg_css_loaded'])): $GLOBALS['__apg_css_loaded'] = true; ?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/administrative-positions.css')) ?>">
<?php endif; ?>
<section class="eff-rh-hero pos-hero">
    <h2 class="eff-page-title">Positions administratives</h2>
    <p class="eff-page-lead">
        La position dit où en est un membre dans la communauté. Elle ne change ni son grade, ni ses fonctions, ni ses accès.
        Un membre en porte une seule, choisie sur sa fiche.
    </p>
</section>

<?php if ($flashErr): ?><p class="bo-eff-settings__flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
<?php if ($flashOk): ?><p class="bo-eff-settings__flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
<?php if (empty($posReady)): ?>
    <p class="bo-eff-settings__flash is-bad" role="status">Créer vos propres positions arrive avec la prochaine mise à jour du site (étape tenant_administrative_positions à lancer dans run-migrations). Les quatre positions du site restent disponibles.</p>
<?php endif; ?>

<h3 class="pos-section">Positions du site</h3>
<p class="pos-section__lead">Toujours disponibles. « En formation » est donnée à l’arrivée, « En service actif » à la fin de l’accueil.</p>
<div class="pos-grid">
    <?php foreach ($posBuiltIn as $p): ?><?= $card($p, false) ?><?php endforeach; ?>
</div>

<h3 class="pos-section">Positions de votre communauté</h3>
<p class="pos-section__lead">Par exemple « En permission », « Détaché à l’état-major », « Réserviste ». Le site ne les change jamais automatiquement.</p>
<?php if ($posCustom === []): ?>
    <p class="pos-none">Aucune position créée pour l’instant.</p>
<?php else: ?>
    <div class="pos-grid">
        <?php foreach ($posCustom as $p): ?><?= $card($p, true) ?><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canManage): ?>
<form class="pos-form" id="position-form" method="post" action="<?= $h(P::url()) ?>">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
    <?php if ($editing !== null): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <h3><?= $editing !== null ? 'Modifier « ' . $h($editing['label']) . ' »' : 'Créer une position' ?></h3>
    <div class="pos-form__grid">
        <label class="pos-field"><span>Nom</span>
            <input type="text" name="label" required minlength="2" maxlength="<?= P::LABEL_MAX ?>" value="<?= $h($editing['label'] ?? '') ?>" placeholder="Ex. : En permission">
        </label>
        <fieldset class="pos-field pos-tones"><legend>Couleur</legend>
            <?php foreach ($tones as $tone => $toneLabel): ?>
                <label class="pos-tone"><input type="radio" name="tone" value="<?= $h($tone) ?>"<?= ($editing['tone'] ?? 'muted') === $tone ? ' checked' : '' ?>><span class="apg__pill apg__pill--<?= $h($tone) ?>"><?= $h($toneLabel) ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <label class="pos-field pos-field--wide"><span>En une phrase</span>
            <input type="text" name="summary" maxlength="<?= P::SUMMARY_MAX ?>" value="<?= $h($editing['summary'] ?? '') ?>" placeholder="Ex. : Absent pour une durée annoncée, revient à une date prévue.">
        </label>
        <label class="pos-field pos-field--wide"><span>Explication (facultatif)</span>
            <textarea name="detail" rows="3" maxlength="<?= P::DETAIL_MAX ?>" placeholder="Ce que cela change pour le membre et pour l’unité."><?= $h($editing['detail'] ?? '') ?></textarea>
        </label>
        <label class="pos-field"><span>Engagé en mission</span>
            <input type="text" name="mission" maxlength="<?= P::SHORT_MAX ?>" value="<?= $h($editing['mission'] ?? '') ?>" placeholder="Oui, Non, À la demande…">
        </label>
        <label class="pos-field"><span>Tient le service</span>
            <input type="text" name="service" maxlength="<?= P::SHORT_MAX ?>" value="<?= $h($editing['service'] ?? '') ?>" placeholder="Oui, Non, Sur convocation…">
        </label>
        <label class="pos-field"><span>Effectif opérationnel</span>
            <input type="text" name="strength" maxlength="<?= P::SHORT_MAX ?>" value="<?= $h($editing['strength'] ?? '') ?>" placeholder="Oui, Non…">
        </label>
    </div>
    <div class="pos-form__actions">
        <button type="submit" class="eff-rh-btn eff-rh-btn--primary"><?= $editing !== null ? 'Enregistrer' : 'Créer la position' ?></button>
        <?php if ($editing !== null): ?><a class="eff-rh-btn" href="<?= $h(P::url()) ?>">Annuler</a><?php endif; ?>
    </div>
</form>
<?php endif; ?>
