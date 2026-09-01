<?php

declare(strict_types=1);

/**
 * Fenêtre « Mise à jour » du portail — même système que le chrome (layout.main) et ATAK.
 * Non bloquant : Actualiser ou Plus tard. Inclus depuis le tableau de bord membre.
 */
if (!empty($GLOBALS['athena_app_update_check_included'])) {
    return;
}
$GLOBALS['athena_app_update_check_included'] = true;
?>
<?php if (is_file(base_path('public/assets/css/app-update-modal.css'))): ?>
<link href="<?= htmlspecialchars(asset_url('assets/css/app-update-modal.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php endif; ?>
<script>
  window.APP_VERSION = <?= json_encode(platform_app_version(), JSON_UNESCAPED_UNICODE) ?>;
  window.APP_BASE_URL = <?= json_encode(rtrim((string) url(''), '/'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/app-version-check.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
