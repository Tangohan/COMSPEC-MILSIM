<?php
declare(strict_types=1);

/** @var array<string,mixed> $hubRow */
$hubRow = is_array($hubRow ?? null) ? $hubRow : [];
$hubMine = is_array($hubMine ?? null) ? $hubMine : null;
$hubIsOwn = !empty($hubIsOwn);
$hubMedians = is_array($hubMedians ?? null) ? $hubMedians : [];
$hubMetrics = is_array($hubMetrics ?? null) ? $hubMetrics : [];
$hubStructure = is_array($hubStructure ?? null) ? $hubStructure : ['levels' => [], 'types' => [], 'tree' => [], 'public' => false];
$hubReviews = is_array($hubReviews ?? null) ? $hubReviews : [];
$hubRecommended = !empty($hubRecommended);
$hubCanCooperate = !empty($hubCanCooperate);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v, int $dec = 0): string => $v === null ? '—' : number_format((float) $v, $dec, ',', "\u{202F}");
$fmt = static function (string $metric, $value) use ($num): string {
    if ($value === null) {
        return '—';
    }

    return match ($metric) {
        'activity' => $num($value) . ' %',
        'ops_per_month' => $num($value, 1),
        'rating' => $num($value, 1) . ' / 5',
        'age_days' => $value >= 365 ? $num($value / 365, 1) . ' an(s)' : $num($value) . ' jour(s)',
        default => $num($value),
    };
};
$typeLabels = ['group' => 'Groupes', 'section' => 'Sections', 'squad' => 'Escouades', 'team' => 'Équipes'];
$stars = static fn (int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$maxLevel = max([1, ...array_map('intval', $hubStructure['levels'])]);
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/hub-ecosystem.css')) ?>">

<div class="hubx">
  <div class="hx-wrap pt-8">
    <a href="<?= $h(url('hub')) ?>#annuaire" class="text-sm font-semibold underline">← Hub des communautés</a>
    <?php if ($flashOk): ?><p class="hx-flash hx-flash--ok" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr): ?><p class="hx-flash hx-flash--err" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>

    <header class="hx-section__head mt-4">
      <div>
        <p class="hx-kicker"><?= $h($hubRow['game'] !== '' ? (string) $hubRow['game'] : 'Communauté') ?><?= $hubRow['unit_label'] !== '' ? ' · ' . $h((string) $hubRow['unit_label']) : '' ?></p>
        <h1 class="hx-title" style="font-size: clamp(1.6rem, 3vw, 2.2rem)"><?= $h((string) $hubRow['name']) ?></h1>
        <?php if ($hubRow['tagline'] !== ''): ?><p class="hx-sub"><?= $h((string) $hubRow['tagline']) ?></p><?php endif; ?>
        <p class="hx-sub">
          <?= $hubRow['recruitment_open'] ? '<span class="hx-pill">Recrutement ouvert</span>' : '<span class="hx-pill hx-pill--muted">Recrutement fermé</span>' ?>
          <?php if ($hubRow['created_at']): ?> · Sur Athena depuis le <?= $h(date('d/m/Y', strtotime((string) $hubRow['created_at']))) ?><?php endif; ?>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="hx-btn hx-btn--line" href="<?= $h(url('c/' . $hubRow['slug'])) ?>">Page publique</a>
        <?php if (!$hubIsOwn): ?>
        <form method="post" action="<?= $h(url('hub/c/' . $hubRow['slug'] . '/recommander')) ?>">
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
          <input type="hidden" name="back" value="hub/c/<?= $h((string) $hubRow['slug']) ?>">
          <button type="submit" class="hx-btn hx-btn--line" aria-pressed="<?= $hubRecommended ? 'true' : 'false' ?>"><?= $hubRecommended ? '♥ Recommandée' : '♡ Recommander' ?> · <?= (int) $hubRow['recommendations'] ?></button>
        </form>
        <?php endif; ?>
        <?php if ($hubMine !== null): ?>
        <a class="hx-btn hx-btn--line" href="<?= $h(url('hub/comparer') . '?a=' . rawurlencode((string) $hubMine['slug']) . '&b=' . rawurlencode((string) $hubRow['slug'])) ?>">Comparer avec <?= $h((string) $hubMine['name']) ?></a>
        <?php endif; ?>
        <?php if ($hubCanCooperate): ?>
        <a class="hx-btn hx-btn--accent" href="<?= $h(cooperation_mission_create_url() . '?' . http_build_query(['partenaire' => (int) $hubRow['id']])) ?>">Proposer une coopération</a>
        <?php endif; ?>
      </div>
    </header>

    <section class="hx-section" aria-labelledby="hx-ind">
      <h2 id="hx-ind" class="hx-title">Indicateurs</h2>
      <p class="hx-sub">Valeurs agrégées, comparées à la médiane du réseau.</p>
      <div class="hx-kpis mt-3">
        <?php foreach ($hubMetrics as $metric => $label): ?>
        <div class="hx-kpi">
          <p class="hx-kpi__label"><?= $h((string) $label) ?></p>
          <p class="hx-kpi__value"><?= $h($fmt($metric, $hubRow[$metric] ?? null)) ?></p>
          <p class="hx-kpi__meta"><span>Médiane <?= $h($fmt($metric, $hubMedians[$metric] ?? null)) ?></span><?php if ($metric === 'rating' && $hubRow['rating_count'] > 0): ?><span><?= (int) $hubRow['rating_count'] ?> avis</span><?php endif; ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="hx-section hx-compare" aria-label="Structure et avis">
      <div class="hx-card">
        <p class="hx-kicker">Organigramme</p>
        <h2 class="hx-title">Structure</h2>
        <?php if ($hubStructure['levels'] === []): ?>
        <p class="hx-empty mt-3">Aucune unité déclarée.</p>
        <?php else: ?>
        <p class="hx-sub"><?= $num($hubRow['units']) ?> unités sur <?= count($hubStructure['levels']) ?> niveaux.</p>
        <div class="hx-levels mt-3">
          <?php foreach ($hubStructure['levels'] as $i => $n): ?>
          <div class="hx-level"><span>Niveau <?= $i + 1 ?></span><span class="hx-level__bar"><span style="width: <?= (int) round($n / $maxLevel * 100) ?>%"></span></span><strong><?= (int) $n ?></strong></div>
          <?php endforeach; ?>
        </div>
        <p class="hx-sub mt-3"><?php
          $bits = [];
          foreach ($hubStructure['types'] as $type => $n) {
              $bits[] = ($typeLabels[$type] ?? ucfirst((string) $type)) . ' : ' . (int) $n;
          }
          echo $h(implode(' · ', $bits));
        ?></p>
        <?php if ($hubStructure['tree'] !== []): ?>
        <ul class="hx-tree">
          <?php foreach ($hubStructure['tree'] as $u): ?>
          <li style="padding-left: <?= min(6, (int) $u['depth']) * 0.9 ?>rem"><span><?= $h((string) $u['name']) ?></span><small><?= (int) $u['members'] ?> membre(s)</small></li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="hx-sub mt-3">Le détail des unités n’est pas public pour cette communauté ; seuls les volumes par niveau sont affichés.</p>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="hx-card">
        <p class="hx-kicker">Après coopération</p>
        <h2 class="hx-title">Avis des partenaires</h2>
        <?php if ($hubReviews === []): ?>
        <p class="hx-empty mt-3">Pas encore d’avis. Les avis viennent uniquement des communautés qui ont mené une coopération clôturée avec elle.</p>
        <?php else: ?>
        <ul class="mt-3 grid gap-3">
          <?php foreach ($hubReviews as $rev): ?>
          <li class="hx-resp__item" style="display:block">
            <p><span class="hx-stars"><?= $h($stars((int) $rev['rating'])) ?></span> <strong><?= $h((string) $rev['reviewer_name']) ?></strong> <small class="hx-dash">· <?= $h((string) $rev['mission_title']) ?> · <?= $h(date('d/m/Y', strtotime((string) $rev['created_at']))) ?></small></p>
            <p class="hx-sub">
              <?php foreach (['reliability' => 'Fiabilité', 'organisation' => 'Organisation', 'ambiance' => 'Esprit d’équipe'] as $k => $lab): ?>
              <?php if ($rev[$k] !== null): ?><?= $h($lab) ?> <?= (int) $rev[$k] ?>/5 · <?php endif; ?>
              <?php endforeach; ?>
            </p>
            <?php if (!empty($rev['comment'])): ?><p class="mt-1 text-sm"><?= nl2br($h((string) $rev['comment'])) ?></p><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
