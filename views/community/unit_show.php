<?php
declare(strict_types=1);

/**
 * Fiche publique d’une unité — même langage visuel que la vitrine communauté (cl-vitrine).
 *
 * @var array<string,mixed> $tenant
 * @var array<string,mixed> $unit
 * @var int $unitMemberCount
 * @var list<array{user_id:int,label:string}> $unitRoster
 * @var string|null $unitCommanderName
 * @var list<array<string,mixed>> $unitChildren
 * @var array<int,int> $unitChildrenCounts
 * @var array<string,mixed>|null $unitParent
 * @var array<string,mixed>|null $tenantBranding
 * @var bool $unitIsPreview
 */

$slug = (string) ($tenant['slug'] ?? '');
$tenantName = (string) ($tenant['name'] ?? 'Communauté');
$unit = is_array($unit ?? null) ? $unit : [];
$unitName = trim((string) ($unit['name'] ?? 'Unité'));
$unitType = trim((string) ($unit['type'] ?? ''));
$unitCode = trim((string) ($unit['code'] ?? ''));
$blurb = trim((string) ($unit['public_blurb'] ?? ''));
$orbatDetails = trim((string) ($unit['orbat_details'] ?? ''));
$orbatImage = trim((string) ($unit['orbat_image_path'] ?? ''));
$memberCount = (int) ($unitMemberCount ?? 0);
$roster = is_array($unitRoster ?? null) ? $unitRoster : [];
$commanderName = trim((string) ($unitCommanderName ?? ''));
$children = is_array($unitChildren ?? null) ? $unitChildren : [];
$childrenCounts = is_array($unitChildrenCounts ?? null) ? $unitChildrenCounts : [];
$parentUnit = is_array($unitParent ?? null) ? $unitParent : null;
$isPreview = !empty($unitIsPreview);

$tenantBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];
$brandLogo = trim((string) ($tenantBranding['logo_url'] ?? ''));
$brandBanner = trim((string) ($tenantBranding['banner_url'] ?? ''));
$brandPrimary = trim((string) ($tenantBranding['primary_color'] ?? ''));
$brandAccent = trim((string) ($tenantBranding['accent_color'] ?? ''));
if ($brandLogo !== '' && !preg_match('#^(https?:)?//#i', $brandLogo) && !str_starts_with($brandLogo, '/')) {
    $brandLogo = asset_url(ltrim($brandLogo, '/'));
} elseif ($brandLogo !== '' && str_starts_with($brandLogo, '/')) {
    $brandLogo = asset_url(ltrim($brandLogo, '/'));
}
if ($brandBanner !== '' && !preg_match('#^(https?:)?//#i', $brandBanner) && !str_starts_with($brandBanner, '/')) {
    $brandBanner = asset_url(ltrim($brandBanner, '/'));
} elseif ($brandBanner !== '' && str_starts_with($brandBanner, '/')) {
    $brandBanner = asset_url(ltrim($brandBanner, '/'));
}
if ($orbatImage !== '' && !preg_match('#^(https?:)?//#i', $orbatImage) && !str_starts_with($orbatImage, '/')) {
    $orbatImage = asset_url(ltrim($orbatImage, '/'));
} elseif ($orbatImage !== '' && str_starts_with($orbatImage, '/')) {
    $orbatImage = asset_url(ltrim($orbatImage, '/'));
}

$tags = [];
$rawTags = $unit['public_tags'] ?? null;
if (is_string($rawTags) && $rawTags !== '') {
    $decoded = json_decode($rawTags, true);
    $tags = is_array($decoded) ? $decoded : [];
} elseif (is_array($rawTags)) {
    $tags = $rawTags;
}

$totalSubMembers = 0;
foreach ($childrenCounts as $cc) {
    $totalSubMembers += (int) $cc;
}

$capacity = isset($unit['public_capacity']) && $unit['public_capacity'] !== null && $unit['public_capacity'] !== ''
    ? (int) $unit['public_capacity']
    : null;
$openSlotsRaw = $unit['public_open_slots'] ?? null;
$openSlotsLabel = null;
$slotsTone = 'ok';
if ($openSlotsRaw !== null && $openSlotsRaw !== '') {
    $os = (int) $openSlotsRaw;
    if ($os === -1) {
        $openSlotsLabel = 'Ouvert';
        $slotsTone = 'info';
    } elseif ($os === 0) {
        $openSlotsLabel = 'Complet';
        $slotsTone = 'warn';
    } else {
        $openSlotsLabel = $os . ' place' . ($os > 1 ? 's' : '');
        $slotsTone = $os <= 2 ? 'warn' : 'ok';
    }
}
$strengthLabel = $capacity !== null && $capacity > 0
    ? $memberCount . ' / ' . $capacity
    : (string) $memberCount;

$formatPublicDate = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        return '';
    }
    $months = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
    $day = (int) $m[3];
    $month = (int) $m[2];
    $year = (int) $m[1];
    if ($month < 1 || $month > 12) {
        return '';
    }

    return $day . ' ' . $months[$month] . ' ' . $year;
};

$foundedOnLabel = $formatPublicDate(isset($unit['public_founded_on']) ? (string) $unit['public_founded_on'] : null);
$customDateLabelText = trim((string) ($unit['public_custom_date_label'] ?? ''));
$customDateValue = $formatPublicDate(isset($unit['public_custom_date']) ? (string) $unit['public_custom_date'] : null);
$showCustomDate = $customDateValue !== '' && $customDateLabelText !== '';

$unitTone = trim((string) ($unit['public_accent_color'] ?? ''));
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $unitTone)) {
    $unitTone = '';
}

$clAccentOn = '#04231a';
$clAccentBg = $brandAccent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandAccent) ? $brandAccent : '';
if ($unitTone !== '') {
    $clAccentBg = $unitTone;
}
if ($clAccentBg !== '') {
    $hx = ltrim($clAccentBg, '#');
    $r = hexdec(substr($hx, 0, 2));
    $g = hexdec(substr($hx, 2, 2));
    $b = hexdec(substr($hx, 4, 2));
    $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    if ($luma < 0.45) {
        $clAccentOn = '#ffffff';
    }
    if ($luma < 0.18) {
        $clAccentBg = '';
        $clAccentOn = '#04231a';
    }
}
$clStyle = '';
if ($brandPrimary !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandPrimary)) {
    $clStyle .= '--cl-tenant-primary:' . $brandPrimary . ';';
}
if ($clAccentBg !== '') {
    $clStyle .= '--cl-tenant-accent:' . $clAccentBg . ';';
}
$clStyle .= '--cl-on-accent:' . $clAccentOn . ';';

$nameInitials = '';
foreach (preg_split('/\s+/u', $tenantName) ?: [] as $part) {
    $part = trim((string) $part);
    if ($part === '') {
        continue;
    }
    $nameInitials .= mb_strtoupper(mb_substr($part, 0, 1));
    if (mb_strlen($nameInitials) >= 2) {
        break;
    }
}
if ($nameInitials === '') {
    $nameInitials = 'C';
}

$backHref = url('c/' . rawurlencode($slug));
$contactHref = $backHref . '#actions-contact';
$enlistHref = url('c/' . rawurlencode($slug) . '/enlistment');
$typeLine = $unitType !== '' ? $unitType : 'Unité';
if ($unitCode !== '') {
    $typeLine .= ' · ' . mb_strtoupper($unitCode);
}

$heroFacts = [];
$heroFacts[] = ['v' => $strengthLabel, 'k' => $capacity !== null && $capacity > 0 ? 'Effectif' : 'Membres'];
if ($openSlotsLabel !== null) {
    $heroFacts[] = ['v' => $openSlotsLabel, 'k' => 'Places'];
}
if ($foundedOnLabel !== '') {
    $heroFacts[] = ['v' => $foundedOnLabel, 'k' => 'Création'];
} elseif ($showCustomDate) {
    $heroFacts[] = ['v' => $customDateValue, 'k' => $customDateLabelText];
}
if (count($children) > 0 && count($heroFacts) < 4) {
    $heroFacts[] = ['v' => (string) count($children), 'k' => 'Sous-unités'];
}

$parentName = $parentUnit ? trim((string) ($parentUnit['name'] ?? '')) : '';
$parentSlug = $parentUnit ? trim((string) ($parentUnit['slug'] ?? '')) : '';
$parentPublic = $parentUnit && !empty($parentUnit['show_on_public_page']);
$parentHref = ($parentPublic && $parentSlug !== '')
    ? url('c/' . rawurlencode($slug) . '/unite/' . rawurlencode($parentSlug))
    : null;

$showAbout = $blurb !== '' || $tags !== [] || $commanderName !== '' || $parentName !== '';
$showDates = $foundedOnLabel !== '' || $showCustomDate;
$showDetails = $orbatDetails !== '';
$showChildren = $children !== [];
?>
<div class="community-public-vitrine community-landing cl-vitrine cl-unit-fiche"<?= $clStyle !== '' ? ' style="' . htmlspecialchars($clStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

  <?php if ($isPreview): ?>
  <div class="cl-unit-fiche__preview" role="status">
    Aperçu réservé au staff — cette unité n’est pas encore visible du public
  </div>
  <?php endif; ?>

  <header class="cl-nav" role="banner">
    <div class="cl-nav__start">
      <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="cl-back" aria-label="Retour à la communauté">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
        <span>Communauté</span>
      </a>
      <div class="cl-nav__brand">
        <?php if ($brandLogo !== ''): ?>
        <img class="cl-nav__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" data-img-fallback="logo" data-img-label="Emblème indisponible">
        <?php else: ?>
        <span class="cl-nav__mark" aria-hidden="true"><?= htmlspecialchars($nameInitials, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <div class="cl-nav__titles">
          <div class="cl-nav__name"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="cl-nav__sub"><?= htmlspecialchars(mb_strtoupper($typeLine), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
    </div>
    <nav class="cl-nav__links" aria-label="Sections de la fiche">
      <?php if ($showAbout): ?><a href="#presentation">Présentation</a><?php endif; ?>
      <?php if ($showChildren): ?><a href="#structure">Structure</a><?php endif; ?>
      <a href="#effectifs">Effectifs</a>
      <a href="#rejoindre">Rejoindre</a>
    </nav>
    <a href="<?= htmlspecialchars($contactHref, ENT_QUOTES, 'UTF-8') ?>" class="cl-btn cl-btn--accent cl-nav__cta">Contacter</a>
  </header>

  <section class="cl-hero cl-rise" aria-labelledby="cl-unit-hero-title">
    <div class="cl-hero__media" aria-hidden="true">
      <?php if ($orbatImage !== ''): ?>
      <img src="<?= htmlspecialchars($orbatImage, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <?php elseif ($brandBanner !== ''): ?>
      <img src="<?= htmlspecialchars($brandBanner, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <?php else: ?>
      <div class="cl-hero__fallback"></div>
      <?php endif; ?>
    </div>
    <div class="cl-hero__scrim" aria-hidden="true"></div>
    <div class="cl-hero__inner">
      <div class="cl-hero__copy">
        <div class="cl-pill cl-pill--muted"><?= htmlspecialchars(mb_strtoupper($typeLine), ENT_QUOTES, 'UTF-8') ?></div>
        <h1 id="cl-unit-hero-title" class="cl-hero__title"><?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="cl-hero__lead">
          Unité de <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>
          <?php if ($commanderName !== ''): ?>
          · Chef d’unité : <?= htmlspecialchars($commanderName, ENT_QUOTES, 'UTF-8') ?>
          <?php endif; ?>
        </p>
        <div class="cl-hero__actions">
          <a href="<?= htmlspecialchars($contactHref, ENT_QUOTES, 'UTF-8') ?>" class="cl-btn cl-btn--accent">Contacter la communauté</a>
          <a href="#effectifs" class="cl-btn cl-btn--ghost">Voir les effectifs</a>
        </div>
      </div>
    </div>
    <?php if ($heroFacts !== []): ?>
    <div class="cl-hero__facts">
      <div class="cl-hero__facts-grid">
        <?php foreach ($heroFacts as $f): ?>
        <div class="cl-hero__fact">
          <div class="cl-hero__fact-v"><?= htmlspecialchars($f['v'], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="cl-hero__fact-k"><?= htmlspecialchars(mb_strtoupper($f['k']), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <div class="cl-wrap cl-unit-fiche__body">

    <?php if ($showAbout): ?>
    <section id="presentation" class="cl-rise cl-unit-fiche__section" aria-labelledby="cl-unit-about-title">
      <p class="cl-kicker">Présentation</p>
      <h2 id="cl-unit-about-title" class="cl-h2">À propos de <?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></h2>
      <?php if ($blurb !== ''): ?>
      <p class="cl-prose"><?= nl2br(htmlspecialchars($blurb, ENT_QUOTES, 'UTF-8')) ?></p>
      <?php else: ?>
      <p class="cl-prose">Cette unité fait partie de <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>.</p>
      <?php endif; ?>

      <?php if ($tags !== []): ?>
      <div class="cl-unit-fiche__tags" aria-label="Mots-clés">
        <?php foreach ($tags as $tg): ?>
          <?php if (is_string($tg) && $tg !== ''): ?>
          <span class="cl-unit-fiche__tag"><?= htmlspecialchars($tg, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="cl-unit-fiche__lead">
        <div class="cl-unit-fiche__avatar" aria-hidden="true"><?= htmlspecialchars($commanderName !== '' ? mb_strtoupper(mb_substr($commanderName, 0, 1)) : '?', ENT_QUOTES, 'UTF-8') ?></div>
        <div>
          <p class="cl-unit-fiche__lead-k">Chef d’unité</p>
          <p class="cl-unit-fiche__lead-v"><?= htmlspecialchars($commanderName !== '' ? $commanderName : 'Non désigné', ENT_QUOTES, 'UTF-8') ?></p>
          <?php if ($parentName !== ''): ?>
          <p class="cl-unit-fiche__lead-sub">
            Rattachée à
            <?php if ($parentHref !== null): ?>
            <a href="<?= htmlspecialchars($parentHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
            <?= htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </p>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($showDates): ?>
    <section id="dates" class="cl-rise cl-unit-fiche__section" aria-labelledby="cl-unit-dates-title">
      <p class="cl-kicker">Repères temporels</p>
      <h2 id="cl-unit-dates-title" class="cl-h2">Dates de l’unité</h2>
      <dl class="cl-unit-fiche__dates">
        <?php if ($foundedOnLabel !== ''): ?>
        <div class="cl-unit-fiche__date">
          <dt>Date de création</dt>
          <dd><?= htmlspecialchars($foundedOnLabel, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($showCustomDate): ?>
        <div class="cl-unit-fiche__date">
          <dt><?= htmlspecialchars($customDateLabelText, ENT_QUOTES, 'UTF-8') ?></dt>
          <dd><?= htmlspecialchars($customDateValue, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <?php endif; ?>
      </dl>
    </section>
    <?php endif; ?>

    <?php if ($showDetails): ?>
    <section id="reperes" class="cl-rise cl-unit-fiche__section" aria-labelledby="cl-unit-details-title">
      <p class="cl-kicker">Contexte</p>
      <h2 id="cl-unit-details-title" class="cl-h2">Repères complémentaires</h2>
      <p class="cl-prose"><?= nl2br(htmlspecialchars($orbatDetails, ENT_QUOTES, 'UTF-8')) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($showChildren): ?>
    <section id="structure" class="cl-rise cl-unit-fiche__section" aria-labelledby="cl-unit-struct-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker">Structure</p>
          <h2 id="cl-unit-struct-title" class="cl-h2"><?= count($children) === 1 ? 'Sous-unité' : (count($children) . ' sous-unités') ?></h2>
        </div>
        <p class="cl-section-aside"><?= $totalSubMembers ?> membre<?= $totalSubMembers > 1 ? 's' : '' ?> dans ces sous-unités</p>
      </div>
      <div class="cl-units">
        <?php foreach ($children as $child): ?>
          <?php
          $csId = (int) ($child['id'] ?? 0);
          $csSlug = trim((string) ($child['slug'] ?? ''));
          $csCount = (int) ($childrenCounts[$csId] ?? 0);
          $csPublic = !empty($child['show_on_public_page']);
          $csHref = $csPublic && $csSlug !== '' ? url('c/' . rawurlencode($slug) . '/unite/' . rawurlencode($csSlug)) : null;
          $csTone = trim((string) ($child['public_accent_color'] ?? ''));
          if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $csTone)) {
              $csTone = $clAccentBg !== '' ? $clAccentBg : '#0b8a5c';
          }
          $csCode = trim((string) ($child['code'] ?? ''));
          $csType = trim((string) ($child['type'] ?? 'Unité'));
          ?>
        <article class="cl-unit" style="--cl-unit-tone:<?= htmlspecialchars($csTone, ENT_QUOTES, 'UTF-8') ?>">
          <div class="cl-unit__code"><?= htmlspecialchars(mb_strtoupper($csCode !== '' ? $csCode : $csType), ENT_QUOTES, 'UTF-8') ?></div>
          <h3 class="cl-unit__name"><?= htmlspecialchars((string) ($child['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
          <div class="cl-unit__meta">
            <span>Effectif</span>
            <span class="cl-mono"><?= $csCount ?> membre<?= $csCount > 1 ? 's' : '' ?></span>
          </div>
          <?php if ($csHref !== null): ?>
          <a class="cl-unit__link" href="<?= htmlspecialchars($csHref, ENT_QUOTES, 'UTF-8') ?>">Voir la fiche →</a>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section id="effectifs" class="cl-rise cl-unit-fiche__section" aria-labelledby="cl-unit-roster-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker">Effectifs</p>
          <h2 id="cl-unit-roster-title" class="cl-h2">Membres rattachés</h2>
        </div>
        <p class="cl-section-aside"><?= $memberCount ?> personne<?= $memberCount > 1 ? 's' : '' ?> listée<?= $memberCount > 1 ? 's' : '' ?></p>
      </div>
      <?php if ($roster === []): ?>
      <p class="cl-prose">Aucun membre n’est listé pour l’instant.</p>
      <?php else: ?>
      <div class="cl-table-wrap">
        <table class="cl-table">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Membre</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roster as $i => $r): ?>
            <tr>
              <td class="cl-mono"><?= (int) $i + 1 ?></td>
              <td><?= htmlspecialchars((string) ($r['label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <section id="rejoindre" class="cl-rise cl-cta" aria-labelledby="cl-unit-cta-title">
      <div class="cl-cta__copy">
        <p class="cl-kicker cl-kicker--on-dark">Rejoindre</p>
        <h2 id="cl-unit-cta-title" class="cl-h2 cl-h2--on-dark">Intéressé par cette unité ?</h2>
        <p class="cl-cta__lead">Le contact et la candidature passent par <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>, qui orientera votre demande vers <?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?>.</p>
      </div>
      <div class="cl-cta__actions">
        <a href="<?= htmlspecialchars($contactHref, ENT_QUOTES, 'UTF-8') ?>" class="cl-btn cl-btn--accent">Contacter la communauté</a>
        <a href="<?= htmlspecialchars($enlistHref, ENT_QUOTES, 'UTF-8') ?>" class="cl-btn cl-btn--ghost">Candidater</a>
      </div>
    </section>

  </div>
</div>
