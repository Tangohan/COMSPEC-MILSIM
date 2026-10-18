<?php
declare(strict_types=1);

$hubA = is_array($hubA ?? null) ? $hubA : null;
$hubB = is_array($hubB ?? null) ? $hubB : null;
$hubRows = is_array($hubRows ?? null) ? $hubRows : [];
$hubMetrics = is_array($hubMetrics ?? null) ? $hubMetrics : [];
$hubMedians = is_array($hubMedians ?? null) ? $hubMedians : [];
$structures = ['a' => is_array($hubStructureA ?? null) ? $hubStructureA : null, 'b' => is_array($hubStructureB ?? null) ? $hubStructureB : null];
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
        'age_days' => $value >= 365 ? $num($value / 365, 1) . ' an(s)' : $num($value) . ' j',
        default => $num($value),
    };
};
$typeLabels = ['group' => 'Groupes', 'section' => 'Sections', 'squad' => 'Escouades', 'team' => 'Équipes'];
$maxLevel = 1;
foreach ($structures as $st) {
    foreach ($st['levels'] ?? [] as $n) {
        $maxLevel = max($maxLevel, (int) $n);
    }
}
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/hub-ecosystem.css')) ?>">

<div class="hubx">
  <div class="hx-wrap pt-8">
    <a href="<?= $h(url('hub')) ?>#annuaire" class="text-sm font-semibold underline">← Hub des communautés</a>
    <div class="hx-section__head mt-4">
      <div>
        <p class="hx-kicker">Comparaison</p>
        <h1 class="hx-title" style="font-size: clamp(1.5rem, 3vw, 2rem)">Deux communautés côte à côte</h1>
        <p class="hx-sub">Indicateurs publics et forme de l’organigramme. Les noms d’unités ne s’affichent que pour votre communauté et celles qui publient leur organigramme.</p>
      </div>
    </div>

    <form method="get" action="<?= $h(url('hub/comparer')) ?>" class="hx-card hx-filters" style="grid-template-columns: 1fr 1fr auto">
      <?php foreach (['a' => $hubA, 'b' => $hubB] as $key => $sel): ?>
      <div class="hx-field">
        <label for="hx-cmp-<?= $key ?>">Communauté <?= $key === 'a' ? 'A' : 'B' ?></label>
        <select id="hx-cmp-<?= $key ?>" name="<?= $key ?>" class="hx-select">
          <option value="">— Choisir —</option>
          <?php foreach ($hubRows as $r): ?>
          <option value="<?= $h((string) $r['slug']) ?>"<?= $sel !== null && $sel['slug'] === $r['slug'] ? ' selected' : '' ?>><?= $h((string) $r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="hx-btn hx-btn--accent self-end">Comparer</button>
    </form>

    <?php if ($hubA === null || $hubB === null): ?>
    <p class="hx-empty mt-6">Choisissez deux communautés pour afficher la comparaison.</p>
    <?php else: ?>
    <section class="hx-section hx-card" aria-label="Indicateurs comparés">
      <table class="hx-vs">
        <thead>
          <tr>
            <th scope="col">Indicateur</th>
            <th scope="col" style="text-align:right"><a href="<?= $h(url('hub/c/' . $hubA['slug'])) ?>"><?= $h((string) $hubA['name']) ?></a></th>
            <th scope="col" style="text-align:right"><a href="<?= $h(url('hub/c/' . $hubB['slug'])) ?>"><?= $h((string) $hubB['name']) ?></a></th>
            <th scope="col" style="text-align:right">Médiane du réseau</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hubMetrics as $metric => $label):
            $va = $hubA[$metric] ?? null;
            $vb = $hubB[$metric] ?? null;
            $bestA = $va !== null && ($vb === null || (float) $va > (float) $vb);
            $bestB = $vb !== null && ($va === null || (float) $vb > (float) $va);
          ?>
          <tr>
            <th scope="row"><?= $h((string) $label) ?></th>
            <td class="<?= $bestA ? 'is-best' : '' ?>"><?= $h($fmt($metric, $va)) ?></td>
            <td class="<?= $bestB ? 'is-best' : '' ?>"><?= $h($fmt($metric, $vb)) ?></td>
            <td><?= $h($fmt($metric, $hubMedians[$metric] ?? null)) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="hx-section hx-compare" aria-label="Organigrammes comparés">
      <?php foreach (['a' => $hubA, 'b' => $hubB] as $key => $row):
        $st = $structures[$key] ?? ['levels' => [], 'types' => [], 'tree' => []];
      ?>
      <div class="hx-card">
        <p class="hx-kicker">Organigramme</p>
        <h2 class="hx-title"><?= $h((string) $row['name']) ?></h2>
        <?php if (($st['levels'] ?? []) === []): ?>
        <p class="hx-empty mt-3">Aucune unité déclarée.</p>
        <?php else: ?>
        <p class="hx-sub"><?= $num($row['units']) ?> unités · <?= count($st['levels']) ?> niveaux · <?= $num($row['members']) ?> membres actifs<?= $row['units'] > 0 ? ' · ' . $num($row['members'] / max(1, $row['units']), 1) . ' membres par unité' : '' ?></p>
        <div class="hx-levels mt-3">
          <?php foreach ($st['levels'] as $i => $n): ?>
          <div class="hx-level"><span>Niveau <?= $i + 1 ?></span><span class="hx-level__bar"><span style="width: <?= (int) round($n / $maxLevel * 100) ?>%"></span></span><strong><?= (int) $n ?></strong></div>
          <?php endforeach; ?>
        </div>
        <p class="hx-sub mt-3"><?php
          $bits = [];
          foreach ($st['types'] as $type => $n) {
              $bits[] = ($typeLabels[$type] ?? ucfirst((string) $type)) . ' : ' . (int) $n;
          }
          echo $h(implode(' · ', $bits));
        ?></p>
        <?php if (($st['tree'] ?? []) !== []): ?>
        <ul class="hx-tree">
          <?php foreach ($st['tree'] as $u): ?>
          <li style="padding-left: <?= min(6, (int) $u['depth']) * 0.9 ?>rem"><span><?= $h((string) $u['name']) ?></span><small><?= (int) $u['members'] ?> membre(s)</small></li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="hx-sub mt-3">Détail des unités non public.</p>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
