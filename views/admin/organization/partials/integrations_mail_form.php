<?php
declare(strict_types=1);

/**
 * Formulaire du compte d'envoi des e-mails (Intégrations) : communauté ou envoi commun de la plateforme.
 * Les préréglages des fournisseurs (port, chiffrement, serveur) sont appliqués par back-office-integrations.js.
 *
 * @var array<string, mixed> $mailFormState état TenantMailSender::state()
 * @var array<string, array{label:string, host:string, port:int, encryption:string, username:string}> $mailFormProviders
 * @var string $mailFormAction URL d'enregistrement
 * @var string $mailFormTestAction URL de l'essai
 * @var string $mailFormId préfixe des identifiants
 * @var string $mailFormWho « la communauté » ou « la plateforme »
 * @var string $mailFormFromPlaceholder
 * @var string $mailFormNamePlaceholder
 * @var string $mailFormTestTo adresse qui recevra l'essai
 * @var callable(string): string $h
 */

$st = is_array($mailFormState ?? null) ? $mailFormState : [];
$providers = is_array($mailFormProviders ?? null) ? $mailFormProviders : [];
$provider = (string) ($st['provider'] ?? 'smtp');
$id = (string) ($mailFormId ?? 'mail');
$tips = [
    'smtp' => 'Les réglages SMTP sont dans l’espace e-mail de votre hébergeur (OVH, Hostinger, o2switch…). Identifiant : souvent l’adresse complète. Gmail : mot de passe d’application obligatoire.',
    'brevo' => 'Brevo › Paramètres › SMTP & API : l’identifiant est l’adresse de connexion SMTP affichée, le mot de passe est une clé SMTP (pas votre mot de passe Brevo). 300 e-mails par jour gratuits.',
    'sendgrid' => 'SendGrid › Settings › API Keys : créez une clé avec le droit « Mail Send » et collez-la comme mot de passe. L’identifiant « apikey » est mis tout seul.',
    'mailgun' => 'Mailgun › Sending › Domain settings › SMTP credentials : identifiant du type postmaster@votre-domaine, mot de passe SMTP du domaine.',
    'ses' => 'Amazon SES › SMTP settings › Create SMTP credentials (région Irlande, eu-west-1). Le domaine d’expéditeur doit être vérifié dans SES.',
];
$tipsJson = (string) json_encode($tips, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$presetsJson = (string) json_encode($providers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<form method="post" action="<?= $h((string) $mailFormAction) ?>" class="bo-int-mail" autocomplete="off" data-mail-form data-presets="<?= $h($presetsJson) ?>" data-tips="<?= $h($tipsJson) ?>">
    <?= \App\Core\Csrf::field() ?>
    <label class="bo-int-switch">
        <input type="hidden" name="enabled" value="0">
        <input type="checkbox" name="enabled" value="1" class="bo-int-switch__input" <?= !empty($st['enabled']) ? 'checked' : '' ?>>
        <span class="bo-int-switch__track" aria-hidden="true"><span class="bo-int-switch__thumb"></span></span>
        <span class="bo-int-switch__text">
            <strong>Envoyer les e-mails de <?= $h((string) ($mailFormWho ?? 'la communauté')) ?> avec ce compte</strong>
            <small>Coupé : le réglage reste enregistré mais ne sert pas.</small>
        </span>
    </label>

    <fieldset class="bo-int-fieldset">
        <legend class="bo-int-fieldset__legend"><span class="bo-int-step">1</span> Expéditeur</legend>
        <div class="bo-int-grid">
            <label class="bo-int-field">
                <span class="bo-int-field__label">Adresse d’expéditeur</span>
                <input type="email" name="from_address" maxlength="190" class="bo-int-input" value="<?= $h((string) ($st['from_address'] ?? '')) ?>" placeholder="<?= $h((string) ($mailFormFromPlaceholder ?? '')) ?>">
                <span class="bo-int-field__help">Une adresse de votre domaine : c’est elle que voient les destinataires.</span>
            </label>
            <label class="bo-int-field">
                <span class="bo-int-field__label">Nom affiché</span>
                <input type="text" name="from_name" maxlength="80" class="bo-int-input" value="<?= $h((string) ($st['from_name'] ?? '')) ?>" placeholder="<?= $h((string) ($mailFormNamePlaceholder ?? '')) ?>">
            </label>
            <label class="bo-int-field">
                <span class="bo-int-field__label">Adresse de réponse <em>facultatif</em></span>
                <input type="email" name="reply_to" maxlength="190" class="bo-int-input" value="<?= $h((string) ($st['reply_to'] ?? '')) ?>" placeholder="contact@votre-domaine.fr">
                <span class="bo-int-field__help">Là où arrivent les réponses des destinataires.</span>
            </label>
        </div>
    </fieldset>

    <fieldset class="bo-int-fieldset">
        <legend class="bo-int-fieldset__legend"><span class="bo-int-step">2</span> Serveur d’envoi</legend>
        <div class="bo-int-grid">
            <label class="bo-int-field">
                <span class="bo-int-field__label">Service d’envoi</span>
                <select name="provider" class="bo-int-input" data-mail-provider>
                    <?php foreach ($providers as $pk => $pv): ?>
                        <option value="<?= $h((string) $pk) ?>" <?= $provider === $pk ? 'selected' : '' ?>><?= $h((string) ($pv['label'] ?? $pk)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="bo-int-field">
                <span class="bo-int-field__label">Serveur SMTP</span>
                <input type="text" name="host" maxlength="190" spellcheck="false" class="bo-int-input bo-int-mono" value="<?= $h((string) ($st['host'] ?? '')) ?>" placeholder="smtp.votre-hebergeur.fr" data-mail-host>
                <span class="bo-int-field__help">Laissez vide pour Brevo, SendGrid, Mailgun ou Amazon SES : le serveur connu est utilisé.</span>
            </label>
            <label class="bo-int-field">
                <span class="bo-int-field__label">Port</span>
                <select name="port" class="bo-int-input" data-mail-port>
                    <?php foreach ([587 => '587 (recommandé)', 465 => '465', 2525 => '2525', 25 => '25'] as $port => $portLabel): ?>
                        <option value="<?= (int) $port ?>" <?= (int) ($st['port'] ?? 587) === $port ? 'selected' : '' ?>><?= $h($portLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="bo-int-field">
                <span class="bo-int-field__label">Chiffrement</span>
                <select name="encryption" class="bo-int-input" data-mail-encryption>
                    <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'Aucun (déconseillé)'] as $enc => $encLabel): ?>
                        <option value="<?= $h($enc) ?>" <?= (string) ($st['encryption'] ?? 'tls') === $enc ? 'selected' : '' ?>><?= $h($encLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <p class="bo-int-tip" data-mail-tip><?= $h($tips[$provider] ?? $tips['smtp']) ?></p>
    </fieldset>

    <fieldset class="bo-int-fieldset">
        <legend class="bo-int-fieldset__legend"><span class="bo-int-step">3</span> Identifiants</legend>
        <div class="bo-int-grid">
            <label class="bo-int-field">
                <span class="bo-int-field__label">Identifiant</span>
                <input type="text" name="username" maxlength="190" spellcheck="false" autocomplete="off" class="bo-int-input" value="<?= $h((string) ($st['username'] ?? '')) ?>" placeholder="Souvent l’adresse d’expéditeur">
                <span class="bo-int-field__help">SendGrid : laissez vide (« apikey » est mis tout seul).</span>
            </label>
            <div class="bo-int-field">
                <label class="bo-int-field__label" for="<?= $h($id) ?>-password">Mot de passe ou clé SMTP</label>
                <input type="password" id="<?= $h($id) ?>-password" name="password" maxlength="500" autocomplete="new-password" class="bo-int-input" placeholder="<?= !empty($st['has_password']) ? 'Enregistré · laissez vide pour le garder' : '' ?>">
                <span class="bo-int-field__help">Chiffré en base, jamais réaffiché.</span>
                <?php if (!empty($st['has_password'])): ?>
                    <label class="bo-int-check">
                        <input type="checkbox" name="clear_password" value="1">
                        <span>Effacer le mot de passe enregistré</span>
                    </label>
                <?php endif; ?>
            </div>
        </div>
    </fieldset>

    <div class="bo-int-actions">
        <button type="submit" class="bo-int-btn bo-int-btn--solid">Enregistrer le compte d’envoi</button>
        <button type="submit" form="<?= $h($id) ?>-test" class="bo-int-btn" <?= !empty($st['ready']) ? '' : 'disabled' ?>>Envoyer un e-mail d’essai</button>
        <span class="bo-int-actions__note">
            <?php if (!empty($st['ready'])): ?>
                L’essai part tout de suite avec le réglage enregistré<?= (string) ($mailFormTestTo ?? '') !== '' ? ', vers ' . $h((string) $mailFormTestTo) : '' ?>.
            <?php else: ?>
                Enregistrez un compte actif pour pouvoir l’essayer.
            <?php endif; ?>
        </span>
    </div>
</form>
<form method="post" action="<?= $h((string) $mailFormTestAction) ?>" id="<?= $h($id) ?>-test" hidden>
    <?= \App\Core\Csrf::field() ?>
</form>
