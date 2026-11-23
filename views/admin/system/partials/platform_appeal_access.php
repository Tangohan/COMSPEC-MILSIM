<?php
declare(strict_types=1);

$isAppealClosed = !empty($appealDecision['appeal_closed']);
$accessUrl = htmlspecialchars(url('admin/sanctions/contestations/' . (int) $appealDecision['id'] . '/acces'), ENT_QUOTES, 'UTF-8');
?>
<form method="post" action="<?= $accessUrl ?>" class="pu-actions">
    <?= \App\Core\Csrf::field() ?>
    <?php if (!empty($fromPersonDossier)): ?><input type="hidden" name="return_person" value="1"><?php endif; ?>
    <input type="hidden" name="appeal_access" value="<?= $isAppealClosed ? 'open' : 'closed' ?>">
    <span class="pa-pill <?= $isAppealClosed ? 'pa-pill--rose' : 'pa-pill--mint' ?>"><?= $isAppealClosed ? 'Contestations fermées' : 'Contestations ouvertes' ?></span>
    <button type="submit" class="pa-btn pa-btn--line pa-btn--sm"><?= $isAppealClosed ? 'Rouvrir les contestations' : 'Interdire les nouvelles contestations' ?></button>
</form>
