<?php
declare(strict_types=1);
/*
 * Accueil de l'administration du site : dernières communautés, derniers comptes et comptes inactifs.
 * Données préparées par PlatformAccountsOverviewService (comptes anonymisés, fusionnés et techniques exclus).
 */
$ov = is_array($adminAccountsOverview ?? null) ? $adminAccountsOverview : [];
$ovErr = (string) ($ov['error'] ?? '');
$ovCommunities = is_array($ov['communities'] ?? null) ? $ov['communities'] : [];
$ovAccounts = is_array($ov['accounts'] ?? null) ? $ov['accounts'] : [];
$ovInactive = is_array($ov['inactivity'] ?? null) ? $ov['inactivity'] : [];
$ovBuckets = is_array($ovInactive['buckets'] ?? null) ? $ovInactive['buckets'] : [];
$ovOldest = is_array($ovInactive['oldest'] ?? null) ? $ovInactive['oldest'] : [];
$hv = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$nfv = static fn (int $n): string => number_format($n, 0, ',', ' ');
$ovDate = static function (?string $raw): string {
    $ts = $raw !== null && $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y', $ts) : '—';
};
$ovAgo = static function (?string $raw): string {
    $ts = $raw !== null && $raw !== '' ? strtotime($raw) : false;
    if (!$ts) {
        return '';
    }
    $days = (int) floor((time() - $ts) / 86400);

    return $days < 1 ? 'aujourd’hui' : ($days === 1 ? 'il y a 1 jour' : 'il y a ' . $days . ' jours');
};
$ovName = static function (array $u): string {
    $name = trim((string) ($u['display_name'] ?? ''));
    $cs = trim((string) ($u['callsign'] ?? ''));
    if ($name === '') {
        $name = $cs !== '' ? $cs : (string) ($u['email'] ?? '');
    } elseif ($cs !== '' && $cs !== $name) {
        $name .= ' (' . $cs . ')';
    }

    return $name;
};
$ovPersonUrl = static fn (array $u): string => url('admin/users/person') . '?user_id=' . (int) ($u['id'] ?? 0);
$ovBucketLabels = [
    '30' => 'Plus de 30 jours',
    '90' => 'Plus de 90 jours',
    '180' => 'Plus de 180 jours',
    'jamais' => 'Jamais connecté',
];
?>
<section id="hub-comptes" class="pa-hub-grid scroll-mt-24" aria-label="Comptes et communautés">
    <section class="pa-card" aria-labelledby="latest-communities-heading">
        <div class="pa-card__head">
            <span class="pa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></span>
            <div>
                <p class="pa-card__kicker">Communautés</p>
                <h2 id="latest-communities-heading" class="pa-card__title">Dernières communautés créées</h2>
            </div>
            <a class="pa-card__link" href="<?= $hv(url('admin/tenants')) ?>">Annuaire →</a>
        </div>
        <?php if ($ovErr !== ''): ?>
            <p class="pa-flash pa-flash--warn" style="margin:0;"><?= $hv($ovErr) ?></p>
        <?php elseif ($ovCommunities === []): ?>
            <p class="pa-empty-state">Aucune communauté pour le moment.</p>
        <?php else: ?>
            <ul class="pa-feed">
                <?php foreach ($ovCommunities as $t): ?>
                    <li>
                        <a class="pa-feed__title" href="<?= $hv(url('admin/tenants/' . (int) ($t['id'] ?? 0) . '/edit')) ?>"><?= $hv(community_display_name($t)) ?></a>
                        <time><?= $hv($ovDate((string) ($t['created_at'] ?? ''))) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="pa-card" aria-labelledby="latest-accounts-heading">
        <div class="pa-card__head">
            <span class="pa-icon pa-icon--violet" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg></span>
            <div>
                <p class="pa-card__kicker">Comptes</p>
                <h2 id="latest-accounts-heading" class="pa-card__title">Derniers comptes créés</h2>
            </div>
            <a class="pa-card__link" href="<?= $hv(url('admin/users')) ?>">Tous les comptes →</a>
        </div>
        <?php if ($ovErr !== ''): ?>
            <p class="pa-flash pa-flash--warn" style="margin:0;"><?= $hv($ovErr) ?></p>
        <?php elseif ($ovAccounts === []): ?>
            <p class="pa-empty-state">Aucun compte pour le moment.</p>
        <?php else: ?>
            <ul class="pa-feed">
                <?php foreach ($ovAccounts as $u): ?>
                    <?php $community = (string) ($u['community_name'] ?? ''); ?>
                    <li>
                        <a class="pa-feed__title" href="<?= $hv($ovPersonUrl($u)) ?>"><?= $hv($ovName($u)) ?></a>
                        <time><?= $hv($ovDate((string) ($u['created_at'] ?? ''))) ?></time>
                        <span class="pa-feed__meta"><?= $hv($community !== '' ? $community : 'Sans communauté') ?> · <?= trim((string) ($u['last_login_at'] ?? '')) !== '' ? 'connecté ' . $hv($ovAgo((string) $u['last_login_at'])) : 'jamais connecté' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</section>

<section class="pa-card scroll-mt-24" id="hub-inactifs" aria-labelledby="inactive-accounts-heading" style="margin-top:14px;">
    <div class="pa-card__head">
        <span class="pa-icon pa-icon--amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <div>
            <p class="pa-card__kicker">Comptes</p>
            <h2 id="inactive-accounts-heading" class="pa-card__title">Comptes inactifs</h2>
        </div>
        <a class="pa-card__link" href="<?= $hv(url('admin/users') . '?inactif=30') ?>">Voir tout →</a>
    </div>
    <?php if ($ovErr !== ''): ?>
        <p class="pa-flash pa-flash--warn" style="margin:0;"><?= $hv($ovErr) ?></p>
    <?php elseif (empty($ovInactive['available'])): ?>
        <p class="pa-empty-state">Les dates de connexion ne sont pas encore enregistrées sur ce site.</p>
    <?php else: ?>
        <dl class="pa-mini-stats">
            <?php foreach ($ovBucketLabels as $key => $label): ?>
                <div>
                    <dt><?= $hv($label) ?></dt>
                    <dd><a href="<?= $hv(url('admin/users') . '?inactif=' . rawurlencode((string) $key)) ?>"><?= $nfv((int) ($ovBuckets[$key] ?? 0)) ?></a></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php if ($ovOldest !== []): ?>
            <p class="pa-card__kicker" style="margin:14px 0 4px;">Les plus anciens</p>
            <ul class="pa-feed">
                <?php foreach ($ovOldest as $u): ?>
                    <?php $community = (string) ($u['community_name'] ?? ''); ?>
                    <li>
                        <a class="pa-feed__title" href="<?= $hv($ovPersonUrl($u)) ?>"><?= $hv($ovName($u)) ?></a>
                        <time title="Dernière connexion"><?= $hv($ovDate((string) ($u['last_login_at'] ?? ''))) ?></time>
                        <span class="pa-feed__meta"><?= $hv($community !== '' ? $community : 'Sans communauté') ?> · dernière connexion <?= $hv($ovAgo((string) ($u['last_login_at'] ?? ''))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="pa-card__foot">Sur <strong><?= $nfv((int) ($ovInactive['total'] ?? 0)) ?></strong> personnes inscrites (comptes supprimés exclus). Les tranches se cumulent : « plus de 90 jours » compte aussi ceux de plus de 180 jours.</p>
    <?php endif; ?>
</section>
