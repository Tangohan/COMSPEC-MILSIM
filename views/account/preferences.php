<?php
declare(strict_types=1);

$user = $user ?? [];
$profile = $profile ?? null;
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$uiPrefs = $uiPrefs ?? ['theme' => 'system', 'density' => 'comfortable', 'sidebar_collapsed' => false];
$notifEmailCatalog = $notifEmailCatalog ?? [];
$notifEmailState = $notifEmailState ?? [];
$accountSnapshot = $accountSnapshot ?? ['email_masked' => '—', 'email_verified' => false, 'last_login_label' => null];
$timezoneSuggestions = $timezoneSuggestions ?? [];
$steamWebConfigured = !empty($steamWebConfigured ?? false);
$steamSyncReport = is_array($steamSyncReport ?? null) ? $steamSyncReport : null;
$loginOtpMandatory = !empty($loginOtpMandatory ?? false);
$loginOtpVoluntaryActive = !empty($loginOtpVoluntaryActive ?? false);
$totpEnabled = !empty($totpEnabled ?? false);
$loginOtpTtlMinutes = isset($loginOtpTtlMinutes) ? (int) $loginOtpTtlMinutes : 10;

$notifByGroup = [];
foreach ($notifEmailCatalog as $item) {
    $g = $item['group'] ?? 'Autres';
    $notifByGroup[$g][] = $item;
}

$accountNavKey = 'preferences';
$accountTitle = 'Profil & préférences';
$accountLead = 'Affichage sur le portail, fuseau horaire et notifications — séparés du dossier opérationnel (personnage).';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<nav class="account-hub__subnav" aria-label="Sections des préférences">
    <a href="#section-profil">Profil portail</a>
    <a href="#section-locale">Fuseau &amp; langue</a>
    <a href="#section-interface">Interface</a>
    <a href="#connexion-verification">Double vérification</a>
    <a href="#notifications-email">Notifications</a>
</nav>

<?php if ($steamSyncReport !== null): ?>
<div class="account-hub__panel" style="margin-bottom:1.25rem;border-color:<?= !empty($steamSyncReport['ok']) ? '#a7f3d0' : '#fde68a' ?>" role="region" aria-label="Détail de la synchronisation Steam">
    <div class="account-hub__panel-body">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:.75rem">
            <div>
                <h2 class="account-hub__panel-title" style="font-size:1rem"><?= !empty($steamSyncReport['ok']) ? 'Synchronisation terminée' : 'Synchronisation interrompue' ?></h2>
                <?php if (!empty($steamSyncReport['finished_at'])): ?>
                <p class="account-hub__hint"><?= htmlspecialchars((string) $steamSyncReport['finished_at'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <span class="account-hub__badge <?= !empty($steamSyncReport['ok']) ? 'account-hub__badge--ok' : 'account-hub__badge--warn' ?>">
                <?= !empty($steamSyncReport['ok']) ? 'Réussi' : 'À vérifier' ?>
            </span>
        </div>
        <?php $steps = isset($steamSyncReport['steps']) && is_array($steamSyncReport['steps']) ? $steamSyncReport['steps'] : []; ?>
        <?php if ($steps !== []): ?>
        <ol style="margin:1rem 0 0;padding:0;list-style:none;display:grid;gap:.65rem">
            <?php foreach ($steps as $idx => $st):
                $ok = !empty($st['ok']);
                ?>
            <li class="account-hub__check" style="background:#fff">
                <span class="account-hub__badge <?= $ok ? 'account-hub__badge--ok' : 'account-hub__badge--warn' ?>" aria-hidden="true"><?= (int) $idx + 1 ?></span>
                <div>
                    <p style="margin:0;font-size:.875rem;font-weight:800"><?= htmlspecialchars((string) ($st['label'] ?? 'Étape'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($st['detail'])): ?>
                    <p class="account-hub__hint"><?= htmlspecialchars((string) $st['detail'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
        <?php
        $sd = isset($steamSyncReport['data']) && is_array($steamSyncReport['data']) ? $steamSyncReport['data'] : [];
        $pseudo = isset($sd['public_pseudo']) ? trim((string) $sd['public_pseudo']) : '';
        $sidShow = isset($sd['steam_id']) ? trim((string) $sd['steam_id']) : '';
        ?>
        <?php if ($pseudo !== '' || $sidShow !== '' || !empty($sd['avatar_updated']) || !empty($sd['display_name_updated'])): ?>
        <div style="margin-top:1rem;padding:1rem;border-radius:.75rem;border:1px solid #e2e8f0;background:#f8fafc">
            <p class="account-hub__stat-label">Données lues sur le profil public</p>
            <dl class="account-hub__form-grid account-hub__form-grid--2" style="margin-top:.75rem">
                <?php if ($pseudo !== ''): ?>
                <div><dt class="account-hub__hint" style="margin:0">Pseudo affiché côté Steam</dt><dd style="margin:.2rem 0 0;font-weight:700"><?= htmlspecialchars($pseudo, ENT_QUOTES, 'UTF-8') ?></dd></div>
                <?php endif; ?>
                <?php if ($sidShow !== ''): ?>
                <div><dt class="account-hub__hint" style="margin:0">Identifiant numérique confirmé</dt><dd style="margin:.2rem 0 0;font-family:ui-monospace,monospace;font-weight:700"><?= htmlspecialchars($sidShow, ENT_QUOTES, 'UTF-8') ?></dd></div>
                <?php endif; ?>
                <div><dt class="account-hub__hint" style="margin:0">Photo du compte</dt><dd style="margin:.2rem 0 0"><?= !empty($sd['avatar_updated']) ? 'Mise à jour enregistrée' : 'Inchangée sur cette passe' ?></dd></div>
                <div><dt class="account-hub__hint" style="margin:0">Nom d’affichage</dt><dd style="margin:.2rem 0 0"><?= !empty($sd['display_name_updated']) ? 'Aligné sur le pseudo Steam' : 'Inchangé sur cette passe' ?></dd></div>
            </dl>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<section class="account-hub__panel" style="margin-bottom:1.25rem">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Résumé</p>
        <h2 class="account-hub__panel-title">Compte actuel</h2>
    </div>
    <div class="account-hub__panel-body">
        <div class="account-hub__stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(11rem,1fr))">
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">E-mail</p>
                <p class="account-hub__stat-value" style="font-family:ui-monospace,monospace;font-size:.85rem"><?= htmlspecialchars((string) $accountSnapshot['email_masked'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="account-hub__stat-meta"><a href="<?= htmlspecialchars(url('account/mail'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">Modifier l’adresse</a></p>
            </div>
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">Vérification</p>
                <p class="account-hub__stat-meta" style="margin-top:.45rem">
                    <?php if (!empty($accountSnapshot['email_verified'])): ?>
                    <span class="account-hub__badge account-hub__badge--ok">Adresse confirmée</span>
                    <?php else: ?>
                    <span class="account-hub__badge account-hub__badge--warn">En attente de confirmation</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="account-hub__stat">
                <p class="account-hub__stat-label">Dernière connexion</p>
                <p class="account-hub__stat-value" style="font-size:.875rem"><?= $accountSnapshot['last_login_label'] !== null ? htmlspecialchars((string) $accountSnapshot['last_login_label'], ENT_QUOTES, 'UTF-8') : 'Non enregistrée' ?></p>
            </div>
        </div>
    </div>
</section>

<section id="connexion-verification" class="account-hub__panel account-hub__section-anchor" style="margin-bottom:1.25rem" aria-labelledby="login-otp-title">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Connexion</p>
        <h2 id="login-otp-title" class="account-hub__panel-title">Double vérification</h2>
        <p class="account-hub__panel-desc">Après le mot de passe, un second code peut être demandé (e-mail ou application d’authentification).</p>
        <p style="margin:.85rem 0 0">
            <?php if (!empty($totpEnabled)): ?>
            <span class="account-hub__badge account-hub__badge--ok">Application activée</span>
            <?php endif; ?>
            <?php if ($loginOtpMandatory): ?>
            <span class="account-hub__badge account-hub__badge--ok">Imposée pour votre rôle</span>
            <?php elseif ($loginOtpVoluntaryActive): ?>
            <span class="account-hub__badge account-hub__badge--ok">Code e-mail activé</span>
            <?php elseif (empty($totpEnabled)): ?>
            <span class="account-hub__badge account-hub__badge--off">Non activée (mot de passe seul)</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="account-hub__panel-body">
        <?php if (!empty($totpEnabled)): ?>
        <p style="margin:0;font-size:.875rem;line-height:1.55;color:#334155">
            L’application d’authentification est active : un code de l’application sera demandé en priorité à la connexion.
            Gérez les méthodes sur <a href="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">Double vérification</a>.
        </p>
        <?php elseif ($loginOtpMandatory): ?>
        <p style="margin:0;font-size:.875rem;line-height:1.55;color:#334155">
            Compte tenu de vos responsabilités, le portail envoie un code après le mot de passe. Validité d’environ <strong><?= (int) $loginOtpTtlMinutes ?> minute<?= (int) $loginOtpTtlMinutes > 1 ? 's' : '' ?></strong>. Pensez aux courriers indésirables si rien n’arrive. Vous pouvez aussi activer une application sur <a href="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">Double vérification</a>.
        </p>
        <?php elseif ($loginOtpVoluntaryActive): ?>
        <p style="margin:0;font-size:.875rem;line-height:1.55;color:#334155">
            Vous avez ajouté le code par e-mail. Modifiez les méthodes sur <a href="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">Double vérification</a>. Validité d’environ <strong><?= (int) $loginOtpTtlMinutes ?> minute<?= (int) $loginOtpTtlMinutes > 1 ? 's' : '' ?></strong>.
        </p>
        <?php else: ?>
        <p style="margin:0;font-size:.875rem;line-height:1.55;color:#334155">
            Activez la double vérification sur <a href="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">Double vérification</a>, ou demandez un envoi d’essai ci-dessous pour vérifier votre boîte de réception.
        </p>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(url('account/preferences/login-otp-mailbox-test'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:1.15rem;display:flex;flex-wrap:wrap;align-items:center;gap:.85rem">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="account-hub__btn account-hub__btn--primary">Envoyer un code d’essai</button>
            <p class="account-hub__hint" style="margin:0;max-width:18rem">Au plus un envoi par minute, pour limiter les envois répétés.</p>
        </form>
    </div>
</section>

<form method="post" action="<?= htmlspecialchars(url('account/preferences'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__stack">
    <?= \App\Core\Csrf::field() ?>

    <section id="section-profil" class="account-hub__panel account-hub__section-anchor">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Profil</p>
            <h2 class="account-hub__panel-title">Profil portail &amp; liaisons</h2>
            <p class="account-hub__panel-desc">Nom affiché, prénom et nom du personnage, indicatif et liens utiles.</p>
        </div>
        <div class="account-hub__panel-body">
            <div class="account-hub__form-grid">
                <div>
                    <label class="account-hub__label" for="display_name">Nom d’affichage</label>
                    <input type="text" name="display_name" id="display_name" value="<?= htmlspecialchars((string) ($user['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="100">
                    <?php if (!empty($errors['display_name'])): foreach ($errors['display_name'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="callsign">Indicatif</label>
                    <input type="text" name="callsign" id="callsign" value="<?= htmlspecialchars((string) ($user['callsign'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="50">
                    <p class="account-hub__hint">Utilisé sur le portail et pour les outils cartographiques.</p>
                    <?php if (!empty($errors['callsign'])): foreach ($errors['callsign'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="first_name">Prénom (personnage)</label>
                    <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars((string) ($profile['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="100" autocomplete="off">
                    <p class="account-hub__hint">Prénom du personnage, tel qu’il figure sur le dossier.</p>
                    <?php if (!empty($errors['first_name'])): foreach ($errors['first_name'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="last_name">Nom (personnage)</label>
                    <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars((string) ($profile['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="100" autocomplete="off">
                    <p class="account-hub__hint">Nom de famille du personnage. N’efface pas l’indicatif en mission.</p>
                    <?php if (!empty($errors['last_name'])): foreach ($errors['last_name'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="profile_slug">Adresse courte de votre fiche (optionnel)</label>
                    <input type="text" name="profile_slug" id="profile_slug" value="<?= htmlspecialchars((string) ($user['profile_slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="40" placeholder="ex. jean-dupont" autocomplete="username" style="font-family:ui-monospace,monospace;text-transform:lowercase">
                    <p class="account-hub__hint">Lettres minuscules, chiffres et tirets uniquement. Laissez vide pour l’adresse par défaut.</p>
                    <?php if (!empty($errors['profile_slug'])): foreach ($errors['profile_slug'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>

                <div style="padding:1rem;border-radius:.85rem;border:1px solid #e2e8f0;background:#f8fafc">
                    <label class="account-hub__label" for="steam_id">Liaison Steam (jeu et cartographie)</label>
                    <input type="text" name="steam_id" id="steam_id" value="<?= htmlspecialchars((string) ($user['steam_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Numéro en jeu, STEAM_0:…, ou adresse de profil" maxlength="512" autocomplete="off">
                    <p class="account-hub__hint">Collez le numéro affiché dans le jeu, un identifiant Steam classique, ou une adresse de profil public<?php if ($steamWebConfigured): ?> (y compris un lien avec votre pseudo)<?php endif; ?>.</p>
                    <?php if (!empty($errors['steam_id'])): foreach ($errors['steam_id'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>

                    <?php if ($steamWebConfigured): ?>
                    <div style="margin-top:1rem;padding:1rem;border-radius:.75rem;border:1px solid #e2e8f0;background:#fff">
                        <p class="account-hub__stat-label">Synchronisation du profil public</p>
                        <p class="account-hub__hint">Met à jour la photo (et éventuellement le nom d’affichage). Les autres champs de cette page ne sont enregistrés qu’avec « Enregistrer tout ».</p>
                        <label class="account-hub__check" style="margin-top:.85rem;cursor:pointer">
                            <input type="checkbox" name="apply_steam_display_name" value="1">
                            <span style="font-size:.8125rem;line-height:1.45"><strong>Aligner le nom d’affichage</strong> sur le pseudo public Steam.</span>
                        </label>
                        <button type="submit" formaction="<?= htmlspecialchars(url('account/steam-sync'), ENT_QUOTES, 'UTF-8') ?>" formmethod="post" formnovalidate class="account-hub__btn account-hub__btn--ink" style="margin-top:.85rem">
                            Synchroniser photo &amp; profil Steam
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="account-hub__hint">La lecture automatique du profil public n’est pas activée sur ce serveur : vous pouvez tout de même enregistrer le numéro ou une adresse de profil.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="section-locale" class="account-hub__panel account-hub__section-anchor">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Locale</p>
            <h2 class="account-hub__panel-title">Fuseau horaire &amp; langue</h2>
        </div>
        <div class="account-hub__panel-body">
            <div class="account-hub__form-grid account-hub__form-grid--2">
                <div>
                    <label class="account-hub__label" for="timezone">Fuseau horaire</label>
                    <input type="text" name="timezone" id="timezone" value="<?= htmlspecialchars((string) ($profile['timezone'] ?? 'Europe/Paris'), ENT_QUOTES, 'UTF-8') ?>" placeholder="Europe/Paris" list="tz-suggestions" autocomplete="off" maxlength="50">
                    <datalist id="tz-suggestions">
                        <?php foreach ($timezoneSuggestions as $tz): ?>
                        <option value="<?= htmlspecialchars((string) $tz, ENT_QUOTES, 'UTF-8') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <p class="account-hub__hint">Choisissez dans la liste proposée, ou saisissez un fuseau standard (ex. Europe/Paris).</p>
                </div>
                <div>
                    <label class="account-hub__label" for="language"><?= htmlspecialchars(__('common.language'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="language" id="language">
                        <option value="fr" <?= ($profile['language'] ?? '') === 'fr' ? 'selected' : '' ?>><?= htmlspecialchars(__('common.language_fr'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="en" <?= ($profile['language'] ?? '') === 'en' ? 'selected' : '' ?>><?= htmlspecialchars(__('common.language_en'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section id="section-interface" class="account-hub__panel account-hub__section-anchor">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Affichage</p>
            <h2 class="account-hub__panel-title">Interface</h2>
            <p class="account-hub__panel-desc">Thème et densité enregistrés pour votre compte sur tout le portail.</p>
        </div>
        <div class="account-hub__panel-body">
            <div class="account-hub__form-grid account-hub__form-grid--2">
                <div>
                    <label class="account-hub__label" for="ui_theme">Thème</label>
                    <select name="ui_theme" id="ui_theme">
                        <option value="system" <?= ($uiPrefs['theme'] ?? '') === 'system' ? 'selected' : '' ?>>Système (automatique)</option>
                        <option value="light" <?= ($uiPrefs['theme'] ?? '') === 'light' ? 'selected' : '' ?>>Clair</option>
                        <option value="dark" <?= ($uiPrefs['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Sombre</option>
                        <option value="tenant" <?= ($uiPrefs['theme'] ?? '') === 'tenant' ? 'selected' : '' ?>>Communauté (marque)</option>
                    </select>
                </div>
                <div>
                    <label class="account-hub__label" for="ui_density">Densité des listes</label>
                    <select name="ui_density" id="ui_density">
                        <option value="comfortable" <?= ($uiPrefs['density'] ?? '') === 'comfortable' ? 'selected' : '' ?>>Confortable</option>
                        <option value="compact" <?= ($uiPrefs['density'] ?? '') === 'compact' ? 'selected' : '' ?>>Compact</option>
                    </select>
                </div>
            </div>
            <label class="account-hub__check" style="margin-top:1rem;cursor:pointer">
                <input type="checkbox" name="ui_sidebar_collapsed" id="ui_sidebar_collapsed" value="1" <?= !empty($uiPrefs['sidebar_collapsed']) ? 'checked' : '' ?>>
                <span>
                    <strong style="font-size:.875rem">Barre latérale repliée par défaut</strong>
                    <span class="account-hub__hint" style="display:block">Utile sur petit écran ou pour se concentrer sur le contenu.</span>
                </span>
            </label>
        </div>
    </section>

    <section id="notifications-email" class="account-hub__panel account-hub__section-anchor">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Courriels</p>
            <h2 class="account-hub__panel-title">Notifications par e-mail</h2>
            <p class="account-hub__panel-desc">
                Décochez les messages que vous ne souhaitez plus recevoir. Les e-mails indispensables (réinitialisation de mot de passe, vérification d’adresse, liens à usage unique) peuvent toujours être envoyés.
            </p>
        </div>
        <div class="account-hub__panel-body">
            <?php if ($notifByGroup === []): ?>
            <div class="account-hub__empty">
                <p class="account-hub__empty-title">Aucune préférence disponible</p>
                <p class="account-hub__empty-desc">Les types de messages seront proposés ici dès qu’ils seront activés pour votre communauté.</p>
            </div>
            <?php else: ?>
            <div style="display:grid;gap:.75rem;margin-bottom:1rem">
                <div style="display:grid;gap:.65rem">
                    <label class="account-hub__label" for="notif-search">Filtrer les notifications</label>
                    <input type="search" id="notif-search" placeholder="Ex. sécurité, formation, recrutement…">
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center">
                    <span id="notif-stats" class="account-hub__badge account-hub__badge--off">0 / 0 actives</span>
                    <button type="button" id="notif-enable-all" class="account-hub__btn account-hub__btn--soft" style="padding:.45rem .7rem;font-size:.6875rem">Tout activer</button>
                    <button type="button" id="notif-disable-all" class="account-hub__btn" style="padding:.45rem .7rem;font-size:.6875rem;background:#fffbeb;color:#92400e;border:1px solid #fde68a">Tout désactiver</button>
                    <button type="button" id="notif-reset-filter" class="account-hub__btn" style="padding:.45rem .7rem;font-size:.6875rem;background:#fff;color:#475569;border:1px solid #e2e8f0">Réinitialiser le filtre</button>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem">
                    <button type="button" data-notif-preset="minimum" class="account-hub__btn" style="padding:.45rem .7rem;font-size:.6875rem;background:#fff;color:#334155;border:1px solid #e2e8f0">Essentiel (sécurité)</button>
                    <button type="button" data-notif-preset="standard" class="account-hub__btn" style="padding:.45rem .7rem;font-size:.6875rem;background:#fff;color:#334155;border:1px solid #e2e8f0">Usage courant</button>
                    <button type="button" data-notif-preset="ops" class="account-hub__btn" style="padding:.45rem .7rem;font-size:.6875rem;background:#fff;color:#334155;border:1px solid #e2e8f0">Tout activer (complet)</button>
                </div>
            </div>

            <div style="display:grid;gap:1.5rem">
                <?php foreach ($notifByGroup as $groupName => $items): ?>
                <div data-notif-group="<?= htmlspecialchars(strtolower((string) $groupName), ENT_QUOTES, 'UTF-8') ?>">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;border-bottom:1px solid #f1f5f9;padding-bottom:.5rem">
                        <h3 class="account-hub__stat-label" style="margin:0"><?= htmlspecialchars((string) $groupName, ENT_QUOTES, 'UTF-8') ?></h3>
                        <div style="display:flex;gap:.35rem">
                            <button type="button" data-group-toggle="1" class="account-hub__btn" style="padding:.3rem .55rem;font-size:.625rem;background:#fff;color:#475569;border:1px solid #e2e8f0">Activer le groupe</button>
                            <button type="button" data-group-toggle="0" class="account-hub__btn" style="padding:.3rem .55rem;font-size:.625rem;background:#fff;color:#475569;border:1px solid #e2e8f0">Désactiver le groupe</button>
                        </div>
                    </div>
                    <ul style="list-style:none;margin:.75rem 0 0;padding:0;display:grid;gap:.55rem">
                        <?php foreach ($items as $item): ?>
                        <?php
                            $key = $item['key'];
                            $checked = !empty($notifEmailState[$key]);
                            $searchBlob = strtolower(($item['label'] ?? '') . ' ' . ($item['hint'] ?? '') . ' ' . $groupName);
                            $idSafe = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $key);
                        ?>
                        <li class="account-hub__check" data-notif-item="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="checkbox" class="notif-email-toggle" name="notif_email[<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>]" id="notif_<?= htmlspecialchars((string) $idSafe, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= $checked ? 'checked' : '' ?>>
                            <div>
                                <label for="notif_<?= htmlspecialchars((string) $idSafe, ENT_QUOTES, 'UTF-8') ?>" style="font-size:.875rem;font-weight:700;color:#0f172a;cursor:pointer"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></label>
                                <p class="account-hub__hint"><?= htmlspecialchars((string) $item['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="account-hub__sticky-bar">
        <button type="submit" class="account-hub__btn account-hub__btn--ink">Enregistrer tout</button>
        <a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn" style="background:#fff;color:#475569;border:1px solid #e2e8f0">Retour à la vue d’ensemble</a>
        <p class="account-hub__hint" style="margin:0">Les modifications de cette page sont enregistrées ensemble.</p>
    </div>
</form>

<script>
(function () {
    var search = document.getElementById('notif-search');
    var enableAll = document.getElementById('notif-enable-all');
    var disableAll = document.getElementById('notif-disable-all');
    var resetFilter = document.getElementById('notif-reset-filter');
    var stats = document.getElementById('notif-stats');
    var visibleItems = function () {
        return Array.prototype.slice.call(document.querySelectorAll('[data-notif-item]')).filter(function (row) {
            return !row.classList.contains('hidden');
        });
    };
    var updateStats = function () {
        if (!stats) {
            return;
        }
        var boxes = Array.prototype.slice.call(document.querySelectorAll('.notif-email-toggle'));
        var enabled = boxes.filter(function (b) { return !!b.checked; }).length;
        stats.textContent = enabled + ' / ' + boxes.length + ' actives';
    };
    var applyFilter = function () {
        if (!search) {
            return;
        }
        var q = (search.value || '').toLowerCase().trim();
        var groups = Array.prototype.slice.call(document.querySelectorAll('[data-notif-group]'));
        groups.forEach(function (group) {
            var rows = Array.prototype.slice.call(group.querySelectorAll('[data-notif-item]'));
            var shown = 0;
            rows.forEach(function (row) {
                var blob = row.getAttribute('data-notif-item') || '';
                var ok = q === '' || blob.indexOf(q) !== -1;
                row.classList.toggle('hidden', !ok);
                if (ok) {
                    shown++;
                }
            });
            group.classList.toggle('hidden', shown === 0);
        });
        updateStats();
    };
    if (search) {
        search.addEventListener('input', applyFilter);
    }
    if (enableAll) {
        enableAll.addEventListener('click', function () {
            visibleItems().forEach(function (row) {
                var box = row.querySelector('.notif-email-toggle');
                if (box) {
                    box.checked = true;
                }
            });
            updateStats();
        });
    }
    if (disableAll) {
        disableAll.addEventListener('click', function () {
            visibleItems().forEach(function (row) {
                var box = row.querySelector('.notif-email-toggle');
                if (box) {
                    box.checked = false;
                }
            });
            updateStats();
        });
    }
    if (resetFilter) {
        resetFilter.addEventListener('click', function () {
            if (search) {
                search.value = '';
            }
            applyFilter();
        });
    }
    Array.prototype.slice.call(document.querySelectorAll('[data-notif-group]')).forEach(function (group) {
        Array.prototype.slice.call(group.querySelectorAll('[data-group-toggle]')).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var on = btn.getAttribute('data-group-toggle') === '1';
                Array.prototype.slice.call(group.querySelectorAll('.notif-email-toggle')).forEach(function (box) {
                    box.checked = on;
                });
                updateStats();
            });
        });
    });
    Array.prototype.slice.call(document.querySelectorAll('.notif-email-toggle')).forEach(function (box) {
        box.addEventListener('change', updateStats);
    });
    Array.prototype.slice.call(document.querySelectorAll('[data-notif-preset]')).forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.getAttribute('data-notif-preset');
            Array.prototype.slice.call(document.querySelectorAll('.notif-email-toggle')).forEach(function (box) {
                var id = box.id || '';
                if (mode === 'minimum') {
                    box.checked = id.indexOf('new_device_login') !== -1 || id.indexOf('multiple_login_attempts') !== -1;
                    return;
                }
                if (mode === 'standard') {
                    box.checked = id.indexOf('community_report_new_staff') === -1 && id.indexOf('new_community_member') === -1;
                    return;
                }
                if (mode === 'ops') {
                    box.checked = true;
                }
            });
            updateStats();
        });
    });
    updateStats();
})();
</script>

<?php require base_path('views/partials/account/shell_close.php'); ?>
