<?php
/** @var list<array<string, mixed>> $registryTenants */
$registryTenants = $registryTenants ?? [];
$registryCount = count($registryTenants);
$loggedIn = (bool) \App\Core\Session::get('user_id');
$createCommunityUrl = $loggedIn ? url('communities/create') : url('register');
$createCommunityLabel = 'Créer une communauté';

$styleBadgeCatalog = \App\Services\Community\TenantCommunityProfileService::badgeLabels();

$filterGames = [];
$filterUnits = [];
$filterLocales = [];
$filterCountries = [];
$filterTags = [];
$registryTrending = [];
$registryPinnedCount = 0;
foreach ($registryTenants as $tRow) {
    $cKey = trim((string) ($tRow['registry_country'] ?? ''));
    $cLabel = trim((string) ($tRow['registry_country_label'] ?? ''));
    if ($cKey !== '' && $cLabel !== '') {
        $filterCountries[$cKey] = $cLabel;
    }
    $tagSlugs = is_array($tRow['registry_tag_slugs'] ?? null) ? $tRow['registry_tag_slugs'] : [];
    $tagLabels = is_array($tRow['registry_tag_labels'] ?? null) ? $tRow['registry_tag_labels'] : [];
    foreach ($tagSlugs as $ti => $tagSlug) {
        if (is_string($tagSlug) && $tagSlug !== '' && isset($tagLabels[$ti]) && is_string($tagLabels[$ti])) {
            $filterTags[$tagSlug] = $tagLabels[$ti];
        }
    }
    if ((int) ($tRow['registry_trending_rank'] ?? 0) > 0) {
        $registryTrending[(int) $tRow['registry_trending_rank']] = $tRow;
    }
    if (!empty($tRow['registry_pinned'])) {
        $registryPinnedCount++;
    }
    $g = trim((string) ($tRow['game_label'] ?? ''));
    if ($g !== '') {
        $filterGames[$g] = $g;
    }
    $u = trim((string) ($tRow['registry_unit_affiliation_label'] ?? ''));
    if ($u !== '') {
        $filterUnits[$u] = $u;
    }
    $locKey = trim((string) ($tRow['registry_locale'] ?? ''));
    $locLabel = trim((string) ($tRow['registry_locale_label'] ?? ''));
    if ($locKey !== '' && $locLabel !== '') {
        $filterLocales[$locKey] = $locLabel;
    }
}
natcasesort($filterGames);
natcasesort($filterUnits);
ksort($filterLocales);
natcasesort($filterCountries);
natcasesort($filterTags);
ksort($registryTrending);
$registryTrendDays = \App\Services\Community\RegistryDirectory::TREND_DAYS;

/** Couverture optionnelle : public/assets/img/communities/{slug}-cover.jpg */
$registryCoverUrl = static function (string $slug): ?string {
    $rel = 'assets/img/communities/' . $slug . '-cover.jpg';
    $path = base_path('public/' . $rel);
    return is_file($path) ? url($rel) : null;
};

/** Dégradé distinct par slug (fallback sans image). */
$registryCoverGradient = static function (string $slug): string {
    $h = crc32($slug);
    $h1 = $h % 360;
    $h2 = (($h >> 9) & 0xffff) % 360;
    $h3 = (($h >> 17) & 0xffff) % 360;

    return sprintf(
        'background: radial-gradient(circle at 80%% 20%%, hsla(%d,65%%,42%%,0.35), transparent 45%%), '
        . 'radial-gradient(circle at 10%% 90%%, hsla(%d,55%%,38%%,0.3), transparent 40%%), '
        . 'linear-gradient(155deg, hsl(%d,32%%,12%%) 0%%, hsl(%d,28%%,8%%) 55%%, hsl(%d,35%%,6%%) 100%%);',
        $h1,
        $h2,
        $h1,
        $h2,
        $h3
    );
};

$nameInitials = static function (string $name): string {
    $out = '';
    foreach (preg_split('/\s+/u', $name) ?: [] as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($out) >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : 'C';
};

/* Badge « images » du hero : unités en tendance d'abord, puis celles qui ont un logo, 5 au plus. */
$heroBadgeRows = array_values($registryTrending);
foreach ($registryTenants as $hbRow) {
    if (!in_array($hbRow, $heroBadgeRows, true)) {
        $heroBadgeRows[] = $hbRow;
    }
}
$hbHasLogo = static function (array $row): bool {
    $l = trim((string) ($row['logo_url'] ?? ''));
    return $l !== '' && filter_var($l, FILTER_VALIDATE_URL) !== false;
};
usort($heroBadgeRows, static fn (array $a, array $b): int => (int) $hbHasLogo($b) <=> (int) $hbHasLogo($a));
$heroBadgeItems = [];
foreach (array_slice($heroBadgeRows, 0, 5) as $hbRow) {
    $heroBadgeItems[] = [
        'src' => $hbHasLogo($hbRow) ? trim((string) $hbRow['logo_url']) : null,
        'initials' => $nameInitials(name_mask_text((string) ($hbRow['name'] ?? $hbRow['slug'] ?? ''))),
    ];
}

/* Compteurs des filtres (nombre de communautés par pays, langue, type, jeu, tag). */
$filterCounts = ['pays' => [], 'langue' => [], 'jeu' => [], 'tag' => []];
foreach ($registryTenants as $fcRow) {
    $fcCountry = strtolower(trim((string) ($fcRow['registry_country'] ?? '')));
    if ($fcCountry !== '') {
        $filterCounts['pays'][$fcCountry] = ($filterCounts['pays'][$fcCountry] ?? 0) + 1;
    }
    $fcLang = trim((string) ($fcRow['registry_locale'] ?? ''));
    if ($fcLang !== '') {
        $filterCounts['langue'][$fcLang] = ($filterCounts['langue'][$fcLang] ?? 0) + 1;
    }
    $fcGame = trim((string) ($fcRow['game_label'] ?? ''));
    if ($fcGame !== '') {
        $filterCounts['jeu'][$fcGame] = ($filterCounts['jeu'][$fcGame] ?? 0) + 1;
    }
    foreach ((is_array($fcRow['registry_tag_slugs'] ?? null) ? $fcRow['registry_tag_slugs'] : []) as $fcTag) {
        if (is_string($fcTag) && $fcTag !== '') {
            $filterCounts['tag'][$fcTag] = ($filterCounts['tag'][$fcTag] ?? 0) + 1;
        }
    }
}
$registryOpenCount = 0;
foreach ($registryTenants as $roRow) {
    $roOpen = array_key_exists('registry_recruitment_open', $roRow)
        ? !empty($roRow['registry_recruitment_open'])
        : empty($roRow['registry_locked']);
    $registryOpenCount += $roOpen ? 1 : 0;
}
$filterCountSuffix = static function (string $group, string $key) use ($filterCounts): string {
    $n = (int) ($filterCounts[$group][$key] ?? 0);
    // « · » : HtmlTranslator traduit chaque morceau séparément (le libellé, pas le nombre).
    return $n > 0 ? ' · ' . $n : '';
};

if ($registryCount === 0) {
    $countLabel = 'Aucune communauté listée';
} elseif ($registryCount === 1) {
    $countLabel = '1 communauté visible';
} else {
    $countLabel = $registryCount . ' communautés visibles';
}
?>
<div class="community-registry cr-shell" data-community-registry>
  <script>
  /* Mode nuit : même préférence que le reste du site (localStorage « athena.bo.theme », sinon le système). */
  (function (el) {
    var pref = null;
    try { pref = localStorage.getItem('athena.bo.theme'); } catch (e) {}
    var dark = pref === 'dark' || (pref !== 'light' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    el.setAttribute('data-cr-theme', dark ? 'dark' : 'light');
  })(document.currentScript.parentNode);
  </script>
  <section class="cr-hero cr-rise" aria-labelledby="cr-hero-title">
    <div class="cr-wrap cr-hero__inner">
      <a href="<?= htmlspecialchars(url('')) ?>" class="cr-brand" aria-label="ATHENA">ATHENA<span>.</span></a>
      <p class="cr-kicker">Annuaire public</p>
      <h1 id="cr-hero-title" class="cr-hero__title">Communautés &amp; unités</h1>
      <p class="cr-hero__lead">
        Parcourez les organisations présentes sur ATHENA, ouvrez leur fiche publique et rejoignez-en une avec un code d’accès.
        Aucun compte n’est requis pour consulter cet annuaire.
      </p>
      <div class="cr-hero__actions">
        <a href="<?= htmlspecialchars(url('join')) ?>" class="cr-btn cr-btn--accent">Rejoindre par code</a>
        <a href="<?= htmlspecialchars($createCommunityUrl) ?>" class="cr-btn cr-btn--ghost"><?= htmlspecialchars($createCommunityLabel) ?></a>
      </div>
      <?php if ($registryCount > 0): ?>
      <aside class="cr-hero__aside" aria-label="L’annuaire en chiffres">
        <?php
        $ibItems = $heroBadgeItems;
        $ibCount = $registryCount;
        $ibLabel = $registryCount === 1 ? 'communauté' : 'communautés';
        $ibHref = '#cr-catalog-title';
        $ibTone = 'dark';
        $ibAria = 'Voir les ' . $registryCount . ' communautés de l’annuaire';
        require base_path('views/partials/images_badge.php');
        unset($ibItems, $ibCount, $ibLabel, $ibHref, $ibTone, $ibAria);
        ?>
        <dl class="cr-hero__stats">
          <div><dt>Recrutent</dt><dd><?= (int) $registryOpenCount ?></dd></div>
          <div><dt>Pays représentés</dt><dd><?= count($filterCountries) ?></dd></div>
          <div><dt>Langues</dt><dd><?= count($filterLocales) ?></dd></div>
        </dl>
      </aside>
      <?php endif; ?>
    </div>
  </section>

  <div class="cr-wrap">
    <section class="cr-toolbar cr-rise cr-rise-d1" aria-labelledby="cr-catalog-title">
      <div class="cr-toolbar__row">
        <div>
          <p class="cr-kicker" style="color: var(--cr-emerald-deep);">Catalogue</p>
          <h2 id="cr-catalog-title" class="cr-toolbar__title">Communautés disponibles</h2>
        </div>
        <div class="cr-toolbar__meta">
          <div class="cr-count" data-cr-count>
            <span class="cr-count__dot<?= $registryCount === 0 ? ' cr-count__dot--empty' : '' ?>" aria-hidden="true"></span>
            <span data-cr-count-label><?= htmlspecialchars($countLabel) ?></span>
          </div>
          <a href="<?= htmlspecialchars(url('join')) ?>" class="cr-btn cr-btn--line cr-btn--sm">Saisir un code</a>
        </div>
      </div>

      <?php if ($registryTenants !== []): ?>
      <form class="cr-filters" role="search" action="<?= htmlspecialchars(url('communities')) ?>" method="get" data-cr-form>
        <label class="cr-search" for="cr-search-input">
          <span class="sr-only">Rechercher une communauté</span>
          <svg class="cr-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
          </svg>
          <input
            type="search"
            id="cr-search-input"
            name="q"
            class="cr-search__input"
            placeholder="Nom, pays, langue, type, tag…"
            autocomplete="off"
            data-cr-search
          >
          <kbd class="cr-search__kbd" aria-hidden="true">/</kbd>
        </label>

        <div class="cr-filter-selects">
          <?php if ($filterCountries !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par pays</span>
            <select name="pays" data-cr-country aria-label="Pays">
              <option value="">Tous les pays</option>
              <?php foreach ($filterCountries as $countryKey => $countryLabel): ?>
              <option value="<?= htmlspecialchars(strtolower((string) $countryKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $countryLabel . $filterCountSuffix('pays', strtolower((string) $countryKey))) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>

          <?php if ($filterLocales !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par langue</span>
            <select name="langue" data-cr-lang aria-label="Langue">
              <option value="">Toutes les langues</option>
              <?php foreach ($filterLocales as $langKey => $langLabel): ?>
              <option value="<?= htmlspecialchars((string) $langKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $langLabel . $filterCountSuffix('langue', (string) $langKey)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>

          <label class="cr-select">
            <span class="sr-only">Filtrer par type</span>
            <select name="type" data-cr-type aria-label="Type de communauté">
              <option value="">Tous les types</option>
              <?php foreach ($styleBadgeCatalog as $typeSlug => $typeLabel): ?>
              <option value="<?= htmlspecialchars((string) $typeSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $typeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <?php if ($filterTags !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par tag</span>
            <select name="tag" data-cr-tag aria-label="Tag">
              <option value="">Tous les tags</option>
              <?php foreach ($filterTags as $tagKey => $tagLabel): ?>
              <option value="<?= htmlspecialchars((string) $tagKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $tagLabel . $filterCountSuffix('tag', (string) $tagKey)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>

          <?php if ($filterGames !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par jeu</span>
            <select name="jeu" data-cr-game aria-label="Jeu">
              <option value="">Tous les jeux</option>
              <?php foreach ($filterGames as $gameOpt): ?>
              <option value="<?= htmlspecialchars(mb_strtolower($gameOpt), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($gameOpt . $filterCountSuffix('jeu', $gameOpt)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>

          <?php if ($filterUnits !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par unité</span>
            <select name="unite" data-cr-unit aria-label="Unité">
              <option value="">Toutes les unités</option>
              <?php foreach ($filterUnits as $unitOpt): ?>
              <option value="<?= htmlspecialchars(mb_strtolower($unitOpt), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($unitOpt) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>
        </div>

        <div class="cr-filters__foot">
          <div class="cr-chips" role="group" aria-label="Affiner la liste">
            <button type="button" class="cr-chip is-active" data-cr-filter="all" aria-pressed="true">Toutes <span class="cr-chip__n"><?= (int) $registryCount ?></span></button>
            <?php if ($registryPinnedCount > 0): ?>
            <button type="button" class="cr-chip" data-cr-filter="pinned" aria-pressed="false">Épinglées <span class="cr-chip__n"><?= (int) $registryPinnedCount ?></span></button>
            <?php endif; ?>
            <?php if ($registryTrending !== []): ?>
            <button type="button" class="cr-chip" data-cr-filter="trending" aria-pressed="false">Tendance <span class="cr-chip__n"><?= count($registryTrending) ?></span></button>
            <?php endif; ?>
            <button type="button" class="cr-chip" data-cr-filter="featured" aria-pressed="false">À la une</button>
            <button type="button" class="cr-chip" data-cr-filter="open" aria-pressed="false">Recrutement ouvert <span class="cr-chip__n"><?= (int) $registryOpenCount ?></span></button>
            <button type="button" class="cr-chip" data-cr-filter="closed" aria-pressed="false">Recrutement fermé <span class="cr-chip__n"><?= (int) ($registryCount - $registryOpenCount) ?></span></button>
          </div>
          <div class="cr-sort">
            <label class="cr-select cr-select--sort">
              <span class="cr-sort__label">Trier</span>
              <select name="tri" data-cr-sort aria-label="Trier les communautés">
                <option value="">Recommandé</option>
                <option value="tendance">Tendance</option>
                <option value="nom">Nom (A → Z)</option>
                <option value="recent">Plus récentes</option>
              </select>
            </label>
            <button type="button" class="cr-btn cr-btn--line cr-btn--sm" data-cr-reset hidden>Effacer les filtres</button>
          </div>
        </div>
        <noscript><button type="submit" class="cr-btn cr-btn--ink cr-btn--sm">Rechercher</button></noscript>
      </form>
      <?php endif; ?>
    </section>

    <?php if ($registryTrending !== []): ?>
    <section class="cr-trend cr-rise cr-rise-d1" aria-labelledby="cr-trend-title" data-cr-trend-strip>
      <div class="cr-trend__head">
        <div>
          <p class="cr-kicker cr-kicker--trend">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg>
            Unités en tendance
          </p>
          <h2 id="cr-trend-title" class="cr-trend__title">Ça bouge en ce moment</h2>
        </div>
        <p class="cr-trend__hint">Sur les <?= (int) $registryTrendDays ?> derniers jours : visiteurs de la fiche, nouvelles recrues et membres actifs.</p>
      </div>
      <ol class="cr-trend__list">
        <?php foreach ($registryTrending as $rank => $tt): ?>
          <?php
          $ttSlug = (string) ($tt['slug'] ?? '');
          $ttName = name_mask_text((string) ($tt['name'] ?? $ttSlug));
          $ttTrend = is_array($tt['registry_trend'] ?? null) ? $tt['registry_trend'] : [];
          $ttRecruits = (int) ($ttTrend['recruits'] ?? 0);
          $ttVisitors = (int) ($ttTrend['visitors'] ?? 0);
          $ttPrev = (int) ($ttTrend['visitors_prev'] ?? 0);
          $ttLogo = trim((string) ($tt['logo_url'] ?? ''));
          $ttMeta = array_values(array_filter([
              trim((string) ($tt['game_label'] ?? '')),
              trim((string) ($tt['registry_country_label'] ?? '')),
              trim((string) ($tt['registry_locale_label'] ?? '')),
          ]));
          ?>
        <li class="cr-trend__item">
          <a class="cr-trend__card" href="<?= htmlspecialchars(url('c/' . rawurlencode($ttSlug) . '?ref=registry')) ?>">
            <span class="cr-trend__rank" aria-label="Rang <?= (int) $rank ?>">#<?= (int) $rank ?></span>
            <?php if ($ttLogo !== '' && filter_var($ttLogo, FILTER_VALIDATE_URL)): ?>
            <span class="cr-trend__logo"><img src="<?= htmlspecialchars($ttLogo) ?>" alt=""></span>
            <?php else: ?>
            <span class="cr-trend__logo cr-trend__logo--fallback" aria-hidden="true"><?= htmlspecialchars($nameInitials($ttName)) ?></span>
            <?php endif; ?>
            <span class="cr-trend__body">
              <span class="cr-trend__name"><?= name_blur_html($ttName) ?></span>
              <?php if ($ttMeta !== []): ?>
              <span class="cr-trend__meta"><?= htmlspecialchars(implode(' · ', $ttMeta)) ?></span>
              <?php endif; ?>
              <span class="cr-trend__signals">
                <?php if ($ttRecruits > 0): ?>
                <span class="cr-trend__signal cr-trend__signal--up"><?= $ttRecruits === 1 ? '+1 recrue' : '+' . $ttRecruits . ' recrues' ?></span>
                <?php endif; ?>
                <?php if ($ttVisitors > 0): ?>
                <span class="cr-trend__signal"><?= $ttVisitors === 1 ? '1 visiteur' : $ttVisitors . ' visiteurs' ?></span>
                <?php endif; ?>
                <?php if ($ttPrev > 0 && $ttVisitors > $ttPrev): ?>
                <span class="cr-trend__signal cr-trend__signal--up">+<?= (int) round(($ttVisitors - $ttPrev) * 100 / $ttPrev) ?> %</span>
                <?php endif; ?>
              </span>
            </span>
            <span class="cr-trend__go" aria-hidden="true">→</span>
          </a>
        </li>
        <?php endforeach; ?>
      </ol>
    </section>
    <?php endif; ?>

    <?php if ($registryTenants === []): ?>
      <div class="cr-empty cr-rise cr-rise-d2">
        <p class="cr-kicker" style="color: var(--cr-soft);">Annuaire vide</p>
        <p class="cr-empty__title">Aucune communauté listée pour l’instant</p>
        <p class="cr-empty__text">
          Toutes les communautés ne choisissent pas d’apparaître ici. Vous pouvez créer la vôtre ou revenir plus tard.
        </p>
        <a href="<?= htmlspecialchars($createCommunityUrl) ?>" class="cr-btn cr-btn--ink" style="margin-top: 1.5rem;">
          <?= htmlspecialchars($createCommunityLabel) ?>
        </a>
      </div>
    <?php else: ?>
      <ul class="cr-grid" data-cr-grid>
        <?php foreach ($registryTenants as $i => $t): ?>
          <?php
          $slug = (string) ($t['slug'] ?? '');
          $name = name_mask_text((string) ($t['name'] ?? $slug));
          $code = trim((string) ($t['community_code'] ?? ''));
          $logoUrl = trim((string) ($t['logo_url'] ?? ''));
          $locked = !empty($t['registry_locked']);
          $recruitOpen = array_key_exists('registry_recruitment_open', $t)
              ? !empty($t['registry_recruitment_open'])
              : !$locked;
          $featured = !empty($t['registry_featured']);
          $pinned = !empty($t['registry_pinned']);
          $trendRank = (int) ($t['registry_trending_rank'] ?? 0);
          $trendScore = (int) (($t['registry_trend']['score'] ?? 0));
          $countryKey = strtolower(trim((string) ($t['registry_country'] ?? '')));
          $countryLabel = trim((string) ($t['registry_country_label'] ?? ''));
          $tagSlugsAttr = implode(' ', array_map('strval', is_array($t['registry_tag_slugs'] ?? null) ? $t['registry_tag_slugs'] : []));
          $createdTs = strtotime((string) ($t['created_at'] ?? '')) ?: 0;
          $simpleReg = !empty($t['registry_simple_reg']);
          $excerpt = trim((string) ($t['registry_excerpt'] ?? ''));
          $styleBadgeLabels = is_array($t['registry_style_badge_labels'] ?? null) ? $t['registry_style_badge_labels'] : [];
          $styleBadgeSlugs = is_array($t['registry_style_badge_slugs'] ?? null) ? $t['registry_style_badge_slugs'] : [];
          $registryTagLabels = is_array($t['registry_tag_labels'] ?? null) ? $t['registry_tag_labels'] : [];
          $unitAffiliationLabel = trim((string) ($t['registry_unit_affiliation_label'] ?? ''));
          $gameLabel = trim((string) ($t['game_label'] ?? ''));
          $localeKey = trim((string) ($t['registry_locale'] ?? ''));
          $localeLabel = trim((string) ($t['registry_locale_label'] ?? ''));
          $coverUrl = $registryCoverUrl($slug);
          $gradientStyle = $registryCoverGradient($slug);
          $publicUrl = url('c/' . rawurlencode($slug) . '?ref=registry');
          $joinDirect = $code !== '' ? url('join') . '?code=' . rawurlencode($code) : null;
          $searchBlob = mb_strtolower(trim(implode(' ', array_filter([
              $name,
              $gameLabel,
              $excerpt,
              $simpleReg ? 'inscription simple' : 'parcours milsim',
              $featured ? 'à la une mise en avant' : '',
              $pinned ? 'épinglée' : '',
              $trendRank > 0 ? 'tendance' : '',
              $countryLabel,
              implode(' ', array_map('strval', $styleBadgeLabels)),
              implode(' ', array_map('strval', $registryTagLabels)),
              $unitAffiliationLabel,
              $localeLabel,
          ]))));
          $typeAttr = implode(' ', array_map('strval', $styleBadgeSlugs));
          $delayClass = 'cr-rise cr-rise-d' . min(3, 1 + ($i % 3));
          ?>
        <li
          class="cr-card <?= $delayClass ?><?= $featured ? ' cr-card--featured' : '' ?><?= $pinned ? ' cr-card--pinned' : '' ?>"
          data-cr-card
          data-cr-index="<?= (int) $i ?>"
          data-cr-name="<?= htmlspecialchars(mb_strtolower($name), ENT_QUOTES, 'UTF-8') ?>"
          data-cr-created="<?= (int) $createdTs ?>"
          data-cr-pinned="<?= $pinned ? '1' : '0' ?>"
          data-cr-trend="<?= $trendRank > 0 ? '1' : '0' ?>"
          data-cr-score="<?= $trendScore ?>"
          data-cr-country="<?= htmlspecialchars($countryKey, ENT_QUOTES, 'UTF-8') ?>"
          data-cr-tags="<?= htmlspecialchars($tagSlugsAttr, ENT_QUOTES, 'UTF-8') ?>"
          data-cr-locked="<?= $locked ? '1' : '0' ?>"
          data-cr-open="<?= $recruitOpen ? '1' : '0' ?>"
          data-cr-featured="<?= $featured ? '1' : '0' ?>"
          data-cr-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
          data-cr-game="<?= htmlspecialchars(mb_strtolower($gameLabel), ENT_QUOTES, 'UTF-8') ?>"
          data-cr-unit="<?= htmlspecialchars(mb_strtolower($unitAffiliationLabel), ENT_QUOTES, 'UTF-8') ?>"
          data-cr-type="<?= htmlspecialchars($typeAttr, ENT_QUOTES, 'UTF-8') ?>"
          data-cr-lang="<?= htmlspecialchars($localeKey, ENT_QUOTES, 'UTF-8') ?>"
        >
          <div class="cr-card__cover">
            <?php if ($coverUrl !== null): ?>
            <img src="<?= htmlspecialchars($coverUrl) ?>" alt="" loading="lazy">
            <?php else: ?>
            <div class="cr-card__cover-fallback" style="<?= htmlspecialchars($gradientStyle) ?>" role="img" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="cr-card__scrim" aria-hidden="true"></div>

            <div class="cr-card__badges">
              <?php if ($pinned): ?>
              <span class="cr-pill cr-pill--pinned">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 3a1 1 0 0 1 .7 1.7L15 6.4v4.2l2.6 2.6a1 1 0 0 1-.7 1.8H13v5.5a1 1 0 0 1-2 0V15H7.1a1 1 0 0 1-.7-1.8L9 10.6V6.4L7.3 4.7A1 1 0 0 1 8 3h8z"/></svg>
                Épinglée
              </span>
              <?php endif; ?>
              <?php if ($trendRank > 0): ?>
              <span class="cr-pill cr-pill--trend">Tendance #<?= $trendRank ?></span>
              <?php endif; ?>
              <?php if ($featured): ?>
              <span class="cr-pill cr-pill--featured">À la une</span>
              <?php endif; ?>
              <?php if ($recruitOpen): ?>
              <span class="cr-pill cr-pill--live"><span class="cr-pulse" aria-hidden="true"></span> Recrutement ouvert</span>
              <?php else: ?>
              <span class="cr-pill cr-pill--warn">Recrutement fermé</span>
              <?php endif; ?>
              <?php if (!$pinned && $trendRank === 0): ?>
              <span class="cr-pill cr-pill--glass">Fiche publique</span>
              <?php endif; ?>
            </div>

            <div class="cr-card__foot">
              <div class="min-w-0">
                <h3 class="cr-card__name"><?= name_blur_html($name) ?></h3>
                <?php if ($gameLabel !== ''): ?>
                <p class="cr-card__game"><?= htmlspecialchars($gameLabel) ?></p>
                <?php endif; ?>
                <?php if ($unitAffiliationLabel !== ''): ?>
                <p class="cr-card__unit"><?= htmlspecialchars($unitAffiliationLabel) ?></p>
                <?php endif; ?>
              </div>
              <?php if ($logoUrl !== '' && filter_var($logoUrl, FILTER_VALIDATE_URL)): ?>
              <div class="cr-card__logo">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="">
              </div>
              <?php else: ?>
              <div class="cr-card__logo cr-card__logo-fallback" aria-hidden="true"><?= htmlspecialchars($nameInitials($name)) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="cr-card__body">
            <div class="cr-tags">
              <span class="cr-pill <?= $simpleReg ? 'cr-pill--muted' : 'cr-pill--ok' ?>">
                <?= $simpleReg ? 'Inscription simple' : 'Parcours MilSim' ?>
              </span>
              <?php if ($countryLabel !== ''): ?>
              <span class="cr-pill cr-pill--muted"><?= htmlspecialchars($countryLabel) ?></span>
              <?php endif; ?>
              <?php if ($localeLabel !== ''): ?>
              <span class="cr-pill cr-pill--muted"><?= htmlspecialchars($localeLabel) ?></span>
              <?php endif; ?>
              <?php foreach ($styleBadgeLabels as $bl): ?>
                <?php if (is_string($bl) && $bl !== ''): ?>
                <span class="cr-pill cr-pill--info"><?= htmlspecialchars($bl) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php foreach ($registryTagLabels as $tl): ?>
                <?php if (is_string($tl) && $tl !== ''): ?>
                <span class="cr-pill cr-pill--muted"><?= htmlspecialchars($tl) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>

            <?php if ($excerpt !== ''): ?>
            <p class="cr-excerpt"><?= nl2br(htmlspecialchars($excerpt)) ?></p>
            <?php else: ?>
            <p class="cr-excerpt">
              Cette communauté n’a pas encore rédigé de présentation courte. Ouvrez la fiche publique pour en savoir plus sur le recrutement et la vie de l’unité.
            </p>
            <?php endif; ?>

            <div class="cr-meta">
              <div class="cr-meta__cell">
                <p class="cr-meta__label">Accès</p>
                <p class="cr-meta__value"><?= $locked ? 'Sur invitation' : 'Public + code' ?></p>
              </div>
              <div class="cr-meta__cell">
                <p class="cr-meta__label">Recrutement</p>
                <p class="cr-meta__value"><?= $locked ? 'Fermé' : 'Ouvert' ?></p>
              </div>
              <div class="cr-meta__cell">
                <?php if ($loggedIn && $code !== ''): ?>
                <p class="cr-meta__label">Code d’accès</p>
                <p class="cr-meta__value"><?= htmlspecialchars($code) ?></p>
                <?php else: ?>
                <p class="cr-meta__label">Lien</p>
                <p class="cr-meta__value">Fiche publique</p>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($loggedIn && $code !== ''): ?>
            <div class="cr-code-box">
              <p class="cr-code-box__label">Code communauté</p>
              <div class="cr-code-box__row">
                <code class="cr-code-box__code cr-mono"><?= htmlspecialchars($code) ?></code>
                <button
                  type="button"
                  class="cr-btn cr-btn--line cr-btn--sm"
                  data-registry-copy="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                  aria-label="Copier le code communauté"
                >
                  <span data-registry-copy-label>Copier</span>
                </button>
                <p class="cr-code-box__hint">Utilisez ce code sur la page « Rejoindre » pour être orienté vers cette communauté.</p>
              </div>
            </div>
            <?php endif; ?>

            <div class="cr-card__actions">
              <a href="<?= htmlspecialchars($publicUrl) ?>" class="cr-btn cr-btn--ink">
                Voir la fiche
                <span aria-hidden="true">→</span>
              </a>
              <?php if ($joinDirect !== null && !$locked): ?>
              <a href="<?= htmlspecialchars($joinDirect) ?>" class="cr-btn cr-btn--line">Rejoindre</a>
              <?php endif; ?>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>

      <div class="cr-empty cr-no-match" data-cr-empty hidden>
        <p class="cr-kicker" style="color: var(--cr-soft);">Aucun résultat</p>
        <p class="cr-empty__title">Aucune communauté ne correspond</p>
        <p class="cr-empty__text">Essayez un autre mot-clé ou réinitialisez les filtres.</p>
        <button type="button" class="cr-btn cr-btn--ink" style="margin-top: 1.25rem;" data-cr-reset>Réinitialiser</button>
      </div>
    <?php endif; ?>

    <section class="cr-help cr-rise cr-rise-d2" aria-labelledby="cr-help-title">
      <p class="cr-kicker" style="color: var(--cr-soft);">Pour les équipes qui gèrent une communauté</p>
      <h3 id="cr-help-title" class="cr-help__title">Rendre votre carte plus claire et plus accueillante</h3>
      <p class="cr-help__text">
        Connectez-vous au <strong>back-office de votre communauté</strong>, puis ouvrez <strong>Fiche registre &amp; contact</strong>.
        Vous pouvez y rédiger un court texte de présentation, indiquer le <strong>jeu</strong> auquel vous jouez,
        choisir des <strong>pastilles</strong> qui décrivent votre style et vos thèmes,
        et décider si votre unité doit <strong>apparaître dans cette liste</strong>.
      </p>
      <p class="cr-help__text">
        Une <strong>grande image d’en-tête</strong> (paysage) peut remplacer le fond coloré&nbsp;: déposez-la depuis la même fiche registre — le portail l’affichera automatiquement sur votre carte.
      </p>
    </section>
  </div>
</div>
<script>
(function () {
  var root = document.querySelector('[data-community-registry]');
  if (!root) return;
  var grid = root.querySelector('[data-cr-grid]');
  var cards = Array.prototype.slice.call(root.querySelectorAll('[data-cr-card]'));
  if (!cards.length || !grid) return;
  var form = root.querySelector('[data-cr-form]');
  var search = root.querySelector('[data-cr-search]');
  var sortSelect = root.querySelector('[data-cr-sort]');
  var chips = Array.prototype.slice.call(root.querySelectorAll('[data-cr-filter]'));
  var empty = root.querySelector('[data-cr-empty]');
  var trendStrip = root.querySelector('[data-cr-trend-strip]');
  var countLabel = root.querySelector('[data-cr-count-label]');
  var resets = Array.prototype.slice.call(root.querySelectorAll('[data-cr-reset]'));
  // Sélecteurs : attribut de la carte comparé, paramètre d'URL (lien partageable).
  var selects = [
    { el: root.querySelector('[data-cr-country]'), attr: 'data-cr-country', param: 'pays' },
    { el: root.querySelector('[data-cr-lang]'), attr: 'data-cr-lang', param: 'langue' },
    { el: root.querySelector('[data-cr-type]'), attr: 'data-cr-type', param: 'type', list: true },
    { el: root.querySelector('[data-cr-tag]'), attr: 'data-cr-tags', param: 'tag', list: true },
    { el: root.querySelector('[data-cr-game]'), attr: 'data-cr-game', param: 'jeu' },
    { el: root.querySelector('[data-cr-unit]'), attr: 'data-cr-unit', param: 'unite' }
  ].filter(function (f) { return !!f.el; });
  var filter = 'all';

  function norm(v) {
    return (v || '').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  cards.forEach(function (card) {
    card._blob = norm(card.getAttribute('data-cr-search'));
  });

  function labelFor(n) {
    if (n === 0) return 'Aucune communauté correspondante';
    if (n === 1) return '1 communauté visible';
    return `${n} communautés visibles`;
  }

  function setChip(value) {
    filter = value || 'all';
    var found = false;
    chips.forEach(function (c) {
      var on = (c.getAttribute('data-cr-filter') || '') === filter;
      if (on) found = true;
      c.classList.toggle('is-active', on);
      c.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    if (!found) setChip('all');
  }

  function sortCards() {
    var mode = sortSelect ? sortSelect.value : '';
    var sorted = cards.slice().sort(function (a, b) {
      if (mode === 'tendance') {
        return (+b.getAttribute('data-cr-score')) - (+a.getAttribute('data-cr-score'))
          || (+a.getAttribute('data-cr-index')) - (+b.getAttribute('data-cr-index'));
      }
      if (mode === 'nom') {
        return (a.getAttribute('data-cr-name') || '').localeCompare(b.getAttribute('data-cr-name') || '');
      }
      if (mode === 'recent') {
        return (+b.getAttribute('data-cr-created')) - (+a.getAttribute('data-cr-created'));
      }
      return (+a.getAttribute('data-cr-index')) - (+b.getAttribute('data-cr-index'));
    });
    sorted.forEach(function (card) { grid.appendChild(card); });
  }

  function syncUrl() {
    if (!window.history || !window.history.replaceState || !window.URLSearchParams) return;
    var params = new URLSearchParams(window.location.search);
    ['q', 'pays', 'langue', 'type', 'tag', 'jeu', 'unite', 'tri', 'vue'].forEach(function (k) { params.delete(k); });
    if (search && search.value.trim()) params.set('q', search.value.trim());
    selects.forEach(function (f) { if (f.el.value) params.set(f.param, f.el.value); });
    if (sortSelect && sortSelect.value) params.set('tri', sortSelect.value);
    if (filter !== 'all') params.set('vue', filter);
    var qs = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
  }

  function apply() {
    var query = norm(search ? search.value : '');
    var terms = query ? query.split(/\s+/) : [];
    var visible = 0;
    cards.forEach(function (card) {
      var open = card.getAttribute('data-cr-open') === '1';
      var okFilter = filter === 'all'
        || (filter === 'pinned' && card.getAttribute('data-cr-pinned') === '1')
        || (filter === 'trending' && card.getAttribute('data-cr-trend') === '1')
        || (filter === 'featured' && card.getAttribute('data-cr-featured') === '1')
        || (filter === 'open' && open)
        || (filter === 'closed' && !open);
      var okSearch = terms.every(function (t) { return card._blob.indexOf(t) !== -1; });
      var okSelects = selects.every(function (f) {
        var want = norm(f.el.value);
        if (!want) return true;
        var have = norm(card.getAttribute(f.attr));
        return f.list ? (' ' + have + ' ').indexOf(' ' + want + ' ') !== -1 : have === want;
      });
      var show = okFilter && okSearch && okSelects;
      card.classList.toggle('is-hidden', !show);
      if (show) visible += 1;
    });
    var active = !!query || filter !== 'all' || selects.some(function (f) { return !!f.el.value; });
    if (countLabel) countLabel.textContent = labelFor(visible);
    if (empty) empty.hidden = visible !== 0;
    if (trendStrip) trendStrip.hidden = active && filter !== 'trending';
    resets.forEach(function (r) { if (!r.closest('[data-cr-empty]')) r.hidden = !active && !(sortSelect && sortSelect.value); });
    syncUrl();
  }

  // Lien partagé : /communities?q=…&pays=…&vue=tendance
  if (window.URLSearchParams) {
    var init = new URLSearchParams(window.location.search);
    if (search && init.get('q')) search.value = init.get('q');
    selects.forEach(function (f) {
      var v = init.get(f.param);
      if (v && f.el.querySelector('option[value="' + v.replace(/"/g, '') + '"]')) f.el.value = v;
    });
    if (sortSelect && init.get('tri') && sortSelect.querySelector('option[value="' + init.get('tri').replace(/"/g, '') + '"]')) {
      sortSelect.value = init.get('tri');
    }
    var vue = init.get('vue');
    if (vue === 'tendance') vue = 'trending';
    if (vue === 'epinglees') vue = 'pinned';
    setChip(vue || 'all');
  }

  if (form) form.addEventListener('submit', function (e) { e.preventDefault(); apply(); });
  if (search) search.addEventListener('input', apply);
  selects.forEach(function (f) { f.el.addEventListener('change', apply); });
  if (sortSelect) sortSelect.addEventListener('change', function () { sortCards(); apply(); });
  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      setChip(chip.getAttribute('data-cr-filter'));
      apply();
    });
  });
  resets.forEach(function (reset) {
    reset.addEventListener('click', function () {
      if (search) search.value = '';
      selects.forEach(function (f) { f.el.value = ''; });
      if (sortSelect) sortSelect.value = '';
      setChip('all');
      sortCards();
      apply();
    });
  });
  // « / » place le curseur dans la recherche.
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || !search || e.ctrlKey || e.metaKey || e.altKey) return;
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag) || (document.activeElement && document.activeElement.isContentEditable)) return;
    e.preventDefault();
    search.focus();
  });

  sortCards();
  apply();
})();
</script>
