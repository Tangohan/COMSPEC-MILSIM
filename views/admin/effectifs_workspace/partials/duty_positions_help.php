<?php
declare(strict_types=1);

/**
 * Aide partagée : positions administratives et situations « en mission » / « en service ».
 * Utilisée par la fiche membre (carte Statut) et par l'aide du registre des droits.
 */
$dphH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="eff-duty-help">
    <p class="eff-duty-help__lead">La <b>position administrative</b> dit où en est le membre dans la communauté. Un membre n’a qu’une position à la fois.</p>
    <dl class="eff-duty-help__list">
        <?php foreach (\App\Services\Personnel\PersonnelDutyPositionService::positions() as $dphPos): ?>
            <dt><?= $dphH($dphPos['label']) ?></dt>
            <dd><?= $dphH($dphPos['help']) ?></dd>
        <?php endforeach; ?>
    </dl>
    <p class="eff-duty-help__lead"><b>Dans l’exercice de ses fonctions</b>, un membre est soit en mission, soit en service :</p>
    <dl class="eff-duty-help__list">
        <dt>En mission</dt>
        <dd>Engagé sur une opération : intervention, interpellation, maintien de l’ordre, escorte, renseignement, garde et surveillance, missions militaires, OPEX et OPINT, appui et soutien opérationnel.</dd>
        <dt>En service</dt>
        <dd>Présent hors opération : instruction, stage, entraînement (physique, militaire, tir, intervention), permanence ou astreinte, liaison, casernement, briefing ou travail à distance.</dd>
    </dl>
    <p class="eff-duty-help__note">« En formation » et « En service actif » s’attribuent seuls (arrivée, puis fin du parcours d’intégration). « En service non actif » et « En service statutaire de renforcement » se choisissent par un responsable.</p>
</div>
