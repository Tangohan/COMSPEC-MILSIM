<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $hubRows */
$hubRows = is_array($hubRows ?? null) ? $hubRows : [];
$hubMine = is_array($hubMine ?? null) ? $hubMine : null;
$hubMedians = is_array($hubMedians ?? null) ? $hubMedians : [];
$hubRanks = is_array($hubRanks ?? null) ? $hubRanks : [];
$hubSort = (string) ($hubSort ?? 'members');
$hubGames = is_array($hubGames ?? null) ? $hubGames : [];
$hubRecommended = is_array($hubRecommended ?? null) ? $hubRecommended : [];
$hubPendingReviews = is_array($hubPendingReviews ?? null) ? $hubPendingReviews : [];
$hubBoardPosts = is_array($hubBoardPosts ?? null) ? $hubBoardPosts : [];
$hubCanCooperate = !empty($hubCanCooperate);
$hubQuickLinks = is_array($hubQuickLinks ?? null) ? $hubQuickLinks : [];
$hubAnnounceItems = is_array($hub_announce_items ?? null) ? $hub_announce_items : [];
$hubAnnounceManageUrl = isset($hub_announce_manage_url) && is_string($hub_announce_manage_url) && $hub_announce_manage_url !== '' ? $hub_announce_manage_url : null;
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$hubHeroImage = asset_url('assets/images/fog-team.jpg');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v, int $dec = 0): string => $v === null ? '—' : number_format((float) $v, $dec, ',', "\u{202F}");
$ordinal = static fn (int $n): string => $n === 1 ? '1er' : $n . 'e';
$needTypes = \App\Repositories\CommunityEcosystemRepository::NEED_TYPES;
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');

$totalMembers = 0;
$totalOps = 0.0;
foreach ($hubRows as $r) {
    $totalMembers += (int) $r['members'];
    $totalOps += (float) $r['ops_per_month'];
}

$kpiMetrics = ['members', 'activity', 'units', 'ops_per_month', 'sse_records', 'cooperations', 'recommendations', 'rating'];
$maxOf = [];
foreach ($kpiMetrics as $m) {
    $maxOf[$m] = 0.0;
    foreach ($hubRows as $r) {
        $maxOf[$m] = max($maxOf[$m], (float) ($r[$m] ?? 0));
    }
}
$fmtMetric = static function (string $metric, $value) use ($num): string {
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
$sortLink = static fn (string $key): string => url('hub') . '?tri=' . rawurlencode($key) . '#annuaire';
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/hub-ecosystem.css')) ?>">

<div class="hubx">
  <section class="hub-hero" aria-labelledby="hub-hero-title">
    <img class="hub-hero__media" src="<?= $h($hubHeroImage) ?>" alt="" width="2048" height="933" decoding="async" fetchpriority="high">
    <div class="hub-hero__shade" aria-hidden="true"></div>
    <div class="hub-hero__inner">
      <p class="hub-hero__brand">Athena · Hub</p>
      <h1 id="hub-hero-title" class="hub-hero__title">Les communautés, côte à côte</h1>
      <p class="hub-hero__lead">Comparez votre organisation aux autres unités Athena, recommandez celles avec qui vous jouez, évaluez vos partenaires après une coopération et trouvez du renfort dans la bourse aux coopérations.</p>
      <div class="hub-hero__actions">
        <a class="hx-btn hx-btn--solid" href="#annuaire">Parcourir l’annuaire</a>
        <a class="hx-btn hx-btn--ghost" href="<?= $h(url('hub/comparer')) ?>">Comparer deux communautés</a>
        <a class="hx-btn hx-btn--ghost" href="<?= $h(url('hub/bourse')) ?>">Bourse aux coopérations</a>
      </div>
      <div class="hub-hero__stats" aria-label="Chiffres du réseau">
        <span><strong><?= $num(count($hubRows)) ?></strong> communautés</span>
        <span><strong><?= $num($totalMembers) ?></strong> membres actifs</span>
        <span><strong><?= $num($totalOps, 0) ?></strong> opérations par mois</span>
        <span><strong><?= $num((int) ($hubBoardOpenCount ?? count($hubBoardPosts))) ?></strong> besoins ouverts</span>
      </div>
    </div>
  </section>

  <div class="hx-wrap">
    <?php if ($flashOk): ?><p class="hx-flash hx-flash--ok" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr): ?><p class="hx-flash hx-flash--err" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>

    <?php if ($hubQuickLinks !== []): ?>
    <nav class="hx-quick" aria-label="Mon espace">
      <?php foreach ($hubQuickLinks as $link): ?>
      <a href="<?= $h((string) $link['url']) ?>"><strong><?= $h((string) $link['label']) ?></strong><span><?= $h((string) $link['description']) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($hubAnnounceItems !== []): ?>
    <div class="hx-section">
      <?php
      $announce_items = $hubAnnounceItems;
      $announce_heading = 'Annonces de votre communauté';
      $announce_kicker = 'Transmission';
      $announce_empty = 'Aucune annonce active pour le moment.';
      $announce_id = 'hub-announce';
      $announce_list_url = url('alertes');
      $announce_manage_url = $hubAnnounceManageUrl;
      $announce_start_open = false;
      require base_path('views/partials/announce_tiles.php');
      ?>
    </div>
    <?php endif; ?>

    <?php if ($hubMine !== null): ?>
    <section class="hx-section" aria-labelledby="hx-mine-title">
      <div class="hx-section__head">
        <div>
          <p class="hx-kicker">Votre position</p>
          <h2 id="hx-mine-title" class="hx-title"><?= $h((string) $hubMine['name']) ?> face au réseau</h2>
          <p class="hx-sub">Chaque indicateur est comparé à la médiane des <?= $num(count($hubRows)) ?> communautés du registre. La barre montre votre valeur par rapport à la plus haute.</p>
        </div>
        <a class="hx-btn hx-btn--line" href="<?= $h(url('hub/c/' . $hubMine['slug'])) ?>">Voir notre fiche publique comparée</a>
      </div>
      <div class="hx-kpis">
        <?php foreach ($kpiMetrics as $metric):
          $value = $hubMine[$metric] ?? null;
          $median = $hubMedians[$metric] ?? null;
          $rank = $hubRanks[$metric] ?? null;
          $pct = ($value !== null && $maxOf[$metric] > 0) ? (int) round((float) $value / $maxOf[$metric] * 100) : 0;
        ?>
        <div class="hx-kpi">
          <p class="hx-kpi__label"><?= $h(\App\Services\Ecosystem\CommunityStatsService::METRICS[$metric]) ?></p>
          <p class="hx-kpi__value"><?= $h($fmtMetric($metric, $value)) ?></p>
          <div class="hx-kpi__bar" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></div>
          <p class="hx-kpi__meta">
            <span>Médiane <?= $h($fmtMetric($metric, $median)) ?></span>
            <?php if ($rank !== null): ?>
            <span class="<?= $value !== null && $median !== null && (float) $value >= (float) $median ? 'hx-up' : 'hx-down' ?>"><?= $h($ordinal($rank)) ?> / <?= count($hubRows) ?></span>
            <?php endif; ?>
          </p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($hubPendingReviews !== []): ?>
    <section class="hx-section" id="avis" aria-labelledby="hx-reviews-title">
      <div class="hx-section__head">
        <div>
          <p class="hx-kicker">À évaluer</p>
          <h2 id="hx-reviews-title" class="hx-title">Vos partenaires de coopération</h2>
          <p class="hx-sub">Ces coopérations sont clôturées. Votre avis apparaît sur la fiche de l’unité partenaire et alimente sa note : restez factuel, il est signé du nom de votre communauté.</p>
        </div>
      </div>
      <div class="hx-board">
        <?php foreach ($hubPendingReviews as $p): ?>
        <form class="hx-card hx-review-form" method="post" action="<?= $h(url('hub/avis')) ?>">
          <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
          <input type="hidden" name="mission_id" value="<?= (int) $p['mission_id'] ?>">
          <input type="hidden" name="tenant_id" value="<?= (int) $p['tenant_id'] ?>">
          <div>
            <p class="hx-kicker"><?= $h((string) $p['mission_title']) ?></p>
            <h3 class="text-base font-black"><?= $h((string) $p['tenant_name']) ?></h3>
          </div>
          <?php foreach (['rating' => 'Note globale', 'reliability' => 'Fiabilité (présence, ponctualité)', 'organisation' => 'Organisation (briefings, échanges)', 'ambiance' => 'Esprit d’équipe'] as $field => $label): ?>
          <fieldset>
            <legend class="text-xs font-bold text-slate-500"><?= $h($label) ?><?= $field === 'rating' ? ' *' : ' (facultatif)' ?></legend>
            <div class="hx-rate">
              <?php for ($i = 1; $i <= 5; $i++): ?>
              <label><input type="radio" name="<?= $h($field) ?>" value="<?= $i ?>" <?= $field === 'rating' ? 'required' : '' ?>><?= $i ?> ★</label>
              <?php endfor; ?>
            </div>
          </fieldset>
          <?php endforeach; ?>
          <textarea class="hx-area" name="comment" maxlength="2000" placeholder="Ce qui a bien fonctionné, ce qui serait à améliorer…"></textarea>
          <button type="submit" class="hx-btn hx-btn--accent">Publier l’avis</button>
        </form>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="hx-section" aria-labelledby="hx-board-title">
      <div class="hx-section__head">
        <div>
          <p class="hx-kicker">Bourse aux coopérations</p>
          <h2 id="hx-board-title" class="hx-title">Besoins ouverts</h2>
          <p class="hx-sub">Une communauté cherche du renfort, une force adverse, un appui aérien ou un partenaire d’entraînement ? Proposez votre aide : si elle accepte, la coopération s’ouvre directement entre vos deux unités.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <?php if ($hubCanCooperate): ?><a class="hx-btn hx-btn--accent" href="<?= $h(url('hub/bourse')) ?>#publier">Publier un besoin</a><?php endif; ?>
          <a class="hx-btn hx-btn--line" href="<?= $h(url('hub/bourse')) ?>">Toute la bourse</a>
        </div>
      </div>
      <?php if ($hubBoardPosts === []): ?>
      <p class="hx-empty">Aucun besoin ouvert pour le moment.<?= $hubCanCooperate ? ' Soyez la première communauté à en publier un.' : '' ?></p>
      <?php else: ?>
      <div class="hx-board">
        <?php foreach ($hubBoardPosts as $post): ?>
        <article class="hx-post">
          <div class="hx-post__meta">
            <span class="hx-pill"><?= $h($needTypes[(string) $post['need_type']] ?? 'Besoin') ?></span>
            <?php if (!empty($post['starts_at'])): ?><span><?= $h(date('d/m/Y H:i', strtotime((string) $post['starts_at']))) ?></span><?php endif; ?>
            <?php if (!empty($post['slots'])): ?><span><?= (int) $post['slots'] ?> places</span><?php endif; ?>
          </div>
          <h3><?= $h((string) $post['title']) ?></h3>
          <p class="hx-post__by">Publié par <a href="<?= $h(url('hub/c/' . $post['tenant_slug'])) ?>"><?= $h((string) $post['tenant_name']) ?></a><?= !empty($post['game_label']) ? ' · ' . $h((string) $post['game_label']) : '' ?> · <?= (int) $post['responses_count'] ?> proposition(s)</p>
          <a class="hx-btn hx-btn--line hx-btn--small self-start" href="<?= $h(url('hub/bourse')) ?>#besoin-<?= (int) $post['id'] ?>">Voir et répondre</a>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="hx-section" id="annuaire" aria-labelledby="hx-dir-title">
      <div class="hx-section__head">
        <div>
          <p class="hx-kicker">Annuaire comparatif</p>
          <h2 id="hx-dir-title" class="hx-title">Toutes les communautés</h2>
          <p class="hx-sub">Chiffres agrégés et publics : membres actifs, organigramme, opérations des 90 derniers jours, volume de renseignement (SSE), coopérations, recommandations et note des partenaires. Aucun nom de membre ni contenu n’est exposé. Cliquez sur un en-tête pour trier.</p>
        </div>
      </div>

      <div class="hx-filters" data-hx-filters>
        <div class="hx-field">
          <label for="hx-q">Rechercher</label>
          <input id="hx-q" class="hx-input" type="search" placeholder="Nom, jeu, unité représentée…" autocomplete="off" data-hx-q>
        </div>
        <div class="hx-field">
          <label for="hx-game">Jeu</label>
          <select id="hx-game" class="hx-select" data-hx-game>
            <option value="">Tous les jeux</option>
            <?php foreach ($hubGames as $g): ?><option value="<?= $h(mb_strtolower($g)) ?>"><?= $h($g) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="hx-field">
          <label for="hx-sort">Trier par</label>
          <select id="hx-sort" class="hx-select" data-hx-sort>
            <?php foreach (\App\Services\Ecosystem\CommunityStatsService::METRICS as $key => $label): ?>
            <option value="<?= $h($sortLink($key)) ?>"<?= $key === $hubSort ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
            <option value="<?= $h($sortLink('name')) ?>"<?= $hubSort === 'name' ? ' selected' : '' ?>>Nom</option>
          </select>
        </div>
        <label class="hx-check"><input type="checkbox" data-hx-open> Recrutement ouvert</label>
      </div>

      <div class="hx-table-wrap">
        <table class="hx-table">
          <thead>
            <tr>
              <th scope="col"><a href="<?= $h($sortLink('name')) ?>"<?= $hubSort === 'name' ? ' aria-current="true"' : '' ?>>Communauté</a></th>
              <?php foreach (['members' => 'Membres', 'activity' => 'Actifs 30 j', 'units' => 'Unités', 'ops_per_month' => 'Op./mois', 'sse_records' => 'SSE', 'cooperations' => 'Coop.', 'rating' => 'Note', 'recommendations' => 'Recos'] as $key => $label): ?>
              <th scope="col"><a href="<?= $h($sortLink($key)) ?>"<?= $hubSort === $key ? ' aria-current="true"' : '' ?> title="<?= $h(\App\Services\Ecosystem\CommunityStatsService::METRICS[$key]) ?>"><?= $h($label) ?></a></th>
              <?php endforeach; ?>
              <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hubRows as $r):
              $isMine = $hubMine !== null && $r['id'] === $hubMine['id'];
              $search = mb_strtolower(implode(' ', [$r['name'], $r['game'], $r['unit_label'], $r['tagline'], $r['locale']]));
              $recommended = in_array((int) $r['id'], $hubRecommended, true);
              $initials = mb_strtoupper(mb_substr((string) $r['name'], 0, 2));
            ?>
            <tr class="<?= $isMine ? 'is-mine' : '' ?>" data-hx-row data-search="<?= $h($search) ?>" data-game="<?= $h(mb_strtolower((string) $r['game'])) ?>" data-open="<?= $r['recruitment_open'] ? '1' : '0' ?>">
              <td>
                <div class="hx-org">
                  <?php if ($r['logo'] !== '' && filter_var($r['logo'], FILTER_VALIDATE_URL)): ?>
                  <img class="hx-org__logo" src="<?= $h((string) $r['logo']) ?>" alt="" loading="lazy">
                  <?php else: ?>
                  <span class="hx-org__logo" aria-hidden="true"><?= $h($initials) ?></span>
                  <?php endif; ?>
                  <div>
                    <a href="<?= $h(url('hub/c/' . $r['slug'])) ?>"><?= $h((string) $r['name']) ?></a>
                    <small>
                      <?= $h($r['game'] !== '' ? (string) $r['game'] : 'Jeu non précisé') ?>
                      <?php if ($r['recruitment_open']): ?> · <span class="hx-pill">Recrute</span><?php endif; ?>
                      <?php if ($isMine): ?> · <span class="hx-pill hx-pill--muted">Vous</span><?php endif; ?>
                    </small>
                  </div>
                </div>
              </td>
              <td><?= $num($r['members']) ?></td>
              <td><?= $r['activity'] !== null ? $num($r['activity']) . ' %' : '<span class="hx-dash">—</span>' ?></td>
              <td title="<?= (int) $r['depth'] ?> niveau(x)"><?= $num($r['units']) ?></td>
              <td><?= $num($r['ops_per_month'], 1) ?></td>
              <td><?= $num($r['sse_records']) ?></td>
              <td><?= $num($r['cooperations']) ?></td>
              <td><?= $r['rating'] !== null ? '<span class="hx-stars">★ ' . $num($r['rating'], 1) . '</span> <small class="hx-dash">(' . (int) $r['rating_count'] . ')</small>' : '<span class="hx-dash">—</span>' ?></td>
              <td><?= $num($r['recommendations']) ?></td>
              <td>
                <div class="hx-actions">
                  <?php if (!$isMine): ?>
                  <form method="post" action="<?= $h(url('hub/c/' . $r['slug'] . '/recommander')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="back" value="hub?tri=<?= $h($hubSort) ?>">
                    <button type="submit" class="hx-btn hx-btn--line hx-btn--small" aria-pressed="<?= $recommended ? 'true' : 'false' ?>" title="<?= $recommended ? 'Retirer ma recommandation' : 'Recommander cette communauté' ?>"><?= $recommended ? '♥ Recommandée' : '♡ Recommander' ?></button>
                  </form>
                  <?php if ($hubMine !== null): ?>
                  <a class="hx-btn hx-btn--line hx-btn--small" href="<?= $h(url('hub/comparer') . '?a=' . rawurlencode((string) $hubMine['slug']) . '&b=' . rawurlencode((string) $r['slug'])) ?>">Comparer</a>
                  <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="hx-empty mt-3 hidden" data-hx-none>Aucune communauté ne correspond à ces filtres.</p>
    </section>
  </div>
</div>

<script>
(function () {
  var root = document.querySelector('[data-hx-filters]');
  if (!root) return;
  var q = root.querySelector('[data-hx-q]');
  var game = root.querySelector('[data-hx-game]');
  var open = root.querySelector('[data-hx-open]');
  var sort = root.querySelector('[data-hx-sort]');
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-hx-row]'));
  var none = document.querySelector('[data-hx-none]');
  function fold(v) {
    var t = String(v || '').toLowerCase();
    if (t.normalize) t = t.normalize('NFD').replace(/[̀-ͯ]/g, '');
    return t.trim();
  }
  function apply() {
    var tokens = fold(q.value).split(/\s+/).filter(Boolean);
    var g = game.value;
    var onlyOpen = open.checked;
    var shown = 0;
    rows.forEach(function (row) {
      var hay = fold(row.getAttribute('data-search'));
      var ok = tokens.every(function (t) { return hay.indexOf(t) !== -1; })
        && (!g || row.getAttribute('data-game') === g)
        && (!onlyOpen || row.getAttribute('data-open') === '1');
      row.hidden = !ok;
      if (ok) shown++;
    });
    if (none) none.classList.toggle('hidden', shown > 0);
  }
  q.addEventListener('input', apply);
  game.addEventListener('change', apply);
  open.addEventListener('change', apply);
  sort.addEventListener('change', function () { window.location.href = sort.value; });
})();
</script>
