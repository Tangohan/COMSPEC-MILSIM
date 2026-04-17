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
$steamWebConfigured = !empty($steamWebConfigured ?? false);
$steamSyncReport = is_array($steamSyncReport ?? null) ? $steamSyncReport : null;

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
            Données légales (identité civile), données de profil opérationnel et préférences d’interface sont séparées pour limiter l’exposition des informations sensibles.
        </p>
    </div>

    <?php if ($success): ?>
    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-900"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($steamSyncReport !== null): ?>
    <div class="mb-6 rounded-2xl border <?= !empty($steamSyncReport['ok']) ? 'border-emerald-200 bg-emerald-50/90' : 'border-amber-200 bg-amber-50/90' ?> p-5 shadow-sm sm:p-6" role="region" aria-label="Détail de la synchronisation Steam">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-black text-slate-900"><?= !empty($steamSyncReport['ok']) ? 'Synchronisation terminée' : 'Synchronisation interrompue' ?></h2>
                <?php if (!empty($steamSyncReport['finished_at'])): ?>
                <p class="mt-1 text-xs text-slate-600"><?= htmlspecialchars((string) $steamSyncReport['finished_at'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold <?= !empty($steamSyncReport['ok']) ? 'bg-emerald-600 text-white' : 'bg-amber-700 text-white' ?>">
                <?= !empty($steamSyncReport['ok']) ? 'Réussi' : 'À vérifier' ?>
            </span>
        </div>
        <?php $steps = isset($steamSyncReport['steps']) && is_array($steamSyncReport['steps']) ? $steamSyncReport['steps'] : []; ?>
        <?php if ($steps !== []): ?>
        <ol class="mt-5 space-y-3">
            <?php foreach ($steps as $idx => $st):
                $ok = !empty($st['ok']);
                $label = htmlspecialchars((string) ($st['label'] ?? 'Étape'), ENT_QUOTES, 'UTF-8');
                $detail = htmlspecialchars((string) ($st['detail'] ?? ''), ENT_QUOTES, 'UTF-8');
                ?>
            <li class="flex gap-3 rounded-xl border border-white/60 bg-white/70 px-4 py-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-black <?= $ok ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' ?>" aria-hidden="true"><?= (int) $idx + 1 ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-900"><?= $label ?></p>
                    <?php if ($detail !== ''): ?><p class="mt-0.5 text-xs leading-relaxed text-slate-600"><?= $detail ?></p><?php endif; ?>
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
        <div class="mt-5 rounded-xl border border-slate-200/80 bg-white/90 px-4 py-4">
            <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-500">Données lues sur le profil public</h3>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                <?php if ($pseudo !== ''): ?>
                <div><dt class="text-[10px] font-bold uppercase text-slate-400">Pseudo affiché côté Steam</dt><dd class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($pseudo, ENT_QUOTES, 'UTF-8') ?></dd></div>
                <?php endif; ?>
                <?php if ($sidShow !== ''): ?>
                <div><dt class="text-[10px] font-bold uppercase text-slate-400">Identifiant numérique confirmé</dt><dd class="text-sm font-mono font-semibold text-slate-900"><?= htmlspecialchars($sidShow, ENT_QUOTES, 'UTF-8') ?></dd></div>
                <?php endif; ?>
                <div><dt class="text-[10px] font-bold uppercase text-slate-400">Photo du compte</dt><dd class="text-sm text-slate-800"><?= !empty($sd['avatar_updated']) ? 'Mise à jour enregistrée' : 'Inchangée sur cette passe' ?></dd></div>
                <div><dt class="text-[10px] font-bold uppercase text-slate-400">Nom d’affichage sur le portail</dt><dd class="text-sm text-slate-800"><?= !empty($sd['display_name_updated']) ? 'Aligné sur le pseudo Steam (option cochée)' : 'Inchangé sur cette passe' ?></dd></div>
            </dl>
        </div>
        <?php endif; ?>
    </div>
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
            <h2 class="text-lg font-black text-slate-900">Profil portail & contact opérationnel</h2>
            <p class="mt-1 text-sm text-slate-600">Nom affiché, indicatif et liens techniques pour le portail. L’identité légale est gérée séparément ci-dessous.</p>
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
                    <input type="text" name="profile_slug" id="profile_slug" value="<?= htmlspecialchars($user['profile_slug'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono lowercase focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="40" placeholder="ex. jean-dupont" autocomplete="username">
                    <p class="mt-1 text-xs text-slate-500">Lettres minuscules, chiffres et tirets uniquement ; commence et finit par une lettre ou un chiffre. Laissez vide pour revenir à l’adresse par défaut.</p>
                    <?php if (!empty($errors['profile_slug'])): foreach ($errors['profile_slug'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 sm:p-5">
                    <label for="steam_id" class="block text-sm font-medium text-slate-700 mb-1">Lien avec Steam (jeu et cartographie)</label>
                    <input type="text" name="steam_id" id="steam_id" value="<?= htmlspecialchars($user['steam_id'] ?? '') ?>" placeholder="Numéro à 17 chiffres ou adresse de votre profil public" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-900 bg-white" maxlength="512" autocomplete="off">
                    <p class="mt-2 text-xs text-slate-600 leading-relaxed">Vous pouvez coller le <strong>numéro</strong> affiché dans le jeu, une adresse du type <span class="whitespace-nowrap font-mono text-[11px] text-slate-700">…/profiles/76561198…</span><?php if ($steamWebConfigured): ?> ou une adresse <span class="whitespace-nowrap font-mono text-[11px] text-slate-700">…/id/votre-pseudo</span><?php endif; ?>. La synchronisation enregistre aussi l’identifiant si vous venez de le coller.</p>
                    <?php if (!empty($errors['steam_id'])): foreach ($errors['steam_id'] as $e): ?>
                    <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; endif; ?>

                    <?php if ($steamWebConfigured): ?>
                    <div class="mt-5 space-y-4 rounded-xl border border-slate-200/80 bg-white px-4 py-4 sm:px-5">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Synchronisation du profil public</p>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">Cochez l’option souhaitée puis lancez la lecture du profil public (photo et éventuellement nom d’affichage). Les autres réglages de la page ne sont pas enregistrés tant que vous n’utilisez pas « Enregistrer tout ».</p>
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-3 text-xs text-slate-800">
                            <input type="checkbox" name="apply_steam_display_name" value="1" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                            <span><strong>Aligner le nom d’affichage</strong> sur le pseudo public Steam (en plus de la photo).</span>
                        </label>
                        <button type="submit" formaction="<?= htmlspecialchars(url('account/steam-sync'), ENT_QUOTES, 'UTF-8') ?>" formmethod="post" formnovalidate class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2">
                            Synchroniser photo &amp; profil Steam
                        </button>
                        <p class="text-[11px] text-slate-500">Un récapitulatif (étapes, état, détails) s’affiche après la synchronisation.</p>
                    </div>
                    <?php else: ?>
                    <p class="mt-3 text-xs text-slate-600">La lecture automatique du profil public n’est pas activée sur ce serveur : vous pouvez tout de même enregistrer le numéro à 17 chiffres ou une adresse se terminant par <span class="font-mono">…/profiles/…</span> pour les outils qui en ont besoin.</p>
                    <?php endif; ?>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 sm:p-5">
                    <h3 class="text-sm font-black text-amber-900">Identité légale (isolée)</h3>
                    <p class="mt-1 text-xs text-amber-900/80">Ces champs sont stockés dans un espace de données séparé de votre profil opérationnel.</p>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                    </div>
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
            <div class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 sm:grid-cols-[1fr_auto] sm:items-center">
                <label class="block">
                    <span class="sr-only">Filtrer les notifications</span>
                    <input type="search" id="notif-search" placeholder="Filtrer (ex. sécurité, formation, recrutement…)" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:ring-2 focus:ring-slate-900">
                </label>
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <button type="button" id="notif-enable-all" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-[11px] font-black uppercase tracking-wider text-emerald-900 hover:bg-emerald-100">Tout activer</button>
                    <button type="button" id="notif-disable-all" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-[11px] font-black uppercase tracking-wider text-amber-900 hover:bg-amber-100">Tout désactiver</button>
                    <button type="button" id="notif-reset-filter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-[11px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-100">Réinitialiser filtre</button>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span id="notif-stats" class="rounded-lg bg-slate-900 px-3 py-1.5 text-[11px] font-bold text-white">0 / 0 actives</span>
                <button type="button" data-notif-preset="minimum" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-100">Preset minimum</button>
                <button type="button" data-notif-preset="standard" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-100">Preset standard</button>
                <button type="button" data-notif-preset="ops" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-100">Preset ops</button>
            </div>
            <div class="mt-6 space-y-8">
                <?php foreach ($notifByGroup as $groupName => $items): ?>
                <div data-notif-group="<?= htmlspecialchars(strtolower($groupName), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-500"><?= htmlspecialchars($groupName) ?></h3>
                        <div class="flex gap-2">
                            <button type="button" data-group-toggle="1" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[10px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-100">Activer groupe</button>
                            <button type="button" data-group-toggle="0" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[10px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-100">Désactiver groupe</button>
                        </div>
                    </div>
                    <ul class="mt-4 space-y-3">
                        <?php foreach ($items as $item): ?>
                        <?php
                            $key = $item['key'];
                            $checked = !empty($notifEmailState[$key]);
                            $searchBlob = strtolower(($item['label'] ?? '') . ' ' . ($item['hint'] ?? '') . ' ' . $groupName);
                        ?>
                        <li class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/50 px-4 py-3" data-notif-item="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="checkbox" name="notif_email[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" id="notif_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_]/', '_', $key)) ?>" value="1" class="notif-email-toggle mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900" <?= $checked ? 'checked' : '' ?>>
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
