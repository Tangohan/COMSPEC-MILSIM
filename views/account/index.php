<?php
declare(strict_types=1);

$accountUser = is_array($accountUser ?? null) ? $accountUser : [];
$accountProfile = is_array($accountProfile ?? null) ? $accountProfile : [];
$accountSnapshot = $accountSnapshot ?? ['email_masked' => '—', 'email_verified' => false, 'last_login_label' => null];
$onboardingSnapshot = is_array($onboardingSnapshot ?? null) ? $onboardingSnapshot : [];
$accountHasPortrait = !empty($accountHasPortrait);
$accountSecurity = is_array($accountSecurity ?? null) ? $accountSecurity : [];
$accountActivity = is_array($accountActivity ?? null) ? $accountActivity : [];
$accountDeletionScheduledAt = trim((string) ($accountDeletionScheduledAt ?? ''));
$accountMemberSince = trim((string) ($accountMemberSince ?? ''));

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$callsign = trim((string) ($accountUser['callsign'] ?? ''));
$onboardingPercent = max(0, min(100, (int) ($onboardingSnapshot['percent'] ?? 0)));
$onboardingNudge = trim((string) ($onboardingSnapshot['nudge'] ?? ''));
$onboardingTotal = (int) ($onboardingSnapshot['total_count'] ?? 0);
$showOnboarding = $onboardingTotal > 0 && $onboardingPercent < 100;

$secondFactor = $accountSecurity['second_factor'] ?? null;
$secondFactorMandatory = !empty($accountSecurity['second_factor_mandatory']);
$steamLinked = !empty($accountSecurity['steam_linked']);
$devicesActive = (int) ($accountSecurity['devices_active'] ?? 0);
$devicesTotal = (int) ($accountSecurity['devices_total'] ?? 0);

$formatDay = static function (string $raw): ?string {
    if ($raw === '' || str_starts_with($raw, '0000')) {
        return null;
    }
    try {
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $dt = new \DateTimeImmutable($raw);

        return $dt->format('j') . ' ' . $months[(int) $dt->format('n') - 1] . ' ' . $dt->format('Y');
    } catch (\Throwable) {
        return null;
    }
};

/* Protections du compte : chaque ligne dit son état et mène au réglage. */
$checks = [
    [
        'ok' => !empty($accountSnapshot['email_verified']),
        'title' => 'Adresse e-mail confirmée',
        'detail' => (string) ($accountSnapshot['email_masked'] ?? '—'),
        'detail_mono' => true,
        'warn' => 'Confirmez-la : c’est elle qui sert à récupérer le compte.',
        'href' => url('account/mail'),
        'cta' => !empty($accountSnapshot['email_verified']) ? 'Changer' : 'Vérifier',
    ],
    [
        'ok' => $secondFactor !== null,
        'title' => 'Double vérification',
        'detail' => $secondFactor === 'totp'
            ? 'Application d’authentification'
            : ($secondFactor === 'email'
                ? 'Code par e-mail' . ($secondFactorMandatory ? ' (imposé pour votre rôle)' : '')
                : 'Mot de passe seul'),
        'warn' => 'Un second code bloque la connexion même si le mot de passe fuite.',
        'href' => url('account/security'),
        'cta' => $secondFactor !== null ? 'Gérer' : 'Activer',
    ],
    [
        'ok' => $steamLinked,
        'title' => 'Compte Steam lié',
        'detail' => $steamLinked ? 'Reconnu en jeu et par COMSPEC ATAK' : 'Non lié',
        'warn' => 'Nécessaire pour être reconnu sur le serveur et dans ATAK.',
        'href' => $steamLinked ? url('account/preferences') . '#section-profil' : url('account/steam/connect'),
        'cta' => $steamLinked ? 'Voir' : 'Lier Steam',
    ],
    [
        'ok' => $accountHasPortrait,
        'title' => 'Portrait opérateur',
        'detail' => $accountHasPortrait ? 'Affiché sur le portail et la fiche' : 'Photo « inconnu » affichée',
        'warn' => 'Votre portrait remplace la silhouette partout sur le site.',
        'href' => url('account/portrait'),
        'cta' => $accountHasPortrait ? 'Changer' : 'Ajouter',
    ],
];
$checksDone = count(array_filter($checks, static fn (array $c): bool => $c['ok']));
$checksTotal = count($checks);
$checksPct = $checksTotal > 0 ? (int) round($checksDone * 100 / $checksTotal) : 0;
$healthTone = $checksDone === $checksTotal ? 'ok' : ($checksDone >= $checksTotal - 1 ? 'mid' : 'low');
$healthLabel = match ($healthTone) {
    'ok' => 'Compte bien protégé',
    'mid' => 'Presque complet',
    default => 'À compléter',
};

$accountNavKey = 'overview';
$accountTitle = 'Mon compte';
$accountLead = 'Connexion, sécurité et préférences. L’unité et le grade restent sur votre fiche.';
require base_path('views/partials/account/shell_open.php');
?>

<?php if ($accountDeletionScheduledAt !== ''): ?>
<div class="account-hub__ov-alert account-hub__ov-alert--danger" role="alert">
    <div>
        <strong>Suppression du compte programmée<?= ($d = $formatDay($accountDeletionScheduledAt)) !== null ? ' le ' . $h($d) : '' ?>.</strong>
        <span>Vous pouvez encore l’annuler.</span>
    </div>
    <a href="<?= $h(url('account/donnees')) ?>" class="account-hub__btn account-hub__btn--ink">Annuler la suppression</a>
</div>
<?php endif; ?>

<?php if ($showOnboarding): ?>
<div class="account-hub__ov-alert" aria-labelledby="onboarding-heading">
    <div class="account-hub__ov-alert-main">
        <strong id="onboarding-heading"><?= $h(function_exists('__') ? __('common.integration_account_heading') : 'Votre arrivée n’est pas terminée') ?></strong>
        <span>
            <?= $onboardingNudge !== '' && $onboardingNudge !== 'RAS'
                ? $h($onboardingNudge)
                : $h(function_exists('__') ? __('common.integration_account_nudge') : 'Les étapes restantes se trouvent dans Mon intégration, pas ici.') ?>
        </span>
        <span class="account-hub__ov-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $onboardingPercent ?>"><span style="width:<?= $onboardingPercent ?>%"></span></span>
    </div>
    <a href="<?= $h(url('mon-integration')) ?>" class="account-hub__btn account-hub__btn--ink"><?= $h(function_exists('__') ? __('common.integration_open') : 'Ouvrir Mon intégration') ?></a>
</div>
<?php endif; ?>

<section class="account-hub__ov-health is-<?= $h($healthTone) ?>" aria-labelledby="account-health-heading">
    <div class="account-hub__ov-score">
        <span class="account-hub__ov-ring" style="--pct:<?= $checksPct ?>" aria-hidden="true">
            <span><?= $checksDone ?><small>/<?= $checksTotal ?></small></span>
        </span>
        <div>
            <p class="account-hub__panel-kicker">État du compte</p>
            <h2 id="account-health-heading" class="account-hub__ov-score-title"><?= $h($healthLabel) ?></h2>
            <p class="account-hub__ov-score-desc">
                <?= $checksDone === $checksTotal
                    ? 'Les quatre protections sont en place.'
                    : $h(($checksTotal - $checksDone) . ' point' . ($checksTotal - $checksDone > 1 ? 's' : '') . ' à régler, chacun en un clic.') ?>
            </p>
        </div>
    </div>
    <ul class="account-hub__ov-checks">
        <?php foreach ($checks as $check): ?>
        <li class="account-hub__ov-check<?= $check['ok'] ? ' is-ok' : ' is-todo' ?>">
            <span class="account-hub__ov-check-mark" aria-hidden="true">
                <?php if ($check['ok']): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>
                <?php endif; ?>
            </span>
            <span class="account-hub__ov-check-text">
                <span class="account-hub__ov-check-title"><?= $h($check['title']) ?><span class="account-hub__sr"><?= $check['ok'] ? ' : en place' : ' : à régler' ?></span></span>
                <span class="account-hub__ov-check-detail<?= !empty($check['detail_mono']) ? ' is-mono' : '' ?>"><?= $h($check['detail']) ?></span>
                <?php if (!$check['ok']): ?>
                <span class="account-hub__ov-check-why"><?= $h($check['warn']) ?></span>
                <?php endif; ?>
            </span>
            <a href="<?= $h($check['href']) ?>" class="account-hub__ov-check-cta"><?= $h($check['cta']) ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="account-hub__ov-stats">
    <div class="account-hub__ov-stat">
        <p class="account-hub__ov-stat-label">Dernière connexion</p>
        <p class="account-hub__ov-stat-value is-sm"><?= $h(preg_replace('/\s*\([^)]*\)$/', '', (string) ($accountSnapshot['last_login_label'] ?? '')) ?: 'Non enregistrée') ?></p>
    </div>
    <a class="account-hub__ov-stat is-link" href="<?= $h(url('account/security/devices')) ?>">
        <p class="account-hub__ov-stat-label">Appareils ATAK</p>
        <p class="account-hub__ov-stat-value"><?= $devicesActive ?></p>
        <p class="account-hub__ov-stat-note"><?= $devicesTotal === 0 ? 'Aucun téléphone lié' : ($devicesActive === $devicesTotal ? 'actif' . ($devicesActive > 1 ? 's' : '') : 'actif' . ($devicesActive > 1 ? 's' : '') . ' sur ' . $devicesTotal) ?></p>
    </a>
    <div class="account-hub__ov-stat">
        <p class="account-hub__ov-stat-label">Indicatif</p>
        <p class="account-hub__ov-stat-value is-sm"><?= $h($callsign !== '' ? $callsign : 'Non renseigné') ?></p>
        <p class="account-hub__ov-stat-note"><a href="<?= $h(url('account/preferences')) ?>#section-profil">Modifier le profil</a></p>
    </div>
    <div class="account-hub__ov-stat">
        <p class="account-hub__ov-stat-label">Membre depuis</p>
        <p class="account-hub__ov-stat-value is-sm"><?= $h($formatDay($accountMemberSince) ?? '—') ?></p>
    </div>
</div>

<div class="account-hub__ov-grid">
    <section class="account-hub__panel" aria-labelledby="account-activity-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Journal</p>
            <h2 id="account-activity-heading" class="account-hub__panel-title">Activité récente</h2>
            <p class="account-hub__panel-desc">Connexions et changements de sécurité de ce compte. Adresse IP volontairement tronquée.</p>
        </div>
        <div class="account-hub__panel-body">
            <?php if ($accountActivity === []): ?>
            <p class="account-hub__ov-empty">Aucune activité enregistrée pour l’instant.</p>
            <?php else: ?>
            <ol class="account-hub__ov-feed">
                <?php foreach ($accountActivity as $event): ?>
                <li class="account-hub__ov-event is-<?= $h($event['tone'] ?? 'muted') ?>">
                    <span class="account-hub__ov-event-dot" aria-hidden="true"></span>
                    <span class="account-hub__ov-event-main">
                        <span class="account-hub__ov-event-title"><?= $h($event['label'] ?? '') ?></span>
                        <span class="account-hub__ov-event-meta"><?= $h($event['device'] ?? '') ?> · IP <?= $h($event['ip'] ?? '—') ?></span>
                    </span>
                    <time class="account-hub__ov-event-when"><?= $h($event['when'] ?? '') ?></time>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>
    </section>

    <section id="sessions" class="account-hub__panel account-hub__section-anchor" aria-labelledby="account-sessions-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Sessions</p>
            <h2 id="account-sessions-heading" class="account-hub__panel-title">Connecté ailleurs ?</h2>
            <p class="account-hub__panel-desc">Un ordinateur partagé, un téléphone perdu ou une connexion que vous ne reconnaissez pas : fermez toutes les autres sessions. Celle-ci reste ouverte.</p>
        </div>
        <div class="account-hub__panel-body">
            <form method="post" action="<?= $h(url('account/sessions/fermer-les-autres')) ?>" onsubmit="return confirm('Fermer toutes vos autres sessions ? Les autres navigateurs devront se reconnecter.');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Déconnecter mes autres sessions</button>
            </form>
            <p class="account-hub__hint" style="margin-top:.85rem">Changer de mot de passe ferme aussi les autres sessions. Les téléphones ATAK se gèrent dans <a href="<?= $h(url('account/security/devices')) ?>">Appareils ATAK</a>.</p>
        </div>
    </section>
</div>

<?php require base_path('views/partials/account/shell_close.php'); ?>
