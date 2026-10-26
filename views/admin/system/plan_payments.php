<?php
declare(strict_types=1);

/**
 * Administration du site — Paiements et dons (/admin/paiements).
 * Vues : aperçu, paiements, abonnements, dons, réglages. Interactions : public/assets/js/admin-payments.js.
 */
use App\Services\Billing\DonorService;
use App\Support\ManualBilling;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$money = static fn (?int $c): string => $c !== null ? ManualBilling::formatAmount((int) $c) : '—';
$base = url('admin/paiements');
$view = (string) ($payView ?? 'apercu');
$k = is_array($payKpis ?? null) ? $payKpis : [];
$ledger = is_array($payLedger ?? null) ? $payLedger : [];
$rows = is_array($payRows ?? null) ? $payRows : [];
$f = is_array($payFilters ?? null) ? $payFilters : ['source' => '', 'status' => '', 'q' => '', 'days' => 0];
$events = is_array($payEvents ?? null) ? $payEvents : [];
$subs = is_array($paySubscriptions ?? null) ? $paySubscriptions : [];
$donors = is_array($payDonors ?? null) ? $payDonors : [];
$revoked = is_array($payRevoked ?? null) ? $payRevoked : [];
$wallHidden = is_array($payHidden ?? null) ? $payHidden : [];
$supporters = is_array($paySupporters ?? null) ? $paySupporters : [];
$planNames = is_array($payPlanNames ?? null) ? $payPlanNames : [];
$tenantNames = is_array($payTenantNames ?? null) ? $payTenantNames : [];
$focus = (string) ($payFocus ?? '');
$csrfTok = (string) ($csrfToken ?? '');
$csrf = static fn (string $vue, string $ref = ''): string => '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($csrfTok, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="_vue" value="' . htmlspecialchars($vue, ENT_QUOTES, 'UTF-8') . '">' . ($ref !== '' ? '<input type="hidden" name="_ref" value="' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '">' : '');
$fmt = static function (mixed $raw, string $p = 'd/m/Y H:i'): string {
    if (!is_string($raw) || $raw === '') {
        return '—';
    }
    $t = strtotime($raw . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', $raw) ? '' : ' UTC'));

    return $t ? date($p, $t) : $raw;
};
$ago = static function (mixed $raw): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw . ' UTC') : false;
    if (!$t) {
        return '';
    }
    $d = time() - $t;

    return $d < 3600 ? 'il y a ' . max(1, (int) floor($d / 60)) . ' min' : ($d < 86400 ? 'il y a ' . (int) floor($d / 3600) . ' h' : 'il y a ' . (int) floor($d / 86400) . ' j');
};
$sources = ['plan' => ['Formule', 'info'], 'heart' => ['Soutien du cœur', 'warn'], 'stripe_donation' => ['Don ATAK · Stripe', 'ok'], 'donation' => ['Don', 'ok']];
$statuses = [
    'declared' => ['À vérifier', 'warn'], 'awaiting' => ['À payer', 'neut'], 'activated' => ['Encaissé', 'ok'],
    'rejected' => ['Refusé', 'bad'], 'cancelled' => ['Annulé', 'neut'], 'refunded' => ['Remboursé', 'info'],
];
$eventLabels = [
    'created' => 'Demande créée', 'declared' => 'Paiement déclaré par le payeur', 'activated' => 'Paiement confirmé',
    'rejected' => 'Paiement refusé', 'cancelled' => 'Demande annulée', 'refunded' => 'Paiement remboursé',
    'reminder' => 'Relance envoyée', 'reminder_auto' => 'Relance automatique envoyée', 'renewal_sent' => 'Renouvellement envoyé',
    'extended' => 'Formule prolongée', 'plan_ended' => 'Formule arrêtée', 'note' => 'Note', 'expired' => 'Annulée automatiquement',
    'donation_recorded' => 'Don enregistré', 'donation_refunded' => 'Don remboursé', 'donation_paid' => 'Don Stripe payé',
    'donor_granted' => 'Rôle donateur attribué', 'donor_revoked' => 'Rôle donateur retiré', 'donor_restored' => 'Rôle donateur rétabli',
];
$tierTone = ['soutien' => 'neut', 'allie' => 'info', 'pilier' => 'ok'];
$channels = ['paypal' => 'PayPal', 'stripe' => 'Stripe', 'virement' => 'Virement', 'especes' => 'Espèces', 'autre' => 'Autre'];
$sc = is_array($payStatusCounts ?? null) ? $payStatusCounts : [];
$donationRows = array_values(array_filter($ledger, static fn ($e) => in_array($e['source'], ['heart', 'stripe_donation', 'donation'], true)));
$tabs = [
    'apercu' => ['Aperçu', null],
    'paiements' => ['Paiements', (int) ($sc['declared'] ?? 0)],
    'abonnements' => ['Abonnements', count($subs)],
    'dons' => ['Dons et donateurs', count($donors)],
    'reglages' => ['Réglages', null],
];
$qs = static function (array $over) use ($f): string {
    $q = array_filter(['vue' => 'paiements', 'nature' => $f['source'], 'statut' => $f['status'], 'q' => $f['q'], 'periode' => $f['days'] ?: ''] , static fn ($v) => $v !== '' && $v !== null);

    return '?' . http_build_query(array_filter(array_merge($q, $over), static fn ($v) => $v !== '' && $v !== null));
};
?>
<div class="pa">
<div class="pa__frame">
<header class="pa-hero">
  <p class="pa-crumb"><a href="<?= $h(url('admin')) ?>">Administration du site</a><span aria-hidden="true"> / </span>Finances</p>
  <h1 class="pa-hero__title" id="ap-title">Paiements et dons</h1>
  <p class="pa-hero__lead">Formules payées par PayPal, soutiens du cœur et dons : vérification, récurrence, relances, remboursements, et rôle donateur sur le site.</p>
  <div class="pa-hero__actions">
    <a class="pa-btn pa-btn--ghost" href="<?= $h(url('soutenir-atak')) ?>" target="_blank" rel="noopener">Page publique de soutien</a>
    <a class="pa-btn pa-btn--ghost" href="<?= $h($base . '/export.csv') ?>">Exporter (Excel)</a>
  </div>
</header>
<?php $bo_flash_margin_class = ''; require base_path('views/partials/bo_session_flashes.php'); ?>

<div class="ap" id="ap" data-csrf="<?= $h($csrfTok) ?>" data-members="<?= $h($base . '/membres') ?>" data-focus="<?= $h($focus) ?>">

  <?php if (!empty($payError)): ?><div class="ap-banner is-bad"><b>Registre indisponible.</b> <?= $h($payError) ?></div><?php endif; ?>
  <?php if ((int) ($k['to_verify'] ?? 0) > 0): $late = (int) ($k['oldest_declared_hours'] ?? 0) >= 24; ?>
    <div class="ap-banner <?= $late ? 'is-bad' : 'is-warn' ?>">
      <div><b><?= (int) $k['to_verify'] ?> paiement<?= (int) $k['to_verify'] > 1 ? 's' : '' ?> à vérifier sur PayPal</b> — le plus ancien attend depuis <?= (int) $k['oldest_declared_hours'] ?> h<?= $late ? ' (au-delà de 24 h : le payeur attend son accès)' : '' ?>.</div>
      <a class="ath-btn ath-btn--solid" href="<?= $h($base . '?vue=paiements&statut=declared') ?>">Vérifier maintenant</a>
    </div>
  <?php endif; ?>

  <div class="ath-kpi-grid ap-kpis">
    <article class="ath-card ath-kpi is-ok"><p class="ath-kpi__label">Encaissé ce mois</p><p class="ath-kpi__value"><?= $h($money($k['month_cents'] ?? 0)) ?></p>
      <p class="ath-kpi__note"><?php if (($k['month_delta_pct'] ?? null) !== null): ?><span class="ap-delta <?= $k['month_delta_pct'] >= 0 ? 'is-up' : 'is-down' ?>"><?= $k['month_delta_pct'] >= 0 ? '▲' : '▼' ?> <?= abs((int) $k['month_delta_pct']) ?> %</span> vs <?= $h($money($k['prev_month_cents'] ?? 0)) ?><?php else: ?>12 mois : <?= $h($money($k['year_cents'] ?? 0)) ?><?php endif; ?></p></article>
    <article class="ath-card ath-kpi"><p class="ath-kpi__label">Récurrent mensuel</p><p class="ath-kpi__value"><?= $h($money($k['mrr_cents'] ?? 0)) ?></p><p class="ath-kpi__note">soit <?= $h($money($k['arr_cents'] ?? 0)) ?> / an</p></article>
    <article class="ath-card ath-kpi<?= (int) ($k['renewals_soon'] ?? 0) > 0 ? ' is-warn' : '' ?>"><p class="ath-kpi__label">Abonnements actifs</p><p class="ath-kpi__value"><?= (int) ($k['active_subscriptions'] ?? 0) ?></p><p class="ath-kpi__note"><?= (int) ($k['renewals_soon'] ?? 0) ?> à renouveler sous 14 j</p></article>
    <article class="ath-card ath-kpi<?= (int) ($k['to_verify'] ?? 0) > 0 ? ' is-warn' : '' ?>"><p class="ath-kpi__label">À vérifier</p><p class="ath-kpi__value"><?= (int) ($k['to_verify'] ?? 0) ?></p><p class="ath-kpi__note"><?= ($k['verify_median_hours'] ?? null) !== null ? 'délai médian ' . $h(str_replace('.', ',', (string) $k['verify_median_hours'])) . ' h' : 'aucun délai mesuré' ?> · <?= (int) ($k['unpaid'] ?? 0) ?> non payée(s)</p></article>
    <article class="ath-card ath-kpi"><p class="ath-kpi__label">Dons · 12 mois</p><p class="ath-kpi__value"><?= $h($money($k['donations_12_cents'] ?? 0)) ?></p><p class="ath-kpi__note"><?= (int) ($k['donors_12'] ?? 0) ?> donateur(s) · <?= count($supporters) ?> communauté(s) soutien</p></article>
    <article class="ath-card ath-kpi<?= (int) ($k['refunds_90_cents'] ?? 0) > 0 ? ' is-bad' : '' ?>"><p class="ath-kpi__label">Remboursé · 90 j</p><p class="ath-kpi__value"><?= $h($money($k['refunds_90_cents'] ?? 0)) ?></p><p class="ath-kpi__note">transformation <?= ($k['conversion_pct'] ?? null) !== null ? (int) $k['conversion_pct'] . ' %' : '—' ?> (90 j)</p></article>
  </div>

  <nav class="ap-tabs" aria-label="Vues">
    <?php foreach ($tabs as $key => [$label, $n]): ?>
      <a class="ap-tabs__tab<?= $view === $key ? ' is-active' : '' ?>" href="<?= $h($base . '?vue=' . $key) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= $h($label) ?><?= $n ? ' <span class="ap-tabs__n">' . (int) $n . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </nav>

<?php if ($view === 'apercu'):
    $months = is_array($payMonthly ?? null) ? $payMonthly : [];
    $max = 1;
    $minR = 0;
    foreach ($months as $m) {
        $max = max($max, $m['plan'] + $m['heart'] + $m['donation']);
        $minR = min($minR, $m['refund']);
    }
    $W = 760; $H = 240; $padL = 56; $padB = 26; $padT = 10;
    $range = $max - $minR;
    $plotH = $H - $padB - $padT;
    $y = static fn (int $v): float => $padT + $plotH * ($max - $v) / max(1, $range);
    $bw = ($W - $padL - 10) / max(1, count($months));
    $declared = array_values(array_filter($ledger, static fn ($e) => $e['status'] === 'declared'));
    $unpaid = array_values(array_filter($ledger, static fn ($e) => $e['status'] === 'awaiting' && in_array($e['source'], ['plan', 'heart'], true)));
    $soon = array_values(array_filter($subs, static fn ($s) => (int) $s['days_left'] <= 14));
    $lapsed = is_array($payLapsed ?? null) ? $payLapsed : [];
    ?>
  <div class="ap-grid ap-grid--main">
    <section class="ath-panel ap-card">
      <header class="ap-card__head"><h2 class="ap-card__t">Encaissements des 12 derniers mois</h2>
        <ul class="ap-legend"><li><i class="ap-sw ap-sw--plan"></i>Formules</li><li><i class="ap-sw ap-sw--heart"></i>Soutien du cœur</li><li><i class="ap-sw ap-sw--don"></i>Dons</li><li><i class="ap-sw ap-sw--ref"></i>Remboursements</li></ul></header>
      <svg class="ap-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Encaissements mensuels">
        <?php foreach ([0, 0.5, 1] as $g): $v = (int) round($max * $g); $yy = $y($v); ?>
          <line x1="<?= $padL ?>" x2="<?= $W - 6 ?>" y1="<?= $yy ?>" y2="<?= $yy ?>" class="ap-chart__grid"/>
          <text x="<?= $padL - 8 ?>" y="<?= $yy + 4 ?>" class="ap-chart__ax" text-anchor="end"><?= $h(number_format($v / 100, 0, ',', ' ')) ?> €</text>
        <?php endforeach; ?>
        <?php foreach ($months as $i => $m):
            $x = $padL + $i * $bw + $bw * 0.18; $w = $bw * 0.64; $acc = 0; ?>
          <g><title><?= $h($m['label'] . ' : ' . $money($m['total'])) ?></title>
          <?php foreach (['plan' => 'plan', 'heart' => 'heart', 'donation' => 'don'] as $key => $cls): if ($m[$key] <= 0) { continue; } $top = $y($acc + $m[$key]); $bot = $y($acc); ?>
            <rect x="<?= $x ?>" y="<?= $top ?>" width="<?= $w ?>" height="<?= max(0.5, $bot - $top) ?>" class="ap-chart__bar ap-chart__bar--<?= $cls ?>"/>
          <?php $acc += $m[$key]; endforeach; ?>
          <?php if ($m['refund'] < 0): ?><rect x="<?= $x ?>" y="<?= $y(0) ?>" width="<?= $w ?>" height="<?= $y($m['refund']) - $y(0) ?>" class="ap-chart__bar ap-chart__bar--ref"/><?php endif; ?>
          </g>
          <text x="<?= $x + $w / 2 ?>" y="<?= $H - 8 ?>" class="ap-chart__ax" text-anchor="middle"><?= $h($m['label']) ?></text>
        <?php endforeach; ?>
        <line x1="<?= $padL ?>" x2="<?= $W - 6 ?>" y1="<?= $y(0) ?>" y2="<?= $y(0) ?>" class="ap-chart__zero"/>
      </svg>
      <details class="ap-howto"><summary>Voir les montants</summary>
        <div class="ath-table-wrap"><table class="ath-table ap-table"><thead><tr><th>Mois</th><th class="ath-th-num">Formules</th><th class="ath-th-num">Cœur</th><th class="ath-th-num">Dons</th><th class="ath-th-num">Remb.</th><th class="ath-th-num">Net</th></tr></thead><tbody>
        <?php foreach (array_reverse($months) as $m): ?><tr><td><?= $h($m['label']) ?></td><td class="ath-td-num"><?= $h($money($m['plan'])) ?></td><td class="ath-td-num"><?= $h($money($m['heart'])) ?></td><td class="ath-td-num"><?= $h($money($m['donation'])) ?></td><td class="ath-td-num"><?= $h($money($m['refund'])) ?></td><td class="ath-td-num"><b><?= $h($money($m['total'])) ?></b></td></tr><?php endforeach; ?>
        </tbody></table></div></details>
    </section>

    <section class="ath-panel ap-card">
      <header class="ap-card__head"><h2 class="ap-card__t">À faire</h2></header>
      <ul class="ap-todo">
        <li class="<?= $declared ? 'is-warn' : 'is-ok' ?>"><b><?= count($declared) ?></b><span>paiement(s) déclaré(s) à vérifier sur PayPal</span><?php if ($declared): ?><a href="<?= $h($base . '?vue=paiements&statut=declared') ?>">Vérifier</a><?php endif; ?></li>
        <li class="<?= $soon ? 'is-warn' : 'is-ok' ?>"><b><?= count($soon) ?></b><span>formule(s) à renouveler sous 14 jours</span><?php if ($soon): ?><a href="<?= $h($base . '?vue=abonnements') ?>">Préparer</a><?php endif; ?></li>
        <li class="<?= $unpaid ? 'is-neut' : 'is-ok' ?>"><b><?= count($unpaid) ?></b><span>demande(s) commencée(s) mais jamais payée(s) — relance automatique à J+2, J+7, J+12</span><?php if ($unpaid): ?><a href="<?= $h($base . '?vue=paiements&statut=awaiting') ?>">Voir</a><?php endif; ?></li>
        <li class="<?= $lapsed ? 'is-bad' : 'is-ok' ?>"><b><?= count($lapsed) ?></b><span>communauté(s) perdue(s) ces 60 jours (formule terminée, pas renouvelée)</span><?php if ($lapsed): ?><a href="<?= $h($base . '?vue=abonnements#perdues') ?>">Relancer</a><?php endif; ?></li>
      </ul>
      <header class="ap-card__head ap-card__head--sub"><h3 class="ap-card__t">Journal récent</h3></header>
      <ol class="ap-feed">
        <?php foreach (array_slice(is_array($payRecentEvents ?? null) ? $payRecentEvents : [], 0, 12) as $ev): ?>
          <li><span class="ap-feed__dot ap-feed__dot--<?= $h($ev['kind']) ?>"></span><div><b><?= $h($eventLabels[$ev['kind']] ?? $ev['kind']) ?></b><?= ($ev['tenant_name'] ?? '') !== '' ? ' · ' . $h($ev['tenant_name']) : '' ?><small><?= $h($ago($ev['created_at'] ?? null)) ?><?= ($ev['actor_name'] ?? '') !== '' ? ' · par ' . $h($ev['actor_name']) : '' ?><?= ($ev['note'] ?? '') !== '' ? ' · « ' . $h(mb_strimwidth((string) $ev['note'], 0, 80, '…')) . ' »' : '' ?></small></div></li>
        <?php endforeach; ?>
        <?php if (empty($payRecentEvents)): ?><li class="ap-muted">Le journal se remplit à chaque demande, relance et décision.</li><?php endif; ?>
      </ol>
    </section>
  </div>

  <?php elseif ($view === 'paiements'): ?>
  <section class="ath-table-panel">
    <form class="ath-table-toolbar ap-toolbar" method="get" action="<?= $h($base) ?>">
      <input type="hidden" name="vue" value="paiements">
      <span class="ath-table-toolbar__title">Registre</span>
      <span class="ath-table-toolbar__count"><?= (int) ($payRowsTotal ?? 0) ?></span>
      <input class="ath-table-toolbar__search" type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Référence, communauté, payeur, transaction…">
      <select class="ath-table-toolbar__filter" name="nature" onchange="this.form.submit()">
        <?php foreach (['' => 'Toutes natures', 'plan' => 'Formules', 'heart' => 'Soutien du cœur', 'donations' => 'Tous les dons'] as $v => $l): ?><option value="<?= $h($v) ?>"<?= $f['source'] === $v ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
      </select>
      <select class="ath-table-toolbar__filter" name="periode" onchange="this.form.submit()">
        <?php foreach ([0 => 'Toute période', 30 => '30 jours', 90 => '3 mois', 365 => '12 mois'] as $v => $l): ?><option value="<?= $v ?>"<?= (int) $f['days'] === $v ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
      </select>
      <?php if ($f['status'] !== ''): ?><input type="hidden" name="statut" value="<?= $h($f['status']) ?>"><?php endif; ?>
      <span class="ath-table-toolbar__spacer"></span>
      <a class="ath-btn ath-table-toolbar__export" href="<?= $h($base . '/export.csv' . substr($qs([]), 0)) ?>">Exporter</a>
    </form>
    <div class="ap-seg" role="group" aria-label="Statut">
      <a class="ap-seg__btn<?= $f['status'] === '' ? ' is-active' : '' ?>" href="<?= $h($base . $qs(['statut' => ''])) ?>">Tous</a>
      <?php foreach ($statuses as $code => [$label, $tone]): ?>
        <a class="ap-seg__btn<?= $f['status'] === $code ? ' is-active' : '' ?>" href="<?= $h($base . $qs(['statut' => $code])) ?>"><i class="ap-dot ap-dot--<?= $tone ?>"></i><?= $h($label) ?> <b><?= (int) ($sc[$code] ?? 0) ?></b></a>
      <?php endforeach; ?>
    </div>
    <?php if ($rows === []): ?>
      <p class="ap-empty">Aucune entrée ne correspond. Les demandes apparaissent dès qu’une communauté choisit une formule ou un soutien.</p>
    <?php else: ?>
    <div class="ath-table-wrap">
      <table class="ath-table ap-table ap-ledger">
        <thead><tr><th>Référence</th><th>Nature</th><th>Communauté · payeur</th><th class="ath-th-num">Montant</th><th>Statut</th><th>Dates</th><th></th></tr></thead>
        <?php foreach ($rows as $e):
            [$sl, $stn] = $sources[$e['source']] ?? [$e['source'], 'neut'];
            [$tl, $tt] = $statuses[$e['status']] ?? [$e['status'], 'neut'];
            $isReq = in_array($e['source'], ['plan', 'heart'], true);
            $evs = $events[$isReq ? 'r' . $e['id'] : 'd' . $e['id']] ?? [];
            $ref = (string) $e['reference'];
            $open = $focus !== '' && $focus === strtoupper($ref);
            ?>
        <tbody class="ap-item<?= $open ? ' is-open' : '' ?>" id="<?= $h($ref) ?>">
          <tr class="ap-item__row" data-ap-toggle>
            <td class="ap-mono"><b><?= $h($ref) ?></b></td>
            <td><span class="ath-tag ath-tag--<?= $stn ?>"><?= $h($sl) ?></span><?php if ($e['source'] === 'plan'): ?><small class="ap-sub"><?= $h(($planNames[$e['plan_slug']] ?? $e['plan_slug']) . ' · ' . ($e['interval'] === 'yearly' ? '1 an' : '1 mois')) ?></small><?php endif; ?></td>
            <td><b class="ap-name<?= DonorService::nameClass((int) $e['user_id']) ?>"><?= $h($e['tenant_name'] !== '' ? $e['tenant_name'] : ($e['payer_name'] ?: '—')) ?></b><small class="ap-sub"><?= $h(trim(($e['tenant_name'] !== '' ? $e['payer_name'] . ' · ' : '') . $e['payer_email'], ' ·')) ?></small></td>
            <td class="ath-td-num"><b><?= $h($money((int) $e['amount_cents'])) ?></b></td>
            <td><span class="ath-tag ath-tag--<?= $tt ?>"><?= $h($tl) ?></span><?php if (($e['payer_reminder_count'] ?? 0) > 0): ?><small class="ap-sub"><?= (int) $e['payer_reminder_count'] ?> relance(s)</small><?php endif; ?></td>
            <td class="ap-dates"><span>créé <?= $h($fmt($e['created_at'], 'd/m/y')) ?></span><?php if ($e['declared_at']): ?><span>déclaré <?= $h($ago($e['declared_at'])) ?></span><?php endif; ?><?php if ($e['paid_at']): ?><span>encaissé <?= $h($fmt($e['paid_at'], 'd/m/y')) ?></span><?php endif; ?></td>
            <td class="ap-right"><button type="button" class="ath-btn" aria-expanded="<?= $open ? 'true' : 'false' ?>">Ouvrir</button></td>
          </tr>
          <tr class="ap-item__detail"><td colspan="7">
            <div class="ap-detail">
              <dl class="ap-kv">
                <?php if ($e['transaction_ref'] !== ''): ?><div><dt>Transaction <?= $h($channels[$e['channel']] ?? $e['channel']) ?></dt><dd class="ap-mono"><?= $h($e['transaction_ref']) ?> <button type="button" class="ap-copy" data-ap-copy="<?= $h($e['transaction_ref']) ?>">Copier</button></dd></div><?php endif; ?>
                <?php if ($e['payer_note'] !== ''): ?><div><dt>Message du payeur</dt><dd><?= $h($e['payer_note']) ?></dd></div><?php endif; ?>
                <?php if ($e['admin_note'] !== ''): ?><div><dt>Note de l’équipe</dt><dd><?= $h($e['admin_note']) ?></dd></div><?php endif; ?>
                <?php if ($e['period_ends_at']): ?><div><dt>Période payée jusqu’au</dt><dd><?= $h($fmt($e['period_ends_at'], 'd/m/Y')) ?></dd></div><?php endif; ?>
                <?php if ($e['refunded_at']): ?><div><dt>Remboursé le</dt><dd><?= $h($fmt($e['refunded_at'])) ?></dd></div><?php endif; ?>
                <?php if ($e['payer_email'] !== ''): ?><div><dt>Payeur</dt><dd><?= $h($e['payer_email']) ?><?= DonorService::mark((int) $e['user_id'], true) ?></dd></div><?php endif; ?>
                <?php if ($e['tenant_slug'] !== ''): ?><div><dt>Communauté</dt><dd><a href="<?= $h(url('c/' . $e['tenant_slug'])) ?>" target="_blank" rel="noopener"><?= $h($e['tenant_name']) ?></a></dd></div><?php endif; ?>
              </dl>
              <?php if ($isReq && $e['status'] === 'declared'): ?>
                <p class="ap-check">Sur PayPal, cherchez <b class="ap-mono"><?= $h($e['transaction_ref']) ?></b> (ou la référence <b class="ap-mono"><?= $h($ref) ?></b>) et vérifiez le montant <b><?= $h($money((int) $e['amount_cents'])) ?></b>. <a href="https://www.paypal.com/activities" target="_blank" rel="noopener">Ouvrir l’activité PayPal</a></p>
              <?php endif; ?>
              <div class="ap-acts">
                <?php if ($isReq && in_array($e['status'], ['awaiting', 'declared'], true)): ?>
                  <form method="post" action="<?= $h($base . '/' . $e['id'] . '/activer') ?>" class="ap-act ap-act--ok"><?= $csrf('paiements', $ref) ?><input name="admin_note" placeholder="Message au payeur (facultatif)" maxlength="500"><button class="ath-btn ath-btn--solid"><?= $e['status'] === 'awaiting' ? 'Confirmer (payé sans déclaration)' : 'Confirmer et activer' ?></button></form>
                  <?php if ($e['status'] === 'declared'): ?>
                  <form method="post" action="<?= $h($base . '/' . $e['id'] . '/refuser') ?>" class="ap-act" data-ap-confirm="Refuser ce paiement ? Le payeur est prévenu."><?= $csrf('paiements', $ref) ?><input name="admin_note" placeholder="Raison du refus (envoyée au payeur)" maxlength="500" required><button class="ath-btn ap-danger">Refuser</button></form>
                  <?php else: ?>
                  <form method="post" action="<?= $h($base . '/' . $e['id'] . '/relancer') ?>" class="ap-act"><?= $csrf('paiements', $ref) ?><input name="admin_note" placeholder="Petit mot ajouté à la relance (facultatif)" maxlength="500"><button class="ath-btn">Relancer par e-mail</button></form>
                  <?php endif; ?>
                  <form method="post" action="<?= $h($base . '/' . $e['id'] . '/annuler') ?>" class="ap-act" data-ap-confirm="Annuler cette demande ?"><?= $csrf('paiements', $ref) ?><input name="admin_note" placeholder="Raison (doublon, erreur de formule…)" maxlength="500"><button class="ath-btn">Annuler la demande</button></form>
                <?php elseif ($isReq && $e['status'] === 'activated'): ?>
                  <form method="post" action="<?= $h($base . '/' . $e['id'] . '/rembourser') ?>" class="ap-act" data-ap-confirm="Marquer remboursé ? Faites d’abord le remboursement sur PayPal."><?= $csrf('paiements', $ref) ?><input name="admin_note" placeholder="Raison du remboursement" maxlength="500"><?php if ($e['source'] === 'plan'): ?><label class="ap-checkline"><input type="checkbox" name="end_plan" value="1" checked> Arrêter aussi la formule</label><?php endif; ?><button class="ath-btn ap-danger">Marquer remboursé</button></form>
                <?php elseif ($e['source'] === 'donation' && $e['status'] === 'activated'): ?>
                  <form method="post" action="<?= $h($base . '/dons/' . $e['id'] . '/rembourser') ?>" class="ap-act" data-ap-confirm="Marquer ce don remboursé ? Le rôle donateur sera recalculé."><?= $csrf('paiements', $ref) ?><input name="admin_note" placeholder="Raison" maxlength="500"><button class="ath-btn ap-danger">Marquer remboursé</button></form>
                <?php endif; ?>
                <?php if ($isReq): ?>
                  <form method="post" action="<?= $h($base . '/' . $e['id'] . '/note') ?>" class="ap-act"><?= $csrf('paiements', $ref) ?><input name="admin_note" placeholder="Note interne au suivi (non envoyée)" maxlength="500" required><button class="ath-btn">Ajouter une note</button></form>
                <?php endif; ?>
              </div>
              <ol class="ap-timeline">
                <?php if ($evs === []): ?>
                  <li><span class="ap-timeline__dot"></span><b>Créé</b> <small><?= $h($fmt($e['created_at'])) ?></small></li>
                  <?php if ($e['declared_at']): ?><li><span class="ap-timeline__dot"></span><b>Déclaré par le payeur</b> <small><?= $h($fmt($e['declared_at'])) ?></small></li><?php endif; ?>
                  <?php if ($e['decided_at'] && $e['status'] !== 'declared'): ?><li><span class="ap-timeline__dot"></span><b><?= $h($tl) ?></b> <small><?= $h($fmt($e['decided_at'])) ?></small></li><?php endif; ?>
                <?php else: foreach ($evs as $ev): ?>
                  <li><span class="ap-timeline__dot ap-feed__dot--<?= $h($ev['kind']) ?>"></span><b><?= $h($eventLabels[$ev['kind']] ?? $ev['kind']) ?></b> <small><?= $h($fmt($ev['created_at'])) ?><?= ($ev['actor_name'] ?? '') !== '' ? ' · ' . $h($ev['actor_name']) : '' ?></small><?php if (($ev['note'] ?? '') !== ''): ?><p><?= $h($ev['note']) ?></p><?php endif; ?></li>
                <?php endforeach; endif; ?>
              </ol>
            </div>
          </td></tr>
        </tbody>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if ((int) ($payRowsTotal ?? 0) > count($rows)): ?><p class="ap-muted ap-pad">Affichage des <?= count($rows) ?> plus récentes sur <?= (int) $payRowsTotal ?> : affinez la recherche ou exportez.</p><?php endif; ?>
    <?php endif; ?>
  </section>

  <?php elseif ($view === 'abonnements'):
    $apiSubs = is_array($payApiSubs ?? null) ? $payApiSubs : [];
    $lapsed = is_array($payLapsed ?? null) ? $payLapsed : [];
    ?>
  <details class="ap-howto ath-panel" open><summary>Comment fonctionne la récurrence avec PayPal ?</summary>
    <ol>
      <li><b>Pas de prélèvement automatique</b> : chaque période (mois ou année) est une demande ATH-… payée par la communauté sur paypal.me.</li>
      <li><b>Avant la fin</b>, les gestionnaires reçoivent un rappel automatique (N jours avant et la veille). Vous pouvez aussi <b>préparer le renouvellement</b> : une nouvelle demande est créée et le lien de paiement envoyé au payeur.</li>
      <li><b>Paiement déclaré</b> : vous vérifiez sur PayPal et confirmez ; la période s’ajoute à la suite de la précédente, sans perte de jours.</li>
      <li><b>Geste commercial</b> : « Prolonger » ajoute des jours, un mois ou un an sans paiement. « Arrêter » remet la communauté en formule gratuite tout de suite.</li>
    </ol>
  </details>
  <section class="ath-table-panel">
    <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Formules payées en cours (PayPal)</span><span class="ath-table-toolbar__count"><?= count($subs) ?></span><span class="ath-table-toolbar__spacer"></span><span class="ap-muted">Les plus proches de leur fin d’abord.</span></div>
    <?php if ($subs === []): ?><p class="ap-empty">Aucune formule payée en cours.</p><?php else: ?>
    <div class="ath-table-wrap"><table class="ath-table ap-table">
      <thead><tr><th>Communauté</th><th>Formule</th><th class="ath-th-num">Prix</th><th>Fin de période</th><th>Fidélité</th><th>Prochaine étape</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($subs as $s): $d = (int) $s['days_left']; $tone = $d <= 3 ? 'bad' : ($d <= 14 ? 'warn' : 'ok'); $tid = (int) $s['tenant_id']; ?>
        <tr>
          <td><b class="ap-name is-supporter"><?= $h($s['tenant_name']) ?></b><small class="ap-sub"><?= $h($s['payer_email'] ?: '—') ?></small></td>
          <td><?= $h($s['plan_name']) ?> <small class="ap-sub"><?= $s['interval'] === 'yearly' ? 'Annuel' : 'Mensuel' ?> · <?= $h($s['reference']) ?></small></td>
          <td class="ath-td-num"><?= $h($money($s['price_cents'] ?? null)) ?></td>
          <td><span class="ath-tag ath-tag--<?= $tone ?>"><?= $d === 0 ? 'aujourd’hui' : 'dans ' . $d . ' j' ?></span><small class="ap-sub"><?= $h($fmt($s['ends_at'], 'd/m/Y')) ?></small></td>
          <td><?= (int) $s['history']['periods'] ?> période(s)<small class="ap-sub"><?= $h($money($s['history']['total_cents'])) ?> depuis <?= $h($fmt($s['history']['first_at'], 'm/Y')) ?></small></td>
          <td><?php if ($s['open']): ?><a href="<?= $h($base . '?vue=paiements&ref=' . rawurlencode((string) $s['open']['reference'])) ?>" class="ap-mono"><?= $h($s['open']['reference']) ?></a> <span class="ath-tag ath-tag--<?= ($statuses[$s['open']['status']][1] ?? 'neut') ?>"><?= $h($statuses[$s['open']['status']][0] ?? '') ?></span><?php else: ?><span class="ap-muted"><?= $d <= 14 ? 'Rappel automatique envoyé aux gestionnaires' : 'Rappel automatique avant la fin' ?></span><?php endif; ?></td>
          <td class="ap-right ap-rowacts">
            <form method="post" action="<?= $h($base . '/communaute/' . $tid . '/renouveler') ?>"><?= $csrf('abonnements') ?><button class="ath-btn<?= $d <= 14 && !$s['open'] ? ' ath-btn--solid' : '' ?>"><?= $s['open'] ? 'Renvoyer le lien' : 'Préparer le renouvellement' ?></button></form>
            <details class="ap-menu"><summary class="ath-btn">Plus</summary>
              <div class="ap-menu__pop">
                <form method="post" action="<?= $h($base . '/communaute/' . $tid . '/prolonger') ?>"><?= $csrf('abonnements') ?>
                  <label>Prolonger sans paiement<select name="span"><option value="7">+7 jours</option><option value="14">+14 jours</option><option value="30">+30 jours</option><option value="month">+1 mois</option><option value="year">+1 an</option></select></label>
                  <input name="admin_note" placeholder="Motif (envoyé à la communauté)" maxlength="500"><button class="ath-btn">Prolonger</button></form>
                <form method="post" action="<?= $h($base . '/communaute/' . $tid . '/arreter') ?>" data-ap-confirm="Arrêter la formule de <?= $h($s['tenant_name']) ?> maintenant ? Elle repasse en gratuit."><?= $csrf('abonnements') ?>
                  <input name="admin_note" placeholder="Motif de l’arrêt" maxlength="500"><button class="ath-btn ap-danger">Arrêter la formule</button></form>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <section class="ath-table-panel" id="perdues">
    <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Communautés perdues (60 jours)</span><span class="ath-table-toolbar__count"><?= count($lapsed) ?></span><span class="ath-table-toolbar__spacer"></span><span class="ap-muted">Formule terminée et pas renouvelée : un lien de paiement suffit souvent.</span></div>
    <?php if ($lapsed === []): ?><p class="ap-empty">Aucune communauté perdue récemment.</p><?php else: ?>
    <div class="ath-table-wrap"><table class="ath-table ap-table"><thead><tr><th>Communauté</th><th>Ancienne formule</th><th>Terminée</th><th>Payeur</th><th></th></tr></thead><tbody>
      <?php foreach ($lapsed as $l): ?>
        <tr><td><b><?= $h($l['tenant_name']) ?></b></td><td><?= $h($planNames[$l['plan_slug']] ?? $l['plan_slug']) ?> <small class="ap-sub"><?= $l['interval'] === 'yearly' ? 'Annuel' : 'Mensuel' ?></small></td>
          <td><span class="ath-tag ath-tag--bad">il y a <?= (int) $l['days_ago'] ?> j</span></td><td><?= $h($l['payer_email'] ?: '—') ?></td>
          <td class="ap-right"><form method="post" action="<?= $h($base . '/communaute/' . (int) $l['tenant_id'] . '/renouveler') ?>"><?= $csrf('abonnements') ?><button class="ath-btn ath-btn--solid">Envoyer une offre de reprise</button></form></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </section>

  <?php if ($apiSubs !== []): ?>
  <section class="ath-table-panel">
    <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Abonnements automatiques (prestataire)</span><span class="ath-table-toolbar__count"><?= count($apiSubs) ?></span><span class="ath-table-toolbar__spacer"></span><span class="ap-muted">Prélevés par le prestataire : à gérer depuis son tableau de bord.</span></div>
    <div class="ath-table-wrap"><table class="ath-table ap-table"><thead><tr><th>Communauté</th><th>Formule</th><th>État</th><th>Fin de période</th><th>Identifiant</th></tr></thead><tbody>
      <?php foreach ($apiSubs as $t): ?><tr><td><b><?= $h($t['name']) ?></b></td><td><?= $h($planNames[$t['plan_slug']] ?? $t['plan_slug']) ?></td><td><span class="ath-tag ath-tag--<?= in_array($t['subscription_status'], ['active', 'trialing'], true) ? 'ok' : 'warn' ?>"><?= $h($t['subscription_status']) ?></span></td><td><?= $h($fmt($t['subscription_current_period_end'] ?? null, 'd/m/Y')) ?></td><td class="ap-mono"><?= $h($t['stripe_subscription_id']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
  <?php endif; ?>

  <?php elseif ($view === 'dons'): ?>
  <div class="ap-grid ap-grid--main">
    <section class="ath-panel ap-card">
      <header class="ap-card__head"><h2 class="ap-card__t">Enregistrer un don</h2></header>
      <p class="ap-muted">Pour un don reçu hors du site (paypal.me, virement…) : le don entre dans les statistiques et la personne reçoit le rôle donateur sur toutes ses communautés.</p>
      <form method="post" action="<?= $h($base . '/dons') ?>" class="ap-form" autocomplete="off"><?= $csrf('dons') ?>
        <label class="ap-field ap-field--wide ap-member"><span>Membre (nom ou e-mail)</span><input name="donor_email" type="email" placeholder="Rechercher un membre ou saisir l’e-mail du donateur" data-ap-member><ul class="ap-member__list" data-ap-member-list hidden></ul><small>Laisser vide pour un don anonyme (sans rôle).</small></label>
        <label class="ap-field"><span>Nom affiché</span><input name="donor_name" maxlength="120" placeholder="Ex. Kodiak"></label>
        <label class="ap-field"><span>Montant (€)</span><input name="amount" inputmode="decimal" required placeholder="20,00" pattern="\d+([.,]\d{1,2})?"></label>
        <label class="ap-field"><span>Canal</span><select name="channel"><?php foreach (['paypal', 'virement', 'especes', 'autre'] as $c): ?><option value="<?= $c ?>"><?= $h($channels[$c]) ?></option><?php endforeach; ?></select></label>
        <label class="ap-field"><span>Reçu le</span><input name="received_at" type="date" value="<?= date('Y-m-d') ?>"></label>
        <label class="ap-field"><span>Transaction</span><input name="transaction_ref" maxlength="80" placeholder="Ex. 8AB12345CD678901E"></label>
        <label class="ap-field"><span>Au nom de la communauté</span><select name="tenant_id"><option value="0">Aucune (don personnel)</option><?php asort($tenantNames); foreach ($tenantNames as $tid => $tn): ?><option value="<?= (int) $tid ?>"><?= $h($tn) ?></option><?php endforeach; ?></select></label>
        <label class="ap-field ap-field--wide"><span>Note interne</span><input name="note" maxlength="500" placeholder="Ex. Don pour l’hébergement du serveur, merci envoyé sur Discord"></label>
        <label class="ap-checkline ap-field--wide"><input type="checkbox" name="is_public" value="1" checked> Peut apparaître parmi les remerciements publics</label>
        <div class="ap-field--wide ap-formbar"><button class="ath-btn ath-btn--solid">Enregistrer le don</button></div>
      </form>
    </section>

    <section class="ath-panel ap-card">
      <header class="ap-card__head"><h2 class="ap-card__t">Rôle donateur sur le site</h2></header>
      <ul class="ap-tiers">
        <?php foreach (DonorService::TIERS as $key => $t): ?>
          <li><span class="ap-name is-donor is-donor--<?= $h($key) ?>">Kodiak</span><span class="ath-donor-mark ath-donor-mark--<?= $h($key) ?>"><svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z" fill="currentColor"/></svg><span><?= $h($t['label']) ?></span></span><small>dès <?= $h($money($t['min'])) ?> donnés au total</small></li>
        <?php endforeach; ?>
      </ul>
      <p class="ap-muted">Le palier suit le total des dons (Stripe ATAK, soutien du cœur, dons enregistrés). Le donateur reçoit le badge « <?= $h(DonorService::BADGE_NAME) ?> » sur chacune de ses communautés, et son nom porte ce liseré dans l’annuaire, les effectifs et la barre latérale. Les communautés soutien sont mises en avant dans l’annuaire public.</p>
      <div class="ap-supporter-demo" aria-hidden="true"><span class="ap-demo-card is-supporter"><b>Escadron Alpha</b><span class="ap-demo-ribbon">♥ Soutient Athena</span></span></div>
      <form method="post" action="<?= $h($base . '/donateurs/attribuer') ?>" class="ap-form ap-form--inline"><?= $csrf('dons') ?>
        <label class="ap-field ap-field--wide"><span>Attribuer sans don enregistré</span><input name="email" type="email" required placeholder="E-mail du membre (partenaire, contributeur…)"></label>
        <label class="ap-field"><span>Palier</span><select name="tier"><?php foreach (DonorService::TIERS as $key => $t): ?><option value="<?= $h($key) ?>"><?= $h($t['label']) ?></option><?php endforeach; ?></select></label>
        <div class="ap-formbar"><button class="ath-btn">Attribuer</button></div>
      </form>
      <form method="post" action="<?= $h($base . '/donateurs/recalculer') ?>" class="ap-recalc"><?= $csrf('dons') ?><span class="ap-muted">Dernier recalcul : <?= $h(($payDonorCacheAt ?? '') !== '' ? $fmt((string) $payDonorCacheAt) : 'jamais') ?> (automatique chaque jour).</span><button class="ath-btn">Recalculer maintenant</button></form>
    </section>
  </div>

  <section class="ath-table-panel">
    <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Donateurs</span><span class="ath-table-toolbar__count"><?= count($donors) ?></span><span class="ath-table-toolbar__spacer"></span><span class="ap-muted">Classés par total donné · <?= (int) ($payBoardCount ?? 0) ?> sur le mur public.</span><a class="ath-btn" href="<?= $h(url('soutenir-atak') . '#donateurs') ?>" target="_blank" rel="noopener">Voir le mur</a></div>
    <?php if ($donors === []): ?><p class="ap-empty">Pas encore de donateur. Ils apparaissent après un don payé ou une attribution.</p><?php else: ?>
    <div class="ath-table-wrap"><table class="ath-table ap-table"><thead><tr><th>Donateur</th><th>Palier</th><th class="ath-th-num">Total</th><th class="ath-th-num">Dons</th><th>Dernier don</th><th>Origine</th><th>Mur public</th><th></th></tr></thead><tbody>
      <?php foreach ($donors as $d): $isRevoked = in_array($d['email'], $revoked, true); ?>
        <tr<?= $isRevoked ? ' class="is-muted"' : '' ?>>
          <td><b class="ap-name<?= $isRevoked ? '' : ' is-donor is-donor--' . $h($d['tier']) ?>"><?= $h($d['name'] !== '' ? $d['name'] : ($d['email'] ?: 'Anonyme')) ?></b><small class="ap-sub"><?= $h($d['email']) ?></small></td>
          <td><span class="ath-tag ath-tag--<?= $tierTone[$d['tier']] ?? 'neut' ?>"><?= $h(DonorService::tierLabel($d['tier'])) ?></span></td>
          <td class="ath-td-num"><b><?= $h($money($d['total_cents'])) ?></b></td>
          <td class="ath-td-num"><?= (int) $d['count'] ?></td>
          <td><?= $h($fmt($d['last_at'], 'd/m/Y')) ?></td>
          <td><?= $d['manual'] ? '<span class="ath-tag ath-tag--info">Attribué</span>' : '<span class="ap-muted">Dons</span>' ?><?= $isRevoked ? ' <span class="ath-tag ath-tag--bad">Retiré</span>' : '' ?></td>
          <td><?php if ($d['email'] !== '' && !$isRevoked): $onWall = !empty($d['public']) && !in_array($d['email'], $wallHidden, true); ?>
            <form method="post" action="<?= $h($base . '/donateurs/mur') ?>" class="ap-inline"><?= $csrf('dons') ?><input type="hidden" name="email" value="<?= $h($d['email']) ?>"><input type="hidden" name="visible" value="<?= $onWall ? '0' : '1' ?>"><span class="ath-tag ath-tag--<?= $onWall ? 'ok' : 'neut' ?>"><?= $onWall ? 'Affiché' : (empty($d['public']) ? 'Don non public' : 'Masqué') ?></span> <button class="ap-copy"><?= $onWall ? 'Masquer' : 'Afficher' ?></button></form>
          <?php endif; ?></td>
          <td class="ap-right"><?php if ($d['email'] !== ''): ?>
            <form method="post" action="<?= $h($base . '/donateurs/' . ($isRevoked ? 'retablir' : 'retirer')) ?>"<?= $isRevoked ? '' : ' data-ap-confirm="Retirer le rôle donateur à ' . $h($d['email']) . ' ? Ses dons restent enregistrés."' ?>><?= $csrf('dons') ?><input type="hidden" name="email" value="<?= $h($d['email']) ?>"><button class="ath-btn<?= $isRevoked ? '' : ' ap-danger' ?>"><?= $isRevoked ? 'Rétablir' : 'Retirer le rôle' ?></button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </section>

  <div class="ap-grid ap-grid--2">
    <section class="ath-table-panel">
      <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Communautés soutien</span><span class="ath-table-toolbar__count"><?= count($supporters) ?></span></div>
      <?php if ($supporters === []): ?><p class="ap-empty">Aucune pour l’instant.</p><?php else: ?>
      <ul class="ap-supporters">
        <?php foreach ($supporters as $tid => $s): ?><li><b class="ap-name is-supporter"><?= $h($tenantNames[$tid] ?? ('#' . $tid)) ?></b><span class="ath-tag ath-tag--<?= $s['kind'] === 'plan' ? 'ok' : ($s['kind'] === 'heart' ? 'warn' : 'info') ?>"><?= $h($s['label']) ?></span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <section class="ath-table-panel">
      <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Derniers dons</span><span class="ath-table-toolbar__count"><?= count($donationRows) ?></span><span class="ath-table-toolbar__spacer"></span><a class="ath-btn" href="<?= $h($base . '?vue=paiements&nature=donations') ?>">Tout voir</a></div>
      <?php if ($donationRows === []): ?><p class="ap-empty">Aucun don.</p><?php else: ?>
      <div class="ath-table-wrap"><table class="ath-table ap-table"><tbody>
        <?php foreach (array_slice($donationRows, 0, 10) as $e): [$tl, $tt] = $statuses[$e['status']] ?? [$e['status'], 'neut']; ?>
          <tr><td><b class="ap-name<?= DonorService::nameClass((int) $e['user_id']) ?>"><?= $h($e['payer_name'] ?: ($e['payer_email'] ?: 'Anonyme')) ?></b><small class="ap-sub"><?= $h(($sources[$e['source']][0] ?? '') . ' · ' . $fmt($e['paid_at'] ?? $e['created_at'], 'd/m/Y')) ?></small></td><td class="ath-td-num"><b><?= $h($money((int) $e['amount_cents'])) ?></b></td><td><span class="ath-tag ath-tag--<?= $tt ?>"><?= $h($tl) ?></span></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </section>
  </div>

  <?php else:
    $offers = is_array($payOffers ?? null) ? $payOffers : [];
    $pv = is_array($payProviders ?? null) ? $payProviders : [];
    ?>
  <div class="ap-grid ap-grid--2">
    <form method="post" action="<?= $h($base . '/reglages') ?>" class="ath-panel ap-card"><?= $csrf('reglages') ?>
      <header class="ap-card__head"><h2 class="ap-card__t">Compte PayPal et formules en vente</h2></header>
      <label class="ap-field"><span>Lien paypal.me qui reçoit les paiements</span><input name="paypal_url" type="url" maxlength="200" value="<?= $h($payPaypalUrl ?? '') ?>" placeholder="https://www.paypal.me/MonCompte"><small>Le montant est ajouté au lien automatiquement sur la page de paiement (ex. …/MonCompte/12EUR). Vide : compte par défaut d’Athena.</small></label>
      <p class="ap-label">Formules proposées à la vente</p>
      <ul class="ap-sale">
        <?php foreach ($offers as $o): ?>
          <li><label class="ap-checkline"><input type="checkbox" name="on_sale[]" value="<?= $h($o['slug']) ?>"<?= $o['on_sale'] ? ' checked' : '' ?>> <b><?= $h($o['name']) ?></b></label><span class="ap-muted"><?= $h($money($o['monthly'])) ?> / mois · <?= $h($money($o['yearly'])) ?> / an<?= ($o['monthly'] === null && $o['yearly'] === null) ? ' — prix à régler dans Formules d’accès' : '' ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="ap-muted">Une formule retirée reste active pour les communautés qui l’ont déjà ; seules les nouvelles souscriptions sont bloquées.</p>
      <div class="ap-formbar"><button class="ath-btn ath-btn--solid">Enregistrer</button></div>
    </form>

    <section class="ath-panel ap-card">
      <header class="ap-card__head"><h2 class="ap-card__t">Prestataires de paiement</h2></header>
      <ul class="ap-status">
        <li class="is-ok"><b>PayPal manuel (paypal.me)</b><span>Actif — vérification et activation à la main dans l’onglet Paiements.</span></li>
        <li class="<?= !empty($pv['paypal_api']) ? (!empty($pv['paypal_webhook']) ? 'is-ok' : 'is-warn') : 'is-neut' ?>"><b>PayPal automatique (API, abonnements)</b><span><?= !empty($pv['paypal_api']) ? (!empty($pv['paypal_webhook']) ? 'Configuré, webhook actif.' : 'Identifiants présents mais PAYPAL_WEBHOOK_ID manquant : les paiements ne sont pas confirmés.') : 'Non configuré (PAYPAL_CLIENT_ID / PAYPAL_SECRET).' ?></span></li>
        <li class="<?= !empty($pv['stripe']) ? 'is-ok' : 'is-neut' ?>"><b>Stripe (dons ATAK par carte)</b><span><?= !empty($pv['stripe']) ? 'Configuré.' : 'Non configuré (STRIPE_SECRET_KEY) : la page de soutien ne propose que PayPal.' ?></span></li>
      </ul>
      <p class="ap-label">Adresse du webhook PayPal</p>
      <div class="ap-copyline"><code id="ap-webhook"><?= $h($pv['webhook_url'] ?? '') ?></code><button type="button" class="ath-btn" data-ap-copy="<?= $h($pv['webhook_url'] ?? '') ?>">Copier</button></div>
      <p class="ap-muted">À déclarer dans le tableau de bord développeur PayPal, puis reporter son identifiant dans la variable PAYPAL_WEBHOOK_ID du serveur.</p>
    </section>
  </div>

  <section class="ath-panel ap-card">
    <header class="ap-card__head"><h2 class="ap-card__t">Automatismes</h2><a class="ath-btn" href="<?= $h(url('admin/system/cron')) ?>">Tâches planifiées</a></header>
    <div class="ath-table-wrap"><table class="ath-table ap-table"><thead><tr><th>Quand</th><th>Ce qui se passe</th><th>Pour qui</th></tr></thead><tbody>
      <tr><td>Paiement déclaré</td><td>E-mail « paiement à vérifier », puis relance au bout de 24 h sans décision.</td><td>Administration du site</td></tr>
      <tr><td>Demande jamais payée</td><td>Relance avec le lien de paiement à J+2, J+7 et J+12 ; annulation automatique à J+30.</td><td>Payeur</td></tr>
      <tr><td>Fin de formule proche</td><td>Rappel N jours avant la fin et la veille (réglable dans Formules offertes).</td><td>Gestionnaires de la communauté</td></tr>
      <tr><td>Décision</td><td>E-mail de confirmation, refus, annulation, remboursement, prolongation ou arrêt.</td><td>Payeur</td></tr>
      <tr><td>Chaque jour</td><td>Recalcul des donateurs (paliers, badges) et des communautés soutien.</td><td>Site</td></tr>
    </tbody></table></div>
  </section>
  <?php endif; ?>
</div>
</div>
</div>
<script src="<?= $h(asset_url('assets/js/admin-payments.js')) ?>" defer></script>
