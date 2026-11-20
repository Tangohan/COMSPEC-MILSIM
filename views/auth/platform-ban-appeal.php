<?php

declare(strict_types=1);

use App\Core\Csrf;

$escapeAppeal = static fn (string|int|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$authTitle = 'Décision de modération';
$athena_header_current = 'login';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<?php require base_path('views/partials/auth/head.php'); ?>
</head>
<body class="ds-page ds-page--split ds-page--auth">
<a class="ds-skip" href="#contenu">Aller au contenu</a>
<?php require base_path('views/partials/athena_header_guest.php'); ?>
<div class="ds-split">
    <?php $authAside = 'Consulter une décision de modération'; require base_path('views/partials/auth/visual.php'); ?>
    <main class="ds-main" id="contenu">
        <div class="ds-main__inner au-inner">
            <p class="ds-kicker">Modération Athena</p>
            <h1 class="ds-title"><?= $notice === null ? 'Contester un bannissement' : 'Décision de modération' ?></h1>
            <div class="ds-alert-stack">
                <?php if ($error): ?>
                    <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
                <?php endif; ?>
                <?php if ($success): ?>
                    <?php $flash_variant = 'success'; $flash_message = $success; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
                <?php endif; ?>
            </div>
            <?php if ($notice === null): ?>
                <p class="ds-lead rg-lead">Recevez un lien personnel pour lire la décision, déposer une contestation ou consulter la réponse de la modération.</p>
                <form class="ds-form rg-form" method="post" action="<?= $escapeAppeal(url('contester-sanction/lien')) ?>">
                    <?= Csrf::field() ?>
                    <section class="rg-section au-card" aria-label="Demander un lien">
                        <div class="rg-field">
                            <label class="rg-label" for="ban-appeal-email">E-mail du compte concerné</label>
                            <input class="rg-input" id="ban-appeal-email" type="email" name="email" maxlength="255" autocomplete="email" required>
                        </div>
                        <button class="ds-btn ds-btn--primary ds-btn--block au-btn au-btn--primary">Recevoir un lien</button>
                    </section>
                </form>
            <?php else: ?>
                <section class="rg-section au-card" aria-labelledby="ban-notice-title">
                    <h2 id="ban-notice-title">Décision n° <?= $escapeAppeal($notice['id']) ?></h2>
                    <p><strong><?= $notice['active'] ? 'Accès restreint' : 'Restriction terminée' ?></strong> · <?= $escapeAppeal($notice['end']) ?></p>
                    <p><?= nl2br($escapeAppeal($notice['message'])) ?></p>
                    <?php if ($notice['rule'] !== ''): ?>
                        <p><strong>Règle citée</strong> · <?= $escapeAppeal($notice['rule']) ?></p>
                    <?php endif; ?>
                    <a class="au-link" href="<?= $escapeAppeal(url('legal/site') . '#cgu') ?>">Lire les conditions d’utilisation</a>
                </section>
                <?php if ($appeal === null): ?>
                    <form class="ds-form rg-form" method="post" action="<?= $escapeAppeal(url('contester-sanction/deposer')) ?>">
                        <?= Csrf::field() ?>
                        <section class="rg-section au-card" aria-labelledby="ban-appeal-title">
                            <h2 id="ban-appeal-title">Demander un réexamen</h2>
                            <div class="rg-field">
                                <label class="rg-label" for="ban-appeal-body">Votre version des faits</label>
                                <textarea class="rg-input" id="ban-appeal-body" name="statement" rows="7" required minlength="10" maxlength="4000" aria-describedby="ban-appeal-help"></textarea>
                                <p class="rg-hint" id="ban-appeal-help">Précisez les faits et les dates utiles. Ne joignez ni mot de passe ni données privées d’une autre personne.</p>
                            </div>
                            <button class="ds-btn ds-btn--primary ds-btn--block au-btn au-btn--primary">Envoyer la contestation</button>
                        </section>
                    </form>
                <?php else: ?>
                    <section class="rg-section au-card" aria-labelledby="ban-followup-title">
                        <h2 id="ban-followup-title">Suivi de votre contestation</h2>
                        <p><strong><?= match ($appeal['decision']) {
                            'lift' => 'Contestation acceptée',
                            'uphold' => 'Décision maintenue',
                            default => 'En attente de réexamen',
                        } ?></strong></p>
                        <?php if ($appeal['reply'] !== null): ?><p><?= nl2br($escapeAppeal($appeal['reply'])) ?></p><?php endif; ?>
                        <details><summary>Relire votre demande</summary><p><?= nl2br($escapeAppeal($appeal['statement'])) ?></p></details>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
            <div class="ds-alt au-alt"><a class="au-link" href="<?= $escapeAppeal(url('login')) ?>">Retour à la connexion</a></div>
        </div>
    </main>
</div>
</body>
</html>
