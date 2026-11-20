<?php
declare(strict_types=1);

use App\Core\Csrf;

$e = static fn (string|int|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$authTitle = $notice === null ? 'Contester une sanction' : 'Décision de modération';
$athena_header_current = 'login';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require base_path('views/partials/auth/head.php'); ?>
    <link rel="stylesheet" href="<?= $e(asset_url('assets/css/auth-appeal.css')) ?>">
</head>
<body class="ds-page ds-page--auth athena-appeal">
<a class="ds-skip" href="#contenu">Aller au contenu</a>
<?php require base_path('views/partials/athena_header_guest.php'); ?>
<main class="appeal-shell" id="contenu">
    <header class="appeal-hero">
        <div>
            <p class="ds-kicker">Modération Athena</p>
            <h1><?= $e($authTitle) ?></h1>
            <p><?= $notice === null
                ? 'Consultez la décision concernant votre compte et demandez son réexamen.'
                : 'Retrouvez la restriction appliquée à votre compte et le suivi de votre recours.' ?></p>
        </div>
        <?php if ($notice !== null): ?>
            <span class="appeal-state"><?= $notice['active'] ? 'Restriction en cours' : 'Restriction terminée' ?></span>
        <?php endif; ?>
    </header>

    <div class="ds-alert-stack">
        <?php if ($error): ?>
            <?php $flash_variant = 'error'; $flash_message = $error; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($success): ?>
            <?php $flash_variant = 'success'; $flash_message = $success; $flash_margin_class = ''; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
    </div>

    <div class="appeal-grid">
        <?php if ($notice === null): ?>
            <section class="appeal-card" aria-labelledby="appeal-request">
                <h2 id="appeal-request">Recevoir votre lien confidentiel</h2>
                <form class="ds-form rg-form" method="post" action="<?= $e(url('contester-sanction/lien')) ?>" data-busy-form>
                    <?= Csrf::field() ?>
                    <div class="rg-field">
                        <label class="rg-label" for="ban-appeal-email">Adresse e-mail du compte Athena</label>
                        <input class="rg-input" id="ban-appeal-email" type="email" name="email" maxlength="255" autocomplete="email" required aria-describedby="appeal-delivery">
                        <p class="rg-hint" id="appeal-delivery">Si une décision correspond, le lien est envoyé à cette adresse. Pensez à vérifier les courriers indésirables.</p>
                    </div>
                    <button type="submit" class="ds-btn ds-btn--primary au-btn" data-busy-label="Envoi en cours…">Recevoir le lien</button>
                </form>
            </section>
            <aside class="appeal-card appeal-card--guide" aria-labelledby="appeal-steps">
                <h2 id="appeal-steps">Le parcours de contestation</h2>
                <ol class="appeal-steps">
                    <li><strong>Ouvrez le lien reçu</strong><span>Il est valable une heure et donne accès à la décision.</span></li>
                    <li><strong>Exposez les faits</strong><span>Indiquez les dates et le contexte utiles au réexamen.</span></li>
                    <li><strong>Consultez la réponse</strong><span>Rouvrez votre lien ou demandez-en un nouveau pour retrouver le suivi.</span></li>
                </ol>
            </aside>
        <?php else: ?>
            <section class="appeal-card" aria-labelledby="ban-notice-title">
                <h2 id="ban-notice-title">Décision n° <?= $e($notice['id']) ?></h2>
                <p class="appeal-message"><?= $e($notice['message']) ?></p>
                <dl class="appeal-facts">
                    <?php if ($notice['active']): ?>
                        <div><dt>Fin de la restriction</dt><dd><?= $e($notice['end']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($notice['rule'] !== ''): ?>
                        <div><dt>Règle citée</dt><dd><?= $e($notice['rule']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </section>
            <?php if ($appeal === null): ?>
                <section class="appeal-card" aria-labelledby="ban-appeal-title">
                    <h2 id="ban-appeal-title">Demander un réexamen</h2>
                    <form class="ds-form rg-form" method="post" action="<?= $e(url('contester-sanction/deposer')) ?>" data-busy-form>
                        <?= Csrf::field() ?>
                        <div class="rg-field">
                            <label class="rg-label" for="ban-appeal-body">Votre version des faits</label>
                            <textarea class="rg-input" id="ban-appeal-body" name="statement" rows="7" required minlength="10" maxlength="4000" aria-describedby="ban-appeal-help"></textarea>
                            <p class="rg-hint" id="ban-appeal-help">Entre 10 et 4 000 caractères. Indiquez les faits et les dates utiles, en préservant les informations privées des autres personnes.</p>
                        </div>
                        <button type="submit" class="ds-btn ds-btn--primary au-btn" data-busy-label="Envoi en cours…">Envoyer la contestation</button>
                    </form>
                </section>
            <?php else: ?>
                <section class="appeal-card" aria-labelledby="ban-followup-title">
                    <h2 id="ban-followup-title">Suivi de votre contestation</h2>
                    <p class="appeal-review"><?= match ($appeal['decision']) {
                        'lift' => 'Contestation acceptée',
                        'uphold' => 'Décision maintenue',
                        default => 'En attente de réexamen',
                    } ?></p>
                    <?php if ($appeal['reply'] !== null): ?>
                        <h3>Réponse de la modération</h3>
                        <p class="appeal-message"><?= $e($appeal['reply']) ?></p>
                    <?php elseif ($appeal['decision'] === null): ?>
                        <p>Votre demande a été transmise. La réponse apparaîtra ici.</p>
                    <?php endif; ?>
                    <details class="appeal-statement">
                        <summary>Relire votre demande</summary>
                        <p class="appeal-message"><?= $e($appeal['statement']) ?></p>
                    </details>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="appeal-footer" aria-label="Liens utiles">
        <a class="au-link" href="<?= $e(url('login')) ?>">Retour à la connexion</a>
        <a class="au-link" href="<?= $e(url('legal/site') . '#cgu') ?>">Conditions d’utilisation</a>
        <a class="au-link" href="<?= $e(url('contact')) ?>">Contacter l’assistance</a>
    </footer>
</main>
<script defer src="<?= $e(asset_url('assets/js/athena-header.js')) ?>"></script>
<script defer src="<?= $e(asset_url('assets/js/auth_forms.js')) ?>"></script>
</body>
</html>
