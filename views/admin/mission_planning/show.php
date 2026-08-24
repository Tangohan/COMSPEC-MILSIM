<?php
declare(strict_types=1);

use App\Support\MissionPlanningLabels;

/** @var array<string,mixed> $mpBoard */
/** @var string $mpTab */
/** @var list<array<string,mixed>> $mpEvents */
/** @var list<array<string,mixed>> $mpMaps */
/** @var list<array<string,mixed>> $mpUsers */

$board = is_array($mpBoard ?? null) ? $mpBoard : [];
$plan = is_array($board['plan'] ?? null) ? $board['plan'] : [];
$tab = (string) ($mpTab ?? 'planning');
$events = is_array($mpEvents ?? null) ? $mpEvents : [];
$maps = is_array($mpMaps ?? null) ? $mpMaps : [];
$users = is_array($mpUsers ?? null) ? $mpUsers : [];
$roster = is_array($board['roster'] ?? null) ? $board['roster'] : [];
$elements = is_array($board['elements'] ?? null) ? $board['elements'] : [];
$tree = is_array($board['tree'] ?? null) ? $board['tree'] : [];
$doc = is_array($board['document'] ?? null) ? $board['document'] : [];
$matrix = is_array($board['matrix'] ?? null) ? $board['matrix'] : [];
$log = is_array($board['log'] ?? null) ? $board['log'] : [];
$counts = is_array($board['counts'] ?? null) ? $board['counts'] : [];
$cmp = is_array($board['comparison'] ?? null) ? $board['comparison'] : [];
$aar = is_array($board['aar'] ?? null) ? $board['aar'] : null;
$planId = (int) ($plan['id'] ?? 0);
$status = (string) ($plan['status'] ?? 'draft');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = \App\Core\Csrf::token();
$base = url('back-office/planification/' . $planId);

$personOptions = static function (array $users, mixed $selected) use ($h): string {
    $sel = (int) $selected;
    $html = '<option value="">Vacant</option>';
    foreach ($users as $u) {
        $id = (int) ($u['id'] ?? 0);
        $label = trim((string) ($u['callsign'] ?? ''));
        $name = trim((string) ($u['display_name'] ?? ''));
        if ($label !== '' && $name !== '') {
            $label .= ' · ' . $name;
        } elseif ($label === '') {
            $label = $name !== '' ? $name : (string) ($u['email'] ?? 'Membre');
        }
        $html .= '<option value="' . $id . '"' . ($id === $sel ? ' selected' : '') . '>' . $h($label) . '</option>';
    }

    return $html;
};

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<?php if ($s): ?><div class="ath-banner-warn ath-rise" style="background:#e6f8f0;border-color:#bfe9d8;" role="status"><div class="ath-banner-warn__text" style="color:#0b6b47;"><?= $h($s) ?></div></div><?php endif; ?>
<?php if ($e): ?><div class="ath-banner-warn ath-rise" role="alert"><div class="ath-banner-warn__text"><?= $h($e) ?></div></div><?php endif; ?>

<div class="mp-strip ath-rise">
    <div>
        <p class="mp-strip__kicker"><?= $h($plan['operation_name'] ?: $plan['title']) ?></p>
        <p class="mp-strip__id">Identifiant : <?= $h($plan['mission_code']) ?> · Horodatage <?= $h($plan['dtg']) ?></p>
    </div>
    <dl class="mp-strip__stats">
        <div><dt>Plan</dt><dd><?= $h(MissionPlanningLabels::status($status)) ?></dd></div>
        <div><dt>Ordre</dt><dd>v<?= $h($plan['opord_version'] ?? '1.0') ?></dd></div>
        <div><dt>Effectifs</dt><dd><?= (int) ($counts['assigned'] ?? 0) ?> / <?= (int) ($counts['auth'] ?? 0) ?></dd></div>
        <div><dt>En session</dt><dd><?= (int) ($counts['present'] ?? 0) ?> présents</dd></div>
        <div><dt>Organisation</dt><dd><?= $status === 'live' ? 'Synchronisée' : ($status === 'closed' ? 'Finale' : 'Prévue') ?></dd></div>
    </dl>
    <form method="post" action="<?= $h($base . '/statut') ?>" class="mp-strip__actions">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <label class="visually-hidden" for="mp-status">État du plan</label>
        <select id="mp-status" name="status" class="bo-select">
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Brouillon</option>
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publié</option>
            <option value="live" <?= $status === 'live' ? 'selected' : '' ?>>En session</option>
            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Clôturé</option>
        </select>
        <button type="submit" class="ath-btn ath-btn--solid">Mettre à jour</button>
        <?php if (is_array($aar) && (int) ($aar['id'] ?? 0) > 0): ?>
            <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/atak/comptes-rendus/' . (int) $aar['id'] . '/edit')) ?>">Ouvrir le compte rendu</a>
        <?php endif; ?>
        <a class="ath-btn" href="<?= $h($base . '/paquet.pdf') ?>">Télécharger le paquet</a>
        <a class="ath-btn" href="<?= $h(url('back-office/planification')) ?>">Tous les plans</a>
    </form>
</div>

<div class="ath-users-filters ath-rise">
    <a href="<?= $h($base . '?vue=planning') ?>" class="ath-btn<?= $tab === 'planning' ? ' ath-btn--solid' : '' ?>">Planning</a>
    <a href="<?= $h($base . '?vue=organisation') ?>" class="ath-btn<?= $tab === 'organisation' ? ' ath-btn--solid' : '' ?>">Organisation de combat</a>
    <a href="<?= $h($base . '?vue=documents') ?>" class="ath-btn<?= $tab === 'documents' ? ' ath-btn--solid' : '' ?>">Documents de mission</a>
</div>

<?php if ($tab === 'planning'): ?>
<form method="post" action="<?= $h($base . '/planning') ?>" class="ath-card ath-rise" style="padding:18px 20px;">
    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
    <div class="mp-form-grid">
        <div>
            <label class="ath-users-filters__label" for="mp-title">Nom de la mission</label>
            <input id="mp-title" name="title" class="bo-select" style="height:40px;width:100%;" value="<?= $h($plan['title'] ?? '') ?>">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-op">Nom d’opération</label>
            <input id="mp-op" name="operation_name" class="bo-select" style="height:40px;width:100%;" value="<?= $h($plan['operation_name'] ?? '') ?>">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-tf">Force</label>
            <input id="mp-tf" name="task_force_name" class="bo-select" style="height:40px;width:100%;" value="<?= $h($plan['task_force_name'] ?? '') ?>">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-code">Identifiant mission</label>
            <input id="mp-code" name="mission_code" class="bo-select" style="height:40px;width:100%;" value="<?= $h($plan['mission_code'] ?? '') ?>">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-dtg">Horodatage</label>
            <input id="mp-dtg" name="dtg" class="bo-select" style="height:40px;width:100%;" value="<?= $h($plan['dtg'] ?? '') ?>">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-ver">Version de l’ordre</label>
            <input id="mp-ver" name="opord_version" class="bo-select" style="height:40px;width:100%;" value="<?= $h($plan['opord_version'] ?? '1.0') ?>">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-class">Mention</label>
            <input id="mp-class" name="classification" class="bo-select" style="height:40px;width:100%;" value="<?= $h($plan['classification'] ?? '') ?>">
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-event">Événement lié</label>
            <select id="mp-event" name="event_id" class="bo-select">
                <option value="">Aucun</option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?= (int) $ev['id'] ?>" <?= (int) ($plan['event_id'] ?? 0) === (int) $ev['id'] ? 'selected' : '' ?>><?= $h($ev['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="ath-users-filters__label" for="mp-map">Carte</label>
            <select id="mp-map" name="map_id" class="bo-select">
                <option value="">Non précisée</option>
                <?php foreach ($maps as $map): ?>
                    <option value="<?= (int) $map['id'] ?>" <?= (int) ($plan['map_id'] ?? 0) === (int) $map['id'] ? 'selected' : '' ?>><?= $h($map['label'] ?? $map['slug'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="submit" class="ath-btn ath-btn--solid" style="margin-top:14px;">Enregistrer le planning</button>
</form>

<?php if (is_array($aar) && (int) ($aar['id'] ?? 0) > 0): ?>
<div class="ath-card ath-rise" style="padding:18px 20px;margin-top:16px;">
    <div style="font-size:9px;font-weight:800;letter-spacing:0.18em;color:#8c979b;margin-bottom:8px;">COMPTE RENDU</div>
    <p>Un compte rendu est ouvert pour cette mission (<?= $h($aar['status_label'] ?? 'En attente') ?>). Il n’est pas publié tout seul : complétez-le puis validez-le.</p>
    <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/atak/comptes-rendus/' . (int) $aar['id'] . '/edit')) ?>">Compléter le compte rendu</a>
</div>
<?php endif; ?>

<div class="ath-table-panel ath-rise" style="margin-top:16px;">
    <div class="ath-table-toolbar">
        <span class="ath-table-toolbar__title">Prévu / réel</span>
    </div>
    <div class="ath-table-wrap">
        <table class="ath-table">
            <thead>
                <tr>
                    <th>Organisation prévue</th>
                    <th>Organisation réelle</th>
                    <th>Remplacements</th>
                    <th>Réaffectations</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= (int) ($cmp['planned'] ?? 0) ?> personnels</td>
                    <td><?= (int) ($cmp['actual'] ?? 0) ?> personnels</td>
                    <td><?= (int) ($cmp['substitutions'] ?? 0) ?></td>
                    <td><?= (int) ($cmp['reassignments'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="ath-table-panel ath-rise" style="margin-top:16px;">
    <div class="ath-table-toolbar">
        <span class="ath-table-toolbar__title">Journal d’organisation</span>
        <span class="ath-table-toolbar__count"><?= count($log) ?> entrée<?= count($log) > 1 ? 's' : '' ?></span>
    </div>
    <?php if ($log === []): ?>
        <div class="ath-table-empty">Aucun mouvement pour l’instant.</div>
    <?php else: ?>
        <div class="ath-table-wrap">
            <table class="ath-table">
                <thead><tr><th>Heure</th><th>Événement</th></tr></thead>
                <tbody>
                    <?php foreach ($log as $entry):
                        $ts = strtotime((string) ($entry['occurred_at'] ?? ''));
                        ?>
                        <tr>
                            <td><?= $ts ? date('H:i', $ts) : '—' ?></td>
                            <td><?= $h($entry['message'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'organisation'): ?>
<p class="mp-lede">L’organisation prévue reste la référence. Chaque unité garde son type (état-major, manœuvre, air, soutien). L’organisation en cours reflète ce qui est réellement engagé. Glissez un poste vers une autre unité pour le réaffecter.</p>

<div class="mp-org-toolbar" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <form method="post" action="<?= $h($base . '/organigramme') ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <button type="submit" class="ath-btn">Reprendre l’organigramme de la communauté</button>
    </form>
    <?php if ((int) ($plan['event_id'] ?? 0) > 0): ?>
        <form method="post" action="<?= $h($base . '/inscrits-evenement') ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <button type="submit" class="ath-btn ath-btn--solid">Reprendre les inscrits de l’événement</button>
        </form>
    <?php else: ?>
        <p class="mp-muted" style="margin:0;align-self:center;">Liez un événement dans l’onglet Planning pour reprendre les inscrits.</p>
    <?php endif; ?>
</div>

<div class="mp-org-layout">
    <div class="mp-org-board" id="mp-org-board" data-move-base="<?= $h($base) ?>" data-csrf="<?= $h($csrf) ?>">
        <div class="mp-org-root"><?= $h($plan['task_force_name'] ?? 'Force') ?></div>
        <?php
        $renderTree = null;
        $renderTree = static function (array $nodes, bool $root = false) use (&$renderTree, $h, $planId, $csrf, $base, $personOptions, $users): void {
            echo '<ul class="mp-tree">';
            foreach ($nodes as $node) {
                $el = $node['element'] ?? [];
                $eid = (int) ($el['id'] ?? 0);
                echo '<li class="mp-tree__el" data-element-id="' . $eid . '">';
                echo '<div class="mp-tree__el-head">' . $h($el['label'] ?? '')
                    . ' <span class="mp-muted">· ' . $h(MissionPlanningLabels::elementKind((string) ($el['kind'] ?? ''))) . '</span></div>';
                echo '<ul class="mp-tree__slots" data-drop-element="' . $eid . '">';
                foreach ($node['slots'] ?? [] as $slot) {
                    $sid = (int) ($slot['id'] ?? 0);
                    $presence = (string) ($slot['presence_status'] ?? 'vacant');
                    echo '<li class="mp-tree__slot" draggable="true" data-slot-id="' . $sid . '" data-order="' . (int) ($slot['display_order'] ?? 0) . '">';
                    echo '<span class="mp-tree__cs">' . $h($slot['callsign'] ?? '') . '</span>';
                    echo '<span class="mp-tree__fn">' . $h($slot['function_label'] ?? '') . '</span>';
                    echo '<span class="mp-tree__who">' . $h($slot['assigned_label'] ?? 'Vacant') . '</span>';
                    echo '<span class="mp-tree__st mp-tree__st--' . $h($presence) . '">' . $h($slot['presence_label'] ?? '') . '</span>';
                    echo '</li>';
                }
                echo '</ul>';
                if (!empty($node['children'])) {
                    $renderTree($node['children']);
                }
                echo '</li>';
            }
            echo '</ul>';
        };
        $renderTree($tree, true);
        ?>
    </div>

    <div>
        <div class="ath-table-panel">
            <div class="ath-table-toolbar">
                <span class="ath-table-toolbar__title">Matrice des effectifs</span>
            </div>
            <div class="ath-table-wrap">
                <table class="ath-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Autorisés</th>
                            <th>Affectés</th>
                            <th>Présents</th>
                            <th>Absents</th>
                            <th>Renforts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matrix as $row): ?>
                            <tr<?= ($row['kind'] ?? '') === 'total' ? ' class="mp-total-row"' : '' ?>>
                                <td><?= $h($row['label'] ?? '') ?><?php if (($row['kind'] ?? '') !== 'total' && ($row['kind_label'] ?? '') !== ''): ?> <span class="mp-muted">· <?= $h($row['kind_label']) ?></span><?php endif; ?></td>
                                <td><?= (int) ($row['auth'] ?? 0) ?></td>
                                <td><?= (int) ($row['assigned'] ?? 0) ?></td>
                                <td><?= (int) ($row['present'] ?? 0) ?></td>
                                <td><?= (int) ($row['absent'] ?? 0) ?></td>
                                <td><?= (int) ($row['attached'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="ath-table-panel ath-rise" style="margin-top:16px;">
    <div class="ath-table-toolbar">
        <span class="ath-table-toolbar__title">Tableau des effectifs</span>
        <span class="ath-table-toolbar__count"><?= count($roster) ?> postes</span>
    </div>
    <div class="ath-table-wrap">
        <table class="ath-table" style="min-width:1100px">
            <thead>
                <tr>
                    <th>Unité</th>
                    <th>Type</th>
                    <th>Indicatif</th>
                    <th>Fonction</th>
                    <th>Personnel</th>
                    <th>État</th>
                    <th>Mode</th>
                    <th>Affecter</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roster as $row):
                    $sid = (int) ($row['id'] ?? 0);
                    $presence = (string) ($row['presence_status'] ?? 'vacant');
                    ?>
                    <tr>
                        <td><?= $h($row['element_label'] ?? '') ?></td>
                        <td><?= $h(MissionPlanningLabels::elementKind((string) ($row['element_kind'] ?? ''))) ?></td>
                        <td><?= $h($row['callsign'] ?? '') ?></td>
                        <td><?= $h($row['function_label'] ?? '') ?></td>
                        <td><?= $h($row['assigned_label'] ?? 'Vacant') ?></td>
                        <td><?= $h($row['presence_label'] ?? '') ?></td>
                        <td><?= $h($row['mode_label'] ?? '') ?></td>
                        <td>
                            <form method="post" action="<?= $h($base . '/postes/' . $sid . '/affecter') ?>" class="mp-inline-form">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                <select name="user_id" class="bo-select"><?= $personOptions($users, $row['planned_user_id'] ?? $row['current_user_id'] ?? 0) ?></select>
                                <button type="submit" class="ath-btn">OK</button>
                            </form>
                            <?php if ($presence === 'mismatch'): ?>
                                <form method="post" action="<?= $h($base . '/postes/' . $sid . '/rapprocher') ?>" class="mp-inline-form" style="margin-top:6px;">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                    <button class="ath-btn ath-btn--solid" name="action" value="replace">Remplacer</button>
                                    <button class="ath-btn" name="action" value="temporary">Affecter temporairement</button>
                                    <button class="ath-btn" name="action" value="leave">Laisser non rapproché</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="mp-slot-extra">
                        <td colspan="8">
                            <form method="post" action="<?= $h($base . '/postes/' . $sid) ?>" class="mp-slot-details">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                <input type="hidden" name="element_id" value="<?= (int) ($row['element_id'] ?? 0) ?>">
                                <input type="hidden" name="display_order" value="<?= (int) ($row['display_order'] ?? 0) ?>">
                                <input type="hidden" name="role_code" value="<?= $h($row['role_code'] ?? '') ?>">
                                <label>Indicatif <input name="callsign" value="<?= $h($row['callsign'] ?? '') ?>"></label>
                                <label>Fonction <input name="function_label" value="<?= $h($row['function_label'] ?? '') ?>"></label>
                                <label>Grade <input name="rank_label" value="<?= $h($row['rank_label'] ?? '') ?>"></label>
                                <label>Véhicule <input name="vehicle_label" value="<?= $h($row['vehicle_label'] ?? '') ?>"></label>
                                <label>Radio principale <input name="radio_primary" value="<?= $h($row['radio_primary'] ?? '') ?>"></label>
                                <label>Radio secondaire <input name="radio_secondary" value="<?= $h($row['radio_secondary'] ?? '') ?>"></label>
                                <label>Équipement <input name="equipment_notes" value="<?= $h($row['equipment_notes'] ?? '') ?>"></label>
                                <button type="submit" class="ath-btn">Enregistrer le poste</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="<?= htmlspecialchars(asset_url('assets/js/mission-planning-org.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>

<?php if ($tab === 'documents'): ?>
<div class="mp-doc-grid">
    <form method="post" action="<?= $h($base . '/documents') ?>" class="ath-card" style="padding:18px 20px;">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <p class="mp-lede">Athena pré-remplit ce qu’elle connaît. Le rédacteur complète la partie doctrinale.</p>
        <p class="mp-mission-line"><strong>Mission.</strong> <?= $h($board['mission_sentence'] ?? '') ?></p>
        <div class="mp-form-grid">
            <div>
                <label class="ath-users-filters__label" for="m-task">Tâche</label>
                <input id="m-task" name="mission_task" class="bo-select" style="height:40px;width:100%;" value="<?= $h($doc['mission_task'] ?? '') ?>">
            </div>
            <div>
                <label class="ath-users-filters__label" for="m-loc">Lieu</label>
                <input id="m-loc" name="mission_location" class="bo-select" style="height:40px;width:100%;" value="<?= $h($doc['mission_location'] ?? '') ?>">
            </div>
            <div>
                <label class="ath-users-filters__label" for="m-nlt">Pas plus tard que</label>
                <input id="m-nlt" name="mission_nlt" class="bo-select" style="height:40px;width:100%;" value="<?= $h($doc['mission_nlt'] ?? '') ?>">
            </div>
            <div>
                <label class="ath-users-filters__label" for="m-purpose">But</label>
                <input id="m-purpose" name="mission_purpose" class="bo-select" style="height:40px;width:100%;" value="<?= $h($doc['mission_purpose'] ?? '') ?>">
            </div>
        </div>
        <label class="ath-users-filters__label" for="s-en">1.a Forces adverses</label>
        <textarea id="s-en" name="situation_enemy" class="mp-textarea"><?= $h($doc['situation_enemy'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="s-fr">1.b Forces amies</label>
        <textarea id="s-fr" name="situation_friendly" class="mp-textarea"><?= $h($doc['situation_friendly'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="s-at">1.c Renforts / détachements</label>
        <textarea id="s-at" name="situation_attachments" class="mp-textarea"><?= $h($doc['situation_attachments'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="s-cv">1.d Considérations civiles</label>
        <textarea id="s-cv" name="situation_civil" class="mp-textarea"><?= $h($doc['situation_civil'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="e-in">3.a Intention du chef</label>
        <textarea id="e-in" name="execution_intent" class="mp-textarea"><?= $h($doc['execution_intent'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="e-co">3.b Concept des opérations</label>
        <textarea id="e-co" name="execution_concept" class="mp-textarea"><?= $h($doc['execution_concept'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="e-ta">3.c Tâches aux unités</label>
        <textarea id="e-ta" name="execution_tasks" class="mp-textarea"><?= $h($doc['execution_tasks'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="e-ci">3.d Instructions de coordination</label>
        <textarea id="e-ci" name="execution_coordinating" class="mp-textarea"><?= $h($doc['execution_coordinating'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="su-lo">4.a Logistique</label>
        <textarea id="su-lo" name="sustainment_logistics" class="mp-textarea"><?= $h($doc['sustainment_logistics'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="su-me">4.b Sanitaire</label>
        <textarea id="su-me" name="sustainment_medical" class="mp-textarea"><?= $h($doc['sustainment_medical'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="su-re">4.c Ravitaillement</label>
        <textarea id="su-re" name="sustainment_resupply" class="mp-textarea"><?= $h($doc['sustainment_resupply'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="c-cmd">5.a Commandement</label>
        <textarea id="c-cmd" name="command_command" class="mp-textarea"><?= $h($doc['command_command'] ?? '') ?></textarea>
        <label class="ath-users-filters__label" for="c-sig">5.b Transmissions</label>
        <textarea id="c-sig" name="command_signal" class="mp-textarea"><?= $h($doc['command_signal'] ?? '') ?></textarea>
        <button type="submit" class="ath-btn ath-btn--solid" style="margin-top:12px;">Enregistrer les documents</button>
    </form>
    <aside class="ath-card" style="padding:18px 20px;">
        <div style="font-size:9px;font-weight:800;letter-spacing:0.18em;color:#8c979b;">PAQUET MISSION</div>
        <ol class="mp-toc">
            <li>Couverture</li>
            <li>Organisation de combat</li>
            <li>Tableau des effectifs</li>
            <li>Mission</li>
            <li>Situation</li>
            <li>Exécution</li>
            <li>Soutien</li>
            <li>Commandement et transmissions</li>
            <li>Plan de communication</li>
            <li>Matrice véhicules / air</li>
            <li>Chronologie</li>
            <li>Annexes A à H</li>
        </ol>
        <p class="mp-muted">Les annexes B à H sont indiquées au sommaire dès maintenant ; leur rédaction détaillée suivra.</p>
        <a class="ath-btn ath-btn--solid" href="<?= $h($base . '/paquet.pdf') ?>">Générer le paquet</a>
    </aside>
</div>
<?php endif; ?>
