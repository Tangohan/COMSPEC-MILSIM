<?php
declare(strict_types=1);

/**
 * Groupe de boutons RSVP rapide (oui/peut-être/non) — progressive enhancement JS
 * via /api/events/{id}/rsvp (voir public/assets/js/dashboard-rsvp.js), pas de rechargement.
 *
 * @var int $rsvpEventId
 * @var string $rsvpCurrentStatus
 * @var bool $rsvpCompact
 * @var bool $rsvpShowAbsenceReason Affiche un motif d’absence quand « Absent » est choisi
 */

$rsvpEventId = (int) ($rsvpEventId ?? 0);
if ($rsvpEventId < 1) {
    return;
}
$rsvpCurrentStatus = (string) ($rsvpCurrentStatus ?? '');
$rsvpCompact = (bool) ($rsvpCompact ?? false);
$rsvpShowAbsenceReason = (bool) ($rsvpShowAbsenceReason ?? false);

$rsvpStatusLabels = [
    'yes' => 'Enregistré : vous participez',
    'maybe' => 'Enregistré : peut-être',
    'no' => 'Enregistré : absent(e)',
];
$rsvpInitialLabel = $rsvpStatusLabels[$rsvpCurrentStatus] ?? '';

$rsvpChoices = [
    'yes' => 'Je participe',
    'maybe' => 'Peut-être',
    'no' => 'Absent',
];

if (empty($GLOBALS['dash_rsvp_assets_printed'])) {
    $GLOBALS['dash_rsvp_assets_printed'] = true;
    require base_path('views/partials/dashboard_rsvp_assets.php');
}
?>
<div class="dash-rsvp<?= $rsvpCompact ? ' dash-rsvp--compact' : '' ?><?= $rsvpCurrentStatus !== '' ? ' dash-rsvp--saved' : '' ?>"
     data-rsvp-group
     data-event-id="<?= $rsvpEventId ?>"
     data-rsvp-current="<?= htmlspecialchars($rsvpCurrentStatus, ENT_QUOTES, 'UTF-8') ?>"
     role="group"
     aria-label="Confirmer votre participation">
    <?php foreach ($rsvpChoices as $choice => $choiceLabel):
        $isActive = $rsvpCurrentStatus === $choice;
        ?>
        <button type="button"
                class="dash-rsvp__btn dash-rsvp__btn--<?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?><?= $isActive ? ' is-active' : '' ?>"
                data-rsvp-choice="<?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?>"
                aria-pressed="<?= $isActive ? 'true' : 'false' ?>">
            <?= htmlspecialchars($choiceLabel, ENT_QUOTES, 'UTF-8') ?>
        </button>
    <?php endforeach; ?>
    <?php if ($rsvpShowAbsenceReason): ?>
        <span class="dash-rsvp__reason<?= $rsvpCurrentStatus === 'no' ? '' : ' hidden' ?>" data-rsvp-reason-wrap<?= $rsvpCurrentStatus === 'no' ? '' : ' hidden' ?>>
            <label class="sr-only" for="rsvp-reason-<?= $rsvpEventId ?>">Motif d’absence</label>
            <select id="rsvp-reason-<?= $rsvpEventId ?>" name="absence_reason" class="dash-rsvp__select" data-rsvp-absence-reason>
                <option value="">Motif d’absence</option>
                <option value="service">Service</option>
                <option value="sante">Santé</option>
                <option value="indisponibilite_planifiee">Indispo planifiée</option>
                <option value="absence_non_justifiee">Absence non justifiée</option>
                <option value="autre">Autre</option>
            </select>
        </span>
    <?php endif; ?>
    <span class="dash-rsvp__status" data-rsvp-status-label aria-live="polite"><?= htmlspecialchars($rsvpInitialLabel, ENT_QUOTES, 'UTF-8') ?></span>
</div>
