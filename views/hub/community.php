<?php
declare(strict_types=1);

/** @var array<string,mixed> $hubRow */
$hubRow = is_array($hubRow ?? null) ? $hubRow : [];
$hubMine = is_array($hubMine ?? null) ? $hubMine : null;
$hubIsOwn = !empty($hubIsOwn);
$hubMedians = is_array($hubMedians ?? null) ? $hubMedians : [];
$hubMetrics = is_array($hubMetrics ?? null) ? $hubMetrics : [];
$hubRanks = is_array($hubRanks ?? null) ? $hubRanks : [];
$hubPeaks = is_array($hubPeaks ?? null) ? $hubPeaks : [];
$hubNetworkSize = (int) ($hubNetworkSize ?? 0);
$hubStructure = is_array($hubStructure ?? null) ? $hubStructure : ['levels' => [], 'types' => [], 'tree' => [], 'public' => false];
$hubReviews = is_array($hubReviews ?? null) ? $hubReviews : [];
$hubRecommended = !empty($hubRecommended);
$hubCanCooperate = !empty($hubCanCooperate);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v, int $dec = 0): string => $v === null ? '—' : number_format((float) $v, $dec, ',', "\u{202F}");
$ordinal = static fn (int $n): string => $n === 1 ? '1er' : $n . 'e';
$fmt = static function (string $metric, $value) use ($num): string {
    if ($value === null) {
        return '—';
    }

    return match ($metric) {
        'activity' => $num($value) . ' %',
        'ops_per_month' => (float) $value == 0.0 ? '0' : $num($value, 1),
        'rating' => $num($value, 1) . ' / 5',
        'age_days' => (static function ($days) use ($num): string {
            $days = (int) $days;
            if ($days >= 365) {
                $years = $days / 365;

                return abs($years - round($years)) < 0.05
                    ? ((int) round($years)) . ((int) round($years) > 1 ? ' ans' : ' an')
                    : $num($years, 1) . ' ans';
            }

            return $days . ($days > 1 ? ' jours' : ' jour');
        })($value),
        default => $num($value),
    };
};
$typeLabels = ['group' => 'Groupes', 'section' => 'Sections', 'squad' => 'Escouades', 'team' => 'Équipes'];
$stars = static fn (int $n): string => str_repeat('★', max(0, min(5, $n))) . str_repeat('☆', max(0, 5 - $n));
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$levelCounts = array_map('intval', $hubStructure['levels']);
$maxLevel = max([1, ...$levelCounts]);
$name = (string) ($hubRow['name'] ?? '');
$initials = mb_strtoupper(mb_substr($name, 0, 2));
$logo = (string) ($hubRow['logo'] ?? '');
$hasLogo = $logo !== '' && filter_var($logo, FILTER_VALIDATE_URL);
$groups = [
    'Effectif' => ['members', 'activity', 'age_days'],
    'Rythme' => ['ops_per_month', 'sse_records'],
    'Organisation' => ['units', 'depth'],
    'Réseau' => ['cooperations', 'recommendations', 'rating'],
];
$members = (int) ($hubRow['members'] ?? 0);
$activity = $hubRow['activity'] ?? null;
$units = (int) ($hubRow['units'] ?? 0);
$depth = (int) ($hubRow['depth'] ?? 0);
$ops = (float) ($hubRow['ops_per_month'] ?? 0);
$upcoming = (int) ($hubRow['upcoming_ops'] ?? 0);
$recos = (int) ($hubRow['recommendations'] ?? 0);
$reading = [];
$who = $members . ' membre' . ($members > 1 ? 's actifs' : ' actif');
if ($activity !== null) {
    $who .= (int) $activity === 100
        ? ', tous présents sur les 30 derniers jours'
        : ', ' . $num($activity) . ' % présents sur les 30 derniers jours';
}
$reading[] = $who . '.';
if ($units > 0) {
    $reading[] = $units . ' unité' . ($units > 1 ? 's' : '') . ' sur ' . max(1, $depth) . ' niveau' . ($depth > 1 ? 'x' : '') . '.';
}
$reading[] = $ops <= 0
    ? 'Aucune opération sur les trois derniers mois.'
    : $num($ops, 1) . ' opération' . ($ops > 1 ? 's' : '') . ' par mois.';
if ($upcoming > 0) {
    $reading[] = $upcoming . ' opération' . ($upcoming > 1 ? 's' : '') . ' à venir.';
}
if ($recos > 0) {
    $reading[] = $recos . ' recommandation' . ($recos > 1 ? 's' : '') . ' du réseau.';
}
$since = !empty($hubRow['created_at']) ? date('d/m/Y', strtotime((string) $hubRow['created_at'])) : '';
?>
<?php
$hxBack = [url('hub') . '#annuaire', 'Hub des communautés'];
$hxKicker = $hubIsOwn ? 'Votre communauté · fiche comparée' : 'Communauté du réseau · fiche comparée';
$hxTitle = $name;
$hxLead = '';
require __DIR__ . '/partials/shell_open.php';
?>
  <div class="hx-profile">
    <header class="hx-profile__head">
      <div class="hx-profile__id">
        <?php if ($hasLogo): ?>
        <img class="hx-profile__mark" src="<?= $h($logo) ?>" alt="">
        <?php else: ?>
        <span class="hx-profile__mark" aria-hidden="true"><?= $h($initials) ?></span>
        <?php endif; ?>
        <div>
          <?php if (($hubRow['tagline'] ?? '') !== ''): ?><p class="hx-profile__tag"><?= $h((string) $hubRow['tagline']) ?></p><?php endif; ?>
          <ul class="hx-chips">
            <?php if (($hubRow['game'] ?? '') !== ''): ?><li><?= $h((string) $hubRow['game']) ?></li><?php endif; ?>
            <?php if (($hubRow['unit_label'] ?? '') !== ''): ?><li><?= $h((string) $hubRow['unit_label']) ?></li><?php endif; ?>
            <?php if (($hubRow['locale'] ?? '') !== ''): ?><li><?= $h((string) $hubRow['locale']) ?></li><?php endif; ?>
            <li class="<?= !empty($hubRow['recruitment_open']) ? 'is-open' : '' ?>"><?= !empty($hubRow['recruitment_open']) ? 'Recrutement ouvert' : 'Recrutement fermé' ?></li>
            <?php if ($since !== ''): ?><li>Sur Athena depuis le <?= $h($since) ?></li><?php endif; ?>
          </ul>
        </div>
      </div>
      <div class="hx-profile__actions">
        <a class="hx-btn hx-btn--line" href="<?= $h(url('c/' . $hubRow['slug'])) ?>">Page publique</a>
        <?php if (!$hubIsOwn): ?>
        <form method="post" action="<?= $h(url('hub/c/' . $hubRow['slug'] . '/recommander')) ?>">
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
          <input type="hidden" name="back" value="hub/c/<?= $h((string) $hubRow['slug']) ?>">
          <button type="submit" class="hx-btn hx-btn--line" aria-pressed="<?= $hubRecommended ? 'true' : 'false' ?>"><?= $hubRecommended ? 'Recommandée' : 'Recommander' ?> · <?= (int) $hubRow['recommendations'] ?></button>
        </form>
        <?php endif; ?>
        <?php if ($hubMine !== null): ?>
        <a class="hx-btn hx-btn--line" href="<?= $h(url('hub/comparer') . '?a=' . rawurlencode((string) $hubMine['slug']) . '&b=' . rawurlencode((string) $hubRow['slug'])) ?>" title="Comparer <?= $h((string) $hubMine['name']) ?> et <?= $h($name) ?>">Comparer avec la vôtre</a>
        <?php endif; ?>
        <?php if ($hubCanCooperate): ?>
        <a class="hx-btn hx-btn--accent" href="<?= $h(cooperation_mission_create_url() . '?' . http_build_query(['partenaire' => (int) $hubRow['id']])) ?>">Proposer une coopération</a>
        <?php endif; ?>
      </div>
    </header>

    <p class="hx-read"><?= $h(implode(' ', $reading)) ?></p>

    <section aria-labelledby="hx-ind">
      <div class="hx-section__head">
        <div>
          <h2 id="hx-ind" class="hx-title">Face au réseau</h2>
          <p class="hx-sub">Chaque barre compare cette communauté à la plus haute valeur du registre<?= $hubNetworkSize > 0 ? ' (' . $hubNetworkSize . ' communautés)' : '' ?>. Le rang place la plus haute en premier.</p>
        </div>
      </div>
      <div class="hx-groups">
        <?php foreach ($groups as $group => $keys): ?>
        <section class="hx-group" aria-label="<?= $h($group) ?>">
          <h3 class="hx-kicker"><?= $h($group) ?></h3>
          <?php foreach ($keys as $metric):
            if (!isset($hubMetrics[$metric])) {
                continue;
            }
            $value = $hubRow[$metric] ?? null;
            $median = $hubMedians[$metric] ?? null;
            $rank = $hubRanks[$metric] ?? null;
            $peak = (float) ($hubPeaks[$metric] ?? 0);
            $pct = ($value !== null && $peak > 0) ? (int) round((float) $value / $peak * 100) : 0;
            $cmp = ($value !== null && $median !== null) ? ((float) $value <=> (float) $median) : 0;
            $tone = $cmp > 0 ? 'hx-up' : ($cmp < 0 ? 'hx-down' : '');
            $zero = $value === null || (float) $value == 0.0;
          ?>
          <div class="hx-metric<?= $zero ? ' is-zero' : '' ?>">
            <p class="hx-metric__label"><?= $h((string) $hubMetrics[$metric]) ?></p>
            <p class="hx-metric__value"><?= $h($fmt($metric, $value)) ?></p>
            <div class="hx-metric__bar" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></div>
            <p class="hx-metric__meta">
              <span>Médiane <?= $h($fmt($metric, $median)) ?></span>
              <?php if ($rank !== null && $hubNetworkSize > 0 && $peak > 0): ?>
              <span<?= $tone !== '' ? ' class="' . $tone . '"' : '' ?>><?= $h($ordinal((int) $rank)) ?> / <?= $hubNetworkSize ?></span>
              <?php endif; ?>
              <?php if ($metric === 'rating' && (int) ($hubRow['rating_count'] ?? 0) > 0): ?>
              <span><?= (int) $hubRow['rating_count'] ?> avis</span>
              <?php endif; ?>
            </p>
          </div>
          <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="hx-split" aria-label="Structure et avis">
      <div class="hx-card">
        <p class="hx-kicker">Organigramme</p>
        <h2 class="hx-title">Structure</h2>
        <?php if ($levelCounts === []): ?>
        <p class="hx-note">Aucune unité déclarée.</p>
        <?php else: ?>
        <p class="hx-sub"><?= $num($units) ?> unités sur <?= count($levelCounts) ?> niveau<?= count($levelCounts) > 1 ? 'x' : '' ?>.</p>
        <div class="hx-levels mt-3">
          <?php foreach ($levelCounts as $i => $n): ?>
          <div class="hx-level">
            <span><?= $i === 0 ? '1er niveau' : ($i + 1) . 'e niveau' ?></span>
            <span class="hx-level__bar"><span style="width: <?= (int) round($n / $maxLevel * 100) ?>%"></span></span>
            <strong><?= (int) $n ?></strong>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($hubStructure['types'] !== []): ?>
        <ul class="hx-types">
          <?php foreach ($typeLabels as $type => $label): ?>
          <?php if (!isset($hubStructure['types'][$type])) { continue; } ?>
          <li><?= $h($label) ?> <strong><?= (int) $hubStructure['types'][$type] ?></strong></li>
          <?php endforeach; ?>
          <?php foreach ($hubStructure['types'] as $type => $n): ?>
          <?php if (isset($typeLabels[$type])) { continue; } ?>
          <li><?= $h(ucfirst((string) $type)) ?> <strong><?= (int) $n ?></strong></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($hubStructure['tree'] !== []): ?>
        <ul class="hx-tree">
          <?php foreach ($hubStructure['tree'] as $u): ?>
          <li style="padding-left: <?= min(6, (int) $u['depth']) * 0.9 ?>rem"><span><?= $h((string) $u['name']) ?></span><small><?= (int) $u['members'] ?> membre<?= (int) $u['members'] > 1 ? 's' : '' ?></small></li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="hx-note">Cette communauté ne publie pas le nom de ses unités. Seuls les volumes par niveau sont visibles.</p>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="hx-card">
        <p class="hx-kicker">Après coopération</p>
        <h2 class="hx-title">Avis des partenaires</h2>
        <?php if ($hubReviews === []): ?>
        <p class="hx-note">Pas encore d’avis. Seules les communautés qui ont mené une coopération terminée avec celle-ci peuvent en laisser un.</p>
        <?php else: ?>
        <ul class="hx-reviews">
          <?php foreach ($hubReviews as $rev): ?>
          <li class="hx-review">
            <p class="hx-review__head">
              <span class="hx-stars" aria-label="<?= (int) $rev['rating'] ?> sur 5"><?= $h($stars((int) $rev['rating'])) ?></span>
              <strong><?= $h((string) $rev['reviewer_name']) ?></strong>
            </p>
            <p class="hx-review__meta"><?= $h((string) $rev['mission_title']) ?> · <?= $h(date('d/m/Y', strtotime((string) $rev['created_at']))) ?></p>
            <p class="hx-review__scores">
              <?php
              $scores = [];
              foreach (['reliability' => 'Fiabilité', 'organisation' => 'Organisation', 'ambiance' => 'Esprit d’équipe'] as $k => $lab) {
                  if ($rev[$k] !== null) {
                      $scores[] = $lab . ' ' . (int) $rev[$k] . '/5';
                  }
              }
              echo $h(implode(' · ', $scores));
              ?>
            </p>
            <?php if (!empty($rev['comment'])): ?><p class="hx-review__text"><?= nl2br($h((string) $rev['comment'])) ?></p><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </section>
  </div>
<?php require __DIR__ . '/partials/shell_close.php'; ?>
