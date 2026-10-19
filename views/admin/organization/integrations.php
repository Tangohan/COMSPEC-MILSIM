<?php
declare(strict_types=1);

/**
 * Intégrations de la communauté (back-office) : Discord, e-mails, transmissions terrain, jetons d'accès,
 * et, pour l'administrateur de la plateforme, les salons et l'envoi commun de la plateforme (plus rien dans le .env).
 *
 * Sommaire à sections, vue d'ensemble de l'état de chaque connexion, explications pas à pas.
 * Comportements : public/assets/js/back-office-integrations.js. Styles : back-office-integrations.css.
 *
 * @var list<array<string, mixed>> $integration_keys
 * @var string|null $new_integration_key_plain
 * @var list<string> $available_scopes
 * @var bool $api_keys_allowed
 * @var list<array{key:string, group:string, label:string, hint:string, default_mode:string}> $discord_events
 * @var array{default_url:string, events:array<string, array{mode:string, url:string}>} $discord_state
 * @var list<array{id:string,label:string,masked:string}> $discord_relays
 * @var bool $use_community_relay
 * @var bool $community_relay_ready
 * @var array<string, mixed> $mail_sender
 * @var array<string, array{label:string, host:string, port:int, encryption:string, username:string}> $mail_providers
 * @var string $admin_email
 * @var bool $platform_admin
 * @var array<string, mixed>|null $platform_mail_sender
 * @var string $platform_env_mailer
 * @var array<string, array{url:string, from_env:bool}> $platform_discord
 */

$keys = is_array($integration_keys ?? null) ? $integration_keys : [];
$newKeyPlain = $new_integration_key_plain ?? null;
$availableScopes = is_array($available_scopes ?? null) && $available_scopes !== [] ? $available_scopes : ['events:read'];
$apiKeysAllowed = !empty($api_keys_allowed);
$discordEvents = is_array($discord_events ?? null) ? $discord_events : [];
$discordState = is_array($discord_state ?? null) ? $discord_state : ['default_url' => '', 'events' => []];
$sseRelays = is_array($discord_relays ?? null) ? $discord_relays : [];
$useCommunityRelay = !empty($use_community_relay);
$communityRelayReady = !empty($community_relay_ready);
$mailSender = is_array($mail_sender ?? null) ? $mail_sender : [];
$mailProviders = is_array($mail_providers ?? null) ? $mail_providers : [];
$adminEmail = (string) ($admin_email ?? '');
$platformAdmin = !empty($platform_admin);
$platformMail = is_array($platform_mail_sender ?? null) ? $platform_mail_sender : [];
$platformEnvMailer = (string) ($platform_env_mailer ?? '');
$platformDiscord = is_array($platform_discord ?? null) ? $platform_discord : [];
$discordDefaultUrl = (string) ($discordState['default_url'] ?? '');
$discordEventState = is_array($discordState['events'] ?? null) ? $discordState['events'] : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$icon = static function (string $name): string {
    $paths = match ($name) {
        'overview' => '<path d="M4 13h6V4H4zM14 20h6v-9h-6zM14 4v4h6V4zM4 20h6v-3H4z"/>',
        'discord' => '<path d="M8 17c-2.5-.4-4-1.3-5-2.3.2-4 1.5-7.4 3.4-9.5A15 15 0 0110 4l.6 1.3a12 12 0 012.8 0L14 4a15 15 0 013.6 1.2c1.9 2.1 3.2 5.5 3.4 9.5-1 1-2.5 1.9-5 2.3l-1-1.8"/><path d="M8 15.5c2.6 1 5.4 1 8 0"/><circle cx="9" cy="11.5" r="1.2"/><circle cx="15" cy="11.5" r="1.2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'radio' => '<path d="M5 12a7 7 0 0114 0M8 12a4 4 0 018 0"/><circle cx="12" cy="12" r="1.3"/><path d="M12 13.5V21M9 21h6"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M14 9l2 2"/>',
        'platform' => '<path d="M12 3l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V7l8-4z"/><path d="M9 12l2 2 4-4"/>',
        'game' => '<rect x="2" y="7" width="20" height="11" rx="4"/><path d="M7 11v4M5 13h4M15.5 12.5h.01M18 14h.01"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'chevron' => '<path d="M9 6l6 6-6 6"/>',
        'warn' => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.01"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'link' => '<path d="M10 14a5 5 0 007 0l3-3a5 5 0 00-7-7l-1 1"/><path d="M14 10a5 5 0 00-7 0l-3 3a5 5 0 007 7l1-1"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.01"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="bo-int-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};

/** @return list<string> */
$decodeScopes = static function (mixed $raw): array {
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(
        array_map(static fn ($scope): string => trim((string) $scope), $decoded),
        static fn (string $scope): bool => $scope !== ''
    ));
};

$scopeLabelFr = static function (string $scope): string {
    return match ($scope) {
        'events:read' => 'Lecture des événements',
        default => $scope,
    };
};

$fmtDt = static function (mixed $raw, string $fallback = '—'): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return $fallback;
    }
    $t = strtotime($s);

    return $t ? date('d/m/Y H:i', $t) : $fallback;
};

/** Lien webhook raccourci pour l'affichage : discord.com/api/webhooks/1234…/•••• */
$maskWebhook = static function (string $url): string {
    if (preg_match('#webhooks/(\d{4})\d*/#', $url, $m) === 1) {
        return 'discord.com/api/webhooks/' . $m[1] . '…/••••';
    }

    return $url !== '' ? 'lien enregistré' : '';
};

// Discord : événements par groupe, compteurs de publication.
$discordGroups = [];
$publishedCount = 0;
$customCount = 0;
foreach ($discordEvents as $ev) {
    $g = (string) ($ev['group'] ?? 'Autres');
    $discordGroups[$g][] = $ev;
    $mode = (string) ($discordEventState[(string) ($ev['key'] ?? '')]['mode'] ?? ($ev['default_mode'] ?? 'off'));
    if ($mode !== 'off') {
        $publishedCount++;
    }
    if ($mode === 'custom') {
        $customCount++;
    }
}
$discordLinked = $discordDefaultUrl !== '' || $customCount > 0;
$groupIcons = [
    'Portail' => '📣', 'Téléphone ATAK' => '📱', 'Effectifs' => '🪖', 'Recrutement' => '📝', 'Opérations' => '🎯',
    'Formation' => '🎓', 'Modération' => '🛡️', 'Renseignement' => '🛰️', 'Accès' => '🔑',
];

// Jetons.
$activeCount = 0;
$revokedCount = 0;
$todayTotal = 0;
$quotaTotal = 0;
foreach ($keys as $k) {
    if (!empty($k['revoked_at'])) {
        $revokedCount++;
        continue;
    }
    $activeCount++;
    $todayTotal += max(0, (int) ($k['today_request_count'] ?? 0));
    $quotaTotal += max(1, (int) ($k['quota_per_day'] ?? 10000));
}
$usageRatio = $quotaTotal > 0 ? (int) round($todayTotal / $quotaTotal * 100) : 0;

// Vue d'ensemble : un état par connexion.
$mailState = !empty($mailSender['ready']) ? 'ok' : (!empty($mailSender['enabled']) ? 'warn' : 'off');
$platformDiscordSaved = 0;
$platformFromEnv = false;
foreach ($platformDiscord as $row) {
    if ((string) ($row['url'] ?? '') !== '') {
        $platformDiscordSaved++;
    }
    if (!empty($row['from_env'])) {
        $platformFromEnv = true;
    }
}
$platformMailReady = !empty($platformMail['ready']);
$platformEnvActive = $platformEnvMailer !== '' && $platformEnvMailer !== 'file';
if ($platformMailReady) {
    $platformMailFromEnv = false;
} else {
    $platformMailFromEnv = $platformEnvActive;
}
$platformState = ($platformFromEnv || $platformMailFromEnv) ? 'warn' : (($platformMailReady || $platformDiscordSaved > 0) ? 'ok' : 'off');

$tiles = [
    [
        'href' => '#relais-discord',
        'icon' => 'discord',
        'title' => 'Discord',
        'state' => $discordLinked ? ($publishedCount > 0 ? 'ok' : 'warn') : 'off',
        'value' => $discordLinked ? $publishedCount . ' / ' . count($discordEvents) : 'Non relié',
        'note' => $discordLinked ? 'événements publiés' : 'aucun salon renseigné',
    ],
    [
        'href' => '#emails-communaute',
        'icon' => 'mail',
        'title' => 'E-mails',
        'state' => $mailState,
        'value' => match ($mailState) { 'ok' => 'Votre compte', 'warn' => 'Incomplet', default => 'Envoi commun' },
        'note' => $mailState === 'ok' ? (string) ($mailSender['from_address'] ?? '') : ($mailState === 'warn' ? 'expéditeur ou serveur manquant' : 'partent avec Athena'),
    ],
    [
        'href' => '#relais-transmissions',
        'icon' => 'radio',
        'title' => 'Transmissions',
        'state' => ($sseRelays !== [] || $useCommunityRelay) ? 'ok' : 'off',
        'value' => (string) (count($sseRelays) + ($useCommunityRelay ? 1 : 0)),
        'note' => (count($sseRelays) + ($useCommunityRelay ? 1 : 0)) > 1 ? 'salons du journal SSE' : 'salon du journal SSE',
    ],
    [
        'href' => '#jetons',
        'icon' => 'key',
        'title' => 'Jetons',
        'state' => !$apiKeysAllowed ? 'lock' : ($activeCount > 0 ? 'ok' : 'off'),
        'value' => !$apiKeysAllowed ? 'Formule Pro+' : (string) $activeCount,
        'note' => !$apiKeysAllowed ? 'non inclus dans votre formule' : ($activeCount > 1 ? 'jetons actifs' : 'jeton actif'),
    ],
];
if ($platformAdmin) {
    $tiles[] = [
        'href' => '#plateforme',
        'icon' => 'platform',
        'title' => 'Plateforme',
        'state' => $platformState,
        'value' => $platformState === 'warn' ? 'Ancien .env' : ($platformState === 'ok' ? 'En base' : 'Rien de réglé'),
        'note' => $platformState === 'warn' ? 'à reprendre ici' : 'salons et envoi commun',
    ];
}
$stateLabel = ['ok' => 'Actif', 'warn' => 'À vérifier', 'off' => 'Coupé', 'lock' => 'Formule'];

$nav = [
    ['href' => '#vue-ensemble', 'icon' => 'overview', 'label' => 'Vue d’ensemble'],
    ['href' => '#relais-discord', 'icon' => 'discord', 'label' => 'Discord', 'count' => $publishedCount],
    ['href' => '#emails-communaute', 'icon' => 'mail', 'label' => 'E-mails'],
    ['href' => '#relais-transmissions', 'icon' => 'radio', 'label' => 'Transmissions terrain', 'count' => count($sseRelays)],
    ['href' => '#jetons', 'icon' => 'key', 'label' => 'Jetons d’accès', 'count' => $apiKeysAllowed ? $activeCount : null],
];
if ($platformAdmin) {
    $nav[] = ['href' => '#plateforme', 'icon' => 'platform', 'label' => 'Plateforme'];
}
$nav[] = ['href' => '#arma-overwatch', 'icon' => 'game', 'label' => 'Arma 3 / Overwatch'];

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

<?php if ($newKeyPlain): ?>
<div class="bo-int-secret" role="alert">
    <p class="bo-int-secret__title">Copiez ce jeton maintenant : il ne sera plus affiché en clair.</p>
    <code class="bo-int-secret__value"><?= $h((string) $newKeyPlain) ?></code>
    <p class="bo-int-secret__note">Conservez-le dans un gestionnaire de secrets, jamais en clair dans du code source.</p>
</div>
<?php endif; ?>

<div class="bo-int__layout">
    <nav class="bo-int-nav" aria-label="Sections des intégrations">
        <p class="bo-int-nav__title">Sections</p>
        <?php foreach ($nav as $item): ?>
            <a class="bo-int-nav__link" href="<?= $h($item['href']) ?>" data-int-nav>
                <?= $icon($item['icon']) ?>
                <span><?= $h($item['label']) ?></span>
                <?php if (isset($item['count']) && $item['count'] !== null && (int) $item['count'] > 0): ?>
                    <span class="bo-int-nav__count"><?= (int) $item['count'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="bo-int__main">

    <!-- ── Vue d'ensemble ─────────────────────────────────────────── -->
    <section id="vue-ensemble" class="bo-int-section" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon"><?= $icon('overview') ?></span>
            <div>
                <h2 class="bo-int-section__title">Vue d’ensemble</h2>
                <p class="bo-int-section__desc">Tout ce qui relie votre communauté au reste du monde, réglé ici et propre à votre communauté : aucune clé à demander à l’administrateur du serveur.</p>
            </div>
        </header>
        <div class="bo-int-tiles">
            <?php foreach ($tiles as $tile): ?>
                <a class="bo-int-tile bo-int-tile--<?= $h($tile['state']) ?>" href="<?= $h($tile['href']) ?>">
                    <span class="bo-int-tile__head">
                        <?= $icon($tile['icon']) ?>
                        <span class="bo-int-tile__title"><?= $h($tile['title']) ?></span>
                    </span>
                    <span class="bo-int-tile__value"><?= $h($tile['value']) ?></span>
                    <span class="bo-int-tile__note"><?= $h($tile['note']) ?></span>
                    <span class="bo-int-badge bo-int-badge--<?= $h($tile['state']) ?>"><?= $h($stateLabel[$tile['state']] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="bo-int-concepts">
            <div class="bo-int-concept">
                <p class="bo-int-concept__title"><?= $icon('link') ?> Un webhook, c’est quoi ?</p>
                <p class="bo-int-concept__text">Un lien secret, créé dans un salon Discord, qui permet à Athena d’y publier des messages sans bot ni invitation. Un salon = un lien. Quiconque a le lien peut écrire dans le salon : gardez-le pour vous.</p>
            </div>
            <div class="bo-int-concept">
                <p class="bo-int-concept__title"><?= $icon('send') ?> Salon commun ou autre salon</p>
                <p class="bo-int-concept__text">Le salon commun reçoit tout ce que vous réglez sur « Salon commun ». Pour envoyer un événement ailleurs (photos dans #renseignement, candidatures dans #recrutement), choisissez « Autre salon » et collez le lien de ce salon.</p>
            </div>
            <div class="bo-int-concept">
                <p class="bo-int-concept__title"><?= $icon('mail') ?> Votre propre adresse d’envoi</p>
                <p class="bo-int-concept__text">Les e-mails de la communauté (invitations, candidatures, codes de connexion…) peuvent partir de votre domaine, à votre nom et sur votre quota, au lieu de l’envoi commun d’Athena.</p>
            </div>
        </div>
    </section>

    <!-- ── Discord ─────────────────────────────────────────────────── -->
    <section id="relais-discord" class="bo-int-section" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon bo-int-section__icon--discord"><?= $icon('discord') ?></span>
            <div>
                <h2 class="bo-int-section__title">Discord</h2>
                <p class="bo-int-section__desc">Choisissez ce qu’Athena publie dans vos salons : annonces, photos du téléphone, notes de reco, candidatures, effectifs, opérations…</p>
            </div>
        </header>

        <details class="bo-int-guide"<?= $discordLinked ? '' : ' open' ?>>
            <summary class="bo-int-guide__summary"><?= $icon('info') ?> Créer le lien webhook d’un salon (30 secondes)</summary>
            <ol class="bo-int-guide__steps">
                <li><span class="bo-int-step">1</span><span>Dans Discord : clic droit sur le salon › <strong>Modifier le salon</strong> › <strong>Intégrations</strong> › <strong>Webhooks</strong>.</span></li>
                <li><span class="bo-int-step">2</span><span><strong>Nouveau webhook</strong>, donnez-lui un nom (par exemple « Athena »), puis <strong>Copier l’URL du webhook</strong>.</span></li>
                <li><span class="bo-int-step">3</span><span>Collez-le dans <strong>Salon commun</strong> ci-dessous, ou dans <strong>Autre salon</strong> pour un événement précis, puis enregistrez.</span></li>
                <li><span class="bo-int-step">4</span><span>Bouton <strong>Essai</strong> à côté de l’événement : un court message doit apparaître dans le salon.</span></li>
            </ol>
            <div class="bo-int-anatomy" aria-label="Forme d’un lien webhook">
                <code><span class="bo-int-anatomy__base">https://discord.com/api/webhooks/</span><span class="bo-int-anatomy__id">1290000000000000001</span>/<span class="bo-int-anatomy__token">AbCdEf…</span></code>
                <span class="bo-int-anatomy__legend"><span class="bo-int-anatomy__dot bo-int-anatomy__dot--id"></span>numéro du webhook <span class="bo-int-anatomy__dot bo-int-anatomy__dot--token"></span>jeton secret</span>
            </div>
            <p class="bo-int-guide__note"><?= $icon('warn') ?> L’identifiant du salon (le grand nombre de « Copier l’identifiant du salon ») et le lien <code>discord.com/channels/…</code> ne suffisent pas : Athena publie sans bot, Discord n’accepte que le lien webhook.</p>
        </details>

        <p class="bo-int-guide__note"><?= $icon('send') ?> Pour présenter votre communauté sur le Discord Athena (salon des communautés), voyez <a href="<?= $h(url('back-office/community/discord')) ?>">Annoncer sur Discord</a>.</p>

        <form method="post" action="<?= $h(url('back-office/integrations/discord')) ?>" id="form-relais-discord" class="bo-int-discord" data-discord-form>
            <?= \App\Core\Csrf::field() ?>
            <div class="bo-int-common">
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Salon commun · lien webhook</span>
                    <input type="text" inputmode="url" autocomplete="off" spellcheck="false" name="discord_webhook_url" maxlength="500" class="bo-int-input bo-int-mono" value="<?= $h($discordDefaultUrl) ?>" placeholder="https://discord.com/api/webhooks/…" data-webhook-input>
                    <span class="bo-int-check-msg" data-webhook-msg aria-live="polite"></span>
                    <span class="bo-int-field__help">Tous les événements réglés sur « Salon commun » partent ici. Laissez vide si vous n’utilisez que des salons à part.</span>
                </label>
            </div>

            <div class="bo-int-toolbar">
                <label class="bo-int-search">
                    <?= $icon('search') ?>
                    <span class="bo-int-sr">Chercher un événement</span>
                    <input type="search" class="bo-int-search__input" placeholder="Chercher un événement (photo, candidature, reco…)" data-discord-search>
                </label>
                <div class="bo-int-filter" role="group" aria-label="Filtrer les événements">
                    <button type="button" class="bo-int-filter__btn is-active" data-discord-filter="all">Tous</button>
                    <button type="button" class="bo-int-filter__btn" data-discord-filter="on">Publiés <span data-discord-count-on><?= (int) $publishedCount ?></span></button>
                    <button type="button" class="bo-int-filter__btn" data-discord-filter="off">Coupés</button>
                </div>
            </div>
            <p class="bo-int-empty" data-discord-empty hidden>Aucun événement ne correspond.</p>

            <?php foreach ($discordGroups as $groupLabel => $groupEvents): ?>
                <?php
                $groupOn = 0;
                foreach ($groupEvents as $gev) {
                    if ((string) ($discordEventState[(string) ($gev['key'] ?? '')]['mode'] ?? ($gev['default_mode'] ?? 'off')) !== 'off') {
                        $groupOn++;
                    }
                }
                ?>
                <details class="bo-int-group" data-discord-group<?= $groupLabel === 'Téléphone ATAK' ? ' id="relais-discord-atak"' : '' ?><?= ($groupOn > 0 || $groupLabel === 'Téléphone ATAK') ? ' open' : '' ?>>
                    <summary class="bo-int-group__head">
                        <span class="bo-int-group__caret" aria-hidden="true"><?= $icon('chevron') ?></span>
                        <span class="bo-int-group__emoji" aria-hidden="true"><?= $h($groupIcons[$groupLabel] ?? '•') ?></span>
                        <h3 class="bo-int-group__title"><?= $h((string) $groupLabel) ?></h3>
                        <span class="bo-int-group__count"><span data-group-on><?= $groupOn ?></span> / <?= count($groupEvents) ?> publiés</span>
                        <span class="bo-int-group__bulk">
                            <button type="button" class="bo-int-link-btn" data-group-set="default">Tout sur le salon commun</button>
                            <button type="button" class="bo-int-link-btn" data-group-set="off">Tout couper</button>
                        </span>
                    </summary>
                    <?php if ($groupLabel === 'Téléphone ATAK'): ?>
                        <p class="bo-int-group__note">Le lien du salon reste sur Athena : il n’est jamais envoyé au jeu. L’<strong>App Discord du téléphone</strong> ne fonctionne qu’une fois un salon choisi ici. Pour vérifier le téléphone, essayez « App Discord du téléphone » ou « Photos Quick Picture ».</p>
                    <?php endif; ?>
                    <?php foreach ($groupEvents as $ev): ?>
                        <?php
                        $ek = (string) ($ev['key'] ?? '');
                        $st = is_array($discordEventState[$ek] ?? null) ? $discordEventState[$ek] : [];
                        $mode = (string) ($st['mode'] ?? ($ev['default_mode'] ?? 'off'));
                        $dedicatedUrl = (string) ($st['url'] ?? '');
                        $search = mb_strtolower((string) ($ev['label'] ?? '') . ' ' . (string) ($ev['hint'] ?? '') . ' ' . (string) $groupLabel);
                        ?>
                        <div class="bo-int-event" data-discord-event data-mode="<?= $h($mode) ?>" data-search="<?= $h($search) ?>">
                            <div class="bo-int-event__text">
                                <p class="bo-int-event__name"><?= $h((string) ($ev['label'] ?? '')) ?></p>
                                <p class="bo-int-event__hint"><?= $h((string) ($ev['hint'] ?? '')) ?></p>
                            </div>
                            <div class="bo-int-event__ctrl">
                                <div class="bo-int-seg" role="radiogroup" aria-label="Où publier « <?= $h((string) ($ev['label'] ?? '')) ?> »">
                                    <?php foreach (['off' => 'Ne pas publier', 'default' => 'Salon commun', 'custom' => 'Autre salon'] as $mv => $ml): ?>
                                        <label class="bo-int-seg__opt bo-int-seg__opt--<?= $h($mv) ?>">
                                            <input type="radio" class="js-discord-mode" name="discord_event[<?= $h($ek) ?>][mode]" value="<?= $h($mv) ?>" <?= $mode === $mv ? 'checked' : '' ?>>
                                            <span><?= $h($ml) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" form="form-discord-test" name="event_key" value="<?= $h($ek) ?>" class="bo-int-btn bo-int-btn--sm" data-discord-test title="Envoyer un message d’essai dans le salon enregistré"><?= $icon('send') ?><span>Essai</span></button>
                            </div>
                            <label class="bo-int-field bo-int-event__custom js-discord-custom"<?= $mode === 'custom' ? '' : ' hidden' ?>>
                                <span class="bo-int-field__label">Autre salon · lien webhook</span>
                                <input type="text" inputmode="url" autocomplete="off" spellcheck="false" name="discord_event[<?= $h($ek) ?>][url]" maxlength="500" class="bo-int-input bo-int-mono" value="<?= $h($dedicatedUrl) ?>" placeholder="https://discord.com/api/webhooks/…" data-webhook-input>
                                <span class="bo-int-check-msg" data-webhook-msg aria-live="polite"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>

            <div class="bo-int-savebar" data-savebar>
                <span class="bo-int-savebar__state" data-savebar-state>Tout est enregistré.</span>
                <button type="reset" class="bo-int-btn" data-savebar-reset disabled>Annuler</button>
                <button type="submit" class="bo-int-btn bo-int-btn--solid">Enregistrer les relais</button>
            </div>
        </form>
        <form method="post" action="<?= $h(url('back-office/integrations/discord/essai')) ?>" id="form-discord-test" hidden>
            <?= \App\Core\Csrf::field() ?>
        </form>
    </section>

    <!-- ── E-mails ─────────────────────────────────────────────────── -->
    <section id="emails-communaute" class="bo-int-section" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon"><?= $icon('mail') ?></span>
            <div>
                <h2 class="bo-int-section__title">E-mails de la communauté</h2>
                <p class="bo-int-section__desc">Invitations, candidatures, effectifs, codes de connexion, mots de passe… partent de <strong>votre</strong> compte d’envoi : à votre nom, depuis votre adresse, sur votre quota.</p>
            </div>
        </header>

        <div class="bo-int-route">
            <div class="bo-int-route__step<?= $mailState === 'ok' ? ' is-active' : '' ?>">
                <span class="bo-int-route__num">1</span>
                <span class="bo-int-route__text"><strong>Votre compte d’envoi</strong><small><?= $mailState === 'ok' ? $h((string) ($mailSender['from_address'] ?? '')) : 'utilisé dès qu’il est actif' ?></small></span>
            </div>
            <span class="bo-int-route__arrow" aria-hidden="true"><?= $icon('chevron') ?></span>
            <div class="bo-int-route__step<?= $mailState !== 'ok' ? ' is-active' : '' ?>">
                <span class="bo-int-route__num">2</span>
                <span class="bo-int-route__text"><strong>Envoi commun d’Athena</strong><small>sinon, s’il est réglé</small></span>
            </div>
            <span class="bo-int-route__arrow" aria-hidden="true"><?= $icon('chevron') ?></span>
            <div class="bo-int-route__step">
                <span class="bo-int-route__num">3</span>
                <span class="bo-int-route__text"><strong>Rien ne part</strong><small>si aucun des deux n’existe</small></span>
            </div>
        </div>

        <div class="bo-int-status bo-int-status--<?= $h($mailState) ?>">
            <?php if ($mailState === 'ok'): ?>
                <?= $icon('check') ?><span><strong>Actif</strong> : les e-mails partent de <?= $h((string) ($mailSender['from_address'] ?? '')) ?>.</span>
            <?php elseif ($mailState === 'warn'): ?>
                <?= $icon('warn') ?><span><strong>Incomplet</strong> : renseignez l’expéditeur et le serveur, puis enregistrez.</span>
            <?php else: ?>
                <?= $icon('info') ?><span><strong>Coupé</strong> : aucun compte d’envoi propre à la communauté, l’envoi commun d’Athena s’applique.</span>
            <?php endif; ?>
        </div>

        <?php
        $mailFormState = $mailSender;
        $mailFormProviders = $mailProviders;
        $mailFormAction = url('back-office/integrations/emails');
        $mailFormTestAction = url('back-office/integrations/emails/essai');
        $mailFormId = 'mail-community';
        $mailFormWho = 'la communauté';
        $mailFormFromPlaceholder = 'no-reply@votre-communaute.fr';
        $mailFormNamePlaceholder = 'Votre communauté';
        $mailFormTestTo = $adminEmail;
        require base_path('views/admin/organization/partials/integrations_mail_form.php');
        ?>
        <p class="bo-int-guide__note"><?= $icon('info') ?> Pour ne pas finir en indésirables, déclarez le service d’envoi dans le DNS de votre domaine (enregistrements SPF et DKIM indiqués par le service).</p>
    </section>

    <!-- ── Transmissions terrain ──────────────────────────────────── -->
    <section id="relais-transmissions" class="bo-int-section" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon"><?= $icon('radio') ?></span>
            <div>
                <h2 class="bo-int-section__title">Transmissions terrain</h2>
                <p class="bo-int-section__desc">Le journal SSE (transmissions saisies au bureau renseignement) peut être recopié dans un ou plusieurs salons, en plus du salon commun. Vous pouvez aussi les gérer depuis le bureau renseignement.</p>
            </div>
        </header>
        <form method="post" action="<?= $h(url('back-office/integrations/sse-relais/communaute')) ?>" class="bo-int-inline">
            <?= \App\Core\Csrf::field() ?>
            <label class="bo-int-switch">
                <input type="hidden" name="use_community_relay" value="0">
                <input type="checkbox" name="use_community_relay" value="1" class="bo-int-switch__input" <?= $useCommunityRelay ? 'checked' : '' ?> <?= $communityRelayReady ? '' : 'disabled' ?>>
                <span class="bo-int-switch__track" aria-hidden="true"><span class="bo-int-switch__thumb"></span></span>
                <span class="bo-int-switch__text">
                    <strong>Publier aussi sur le salon commun</strong>
                    <small><?= $communityRelayReady ? 'Le salon commun de la section Discord reçoit chaque transmission.' : 'Renseignez d’abord le salon commun dans la section Discord, puis enregistrez.' ?></small>
                </span>
            </label>
            <button type="submit" class="bo-int-btn" <?= $communityRelayReady ? '' : 'disabled' ?>>Enregistrer</button>
        </form>

        <?php if ($sseRelays !== []): ?>
            <ul class="bo-int-list">
                <?php foreach ($sseRelays as $relay): ?>
                    <li class="bo-int-list__item">
                        <span class="bo-int-list__icon" aria-hidden="true">#</span>
                        <span class="bo-int-list__text">
                            <strong><?= $h((string) ($relay['label'] ?? '')) ?></strong>
                            <small class="bo-int-mono"><?= $h((string) ($relay['masked'] ?? '')) ?></small>
                        </span>
                        <span class="bo-int-list__actions">
                            <form method="post" action="<?= $h(url('back-office/integrations/sse-relais/' . rawurlencode((string) ($relay['id'] ?? '')) . '/essai')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="bo-int-btn bo-int-btn--sm"><?= $icon('send') ?><span>Essai</span></button>
                            </form>
                            <form method="post" action="<?= $h(url('back-office/integrations/sse-relais/' . rawurlencode((string) ($relay['id'] ?? '')) . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce salon du journal des transmissions ?');">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="bo-int-btn bo-int-btn--sm bo-int-btn--danger">Retirer</button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="bo-int-empty">Aucun salon supplémentaire pour l’instant.</p>
        <?php endif; ?>

        <form method="post" action="<?= $h(url('back-office/integrations/sse-relais')) ?>" class="bo-int-add">
            <?= \App\Core\Csrf::field() ?>
            <p class="bo-int-add__title">Ajouter un salon</p>
            <div class="bo-int-grid">
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Intitulé du salon</span>
                    <input type="text" name="label" maxlength="80" class="bo-int-input" placeholder="Renseignement, TOC…">
                </label>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Lien webhook du salon</span>
                    <input type="text" inputmode="url" autocomplete="off" spellcheck="false" name="discord_url" required maxlength="500" class="bo-int-input bo-int-mono" placeholder="https://discord.com/api/webhooks/…" data-webhook-input>
                    <span class="bo-int-check-msg" data-webhook-msg aria-live="polite"></span>
                </label>
            </div>
            <div class="bo-int-actions">
                <button type="submit" class="bo-int-btn bo-int-btn--solid">Ajouter le salon</button>
            </div>
        </form>
    </section>

    <!-- ── Jetons d'accès ─────────────────────────────────────────── -->
    <section id="jetons" class="bo-int-section" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon"><?= $icon('key') ?></span>
            <div>
                <h2 class="bo-int-section__title">Jetons d’accès</h2>
                <p class="bo-int-section__desc">Pour qu’un site vitrine ou un outil externe lise votre calendrier d’événements. Chaque jeton a son quota et ses droits ; il se renouvelle ou se révoque à tout moment.</p>
            </div>
        </header>
        <?php if ($apiKeysAllowed): ?>
            <div class="bo-int-kpis">
                <div class="bo-int-kpi"><span class="bo-int-kpi__label">Jetons actifs</span><span class="bo-int-kpi__value"><?= (int) $activeCount ?></span></div>
                <div class="bo-int-kpi"><span class="bo-int-kpi__label">Révoqués</span><span class="bo-int-kpi__value"><?= (int) $revokedCount ?></span></div>
                <div class="bo-int-kpi"><span class="bo-int-kpi__label">Requêtes du jour</span><span class="bo-int-kpi__value"><?= $h(number_format($todayTotal, 0, ',', ' ')) ?></span><span class="bo-int-kpi__note">sur <?= $h(number_format($quotaTotal, 0, ',', ' ')) ?> autorisées</span></div>
                <div class="bo-int-kpi"><span class="bo-int-kpi__label">Charge du quota</span><span class="bo-int-kpi__value"><?= $quotaTotal > 0 ? (int) $usageRatio . ' %' : '—' ?></span>
                    <span class="bo-int-meter"><span class="bo-int-meter__fill<?= $usageRatio >= 95 ? ' is-bad' : ($usageRatio >= 80 ? ' is-warn' : '') ?>" style="width:<?= max(0, min(100, $usageRatio)) ?>%"></span></span>
                </div>
            </div>

            <?php if ($keys === []): ?>
                <p class="bo-int-empty">Aucun jeton pour l’instant : créez-en un avec le formulaire ci-dessous.</p>
            <?php else: ?>
                <div class="bo-int-keys">
                    <?php foreach ($keys as $k): ?>
                        <?php
                        $keyId = (int) ($k['id'] ?? 0);
                        $quota = max(1, (int) ($k['quota_per_day'] ?? 10000));
                        $today = max(0, (int) ($k['today_request_count'] ?? 0));
                        $ratio = min(100, (int) round($today / $quota * 100));
                        $isRevoked = !empty($k['revoked_at']);
                        $scopes = $decodeScopes($k['scopes_json'] ?? '');
                        ?>
                        <article class="bo-int-key<?= $isRevoked ? ' is-revoked' : '' ?>">
                            <div class="bo-int-key__head">
                                <div class="bo-int-key__id">
                                    <p class="bo-int-key__name"><?= $h((string) ($k['name'] ?? '')) ?></p>
                                    <p class="bo-int-key__meta">
                                        <span class="bo-int-mono"><?= $h((string) ($k['key_prefix'] ?? '—')) ?>…</span>
                                        · créé le <?= $h($fmtDt($k['created_at'] ?? null)) ?>
                                        · dernière utilisation <?= $h($fmtDt($k['last_used_at'] ?? null, 'jamais')) ?>
                                    </p>
                                </div>
                                <span class="bo-int-badge <?= $isRevoked ? 'bo-int-badge--bad' : 'bo-int-badge--ok' ?>"><?= $isRevoked ? 'Révoqué' : 'Actif' ?></span>
                            </div>
                            <div class="bo-int-key__usage">
                                <span>Consommation du jour</span>
                                <span class="bo-int-mono"><?= number_format($today, 0, ',', ' ') ?> / <?= number_format($quota, 0, ',', ' ') ?></span>
                                <span class="bo-int-meter"><span class="bo-int-meter__fill<?= $ratio >= 95 ? ' is-bad' : ($ratio >= 80 ? ' is-warn' : '') ?>" style="width:<?= $ratio ?>%"></span></span>
                            </div>
                            <?php if ($scopes !== []): ?>
                                <div class="bo-int-key__tags">
                                    <?php foreach ($scopes as $scope): ?>
                                        <span class="bo-int-tag"><?= $h($scopeLabelFr($scope)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$isRevoked): ?>
                                <details class="bo-int-key__edit">
                                    <summary>Modifier le nom, le quota ou les droits</summary>
                                    <form method="post" action="<?= $h(url('back-office/integrations/api-keys/' . $keyId . '/update')) ?>">
                                        <?= \App\Core\Csrf::field() ?>
                                        <div class="bo-int-grid">
                                            <label class="bo-int-field">
                                                <span class="bo-int-field__label">Nom du service</span>
                                                <input type="text" name="name" maxlength="120" required value="<?= $h((string) ($k['name'] ?? '')) ?>" class="bo-int-input">
                                            </label>
                                            <label class="bo-int-field">
                                                <span class="bo-int-field__label">Quota par jour</span>
                                                <input type="number" name="quota_per_day" min="100" max="500000" value="<?= $quota ?>" class="bo-int-input">
                                            </label>
                                        </div>
                                        <div class="bo-int-checks">
                                            <?php foreach ($availableScopes as $scope): ?>
                                                <label class="bo-int-check">
                                                    <input type="checkbox" name="scopes[]" value="<?= $h((string) $scope) ?>" <?= in_array((string) $scope, $scopes, true) ? 'checked' : '' ?>>
                                                    <span><?= $h($scopeLabelFr((string) $scope)) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="bo-int-actions">
                                            <button type="submit" class="bo-int-btn">Enregistrer les réglages</button>
                                        </div>
                                    </form>
                                </details>
                                <div class="bo-int-actions">
                                    <form method="post" action="<?= $h(url('back-office/integrations/api-keys/' . $keyId . '/rotate')) ?>"
                                          onsubmit="return confirm('Renouveler ce jeton ? Un nouveau secret sera généré et l’ancien cessera de fonctionner immédiatement.');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="bo-int-btn bo-int-btn--sm">Renouveler le secret</button>
                                    </form>
                                    <form method="post" action="<?= $h(url('back-office/integrations/api-keys/' . $keyId . '/revoke')) ?>"
                                          onsubmit="return confirm('Révoquer ce jeton ? Les outils qui l’utilisent cesseront de fonctionner.');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="bo-int-btn bo-int-btn--sm bo-int-btn--danger">Révoquer</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= $h(url('back-office/integrations/api-keys')) ?>" class="bo-int-add">
                <?= \App\Core\Csrf::field() ?>
                <p class="bo-int-add__title">Nouveau jeton d’accès <small>Le secret n’est affiché qu’une seule fois, juste après la création.</small></p>
                <div class="bo-int-grid">
                    <label class="bo-int-field">
                        <span class="bo-int-field__label">Nom du service</span>
                        <input type="text" name="name" maxlength="120" required placeholder="Site vitrine, outil logistique…" class="bo-int-input">
                    </label>
                    <label class="bo-int-field">
                        <span class="bo-int-field__label">Quota par jour</span>
                        <input type="number" name="quota_per_day" min="100" max="500000" value="10000" class="bo-int-input">
                        <span class="bo-int-field__help">Entre 100 et 500 000 requêtes.</span>
                    </label>
                </div>
                <div class="bo-int-checks">
                    <?php foreach ($availableScopes as $scope): ?>
                        <label class="bo-int-check">
                            <input type="checkbox" name="scopes[]" value="<?= $h((string) $scope) ?>" checked>
                            <span><?= $h($scopeLabelFr((string) $scope)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="bo-int-actions">
                    <button type="submit" class="bo-int-btn bo-int-btn--solid">Créer le jeton</button>
                </div>
            </form>
        <?php else: ?>
            <div class="bo-int-status bo-int-status--lock">
                <?= $icon('key') ?><span>Les jetons pour relier un site vitrine ou un outil externe au calendrier sont proposés avec une formule supérieure. Discord, e-mails et transmissions terrain restent disponibles pour votre communauté.</span>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($platformAdmin): ?>
    <!-- ── Plateforme (administrateur seulement) ──────────────────── -->
    <section id="plateforme" class="bo-int-section bo-int-section--platform" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon bo-int-section__icon--platform"><?= $icon('platform') ?></span>
            <div>
                <h2 class="bo-int-section__title">Plateforme <span class="bo-int-badge bo-int-badge--platform">Administrateur seulement</span></h2>
                <p class="bo-int-section__desc">Réglages communs à toutes les communautés, enregistrés en base : plus aucune clé Discord ni compte e-mail à mettre dans le .env du serveur. Les communautés ne voient pas cette section.</p>
            </div>
        </header>

        <div class="bo-int-card">
            <h3 class="bo-int-card__title"><?= $icon('discord') ?> Salons Discord de l’équipe Athena</h3>
            <p class="bo-int-card__desc">Pour l’équipe de développement, pas pour les communautés : chaque communauté choisit en plus son propre salon pour « Signalements du mod » et « Check de démarrage du mod » dans sa section Discord.</p>
            <form method="post" action="<?= $h(url('back-office/integrations/plateforme/discord')) ?>" class="bo-int-platform-discord">
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
            <form method="post" action="<?= $h(url('back-office/integrations/plateforme/discord/essai')) ?>" id="form-platform-discord-test" hidden>
                <?= \App\Core\Csrf::field() ?>
            </form>
        </div>

        <div class="bo-int-card" id="plateforme-emails">
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
            $mailFormAction = url('back-office/integrations/plateforme/emails');
            $mailFormTestAction = url('back-office/integrations/plateforme/emails/essai');
            $mailFormId = 'mail-platform';
            $mailFormWho = 'la plateforme';
            $mailFormFromPlaceholder = 'no-reply@athena.ttrd.fr';
            $mailFormNamePlaceholder = 'Athena';
            $mailFormTestTo = $adminEmail;
            require base_path('views/admin/organization/partials/integrations_mail_form.php');
            ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── Arma 3 / Overwatch ─────────────────────────────────────── -->
    <section id="arma-overwatch" class="bo-int-section bo-int-section--compact" data-int-section>
        <header class="bo-int-section__head">
            <span class="bo-int-section__icon"><?= $icon('game') ?></span>
            <div>
                <h2 class="bo-int-section__title">Arma 3 / Overwatch</h2>
                <p class="bo-int-section__desc">L’expérience en jeu (image de connexion, méthodes d’accès, fonctions Overwatch) se règle dans la configuration ATAK. Les opérateurs s’identifient avec leur compte Athena : la communauté n’est plus saisie dans le jeu.</p>
            </div>
            <a class="bo-int-btn" href="<?= $h(url('back-office/ressources/atak-config')) ?>#overwatch-game-experience">Ouvrir l’expérience en jeu <?= $icon('chevron') ?></a>
        </header>
    </section>

    </div>
</div>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-integrations.js')) ?>" defer></script>
