<?php
declare(strict_types=1);

/**
 * Création d’un code d’invitation — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office ; les champs sont partagés avec
 * l’écran de modification (`_form_fields.php`).
 *
 * @var list<array<string, mixed>> $recruitmentOpenings
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$recruitmentOpenings = is_array($recruitmentOpenings ?? null) ? $recruitmentOpenings : [];
$csrfToken = \App\Core\Csrf::token();
$baseUrl = url('back-office/recruitments/codes-invitation');
?>
<div class="ath-note">
    <p class="ath-note__title">Ce que fait un code</p>
    <p class="ath-note__text">
        Le code permet à une personne de rejoindre la communauté sans repasser par le circuit
        complet de candidature. Le <strong>code</strong> lui-même n’est plus modifiable après
        création : le libellé, le quota et l’échéance le restent.
    </p>
</div>

<form method="post" action="<?= $h($baseUrl . '/creer') ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Nouveau code d’invitation</span>
        <span class="ath-form__hint">Les champs marqués d’une astérisque sont obligatoires.</span>
    </div>
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    <?php
    $codeFieldEditable = true;
    $codeLabelValue = '';
    $codeMaxUsesValue = null;
    $codeExpiresAtValue = null;
    $codeAutoAcceptValue = false;
    $codeOpeningIdValue = null;
    $codeSpecialtyValue = '';
    require base_path('views/admin/organization/recruitment_invite_codes/_form_fields.php');
    ?>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Créer le code</button>
        <a href="<?= $h($baseUrl) ?>" class="ath-btn">Annuler</a>
    </div>
</form>
