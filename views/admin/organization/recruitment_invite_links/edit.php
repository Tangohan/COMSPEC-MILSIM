<?php
declare(strict_types=1);

/**
 * Modification d'un lien d'invitation — charte ATHENA. L'adresse publique ne change pas.
 *
 * @var array<string, mixed> $inviteLink
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$baseUrl = url('back-office/recruitments/liens-invitation');
$link = is_array($inviteLink ?? null) ? $inviteLink : [];
$meta = is_array($link['metadata'] ?? null) ? $link['metadata'] : [];
$expires = trim((string) ($link['expires_at'] ?? ''));
$linkForm = [
    'label' => (string) ($link['label'] ?? ''),
    'link_action' => (string) ($link['link_action'] ?? 'apply'),
    'registration_mode' => (string) ($meta['registration_mode'] ?? ''),
    'opening_id' => (string) (int) ($link['assign_to_opening_id'] ?? 0),
    'unit_id' => (string) (int) ($meta['unit_id'] ?? 0),
    'priority_code_id' => (string) (int) ($meta['priority_code_id'] ?? 0),
    'grade_id' => (string) (int) ($meta['grade_id'] ?? 0),
    'requires_approval' => !empty($link['requires_approval']) ? '1' : '0',
    'expiry' => 'keep',
    'expires_at' => $expires !== '' ? date('Y-m-d\TH:i', (int) strtotime($expires)) : '',
    'max_uses' => $link['max_uses'] !== null ? (string) $link['max_uses'] : '',
];
$linkFormIsEdit = true;
$linkFormCurrentExpiry = $expires !== '' ? date('d/m/Y H:i', (int) strtotime($expires)) : 'jamais';
$id = (int) ($link['id'] ?? 0);
?>
<form method="post" action="<?= $h($baseUrl . '/' . $id . '/modifier') ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Modifier le lien</span>
        <span class="ath-form__hint">Adresse : <span class="ath-mono" translate="no"><?= $h(\App\Services\Recruitment\InviteLinkService::publicUrl((string) ($link['public_code'] ?? ''))) ?></span></span>
    </div>
    <?= \App\Core\Csrf::field() ?>
    <?php require base_path('views/admin/organization/recruitment_invite_links/_form_fields.php'); ?>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
        <a href="<?= $h($baseUrl . '/' . $id) ?>" class="ath-btn">Annuler</a>
    </div>
</form>
