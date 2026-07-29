<?php
declare(strict_types=1);

/**
 * Modification d’un code d’invitation — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; les champs sont partagés avec
 * l’écran de création (`_form_fields.php`). Le code lui-même n’est pas modifiable.
 *
 * @var array<string, mixed> $inviteCode
 * @var list<array<string, mixed>> $recruitmentOpenings
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$inviteCode = is_array($inviteCode ?? null) ? $inviteCode : [];
$recruitmentOpenings = is_array($recruitmentOpenings ?? null) ? $recruitmentOpenings : [];
$csrfToken = \App\Core\Csrf::token();

$codeId = (int) ($inviteCode['id'] ?? 0);
$codeValue = trim((string) ($inviteCode['code'] ?? ''));
$baseUrl = url('back-office/recruitments/codes-invitation');
?>
<div class="ath-note">
    <p class="ath-note__title">Code prioritaire <span class="ath-mono"><?= $h($codeValue !== '' ? $codeValue : '—') ?></span></p>
    <p class="ath-note__text">
        Le code saisi par les candidats n’est pas modifiable : pour en changer, désactivez celui-ci
        depuis sa fiche et créez-en un nouveau. Les usages déjà enregistrés restent comptés.
    </p>
</div>

<form method="post" action="<?= $h($baseUrl . '/' . $codeId . '/modifier') ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Modifier le code prioritaire</span>
        <span class="ath-form__hint">Nom interne, quota, échéance et rattachement restent ajustables.</span>
    </div>
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    <?php
    $codeFieldEditable = false;
    $codeLabelValue = (string) ($inviteCode['label'] ?? '');
    $codeMaxUsesValue = ($inviteCode['max_uses'] ?? null) !== null ? (int) $inviteCode['max_uses'] : null;
    $codeExpiresAtValue = $inviteCode['expires_at'] ?? null;
    $codeAutoAcceptValue = !empty($inviteCode['auto_accept']);
    $codeOpeningIdValue = isset($inviteCode['assign_to_opening_id']) ? (int) $inviteCode['assign_to_opening_id'] : null;
    $codeSpecialtyValue = (string) ($inviteCode['default_specialty'] ?? '');
    require base_path('views/admin/organization/recruitment_invite_codes/_form_fields.php');
    ?>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
        <a href="<?= $h($baseUrl . '/' . $codeId) ?>" class="ath-btn">Retour à la fiche</a>
    </div>
</form>
