<?php
declare(strict_types=1);

/**
 * Intégrations de la communauté (back-office) : Discord, e-mails, transmissions terrain, jetons d'accès.
 * Les réglages de la plateforme sont dans l'administration (admin/system/integrations).
 *
 * Sommaire à sections, vue d'ensemble de l'état de chaque connexion, explications pas à pas.
 * Comportements : public/assets/js/back-office-integrations.js. Styles : back-office-integrations.css.
 *
 * @var list<array<string, mixed>> $integration_keys
 * @var string|null $new_integration_key_plain
 * @var list<string> $available_scopes
 * @var bool $api_keys_allowed
 * @var list<array{key:string, group:string, label:string, hint:string, default_mode:string}> $discord_events
 * @var array{default_url:string, never_saved:bool, events:array<string, array{mode:string, webhook:string, url:string}>} $discord_state
 * @var array<string, mixed> $discord_org salon de l'organisation (sans lien en clair)
 * @var list<array<string, mixed>> $discord_webhooks salons à part (sans lien en clair)
 * @var string $invite_template
 * @var array<string, string> $invite_variables
 * @var list<array{id:string,label:string,masked:string}> $discord_relays
 * @var bool $use_community_relay
 * @var bool $community_relay_ready
 * @var array<string, mixed> $mail_sender
 * @var array<string, array{label:string, host:string, port:int, encryption:string, username:string}> $mail_providers
 * @var string $admin_email
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
$discordEventState = is_array($discordState['events'] ?? null) ? $discordState['events'] : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$icon = static function (string $name): string {
    $paths = match ($name) {
        'overview' => '<path d="M4 13h6V4H4zM14 20h6v-9h-6zM14 4v4h6V4zM4 20h6v-3H4z"/>',
        'discord' => '<path d="M8 17c-2.5-.4-4-1.3-5-2.3.2-4 1.5-7.4 3.4-9.5A15 15 0 0110 4l.6 1.3a12 12 0 012.8 0L14 4a15 15 0 013.6 1.2c1.9 2.1 3.2 5.5 3.4 9.5-1 1-2.5 1.9-5 2.3l-1-1.8"/><path d="M8 15.5c2.6 1 5.4 1 8 0"/><circle cx="9" cy="11.5" r="1.2"/><circle cx="15" cy="11.5" r="1.2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'radio' => '<path d="M5 12a7 7 0 0114 0M8 12a4 4 0 018 0"/><circle cx="12" cy="12" r="1.3"/><path d="M12 13.5V21M9 21h6"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M14 9l2 2"/>',
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

// Discord : salon de l'organisation, salons à part (mode avancé), usages par groupe.
$discordOrg = is_array($discord_org ?? null) ? $discord_org : ['configured' => false, 'masked' => '', 'name' => '', 'channel' => '', 'last_test_at' => '', 'last_test_ok' => null, 'last_test_message' => ''];
$discordWebhooks = is_array($discord_webhooks ?? null) ? $discord_webhooks : [];
$inviteTemplate = (string) ($invite_template ?? '');
$inviteVariables = is_array($invite_variables ?? null) ? $invite_variables : [];
$communityName = (string) ($community_name ?? '');
$knownWebhooks = array_column($discordWebhooks, 'id');
$usageOn = static fn (string $k): bool => (string) ($discordEventState[$k]['mode'] ?? 'off') !== 'off';
$usageDest = static function (string $k) use ($discordEventState, $knownWebhooks): string {
    $row = is_array($discordEventState[$k] ?? null) ? $discordEventState[$k] : [];
    if ((string) ($row['mode'] ?? '') !== 'custom') {
        return 'organisation';
    }
    $wh = (string) ($row['webhook'] ?? '');
    if ($wh !== '' && in_array($wh, $knownWebhooks, true)) {
        return $wh;
    }

    return trim((string) ($row['url'] ?? '')) !== '' ? 'legacy' : 'organisation';
};
$discordGroups = [];
$publishedCount = 0;
$legacyCount = 0;
$whUses = [];
foreach ($discordEvents as $ev) {
    $g = (string) ($ev['group'] ?? 'Autres');
    $key = (string) ($ev['key'] ?? '');
    $discordGroups[$g][] = $ev;
    if ($usageOn($key)) {
        $publishedCount++;
        $whUses[$usageDest($key)][] = (string) ($ev['label'] ?? '');
    }
    if ($usageDest($key) === 'legacy') {
        $legacyCount++;
    }
}
$usesText = static function (array $labels): string {
    if ($labels === []) {
        return 'rien pour l’instant';
    }
    if (count($labels) <= 3) {
        return implode(', ', $labels);
    }

    return implode(', ', array_slice($labels, 0, 3)) . ' et ' . (count($labels) - 3) . ' autre' . (count($labels) - 3 > 1 ? 's' : '');
};
$discordLinked = !empty($discordOrg['configured']) || $discordWebhooks !== [] || $legacyCount > 0;
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
$tiles = [
    [
        'href' => '#relais-discord',
        'icon' => 'discord',
        'title' => 'Discord',
        'state' => $discordLinked ? ($publishedCount > 0 ? 'ok' : 'warn') : 'off',
        'value' => $discordLinked ? $publishedCount . ' / ' . count($discordEvents) : 'Non relié',
        'note' => $discordLinked ? 'usages publiés' : 'aucun salon relié',
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
$stateLabel = ['ok' => 'Actif', 'warn' => 'À vérifier', 'off' => 'Coupé', 'lock' => 'Formule'];

$nav = [
    ['href' => '#vue-ensemble', 'icon' => 'overview', 'label' => 'Vue d’ensemble'],
    ['href' => '#relais-discord', 'icon' => 'discord', 'label' => 'Discord', 'count' => $publishedCount],
    ['href' => '#emails-communaute', 'icon' => 'mail', 'label' => 'E-mails'],
    ['href' => '#relais-transmissions', 'icon' => 'radio', 'label' => 'Transmissions terrain', 'count' => count($sseRelays)],
    ['href' => '#jetons', 'icon' => 'key', 'label' => 'Jetons d’accès', 'count' => $apiKeysAllowed ? $activeCount : null],
];
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
                <p class="bo-int-concept__title"><?= $icon('send') ?> Un salon pour tout, ou un salon par usage</p>
                <p class="bo-int-concept__text">Le salon de l’organisation reçoit tout ce que vous activez. Pour envoyer un usage ailleurs (photos dans #renseignement, candidatures dans #recrutement), ajoutez ce salon dans le mode avancé et choisissez-le en face de l’usage.</p>
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
                <p class="bo-int-section__desc">Un seul salon suffit : collez son lien une fois, Athena y publie annonces, recrutement, effectifs, liens d’invitation… Vous coupez ensuite ce que vous ne voulez pas.</p>
            </div>
        </header>

        <details class="bo-int-tuto"<?= $discordLinked ? '' : ' open' ?>>
            <summary class="bo-int-guide__summary"><?= $icon('info') ?> Tutoriel : récupérer le lien d’un salon Discord (1 minute)</summary>
            <ol class="bo-int-tuto__steps">
                <li class="bo-int-tuto__step">
                    <svg class="bo-int-tuto__shot" viewBox="0 0 240 132" role="img" aria-label="Liste des salons Discord, roue dentée à côté du salon">
                        <rect width="240" height="132" rx="10" fill="#1e1f22"/><rect x="8" y="8" width="96" height="116" rx="6" fill="#2b2d31"/>
                        <text x="16" y="26" fill="#949ba4" font-size="8" font-family="sans-serif">SALONS TEXTUELS</text>
                        <text x="18" y="44" fill="#949ba4" font-size="10" font-family="sans-serif"># général</text>
                        <rect x="12" y="52" width="88" height="18" rx="4" fill="#404249"/>
                        <text x="18" y="65" fill="#f2f3f5" font-size="10" font-family="sans-serif"># annonces</text>
                        <circle cx="90" cy="61" r="8" fill="none" stroke="#34d399" stroke-width="2"/><circle cx="90" cy="61" r="3.2" fill="none" stroke="#dbdee1" stroke-width="1.4"/>
                        <text x="18" y="86" fill="#949ba4" font-size="10" font-family="sans-serif"># recrutement</text>
                        <rect x="112" y="8" width="120" height="116" rx="6" fill="#313338"/>
                        <text x="122" y="70" fill="#dbdee1" font-size="10" font-family="sans-serif">Modifier le salon</text>
                        <path d="M98 61 H118" stroke="#34d399" stroke-width="1.5" stroke-dasharray="3 2"/>
                    </svg>
                    <p><b>1.</b> Survolez le salon où Athena doit écrire et cliquez sur la <b>roue dentée</b> (« Modifier le salon »).</p>
                </li>
                <li class="bo-int-tuto__step">
                    <svg class="bo-int-tuto__shot" viewBox="0 0 240 132" role="img" aria-label="Menu des paramètres du salon, Intégrations en surbrillance">
                        <rect width="240" height="132" rx="10" fill="#1e1f22"/><rect x="8" y="8" width="96" height="116" rx="6" fill="#2b2d31"/>
                        <text x="16" y="28" fill="#949ba4" font-size="10" font-family="sans-serif">Vue d’ensemble</text>
                        <text x="16" y="46" fill="#949ba4" font-size="10" font-family="sans-serif">Permissions</text>
                        <text x="16" y="64" fill="#949ba4" font-size="10" font-family="sans-serif">Invitations</text>
                        <rect x="11" y="72" width="90" height="18" rx="4" fill="#404249" stroke="#34d399" stroke-width="1.5"/>
                        <text x="16" y="85" fill="#f2f3f5" font-size="10" font-family="sans-serif">Intégrations</text>
                        <rect x="112" y="8" width="120" height="116" rx="6" fill="#313338"/>
                        <text x="122" y="28" fill="#f2f3f5" font-size="11" font-weight="700" font-family="sans-serif">Intégrations</text>
                        <rect x="122" y="40" width="100" height="24" rx="4" fill="#2b2d31"/><text x="130" y="56" fill="#dbdee1" font-size="10" font-family="sans-serif">Webhooks</text>
                    </svg>
                    <p><b>2.</b> Dans le menu du salon, ouvrez <b>Intégrations</b>, puis <b>Webhooks</b>.</p>
                </li>
                <li class="bo-int-tuto__step">
                    <svg class="bo-int-tuto__shot" viewBox="0 0 240 132" role="img" aria-label="Bouton Nouveau webhook">
                        <rect width="240" height="132" rx="10" fill="#1e1f22"/><rect x="8" y="8" width="224" height="116" rx="6" fill="#313338"/>
                        <text x="20" y="30" fill="#f2f3f5" font-size="11" font-weight="700" font-family="sans-serif">Webhooks</text>
                        <text x="20" y="48" fill="#949ba4" font-size="9" font-family="sans-serif">Publiez des messages depuis d’autres services.</text>
                        <rect x="20" y="62" width="96" height="26" rx="4" fill="#5865f2" stroke="#34d399" stroke-width="2"/>
                        <text x="30" y="79" fill="#ffffff" font-size="10" font-weight="700" font-family="sans-serif">Nouveau webhook</text>
                    </svg>
                    <p><b>3.</b> Cliquez sur <b>Nouveau webhook</b>. Donnez-lui un nom (« Athena ») et vérifiez le salon choisi.</p>
                </li>
                <li class="bo-int-tuto__step">
                    <svg class="bo-int-tuto__shot" viewBox="0 0 240 132" role="img" aria-label="Fiche du webhook avec le bouton Copier l’URL du webhook">
                        <rect width="240" height="132" rx="10" fill="#1e1f22"/><rect x="8" y="8" width="224" height="116" rx="6" fill="#313338"/>
                        <circle cx="34" cy="38" r="14" fill="#34d399"/><text x="28" y="43" fill="#052e1c" font-size="13" font-weight="900" font-family="sans-serif">A</text>
                        <text x="56" y="34" fill="#f2f3f5" font-size="11" font-weight="700" font-family="sans-serif">Athena</text>
                        <text x="56" y="48" fill="#949ba4" font-size="9" font-family="sans-serif"># annonces</text>
                        <rect x="20" y="70" width="128" height="26" rx="4" fill="#4e5058" stroke="#34d399" stroke-width="2"/>
                        <text x="28" y="87" fill="#ffffff" font-size="10" font-weight="700" font-family="sans-serif">Copier l’URL du webhook</text>
                    </svg>
                    <p><b>4.</b> Cliquez sur le webhook créé, puis <b>Copier l’URL du webhook</b>. Collez-la ci-dessous et cliquez sur <b>Tester</b>.</p>
                </li>
            </ol>
            <div class="bo-int-anatomy" aria-label="Forme d’un lien webhook">
                <code><span class="bo-int-anatomy__base">https://discord.com/api/webhooks/</span><span class="bo-int-anatomy__id">1290000000000000001</span>/<span class="bo-int-anatomy__token">AbCdEf…</span></code>
                <span class="bo-int-anatomy__legend"><span class="bo-int-anatomy__dot bo-int-anatomy__dot--id"></span>numéro du webhook <span class="bo-int-anatomy__dot bo-int-anatomy__dot--token"></span>partie secrète</span>
            </div>
            <p class="bo-int-guide__note"><?= $icon('warn') ?> Le lien du salon (« Copier le lien ») ou son identifiant ne suffisent pas : seul le lien du webhook permet à Athena d’écrire. Quiconque a ce lien peut écrire dans le salon : Athena le garde chiffré et ne le réaffiche jamais.</p>
        </details>

        <!-- Salon de l'organisation -->
        <div class="bo-int-wh bo-int-wh--org" id="discord-organisation">
            <div class="bo-int-wh__head">
                <div>
                    <h3 class="bo-int-wh__title">Salon de l’organisation</h3>
                    <p class="bo-int-wh__sub">Reçoit tous les usages qui n’ont pas de salon à part.</p>
                </div>
                <span class="bo-int-badge bo-int-badge--<?= $discordOrg['configured'] ? 'ok' : 'off' ?>"><?= $discordOrg['configured'] ? 'Relié' : 'Non relié' ?></span>
            </div>
            <?php if ($discordOrg['configured']): ?>
                <p class="bo-int-wh__saved">
                    <span class="bo-int-mono"><?= $h($discordOrg['masked']) ?></span>
                    <?php if ($discordOrg['channel'] !== '' || $discordOrg['name'] !== ''): ?> · <?= $h(trim($discordOrg['name'] . ' ' . $discordOrg['channel'])) ?><?php endif; ?>
                </p>
                <p class="bo-int-wh__uses">Utilisé pour : <?= $h($usesText($whUses[\App\Services\Integrations\DiscordWebhookDirectory::ORGANISATION] ?? [])) ?></p>
                <?php if ($discordOrg['last_test_at'] !== ''): ?>
                    <p class="bo-int-wh__test is-<?= $discordOrg['last_test_ok'] ? 'ok' : 'bad' ?>">Dernier essai le <?= $h($fmtDt($discordOrg['last_test_at'])) ?> : <?= $h($discordOrg['last_test_ok'] ? 'message reçu par Discord' : $discordOrg['last_test_message']) ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($discordOrg['configured']): ?>
            <form method="post" action="<?= $h(url('back-office/integrations/discord/nom-salon')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="webhook_id" value="organisation">
                <button type="submit" class="bo-int-btn bo-int-btn--sm"><?= $h(__('events.channel_refresh')) ?></button>
            </form>
            <?php endif; ?>
            <p class="bo-int-wh__sub"><?= $h(__('events.channel_help')) ?></p>
            <form method="post" action="<?= $h(url('back-office/integrations/discord/organisation')) ?>" class="bo-int-wh__form" data-wh-form>
                <?= \App\Core\Csrf::field() ?>
                <label class="bo-int-field bo-int-wh__url">
                    <span class="bo-int-field__label"><?= $discordOrg['configured'] ? 'Remplacer le lien (laisser vide pour garder l’actuel)' : 'Lien du webhook' ?></span>
                    <input type="text" inputmode="url" autocomplete="off" spellcheck="false" name="url" maxlength="500" class="bo-int-input bo-int-mono" value="" placeholder="https://discord.com/api/webhooks/…" data-webhook-input<?= $discordOrg['configured'] ? '' : ' required' ?>>
                    <span class="bo-int-check-msg" data-webhook-msg aria-live="polite"></span>
                </label>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Nom <em>pour vous repérer</em></span>
                    <input type="text" name="name" maxlength="80" class="bo-int-input" value="<?= $h($discordOrg['name']) ?>" placeholder="Discord de la communauté">
                </label>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Salon</span>
                    <input type="text" name="channel" maxlength="60" class="bo-int-input" value="<?= $h($discordOrg['channel']) ?>" placeholder="#annonces">
                </label>
                <div class="bo-int-wh__acts">
                    <button type="submit" class="bo-int-btn bo-int-btn--solid">Enregistrer</button>
                    <button type="button" class="bo-int-btn" data-wh-test="<?= $h(\App\Services\Integrations\DiscordWebhookDirectory::ORGANISATION) ?>"><?= $icon('send') ?><span>Tester</span></button>
                    <?php if ($discordOrg['configured']): ?>
                        <button type="submit" name="clear" value="1" class="bo-int-link-btn bo-int-link-btn--danger" formnovalidate onclick="return confirm('Retirer le salon de l’organisation ? Les usages qui en dépendent ne publieront plus.')">Retirer ce salon</button>
                    <?php endif; ?>
                </div>
                <p class="bo-int-wh__result" data-wh-result role="status" aria-live="polite"></p>
            </form>
        </div>

        <p class="bo-int-guide__note"><?= $icon('send') ?> Pour présenter votre communauté sur le Discord Athena (salon des communautés), voyez <a href="<?= $h(url('back-office/community/discord')) ?>">Annoncer sur Discord</a>.</p>

        <!-- Usages -->
        <form method="post" action="<?= $h(url('back-office/integrations/discord')) ?>" id="discord-usages" class="bo-int-discord" data-discord-form>
            <?= \App\Core\Csrf::field() ?>
            <div class="bo-int-usages__head">
                <div>
                    <h3 class="bo-int-wh__title">Ce qu’Athena publie</h3>
                    <p class="bo-int-wh__sub">Activé = publié dans le salon de l’organisation<?= $discordWebhooks !== [] ? ', ou dans le salon à part choisi' : '' ?>.</p>
                </div>
                <div class="bo-int-usages__bulk">
                    <button type="button" class="bo-int-btn bo-int-btn--sm" data-usage-all="on">Tout publier</button>
                    <button type="button" class="bo-int-btn bo-int-btn--sm" data-usage-all="off">Tout couper</button>
                </div>
            </div>

            <div class="bo-int-toolbar">
                <label class="bo-int-search">
                    <?= $icon('search') ?>
                    <span class="bo-int-sr">Chercher un usage</span>
                    <input type="search" class="bo-int-search__input" placeholder="Chercher un usage (photo, candidature, invitation…)" data-discord-search>
                </label>
                <div class="bo-int-filter" role="group" aria-label="Filtrer les usages">
                    <button type="button" class="bo-int-filter__btn is-active" data-discord-filter="all">Tous</button>
                    <button type="button" class="bo-int-filter__btn" data-discord-filter="on">Publiés <span data-discord-count-on><?= (int) $publishedCount ?></span></button>
                    <button type="button" class="bo-int-filter__btn" data-discord-filter="off">Coupés</button>
                </div>
            </div>
            <p class="bo-int-empty" data-discord-empty hidden>Aucun usage ne correspond.</p>

            <?php foreach ($discordGroups as $groupLabel => $groupEvents): ?>
                <?php
                $groupOn = 0;
                foreach ($groupEvents as $gev) {
                    if ($usageOn((string) ($gev['key'] ?? ''))) {
                        $groupOn++;
                    }
                }
                ?>
                <details class="bo-int-group" data-discord-group<?= $groupLabel === 'Téléphone ATAK' ? ' id="relais-discord-atak"' : '' ?><?= $groupOn > 0 ? ' open' : '' ?>>
                    <summary class="bo-int-group__head">
                        <span class="bo-int-group__caret" aria-hidden="true"><?= $icon('chevron') ?></span>
                        <span class="bo-int-group__emoji" aria-hidden="true"><?= $h($groupIcons[$groupLabel] ?? '•') ?></span>
                        <h3 class="bo-int-group__title"><?= $h((string) $groupLabel) ?></h3>
                        <span class="bo-int-group__count"><span data-group-on><?= $groupOn ?></span> / <?= count($groupEvents) ?> publiés</span>
                        <span class="bo-int-group__bulk">
                            <button type="button" class="bo-int-link-btn" data-group-set="on">Tout publier</button>
                            <button type="button" class="bo-int-link-btn" data-group-set="off">Tout couper</button>
                        </span>
                    </summary>
                    <?php if ($groupLabel === 'Téléphone ATAK'): ?>
                        <p class="bo-int-group__note">Le lien du salon reste sur Athena : il n’est jamais envoyé au jeu. L’<strong>App Discord du téléphone</strong> ne fonctionne qu’une fois cet usage activé.</p>
                    <?php endif; ?>
                    <?php foreach ($groupEvents as $ev): ?>
                        <?php
                        $ek = (string) ($ev['key'] ?? '');
                        $on = $usageOn($ek);
                        $dest = $usageDest($ek);
                        $search = mb_strtolower((string) ($ev['label'] ?? '') . ' ' . (string) ($ev['hint'] ?? '') . ' ' . (string) $groupLabel);
                        ?>
                        <div class="bo-int-event" data-discord-event data-mode="<?= $on ? 'on' : 'off' ?>" data-search="<?= $h($search) ?>">
                            <label class="bo-int-switch">
                                <input type="checkbox" name="discord_event[<?= $h($ek) ?>][on]" value="1" data-usage-on <?= $on ? 'checked' : '' ?>>
                                <span class="bo-int-switch__track" aria-hidden="true"></span>
                                <span class="bo-int-event__text">
                                    <span class="bo-int-event__name"><?= $h((string) ($ev['label'] ?? '')) ?></span>
                                    <span class="bo-int-event__hint"><?= $h((string) ($ev['hint'] ?? '')) ?></span>
                                </span>
                            </label>
                            <?php if ($discordWebhooks !== [] || $dest === 'legacy'): ?>
                                <label class="bo-int-event__dest">
                                    <span class="bo-int-sr">Salon pour « <?= $h((string) ($ev['label'] ?? '')) ?> »</span>
                                    <select name="discord_event[<?= $h($ek) ?>][dest]" class="bo-int-select">
                                        <option value="organisation"<?= $dest === 'organisation' ? ' selected' : '' ?>><?= $h(__('events.organisation') . ($discordOrg['channel'] !== '' ? ' (' . $discordOrg['channel'] . ')' : '')) ?></option>
                                        <?php foreach ($discordWebhooks as $wh): ?>
                                            <option value="<?= $h($wh['id']) ?>"<?= $dest === $wh['id'] ? ' selected' : '' ?>><?= $h($wh['name'] . ($wh['channel'] !== '' ? ' (' . $wh['channel'] . ')' : '')) ?></option>
                                        <?php endforeach; ?>
                                        <?php if ($dest === 'legacy'): ?>
                                            <option value="legacy" selected>Salon à part enregistré avant</option>
                                        <?php endif; ?>
                                    </select>
                                </label>
                            <?php else: ?>
                                <input type="hidden" name="discord_event[<?= $h($ek) ?>][dest]" value="organisation">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>

            <div class="bo-int-savebar" data-savebar>
                <span class="bo-int-savebar__state" data-savebar-state>Tout est enregistré.</span>
                <button type="reset" class="bo-int-btn" data-savebar-reset disabled>Annuler</button>
                <button type="submit" class="bo-int-btn bo-int-btn--solid">Enregistrer les usages</button>
            </div>
        </form>

        <!-- Mode avancé : salons à part -->
        <details class="bo-int-advanced" id="discord-avance"<?= $discordWebhooks !== [] ? ' open' : '' ?>>
            <summary class="bo-int-guide__summary"><?= $icon('link') ?> Mode avancé : envoyer certains usages dans d’autres salons</summary>
            <p class="bo-int-wh__sub">Par exemple les candidatures dans #recrutement et les photos dans #renseignement. Ajoutez le salon ici, puis choisissez-le en face de l’usage dans la liste ci-dessus. Un salon retiré renvoie ses usages vers le salon de l’organisation.</p>
            <?php if ($discordWebhooks !== []): ?>
            <ul class="bo-int-wh-list">
                <?php foreach ($discordWebhooks as $wh): ?>
                <li class="bo-int-wh">
                    <div class="bo-int-wh__head">
                        <div>
                            <h4 class="bo-int-wh__title"><?= $h($wh['name']) ?><?= $wh['channel'] !== '' ? ' <span class="bo-int-wh__chan">' . $h($wh['channel']) . '</span>' : '' ?></h4>
                            <p class="bo-int-wh__saved bo-int-mono"><?= $h($wh['masked']) ?></p>
                        </div>
                        <div class="bo-int-wh__acts">
                            <button type="button" class="bo-int-btn bo-int-btn--sm" data-wh-test="<?= $h($wh['id']) ?>"><?= $icon('send') ?><span>Tester</span></button>
                            <form method="post" action="<?= $h(url('back-office/integrations/discord/nom-salon')) ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="webhook_id" value="<?= $h($wh['id']) ?>">
                                <button type="submit" class="bo-int-btn bo-int-btn--sm"><?= $h(__('events.channel_refresh')) ?></button>
                            </form>
                            <form method="post" action="<?= $h(url('back-office/integrations/discord/salons/' . $wh['id'] . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce salon ? Ses usages passeront sur le salon de l’organisation.')">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="bo-int-link-btn bo-int-link-btn--danger">Retirer</button>
                            </form>
                        </div>
                    </div>
                    <p class="bo-int-wh__uses">Utilisé pour : <?= $h($usesText($whUses[$wh['id']] ?? [])) ?></p>
                    <?php if ($wh['last_test_at'] !== ''): ?>
                        <p class="bo-int-wh__test is-<?= $wh['last_test_ok'] ? 'ok' : 'bad' ?>">Dernier essai le <?= $h($fmtDt($wh['last_test_at'])) ?> : <?= $h($wh['last_test_ok'] ? 'message reçu par Discord' : $wh['last_test_message']) ?></p>
                    <?php endif; ?>
                    <p class="bo-int-wh__result" data-wh-result role="status" aria-live="polite"></p>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= $h(url('back-office/integrations/discord/salons')) ?>" class="bo-int-wh bo-int-wh__form bo-int-wh--new" data-wh-form>
                <?= \App\Core\Csrf::field() ?>
                <h4 class="bo-int-wh__title">Ajouter un salon</h4>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Nom</span>
                    <input type="text" name="name" maxlength="80" class="bo-int-input" required placeholder="Recrutement">
                </label>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Salon</span>
                    <input type="text" name="channel" maxlength="60" class="bo-int-input" placeholder="#recrutement">
                </label>
                <label class="bo-int-field bo-int-wh__url">
                    <span class="bo-int-field__label">Lien du webhook</span>
                    <input type="text" inputmode="url" autocomplete="off" spellcheck="false" name="url" maxlength="500" class="bo-int-input bo-int-mono" required placeholder="https://discord.com/api/webhooks/…" data-webhook-input>
                    <span class="bo-int-check-msg" data-webhook-msg aria-live="polite"></span>
                </label>
                <div class="bo-int-wh__acts">
                    <button type="submit" class="bo-int-btn bo-int-btn--solid">Ajouter le salon</button>
                    <button type="button" class="bo-int-btn" data-wh-test="nouveau"><?= $icon('send') ?><span>Tester ce lien</span></button>
                </div>
                <p class="bo-int-wh__result" data-wh-result role="status" aria-live="polite"></p>
            </form>
        </details>

        <!-- Message des liens d'invitation -->
        <details class="bo-int-advanced" id="discord-message">
            <summary class="bo-int-guide__summary"><?= $icon('mail') ?> Message des liens « compléter son profil »</summary>
            <p class="bo-int-wh__sub">Texte publié quand un gestionnaire clique sur « Envoyer sur Discord » depuis les effectifs. La personne est mentionnée si son identifiant Discord est connu ; une carte rappelle la communauté, le grade, l’unité, le lien et l’expiration.</p>
            <form method="post" action="<?= $h(url('back-office/integrations/discord/modele-invitation')) ?>" class="bo-int-tpl" data-tpl-form data-community="<?= $h($communityName !== '' ? $communityName : 'Votre communauté') ?>">
                <?= \App\Core\Csrf::field() ?>
                <label class="bo-int-field">
                    <span class="bo-int-field__label">Message</span>
                    <textarea name="template" rows="4" maxlength="<?= \App\Support\ProfileInviteDiscordMessage::MAX_TEMPLATE ?>" class="bo-int-input" data-tpl-input><?= $h($inviteTemplate) ?></textarea>
                </label>
                <div class="bo-int-tpl__vars" role="group" aria-label="Variables à insérer">
                    <?php foreach ($inviteVariables as $var => $varHint): ?>
                        <button type="button" class="bo-int-chip" data-tpl-var="<?= $h($var) ?>" title="<?= $h($varHint) ?>">{<?= $h($var) ?>}</button>
                    <?php endforeach; ?>
                </div>
                <div class="bo-int-tpl__preview" aria-live="polite">
                    <span class="bo-int-tpl__avatar" aria-hidden="true">A</span>
                    <div>
                        <p class="bo-int-tpl__bot">Athena <span>APPLI</span></p>
                        <p class="bo-int-tpl__text" data-tpl-preview></p>
                        <div class="bo-int-tpl__embed">
                            <p class="bo-int-tpl__embed-title">Compléter mon profil — <?= $h($communityName !== '' ? $communityName : 'Votre communauté') ?></p>
                            <p>Grade : Caporal · Unité : 1re section · SOAR-B-1 · Expire dans 2 jours</p>
                        </div>
                    </div>
                </div>
                <div class="bo-int-wh__acts">
                    <button type="submit" class="bo-int-btn bo-int-btn--solid">Enregistrer le message</button>
                    <button type="submit" name="reset" value="1" class="bo-int-link-btn">Remettre le message par défaut</button>
                </div>
            </form>
        </details>
        <form method="post" action="<?= $h(url('back-office/integrations/discord/tester')) ?>" id="form-wh-test" hidden data-wh-test-form>
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
