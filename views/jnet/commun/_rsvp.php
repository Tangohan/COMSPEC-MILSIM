<?php
/**
 * Réponse d'un membre à un événement (présent, incertain, absent). Sans JavaScript, chaque bouton est un formulaire vers la
 * route existante des événements ; avec, jnet_commun.js envoie la réponse sans recharger (API JSON des événements).
 *
 * @var array<string, mixed> $e événement normalisé (JnetAgenda::normalize)
 */

use App\Services\Jnet\JnetAgenda;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rsvpTone = ['yes' => 'ok', 'maybe' => 'warn', 'no' => 'bad'];
?>
<div class="jc-rsvp" role="group" aria-label="Votre réponse" data-jc-rsvp-group="<?= (int) $e['id'] ?>">
    <?php foreach (JnetAgenda::RSVP as $status => $label): ?>
        <form method="post" action="<?= $h(url('evenements/rsvp')) ?>" data-jc-rsvp="<?= (int) $e['id'] ?>" data-status="<?= $h($status) ?>" data-api="<?= $h(url('api/events/' . (int) $e['id'] . '/rsvp')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="event_id" value="<?= (int) $e['id'] ?>">
            <input type="hidden" name="status" value="<?= $h($status) ?>">
            <input type="hidden" name="return_to" value="jnet">
            <button type="submit" class="jc-rs jc-rs--<?= $h($rsvpTone[$status]) ?><?= (string) $e['rsvp'] === $status ? ' is-on' : '' ?>" aria-pressed="<?= (string) $e['rsvp'] === $status ? 'true' : 'false' ?>"><?= $h($label) ?></button>
        </form>
    <?php endforeach; ?>
</div>
