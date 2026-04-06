<?php
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

$quickLinks = [
    ['href' => url('account'), 'label' => 'Vue d’ensemble compte', 'sub' => 'Tableau des réglages'],
    ['href' => url('account/mail'), 'label' => 'Adresse e-mail', 'sub' => 'Connexion & notifications'],
    ['href' => url('account/password'), 'label' => 'Mot de passe', 'sub' => 'Secret d’accès'],
    ['href' => url('account/image'), 'label' => 'Photo de compte', 'sub' => 'Avatar'],
    ['href' => url('account/portrait'), 'label' => 'Portrait opérateur', 'sub' => 'ORBAT & briefings'],
    ['href' => url('account/recruitment-presets'), 'label' => 'Profils de candidature', 'sub' => 'Enrôlement'],
];

$notifByGroup = [];
foreach ($notifEmailCatalog as $item) {
    $g = $item['group'] ?? 'Autres';
    $notifByGroup[$g][] = $item;
}
?>
<div class="relative mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
    <div class="mb-8">
        <p class="text-[11px] font-black uppercase tracking-[0.3em] text-emerald-700/90">Compte</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Préférences</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Identité sur la plateforme, confort d’affichage et e-mails utiles — au même endroit que votre indicatif et votre fuseau horaire.
        </p>
    </div>

    <?php if ($success): ?>
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Accès rapide -->
    <section class="mb-8 rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm ring-1 ring-slate-900/[0.04] sm:p-6">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Accès rapide</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($quickLinks as $ql): ?>
            <a href="<?= htmlspecialchars($ql['href']) ?>" class="group flex flex-col rounded-xl border border-slate-200/80 bg-white px-4 py-3 transition hover:border-emerald-300 hover:shadow-md">
                <span class="text-sm font-bold text-slate-900 group-hover:text-emerald-900"><?= htmlspecialchars($ql['label']) ?></span>
                <span class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($ql['sub']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Résumé compte -->
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Résumé du compte</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail (masqué)</dt>
                <dd class="mt-1 font-mono text-sm text-slate-900"><?= htmlspecialchars($accountSnapshot['email_masked']) ?></dd>
                <dd class="mt-2">
                    <a href="<?= url('account/mail') ?>" class="text-xs font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950">Modifier l’adresse</a>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vérification</dt>
                <dd class="mt-1">
                    <?php if (!empty($accountSnapshot['email_verified'])): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-900">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        Adresse confirmée
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900">
                        En attente de confirmation
                    </span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dernière connexion</dt>
                <dd class="mt-1 text-sm text-slate-800"><?= $accountSnapshot['last_login_label'] !== null ? htmlspecialchars($accountSnapshot['last_login_label']) : '— (première session ou non enregistrée)' ?></dd>
            </div>
        </dl>
    </section>

    <form method="post" action="<?= url('account/preferences') ?>" class="space-y-8">
        <?= \App\Core\Csrf::field() ?>

        <!-- Identité -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-black text-slate-900">Identité & contact</h2>
            <p class="mt-1 text-sm text-slate-600">Ces informations alimentent l’affichage sur le portail, le forum et les intégrations (ex. ATAK).</p>
            <div class="mt-6 space-y-4">
                <div>
                    <label for="display_name" class="block text-sm font-medium text-slate-700 mb-1">Nom d'affichage</label>
                    <input type="text" name="display_name" id="display_name" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="100">
                    <?php if (!empty($errors['display_name'])): foreach ($errors['display_name'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label for="callsign" class="block text-sm font-medium text-slate-700 mb-1">Indicatif (plateforme, outil cartographique)</label>
                    <input type="text" name="callsign" id="callsign" value="<?= htmlspecialchars($user['callsign'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="50">
                    <p class="mt-1 text-xs text-slate-500">Même valeur partout sur le portail et pour les intégrations cartographiques.</p>
                    <?php if (!empty($errors['callsign'])): foreach ($errors['callsign'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label for="profile_slug" class="block text-sm font-medium text-slate-700 mb-1">Adresse courte de votre fiche (optionnel)</label>
                    <input type="text" name="profile_slug" id="profile_slug" value="<?= htmlspecialchars($user['profile_slug'] ?? '') ?>" pattern="[a-z0-9]([a-z0-9-]{0,38}[a-z0-9])?" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono lowercase focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="40" placeholder="ex. jean-dupont">
                    <p class="mt-1 text-xs text-slate-500">Permet d’ouvrir votre fiche avec une adresse plus lisible dans le navigateur.</p>
                    <?php if (!empty($errors['profile_slug'])): foreach ($errors['profile_slug'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label for="steam_id" class="block text-sm font-medium text-slate-700 mb-1">Steam ID (liaison ATAK)</label>
                    <input type="text" name="steam_id" id="steam_id" value="<?= htmlspecialchars($user['steam_id'] ?? '') ?>" placeholder="76561198…" class="w-full max-w-md px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900" maxlength="20">
                    <?php if (!empty($errors['steam_id'])): foreach ($errors['steam_id'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-slate-700 mb-1">Prénom</label>
                        <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900" maxlength="100">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
                        <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900" maxlength="100">
                    </div>
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
                    <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900" maxlength="50">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-slate-700 mb-1">Fuseau horaire</label>
                        <input type="text" name="timezone" id="timezone" value="<?= htmlspecialchars($profile['timezone'] ?? 'Europe/Paris') ?>" placeholder="Europe/Paris" list="tz-suggestions" autocomplete="off" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="50">
                        <datalist id="tz-suggestions">
                            <?php foreach ($timezoneSuggestions as $tz): ?>
                            <option value="<?= htmlspecialchars($tz) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <p class="mt-1 text-xs text-slate-500">Saisie libre (IANA), ou choix dans la liste.</p>
                    </div>
                    <div>
                        <label for="language" class="block text-sm font-medium text-slate-700 mb-1">Langue</label>
                        <select name="language" id="language" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900">
                            <option value="fr" <?= ($profile['language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="en" <?= ($profile['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interface -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-black text-slate-900">Interface</h2>
            <p class="mt-1 text-sm text-slate-600">Thème et densité sont enregistrés pour votre compte et réutilisés sur tout le portail.</p>
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="ui_theme" class="block text-sm font-medium text-slate-700 mb-1">Thème</label>
                    <select name="ui_theme" id="ui_theme" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900">
                        <option value="system" <?= ($uiPrefs['theme'] ?? '') === 'system' ? 'selected' : '' ?>>Système (auto)</option>
                        <option value="light" <?= ($uiPrefs['theme'] ?? '') === 'light' ? 'selected' : '' ?>>Clair</option>
                        <option value="dark" <?= ($uiPrefs['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Sombre</option>
                        <option value="tenant" <?= ($uiPrefs['theme'] ?? '') === 'tenant' ? 'selected' : '' ?>>Communauté (marque)</option>
                    </select>
                </div>
                <div>
                    <label for="ui_density" class="block text-sm font-medium text-slate-700 mb-1">Densité des listes</label>
                    <select name="ui_density" id="ui_density" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900">
                        <option value="comfortable" <?= ($uiPrefs['density'] ?? '') === 'comfortable' ? 'selected' : '' ?>>Confortable</option>
                        <option value="compact" <?= ($uiPrefs['density'] ?? '') === 'compact' ? 'selected' : '' ?>>Compact</option>
                    </select>
                </div>
            </div>
            <div class="mt-5 flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                <input type="checkbox" name="ui_sidebar_collapsed" id="ui_sidebar_collapsed" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900" <?= !empty($uiPrefs['sidebar_collapsed']) ? 'checked' : '' ?>>
                <div>
                    <label for="ui_sidebar_collapsed" class="text-sm font-semibold text-slate-900">Barre latérale repliée par défaut</label>
                    <p class="mt-0.5 text-xs text-slate-600">Utile sur petit écran ou pour un focus sur le contenu central.</p>
                </div>
            </div>
        </div>

        <!-- Notifications e-mail -->
        <div id="notifications-email" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 scroll-mt-24">
            <h2 class="text-lg font-black text-slate-900">Notifications par e-mail</h2>
            <p class="mt-1 text-sm text-slate-600">
                Décochez les types de messages que vous ne souhaitez plus recevoir. Les e-mails indispensables (réinitialisation de mot de passe, vérification d’adresse, liens à usage unique) peuvent toujours être envoyés.
                Les thèmes ci-dessous couvrent la sécurité du compte, les événements, les formations, le recrutement et les alertes utiles à l’équipe (modération, nouveaux membres).
            </p>
            <div class="mt-6 space-y-8">
                <?php foreach ($notifByGroup as $groupName => $items): ?>
                <div>
                    <h3 class="border-b border-slate-100 pb-2 text-xs font-black uppercase tracking-wider text-slate-500"><?= htmlspecialchars($groupName) ?></h3>
                    <ul class="mt-4 space-y-3">
                        <?php foreach ($items as $item): ?>
                        <?php
                            $key = $item['key'];
                            $checked = !empty($notifEmailState[$key]);
                        ?>
                        <li class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 px-4 py-3">
                            <input type="checkbox" name="notif_email[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" id="notif_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_]/', '_', $key)) ?>" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900" <?= $checked ? 'checked' : '' ?>>
                            <div class="min-w-0 flex-1">
                                <label for="notif_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_]/', '_', $key)) ?>" class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($item['label']) ?></label>
                                <p class="mt-0.5 text-xs text-slate-600"><?= htmlspecialchars($item['hint']) ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800">Enregistrer tout</button>
            <a href="<?= url('account') ?>" class="text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Retour aux paramètres</a>
        </div>
    </form>
</div>
