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
// Habillage sombre, le même que la page publique de la communauté (community-public-dark.css) : on arrive ici par son
// bouton « Rejoindre ». Les blocs communs (identité, consentement) sortent leur version sombre (classes ce-*).
$accountTheme = 'dark';
$accountNeedsDiscord = true;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$loginStepUrl = (string) ($enlistmentContext['loginStepUrl'] ?? url('login'));
$registerStepUrl = (string) ($enlistmentContext['registerStepUrl'] ?? url('register'));
$discordStepUrl = $loginStepUrl . (str_contains($loginStepUrl, '?') ? '&' : '?') . 'discord=1';
$edBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];
$edLogo = trim((string) ($edBranding['logo_url'] ?? ''));
$edBanner = trim((string) ($edBranding['banner_url'] ?? ''));
foreach (['edLogo', 'edBanner'] as $edKey) {
    if ($$edKey !== '' && preg_match('#^https?://#i', $$edKey) !== 1) {
        $$edKey = url(ltrim($$edKey, '/'));
    }
}
$edPrimary = trim((string) ($edBranding['primary_color'] ?? ''));
$edStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $edPrimary) === 1 ? '--cp-tenant-primary:' . $edPrimary . ';' : '';
$edSlug = trim((string) ($tenant['slug'] ?? ''));
$edCommunityUrl = $edSlug !== '' ? url('c/' . rawurlencode($edSlug)) : url('');
$edLetter = mb_strtoupper(mb_substr($tenantName, 0, 1));
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= $h(asset_url('assets/css/community-public-dark.css')) ?>" rel="stylesheet">
<link href="<?= $h(asset_url('assets/css/discord-recruitment.css')) ?>" rel="stylesheet">
<div class="cp ed-cp"<?= $edStyle !== '' ? ' style="' . $h($edStyle) . '"' : '' ?>>
    <?php if ($edBanner !== ''): ?>
    <div class="cp__bg" aria-hidden="true"><img src="<?= $h($edBanner) ?>" alt=""></div>
    <?php endif; ?>
    <div class="cp__grid ed-cp__grid">
        <div class="ed-cp__main">
            <div class="ed-cp__brand">
                <?php if ($edLogo !== ''): ?>
                    <img class="cp__logo" src="<?= $h($edLogo) ?>" alt="Emblème de la communauté" width="72" height="72">
                <?php else: ?>
                    <span class="cp__mark" aria-hidden="true"><?= $h($edLetter) ?></span>
                <?php endif; ?>
                <div class="cp__chips">
                    <span class="cp__chip cp__chip--open">Recrutement ouvert</span>
                    <span class="cp__chip">Par Discord</span>
                </div>
            </div>
            <h1 class="cp__title">Rejoindre <?= $h($tenantName) ?></h1>
            <p class="cp__lead">Ici, on se rencontre d’abord sur Discord. Trois étapes, quelques minutes.</p>

            <ol class="edc-steps">
                <li class="edc-step">
                    <span class="edc-step__n" aria-hidden="true">1</span>
                    <div class="edc-step__body">
                        <h2 class="edc-step__title">Passez sur le Discord</h2>
                        <?php if ($discordInviteUrl !== ''): ?>
                            <p class="edc-step__text">Présentez-vous à l’équipe recrutement sur le serveur de la communauté.</p>
                            <a href="<?= $h($discordInviteUrl) ?>" target="_blank" rel="noopener noreferrer" class="edc-discord">Ouvrir le Discord</a>
                        <?php else: ?>
                            <p class="edc-step__text">Le lien du Discord n’est pas encore renseigné : l’équipe recrutement vous contactera après le dépôt de votre fiche.</p>
                        <?php endif; ?>
                    </div>
                </li>
                <li class="edc-step">
                    <span class="edc-step__n" aria-hidden="true">2</span>
                    <div class="edc-step__body">
                        <h2 class="edc-step__title">Remplissez votre fiche</h2>
                        <p class="edc-step__text">Quelques questions, ici même. Votre fiche reste consultable depuis votre espace candidat.</p>
                    </div>
                </li>
                <li class="edc-step">
                    <span class="edc-step__n" aria-hidden="true">3</span>
                    <div class="edc-step__body">
                        <h2 class="edc-step__title">L’équipe vous propose un rendez-vous</h2>
                        <p class="edc-step__text">Elle lit votre fiche et cale un échange avec vous sur Discord.</p>
                    </div>
                </li>
            </ol>
            <a class="edc-back" href="<?= $h($edCommunityUrl) ?>">Retour à la page de la communauté</a>
        </div>

        <div class="ed-cp__side">
            <?php if (!$isAuthenticated): ?>
            <section class="cp__card edc-panel" aria-labelledby="ed-gate-title">
                <h2 class="edc-panel__title" id="ed-gate-title">Connectez-vous pour candidater</h2>
                <p class="edc-panel__text">Votre candidature chez <strong><?= $h($tenantName) ?></strong> est rattachée à votre compte : votre nom et votre e-mail sont repris de votre profil. Après la connexion, vous revenez sur cette fiche.</p>
                <?php $discordNext = null; $discordHrefOverride = $discordStepUrl; require base_path('views/partials/auth/discord_button.php'); ?>
                <div class="edc-panel__actions">
                    <a class="cp__btn cp__btn--primary" href="<?= $h($loginStepUrl) ?>">Se connecter</a>
                    <a class="cp__btn cp__btn--ghost" href="<?= $h($registerStepUrl) ?>">Créer un compte</a>
                </div>
            </section>
            <?php else: ?>
            <form method="post" action="<?= $h($formAction) ?>" class="cp__card edc-panel edc-form">
                <h2 class="edc-panel__title">Votre fiche de candidature</h2>
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
                    <div class="edc-field">
                        <?php if ($type === 'closed'): ?>
                            <span class="edc-label" id="<?= $h($fieldId) ?>-label"><?= $h((string) $q['label']) ?><?php if ($required): ?> <span class="edc-req" aria-hidden="true">*</span><?php endif; ?></span>
                            <div class="edc-choices" role="radiogroup" aria-labelledby="<?= $h($fieldId) ?>-label">
                                <label><input type="radio" name="<?= $h($fieldName) ?>" value="Oui" <?= $required ? 'required' : '' ?>> Oui</label>
                                <label><input type="radio" name="<?= $h($fieldName) ?>" value="Non"> Non</label>
                            </div>
                        <?php else: ?>
                            <label class="edc-label" for="<?= $h($fieldId) ?>"><?= $h((string) $q['label']) ?><?php if ($required): ?> <span class="edc-req" aria-hidden="true">*</span><?php endif; ?></label>
                            <?php if ($type === 'select'): ?>
                                <select id="<?= $h($fieldId) ?>" name="<?= $h($fieldName) ?>" <?= $required ? 'required' : '' ?> class="edc-input">
                                    <option value="">Sélectionnez</option>
                                    <?php foreach ((array) ($q['options'] ?? []) as $opt): ?>
                                        <option value="<?= $h((string) $opt) ?>"><?= $h((string) $opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($type === 'open'): ?>
                                <textarea id="<?= $h($fieldId) ?>" name="<?= $h($fieldName) ?>" rows="3" <?= $required ? 'required' : '' ?> class="edc-input"></textarea>
                            <?php else: ?>
                                <input type="text" id="<?= $h($fieldId) ?>" name="<?= $h($fieldName) ?>" maxlength="4000" <?= $required ? 'required' : '' ?> class="edc-input">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="edc-field">
                    <label class="edc-label" for="invite-code">Code d’invitation prioritaire (facultatif)</label>
                    <p class="edc-hint">Si l’équipe vous a transmis un code prioritaire, saisissez-le ici pour accélérer votre candidature.</p>
                    <input type="text" name="invite_code" id="invite-code" maxlength="64" pattern="[A-Z0-9\-_]*" placeholder="Ex : MIGRATION2026" class="edc-input edc-code">
                </div>

                <?php require base_path('views/enlistment/_account_consent.php'); ?>

                <button type="submit" class="cp__btn cp__btn--primary edc-submit">Envoyer ma candidature</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
