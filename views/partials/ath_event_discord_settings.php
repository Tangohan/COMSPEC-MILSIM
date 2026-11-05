<?php
$botConfigured = (new \App\Services\Integrations\DiscordEventAttendanceService())->botToken((int) \App\Core\Session::get('tenant_id')) !== '';
?>
<details id="event-discord-settings" class="ath-card ev-discord-settings">
    <summary><?= $h(__('events.discord_settings')) ?> · <?= $h(__($botConfigured ? 'events.bot_configured' : 'events.bot_missing')) ?></summary>
    <form method="post" action="<?= $h(url('back-office/events/discord/bot')) ?>" class="ev-create">
        <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
        <p class="ev-create__lead"><?= $h(__('events.discord_bot_hint')) ?></p>
        <div class="ev-create__field">
            <label for="event-discord-bot-token"><?= $h(__('events.bot_token')) ?></label>
            <input id="event-discord-bot-token" type="password" name="discord_bot_token" autocomplete="new-password" maxlength="200" placeholder="<?= $h(__('events.bot_keep')) ?>">
        </div>
        <label class="ev-create__check"><input type="checkbox" name="clear_bot" value="1"> <?= $h(__('events.bot_clear')) ?></label>
        <div class="ev-create__foot">
            <a class="ath-btn" href="<?= $h(url('back-office/integrations')) ?>"><?= $h(__('events.webhook_manage')) ?></a>
            <button class="ath-btn ath-btn--solid" type="submit"><?= $h(__('events.save')) ?></button>
        </div>
    </form>
</details>
