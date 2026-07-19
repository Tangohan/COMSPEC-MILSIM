<?php
declare(strict_types=1);

/**
 * Groupe de boutons RSVP rapide (oui/peut-être/non) — progressive enhancement JS
 * via /api/events/{id}/rsvp (voir dashboard-rsvp.js), pas de rechargement de page.
 *
 * @var int $rsvpEventId
 * @var string $rsvpCurrentStatus
 * @var bool $rsvpCompact
 */

$rsvpEventId = (int) ($rsvpEventId ?? 0);
if ($rsvpEventId < 1) {
    return;
}
$rsvpCurrentStatus = (string) ($rsvpCurrentStatus ?? '');
$rsvpCompact = (bool) ($rsvpCompact ?? false);
?>
<div class="dash-rsvp<?= $rsvpCompact ? ' dash-rsvp--compact' : '' ?>"
     data-rsvp-group
     data-event-id="<?= $rsvpEventId ?>"
     data-rsvp-current="<?= htmlspecialchars($rsvpCurrentStatus, ENT_QUOTES, 'UTF-8') ?>">
    <button type="button" class="dash-rsvp__btn dash-rsvp__btn--yes" data-rsvp-choice="yes">Je participe</button>
    <button type="button" class="dash-rsvp__btn dash-rsvp__btn--maybe" data-rsvp-choice="maybe">Peut-être</button>
    <button type="button" class="dash-rsvp__btn dash-rsvp__btn--no" data-rsvp-choice="no">Absent</button>
    <span class="dash-rsvp__status" data-rsvp-status-label></span>
</div>
