<?php
declare(strict_types=1);

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$settings = is_array($duplicateSettings ?? null) ? $duplicateSettings : [];
$labels = is_array($duplicateFieldLabels ?? null) ? $duplicateFieldLabels : [];
$scan = is_array($personnelDuplicateScan ?? null) ? $personnelDuplicateScan : [];
$enabled = array_key_exists('enabled', $settings) ? !empty($settings['enabled']) : true;
$selected = is_array($settings['fields'] ?? null) ? $settings['fields'] : ['matricule', 'callsign'];
$csrfToken = (string) ($csrfToken ?? '');
$groups = is_array($scan['groups'] ?? null) ? $scan['groups'] : [];
$groupCount = (int) ($scan['group_count'] ?? count($groups));
$memberCount = (int) ($scan['member_count'] ?? 0);
$selectedCount = count($selected);
$fieldHints = [
    'matricule' => 'Le numéro de dossier attribué dans la communauté.',
    'callsign' => 'L’indicatif radio ou d’usage du membre.',
    'display_name' => 'Le nom tel qu’il apparaît dans l’annuaire.',
    'character_name' => 'Le nom du personnage, s’il est renseigné.',
    'email' => 'L’adresse e-mail du compte.',
];
?>
<section class="eff-rh-hero">
    <p class="eff-page-kicker">Dossier RH</p>
    <h1 class="eff-page-title">Fiches jumelles</h1>
    <p class="eff-page-lead">
        Repérez les dossiers qui partagent une même valeur — matricule, indicatif, nom.
        Choisissez ce qui déclenche l’alerte, puis ouvrez les fiches concernées.
    </p>
    <div class="eff-rh-tiles" aria-label="Synthèse des fiches jumelles">
        <article class="eff-rh-tile <?= $enabled ? 'eff-rh-tile--ok' : 'eff-rh-tile--info' ?>">
            <span class="eff-rh-tile__kicker">Détection</span>
            <strong class="eff-rh-tile__value"><?= $enabled ? 'Active' : 'Off' ?></strong>
            <span class="eff-rh-tile__label"><?= $enabled ? 'Les champs cochés sont surveillés' : 'Aucune alerte n’est affichée' ?></span>
            <em class="eff-rh-tile__tone"><?= $enabled ? 'En service' : 'En pause' ?></em>
        </article>
        <article class="eff-rh-tile">
            <span class="eff-rh-tile__kicker">Champs</span>
            <strong class="eff-rh-tile__value"><?= $selectedCount ?></strong>
            <span class="eff-rh-tile__label">critère<?= $selectedCount > 1 ? 's' : '' ?> retenu<?= $selectedCount > 1 ? 's' : '' ?></span>
        </article>
        <article class="eff-rh-tile <?= $enabled && $groupCount > 0 ? 'eff-rh-tile--warn' : 'eff-rh-tile--ok' ?>">
            <span class="eff-rh-tile__kicker">Groupes</span>
            <strong class="eff-rh-tile__value"><?= $enabled ? $groupCount : '—' ?></strong>
            <span class="eff-rh-tile__label">
                <?php if (!$enabled): ?>
                    Détection en pause
                <?php elseif ($groupCount === 0): ?>
                    Aucune fiche jumelle
                <?php else: ?>
                    <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> concerné<?= $memberCount > 1 ? 's' : '' ?>
                <?php endif; ?>
            </span>
            <em class="eff-rh-tile__tone"><?= $enabled && $groupCount > 0 ? 'À traiter' : 'Rien à signaler' ?></em>
        </article>
    </div>
</section>

<section class="eff-rh-form" aria-labelledby="eff-dup-settings-title">
    <div class="eff-rh-form__head">
        <h2 id="eff-dup-settings-title" class="eff-rh-form__title">Réglage de la détection</h2>
        <p class="eff-rh-form__lead">Cochez uniquement ce qui, dans votre communauté, ne doit jamais se répéter d’une fiche à l’autre.</p>
    </div>
    <form method="post" action="<?= $h(effectifs_workspace_url('doublons')) ?>" class="eff-rh-dup-form">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <label class="eff-rh-switch">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            <span>
                <strong>Activer la détection</strong>
                <em>Lorsqu’elle est active, les fiches jumelles apparaissent ici et sur le tableur.</em>
            </span>
        </label>
        <p class="eff-rh-field__label">Champs considérés comme identiques</p>
        <div class="eff-rh-checks" role="group" aria-label="Champs considérés comme identiques">
            <?php foreach ($labels as $key => $label): ?>
                <?php $key = (string) $key; ?>
                <label class="eff-rh-check <?= in_array($key, $selected, true) ? 'is-on' : '' ?>">
                    <input type="checkbox" name="fields[]" value="<?= $h($key) ?>"
                           <?= in_array($key, $selected, true) ? 'checked' : '' ?>>
                    <span>
                        <strong><?= $h((string) $label) ?></strong>
                        <?php if (!empty($fieldHints[$key])): ?>
                            <em><?= $h($fieldHints[$key]) ?></em>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="eff-rh-form__actions">
            <button type="submit" class="eff-rh-btn eff-rh-btn--primary">Enregistrer</button>
        </div>
    </form>
</section>

<section class="eff-rh-list-card" aria-labelledby="eff-dup-results-title">
    <div class="eff-rh-list-card__head">
        <h2 id="eff-dup-results-title" class="eff-rh-list-card__title">Résultat</h2>
        <?php $rhTip('tip-dup-result', 'À propos du résultat', 'Un groupe rassemble les fiches qui partagent exactement la même valeur sur un champ coché. Ouvrez chaque fiche pour décider laquelle conserver.'); ?>
    </div>
    <?php if (!$enabled): ?>
        <p class="eff-rh-list-card__empty">La détection est en pause. Activez-la ci-dessus pour afficher les fiches jumelles.</p>
    <?php elseif ($groups === []): ?>
        <p class="eff-rh-list-card__empty">Aucune fiche jumelle sur les champs retenus. La surveillance continue en arrière-plan.</p>
    <?php else: ?>
        <p class="eff-rh-list-card__lead"><?= $groupCount ?> groupe<?= $groupCount > 1 ? 's' : '' ?> · <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> à relire.</p>
        <ul class="eff-rh-dup-groups">
            <?php foreach ($groups as $g): ?>
                <?php
                if (!is_array($g)) {
                    continue;
                }
                $members = is_array($g['members'] ?? null) ? $g['members'] : [];
                ?>
                <li class="eff-rh-dup-group">
                    <p class="eff-rh-dup-group__meta">
                        <?= $h((string) ($g['field_label'] ?? '')) ?>
                        <span>« <?= $h((string) ($g['value'] ?? '')) ?> »</span>
                    </p>
                    <ul class="eff-rh-dup-group__people">
                        <?php foreach ($members as $m): ?>
                            <?php
                            if (!is_array($m)) {
                                continue;
                            }
                            $mid = (int) ($m['id'] ?? 0);
                            $mName = trim((string) ($m['display_name'] ?? '')) ?: 'Membre';
                            $mCall = trim((string) ($m['callsign'] ?? ''));
                            ?>
                            <li>
                                <a href="<?= $h(effectifs_workspace_url('membres/' . $mid)) ?>">
                                    <?= $h($mName) ?>
                                    <?php if ($mCall !== '' && strcasecmp($mCall, $mName) !== 0): ?>
                                        <span><?= $h($mCall) ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php $rhShortcutCurrent = 'doublons'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
