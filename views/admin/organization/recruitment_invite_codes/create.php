<?php
declare(strict_types=1);

/**
 * Création d’un code d’invitation prioritaire — charte ATHENA.
 *
 * @var list<array<string, mixed>> $recruitmentOpenings
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$recruitmentOpenings = is_array($recruitmentOpenings ?? null) ? $recruitmentOpenings : [];
$csrfToken = \App\Core\Csrf::token();
$baseUrl = url('back-office/recruitments/codes-invitation');
?>
<div class="ath-note">
    <p class="ath-note__title">À quoi sert ce code ?</p>
    <p class="ath-note__text">
        Un code prioritaire accélère une candidature sur le formulaire d’enrôlement.
        Ce n’est <strong>pas</strong> une invitation par e-mail ni le code communauté :
        la personne saisit ce code dans sa candidature. Le texte du code n’est plus
        modifiable après création ; le nom interne, le quota et l’échéance le restent.
    </p>
</div>

<form method="post" action="<?= $h($baseUrl . '/creer') ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Nouveau code d’invitation prioritaire</span>
        <span class="ath-form__hint">Les champs marqués d’une astérisque sont obligatoires.</span>
    </div>
    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
    <?php
    $codeFieldEditable = true;
    $codeLabelValue = '';
    $codeMaxUsesValue = null;
    $codeExpiresAtValue = null;
    $codeAutoAcceptValue = true;
    $codeOpeningIdValue = null;
    $codeSpecialtyValue = '';
    require base_path('views/admin/organization/recruitment_invite_codes/_form_fields.php');
    ?>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Créer le code prioritaire</button>
        <a href="<?= $h($baseUrl) ?>" class="ath-btn">Annuler</a>
    </div>
</form>
