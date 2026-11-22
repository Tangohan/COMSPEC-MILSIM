<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Support\PlatformBoMessage;

if ($alertTenants === []) {
    return;
}
$alertEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$alertText = static fn (string $key, array $values = []): string => $alertEscape(__('admin_community_alert.' . $key, $values));
$primaryLanguage = locale() === 'en' ? 'en' : 'fr';
$secondaryLanguage = $primaryLanguage === 'fr' ? 'en' : 'fr';
?>
<dialog class="pu-dialog" data-community-alert-dialog aria-labelledby="community-alert-title" aria-describedby="community-alert-audience">
    <form class="pu-dialog__form" method="post" action="<?= $alertEscape(url('admin/system/messages-communautes/cible')) ?>"
          data-publishing="<?= $alertText('publishing') ?>" data-error="<?= $alertText('publish_failed') ?>">
        <?= Csrf::field() ?>
        <h2 class="pu-dialog__title" id="community-alert-title"><?= $alertText('open') ?></h2>
        <p class="pu-muted" id="community-alert-audience"><?= $alertText('audience') ?></p>
        <div data-community-alert-fields>
            <?php if (count($alertTenants) === 1): ?>
                <input type="hidden" name="tenant_id" value="<?= (int) $alertTenants[0]['id'] ?>">
                <p><strong><?= $alertEscape(community_display_name($alertTenants[0])) ?></strong></p>
            <?php else: ?>
                <label class="pu-dialog__field">
                    <span><?= $alertText('tenant') ?></span>
                    <select name="tenant_id" required>
                        <option value=""><?= $alertText('choose_tenant') ?></option>
                        <?php foreach ($alertTenants as $alertTenant): $alertTenantId = (int) $alertTenant['id']; ?>
                            <option value="<?= $alertTenantId ?>"<?= $alertTenantId === $alertDefaultTenant ? ' selected' : '' ?>><?= $alertEscape(community_display_name($alertTenant)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="pu-dialog__field">
                <span><?= $alertText('title') ?></span>
                <input type="text" name="title_<?= $primaryLanguage ?>" maxlength="<?= PlatformBoMessage::TITLE_MAX ?>" required>
            </label>
            <label class="pu-dialog__field">
                <span><?= $alertText('body') ?></span>
                <textarea name="body_<?= $primaryLanguage ?>" rows="4" maxlength="<?= PlatformBoMessage::BODY_MAX ?>" required></textarea>
            </label>
            <details data-community-alert-translation>
                <summary><?= $alertText('translation') ?></summary>
                <label class="pu-dialog__field">
                    <span><?= $alertText('title') ?></span>
                    <input type="text" name="title_<?= $secondaryLanguage ?>" maxlength="<?= PlatformBoMessage::TITLE_MAX ?>">
                </label>
                <label class="pu-dialog__field">
                    <span><?= $alertText('body') ?></span>
                    <textarea name="body_<?= $secondaryLanguage ?>" rows="3" maxlength="<?= PlatformBoMessage::BODY_MAX ?>"></textarea>
                </label>
            </details>
            <div class="pu-community-alert__options">
                <label class="pu-dialog__field">
                    <span><?= $alertText('level') ?></span>
                    <select name="level">
                        <?php foreach (PlatformBoMessage::LEVELS as $alertLevel): ?>
                            <option value="<?= $alertLevel ?>"<?= $alertLevel === 'important' ? ' selected' : '' ?>><?= $alertText($alertLevel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="pu-dialog__field">
                    <span><?= $alertText('duration') ?></span>
                    <select name="duration_days">
                        <?php foreach ([7, 14, 30] as $alertDays): ?>
                            <option value="<?= $alertDays ?>"><?= $alertText('days', ['count' => $alertDays]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
        <p class="pa-flash" role="status" data-community-alert-status hidden></p>
        <div class="pu-dialog__buttons">
            <button class="pa-btn pa-btn--line" type="button" data-community-alert-close><?= $alertText('close') ?></button>
            <button class="pa-btn pa-btn--solid" type="submit" data-community-alert-submit><?= $alertText('publish') ?></button>
            <a class="pa-btn pa-btn--solid" data-community-alert-tracking hidden><?= $alertText('tracking') ?></a>
        </div>
    </form>
</dialog>
<script src="<?= $alertEscape(asset_url('assets/js/platform-community-alert.js')) ?>" defer></script>
