<?php
/** @var list<array<string, mixed>> $registryTenants */
$registryTenants = $registryTenants ?? [];
$registryCount = count($registryTenants);

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
      </p>
      <div class="cr-hero__actions">
        <a href="<?= htmlspecialchars(url('join')) ?>" class="cr-btn cr-btn--accent">Rejoindre par code</a>
        <a href="<?= htmlspecialchars(url('communities/create')) ?>" class="cr-btn cr-btn--ghost">Créer une communauté</a>
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
            placeholder="Nom, jeu, thème…"
            autocomplete="off"
            data-cr-search
          >
        </label>
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
        <a href="<?= htmlspecialchars(url('communities/create')) ?>" class="cr-btn cr-btn--ink" style="margin-top: 1.5rem;">
          Créer une communauté
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
          $registryTagLabels = is_array($t['registry_tag_labels'] ?? null) ? $t['registry_tag_labels'] : [];
          $gameLabel = trim((string) ($t['game_label'] ?? ''));
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
          ]))));
          $delayClass = 'cr-rise cr-rise-d' . min(3, 1 + ($i % 3));
          ?>
        <li
          class="cr-card <?= $delayClass ?>"
          data-cr-card
          data-cr-locked="<?= $locked ? '1' : '0' ?>"
          data-cr-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
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
                <p class="cr-meta__label"><?= $code !== '' ? 'Code d’accès' : 'Lien' ?></p>
                <p class="cr-meta__value"><?= $code !== '' ? htmlspecialchars($code) : 'Fiche publique' ?></p>
              </div>
            </div>

            <?php if ($code !== ''): ?>
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
        <p class="cr-empty__text">Essayez un autre mot-clé ou réinitialisez les filtres de recrutement.</p>
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
  var chips = Array.prototype.slice.call(root.querySelectorAll('[data-cr-filter]'));
  var empty = root.querySelector('[data-cr-empty]');
  var countLabel = root.querySelector('[data-cr-count-label]');
  var filter = 'all';
  var query = '';

  function labelFor(n) {
    if (n === 0) return 'Aucune communauté correspondante';
    if (n === 1) return '1 communauté visible';
    return n + ' communautés visibles';
  }

  function apply() {
    var visible = 0;
    cards.forEach(function (card) {
      var locked = card.getAttribute('data-cr-locked') === '1';
      var blob = card.getAttribute('data-cr-search') || '';
      var okFilter = filter === 'all' || (filter === 'open' && !locked) || (filter === 'closed' && locked);
      var okSearch = !query || blob.indexOf(query) !== -1;
      var show = okFilter && okSearch;
      card.classList.toggle('is-hidden', !show);
      if (show) visible += 1;
    });
    if (countLabel) countLabel.textContent = labelFor(visible);
    if (empty) empty.hidden = visible !== 0;
  }

  if (search) {
    search.addEventListener('input', function () {
      query = (search.value || '').trim().toLowerCase();
      apply();
    });
  }

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
      if (search) search.value = '';
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
