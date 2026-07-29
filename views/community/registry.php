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
foreach ($registryTenants as $tRow) {
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

if ($registryCount === 0) {
    $countLabel = 'Aucune communauté listée';
} elseif ($registryCount === 1) {
    $countLabel = '1 communauté visible';
} else {
    $countLabel = $registryCount . ' communautés visibles';
}
?>
<div class="community-registry cr-shell" data-community-registry>
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
      <div class="cr-filters" role="search">
        <label class="cr-search" for="cr-search-input">
          <span class="sr-only">Rechercher une communauté</span>
          <svg class="cr-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
          </svg>
          <input
            type="search"
            id="cr-search-input"
            class="cr-search__input"
            placeholder="Nom, thème, présentation…"
            autocomplete="off"
            data-cr-search
          >
        </label>

        <div class="cr-filter-selects">
          <?php if ($filterGames !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par jeu</span>
            <select data-cr-game aria-label="Jeu">
              <option value="">Tous les jeux</option>
              <?php foreach ($filterGames as $gameOpt): ?>
              <option value="<?= htmlspecialchars($gameOpt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($gameOpt) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>

          <?php if ($filterUnits !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par unité</span>
            <select data-cr-unit aria-label="Unité">
              <option value="">Toutes les unités</option>
              <?php foreach ($filterUnits as $unitOpt): ?>
              <option value="<?= htmlspecialchars($unitOpt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($unitOpt) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>

          <label class="cr-select">
            <span class="sr-only">Filtrer par type</span>
            <select data-cr-type aria-label="Type de communauté">
              <option value="">Tous les types</option>
              <?php foreach ($styleBadgeCatalog as $typeSlug => $typeLabel): ?>
              <option value="<?= htmlspecialchars((string) $typeSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $typeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <?php if ($filterLocales !== []): ?>
          <label class="cr-select">
            <span class="sr-only">Filtrer par langue</span>
            <select data-cr-lang aria-label="Langue">
              <option value="">Toutes les langues</option>
              <?php foreach ($filterLocales as $langKey => $langLabel): ?>
              <option value="<?= htmlspecialchars((string) $langKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $langLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>
        </div>

        <div class="cr-chips" role="group" aria-label="Filtrer par recrutement">
          <button type="button" class="cr-chip is-active" data-cr-filter="all" aria-pressed="true">Toutes</button>
          <button type="button" class="cr-chip" data-cr-filter="open" aria-pressed="false">Recrutement ouvert</button>
          <button type="button" class="cr-chip" data-cr-filter="closed" aria-pressed="false">Recrutement fermé</button>
        </div>
      </div>
      <?php endif; ?>
    </section>

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
          $name = (string) ($t['name'] ?? $slug);
          $code = trim((string) ($t['community_code'] ?? ''));
          $logoUrl = trim((string) ($t['logo_url'] ?? ''));
          $locked = !empty($t['registry_locked']);
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
              implode(' ', array_map('strval', $styleBadgeLabels)),
              implode(' ', array_map('strval', $registryTagLabels)),
              $unitAffiliationLabel,
              $localeLabel,
          ]))));
          $typeAttr = implode(' ', array_map('strval', $styleBadgeSlugs));
          $delayClass = 'cr-rise cr-rise-d' . min(3, 1 + ($i % 3));
          ?>
        <li
          class="cr-card <?= $delayClass ?>"
          data-cr-card
          data-cr-locked="<?= $locked ? '1' : '0' ?>"
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
              <?php if (!$locked): ?>
              <span class="cr-pill cr-pill--live"><span class="cr-pulse" aria-hidden="true"></span> Recrutement ouvert</span>
              <?php else: ?>
              <span class="cr-pill cr-pill--warn">Recrutement fermé</span>
              <?php endif; ?>
              <span class="cr-pill cr-pill--glass">Fiche publique</span>
            </div>

            <div class="cr-card__foot">
              <div class="min-w-0">
                <h3 class="cr-card__name"><?= htmlspecialchars($name) ?></h3>
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
  var cards = Array.prototype.slice.call(root.querySelectorAll('[data-cr-card]'));
  if (!cards.length) return;
  var search = root.querySelector('[data-cr-search]');
  var gameSelect = root.querySelector('[data-cr-game]');
  var unitSelect = root.querySelector('[data-cr-unit]');
  var typeSelect = root.querySelector('[data-cr-type]');
  var langSelect = root.querySelector('[data-cr-lang]');
  var chips = Array.prototype.slice.call(root.querySelectorAll('[data-cr-filter]'));
  var empty = root.querySelector('[data-cr-empty]');
  var countLabel = root.querySelector('[data-cr-count-label]');
  var filter = 'all';
  var query = '';
  var game = '';
  var unit = '';
  var type = '';
  var lang = '';

  function labelFor(n) {
    if (n === 0) return 'Aucune communauté correspondante';
    if (n === 1) return '1 communauté visible';
    return n + ' communautés visibles';
  }

  function norm(v) {
    return (v || '').trim().toLowerCase();
  }

  function apply() {
    var visible = 0;
    cards.forEach(function (card) {
      var locked = card.getAttribute('data-cr-locked') === '1';
      var blob = card.getAttribute('data-cr-search') || '';
      var cardGame = card.getAttribute('data-cr-game') || '';
      var cardUnit = card.getAttribute('data-cr-unit') || '';
      var cardType = ' ' + (card.getAttribute('data-cr-type') || '') + ' ';
      var cardLang = card.getAttribute('data-cr-lang') || '';
      var okFilter = filter === 'all' || (filter === 'open' && !locked) || (filter === 'closed' && locked);
      var okSearch = !query || blob.indexOf(query) !== -1;
      var okGame = !game || cardGame === game;
      var okUnit = !unit || cardUnit === unit;
      var okType = !type || cardType.indexOf(' ' + type + ' ') !== -1;
      var okLang = !lang || cardLang === lang;
      var show = okFilter && okSearch && okGame && okUnit && okType && okLang;
      card.classList.toggle('is-hidden', !show);
      if (show) visible += 1;
    });
    if (countLabel) countLabel.textContent = labelFor(visible);
    if (empty) empty.hidden = visible !== 0;
  }

  if (search) {
    search.addEventListener('input', function () {
      query = norm(search.value);
      apply();
    });
  }

  function bindSelect(el, setter) {
    if (!el) return;
    el.addEventListener('change', function () {
      setter(norm(el.value));
      apply();
    });
  }
  bindSelect(gameSelect, function (v) { game = v; });
  bindSelect(unitSelect, function (v) { unit = v; });
  bindSelect(typeSelect, function (v) { type = v; });
  bindSelect(langSelect, function (v) { lang = v; });

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      filter = chip.getAttribute('data-cr-filter') || 'all';
      chips.forEach(function (c) {
        var on = c === chip;
        c.classList.toggle('is-active', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
    });
  });

  var reset = root.querySelector('[data-cr-reset]');
  if (reset) {
    reset.addEventListener('click', function () {
      filter = 'all';
      query = '';
      game = '';
      unit = '';
      type = '';
      lang = '';
      if (search) search.value = '';
      if (gameSelect) gameSelect.value = '';
      if (unitSelect) unitSelect.value = '';
      if (typeSelect) typeSelect.value = '';
      if (langSelect) langSelect.value = '';
      chips.forEach(function (c) {
        var on = (c.getAttribute('data-cr-filter') || '') === 'all';
        c.classList.toggle('is-active', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
    });
  }
})();
</script>
