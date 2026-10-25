<?php
declare(strict_types=1);

/**
 * Intégrations de la plateforme (administration du site, administrateur de la plateforme seulement). Contrôleur :
 * SystemPlatformIntegrationsController. Salons Discord de l'équipe Athena, rôles liés de Discord (comptes du site),
 * envoi commun des e-mails : tout en base, plus rien dans le .env. Mêmes styles et comportements que les intégrations
 * des communautés (back-office-integrations.css / .js).
 *
 * @var array<string, mixed> $platform_mail_sender
 * @var array<string, array{label:string, host:string, port:int, encryption:string, username:string}> $mail_providers
 * @var string $platform_env_mailer
 * @var array<string, array{url:string, from_env:bool}> $platform_discord
 * @var array{client_id:string, has_secret:bool, verification_url:string, redirect_uri:string, linked:int} $platform_linked_roles
 * @var string $admin_email
 */

$platformMail = is_array($platform_mail_sender ?? null) ? $platform_mail_sender : [];
$mailProviders = is_array($mail_providers ?? null) ? $mail_providers : [];
$platformEnvMailer = (string) ($platform_env_mailer ?? '');
$platformDiscord = is_array($platform_discord ?? null) ? $platform_discord : [];
$lr = is_array($platform_linked_roles ?? null) ? $platform_linked_roles : null;
$adminEmail = (string) ($admin_email ?? '');

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$icon = static function (string $name): string {
    $paths = match ($name) {
        'discord' => '<path d="M8 17c-2.5-.4-4-1.3-5-2.3.2-4 1.5-7.4 3.4-9.5A15 15 0 0110 4l.6 1.3a12 12 0 012.8 0L14 4a15 15 0 013.6 1.2c1.9 2.1 3.2 5.5 3.4 9.5-1 1-2.5 1.9-5 2.3l-1-1.8"/><path d="M8 15.5c2.6 1 5.4 1 8 0"/><circle cx="9" cy="11.5" r="1.2"/><circle cx="15" cy="11.5" r="1.2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'platform' => '<path d="M12 3l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V7l8-4z"/><path d="M9 12l2 2 4-4"/>',
        'warn' => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.01"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.01"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="bo-int-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};

$platformMailReady = !empty($platformMail['ready']);
$platformEnvActive = $platformEnvMailer !== '' && $platformEnvMailer !== 'file';

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<div class="bo-int" data-bo-int>
<?php if ($flashError): ?>
<p class="bo-int-flash bo-int-flash--err" role="alert"><?= $icon('warn') ?><span><?= $h((string) $flashError) ?></span></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="bo-int-flash bo-int-flash--ok" role="status"><?= $icon('check') ?><span><?= $h((string) $flashSuccess) ?></span></p>
<?php endif; ?>
    <section id="plateforme" class="bo-int-section bo-int-section--platform" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon bo-int-section__icon--platform"><?= $icon('platform') ?></span>
            <div>
                <h2 class="bo-int-section__title">Intégrations de la plateforme <span class="bo-int-badge bo-int-badge--platform">Administrateur de la plateforme</span></h2>
                <p class="bo-int-section__desc">Réglages communs à toutes les communautés, enregistrés en base : plus aucune clé Discord ni compte e-mail à mettre dans le .env du serveur. Les communautés ne voient pas cette page : elles règlent les leurs dans leur back-office (Intégrations externes).</p>
            </div>
        </header>

        <div class="bo-int-card" id="salons-discord">
            <h3 class="bo-int-card__title"><?= $icon('discord') ?> Salons Discord de l’équipe Athena</h3>
            <p class="bo-int-card__desc">Pour l’équipe de développement, pas pour les communautés : chaque communauté choisit en plus son propre salon pour « Signalements du mod » et « Check de démarrage du mod » dans sa section Discord.</p>
            <form method="post" action="<?= $h(url('admin/system/integrations/discord')) ?>" class="bo-int-platform-discord">
                <?= \App\Core\Csrf::field() ?>
                <?php
                $platformChannels = [
                    'mod_reports' => [
                        'field' => 'mod_reports_webhook',
                        'label' => 'Signalements du mod (tickets)',
                        'help' => 'Chaque nouveau signalement ouvre un ticket (un fil par problème) et suit son statut. Salon forum de préférence ; un salon texte marche aussi, sans fil.',
                        'env' => 'DISCORD_MOD_REPORTS_WEBHOOK',
                    ],
                    'startup_check' => [
                        'field' => 'startup_check_webhook',
                        'label' => 'Checks de démarrage (copie)',
                        'help' => 'Copie du check de démarrage de tous les joueurs, communautés confondues, y compris les joueurs pas encore liés.',
                        'env' => 'DISCORD_DEBUG_WEBHOOK',
                    ],
                ];
                ?>
                <?php foreach ($platformChannels as $channel => $meta): ?>
                    <?php $row = is_array($platformDiscord[$channel] ?? null) ? $platformDiscord[$channel] : ['url' => '', 'from_env' => false]; ?>
                    <div class="bo-int-pchan">
                        <label class="bo-int-field">
                            <span class="bo-int-field__label"><?= $h($meta['label']) ?></span>
                            <input type="text" inputmode="url" autocomplete="off" spellcheck="false" name="<?= $h($meta['field']) ?>" maxlength="500" class="bo-int-input bo-int-mono" value="<?= $h((string) ($row['url'] ?? '')) ?>" placeholder="https://discord.com/api/webhooks/…" data-webhook-input>
                            <span class="bo-int-check-msg" data-webhook-msg aria-live="polite"></span>
                            <span class="bo-int-field__help"><?= $h($meta['help']) ?></span>
                        </label>
                        <?php if (!empty($row['from_env'])): ?>
                            <p class="bo-int-envnote"><?= $icon('warn') ?><span>Actuellement lu dans l’ancien réglage <code><?= $h($meta['env']) ?></code> du .env. Collez le lien ici et enregistrez, puis retirez la ligne du .env.</span></p>
                        <?php endif; ?>
                        <button type="submit" form="form-platform-discord-test" name="channel" value="<?= $h($channel) ?>" class="bo-int-btn bo-int-btn--sm"><?= $icon('send') ?><span>Essai</span></button>
                    </div>
                <?php endforeach; ?>
                <div class="bo-int-actions">
                    <button type="submit" class="bo-int-btn bo-int-btn--solid">Enregistrer les salons de la plateforme</button>
                </div>
            </form>
            <form method="post" action="<?= $h(url('admin/system/integrations/discord/essai')) ?>" id="form-platform-discord-test" hidden>
                <?= \App\Core\Csrf::field() ?>
            </form>
        </div>

                <?php if ($lr !== null): ?>
        <div class="bo-int-card" id="roles-lies">
            <h3 class="bo-int-card__title"><?= $icon('discord') ?> Rôles liés de Discord (comptes du site)</h3>
            <p class="bo-int-card__desc">Les membres relient leur compte Discord à leur compte Athena (menu « Rôles liés » du serveur Discord, ou Mon compte › Préférences). Le serveur Discord donne alors lui-même les rôles réservés aux comptes Athena et le profil Discord affiche leurs communautés. Le jeton du bot reste sur le PC : il ne sert qu’à déclarer les conditions, avec le script des rôles.</p>
            <div class="bo-int-status bo-int-status--<?= $lr['client_id'] !== '' && $lr['has_secret'] ? 'ok' : 'off' ?>">
                <?php if ($lr['client_id'] !== '' && $lr['has_secret']): ?>
                    <?= $icon('check') ?><span><strong>Actif</strong> : <?= (int) $lr['linked'] ?> compte(s) Discord relié(s).</span>
                <?php else: ?>
                    <?= $icon('info') ?><span><strong>Pas encore réglé</strong> : collez l’identifiant et le secret de l’application Discord du bot.</span>
                <?php endif; ?>
            </div>
            <p class="bo-int-card__desc">Dans le portail des développeurs Discord (application du bot) : <strong>General Information › Linked Roles Verification URL</strong> = <code><?= $h($lr['verification_url']) ?></code> ; <strong>OAuth2 › Redirects</strong> = <code><?= $h($lr['redirect_uri']) ?></code>.</p>
            <form method="post" action="<?= $h(url('admin/system/integrations/roles-lies')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Identifiant de l’application (Application ID)</span>
                    <input type="text" inputmode="numeric" autocomplete="off" spellcheck="false" name="linked_roles_client_id" maxlength="20" class="bo-int-input bo-int-mono" value="<?= $h($lr['client_id']) ?>" placeholder="123456789012345678">
                </label>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Secret client (OAuth2 › Client Secret)</span>
                    <input type="password" autocomplete="new-password" spellcheck="false" name="linked_roles_client_secret" maxlength="64" class="bo-int-input bo-int-mono" placeholder="<?= $lr['has_secret'] ? '•••••••• (enregistré, laisser vide pour le garder)' : 'Coller le secret' ?>">
                    <span class="bo-int-field__help">Chiffré en base, jamais réaffiché.</span>
                </label>
                <?php if ($lr['has_secret']): ?>
                <label class="bo-int-check"><input type="checkbox" name="linked_roles_clear_secret" value="1"><span>Effacer le secret enregistré</span></label>
                <?php endif; ?>
                <div class="bo-int-actions">
                    <button type="submit" class="bo-int-btn bo-int-btn--solid">Enregistrer les rôles liés</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="bo-int-card" id="emails">
            <h3 class="bo-int-card__title"><?= $icon('mail') ?> Envoi commun de la plateforme</h3>
            <p class="bo-int-card__desc">Sert aux e-mails sans communauté (inscription, mot de passe oublié, alertes) et aux communautés qui n’ont pas leur propre compte. Il compte dans le quota de la plateforme (page Envois d’e-mails).</p>
            <div class="bo-int-status bo-int-status--<?= $platformMailReady ? 'ok' : ($platformEnvActive ? 'warn' : 'off') ?>">
                <?php if ($platformMailReady): ?>
                    <?= $icon('check') ?><span><strong>Actif, réglé ici</strong> : les e-mails communs partent de <?= $h((string) ($platformMail['from_address'] ?? '')) ?>. Les lignes MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD… du .env peuvent être retirées.</span>
                <?php elseif ($platformEnvActive): ?>
                    <?= $icon('warn') ?><span><strong>Ancien réglage .env</strong> (MAIL_MAILER=<?= $h($platformEnvMailer) ?>) : il sert tant qu’aucun compte n’est actif ici. Saisissez-le ci-dessous puis retirez-le du .env.</span>
                <?php else: ?>
                    <?= $icon('info') ?><span><strong>Aucun envoi commun</strong> : seules les communautés qui ont leur propre compte envoient des e-mails.</span>
                <?php endif; ?>
            </div>
            <?php
            $mailFormState = $platformMail;
            $mailFormProviders = $mailProviders;
            $mailFormAction = url('admin/system/integrations/emails');
            $mailFormTestAction = url('admin/system/integrations/emails/essai');
            $mailFormId = 'mail-platform';
            $mailFormWho = 'la plateforme';
            $mailFormFromPlaceholder = 'no-reply@athena.ttrd.fr';
            $mailFormNamePlaceholder = 'Athena';
            $mailFormTestTo = $adminEmail;
            require base_path('views/admin/organization/partials/integrations_mail_form.php');
            ?>
        </div>
    </section>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-integrations.js')) ?>" defer></script>
