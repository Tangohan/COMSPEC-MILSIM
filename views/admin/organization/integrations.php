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
 */

$keys = is_array($integration_keys ?? null) ? $integration_keys : [];
$newKeyPlain = $new_integration_key_plain ?? null;
$availableScopes = is_array($available_scopes ?? null) && $available_scopes !== [] ? $available_scopes : ['events:read'];

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
        Chaque outil externe présente son <strong>jeton confidentiel</strong> à chaque requête. Le flux d’événements
        et la référence détaillée des échanges sont décrits dans la notice d’intégration fournie avec la plateforme.
        Un jeton révoqué cesse de fonctionner immédiatement et ne peut pas être réactivé.
    </p>
</div>

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
