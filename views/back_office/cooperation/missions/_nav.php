<?php
declare(strict_types=1);
$m = $interteamMission ?? [];
$mid = (int) ($m['id'] ?? 0);
$active = (string) ($cooperationMissionNavActive ?? 'overview');
if ($mid <= 0) {
    return;
}
$tabs = [
    ['overview', 'Synthèse', cooperation_mission_show_url($mid)],
    ['edit', 'Proposition', cooperation_mission_edit_url($mid)],
    ['negotiate', 'Négociation', cooperation_mission_negotiate_url($mid)],
    ['exchange', 'Espace commun', cooperation_mission_exchange_url($mid)],
    ['consent', 'Autorisation de partage', cooperation_mission_consent_url($mid)],
    ['timeline', 'Chronologie', cooperation_mission_timeline_url($mid)],
    ['meeting', 'Réunion', cooperation_mission_meeting_url($mid)],
    ['orbat', 'Structures & liaisons', cooperation_mission_orbat_url($mid)],
    ['rex', 'REX', cooperation_mission_rex_url($mid)],
    ['archive', 'Clôture', cooperation_mission_archive_url($mid)],
];
?>
<nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 mb-6" aria-label="Sections de la coopération">
    <?php foreach ($tabs as [$key, $label, $href]): ?>
        <?php $isOn = $active === $key; ?>
        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
           class="<?= $isOn
               ? 'rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm'
               : 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50' ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php endforeach; ?>
</nav>
