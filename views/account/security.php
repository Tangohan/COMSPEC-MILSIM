<?php
declare(strict_types=1);

$user = $user ?? [];
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$hasOtpColumn = !empty($hasOtpColumn ?? false);
$hasTotpColumns = !empty($hasTotpColumns ?? false);
$loginOtpForcedByRole = !empty($loginOtpForcedByRole ?? false);
$emailLoginOtpEnabled = !empty($emailLoginOtpEnabled ?? false);
$totpEnabled = !empty($totpEnabled ?? false);
$pendingTotpSetup = !empty($pendingTotpSetup ?? false);
$totpQrDataUri = $totpQrDataUri ?? null;
$totpSecretDisplay = $totpSecretDisplay ?? null;
$loginOtpTtlMinutes = (int) ($loginOtpTtlMinutes ?? 10);
$recoveryAvailable = !empty($recoveryAvailable ?? false);
$recoveryRemaining = (int) ($recoveryRemaining ?? 0);
$freshRecoveryCodes = is_array($freshRecoveryCodes ?? null) ? $freshRecoveryCodes : [];
$trustedAvailable = !empty($trustedAvailable ?? false);
$trustedDevices = is_array($trustedDevices ?? null) ? $trustedDevices : [];
$trustedTtlDays = (int) ($trustedTtlDays ?? 30);
$recoveryTotal = \App\Services\Auth\RecoveryCodeService::CODE_COUNT;
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmtUtc = static function (?string $v): string {
    $d = $v !== null && $v !== '' ? \App\Support\DbTime::utcToLocal($v) : null;

    return $d !== null ? $d->format('d/m/Y H:i') : '—';
};
// Niveau : 0 mot de passe seul, 1 code e-mail, 2 application, 3 application + codes de secours.
$protectionLevel = 0;
if ($emailLoginOtpEnabled || $loginOtpForcedByRole) {
    $protectionLevel = 1;
}
if ($totpEnabled) {
    $protectionLevel = ($recoveryRemaining > 0 || $freshRecoveryCodes !== []) ? 3 : 2;
}
$protectionLabels = ['Mot de passe seul', 'Bonne : code par e-mail', 'Forte : application d’authentification', 'Renforcée : application + codes de secours'];
$protectionNext = [
    'Activez l’application d’authentification : c’est la protection la plus sûre.',
    'Passez à l’application d’authentification : elle fonctionne même sans réseau et ne dépend pas de votre boîte mail.',
    'Générez vos codes de secours pour ne pas rester bloqué si vous perdez votre téléphone.',
    'Votre compte est protégé au mieux. Gardez vos codes de secours hors de ce téléphone.',
];

$secondFactorActive = $loginOtpForcedByRole || $emailLoginOtpEnabled || $totpEnabled;

$accountNavKey = 'security';
$accountTitle = 'Double vérification';
$accountLead = 'Ajoutez une étape après le mot de passe : code par e-mail ou application d’authentification.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<div class="account-hub__stack">
    <section class="account-hub__panel" aria-labelledby="2fa-overview-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Sécurité du compte</p>
            <h2 id="2fa-overview-heading" class="account-hub__panel-title">État de la double vérification</h2>
            <p class="account-hub__panel-desc">
                Après votre mot de passe, le portail peut demander un second code. Deux méthodes complémentaires sont proposées.
            </p>
            <p style="margin:.85rem 0 0">
                <?php if ($secondFactorActive): ?>
                <span class="account-hub__badge account-hub__badge--ok">Protection active</span>
                <?php else: ?>
                <span class="account-hub__badge account-hub__badge--off">Mot de passe seul</span>
                <?php endif; ?>
                <?php if ($loginOtpForcedByRole): ?>
                <span class="account-hub__badge account-hub__badge--ok">Imposée pour votre rôle</span>
                <?php endif; ?>
            </p>
            <div class="account-hub__level" data-level="<?= (int) $protectionLevel ?>">
                <div class="account-hub__level-bar" aria-hidden="true"><span></span><span></span><span></span></div>
                <p class="account-hub__level-label">Niveau de protection : <strong><?= $h($protectionLabels[$protectionLevel]) ?></strong></p>
                <p class="account-hub__level-next"><?= $h($protectionNext[$protectionLevel]) ?></p>
            </div>
        </div>
        <div class="account-hub__panel-body">
            <div class="account-hub__method-grid">
                <div class="account-hub__method-card<?= $totpEnabled ? ' is-on' : '' ?>">
                    <div class="account-hub__method-card-top">
                        <span class="account-hub__method-icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <p class="account-hub__method-title">Application d’authentification</p>
                            <p class="account-hub__method-meta"><?= $totpEnabled ? 'Activée — recommandée' : 'Non activée' ?></p>
                        </div>
                    </div>
                    <p class="account-hub__method-desc">Code généré sur votre téléphone (Google Authenticator, Microsoft Authenticator, Authy…), même hors ligne.</p>
                    <a href="#authenticator" class="account-hub__btn account-hub__btn--soft" style="align-self:flex-start"><?= $totpEnabled || $pendingTotpSetup ? 'Gérer' : 'Configurer' ?></a>
                </div>
                <div class="account-hub__method-card<?= ($emailLoginOtpEnabled || $loginOtpForcedByRole) ? ' is-on' : '' ?>">
                    <div class="account-hub__method-card-top">
                        <span class="account-hub__method-icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <p class="account-hub__method-title">Code par e-mail</p>
                            <p class="account-hub__method-meta">
                                <?php if ($loginOtpForcedByRole && !$emailLoginOtpEnabled): ?>
                                Appliqué par votre rôle
                                <?php elseif ($emailLoginOtpEnabled): ?>
                                Activé
                                <?php else: ?>
                                Non activé
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <p class="account-hub__method-desc">Un code à six chiffres est envoyé sur votre adresse de connexion (validité d’environ <?= (int) $loginOtpTtlMinutes ?> min).</p>
                    <a href="#email-otp" class="account-hub__btn account-hub__btn--soft" style="align-self:flex-start">Régler</a>
                </div>
                <div class="account-hub__method-card<?= ($totpEnabled && $recoveryRemaining > 0) ? ' is-on' : '' ?>">
                    <div class="account-hub__method-card-top">
                        <span class="account-hub__method-icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        </span>
                        <div>
                            <p class="account-hub__method-title">Codes de secours</p>
                            <p class="account-hub__method-meta">
                                <?php if (!$totpEnabled): ?>
                                Avec l’application
                                <?php elseif ($recoveryRemaining > 0): ?>
                                <?= (int) $recoveryRemaining ?> sur <?= (int) $recoveryTotal ?> disponibles
                                <?php else: ?>
                                À générer
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <p class="account-hub__method-desc">Dix codes à usage unique pour vous connecter si vous perdez votre téléphone.</p>
                    <a href="#codes-secours" class="account-hub__btn account-hub__btn--soft" style="align-self:flex-start"><?= $totpEnabled ? 'Gérer' : 'En savoir plus' ?></a>
                </div>
            </div>
        </div>
    </section>

    <section id="authenticator" class="account-hub__panel account-hub__section-anchor" aria-labelledby="totp-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Méthode 1</p>
            <h2 id="totp-heading" class="account-hub__panel-title">Application d’authentification</h2>
            <p class="account-hub__panel-desc">Scannez un code avec une application dédiée. C’est en général plus rapide et plus sûr que l’e-mail.</p>
        </div>
        <div class="account-hub__panel-body">
            <?php if (!$hasTotpColumns): ?>
            <div class="account-hub__flash account-hub__flash--err" style="background:#fffbeb;border-color:#fde68a;color:#92400e;margin:0">
                Cette option sera disponible après la prochaine mise à jour de la base de données sur ce serveur.
            </div>
            <?php elseif ($totpEnabled && !$pendingTotpSetup): ?>
            <p class="account-hub__text">
                L’application est liée à ce compte. À la connexion, le portail demandera en priorité le code affiché dans l’application.
            </p>
            <form method="post" action="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="security_section" value="totp_disable">
                <div>
                    <label class="account-hub__label" for="totp_disable_code">Code actuel de l’application</label>
                    <input type="text" name="totp_code" id="totp_disable_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required placeholder="000000" style="max-width:12rem;letter-spacing:.2em;font-weight:700">
                    <?php if (!empty($errors['totp_code'])): foreach ($errors['totp_code'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                    <?php if (!empty($errors['totp_enabled'])): foreach ($errors['totp_enabled'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="totp_disable_password">Mot de passe actuel</label>
                    <input type="password" name="confirm_password" id="totp_disable_password" required autocomplete="current-password" style="max-width:24rem">
                    <?php if (!empty($errors['confirm_password'])): foreach ($errors['confirm_password'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--ink">Désactiver l’application</button>
                </div>
            </form>
            <?php elseif ($pendingTotpSetup): ?>
            <ol class="account-hub__setup-steps account-hub__setup-steps--numbered">
                <li><strong>Installez une application</strong> : Google Authenticator, Microsoft Authenticator, Authy, 2FAS ou celle de votre gestionnaire de mots de passe.</li>
                <li><strong>Scannez le code</strong> ci-dessous, ou saisissez la clé à la main.</li>
                <li><strong>Confirmez</strong> avec le code à six chiffres affiché et votre mot de passe. Vos codes de secours s’afficheront ensuite.</li>
            </ol>
            <div class="account-hub__totp-setup">
                <?php if ($totpQrDataUri): ?>
                <div class="account-hub__totp-qr">
                    <img src="<?= htmlspecialchars((string) $totpQrDataUri, ENT_QUOTES, 'UTF-8') ?>" width="200" height="200" alt="Code à scanner avec votre application d’authentification">
                </div>
                <?php endif; ?>
                <div class="account-hub__totp-manual">
                    <p class="account-hub__label" style="margin-bottom:.4rem">Clé à saisir à la main</p>
                    <p class="account-hub__totp-secret" id="totp-secret-display"><?= htmlspecialchars((string) ($totpSecretDisplay ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="account-hub__hint">Conservez cette clé en lieu sûr le temps de la configuration. Elle disparaît une fois l’activation confirmée.</p>
                </div>
            </div>
            <form method="post" action="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid" style="margin-top:1.25rem">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="security_section" value="totp_confirm">
                <div>
                    <label class="account-hub__label" for="totp_confirm_code">Code à six chiffres de l’application</label>
                    <input type="text" name="totp_code" id="totp_confirm_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="000000" style="max-width:12rem;letter-spacing:.2em;font-weight:700">
                    <?php if (!empty($errors['totp_code'])): foreach ($errors['totp_code'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="totp_confirm_password">Mot de passe actuel</label>
                    <input type="password" name="confirm_password" id="totp_confirm_password" required autocomplete="current-password" style="max-width:24rem">
                    <?php if (!empty($errors['confirm_password'])): foreach ($errors['confirm_password'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center">
                    <button type="submit" class="account-hub__btn account-hub__btn--primary">Confirmer l’activation</button>
                </div>
            </form>
            <form method="post" action="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:.75rem">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="security_section" value="totp_cancel">
                <button type="submit" class="account-hub__btn account-hub__btn--ghost">Annuler la configuration</button>
            </form>
            <?php else: ?>
            <p class="account-hub__text">
                Installez une application d’authentification sur votre téléphone, puis démarrez la configuration ici. Vous confirmerez avec un code généré par l’application.
            </p>
            <form method="post" action="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="security_section" value="totp_start">
                <div>
                    <label class="account-hub__label" for="totp_start_password">Mot de passe actuel (pour démarrer)</label>
                    <input type="password" name="confirm_password" id="totp_start_password" required autocomplete="current-password" style="max-width:24rem">
                    <?php if (!empty($errors['confirm_password'])): foreach ($errors['confirm_password'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--primary">Commencer la configuration</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <section id="codes-secours" class="account-hub__panel account-hub__section-anchor" aria-labelledby="recovery-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Plan B</p>
            <h2 id="recovery-heading" class="account-hub__panel-title">Codes de secours</h2>
            <p class="account-hub__panel-desc">Téléphone perdu, application effacée ? Chaque code ouvre une connexion à la place du code à six chiffres, une seule fois.</p>
        </div>
        <div class="account-hub__panel-body">
            <?php if (!$recoveryAvailable): ?>
            <p class="account-hub__text account-hub__text--flush">Cette option sera disponible après la prochaine mise à jour de la base de données sur ce serveur.</p>
            <?php elseif ($freshRecoveryCodes !== []): ?>
            <div class="account-hub__codes-box" data-recovery-box>
                <p class="account-hub__codes-warn"><strong>Notez-les maintenant :</strong> ces codes ne seront plus jamais affichés. Rangez-les hors de votre téléphone (gestionnaire de mots de passe, papier).</p>
                <ol class="account-hub__codes" data-recovery-codes>
                    <?php foreach ($freshRecoveryCodes as $code): ?>
                    <li><code><?= $h((string) $code) ?></code></li>
                    <?php endforeach; ?>
                </ol>
                <div class="account-hub__codes-actions">
                    <button type="button" class="account-hub__btn account-hub__btn--primary" data-recovery-copy data-done="Copiés">Copier</button>
                    <button type="button" class="account-hub__btn account-hub__btn--soft" data-recovery-download
                            data-filename="codes-de-secours-athena.txt"
                            data-header="Codes de secours Athena — <?= $h((string) ($user['email'] ?? '')) ?> — <?= $h(date('d/m/Y')) ?>">Télécharger (.txt)</button>
                    <button type="button" class="account-hub__btn account-hub__btn--soft" data-recovery-print>Imprimer</button>
                </div>
            </div>
            <?php elseif (!$totpEnabled): ?>
            <p class="account-hub__text account-hub__text--flush">Les codes de secours sont créés à l’activation de l’application d’authentification.</p>
            <?php else: ?>
            <p class="account-hub__text">
                <?php if ($recoveryRemaining > 0): ?>
                Il vous reste <strong><?= (int) $recoveryRemaining ?> code<?= $recoveryRemaining > 1 ? 's' : '' ?> sur <?= (int) $recoveryTotal ?></strong>. Si vous les avez perdus ou presque tous utilisés, générez une nouvelle série : l’ancienne cessera de fonctionner.
                <?php else: ?>
                <strong>Vous n’avez aucun code de secours.</strong> Générez-en maintenant : sans eux, perdre votre téléphone vous bloquerait hors de votre compte.
                <?php endif; ?>
            </p>
            <div class="account-hub__codes-meter" aria-hidden="true">
                <?php for ($i = 0; $i < $recoveryTotal; $i++): ?><span class="<?= $i < $recoveryRemaining ? 'is-left' : '' ?>"></span><?php endfor; ?>
            </div>
            <form method="post" action="<?= $h(url('account/security')) ?>#codes-secours" class="account-hub__form-grid" style="margin-top:1rem">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="security_section" value="recovery_regenerate">
                <div>
                    <label class="account-hub__label" for="recovery_password">Mot de passe actuel</label>
                    <input type="password" name="confirm_password" id="recovery_password" required autocomplete="current-password" style="max-width:24rem">
                    <?php if (!empty($errors['recovery_password'])): foreach ($errors['recovery_password'] as $e): ?>
                    <p class="account-hub__field-error"><?= $h((string) $e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--<?= $recoveryRemaining > 0 ? 'soft' : 'primary' ?>"><?= $recoveryRemaining > 0 ? 'Générer une nouvelle série' : 'Générer mes codes de secours' ?></button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <section id="appareils-confiance" class="account-hub__panel account-hub__section-anchor" aria-labelledby="trusted-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Appareils</p>
            <h2 id="trusted-heading" class="account-hub__panel-title">Appareils de confiance</h2>
            <p class="account-hub__panel-desc">Navigateurs où vous avez coché « Ne plus demander de code » : le mot de passe reste demandé, le code non, pendant <?= (int) $trustedTtlDays ?> jours. Changer de mot de passe les retire tous.</p>
        </div>
        <div class="account-hub__panel-body">
            <?php if (!$trustedAvailable): ?>
            <p class="account-hub__text account-hub__text--flush">Pour les administrateurs de la plateforme, le code est demandé à chaque connexion : aucun appareil ne peut être mémorisé.</p>
            <?php elseif ($trustedDevices === []): ?>
            <p class="account-hub__text account-hub__text--flush">Aucun appareil mémorisé. Au prochain code demandé, cochez « Ne plus demander de code sur cet appareil » sur un ordinateur ou un téléphone personnel.</p>
            <?php else: ?>
            <ul class="account-hub__devices">
                <?php foreach ($trustedDevices as $d): ?>
                <li class="account-hub__device">
                    <span class="account-hub__device-icon" aria-hidden="true">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="12" rx="2"/><path stroke-linecap="round" d="M8 20h8M12 16v4"/></svg>
                    </span>
                    <div class="account-hub__device-main">
                        <p class="account-hub__device-title">
                            <?= $h((string) ($d['label'] ?? 'Navigateur')) ?>
                            <?php if (!empty($d['is_current'])): ?><span class="account-hub__badge account-hub__badge--ok">Cet appareil</span><?php endif; ?>
                        </p>
                        <p class="account-hub__device-meta">
                            Mémorisé le <?= $h($fmtUtc((string) ($d['created_at'] ?? ''))) ?>
                            · dernière connexion <?= $h($fmtUtc((string) ($d['last_used_at'] ?? ''))) ?>
                            · jusqu’au <?= $h($fmtUtc((string) ($d['expires_at'] ?? ''))) ?>
                        </p>
                    </div>
                    <form method="post" action="<?= $h(url('account/security')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="security_section" value="trusted_revoke">
                        <input type="hidden" name="device_id" value="<?= (int) ($d['id'] ?? 0) ?>">
                        <button type="submit" class="account-hub__btn account-hub__btn--soft">Retirer</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= $h(url('account/security')) ?>" style="margin-top:1rem">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="security_section" value="trusted_revoke_all">
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Retirer tous les appareils</button>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <section id="email-otp" class="account-hub__panel account-hub__section-anchor" aria-labelledby="email-otp-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Méthode 2</p>
            <h2 id="email-otp-heading" class="account-hub__panel-title">Code par e-mail</h2>
            <p class="account-hub__panel-desc">Utile en secours, ou si vous préférez recevoir le code dans votre boîte de réception.</p>
        </div>
        <div class="account-hub__panel-body">
            <?php if (!$hasOtpColumn): ?>
            <div class="account-hub__flash account-hub__flash--err" style="background:#fffbeb;border-color:#fde68a;color:#92400e;margin:0">
                Cette option sera disponible après la prochaine mise à jour de la base de données sur ce serveur.
            </div>
            <?php elseif ($loginOtpForcedByRole && !$emailLoginOtpEnabled): ?>
            <p class="account-hub__text account-hub__text--flush">
                Votre rôle applique déjà un code par e-mail lorsque l’application d’authentification n’est pas utilisée. Vous n’avez rien à activer ici.
                <?php if ($totpEnabled): ?>
                Comme l’application est active, c’est elle qui sera demandée en priorité.
                <?php endif; ?>
            </p>
            <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="security_section" value="email_login_otp">
                <label class="account-hub__check" style="cursor:pointer">
                    <input type="checkbox" name="email_login_otp_enabled" value="1" <?= $emailLoginOtpEnabled ? 'checked' : '' ?>>
                    <span style="font-size:.875rem;line-height:1.5">
                        <strong>Demander un code par e-mail</strong> après le mot de passe (sur tous vos appareils).
                    </span>
                </label>
                <?php if (!empty($errors['email_login_otp_enabled'])): foreach ($errors['email_login_otp_enabled'] as $e): ?>
                <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; endif; ?>
                <div>
                    <label class="account-hub__label" for="email_otp_password">Mot de passe actuel (pour confirmer)</label>
                    <input type="password" name="confirm_password" id="email_otp_password" required autocomplete="current-password" style="max-width:24rem">
                    <?php if (!empty($errors['confirm_password'])): foreach ($errors['confirm_password'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--primary">Enregistrer le code par e-mail</button>
                </div>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars(url('account/preferences/login-otp-mailbox-test'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:1.25rem;display:flex;flex-wrap:wrap;align-items:center;gap:.85rem">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--soft">Envoyer un code d’essai</button>
                <p class="account-hub__hint" style="margin:0;max-width:20rem">Vérifie uniquement que votre boîte reçoit bien les messages du portail.</p>
            </form>
        </div>
    </section>
</div>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('account/mail'), ENT_QUOTES, 'UTF-8') ?>">Adresse e-mail</a>
    ·
    <a href="<?= htmlspecialchars(url('account/password'), ENT_QUOTES, 'UTF-8') ?>">Mot de passe</a>
    ·
    <a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>">Vue d’ensemble</a>
</p>

<script defer src="<?= htmlspecialchars(asset_url('assets/js/account_security.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require base_path('views/partials/account/shell_close.php'); ?>
