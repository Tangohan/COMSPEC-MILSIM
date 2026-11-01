<?php
/** @var list<array<string, mixed>> $registryTenants */
$registryTenants = array_values($registryTenants ?? []);
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
    $u = trim((string) ($tRow['registry_unit_affiliation_label'] ?? ''));
    if ($u !== '') {
        $uKey = mb_strtolower($u);
        $filterUnits[$uKey] ??= ['label' => $u, 'count' => 0];
        ++$filterUnits[$uKey]['count'];
    }
    $locKey = trim((string) ($tRow['registry_locale'] ?? ''));
    $locLabel = trim((string) ($tRow['registry_locale_label'] ?? ''));
    if ($locKey !== '' && $locLabel !== '') {
        $filterLocales[$locKey] = $locLabel;
    }
}
// Jeux : « Arma3 », « ARMA 3 » et « ArmA III » ne font qu'un seul choix (App\Support\GameCatalog).
$gameGrouping = \App\Support\GameCatalog::group(array_map(static fn (array $r): string => (string) ($r['game_label'] ?? ''), $registryTenants));
$filterGames = $gameGrouping['groups'];
$cardGameKeys = $gameGrouping['keys'];
// Anciens liens (?jeu=arma 3, ?jeu=a3) : clé de l'alias => clé du groupe affiché.
$gameAliasKeys = [];
foreach (\App\Support\GameCatalog::aliasIndex() as $aliasKey => $aliasName) {
    $gameAliasKeys[(string) $aliasKey] = \App\Support\GameCatalog::key($aliasName);
}
uasort($filterUnits, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
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
$filterCounts = ['pays' => [], 'langue' => [], 'type' => [], 'tag' => []];
foreach ($registryTenants as $fcRow) {
    foreach ((is_array($fcRow['registry_style_badge_slugs'] ?? null) ? $fcRow['registry_style_badge_slugs'] : []) as $fcType) {
        if (is_string($fcType) && $fcType !== '') {
            $filterCounts['type'][$fcType] = ($filterCounts['type'][$fcType] ?? 0) + 1;
        }
    }
    $fcCountry = strtolower(trim((string) ($fcRow['registry_country'] ?? '')));
    if ($fcCountry !== '') {
        $filterCounts['pays'][$fcCountry] = ($filterCounts['pays'][$fcCountry] ?? 0) + 1;
    }
    $fcLang = trim((string) ($fcRow['registry_locale'] ?? ''));
    if ($fcLang !== '') {
        $filterCounts['langue'][$fcLang] = ($filterCounts['langue'][$fcLang] ?? 0) + 1;
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
/*
 * Groupes du panneau de filtres : nom du paramètre d'URL => titre, options (valeur => [libellé, nombre]),
 * attribut de carte comparé, liste (plusieurs valeurs par carte) et recherche dans les options.
 */
$facetOptions = static function (array $labels, string $group) use ($filterCounts): array {
    $out = [];
    foreach ($labels as $key => $label) {
        $n = (int) ($filterCounts[$group][(string) $key] ?? 0);
        if ($n > 0) {
            $out[(string) $key] = [(string) $label, $n];
        }
    }
    return $out;
};
$registryFacets = [];
$registryFacets['jeu'] = ['title' => 'Jeu', 'all' => 'Tous les jeux', 'attr' => 'data-cr-game', 'list' => true,
    'options' => array_map(static fn (array $g): array => [$g['label'], $g['count']], $filterGames)];
$registryFacets['type'] = ['title' => 'Type de communauté', 'all' => 'Tous les types', 'attr' => 'data-cr-type', 'list' => true,
    'options' => $facetOptions($styleBadgeCatalog, 'type')];
$registryFacets['unite'] = ['title' => 'Unité réelle de référence', 'all' => 'Toutes les unités', 'attr' => 'data-cr-unit', 'list' => false,
    'options' => array_map(static fn (array $u): array => [$u['label'], $u['count']], $filterUnits)];
$registryFacets['pays'] = ['title' => 'Pays', 'all' => 'Tous les pays', 'attr' => 'data-cr-country', 'list' => false,
    'options' => $facetOptions(array_combine(array_map(static fn ($k): string => strtolower((string) $k), array_keys($filterCountries)), array_values($filterCountries)) ?: [], 'pays')];
$registryFacets['langue'] = ['title' => 'Langue', 'all' => 'Toutes les langues', 'attr' => 'data-cr-lang', 'list' => false,
    'options' => $facetOptions($filterLocales, 'langue')];
$registryFacets['tag'] = ['title' => 'Thèmes', 'all' => 'Tous les thèmes', 'attr' => 'data-cr-tags', 'list' => true,
    'options' => $facetOptions($filterTags, 'tag')];
$registryFacets = array_filter($registryFacets, static fn (array $f): bool => $f['options'] !== []);

$registryViews = ['all' => ['Toutes', $registryCount]];
if ($registryPinnedCount > 0) {
    $registryViews['pinned'] = ['Épinglées', $registryPinnedCount];
}
if ($registryTrending !== []) {
    $registryViews['trending'] = ['Tendance', count($registryTrending)];
}
$registryViews['featured'] = ['À la une', null];
$registryViews['open'] = ['Recrutement ouvert', $registryOpenCount];
$registryViews['closed'] = ['Recrutement fermé', $registryCount - $registryOpenCount];

$registrySorts = [
    '' => 'Recommandé',
    'tendance' => 'Tendance',
    'nom' => 'Nom (A → Z)',
    'recent' => 'Plus récentes',
    'membres' => 'Plus de membres',
    'activite' => 'Les plus actives (30 j)',
    'ops' => 'Opérations à venir',
];

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
        <div class="cr-filters__bar">
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
              placeholder="Nom, pays, langue, jeu, tag…"
              autocomplete="off"
              data-cr-search
            >
            <kbd class="cr-search__kbd" aria-hidden="true">/</kbd>
          </label>

          <?php if ($registryFacets !== []): ?>
          <button type="button" class="cr-fbtn" data-cr-open-filters aria-haspopup="dialog" aria-controls="cr-fdialog" aria-expanded="false" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
            <span>Filtres</span>
            <span class="cr-fbtn__n" data-cr-filter-count hidden>0</span>
          </button>
          <?php endif; ?>

          <label class="cr-sortbox" for="cr-sort-select">
            <span class="cr-sortbox__label">Trier par</span>
            <select id="cr-sort-select" name="tri" data-cr-sort>
              <?php foreach ($registrySorts as $sortKey => $sortLabel): ?>
              <option value="<?= htmlspecialchars((string) $sortKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sortLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="cr-chips" role="group" aria-label="Affichage rapide">
          <?php foreach ($registryViews as $viewKey => [$viewLabel, $viewCount]): ?>
          <button type="button" class="cr-chip<?= $viewKey === 'all' ? ' is-active' : '' ?>" data-cr-filter="<?= htmlspecialchars((string) $viewKey, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="<?= $viewKey === 'all' ? 'true' : 'false' ?>"><?= htmlspecialchars($viewLabel) ?><?php if ($viewCount !== null): ?> <span class="cr-chip__n"><?= (int) $viewCount ?></span><?php endif; ?></button>
          <?php endforeach; ?>
        </div>

        <div class="cr-active" data-cr-active hidden>
          <span class="cr-active__label" id="cr-active-label">Filtres actifs</span>
          <ul class="cr-active__list" aria-labelledby="cr-active-label" data-cr-active-list></ul>
          <button type="button" class="cr-active__clear" data-cr-reset>Tout effacer</button>
        </div>

        <?php if ($registryFacets !== []): ?>
        <noscript><style>.cr-fdialog { display: block; position: static; inset: auto; width: auto; max-width: none; max-height: none; margin: 1rem 0 0; box-shadow: none; } .cr-fdialog__close, .cr-fdialog__apply { display: none; }</style></noscript>
        <dialog class="cr-fdialog" id="cr-fdialog" aria-labelledby="cr-fdialog-title" data-cr-dialog>
          <div class="cr-fdialog__panel">
            <header class="cr-fdialog__head">
              <h2 id="cr-fdialog-title" class="cr-fdialog__title">Filtrer les communautés</h2>
              <button type="button" class="cr-fdialog__close" data-cr-close-filters aria-label="Fermer les filtres">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
              </button>
            </header>
            <div class="cr-fdialog__body">
              <?php foreach ($registryFacets as $facetParam => $facet): ?>
              <?php $facetFind = count($facet['options']) > 8; ?>
              <fieldset class="cr-facet" data-cr-facet="<?= htmlspecialchars((string) $facetParam, ENT_QUOTES, 'UTF-8') ?>" data-cr-facet-attr="<?= htmlspecialchars($facet['attr'], ENT_QUOTES, 'UTF-8') ?>" data-cr-facet-list="<?= $facet['list'] ? '1' : '0' ?>" data-cr-facet-title="<?= htmlspecialchars($facet['title'], ENT_QUOTES, 'UTF-8') ?>">
                <legend class="cr-facet__title"><?= htmlspecialchars($facet['title']) ?></legend>
                <?php if ($facetFind): ?>
                <input type="search" class="cr-facet__find" placeholder="Chercher dans la liste…" aria-label="Chercher : <?= htmlspecialchars($facet['title'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" data-cr-facet-find>
                <?php endif; ?>
                <div class="cr-facet__opts<?= $facetFind ? ' cr-facet__opts--scroll' : '' ?>">
                  <label class="cr-opt"><input type="radio" name="<?= htmlspecialchars((string) $facetParam, ENT_QUOTES, 'UTF-8') ?>" value="" checked><span class="cr-opt__label"><?= htmlspecialchars($facet['all']) ?></span></label>
                  <?php foreach ($facet['options'] as $optValue => [$optLabel, $optCount]): ?>
                  <label class="cr-opt"><input type="radio" name="<?= htmlspecialchars((string) $facetParam, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $optValue, ENT_QUOTES, 'UTF-8') ?>"><span class="cr-opt__label"><?= htmlspecialchars((string) $optLabel) ?></span><span class="cr-opt__n"><?= (int) $optCount ?></span></label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
              <?php endforeach; ?>
            </div>
            <footer class="cr-fdialog__foot">
              <button type="button" class="cr-btn cr-btn--line cr-btn--sm" data-cr-reset>Réinitialiser</button>
              <button type="button" class="cr-btn cr-btn--ink cr-btn--sm cr-fdialog__apply" data-cr-close-filters data-cr-apply-label>Afficher les communautés</button>
            </footer>
          </div>
        </dialog>
        <?php endif; ?>
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
              \App\Support\GameCatalog::normalize((string) ($tt['game_label'] ?? '')),
              trim((string) ($tt['registry_country_label'] ?? '')),
              trim((string) ($tt['registry_locale_label'] ?? '')),
          ]));
          ?>
        <li class="cr-trend__item">
          <a class="cr-trend__card" href="<?= htmlspecialchars(url('c/' . rawurlencode($ttSlug) . '?ref=registry')) ?>">
            <span class="cr-trend__rank" aria-label="Rang <?= (int) $rank ?>">#<?= (int) $rank ?></span>
            <?php if ($ttLogo !== '' && filter_var($ttLogo, FILTER_VALIDATE_URL)): ?>
            <span class="cr-trend__logo"><img src="<?= htmlspecialchars($ttLogo) ?>" alt="" loading="lazy" decoding="async"></span>
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
          $tagline = trim((string) ($t['registry_tagline'] ?? ''));
          if ($excerpt === '' && $tagline !== '') {
              $excerpt = $tagline;
          }
          // Une présentation qui ne fait que répéter le jeu n’apporte rien sur la carte.
          if ($excerpt !== '' && mb_strtolower($excerpt) === mb_strtolower(trim((string) ($t['game_label'] ?? '')))) {
              $excerpt = '';
          }
          $styleBadgeLabels = is_array($t['registry_style_badge_labels'] ?? null) ? $t['registry_style_badge_labels'] : [];
          $styleBadgeSlugs = is_array($t['registry_style_badge_slugs'] ?? null) ? $t['registry_style_badge_slugs'] : [];
          $registryTagLabels = is_array($t['registry_tag_labels'] ?? null) ? $t['registry_tag_labels'] : [];
          $unitAffiliationLabel = trim((string) ($t['registry_unit_affiliation_label'] ?? ''));
          $gameLabel = \App\Support\GameCatalog::normalize((string) ($t['game_label'] ?? ''));
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
          // Habillage et chiffres (CommunityCardDecorator) : la carte reste lisible sans eux.
          $cardBanner = trim((string) ($t['card_banner'] ?? ''));
          if ($cardBanner === '' && $coverUrl !== null) {
              $cardBanner = $coverUrl;
          }
          $cardAccent = \App\Support\CommunityCardDecorator::color((string) ($t['card_accent'] ?? '')) ?? '';
          $cardLogo = trim((string) ($t['card_logo'] ?? ''));
          if ($cardLogo === '' && $logoUrl !== '' && filter_var($logoUrl, FILTER_VALIDATE_URL)) {
              $cardLogo = $logoUrl;
          }
          $cardMembers = (int) ($t['card_members'] ?? 0);
          $cardActivity = isset($t['card_activity']) && $t['card_activity'] !== null ? (int) $t['card_activity'] : null;
          $cardUnits = (int) ($t['card_units'] ?? 0);
          $cardOps = (float) ($t['card_ops'] ?? 0);
          $cardUpcoming = (int) ($t['card_upcoming'] ?? 0);
          $cardRating = isset($t['card_rating']) && $t['card_rating'] !== null ? (float) $t['card_rating'] : null;
          $cardRatingCount = (int) ($t['card_rating_count'] ?? 0);
          $cardAge = \App\Support\CommunityCardDecorator::age(isset($t['card_age_days']) ? (int) $t['card_age_days'] : null);
          $hasStats = $cardMembers > 0 || $cardUnits > 0 || $cardOps > 0;
          $allTags = [];
          foreach ($styleBadgeLabels as $bl) { if (is_string($bl) && $bl !== '') { $allTags[] = ['info', $bl]; } }
          if ($countryLabel !== '') { $allTags[] = ['muted', $countryLabel]; }
          if ($localeLabel !== '') { $allTags[] = ['muted', $localeLabel]; }
          foreach ($registryTagLabels as $tl) { if (is_string($tl) && $tl !== '') { $allTags[] = ['muted', $tl]; } }
          $tagsShown = array_slice($allTags, 0, 4);
          $tagsMore = count($allTags) - count($tagsShown);
          $cardStyle = ($cardAccent !== '' ? '--cr-acc: ' . $cardAccent . ';' : '');
          $delayClass = 'cr-rise cr-rise-d' . min(3, 1 + ($i % 3));
          $supportKind = \App\Services\Billing\DonorService::tenantSupport((int) ($t['id'] ?? 0));
          ?>
        <li
          class="cr-card <?= $delayClass ?><?= $featured ? ' cr-card--featured' : '' ?><?= $pinned ? ' cr-card--pinned cr-card--spot' : '' ?><?= $cardAccent !== '' ? ' has-accent' : '' ?><?= $supportKind !== null ? ' cr-card--supporter' : '' ?>"<?= $cardStyle !== '' ? ' style="' . htmlspecialchars($cardStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
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
          data-cr-game="<?= htmlspecialchars(implode(' ', $cardGameKeys[$i] ?? []), ENT_QUOTES, 'UTF-8') ?>"
          data-cr-members="<?= $cardMembers ?>"
          data-cr-activity="<?= $cardActivity ?? -1 ?>"
          data-cr-upcoming="<?= $cardUpcoming ?>"
          data-cr-unit="<?= htmlspecialchars(mb_strtolower($unitAffiliationLabel), ENT_QUOTES, 'UTF-8') ?>"
          data-cr-type="<?= htmlspecialchars($typeAttr, ENT_QUOTES, 'UTF-8') ?>"
          data-cr-lang="<?= htmlspecialchars($localeKey, ENT_QUOTES, 'UTF-8') ?>"
        >
          <div class="cr-card__cover">
            <?php if ($cardBanner !== ''): ?>
            <img src="<?= htmlspecialchars($cardBanner) ?>" alt="" loading="lazy" decoding="async">
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
              <?php if ($supportKind !== null): ?>
              <span class="cr-pill cr-pill--supporter" title="Cette communauté soutient Athena (formule payée, soutien du cœur ou dons de ses membres)"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z"/></svg> Soutient Athena</span>
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
                <p class="cr-card__unit" translate="no"><?= htmlspecialchars($unitAffiliationLabel) ?></p>
                <?php endif; ?>
              </div>
              <?php if ($cardLogo !== ''): ?>
              <div class="cr-card__logo">
                <img src="<?= htmlspecialchars($cardLogo) ?>" alt="" loading="lazy" decoding="async">
              </div>
              <?php else: ?>
              <div class="cr-card__logo cr-card__logo-fallback" aria-hidden="true"><?= htmlspecialchars($nameInitials($name)) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($hasStats): ?>
          <dl class="cr-stats" aria-label="Chiffres de la communauté">
            <div class="cr-stat"><dt>Membres</dt><dd data-cr-count-to="<?= $cardMembers ?>"><?= htmlspecialchars(\App\Support\CommunityCardDecorator::compact($cardMembers)) ?></dd></div>
            <div class="cr-stat"><dt>Actifs 30 j</dt><dd><?= $cardActivity !== null ? $cardActivity . '<small> %</small>' : '—' ?></dd></div>
            <div class="cr-stat"><dt>Unités ORBAT</dt><dd data-cr-count-to="<?= $cardUnits ?>"><?= $cardUnits ?></dd></div>
            <div class="cr-stat"><dt>Ops / mois</dt><dd><?= htmlspecialchars(\App\Support\CommunityCardDecorator::compact($cardOps)) ?></dd></div>
          </dl>
          <?php endif; ?>

          <div class="cr-card__body">
            <div class="cr-tags">
              <span class="cr-pill <?= $simpleReg ? 'cr-pill--muted' : 'cr-pill--ok' ?>">
                <?= $simpleReg ? 'Inscription simple' : 'Parcours MilSim' ?>
              </span>
              <?php if ($locked): ?>
              <span class="cr-pill cr-pill--warn">Sur invitation</span>
              <?php endif; ?>
              <?php foreach ($tagsShown as [$tagTone, $tagLabel]): ?>
              <span class="cr-pill cr-pill--<?= $tagTone === 'info' ? 'info' : 'muted' ?>"><?= htmlspecialchars((string) $tagLabel) ?></span>
              <?php endforeach; ?>
              <?php if ($tagsMore > 0): ?>
              <span class="cr-pill cr-pill--muted" title="<?= htmlspecialchars(implode(' · ', array_map(static fn ($x) => (string) $x[1], array_slice($allTags, 4))), ENT_QUOTES, 'UTF-8') ?>">+<?= (int) $tagsMore ?></span>
              <?php endif; ?>
            </div>

            <?php if ($excerpt !== ''): ?>
            <p class="cr-excerpt"><?= nl2br(htmlspecialchars($excerpt)) ?></p>
            <?php else: ?>
            <p class="cr-excerpt cr-excerpt--empty">
              Pas encore de présentation : ouvrez la fiche publique pour découvrir le recrutement et la vie de l’unité.
            </p>
            <?php endif; ?>

            <?php if ($cardUpcoming > 0 || $cardRatingCount > 0 || $cardAge !== ''): ?>
            <ul class="cr-facts">
              <?php if ($cardUpcoming > 0): ?>
              <li class="cr-fact cr-fact--live"><span class="cr-pulse" aria-hidden="true"></span><?= $cardUpcoming === 1 ? '1 opération à venir' : $cardUpcoming . ' opérations à venir' ?></li>
              <?php endif; ?>
              <?php if ($cardRatingCount > 0 && $cardRating !== null): ?>
              <li class="cr-fact"><span class="cr-stars" style="--v: <?= htmlspecialchars((string) round($cardRating / 5 * 100), ENT_QUOTES, 'UTF-8') ?>%" aria-hidden="true">★★★★★</span><?= htmlspecialchars(str_replace('.', ',', (string) $cardRating)) ?> · <?= $cardRatingCount ?> avis</li>
              <?php endif; ?>
              <?php if ($cardAge !== ''): ?>
              <li class="cr-fact">Sur Athena depuis <?= htmlspecialchars($cardAge) ?></li>
              <?php endif; ?>
            </ul>
            <?php endif; ?>

            <?php if ($loggedIn && $code !== ''): ?>
            <div class="cr-code">
              <span class="cr-code__label">Code</span>
              <code class="cr-code__value cr-mono" title="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($code) ?></code>
              <button
                type="button"
                class="cr-code__copy"
                data-registry-copy="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="Copier le code communauté"
              >
                <span data-registry-copy-label>Copier</span>
              </button>
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
  var dialog = root.querySelector('[data-cr-dialog]');
  var openBtn = root.querySelector('[data-cr-open-filters]');
  var filterCount = root.querySelector('[data-cr-filter-count]');
  var applyBtn = root.querySelector('[data-cr-apply-label]');
  var activeBox = root.querySelector('[data-cr-active]');
  var activeList = root.querySelector('[data-cr-active-list]');
  // Anciens liens partagés (?jeu=arma 3, ?jeu=a3) : clé d'alias => clé du jeu affiché.
  var gameAliases = <?= json_encode((object) $gameAliasKeys, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  // Groupes du panneau : paramètre d'URL, attribut de carte comparé, valeurs multiples ou non.
  var facets = Array.prototype.slice.call(root.querySelectorAll('[data-cr-facet]')).map(function (fs) {
    return {
      fs: fs,
      param: fs.getAttribute('data-cr-facet'),
      attr: fs.getAttribute('data-cr-facet-attr'),
      list: fs.getAttribute('data-cr-facet-list') === '1',
      title: fs.getAttribute('data-cr-facet-title') || '',
      radios: Array.prototype.slice.call(fs.querySelectorAll('input[type="radio"]'))
    };
  });
  var modal = false;
  var filter = 'all';

  function norm(v) {
    return (v || '').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }
  function gameKey(v) {
    return norm(v).replace(/[^a-z0-9]+/g, '');
  }
  cards.forEach(function (card) {
    card._blob = norm(card.getAttribute('data-cr-search'));
  });

  function checkedRadio(f) {
    return f.radios.filter(function (r) { return r.checked; })[0] || null;
  }
  function valueOf(f) {
    var on = checkedRadio(f);
    return on ? on.value : '';
  }
  function setValue(f, v) {
    var hit = false;
    f.radios.forEach(function (r) { r.checked = r.value === v; if (r.checked) hit = true; });
    if (!hit && f.radios[0]) f.radios[0].checked = true;
    return hit;
  }
  function labelOf(f) {
    var on = checkedRadio(f);
    var span = on && on.parentNode.querySelector('.cr-opt__label');
    return span ? span.textContent : '';
  }

  function labelFor(n) {
    if (n === 0) return 'Aucune communauté correspondante';
    if (n === 1) return '1 communauté visible';
    return n + ' communautés visibles';
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

  function num(card, attr) {
    return +card.getAttribute(attr) || 0;
  }
  function sortCards() {
    var mode = sortSelect ? sortSelect.value : '';
    var byIndex = function (a, b) { return num(a, 'data-cr-index') - num(b, 'data-cr-index'); };
    var desc = function (attr) {
      return function (a, b) { return num(b, attr) - num(a, attr) || byIndex(a, b); };
    };
    var cmp = {
      tendance: desc('data-cr-score'),
      recent: desc('data-cr-created'),
      membres: desc('data-cr-members'),
      activite: desc('data-cr-activity'),
      ops: desc('data-cr-upcoming'),
      nom: function (a, b) {
        return (a.getAttribute('data-cr-name') || '').localeCompare(b.getAttribute('data-cr-name') || '', 'fr', { sensitivity: 'base' });
      }
    }[mode] || byIndex;
    cards.slice().sort(cmp).forEach(function (card) { grid.appendChild(card); });
  }

  function syncUrl() {
    if (!window.history || !window.history.replaceState || !window.URLSearchParams) return;
    var params = new URLSearchParams(window.location.search);
    ['q', 'pays', 'langue', 'type', 'tag', 'jeu', 'unite', 'tri', 'vue'].forEach(function (k) { params.delete(k); });
    if (search && search.value.trim()) params.set('q', search.value.trim());
    facets.forEach(function (f) { var v = valueOf(f); if (v) params.set(f.param, v); });
    if (sortSelect && sortSelect.value) params.set('tri', sortSelect.value);
    if (filter !== 'all') params.set('vue', filter);
    var qs = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
  }

  // Puces des filtres actifs : un clic retire le filtre.
  function renderActive() {
    if (!activeList || !activeBox) return 0;
    activeList.textContent = '';
    var n = 0;
    facets.forEach(function (f) {
      if (!valueOf(f)) return;
      n += 1;
      var text = f.title + ' : ' + labelOf(f);
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'cr-apill';
      b.setAttribute('aria-label', 'Retirer le filtre ' + text);
      var span = document.createElement('span');
      span.textContent = text;
      b.appendChild(span);
      b.insertAdjacentHTML('beforeend', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" d="M7 7l10 10M17 7L7 17"/></svg>');
      b.addEventListener('click', function () {
        setValue(f, '');
        apply();
        var next = activeList.querySelector('button') || (openBtn && !openBtn.hidden ? openBtn : search);
        if (next) next.focus();
      });
      li.appendChild(b);
      activeList.appendChild(li);
    });
    activeBox.hidden = n === 0;
    return n;
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
      var okFacets = facets.every(function (f) {
        var want = norm(valueOf(f));
        if (!want) return true;
        var have = norm(card.getAttribute(f.attr));
        return f.list ? (' ' + have + ' ').indexOf(' ' + want + ' ') !== -1 : have === want;
      });
      var show = okFilter && okSearch && okFacets;
      card.classList.toggle('is-hidden', !show);
      if (show) visible += 1;
    });
    var nFacets = renderActive();
    var active = !!query || filter !== 'all' || nFacets > 0;
    if (countLabel) countLabel.textContent = labelFor(visible);
    if (filterCount) { filterCount.hidden = nFacets === 0; filterCount.textContent = String(nFacets); }
    if (openBtn) openBtn.setAttribute('aria-label', nFacets ? 'Filtres (' + nFacets + ' actif' + (nFacets > 1 ? 's' : '') + ')' : 'Filtres');
    if (applyBtn) applyBtn.textContent = visible === 0 ? 'Aucun résultat' : (visible === 1 ? 'Afficher 1 communauté' : 'Afficher ' + visible + ' communautés');
    if (empty) empty.hidden = visible !== 0;
    if (trendStrip) trendStrip.hidden = active && filter !== 'trending';
    syncUrl();
  }

  // Panneau des filtres en fenêtre modale : Échap ou clic hors du panneau ferment, le focus revient au bouton.
  if (dialog && openBtn && typeof dialog.showModal === 'function') {
    modal = true;
    openBtn.hidden = false;
    dialog.classList.add('is-modal');
    openBtn.addEventListener('click', function () {
      dialog.showModal();
      openBtn.setAttribute('aria-expanded', 'true');
      var first = dialog.querySelector('.cr-facet input:checked') || dialog.querySelector('input');
      if (first) first.focus();
    });
    Array.prototype.forEach.call(dialog.querySelectorAll('[data-cr-close-filters]'), function (b) {
      b.addEventListener('click', function () { dialog.close(); });
    });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('close', function () {
      openBtn.setAttribute('aria-expanded', 'false');
      openBtn.focus();
    });
  } else if (dialog) {
    // Navigateur sans fenêtre modale : le panneau reste affiché sous la barre.
    dialog.setAttribute('open', '');
    dialog.classList.add('is-inline');
  }
  // Recherche dans les longues listes (jeux, unités, thèmes).
  facets.forEach(function (f) {
    var find = f.fs.querySelector('[data-cr-facet-find]');
    if (!find) return;
    find.addEventListener('input', function () {
      var t = norm(find.value);
      f.radios.forEach(function (r, i) {
        r.parentNode.hidden = i > 0 && !!t && !r.checked && norm(r.parentNode.textContent).indexOf(t) === -1;
      });
    });
  });

  // Lien partagé : /communities?q=…&jeu=arma3&tri=membres&vue=open
  if (window.URLSearchParams) {
    var init = new URLSearchParams(window.location.search);
    if (search && init.get('q')) search.value = init.get('q');
    facets.forEach(function (f) {
      var v = init.get(f.param);
      if (!v) return;
      if (!setValue(f, v) && !setValue(f, v.toLowerCase()) && f.param === 'jeu') {
        var k = gameKey(v);
        setValue(f, gameAliases[k] || k);
      }
    });
    var tri = init.get('tri');
    if (sortSelect && tri && Array.prototype.some.call(sortSelect.options, function (o) { return o.value === tri; })) {
      sortSelect.value = tri;
    }
    var vue = init.get('vue');
    if (vue === 'tendance') vue = 'trending';
    if (vue === 'epinglees') vue = 'pinned';
    setChip(vue || 'all');
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      apply();
      if (modal && dialog.open) dialog.close();
    });
  }
  if (search) search.addEventListener('input', apply);
  facets.forEach(function (f) { f.radios.forEach(function (r) { r.addEventListener('change', apply); }); });
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
      facets.forEach(function (f) {
        setValue(f, '');
        var find = f.fs.querySelector('[data-cr-facet-find]');
        if (find) find.value = '';
        f.radios.forEach(function (r) { r.parentNode.hidden = false; });
      });
      if (sortSelect) sortSelect.value = '';
      setChip('all');
      sortCards();
      apply();
      if (!reset.closest('[data-cr-dialog]') && search) search.focus();
    });
  });
  // « / » place le curseur dans la recherche.
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || !search || e.ctrlKey || e.metaKey || e.altKey) return;
    if (modal && dialog.open) return;
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag) || (document.activeElement && document.activeElement.isContentEditable)) return;
    e.preventDefault();
    search.focus();
  });

  sortCards();
  apply();

  // Chiffres des cartes : comptage animé à l’apparition (désactivé si mouvement réduit).
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var counters = Array.prototype.slice.call(root.querySelectorAll('[data-cr-count-to]'));
  if (!reduce && counters.length && 'IntersectionObserver' in window) {
    var run = function (el) {
      var to = parseInt(el.getAttribute('data-cr-count-to'), 10) || 0;
      if (to <= 0 || to >= 1000) return;
      var t0 = null;
      var step = function (ts) {
        if (t0 === null) t0 = ts;
        var p = Math.min(1, (ts - t0) / 900);
        el.textContent = String(Math.round(to * (1 - Math.pow(1 - p, 3))));
        if (p < 1) window.requestAnimationFrame(step);
      };
      window.requestAnimationFrame(step);
    };
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { io.observe(el); });
  }
})();
</script>
