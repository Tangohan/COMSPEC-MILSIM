<?php
declare(strict_types=1);

/**
 * Navigation d’une coopération : six onglets regroupant les dix écrans historiques
 * (toutes les URL restent valides). Les onglets inutiles à l’étape courante sont désactivés,
 * avec une explication accessible indiquant quand ils s’activent.
 */

$m = $interteamMission ?? [];
$mid = (int) ($m['id'] ?? 0);
$active = (string) ($cooperationMissionNavActive ?? 'overview');
if ($mid <= 0) {
    return;
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$navStatus = (string) ($m['status'] ?? '');
$navCanPilot = !empty($interteamCanPilot) && !empty($interteamCanManage);
$navLaunched = in_array($navStatus, ['active', 'archived'], true);
$navArchived = $navStatus === 'archived';
$navPhase = \App\Support\CooperationDictionary::effectivePhase($m);
$navNegotiationOpen = $navStatus === 'pending' || !empty($interteamCounterPending);
$navIsParticipantActive = false;
foreach (($interteamParticipants ?? []) as $np) {
    if ((int) ($np['tenant_id'] ?? 0) === (int) ($sessionTenantId ?? 0) && ($np['status'] ?? '') === 'active') {
        $navIsParticipantActive = true;
    }
}

/**
 * Onglet : clé, libellé, URL, sous-pages [clé => [libellé, URL, actif?, raison si inactif]],
 * raison de désactivation de l’onglet (vide = disponible).
 */
$proposalHref = $navCanPilot ? cooperation_mission_edit_url($mid) : cooperation_mission_negotiate_url($mid);
$groups = [
    [
        'key' => 'overview',
        'label' => 'Synthèse',
        'href' => cooperation_mission_show_url($mid),
        'children' => [],
        'disabled' => '',
    ],
    [
        'key' => 'proposal',
        'label' => 'Proposition & négociation',
        'href' => $proposalHref,
        'children' => array_filter([
            'edit' => $navCanPilot ? ['Cadrage', cooperation_mission_edit_url($mid), ''] : null,
            'negotiate' => ['Négociation', cooperation_mission_negotiate_url($mid), $navNegotiationOpen ? '' : 'La négociation s’ouvre pendant l’étape « Invitations & négociation ».'],
        ]),
        'disabled' => (!$navCanPilot && !$navNegotiationOpen) ? 'Disponible pendant l’étape « Invitations & négociation ».' : '',
    ],
    [
        'key' => 'exchange',
        'label' => 'Espace commun',
        'href' => cooperation_mission_exchange_url($mid),
        'children' => [
            'exchange' => ['Fil, autorisations & verrou', cooperation_mission_exchange_url($mid), ''],
            'consent' => ['Mon autorisation de partage', cooperation_mission_consent_url($mid), $navIsParticipantActive ? '' : 'Réservée aux unités ayant accepté la coopération.'],
        ],
        'disabled' => $navLaunched ? '' : 'L’espace commun s’ouvre au lancement de la coopération.',
    ],
    [
        'key' => 'meeting',
        'label' => 'Réunions',
        'href' => cooperation_mission_meeting_url($mid),
        'children' => [],
        'disabled' => '',
    ],
    [
        'key' => 'orbat',
        'label' => 'Structures & liaisons',
        'href' => cooperation_mission_orbat_url($mid),
        'children' => [],
        'disabled' => '',
    ],
    [
        'key' => 'journal',
        'label' => 'Journal & REX',
        'href' => cooperation_mission_timeline_url($mid),
        'children' => array_filter([
            'timeline' => ['Chronologie', cooperation_mission_timeline_url($mid), ''],
            'rex' => ['Retours d’expérience', cooperation_mission_rex_url($mid), ($navArchived && $navPhase !== 'cancelled') ? '' : 'Les retours d’expérience s’ouvrent à la clôture de la coopération.'],
            'archive' => $navCanPilot ? [$navStatus === 'pending' ? 'Annulation' : 'Clôture', cooperation_mission_archive_url($mid), ''] : null,
        ]),
        'disabled' => '',
    ],
];
$childToGroup = ['edit' => 'proposal', 'negotiate' => 'proposal', 'exchange' => 'exchange', 'consent' => 'exchange', 'timeline' => 'journal', 'rex' => 'journal', 'archive' => 'journal'];
$activeGroup = $childToGroup[$active] ?? $active;
?>
<?php require base_path('views/back_office/cooperation/missions/_progress.php'); ?>
<?php if (empty($GLOBALS['__coopAjaxAssets'])): $GLOBALS['__coopAjaxAssets'] = true; ?>
<script defer src="<?= $h(asset_url('assets/js/cooperation/ajax-actions.js')) ?>"></script>
<?php endif; ?>
<nav class="coop-tabs" aria-label="Sections de la coopération">
    <ul class="coop-tabs__list">
        <?php foreach ($groups as $g): ?>
        <?php $isOn = $activeGroup === $g['key']; ?>
        <li>
            <?php if ($g['disabled'] !== '' && !$isOn): ?>
            <span class="coop-tabs__tab is-disabled" aria-disabled="true" title="<?= $h($g['disabled']) ?>" tabindex="0" aria-describedby="coop-tab-why-<?= $h($g['key']) ?>">
                <?= $h($g['label']) ?>
                <span id="coop-tab-why-<?= $h($g['key']) ?>" class="sr-only"><?= $h($g['disabled']) ?></span>
            </span>
            <?php else: ?>
            <a href="<?= $h($g['href']) ?>" class="coop-tabs__tab<?= $isOn ? ' is-active' : '' ?>"<?= $isOn ? ' aria-current="page"' : '' ?>><?= $h($g['label']) ?></a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php
    $current = null;
    foreach ($groups as $g) {
        if ($g['key'] === $activeGroup) {
            $current = $g;
        }
    }
    ?>
    <?php if ($current !== null && count($current['children']) > 1): ?>
    <ul class="coop-tabs__sub" aria-label="<?= $h($current['label']) ?>">
        <?php foreach ($current['children'] as $ck => [$clabel, $chref, $cwhy]): ?>
        <?php $cOn = $active === $ck; ?>
        <li>
            <?php if ($cwhy !== '' && !$cOn): ?>
            <span class="coop-tabs__subtab is-disabled" aria-disabled="true" title="<?= $h($cwhy) ?>" tabindex="0"><?= $h($clabel) ?><span class="sr-only"> — <?= $h($cwhy) ?></span></span>
            <?php else: ?>
            <a href="<?= $h($chref) ?>" class="coop-tabs__subtab<?= $cOn ? ' is-active' : '' ?>"<?= $cOn ? ' aria-current="page"' : '' ?>><?= $h($clabel) ?></a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</nav>
<?php
$gObCoopNav = \App\Core\Gate::getInstance();
if ($mid > 0 && ($gObCoopNav->allows('operational.board.edit') || $gObCoopNav->allows('admin.organization') || $gObCoopNav->allows('admin.access') || $gObCoopNav->allows('admin.system'))) {
    $opBoardPublishSourceType = 'mission';
    $opBoardPublishSourceId = $mid;
    $opBoardPublishCsrf = \App\Core\Csrf::token();
    $opBoardPublishVariant = 'mission_compact';
    require base_path('views/partials/operational_board_publish_linked_form.php');
}
