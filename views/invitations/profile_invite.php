<?php
declare(strict_types=1);

/**
 * Lien « compléter son profil » (ProfileInviteController) : activation du compte ou connexion,
 * puis l'accueil du profil (/account/bienvenue) prend le relais.
 *
 * @var string $inviteMode activate | login | wrong_account | invalid
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mode = (string) ($inviteMode ?? 'invalid');
$community = (string) ($inviteCommunity ?? '');
$name = trim((string) ($inviteName ?? ''));
$needsEmail = !empty($inviteNeedsEmail);
$emailHint = (string) ($inviteEmailHint ?? '');
$expiresTs = strtotime((string) ($inviteExpiresAt ?? '')) ?: 0;
$flashErr = \App\Core\Session::getFlash('error');
$steps = [
    ['Compte', 'Votre mot de passe, ou votre connexion habituelle.'],
    ['Indicatif', 'Le nom court qu’on vous donne à la radio et sur la carte.'],
    ['Discord', 'Le serveur vous reconnaît et vous donne vos rôles.'],
    ['Steam', 'Le serveur de jeu et ATAK vous reconnaissent.'],
    ['Portrait', 'Votre visage d’opérateur sur la fiche et l’organigramme.'],
    ['Sécurité', 'Un second code à la connexion.'],
];
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/profile-invite.css')) ?>">
<main class="pinv" aria-labelledby="pinv-title">
<?php if ($mode === 'invalid'): ?>
    <section class="pinv-card pinv-card--solo">
        <p class="pinv-kicker"><i aria-hidden="true"></i>Lien personnel</p>
        <h1 id="pinv-title" class="pinv-title">Lien expiré</h1>
        <p class="pinv-lead"><?= $h((string) ($inviteInvalidMessage ?? '') !== '' ? $inviteInvalidMessage : 'Ce lien n’est plus valable : il a expiré, a déjà servi ou a été remplacé. Demandez un nouveau lien à la personne qui vous l’a envoyé.') ?></p>
        <p class="pinv-lead">Vous avez déjà un compte ? <a href="<?= $h(url('login')) ?>">Connectez-vous</a>, votre profil se complète depuis Mon compte.</p>
    </section>
<?php else: ?>
    <section class="pinv-intro">
        <p class="pinv-kicker"><i aria-hidden="true"></i><?= $h($community) ?></p>
        <h1 id="pinv-title" class="pinv-title">Compléter mon profil</h1>
        <p class="pinv-lead">
            <?php if ($name !== ''): ?><b><?= $h($name) ?></b>, votre<?php else: ?>Votre<?php endif; ?>
            fiche est prête dans les effectifs. Il reste quelques minutes pour qu’elle vous ressemble.
        </p>
        <ol class="pinv-steps">
            <?php foreach ($steps as $i => [$stepTitle, $stepText]): ?>
            <li class="<?= $i === 0 ? 'is-now' : '' ?>">
                <span class="pinv-steps__n" aria-hidden="true"><?= $i + 1 ?></span>
                <span><b><?= $h($stepTitle) ?></b><?= $h($stepText) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($expiresTs > 0): ?>
        <p class="pinv-note"><?= $h(sprintf(!empty($inviteSingleUse) ? 'Lien valable jusqu’au %s, une seule fois.' : 'Lien valable jusqu’au %s.', date('d/m/Y à H:i', $expiresTs))) ?></p>
        <?php endif; ?>
    </section>

    <section class="pinv-card" aria-label="Première étape">
        <?php if ($flashErr): ?>
            <p class="pinv-flash" role="alert"><?= $h((string) $flashErr) ?></p>
        <?php endif; ?>

        <?php if ($mode === 'activate'): ?>
            <h2 class="pinv-card__title">Activer mon compte</h2>
            <form method="post" action="<?= $h(url('invitation/profil/activer')) ?>" class="pinv-form" autocomplete="on">
                <?= \App\Core\Csrf::field() ?>
                <label class="pinv-field">
                    <span><?= $needsEmail ? 'Votre adresse e-mail' : 'Confirmez votre adresse e-mail' ?></span>
                    <input type="email" name="email" required autocomplete="email" inputmode="email" maxlength="190">
                    <small><?= $needsEmail
                        ? 'Elle servira à vous connecter et à récupérer votre mot de passe.'
                        : 'Celle que vous avez donnée à votre gestionnaire' . ($emailHint !== '' ? ' (' . $h($emailHint) . ')' : '') . '. Elle prouve que ce lien est bien entre vos mains.' ?></small>
                </label>
                <label class="pinv-field">
                    <span>Choisissez un mot de passe</span>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                    <small>8 caractères au moins. Une phrase courte est plus sûre qu’un mot compliqué.</small>
                </label>
                <label class="pinv-field">
                    <span>Retapez-le</span>
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </label>
                <button type="submit" class="pinv-btn">Activer et continuer</button>
            </form>
        <?php elseif ($mode === 'login'): ?>
            <h2 class="pinv-card__title">Vous avez déjà un compte</h2>
            <p class="pinv-lead">Connectez-vous comme d’habitude : vous reviendrez ici, puis sur les étapes de votre profil.</p>
            <a class="pinv-btn" href="<?= $h(url('login')) ?>">Me connecter</a>
            <p class="pinv-note">Mot de passe oublié ? <a href="<?= $h(url('forgot-password')) ?>">Recevoir un lien de réinitialisation</a>.</p>
        <?php else: ?>
            <h2 class="pinv-card__title">Ce lien est pour quelqu’un d’autre</h2>
            <p class="pinv-lead">Vous êtes connecté avec un autre compte. Déconnectez-vous, puis rouvrez le lien reçu.</p>
            <form method="post" action="<?= $h(url('logout')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="pinv-btn pinv-btn--ghost">Me déconnecter</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
</main>
