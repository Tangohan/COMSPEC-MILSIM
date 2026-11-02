<?php
declare(strict_types=1);

/**
 * Création d'un lien d'invitation — charte ATHENA.
 *
 * @var array<string, string> $inviteLinkOld saisie conservée après une erreur
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$baseUrl = url('back-office/recruitments/liens-invitation');
$linkForm = is_array($inviteLinkOld ?? null) && $inviteLinkOld !== [] ? $inviteLinkOld : ['link_action' => 'apply', 'expiry' => '7d'];
$linkFormIsEdit = false;
?>
<div class="ath-note">
    <p class="ath-note__title">Avant de partager</p>
    <p class="ath-note__text">
        Le lien affiche votre page de présentation (nom, emblème, bannière, jeu, membres) puis l’action choisie.
        Son adresse ne change jamais : vous pourrez modifier les réglages, le révoquer ou le réactiver sans le renvoyer.
    </p>
</div>

<form method="post" action="<?= $h($baseUrl . '/creer') ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Nouveau lien d’invitation</span>
        <span class="ath-form__hint">Les champs marqués d’une astérisque sont obligatoires.</span>
    </div>
    <?= \App\Core\Csrf::field() ?>
    <?php require base_path('views/admin/organization/recruitment_invite_links/_form_fields.php'); ?>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Créer le lien</button>
        <a href="<?= $h($baseUrl) ?>" class="ath-btn">Annuler</a>
    </div>
</form>
