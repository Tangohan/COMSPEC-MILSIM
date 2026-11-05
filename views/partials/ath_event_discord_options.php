<?php
/** @var array<string, mixed>|null $eventDiscord */
$discordDirectory = new \App\Services\Integrations\DiscordWebhookDirectory();
$discordTenant = (int) \App\Core\Session::get('tenant_id');
$discordOrganisation = $discordDirectory->organisation($discordTenant);
$discordOptions = $discordDirectory->list($discordTenant);
$companion = (new \App\Repositories\DiscordCompanionRepository())->forTenant($discordTenant);
$companionChannels = $companion ? json_decode((string) $companion['channels_json'], true) : [];
$eventDiscord = $eventDiscord ?? null;
?>
<fieldset class="ev-create__repeat">
    <legend><?= $h(__('events.discord')) ?></legend>
    <label class="ev-create__check"><input type="checkbox" name="discord_publish" value="1" <?= !empty($eventDiscord['enabled']) ? 'checked' : '' ?>> <?= $h(__('events.discord_publish')) ?></label>
    <div class="ev-create__field">
        <label for="<?= $h($id('discord-destination')) ?>"><?= $h(__('events.destination')) ?></label>
        <select id="<?= $h($id('discord-destination')) ?>" name="discord_destination">
            <?php if ($companion): ?>
            <option value="companion" <?= ($eventDiscord['destination'] ?? '') === 'companion' ? 'selected' : '' ?>><?= $h(__('companion.channel_choice', ['channel' => $companionChannels[$companion['channel_id']] ?? $companion['channel_id']])) ?></option>
            <?php endif; ?>
            <option value="organisation" <?= ($eventDiscord['destination'] ?? ($companion ? 'companion' : 'organisation')) === 'organisation' ? 'selected' : '' ?>><?= $h(__('events.organisation') . ($discordOrganisation['channel'] !== '' ? ' (' . $discordOrganisation['channel'] . ')' : '')) ?></option>
            <?php foreach ($discordOptions as $option): ?>
            <option value="<?= $h((string) $option['id']) ?>" <?= ($eventDiscord['destination'] ?? '') === $option['id'] ? 'selected' : '' ?>><?= $h((string) $option['name'] . ($option['channel'] !== '' ? ' (' . $option['channel'] . ')' : '')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <label class="ev-create__check"><input type="checkbox" name="discord_rsvp" value="1" <?= !empty($eventDiscord['rsvp_enabled']) ? 'checked' : '' ?>> <?= $h(__('events.discord_rsvp')) ?> · ✅ <?= $h(__('events.yes')) ?> · ❌ <?= $h(__('events.no')) ?> · ❓ <?= $h(__('events.maybe')) ?></label>
    <p class="ev-create__lead"><?= $h(__('events.discord_bot_hint')) ?></p>
    <a class="ath-btn" href="<?= $h(url('back-office/integrations')) ?>"><?= $h(__('events.webhook_manage')) ?></a>
</fieldset>
