<?php
declare(strict_types=1);

/**
 * Annonces & alertes communauté — rendu ATHENA.
 *
 * @var list<array<string, mixed>> $tenantAlerts
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$rows = is_array($tenantAlerts ?? null) ? $tenantAlerts : [];

$kindPresentation = static function (string $raw): array {
    return match ($raw) {
        'info' => ['label' => 'Information', 'tone' => 'neut'],
        'novelty' => ['label' => 'Nouveauté', 'tone' => 'ok'],
        'discount' => ['label' => 'Promo / remise', 'tone' => 'warn'],
        'urgent' => ['label' => 'Urgent', 'tone' => 'bad'],
        'notice' => ['label' => 'Consigne', 'tone' => 'info'],
        'event' => ['label' => 'Événement', 'tone' => 'ok'],
        'maintenance' => ['label' => 'Maintenance', 'tone' => 'neut'],
        'training' => ['label' => 'Formation', 'tone' => 'info'],
        'recruitment' => ['label' => 'Recrutement', 'tone' => 'info'],
        'security' => ['label' => 'Sécurité', 'tone' => 'warn'],
        default => ['label' => \App\Support\TenantAlertVisuals::kindLabel($raw), 'tone' => 'neut'],
    };
};

$formatDt = static function (?string $mysql): string {
    if ($mysql === null || trim($mysql) === '') {
        return '';
    }
    $t = strtotime($mysql);

    return $t ? date('d/m/Y H:i', $t) : '';
};

$now = time();
$activeCount = 0;
$inactiveCount = 0;
$visibleNow = 0;
foreach ($rows as $r) {
    $isActive = !empty($r['is_active']);
    if ($isActive) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if (!$isActive) {
        continue;
    }
    $starts = !empty($r['starts_at']) ? strtotime((string) $r['starts_at']) : false;
    $ends = !empty($r['ends_at']) ? strtotime((string) $r['ends_at']) : false;
    if ($starts !== false && $starts > $now) {
        continue;
    }
    if ($ends !== false && $ends < $now) {
        continue;
    }
    $visibleNow++;
}
$totalCount = count($rows);

$athKpis = [
    ['label' => 'TOTAL', 'value' => (string) $totalCount, 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'annonces'],
    ['label' => 'VISIBLES', 'value' => (string) $visibleNow, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $totalCount > 0 ? (int) round($visibleNow / $totalCount * 100) . '%' : '0%', 'note' => 'en ce moment'],
    ['label' => 'ACTIVES', 'value' => (string) $activeCount, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '—', 'note' => 'publiables'],
    ['label' => 'INACTIVES', 'value' => (string) $inactiveCount, 'delta' => '', 'tone' => '#8c979b', 'pct' => '—', 'note' => 'désactivées'],
];
require base_path('views/partials/ath_kpis.php');

$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<?php if ($s): ?><div class="bo-settings-flash bo-settings-flash--ok ath-rise" role="status"><?= $h((string) $s) ?></div><?php endif; ?>
<?php if ($e): ?><div class="bo-settings-flash bo-settings-flash--err ath-rise" role="alert"><?= $h((string) $e) ?></div><?php endif; ?>

<?php
// Option de diffusion : les membres voient-ils les annonces programmées avant leur date ?
$showUpcoming = (bool) ($tenantAlertsShowUpcoming ?? false);
?>
<form method="post" action="<?= $h(url('back-office/alerts/affichage-a-venir')) ?>" class="ath-form ath-rise">
    <div class="ath-form__head">
        <span class="ath-form__title">Annonces à venir sur la page membre</span>
        <span class="ath-form__hint">État actuel : <?= $showUpcoming ? 'visibles avant diffusion' : 'masquées jusqu’à leur date' ?></span>
    </div>
    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="show_upcoming" value="<?= $showUpcoming ? '0' : '1' ?>">
    <p class="ath-field__help" style="margin:0 0 11px;">
        Une annonce dont la date de début est future n’apparaît pas encore sur
        <strong>Alertes &amp; annonces</strong>. En activant cette option, les membres la voient
        d’avance dans une section « À venir », avec sa date de diffusion prévue — utile pour
        annoncer une opération longtemps à l’avance, à éviter si vous préparez vos annonces
        sans vouloir les dévoiler.
    </p>
    <div class="ath-form__actions" style="border-top:0;padding-top:0;">
        <button type="submit" class="ath-btn<?= $showUpcoming ? '' : ' ath-btn--solid' ?>">
            <?= $showUpcoming ? 'Masquer les annonces à venir' : 'Afficher les annonces à venir' ?>
        </button>
    </div>
</form>

<div class="flex flex-wrap gap-2 ath-rise">
    <a href="<?= $h(url('back-office/alerts/create')) ?>" class="ath-btn ath-btn--solid">Nouvelle annonce</a>
    <a href="<?= $h(url('back-office')) ?>" class="ath-btn">Centre de pilotage</a>
</div>

<div class="ath-table-panel ath-rise">
    <div class="ath-table-toolbar">
        <span class="ath-table-toolbar__title">Annonces enregistrées</span>
        <span class="ath-table-toolbar__count"><?= $totalCount ?> annonce<?= $totalCount > 1 ? 's' : '' ?></span>
        <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
        <a href="<?= $h(url('back-office/alerts/create')) ?>" class="ath-table-toolbar__export">Ajouter</a>
    </div>
    <div class="ath-table-wrap">
        <table class="ath-table" style="min-width:960px">
            <thead>
                <tr>
                    <th scope="col">Titre</th>
                    <th scope="col">Type</th>
                    <th scope="col">Emplacement</th>
                    <th scope="col">État</th>
                    <th scope="col">Période</th>
                    <th scope="col" data-ath-num="1">Ordre</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $id = (int) ($r['id'] ?? 0);
                    $active = !empty($r['is_active']);
                    $kind = $kindPresentation((string) ($r['kind'] ?? ''));
                    $body = trim((string) ($r['body'] ?? ''));
                    $starts = $formatDt(isset($r['starts_at']) ? (string) $r['starts_at'] : null);
                    $ends = $formatDt(isset($r['ends_at']) ? (string) $r['ends_at'] : null);
                    $period = 'Toujours';
                    if ($starts !== '' || $ends !== '') {
                        $period = ($starts !== '' ? $starts : '…') . ' → ' . ($ends !== '' ? $ends : '…');
                    }
                    $startsTs = !empty($r['starts_at']) ? strtotime((string) $r['starts_at']) : false;
                    $endsTs = !empty($r['ends_at']) ? strtotime((string) $r['ends_at']) : false;
                    $isVisibleNow = $active
                        && ($startsTs === false || $startsTs <= $now)
                        && ($endsTs === false || $endsTs >= $now);
                    $stateLabel = $isVisibleNow ? 'Visible' : ($active ? 'Programmée' : 'Inactive');
                    ?>
                <tr class="ath-row">
                    <td>
                        <span style="font-weight:900;color:var(--ath-ink);"><?= $h((string) ($r['title'] ?? '')) ?></span>
                        <?php if ($body !== ''): ?>
                        <span style="display:block;font-size:10.5px;color:var(--ath-subtle);margin-top:3px;max-width:28rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $h(mb_strlen($body) > 90 ? mb_substr($body, 0, 90) . '…' : $body) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="ath-cell ath-cell--badge ath-tag--<?= $h($kind['tone']) ?>" style="padding:3px 8px;font-weight:800;"><?= $h($kind['label']) ?></span></td>
                    <td style="font-size:11px;color:var(--ath-subtle);"><?= $h(\App\Support\AlertDisplayStyle::label(isset($r['display_style']) ? (string) $r['display_style'] : 'classic')) ?></td>
                    <td><span class="ath-cell ath-cell--badge ath-tag--<?= $isVisibleNow ? 'ok' : ($active ? 'warn' : 'neut') ?>" style="padding:3px 8px;font-weight:800;"><?= $h($stateLabel) ?></span></td>
                    <td style="font-size:11px;color:var(--ath-subtle);white-space:nowrap;"><?= $h($period) ?></td>
                    <td data-ath-num="1" style="font-family:var(--ath-mono);font-weight:700;"><?= (int) ($r['sort_order'] ?? 0) ?></td>
                    <td>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= $h(url('back-office/alerts/' . $id . '/edit')) ?>" class="ath-btn">Modifier</a>
                            <form method="post" action="<?= $h(url('back-office/alerts/' . $id . '/delete')) ?>" class="inline" onsubmit="return confirm('Supprimer cette annonce ? Les membres ne la verront plus.');">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="ath-btn" style="color:#a32222;border-color:#f6cccc;">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                <tr><td colspan="7" class="ath-table-empty">Aucune annonce pour le moment. Créez un bandeau pour informer vos membres.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($rows !== []): ?>
    <div class="ath-table-foot">
        <div class="ath-table-foot__meta">Ordre d’affichage croissant · <?= $visibleNow ?> visible<?= $visibleNow > 1 ? 's' : '' ?> actuellement</div>
    </div>
    <?php endif; ?>
</div>
