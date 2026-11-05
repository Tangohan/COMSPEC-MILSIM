<?php if (!empty($eventEndless)): ?>
<section class="ath-card ev-create">
    <h2 class="ev-create__title"><?= $h(__('events.endless_active')) ?></h2>
    <p class="ev-create__lead"><?= $h(__('events.series_stop_hint')) ?></p>
    <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/series/stop')) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
        <button type="submit" class="ath-btn"><?= $h(__('events.series_stop')) ?></button>
    </form>
</section>
<?php endif; ?>
<?php if (!$cancelled && $eventStaffActionsEnabled): ?>
<section class="ath-card ev-create">
    <form method="post" action="<?= $h(url('back-office/events/' . $eid . '/discord')) ?>" class="ev-create">
        <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
        <?php $id = static fn (string $name): string => 'event-show-' . $name; require base_path('views/partials/ath_event_discord_options.php'); ?>
        <?php if (!empty($eventDiscord['last_error'])): ?>
        <p role="alert"><?= $h(__((string) $eventDiscord['last_error'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($eventDiscord['message_id'])): ?>
        <a class="ath-btn" href="https://discord.com/channels/@me/<?= $h((string) $eventDiscord['channel_id']) ?>/<?= $h((string) $eventDiscord['message_id']) ?>" target="_blank" rel="noopener"><?= $h(__('events.discord_message')) ?></a>
        <?php elseif ($eventDiscord): ?>
        <p class="ev-create__lead"><?= $h(__('events.discord_pending')) ?></p>
        <?php endif; ?>
        <button type="submit" class="ath-btn ath-btn--solid"><?= $h(__('events.discord_sync')) ?></button>
    </form>
    <a class="ath-btn" href="<?= $h(url('back-office/events#event-discord-settings')) ?>"><?= $h(__('events.discord_settings')) ?></a>
</section>
<?php endif; ?>
