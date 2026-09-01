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
$iconDup = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="7" y="7" width="12" height="14" rx="1.5"/><path stroke-linecap="round" d="M5 17V5.5A1.5 1.5 0 016.5 4H16"/><path stroke-linecap="round" d="M10 12h6M10 15h4"/></svg>';
?>
<section class="eff-page-head">
    <p class="eff-page-kicker">Pilotage RH</p>
    <h1 class="eff-page-title">Fiches jumelles</h1>
    <p class="eff-page-lead">
        Repérez les dossiers qui partagent une même valeur — matricule, indicatif, nom.
        Choisissez ce qui déclenche l’alerte, puis ouvrez les fiches concernées.
    </p>
</section>

<div class="eff-metrics" aria-label="Indicateurs fiches jumelles">
    <div class="eff-metric">
        <p class="eff-metric__k">Détection</p>
        <p class="eff-metric__v"><?= $enabled ? 'Active' : 'Pause' ?></p>
    </div>
    <div class="eff-metric">
        <p class="eff-metric__k">Critères</p>
        <p class="eff-metric__v"><?= $selectedCount ?></p>
    </div>
    <div class="eff-metric">
        <p class="eff-metric__k">Groupes</p>
        <p class="eff-metric__v"><?= $enabled ? $groupCount : '—' ?></p>
    </div>
    <div class="eff-metric">
        <p class="eff-metric__k">À relire</p>
        <p class="eff-metric__v"><?= $enabled ? $memberCount : '—' ?></p>
    </div>
</div>

<div class="eff-dup">
    <section class="eff-dup__panel" aria-labelledby="eff-dup-settings-title">
        <header class="eff-dup__head">
            <div>
                <h2 id="eff-dup-settings-title" class="eff-dup__title">Réglage de la détection</h2>
                <p class="eff-dup__sub">Cochez uniquement ce qui, dans votre communauté, ne doit jamais se répéter d’une fiche à l’autre.</p>
            </div>
        </header>
        <form method="post" action="<?= $h(effectifs_workspace_url('doublons')) ?>" class="eff-dup-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label class="eff-dup-switch">
                <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                <span>
                    <strong>Activer la détection</strong>
                    <em>Lorsqu’elle est active, les fiches jumelles apparaissent ici et sur le tableur.</em>
                </span>
            </label>
            <p class="eff-dup-fields__label" id="eff-dup-fields-label">Champs considérés comme identiques</p>
            <div class="eff-dup-fields" role="group" aria-labelledby="eff-dup-fields-label">
                <?php foreach ($labels as $key => $label): ?>
                    <?php $key = (string) $key; ?>
                    <label class="eff-dup-chip <?= in_array($key, $selected, true) ? 'is-on' : '' ?>">
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
            <div class="eff-dup-form__actions">
                <button type="submit" class="eff-btn eff-btn--primary">Enregistrer</button>
            </div>
        </form>
    </section>

    <section class="eff-dup__panel <?= $enabled && $groupCount > 0 ? 'eff-dup__panel--warn' : 'eff-dup__panel--ok' ?>" aria-labelledby="eff-dup-results-title">
        <header class="eff-dup__head">
            <div>
                <h2 id="eff-dup-results-title" class="eff-dup__title">Résultat</h2>
                <?php if ($enabled && $groupCount > 0): ?>
                    <p class="eff-dup__sub"><?= $groupCount ?> groupe<?= $groupCount > 1 ? 's' : '' ?> · <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> à relire.</p>
                <?php endif; ?>
            </div>
            <?php $rhTip('tip-dup-result', 'À propos du résultat', 'Un groupe rassemble les fiches qui partagent exactement la même valeur sur un champ coché. Ouvrez chaque fiche pour décider laquelle conserver.'); ?>
        </header>
        <?php if (!$enabled): ?>
            <div class="eff-empty">
                <div class="eff-empty__icon" aria-hidden="true"><?= $iconDup ?></div>
                <h3 class="eff-empty__title">Détection en pause</h3>
                <p class="eff-empty__text">Activez-la à gauche pour afficher les fiches jumelles sur les champs retenus.</p>
            </div>
        <?php elseif ($groups === []): ?>
            <div class="eff-empty">
                <div class="eff-empty__icon" aria-hidden="true"><?= $iconDup ?></div>
                <h3 class="eff-empty__title">Aucune fiche jumelle</h3>
                <p class="eff-empty__text">Les champs retenus ne se répètent sur aucun dossier. La surveillance continue.</p>
            </div>
        <?php else: ?>
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
</div>

<?php $rhShortcutCurrent = 'doublons'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
