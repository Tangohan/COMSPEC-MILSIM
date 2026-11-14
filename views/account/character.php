<?php
declare(strict_types=1);

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$user = $user ?? [];
$characterValues = is_array($characterValues ?? null) ? $characterValues : [];
$characterExists = !empty($characterExists);
$characterPortraitUrl = isset($characterPortraitUrl) && is_string($characterPortraitUrl) ? $characterPortraitUrl : null;
$error = $error ?? null;

$accountNavKey = 'overview';
$accountTitle = $characterExists ? 'Mon personnage' : 'Créer mon personnage';
$accountLead = 'Son nom et son indicatif, rien de plus. Le portrait s’ajoute juste après.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-body">
        <?php if ($error): ?>
        <p class="account-hub__ov-alert account-hub__ov-alert--danger" role="alert"><?= $h($error) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= $h(url('account/personnage')) ?>" class="ah-char-form">
            <?= \App\Core\Csrf::field() ?>
            <label>Prénom
                <input type="text" name="first_name" value="<?= $h($characterValues['first_name'] ?? '') ?>" maxlength="60" required autocomplete="off">
            </label>
            <label>Nom
                <input type="text" name="last_name" value="<?= $h($characterValues['last_name'] ?? '') ?>" maxlength="60" required autocomplete="off">
            </label>
            <label>Indicatif
                <input type="text" name="callsign" value="<?= $h($characterValues['callsign'] ?? '') ?>" maxlength="50" required autocomplete="off">
            </label>
            <label>Devise (facultatif)
                <input type="text" name="motto" value="<?= $h($characterValues['motto'] ?? '') ?>" maxlength="255" autocomplete="off">
            </label>
            <div class="ah-char-form__actions">
                <button type="submit" class="account-hub__btn account-hub__btn--ink"><?= $characterExists ? 'Enregistrer' : 'Créer mon personnage' ?></button>
                <a href="<?= $h(url('account/portrait')) ?>" class="account-hub__btn"><?= $characterPortraitUrl ? 'Changer le portrait' : 'Ajouter un portrait' ?></a>
            </div>
        </form>
    </div>
</section>

<?php require base_path('views/partials/account/shell_close.php'); ?>
