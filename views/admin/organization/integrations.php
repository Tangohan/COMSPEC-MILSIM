<?php
declare(strict_types=1);

/**
 * Centre d’intégrations — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. Les jetons restent présentés
 * en fiches : chacun porte son propre formulaire de réglage (nom, quota, portées),
 * ce qu’un tableau ne saurait pas contenir.
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
$discordDefaultUrl = (string) ($discordState['default_url'] ?? '');
$discordEventState = is_array($discordState['events'] ?? null) ? $discordState['events'] : [];
$discordGroups = [];
foreach ($discordEvents as $ev) {
    $g = (string) ($ev['group'] ?? 'Autres');
    $discordGroups[$g][] = $ev;
}

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

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

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
?>
<?php if ($flashError): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashError) ?></p>
<?php endif; ?>
<?php if ($flashSuccess): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
<?php endif; ?>

<?php if ($newKeyPlain): ?>
<div class="ath-secret ath-rise" role="alert">
    <p class="ath-secret__title">Copiez ce jeton maintenant : il ne sera plus affiché en clair.</p>
    <code class="ath-secret__value"><?= $h((string) $newKeyPlain) ?></code>
    <p class="ath-secret__note">Conservez-le dans un gestionnaire de secrets, jamais en clair dans du code source.</p>
</div>
<?php endif; ?>

<div class="ath-note">
    <p class="ath-note__title">Fonctionnement</p>
    <p class="ath-note__text">
        Reliez Discord à votre communauté : un salon commun pour ce qui n’a pas de salon à part,
        puis un choix pour chaque type d’événement (candidatures, effectifs, opérations, photos Quick Picture…).
        Les transmissions terrain peuvent viser des salons supplémentaires. Les jetons d’accès servent aux outils qui viennent lire le calendrier.
    </p>
</div>

<section id="arma-overwatch" class="ath-card ath-rise" style="padding:18px 20px;margin-bottom:22px;">
    <h2 class="ath-section-title" style="margin-top:0;">Arma 3 / Overwatch</h2>
    <p class="ath-item__meta" style="margin:0 0 12px;">
        L’expérience en jeu (image de connexion, méthodes d’accès, fonctions Overwatch) se règle dans la configuration ATAK.
        Les opérateurs s’identifient avec leur compte Athena : la communauté n’est plus saisie dans le jeu.
    </p>
    <a class="ath-btn ath-btn--solid" href="<?= $h(url('admin/atak-config')) ?>#overwatch-game-experience">Ouvrir l’expérience en jeu</a>
</section>

<section id="relais-discord" class="ath-card ath-rise" style="padding:18px 20px;margin-bottom:22px;">
    <h2 class="ath-section-title" style="margin-top:0;">Relais Discord</h2>
    <div class="ath-discord-howto">
        <p class="ath-discord-howto__title">Comment choisir le salon</p>
        <ol class="ath-discord-howto__list">
            <li>Dans Discord, ouvrez les paramètres du salon → Intégrations → créez un relais, puis copiez le lien.</li>
            <li>Collez ce lien dans <strong>Salon commun</strong> : c’est le salon utilisé dès qu’un événement est réglé sur « Salon commun ».</li>
            <li>Pour un type d’événement, trois choix : <strong>Ne pas publier</strong>, <strong>Salon commun</strong>, ou <strong>Autre salon</strong> (un salon différent, avec son propre lien).</li>
        </ol>
        <p class="ath-discord-howto__note">Si vous choisissez « Autre salon », le message n’ira plus dans le salon commun : uniquement dans celui-là.</p>
    </div>
    <form method="post" action="<?= $h(url('back-office/integrations/discord')) ?>" id="form-relais-discord">
        <?= \App\Core\Csrf::field() ?>
        <label class="ath-field">
            <span class="ath-field__label">Salon commun</span>
            <input type="url" name="discord_webhook_url" maxlength="500" class="ath-field__input" value="<?= $h($discordDefaultUrl) ?>" placeholder="Collez le lien copié depuis Discord">
            <span class="ath-field__help">Tous les événements réglés sur « Salon commun » partent ici. Laissez vide si vous n’utilisez que des salons à part.</span>
        </label>
        <?php foreach ($discordGroups as $groupLabel => $groupEvents): ?>
            <h3 class="ath-form__title" style="margin:18px 0 8px;"><?= $h((string) $groupLabel) ?></h3>
            <div class="ath-stack">
                <?php foreach ($groupEvents as $ev): ?>
                    <?php
                    $ek = (string) ($ev['key'] ?? '');
                    $st = is_array($discordEventState[$ek] ?? null) ? $discordEventState[$ek] : [];
                    $mode = (string) ($st['mode'] ?? ($ev['default_mode'] ?? 'off'));
                    $dedicatedUrl = (string) ($st['url'] ?? '');
                    $destHint = match ($mode) {
                        'custom' => 'Publié dans un salon à part.',
                        'default' => 'Publié dans le salon commun.',
                        default => 'Rien n’est envoyé sur Discord.',
                    };
                    ?>
                    <article class="ath-item ath-discord-event" data-discord-event>
                        <p class="ath-item__name"><?= $h((string) ($ev['label'] ?? '')) ?></p>
                        <p class="ath-item__meta"><?= $h((string) ($ev['hint'] ?? '')) ?></p>
                        <p class="ath-discord-event__status" data-discord-status><?= $h($destHint) ?></p>
                        <div class="ath-form__grid" style="margin-top:10px;">
                            <label class="ath-field">
                                <span class="ath-field__label">Où publier</span>
                                <select name="discord_event[<?= $h($ek) ?>][mode]" class="ath-field__input js-discord-mode">
                                    <option value="off" <?= $mode === 'off' ? 'selected' : '' ?>>Ne pas publier</option>
                                    <option value="default" <?= $mode === 'default' ? 'selected' : '' ?>>Salon commun</option>
                                    <option value="custom" <?= $mode === 'custom' ? 'selected' : '' ?>>Autre salon</option>
                                </select>
                            </label>
                            <label class="ath-field ath-discord-custom js-discord-custom"<?= $mode === 'custom' ? '' : ' hidden' ?>>
                                <span class="ath-field__label">Lien de cet autre salon</span>
                                <input type="url" name="discord_event[<?= $h($ek) ?>][url]" maxlength="500" class="ath-field__input" value="<?= $h($dedicatedUrl) ?>" placeholder="Collez le lien copié depuis Discord">
                                <span class="ath-field__help">Uniquement si vous avez choisi « Autre salon ».</span>
                            </label>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div class="ath-form__actions" style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les relais</button>
        </div>
    </form>
    <form method="post" action="<?= $h(url('back-office/integrations/discord/essai')) ?>" class="ath-discord-test" style="margin-top:14px;">
        <?= \App\Core\Csrf::field() ?>
        <label class="ath-field">
            <span class="ath-field__label">Vérifier un salon</span>
            <select name="event_key" class="ath-field__input">
                <?php foreach ($discordEvents as $ev): ?>
                    <?php $ek = (string) ($ev['key'] ?? ''); ?>
                    <option value="<?= $h($ek) ?>" <?= $ek === 'announcements' ? 'selected' : '' ?>><?= $h((string) ($ev['label'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="ath-field__help">Un court message part dans le salon actuellement choisi pour cet événement (pensez à enregistrer d’abord).</span>
        </label>
        <div class="ath-form__actions" style="margin-top:10px;">
            <button type="submit" class="ath-btn">Envoyer un message d’essai</button>
        </div>
    </form>
</section>

<section id="relais-transmissions" class="ath-card ath-rise" style="padding:18px 20px;margin-bottom:22px;">
    <h2 class="ath-section-title" style="margin-top:0;">Transmissions terrain</h2>
    <p class="ath-item__meta" style="margin:0 0 14px;">
        Salons supplémentaires pour le journal SSE. Vous pouvez aussi les gérer depuis le bureau renseignement.
    </p>
    <form method="post" action="<?= $h(url('back-office/integrations/sse-relais/communaute')) ?>" style="margin-bottom:14px;">
        <?= \App\Core\Csrf::field() ?>
        <label class="ath-check">
            <input type="hidden" name="use_community_relay" value="0">
            <input type="checkbox" name="use_community_relay" value="1" <?= $useCommunityRelay ? 'checked' : '' ?> <?= $communityRelayReady ? '' : 'disabled' ?>>
            <span>
                Publier aussi sur le salon commun
                <?php if (!$communityRelayReady): ?>
                    <small> — renseignez d’abord le salon commun ci-dessus, puis enregistrez.</small>
                <?php endif; ?>
            </span>
        </label>
        <div class="ath-form__actions" style="margin-top:10px;">
            <button type="submit" class="ath-btn" <?= $communityRelayReady ? '' : 'disabled' ?>>Enregistrer</button>
        </div>
    </form>
    <?php if ($sseRelays !== []): ?>
        <div class="ath-stack" style="margin-bottom:14px;">
            <?php foreach ($sseRelays as $relay): ?>
                <article class="ath-item">
                    <p class="ath-item__name"><?= $h((string) ($relay['label'] ?? '')) ?></p>
                    <p class="ath-item__meta"><?= $h((string) ($relay['masked'] ?? '')) ?></p>
                    <div class="ath-item__actions">
                        <form method="post" action="<?= $h(url('back-office/integrations/sse-relais/' . rawurlencode((string) ($relay['id'] ?? '')) . '/essai')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="ath-btn">Essai</button>
                        </form>
                        <form method="post" action="<?= $h(url('back-office/integrations/sse-relais/' . rawurlencode((string) ($relay['id'] ?? '')) . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce salon du journal des transmissions ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="ath-row-action ath-row-action--danger">Retirer</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="<?= $h(url('back-office/integrations/sse-relais')) ?>" class="ath-form">
        <?= \App\Core\Csrf::field() ?>
        <div class="ath-form__grid">
            <label class="ath-field">
                <span class="ath-field__label">Intitulé du salon</span>
                <input type="text" name="label" maxlength="80" class="ath-field__input" placeholder="Renseignement, TOC…">
            </label>
            <label class="ath-field">
            <span class="ath-field__label">Lien du salon Discord</span>
            <input type="url" name="discord_url" required maxlength="500" class="ath-field__input" placeholder="Collez le lien copié depuis Discord">
            </label>
        </div>
        <div class="ath-form__actions">
            <button type="submit" class="ath-btn ath-btn--solid">Ajouter un salon</button>
        </div>
    </form>
</section>


<?php if ($apiKeysAllowed): ?>
<?php
$athKpis = [
    [
        'label' => 'JETONS ACTIFS',
        'value' => (string) $activeCount,
        'delta' => '',
        'tone' => $activeCount > 0 ? '#0b8a5c' : '#8c979b',
        'pct' => count($keys) > 0 ? (string) (int) round($activeCount / max(1, count($keys)) * 100) . '%' : '0%',
        'note' => 'outils raccordés',
    ],
    [
        'label' => 'RÉVOQUÉS',
        'value' => (string) $revokedCount,
        'delta' => '',
        'tone' => $revokedCount === 0 ? '#0b8a5c' : '#64748b',
        'pct' => count($keys) > 0 ? (string) (int) round($revokedCount / max(1, count($keys)) * 100) . '%' : '0%',
        'note' => 'définitivement hors service',
    ],
    [
        'label' => 'REQUÊTES DU JOUR',
        'value' => number_format($todayTotal, 0, ',', ' '),
        'delta' => '',
        'tone' => $usageRatio < 80 ? '#0b8a5c' : ($usageRatio < 95 ? '#c98a12' : '#c72e2e'),
        'pct' => (string) max(0, min(100, $usageRatio)) . '%',
        'note' => 'sur ' . number_format($quotaTotal, 0, ',', ' ') . ' autorisées',
    ],
    [
        'label' => 'CHARGE DU QUOTA',
        'value' => $quotaTotal > 0 ? $usageRatio . ' %' : '—',
        'delta' => '',
        'tone' => $usageRatio < 80 ? '#0b8a5c' : ($usageRatio < 95 ? '#c98a12' : '#c72e2e'),
        'pct' => (string) max(0, min(100, $usageRatio)) . '%',
        'note' => 'tous jetons actifs confondus',
    ],
];
require base_path('views/partials/ath_kpis.php');
?>

<form method="post" action="<?= $h(url('back-office/integrations/api-keys')) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Nouveau jeton d’accès</span>
        <span class="ath-form__hint">Le secret n’est affiché qu’une seule fois, juste après la création.</span>
    </div>
    <?= \App\Core\Csrf::field() ?>
    <div class="ath-form__grid">
        <label class="ath-field">
            <span class="ath-field__label">Nom du service</span>
            <input type="text" name="name" maxlength="120" required placeholder="Site vitrine, outil logistique…" class="ath-field__input">
        </label>
        <label class="ath-field">
            <span class="ath-field__label">Quota par jour</span>
            <input type="number" name="quota_per_day" min="100" max="500000" value="10000" class="ath-field__input">
            <span class="ath-field__help">Entre 100 et 500 000 requêtes.</span>
        </label>
    </div>
    <fieldset style="border:0;margin:14px 0 0;padding:0;">
        <legend class="ath-field__label" style="padding:0;margin-bottom:7px;">Types de données autorisés</legend>
        <div class="ath-check-grid">
            <?php foreach ($availableScopes as $scope): ?>
            <label class="ath-check">
                <input type="checkbox" name="scopes[]" value="<?= $h((string) $scope) ?>" checked>
                <span><?= $h($scopeLabelFr((string) $scope)) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <div class="ath-form__actions">
        <button type="submit" class="ath-btn ath-btn--solid">Créer le jeton</button>
    </div>
</form>

<h2 class="ath-section-title">Jetons existants</h2>

<?php if ($keys === []): ?>
<div class="ath-card" style="padding:18px 20px;">
    <p class="ath-item__meta" style="margin:0;">Aucun jeton pour l’instant : créez-en un avec le formulaire ci-dessus.</p>
</div>
<?php else: ?>
<div class="ath-stack">
    <?php foreach ($keys as $k): ?>
        <?php
        $keyId = (int) ($k['id'] ?? 0);
        $quota = max(1, (int) ($k['quota_per_day'] ?? 10000));
        $today = max(0, (int) ($k['today_request_count'] ?? 0));
        $ratio = min(100, (int) round($today / $quota * 100));
        $isRevoked = !empty($k['revoked_at']);
        $scopes = $decodeScopes($k['scopes_json'] ?? '');
        $fillClass = $ratio < 80 ? '' : ($ratio < 95 ? ' ath-meter__fill--warn' : ' ath-meter__fill--bad');
        ?>
        <article class="ath-item ath-rise">
            <div class="ath-item__head">
                <div style="min-width:0;">
                    <p class="ath-item__name"><?= $h((string) ($k['name'] ?? '')) ?></p>
                    <p class="ath-item__meta">
                        Préfixe <span class="ath-mono"><?= $h((string) ($k['key_prefix'] ?? '—')) ?>…</span><br>
                        Créé le <?= $h($fmtDt($k['created_at'] ?? null)) ?>
                        · dernière utilisation <?= $h($fmtDt($k['last_used_at'] ?? null, 'jamais')) ?>
                    </p>
                </div>
                <span class="ath-tag <?= $isRevoked ? 'ath-tag--bad' : 'ath-tag--ok' ?>"><?= $isRevoked ? 'Révoqué' : 'Actif' ?></span>
            </div>

            <div class="ath-meter" style="margin-top:12px;">
                <div class="ath-meter__head">
                    <span>Consommation du jour</span>
                    <span class="ath-meter__value"><?= number_format($today, 0, ',', ' ') ?> / <?= number_format($quota, 0, ',', ' ') ?></span>
                </div>
                <div class="ath-meter__track">
                    <span class="ath-meter__fill<?= $fillClass ?>" style="width:<?= $ratio ?>%"></span>
                </div>
            </div>

            <?php if ($scopes !== []): ?>
            <div class="ath-item__tags">
                <?php foreach ($scopes as $scope): ?>
                <span class="ath-tag ath-tag--neut"><?= $h($scopeLabelFr($scope)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$isRevoked): ?>
            <form method="post" action="<?= $h(url('back-office/integrations/api-keys/' . $keyId . '/update')) ?>" style="margin-top:13px;">
                <?= \App\Core\Csrf::field() ?>
                <div class="ath-form__grid">
                    <label class="ath-field">
                        <span class="ath-field__label">Nom du service</span>
                        <input type="text" name="name" maxlength="120" required value="<?= $h((string) ($k['name'] ?? '')) ?>" class="ath-field__input">
                    </label>
                    <label class="ath-field">
                        <span class="ath-field__label">Quota par jour</span>
                        <input type="number" name="quota_per_day" min="100" max="500000" value="<?= $quota ?>" class="ath-field__input">
                    </label>
                </div>
                <div class="ath-check-grid" style="margin-top:11px;">
                    <?php foreach ($availableScopes as $scope): ?>
                    <label class="ath-check">
                        <input type="checkbox" name="scopes[]" value="<?= $h((string) $scope) ?>" <?= in_array((string) $scope, $scopes, true) ? 'checked' : '' ?>>
                        <span><?= $h($scopeLabelFr((string) $scope)) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="ath-item__actions">
                    <button type="submit" class="ath-btn">Enregistrer les réglages</button>
                </div>
            </form>

            <div class="ath-item__actions">
                <form method="post" action="<?= $h(url('back-office/integrations/api-keys/' . $keyId . '/rotate')) ?>"
                      onsubmit="return confirm('Renouveler ce jeton ? Un nouveau secret sera généré et l’ancien cessera de fonctionner immédiatement.');">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="ath-row-action">Renouveler le secret</button>
                </form>
                <form method="post" action="<?= $h(url('back-office/integrations/api-keys/' . $keyId . '/revoke')) ?>"
                      onsubmit="return confirm('Révoquer ce jeton ? Les outils qui l’utilisent cesseront de fonctionner.');">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="ath-row-action ath-row-action--danger">Révoquer</button>
                </form>
            </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div class="ath-card ath-rise" style="padding:18px 20px;">
    <h2 class="ath-section-title" style="margin-top:0;">Jetons d’accès</h2>
    <p class="ath-item__meta" style="margin:0;">
        Les jetons pour relier un site vitrine ou un outil externe au calendrier sont proposés avec une formule supérieure.
        Les relais Discord ci-dessus restent disponibles pour votre communauté.
    </p>
</div>
<?php endif; ?>
<script>
(function () {
    var hints = {
        off: 'Rien n’est envoyé sur Discord.',
        default: 'Publié dans le salon commun.',
        custom: 'Publié dans un salon à part.'
    };
    function syncCard(select) {
        var card = select.closest('[data-discord-event]');
        if (!card) return;
        var custom = card.querySelector('.js-discord-custom');
        var status = card.querySelector('[data-discord-status]');
        var mode = select.value;
        if (custom) {
            custom.hidden = mode !== 'custom';
        }
        if (status && hints[mode]) {
            status.textContent = hints[mode];
        }
    }
    document.querySelectorAll('.js-discord-mode').forEach(function (select) {
        syncCard(select);
        select.addEventListener('change', function () { syncCard(select); });
    });
})();
</script>
