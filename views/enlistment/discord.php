<?php
declare(strict_types=1);
/** @var array<string,mixed> $tenant */
/** @var list<array{id:int,type:string,label:string,options:list<string>,required:bool}> $discordQuestions */
$tenant = $tenant ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$discordInviteUrl = trim((string) ($discordInviteUrl ?? ''));
$discordQuestions = is_array($discordQuestions ?? null) ? $discordQuestions : [];
$enlistmentContext = is_array($enlistmentContext ?? null) ? $enlistmentContext : [];
$isAuthenticated = !empty($enlistmentContext['isAuthenticated']);
$accountTheme = 'light';
$accountNeedsDiscord = true;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$loginStepUrl = (string) ($enlistmentContext['loginStepUrl'] ?? url('login'));
$registerStepUrl = (string) ($enlistmentContext['registerStepUrl'] ?? url('register'));
$discordStepUrl = $loginStepUrl . (str_contains($loginStepUrl, '?') ? '&' : '?') . 'discord=1';
?>
<link href="<?= $h(asset_url('assets/css/discord-recruitment.css')) ?>" rel="stylesheet">
<div class="ed-page">
    <h1 class="ed-title">Rejoindre <?= $h($tenantName) ?> sur Discord</h1>
    <p class="ed-lead">Le recrutement de cette communauté se fait par un échange sur Discord. Ouvrez le serveur, puis remplissez la fiche ci-dessous : elle permet à l’équipe de caler un rendez-vous avec vous.</p>

    <ol class="ed-steps">
        <li class="ed-step ed-step--join">
            <span class="ed-step__n" aria-hidden="true">1</span>
            <div>
                <h2 class="ed-step__title">Présentez-vous sur le Discord</h2>
                <?php if ($discordInviteUrl !== ''): ?>
                    <p class="ed-step__text">Ouvrez le serveur de la communauté pour vous présenter à l’équipe recrutement.</p>
                    <a href="<?= $h($discordInviteUrl) ?>" target="_blank" rel="noopener noreferrer" class="ed-link-btn">Ouvrir le Discord</a>
                <?php else: ?>
                    <p class="ed-step__text">Le lien du Discord de cette communauté n’est pas encore renseigné. L’équipe recrutement vous contactera après le dépôt de votre fiche.</p>
                <?php endif; ?>
            </div>
        </li>
        <li class="ed-step">
            <span class="ed-step__n" aria-hidden="true">2</span>
            <div>
                <h2 class="ed-step__title">Remplissez votre fiche de candidature</h2>
                <p class="ed-step__text">Quelques questions suffisent. Votre fiche reste consultable depuis votre espace candidat.</p>
            </div>
        </li>
    </ol>

    <?php if (!$isAuthenticated): ?>
    <section class="ed-gate" aria-labelledby="ed-gate-title">
        <h2 class="ed-gate__title" id="ed-gate-title">Connectez-vous pour candidater</h2>
        <p class="ed-step__text">Votre candidature chez <strong><?= $h($tenantName) ?></strong> est rattachée à votre compte : votre nom et votre e-mail sont repris de votre profil. Après la connexion, vous revenez sur cette fiche.</p>
        <div class="ed-gate__actions">
            <a class="ed-btn ed-btn--primary" href="<?= $h($loginStepUrl) ?>">Se connecter</a>
            <a class="ed-btn" href="<?= $h($registerStepUrl) ?>">Créer un compte</a>
        </div>
        <?php $discordNext = null; $discordHrefOverride = $discordStepUrl; require base_path('views/partials/auth/discord_button.php'); ?>
    </section>
    <?php else: ?>
    <form method="post" action="<?= $h($formAction) ?>" class="ed-card">
        <?= \App\Core\Csrf::field() ?>
        <?php require base_path('views/enlistment/_invite_link.php'); ?>

        <?php require base_path('views/enlistment/_account_identity.php'); ?>

        <?php foreach ($discordQuestions as $q): ?>
            <?php
            $fieldId = 'discord_q_' . (int) $q['id'];
            $fieldName = 'discord_q_' . (int) $q['id'];
            $required = !empty($q['required']);
            $type = (string) ($q['type'] ?? 'open');
            ?>
            <div class="ed-field">
                <?php if ($type === 'closed'): ?>
                    <span class="ed-label" id="<?= $h($fieldId) ?>-label"><?= $h((string) $q['label']) ?><?php if ($required): ?> <span class="ed-req" aria-hidden="true">*</span><?php endif; ?></span>
                    <div class="ed-choices" role="radiogroup" aria-labelledby="<?= $h($fieldId) ?>-label">
                        <label><input type="radio" name="<?= $h($fieldName) ?>" value="Oui" <?= $required ? 'required' : '' ?>> Oui</label>
                        <label><input type="radio" name="<?= $h($fieldName) ?>" value="Non"> Non</label>
                    </div>
                <?php else: ?>
                    <label for="<?= $h($fieldId) ?>"><?= $h((string) $q['label']) ?><?php if ($required): ?> <span class="ed-req" aria-hidden="true">*</span><?php endif; ?></label>
                    <?php if ($type === 'select'): ?>
                        <select id="<?= $h($fieldId) ?>" name="<?= $h($fieldName) ?>" <?= $required ? 'required' : '' ?> class="ed-input">
                            <option value="">Sélectionnez</option>
                            <?php foreach ((array) ($q['options'] ?? []) as $opt): ?>
                                <option value="<?= $h((string) $opt) ?>"><?= $h((string) $opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($type === 'open'): ?>
                        <textarea id="<?= $h($fieldId) ?>" name="<?= $h($fieldName) ?>" rows="3" <?= $required ? 'required' : '' ?> class="ed-input"></textarea>
                    <?php else: ?>
                        <input type="text" id="<?= $h($fieldId) ?>" name="<?= $h($fieldName) ?>" maxlength="4000" <?= $required ? 'required' : '' ?> class="ed-input">
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="ed-field">
            <label for="invite-code">Code d’invitation prioritaire (facultatif)</label>
            <p class="ed-hint">Si l’équipe vous a transmis un code prioritaire, saisissez-le ici pour accélérer votre candidature.</p>
            <input type="text" name="invite_code" id="invite-code" maxlength="64" pattern="[A-Z0-9\-_]*" placeholder="Ex : MIGRATION2026" class="ed-input ed-code">
        </div>

        <?php require base_path('views/enlistment/_account_consent.php'); ?>

        <button type="submit" class="ed-submit">Envoyer ma candidature</button>
    </form>
    <?php endif; ?>
</div>
