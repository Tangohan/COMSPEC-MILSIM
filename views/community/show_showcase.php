<?php
/** @var array $tenant */
/** @var array $memberships */
/** @var array $communityConfig */
/** @var array<string, mixed>|null $communityProfile */
/** @var array<string, mixed>|null $showcaseVm */
/** @var list<array<string,mixed>> $publicUnits */
/** @var list<array<string,mixed>> $publicRosterRows */
/** @var array<int,int> $unitMemberCounts */
/** @var array<int,string> $commanderNames */
/** @var bool $hasMembershipInTenant */
/** @var bool $showForumCta */
$slug = $tenant['slug'] ?? '';
$name = $tenant['name'] ?? '';
$cp = $communityProfile ?? \App\Services\Community\TenantCommunityProfileService::getPublicViewModel($communityConfig ?? [], (string) ($tenant['slug'] ?? ''));
$sv = is_array($showcaseVm ?? null) ? $showcaseVm : [];
$publicUnits = is_array($publicUnits ?? null) ? $publicUnits : [];
$publicRosterRows = is_array($publicRosterRows ?? null) ? $publicRosterRows : [];
$unitMemberCounts = is_array($unitMemberCounts ?? null) ? $unitMemberCounts : [];
$commanderNames = is_array($commanderNames ?? null) ? $commanderNames : [];
$hasMembershipInTenant = $hasMembershipInTenant ?? false;
$showForumCta = $showForumCta ?? true;
$communityCode = trim((string) ($tenant['community_code'] ?? ''));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$recruitmentPublishedOpenings = is_array($recruitmentPublishedOpenings ?? null) ? $recruitmentPublishedOpenings : [];
$publicUpcomingEvents = is_array($publicUpcomingEvents ?? null) ? $publicUpcomingEvents : [];
$userId = (int) (\App\Core\Session::get('user_id') ?? 0);
$isLocked = !empty($cp['isLocked']);
$publicAudience = ($cp['publicAudience'] ?? 'unit') === 'platform' ? 'platform' : 'unit';
$discordUrl = (string) ($cp['discordUrl'] ?? '');
$contactEmail = (string) ($cp['contactEmail'] ?? '');
$contactFormEnabled = !empty($cp['contactFormEnabled']);
$hasContactCta = $discordUrl !== '' || $contactEmail !== '' || $contactFormEnabled;
$primaryCta = null;
if (!$isLocked) {
    $primaryCta = $publicAudience === 'platform' ? 'candidater' : 'rejoindre';
} elseif ($hasContactCta) {
    $primaryCta = 'contacter';
}
$styleBadgeLabels = is_array($cp['styleBadgeLabels'] ?? null) ? $cp['styleBadgeLabels'] : [];
$stats = is_array($sv['stats'] ?? null) ? $sv['stats'] : [];
$regionBadges = is_array($sv['regionBadges'] ?? null) ? $sv['regionBadges'] : [];
$specialties = is_array($sv['specialties'] ?? null) ? $sv['specialties'] : [];
$militarySections = is_array($cp['militarySections'] ?? null) ? $cp['militarySections'] : [];
$mods = is_array($sv['publicModules'] ?? null) ? $sv['publicModules'] : [];

$heroHeadline = trim((string) ($sv['heroHeadline'] ?? ''));
if ($heroHeadline === '') {
    $heroHeadline = trim((string) ($sv['heroSubtitle'] ?? ''));
}
$heroLead = trim((string) ($sv['heroSubtitle'] ?? ''));
if ($heroLead === '' || $heroLead === $heroHeadline) {
    $heroLead = trim((string) ($sv['publicMission'] ?? ''));
}
if ($heroLead === '') {
    $heroLead = trim((string) ($cp['simpleBody'] ?? ''));
}
if ($heroLead === '') {
    $heroLead = trim((string) ($cp['welcomeText'] ?? ''));
}
if ($heroHeadline === '') {
    $heroHeadline = $name;
}

$aboutTitle = trim((string) ($sv['aboutTitle'] ?? ''));
if ($aboutTitle === '') {
    $aboutTitle = trim((string) ($sv['publicDoctrine'] ?? ''));
}
if ($aboutTitle === '') {
    $aboutTitle = 'Qui nous sommes';
}
$aboutBody = trim((string) ($sv['aboutBody'] ?? ''));
if ($aboutBody === '') {
    $aboutBody = trim((string) ($sv['publicMission'] ?? ''));
}
if ($aboutBody === '') {
    $aboutBody = trim((string) ($cp['simpleBody'] ?? ''));
}
$aboutBodySecondary = trim((string) ($sv['aboutBodySecondary'] ?? ''));
$sectionsTitle = trim((string) ($sv['sectionsTitle'] ?? ''));
$sectionsLead = trim((string) ($sv['sectionsLead'] ?? ''));
$expectations = trim((string) ($cp['expectations'] ?? ''));
$mainMods = trim((string) ($cp['mainMods'] ?? ''));
$modpackSize = $cp['modpackSize'] ?? null;
$accessLabel = trim((string) ($sv['publicAccessLabel'] ?? ''));
$recruitSessionLabel = trim((string) ($sv['recruitmentSessionLabel'] ?? ''));
$foundedYearSetting = trim((string) ($sv['foundedYear'] ?? ''));

$pitchPoints = [];
foreach (is_array($sv['pitch'] ?? null) ? $sv['pitch'] : [] as $pp) {
    if (!is_array($pp)) {
        continue;
    }
    $pt = trim((string) ($pp['title'] ?? ''));
    $pb = trim((string) ($pp['body'] ?? ''));
    if ($pt === '' && $pb === '') {
        continue;
    }
    $pitchPoints[] = ['t' => $pt, 'b' => $pb];
}
if ($pitchPoints === []) {
    foreach ($militarySections as $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $pt = trim((string) ($sec['title'] ?? ''));
        $pb = trim((string) ($sec['body'] ?? ''));
        if ($pt === '' && $pb === '') {
            continue;
        }
        $pitchPoints[] = ['t' => $pt !== '' ? $pt : (string) ($sec['label'] ?? ''), 'b' => $pb];
    }
}
if ($pitchPoints === []) {
    foreach ($specialties as $sp) {
        if (is_string($sp) && trim($sp) !== '') {
            $pitchPoints[] = ['t' => trim($sp), 'b' => ''];
        }
    }
}

$prereqItems = [];
foreach (is_array($sv['prerequisites'] ?? null) ? $sv['prerequisites'] : [] as $pr) {
    if (!is_array($pr)) {
        continue;
    }
    $status = (string) ($pr['status'] ?? 'required');
    $prereqItems[] = [
        't' => (string) ($pr['label'] ?? ''),
        'b' => (string) ($pr['detail'] ?? ''),
        'ok' => $status === 'required' || $status === 'optional',
        'statusLabel' => (string) ($pr['statusLabel'] ?? ''),
    ];
}
if ($prereqItems === [] && $expectations !== '') {
    foreach (preg_split('/\R+/', $expectations) ?: [] as $line) {
        $line = trim((string) $line);
        $line = preg_replace('/^[\-\*\x{2022}]\s*/u', '', $line) ?? $line;
        if ($line !== '') {
            $prereqItems[] = ['t' => $line, 'b' => '', 'ok' => true, 'statusLabel' => ''];
        }
    }
}
if ($prereqItems === []) {
    if ($mainMods !== '') {
        $prereqItems[] = ['t' => 'Mods principaux', 'b' => $mainMods, 'ok' => true, 'statusLabel' => ''];
    }
    if ($modpackSize !== null && (string) $modpackSize !== '') {
        $prereqItems[] = ['t' => 'Taille du modpack', 'b' => (string) $modpackSize . ' Go environ', 'ok' => true, 'statusLabel' => ''];
    }
    if ($discordUrl !== '') {
        $prereqItems[] = ['t' => 'Discord', 'b' => 'Canal de communication de la communauté.', 'ok' => true, 'statusLabel' => ''];
    }
}

$recruitSteps = [];
foreach (is_array($sv['processSteps'] ?? null) ? $sv['processSteps'] : [] as $st) {
    if (!is_array($st)) {
        continue;
    }
    $recruitSteps[] = [
        'n' => (string) ($st['n'] ?? (string) (count($recruitSteps) + 1)),
        't' => (string) ($st['title'] ?? ''),
        'delay' => (string) ($st['delay'] ?? ''),
        'b' => (string) ($st['body'] ?? ''),
        'accent' => !empty($st['highlight']),
    ];
}
if ($recruitSteps === []) {
    $recruitSteps = [
        ['n' => '1', 't' => 'Candidature en ligne', 'delay' => 'quelques minutes', 'b' => 'Un formulaire court pour présenter votre disponibilité, votre expérience et votre motivation.', 'accent' => false],
        ['n' => '2', 't' => 'Examen du dossier', 'delay' => 'sous quelques jours', 'b' => 'L’équipe recrutement vérifie les prérequis et vous répond, favorablement ou non.', 'accent' => false],
        ['n' => '3', 't' => 'Entretien', 'delay' => 'échange vocal', 'b' => 'Une discussion pour aligner les attentes et répondre à vos questions.', 'accent' => false],
        ['n' => '4', 't' => 'Intégration', 'delay' => 'période d’essai', 'b' => 'Accueil, procédures de base et premiers terrains encadrés.', 'accent' => true],
        ['n' => '5', 't' => 'Affectation', 'delay' => 'selon les places', 'b' => 'Vous rejoignez une unité selon vos affinités et les disponibilités.', 'accent' => true],
    ];
}

$faqItems = is_array($sv['faq'] ?? null) ? $sv['faq'] : [];
$partnerItems = is_array($sv['partners'] ?? null) ? $sv['partners'] : [];
$testimonialItems = is_array($sv['testimonials'] ?? null) ? $sv['testimonials'] : [];
$ctaKicker = trim((string) ($sv['ctaKicker'] ?? ''));
$ctaTitle = trim((string) ($sv['ctaTitle'] ?? ''));
$ctaBody = trim((string) ($sv['ctaBody'] ?? ''));
$videoUrlSetting = trim((string) ($sv['videoUrl'] ?? ''));
$videoTitleSetting = trim((string) ($sv['videoTitle'] ?? ''));
$videoBodySetting = trim((string) ($sv['videoBody'] ?? ''));
$videoChapters = is_array($sv['videoChapters'] ?? null) ? $sv['videoChapters'] : [];
$configuredHeroFacts = is_array($sv['heroFacts'] ?? null) ? $sv['heroFacts'] : [];
$tenantBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];
$publicMediaItems = is_array($publicMediaItems ?? null) ? $publicMediaItems : [];
$publicMediaCollections = is_array($publicMediaCollections ?? null) ? $publicMediaCollections : [];
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

$heroMediaItem = null;
$presentationVideo = null;
$galleryImages = [];
foreach ($publicMediaItems as $pmi) {
    $mk = (string) ($pmi['media_kind'] ?? '');
    if ($presentationVideo === null && ($mk === 'long_video' || $mk === 'short_video')) {
        $presentationVideo = $pmi;
    }
    if ($mk === 'image') {
        $galleryImages[] = $pmi;
    }
    if (!empty($pmi['is_hero']) && $heroMediaItem === null) {
        $heroMediaItem = $pmi;
    }
}
if ($heroMediaItem === null && $publicMediaItems !== []) {
    $first = $publicMediaItems[0];
    if (($first['media_kind'] ?? '') === 'image' || ($first['media_kind'] ?? '') === 'short_video') {
        $heroMediaItem = $first;
    }
}

$nameInitials = '';
foreach (preg_split('/\s+/u', $name) ?: [] as $part) {
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

$foundedLabel = '';
if ($foundedYearSetting !== '') {
    $foundedLabel = 'Fondée en ' . $foundedYearSetting;
} else {
    $createdRaw = trim((string) ($tenant['created_at'] ?? ''));
    if ($createdRaw !== '') {
        $cts = strtotime($createdRaw);
        if ($cts !== false) {
            $foundedLabel = 'Fondée en ' . date('Y', $cts);
        }
    }
}
$badgeLine = [];
foreach ($styleBadgeLabels as $bl) {
    if (is_string($bl) && $bl !== '') {
        $badgeLine[] = $bl;
    }
}
foreach ($regionBadges as $rb) {
    if (is_string($rb) && $rb !== '') {
        $badgeLine[] = $rb;
    }
}
$navSub = $badgeLine !== [] ? implode(' · ', array_slice($badgeLine, 0, 2)) : ($publicAudience === 'platform' ? 'Portail plateforme' : 'Communauté milsim');
if ($foundedLabel !== '') {
    $navSub .= ' · ' . mb_strtoupper($foundedLabel);
}

$enlistUrl = url('c/' . $slug . '/enlistment');
$forumUrl = url('c/' . $slug . '/forum');
$mediasUrl = url('c/' . rawurlencode((string) $slug) . '/medias');

$ctaPrimaryLabel = match ($primaryCta) {
    'rejoindre' => 'Déposer une candidature',
    'candidater' => 'Candidater',
    'contacter' => 'Contacter',
    default => null,
};
$ctaPrimaryHref = match ($primaryCta) {
    'rejoindre', 'candidater' => $enlistUrl,
    'contacter' => '#contact',
    default => null,
};
$navCtaLabel = match ($primaryCta) {
    'rejoindre', 'candidater' => 'Candidater',
    'contacter' => 'Contacter',
    default => null,
};

$heroFacts = [];
if ($configuredHeroFacts !== []) {
    foreach ($configuredHeroFacts as $hf) {
        if (!is_array($hf)) {
            continue;
        }
        $hv = trim((string) ($hf['v'] ?? ''));
        $hk = trim((string) ($hf['k'] ?? ''));
        if ($hv === '' && $hk === '') {
            continue;
        }
        $heroFacts[] = ['v' => $hv, 'k' => $hk !== '' ? $hk : '—'];
    }
}
if ($heroFacts === []) {
    if (trim((string) ($stats['effectif'] ?? '')) !== '') {
        $heroFacts[] = ['v' => (string) $stats['effectif'], 'k' => 'Membres actifs'];
    }
    if (trim((string) ($stats['unites'] ?? '')) !== '') {
        $heroFacts[] = ['v' => (string) $stats['unites'], 'k' => 'Unités publiques'];
    }
    if (trim((string) ($stats['activite'] ?? '')) !== '') {
        $heroFacts[] = ['v' => (string) $stats['activite'], 'k' => 'Présence (30 j)'];
    }
    if (trim((string) ($stats['theatre'] ?? '')) !== '') {
        $heroFacts[] = ['v' => (string) $stats['theatre'], 'k' => 'Théâtre principal'];
    }
    if ($recruitmentPublishedOpenings !== []) {
        $heroFacts[] = ['v' => (string) count($recruitmentPublishedOpenings), 'k' => 'Offres ouvertes'];
    }
}

$statCards = [];
if (trim((string) ($stats['effectif'] ?? '')) !== '') {
    $statCards[] = ['label' => 'Effectif', 'value' => (string) $stats['effectif'], 'note' => 'Membres actifs', 'pct' => 82];
}
if (trim((string) ($stats['activite'] ?? '')) !== '') {
    $actNum = (int) preg_replace('/\D+/', '', (string) $stats['activite']);
    $statCards[] = ['label' => 'Présence moyenne', 'value' => (string) $stats['activite'], 'note' => 'Sur 30 jours', 'pct' => max(8, min(100, $actNum ?: 60))];
}
if (trim((string) ($stats['unites'] ?? '')) !== '') {
    $statCards[] = ['label' => 'Unités', 'value' => (string) $stats['unites'], 'note' => 'Visibles sur la vitrine', 'pct' => 70];
}
if ($recruitmentPublishedOpenings !== []) {
    $statCards[] = ['label' => 'Places / offres', 'value' => (string) count($recruitmentPublishedOpenings), 'note' => 'Recrutement publié', 'pct' => 45];
}
if (trim((string) ($stats['theatre'] ?? '')) !== '') {
    $statCards[] = ['label' => 'Référence', 'value' => (string) $stats['theatre'], 'note' => 'Théâtre / fuseau', 'pct' => 55];
}

$eventTypeLabel = static function (string $et): string {
    return match ($et) {
        'operation' => 'Opération',
        'formation' => 'Formation',
        'autre' => 'Autre',
        default => 'Événement',
    };
};

$rosterIndicatif = static function (array $r): string {
    $cs = trim((string) ($r['callsign'] ?? ''));
    if ($cs !== '') {
        return $cs;
    }
    $fa = trim((string) ($r['forum_alias'] ?? ''));
    if ($fa !== '') {
        return $fa;
    }
    $dn = trim((string) ($r['display_name'] ?? ''));
    return $dn !== '' ? $dn : '—';
};
$rosterStatusLabel = static function (string $st): string {
    return match ($st) {
        'active' => 'Actif',
        'pending' => 'Instruction',
        'inactive' => 'Réserve',
        default => 'Autre',
    };
};

$clStyle = '';
if ($brandPrimary !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandPrimary)) {
    $clStyle .= '--cl-tenant-primary:' . $brandPrimary . ';';
}
if ($brandAccent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandAccent)) {
    $clStyle .= '--cl-tenant-accent:' . $brandAccent . ';';
}

$showAgenda = $publicUpcomingEvents !== [] && !empty($mods['events']);
$showMedia = $publicMediaItems !== [] || $publicMediaCollections !== [];
$showUnits = $publicUnits !== [];
$showRoster = !empty($sv['publicRosterEnabled']);
$showOpenings = $recruitmentPublishedOpenings !== [];
$mediaCount = count($publicMediaItems);
$mediaLikesEnabled = !empty($mediaLikesEnabled);
$mediaViewerCanLike = !empty($mediaViewerCanLike);
$mediaLikeCsrf = \App\Core\Csrf::token();
$mediaLoginUrl = url('login');
?>
<div class="community-public-vitrine community-landing cl-vitrine"<?= $clStyle !== '' ? ' style="' . htmlspecialchars($clStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

  <header class="cl-nav" role="banner">
    <div class="cl-nav__brand">
      <?php if ($brandLogo !== ''): ?>
      <img class="cl-nav__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" data-img-fallback="logo" data-img-label="Emblème indisponible">
      <?php else: ?>
      <span class="cl-nav__mark" aria-hidden="true"><?= htmlspecialchars($nameInitials) ?></span>
      <?php endif; ?>
      <div class="cl-nav__titles">
        <div class="cl-nav__name"><?= htmlspecialchars($name) ?></div>
        <div class="cl-nav__sub"><?= htmlspecialchars(mb_strtoupper($navSub)) ?></div>
      </div>
    </div>
    <nav class="cl-nav__links" aria-label="Sections de la vitrine">
      <a href="#presentation">L’unité</a>
      <?php if ($showUnits): ?><a href="#organisation">Organisation</a><?php endif; ?>
      <?php if ($showAgenda): ?><a href="#agenda">Agenda</a><?php endif; ?>
      <?php if (!$isLocked || $showOpenings): ?><a href="#recrutement">Recrutement</a><?php endif; ?>
      <?php if ($showMedia): ?><a href="#medias">Médias</a><?php endif; ?>
      <?php if ($showForumCta): ?><a href="<?= htmlspecialchars($forumUrl) ?>">Forum</a><?php endif; ?>
    </nav>
    <?php if ($navCtaLabel !== null && $ctaPrimaryHref !== null): ?>
    <a href="<?= htmlspecialchars($ctaPrimaryHref) ?>" class="cl-btn cl-btn--accent cl-nav__cta comspec-analytics-cta" data-comspec-zone="vitrine_nav" data-comspec-cta="<?= htmlspecialchars((string) $primaryCta) ?>"><?= htmlspecialchars(mb_strtoupper($navCtaLabel)) ?></a>
    <?php endif; ?>
  </header>

  <?php if ($flashSuccess): ?>
  <div class="cl-flash cl-flash--ok"><?= htmlspecialchars($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
  <div class="cl-flash cl-flash--err"><?= htmlspecialchars($flashError) ?></div>
  <?php endif; ?>

  <section class="cl-hero cl-rise" aria-labelledby="cl-hero-title">
    <div class="cl-hero__media" aria-hidden="true">
      <?php
      $heroRendered = false;
      if (is_array($heroMediaItem)) {
          $hk = (string) ($heroMediaItem['media_kind'] ?? '');
          $hPath = \App\Support\CommunityMediaDetails::publicUrl(isset($heroMediaItem['storage_path']) ? (string) $heroMediaItem['storage_path'] : null);
          if ($hk === 'image' && $hPath) {
              echo '<img src="' . htmlspecialchars($hPath, ENT_QUOTES, 'UTF-8') . '" alt="">';
              $heroRendered = true;
          } elseif ($hk === 'short_video' && $hPath) {
              echo '<video src="' . htmlspecialchars($hPath, ENT_QUOTES, 'UTF-8') . '" autoplay muted loop playsinline></video>';
              $heroRendered = true;
          }
      }
      if (!$heroRendered && $brandBanner !== '') {
          echo '<img src="' . htmlspecialchars($brandBanner, ENT_QUOTES, 'UTF-8') . '" alt="">';
          $heroRendered = true;
      }
      if (!$heroRendered) {
          echo '<div class="cl-hero__fallback"></div>';
      }
      ?>
    </div>
    <div class="cl-hero__scrim" aria-hidden="true"></div>
    <div class="cl-hero__inner">
      <div class="cl-hero__copy">
        <?php if ($publicAudience !== 'platform' && !empty($sv['recruitmentBadgeOpen']) && !$isLocked): ?>
        <div class="cl-pill cl-pill--live">
          <span class="cl-pulse" aria-hidden="true"></span>
          <span><?= htmlspecialchars(mb_strtoupper($recruitSessionLabel !== '' ? ('Recrutement ouvert · ' . $recruitSessionLabel) : 'Recrutement ouvert')) ?></span>
        </div>
        <?php elseif ($publicAudience !== 'platform' && $isLocked): ?>
        <div class="cl-pill cl-pill--muted">Recrutement fermé</div>
        <?php elseif ($accessLabel !== ''): ?>
        <div class="cl-pill cl-pill--live"><span class="cl-pulse" aria-hidden="true"></span><span><?= htmlspecialchars(mb_strtoupper($accessLabel)) ?></span></div>
        <?php endif; ?>

        <h1 id="cl-hero-title" class="cl-hero__title"><?= nl2br(htmlspecialchars($heroHeadline)) ?></h1>
        <?php if ($heroLead !== '' && $heroLead !== $heroHeadline): ?>
        <p class="cl-hero__lead"><?= nl2br(htmlspecialchars($heroLead)) ?></p>
        <?php endif; ?>

        <div class="cl-hero__actions">
          <?php if ($ctaPrimaryLabel !== null && $ctaPrimaryHref !== null): ?>
          <a href="<?= htmlspecialchars($ctaPrimaryHref) ?>" class="cl-btn cl-btn--accent comspec-analytics-cta" data-comspec-zone="vitrine_hero" data-comspec-cta="<?= htmlspecialchars((string) $primaryCta) ?>"><?= htmlspecialchars(mb_strtoupper($ctaPrimaryLabel)) ?></a>
          <?php endif; ?>
          <?php if ($discordUrl !== ''): ?>
          <a href="<?= htmlspecialchars($discordUrl) ?>" target="_blank" rel="noopener noreferrer" class="cl-btn cl-btn--ghost">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14c1 1.4 6 1.4 7 0M9.5 10.5v.01M14.5 10.5v.01"/></svg>
            Rejoindre le Discord
          </a>
          <?php elseif ($showMedia): ?>
          <a href="#medias" class="cl-btn cl-btn--ghost">Voir les médias</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php if ($heroFacts !== []): ?>
    <div class="cl-hero__facts">
      <div class="cl-hero__facts-grid">
        <?php foreach ($heroFacts as $f): ?>
        <div class="cl-hero__fact">
          <div class="cl-hero__fact-v"><?= htmlspecialchars($f['v']) ?></div>
          <div class="cl-hero__fact-k"><?= htmlspecialchars(mb_strtoupper($f['k'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <?php
  $settingsVideoEmbed = $videoUrlSetting !== ''
      ? \App\Support\CommunityMediaDetails::embedUrl($videoUrlSetting)
      : null;
  $hasSettingsVideo = $videoUrlSetting !== '' && ($settingsVideoEmbed || preg_match('#\.(mp4|webm|ogg)(\?|$)#i', $videoUrlSetting));
  $showVideoBlock = $hasSettingsVideo || is_array($presentationVideo);
  ?>
  <?php if ($showVideoBlock): ?>
  <?php
    $pvKind = is_array($presentationVideo) ? (string) ($presentationVideo['media_kind'] ?? '') : '';
    $pvUrl = is_array($presentationVideo)
        ? \App\Support\CommunityMediaDetails::publicUrl(isset($presentationVideo['storage_path']) ? (string) $presentationVideo['storage_path'] : null)
        : null;
    $pvEmbed = $settingsVideoEmbed
        ?? (is_array($presentationVideo)
            ? \App\Support\CommunityMediaDetails::embedUrl(isset($presentationVideo['external_url']) ? (string) $presentationVideo['external_url'] : null)
            : null);
    if ($hasSettingsVideo && !$pvEmbed && preg_match('#\.(mp4|webm|ogg)(\?|$)#i', $videoUrlSetting)) {
        $pvUrl = $videoUrlSetting;
    }
    $pvTitle = $videoTitleSetting !== ''
        ? $videoTitleSetting
        : (is_array($presentationVideo) ? trim((string) ($presentationVideo['title'] ?? '')) : '');
    $pvCap = $videoBodySetting !== ''
        ? $videoBodySetting
        : (is_array($presentationVideo) ? trim((string) ($presentationVideo['caption'] ?? '')) : '');
  ?>
  <section class="cl-video cl-rise" aria-labelledby="cl-video-title">
    <div class="cl-wrap cl-video__grid">
      <div class="cl-video__player">
        <?php if ($pvEmbed): ?>
        <iframe src="<?= htmlspecialchars($pvEmbed, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($pvTitle !== '' ? $pvTitle : 'Vidéo de présentation') ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
        <?php elseif ($pvUrl): ?>
        <video src="<?= htmlspecialchars($pvUrl, ENT_QUOTES, 'UTF-8') ?>" controls playsinline preload="metadata"></video>
        <?php else: ?>
        <div class="cl-ph">Vidéo de présentation</div>
        <?php endif; ?>
      </div>
      <div class="cl-video__copy">
        <p class="cl-kicker cl-kicker--on-dark">En images</p>
        <h2 id="cl-video-title" class="cl-h2 cl-h2--on-dark"><?= htmlspecialchars($pvTitle !== '' ? $pvTitle : 'À quoi ressemble une soirée chez nous') ?></h2>
        <?php if ($pvCap !== ''): ?>
        <p class="cl-video__lead"><?= nl2br(htmlspecialchars($pvCap)) ?></p>
        <?php endif; ?>
        <?php if ($videoChapters !== []): ?>
        <div class="cl-video__chapters">
          <?php foreach ($videoChapters as $ch): ?>
            <?php if (!is_array($ch)) { continue; } ?>
          <div class="cl-video__chapter">
            <span class="cl-mono"><?= htmlspecialchars((string) ($ch['time'] ?? '')) ?></span>
            <span><?= htmlspecialchars((string) ($ch['label'] ?? '')) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($statCards !== []): ?>
  <div class="cl-stats">
    <div class="cl-stats__grid">
      <?php foreach ($statCards as $sc): ?>
      <div class="cl-stats__card cl-rise">
        <div class="cl-stats__label"><?= htmlspecialchars(mb_strtoupper($sc['label'])) ?></div>
        <div class="cl-stats__value"><?= htmlspecialchars($sc['value']) ?></div>
        <div class="cl-stats__bar" aria-hidden="true"><span style="width:<?= (int) $sc['pct'] ?>%"></span></div>
        <div class="cl-stats__note"><?= htmlspecialchars($sc['note']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="cl-body">

    <section id="presentation" class="cl-about cl-rise" aria-labelledby="cl-about-title">
      <div class="cl-about__text">
        <p class="cl-kicker">Qui nous sommes</p>
        <h2 id="cl-about-title" class="cl-h2"><?= htmlspecialchars($aboutTitle) ?></h2>
        <?php if ($aboutBody !== ''): ?>
        <p class="cl-prose"><?= nl2br(htmlspecialchars($aboutBody)) ?></p>
        <?php endif; ?>
        <?php if ($aboutBodySecondary !== ''): ?>
        <p class="cl-prose"><?= nl2br(htmlspecialchars($aboutBodySecondary)) ?></p>
        <?php endif; ?>
        <?php if ($expectations !== '' && $prereqItems === []): ?>
        <p class="cl-prose"><?= nl2br(htmlspecialchars($expectations)) ?></p>
        <?php endif; ?>
        <?php if ($pitchPoints !== []): ?>
        <div class="cl-pitch">
          <?php foreach ($pitchPoints as $pp): ?>
          <div class="cl-pitch__item">
            <div class="cl-pitch__t"><?= htmlspecialchars($pp['t']) ?></div>
            <?php if ($pp['b'] !== ''): ?>
            <div class="cl-pitch__b"><?= nl2br(htmlspecialchars($pp['b'])) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="cl-about__gallery">
        <?php
        $gal = array_slice($galleryImages, 0, 3);
        if ($gal === [] && is_array($heroMediaItem) && ($heroMediaItem['media_kind'] ?? '') === 'image') {
            $gal = [$heroMediaItem];
        }
        ?>
        <?php if ($gal !== []): ?>
          <?php foreach ($gal as $gi => $gItem): ?>
            <?php
              $gUrl = \App\Support\CommunityMediaDetails::publicUrl(isset($gItem['storage_path']) ? (string) $gItem['storage_path'] : null);
              $gTitle = trim((string) ($gItem['title'] ?? ''));
            ?>
            <?php if ($gUrl): ?>
            <figure class="cl-about__shot<?= $gi === 0 ? ' cl-about__shot--wide' : '' ?>">
              <img src="<?= htmlspecialchars($gUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($gTitle !== '' ? $gTitle : 'Image de la communauté') ?>" loading="lazy">
            </figure>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="cl-ph cl-about__shot cl-about__shot--wide">Galerie</div>
          <div class="cl-ph cl-about__shot">—</div>
          <div class="cl-ph cl-about__shot">—</div>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($showUnits): ?>
    <section id="organisation" class="cl-rise" aria-labelledby="cl-org-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker">Organisation</p>
          <h2 id="cl-org-title" class="cl-h2"><?= htmlspecialchars($sectionsTitle !== '' ? $sectionsTitle : (count($publicUnits) === 1 ? 'Une unité structurée' : (count($publicUnits) . ' unités, une communauté'))) ?></h2>
        </div>
        <p class="cl-section-aside"><?= htmlspecialchars($sectionsLead !== '' ? $sectionsLead : 'Les places ouvertes sont indiquées par unité.') ?></p>
      </div>
      <div class="cl-units">
        <?php foreach ($publicUnits as $unit): ?>
          <?php
            $uid = (int) ($unit['id'] ?? 0);
            $mc = (int) ($unitMemberCounts[$uid] ?? 0);
            $blurb = trim((string) ($unit['public_blurb'] ?? ''));
            $code = trim((string) ($unit['code'] ?? ''));
            $unitSlugForLink = trim((string) ($unit['slug'] ?? ''));
            $cmdId = (int) ($unit['commander_user_id'] ?? 0);
            $cmdName = $cmdId > 0 ? ($commanderNames[$cmdId] ?? '') : '';
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
                ? $mc . ' / ' . $capacity
                : ($mc . ' membre' . ($mc > 1 ? 's' : ''));
            $tone = trim((string) ($unit['public_accent_color'] ?? ''));
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $tone)) {
                $tone = '#0b8a5c';
                $typeRaw = strtolower((string) ($unit['type'] ?? ''));
                if (str_contains($typeRaw, 'support') || str_contains($typeRaw, 'log')) {
                    $tone = '#c98a12';
                } elseif (str_contains($typeRaw, 'instruct') || str_contains($typeRaw, 'école') || str_contains($typeRaw, 'ecole')) {
                    $tone = '#1e6fbf';
                }
            }
          ?>
        <article class="cl-unit" style="--cl-unit-tone:<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($code !== ''): ?>
          <div class="cl-unit__code"><?= htmlspecialchars(mb_strtoupper($code)) ?></div>
          <?php else: ?>
          <div class="cl-unit__code"><?= htmlspecialchars(mb_strtoupper((string) ($unit['type'] ?? 'Unité'))) ?></div>
          <?php endif; ?>
          <h3 class="cl-unit__name"><?= htmlspecialchars((string) ($unit['name'] ?? '')) ?></h3>
          <?php if ($blurb !== ''): ?>
          <p class="cl-unit__desc"><?= nl2br(htmlspecialchars($blurb)) ?></p>
          <?php elseif ($cmdName !== ''): ?>
          <p class="cl-unit__desc">Chef d’unité : <?= htmlspecialchars($cmdName) ?></p>
          <?php else: ?>
          <p class="cl-unit__desc">Unité visible sur la page publique.</p>
          <?php endif; ?>
          <div class="cl-unit__meta">
            <span>Effectif</span>
            <span class="cl-mono"><?= htmlspecialchars($strengthLabel) ?></span>
          </div>
          <?php if ($openSlotsLabel !== null): ?>
          <div class="cl-unit__meta">
            <span>Places</span>
            <span class="cl-badge cl-badge--<?= htmlspecialchars($slotsTone) ?>"><?= htmlspecialchars($openSlotsLabel) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($unitSlugForLink !== ''): ?>
          <a class="cl-unit__link" href="<?= htmlspecialchars(url('c/' . rawurlencode((string) $slug) . '/unite/' . rawurlencode($unitSlugForLink)), ENT_QUOTES, 'UTF-8') ?>">Voir la fiche →</a>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($showAgenda): ?>
    <section id="agenda" class="cl-rise" aria-labelledby="cl-agenda-title">
      <p class="cl-kicker">Agenda public</p>
      <h2 id="cl-agenda-title" class="cl-h2">Les prochains rendez-vous</h2>
      <div class="cl-table-wrap">
        <table class="cl-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Événement</th>
              <th>Type</th>
              <th>Lieu</th>
              <th class="cl-table__right">État</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($publicUpcomingEvents as $ev): ?>
              <?php
                $startsRaw = isset($ev['starts_at']) ? (string) $ev['starts_at'] : '';
                $startsTs = $startsRaw !== '' ? strtotime($startsRaw) : false;
                $dateLabel = $startsTs !== false ? date('d/m · H:i', $startsTs) : '—';
                $etype = $eventTypeLabel((string) ($ev['event_type'] ?? 'evenement'));
                $loc = trim((string) ($ev['location'] ?? ''));
                $state = 'Planifié';
                $stateTone = 'info';
                if ($startsTs !== false && $startsTs <= time()) {
                    $state = 'En cours';
                    $stateTone = 'ok';
                } elseif ($startsTs !== false && $startsTs <= time() + 7 * 86400) {
                    $state = 'À venir';
                    $stateTone = 'ok';
                }
              ?>
            <tr>
              <td class="cl-mono"><?= htmlspecialchars($dateLabel) ?></td>
              <td class="cl-table__strong"><?= htmlspecialchars((string) ($ev['title'] ?? '—')) ?></td>
              <td><?= htmlspecialchars($etype) ?></td>
              <td><?= htmlspecialchars($loc !== '' ? $loc : '—') ?></td>
              <td class="cl-table__right"><span class="cl-badge cl-badge--<?= htmlspecialchars($stateTone) ?>"><?= htmlspecialchars($state) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <section id="recrutement" class="cl-recruit cl-rise" aria-labelledby="cl-recruit-title">
      <div class="cl-recruit__prereq">
        <p class="cl-kicker">Ce qu’il faut</p>
        <h2 id="cl-recruit-title" class="cl-h2">Prérequis</h2>
        <?php if ($prereqItems !== []): ?>
        <div class="cl-prereq">
          <?php foreach ($prereqItems as $pi): ?>
          <div class="cl-prereq__row">
            <span class="cl-prereq__mark" aria-hidden="true"><?= !empty($pi['ok']) ? '✓' : '—' ?></span>
            <div>
              <div class="cl-prereq__t"><?= htmlspecialchars($pi['t']) ?><?php if (!empty($pi['statusLabel'])): ?> <span class="cl-prereq__status">(<?= htmlspecialchars((string) $pi['statusLabel']) ?>)</span><?php endif; ?></div>
              <?php if ($pi['b'] !== ''): ?>
              <div class="cl-prereq__b"><?= htmlspecialchars($pi['b']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="cl-prose">Les prérequis détaillés sont précisés lors de la candidature. Une présence régulière et un micro correct sont généralement attendus.</p>
        <?php endif; ?>
      </div>
      <div class="cl-recruit__steps">
        <p class="cl-kicker">Comment ça se passe</p>
        <h2 class="cl-h2">De la candidature au terrain</h2>
        <ol class="cl-steps">
          <?php foreach ($recruitSteps as $st): ?>
          <li class="cl-steps__item">
            <div class="cl-steps__rail">
              <span class="cl-steps__n<?= !empty($st['accent']) ? ' cl-steps__n--accent' : '' ?>"><?= htmlspecialchars($st['n']) ?></span>
              <span class="cl-steps__line" aria-hidden="true"></span>
            </div>
            <div class="cl-steps__body">
              <div class="cl-steps__head">
                <span class="cl-steps__t"><?= htmlspecialchars($st['t']) ?></span>
                <span class="cl-steps__delay cl-mono"><?= htmlspecialchars($st['delay']) ?></span>
              </div>
              <p class="cl-steps__b"><?= htmlspecialchars($st['b']) ?></p>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

    <?php if ($showOpenings): ?>
    <section id="carrieres" class="cl-rise" aria-labelledby="cl-news-title">
      <p class="cl-kicker">Actualités recrutement</p>
      <h2 id="cl-news-title" class="cl-h2">Offres publiées</h2>
      <div class="cl-news">
        <?php foreach ($recruitmentPublishedOpenings as $ro): ?>
          <?php
            $pc = \App\Services\Recruitment\RecruitmentOpeningPresentation::personnelCategoryLabel((string) ($ro['personnel_category'] ?? 'other'));
            $sum = trim((string) ($ro['summary'] ?? ''));
            if ($sum === '') {
                $sum = trim(strip_tags((string) ($ro['description'] ?? '')));
                if (mb_strlen($sum) > 180) {
                    $sum = mb_substr($sum, 0, 177) . '…';
                }
            }
            $avisSlug = (string) ($ro['public_page_slug'] ?? '');
            $detailUrl = $avisSlug !== ''
                ? url('c/' . rawurlencode((string) $slug) . '/avis/' . rawurlencode($avisSlug))
                : $enlistUrl;
            $upd = isset($ro['updated_at']) ? strtotime((string) $ro['updated_at']) : false;
            $dateLabel = $upd ? date('d/m/Y', $upd) : '';
          ?>
        <article class="cl-news__card">
          <div class="cl-news__meta">
            <span class="cl-news__cat"><?= htmlspecialchars(mb_strtoupper($pc)) ?></span>
            <?php if ($dateLabel !== ''): ?><span class="cl-mono cl-news__date"><?= htmlspecialchars($dateLabel) ?></span><?php endif; ?>
          </div>
          <h3 class="cl-news__title"><?= htmlspecialchars((string) ($ro['title'] ?? '')) ?></h3>
          <?php if (trim((string) ($ro['unit_name'] ?? '')) !== ''): ?>
          <p class="cl-news__unit"><?= htmlspecialchars((string) $ro['unit_name']) ?></p>
          <?php endif; ?>
          <?php if ($sum !== ''): ?>
          <p class="cl-news__excerpt"><?= nl2br(htmlspecialchars($sum)) ?></p>
          <?php endif; ?>
          <div class="cl-news__actions">
            <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">Lire la suite →</a>
            <?php if (!$isLocked): ?>
            <a class="comspec-analytics-cta" href="<?= htmlspecialchars(url('c/' . rawurlencode((string) $slug) . '/enlistment?ouverture=' . (int) ($ro['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" data-comspec-zone="liste_postes" data-comspec-opening="<?= (int) ($ro['id'] ?? 0) ?>">Candidater</a>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($showMedia): ?>
    <section
      id="medias"
      class="cl-media cl-rise community-landing__media"
      aria-labelledby="medias-title"
      data-media-count="<?= (int) $mediaCount ?>"
      data-media-layout="cluster"
      <?php if ($mediaLikesEnabled): ?>
      data-media-likes="1"
      data-media-likes-csrf="<?= htmlspecialchars($mediaLikeCsrf, ENT_QUOTES, 'UTF-8') ?>"
      data-media-likes-auth="<?= $mediaViewerCanLike ? '1' : '0' ?>"
      data-media-likes-login="<?= htmlspecialchars($mediaLoginUrl, ENT_QUOTES, 'UTF-8') ?>"
      <?php endif; ?>
    >
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker">Feed média</p>
          <h2 id="medias-title" class="cl-h2">Nos dernières opérations en images</h2>
        </div>
        <a class="cl-btn cl-btn--light" href="<?= htmlspecialchars($mediasUrl, ENT_QUOTES, 'UTF-8') ?>">Voir toute la galerie</a>
      </div>
      <?php if ($publicMediaCollections !== []): ?>
      <div class="cl-chips" aria-label="Collections">
        <?php foreach ($publicMediaCollections as $col): ?>
          <?php $ct = trim((string) ($col['title'] ?? '')); if ($ct === '') { continue; } ?>
          <span class="cl-chip"><?= htmlspecialchars($ct) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($publicMediaItems !== []): ?>
      <div class="community-landing__gallery community-landing__gallery--cluster cl-media__gallery" data-media-gallery>
        <div class="community-landing__gallery-track">
          <?php foreach (array_slice($publicMediaItems, 0, 6) as $mi): ?>
            <?php
              $mk = (string) ($mi['media_kind'] ?? 'image');
              $mtitle = trim((string) ($mi['title'] ?? ''));
              $mcap = trim((string) ($mi['caption'] ?? ''));
              $murl = \App\Support\CommunityMediaDetails::publicUrl(isset($mi['storage_path']) ? (string) $mi['storage_path'] : null);
              $membed = \App\Support\CommunityMediaDetails::embedUrl(isset($mi['external_url']) ? (string) $mi['external_url'] : null);
              $regions = \App\Support\CommunityMediaDetails::parseBlurRegions($mi['blur_regions_json'] ?? null);
              $wide = $mk === 'long_video' || !empty($mi['is_hero']);
              $kindLabel = \App\Support\CommunityMediaDetails::kindLabel($mk);
              $itemClass = 'community-landing__gallery-item';
              if ($wide) {
                  $itemClass .= ' community-landing__gallery-item--wide';
              }
              if ($mk === 'long_video') {
                  $itemClass .= ' community-landing__gallery-item--video';
              }
              $canLightbox = ($mk === 'image' && $murl) || ($mk === 'short_video' && $murl) || ($mk === 'long_video' && $membed);
              $lightboxAlt = $mtitle !== '' ? $mtitle : ($mk === 'image' ? 'Image de la communauté' : 'Vidéo de la communauté');
              $mediaItemId = (int) ($mi['id'] ?? 0);
              $likesCount = (int) ($mi['likes_count'] ?? 0);
              $likedByViewer = !empty($mi['liked_by_viewer']);
              $likeUrl = $mediaItemId > 0
                  ? url('c/' . rawurlencode((string) $slug) . '/medias/' . $mediaItemId . '/like')
                  : '';
            ?>
            <article
              class="<?= htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') ?>"
              <?php if ($canLightbox): ?>
              data-lightbox-trigger
              data-lightbox-kind="<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>"
              <?php if ($murl): ?>data-lightbox-src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
              <?php if ($membed): ?>data-lightbox-embed="<?= htmlspecialchars($membed, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
              data-lightbox-title="<?= htmlspecialchars($mtitle, ENT_QUOTES, 'UTF-8') ?>"
              data-lightbox-caption="<?= htmlspecialchars($mcap, ENT_QUOTES, 'UTF-8') ?>"
              data-lightbox-alt="<?= htmlspecialchars($lightboxAlt, ENT_QUOTES, 'UTF-8') ?>"
              tabindex="0"
              role="button"
              aria-haspopup="dialog"
              aria-label="<?= htmlspecialchars('Agrandir' . ($mtitle !== '' ? ' : ' . $mtitle : ' le média'), ENT_QUOTES, 'UTF-8') ?>"
              <?php endif; ?>
              <?php if ($mediaLikesEnabled && $mediaItemId > 0): ?>
              data-media-id="<?= $mediaItemId ?>"
              data-like-count="<?= $likesCount ?>"
              data-liked="<?= $likedByViewer ? '1' : '0' ?>"
              data-like-url="<?= htmlspecialchars($likeUrl, ENT_QUOTES, 'UTF-8') ?>"
              <?php endif; ?>
            >
              <div class="community-landing__gallery-frame">
                <?php if ($mk === 'image' && $murl): ?>
                  <div class="community-landing__blur-host">
                    <img src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($lightboxAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                    <?php foreach ($regions as $reg): ?>
                    <span class="community-landing__blur-patch" style="left:<?= htmlspecialchars((string) $reg['x']) ?>%;top:<?= htmlspecialchars((string) $reg['y']) ?>%;width:<?= htmlspecialchars((string) $reg['w']) ?>%;height:<?= htmlspecialchars((string) $reg['h']) ?>%;"></span>
                    <?php endforeach; ?>
                  </div>
                <?php elseif ($mk === 'short_video' && $murl): ?>
                  <video src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>" playsinline muted preload="metadata" tabindex="-1" aria-hidden="true"></video>
                  <span class="community-landing__gallery-play" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg></span>
                <?php elseif ($mk === 'long_video' && $membed): ?>
                  <div class="community-landing__gallery-placeholder community-landing__gallery-placeholder--video" aria-hidden="true"></div>
                  <span class="community-landing__gallery-play" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg></span>
                <?php else: ?>
                  <div class="community-landing__gallery-placeholder" aria-hidden="true"></div>
                <?php endif; ?>
                <span class="community-landing__gallery-kind"><?= htmlspecialchars($kindLabel) ?></span>
                <?php if ($mediaLikesEnabled && $mediaItemId > 0): ?>
                <button type="button" class="community-landing__like-btn<?= $likedByViewer ? ' is-liked' : '' ?>" data-media-like aria-pressed="<?= $likedByViewer ? 'true' : 'false' ?>" aria-label="<?= $likedByViewer ? 'Retirer mon j’aime' : 'J’aime ce média' ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.2-4.35-9.6-8.4C.6 9.3 2.1 5.7 5.7 5.1c1.95-.3 3.75.6 4.8 2.1 1.05-1.5 2.85-2.4 4.8-2.1 3.6.6 5.1 4.2 3.3 7.5C19.2 16.65 12 21 12 21z" fill="currentColor"/></svg>
                  <span class="community-landing__like-count" data-like-count-label><?= $likesCount > 0 ? (int) $likesCount : '' ?></span>
                </button>
                <?php endif; ?>
              </div>
              <?php if ($mtitle !== '' || $mcap !== ''): ?>
              <div class="community-landing__caption">
                <?php if ($mtitle !== ''): ?><p class="community-landing__caption-title"><?= htmlspecialchars($mtitle) ?></p><?php endif; ?>
                <?php if ($mcap !== ''): ?><p class="community-landing__caption-text"><?= htmlspecialchars($mcap) ?></p><?php endif; ?>
              </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if ($mediaCount > 6): ?>
      <a class="cl-media__more" href="<?= htmlspecialchars($mediasUrl, ENT_QUOTES, 'UTF-8') ?>">Voir les <?= (int) $mediaCount ?> médias de la galerie</a>
      <?php endif; ?>
      <?php elseif ($publicMediaCollections !== []): ?>
      <p class="cl-prose">Les collections sont prêtes — les images et vidéos publiées apparaîtront ici.</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($showRoster): ?>
    <section id="roster" class="cl-rise" aria-labelledby="cl-roster-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker">Effectifs publics</p>
          <h2 id="cl-roster-title" class="cl-h2">Membres visibles</h2>
        </div>
        <label class="cl-search">
          <span class="sr-only">Filtrer le roster</span>
          <input type="search" id="roster-filter" autocomplete="off" placeholder="Filtrer (indicatif, grade, unité…)">
        </label>
      </div>
      <?php if ($publicRosterRows === []): ?>
      <p class="cl-prose">Aucun membre n’a encore choisi d’apparaître sur la page publique.</p>
      <?php else: ?>
      <div class="cl-table-wrap">
        <table class="cl-table">
          <thead>
            <tr>
              <th>Indicatif</th>
              <th>Grade</th>
              <th>Fonction</th>
              <th>Unité</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($publicRosterRows as $rr): ?>
              <?php $st = (string) ($rr['status'] ?? 'active'); ?>
            <tr class="roster-row" data-roster-search="<?= htmlspecialchars(strtolower($rosterIndicatif($rr) . ' ' . ($rr['grade_short'] ?? '') . ' ' . ($rr['role_name'] ?? '') . ' ' . ($rr['unit_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
              <td class="cl-table__strong"><?= htmlspecialchars($rosterIndicatif($rr)) ?></td>
              <td class="cl-mono"><?= htmlspecialchars((string) ($rr['grade_short'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($rr['role_name'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($rr['unit_name'] ?? '—')) ?></td>
              <td><span class="cl-badge cl-badge--<?= $st === 'active' ? 'ok' : ($st === 'pending' ? 'info' : 'warn') ?>"><?= htmlspecialchars($rosterStatusLabel($st)) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($faqItems !== []): ?>
    <section id="faq" class="cl-rise" aria-labelledby="cl-faq-title">
      <p class="cl-kicker">Questions fréquentes</p>
      <h2 id="cl-faq-title" class="cl-h2">Avant de candidater</h2>
      <div class="cl-faq">
        <?php foreach ($faqItems as $fi): ?>
          <?php if (!is_array($fi)) { continue; } ?>
        <article class="cl-faq__card">
          <h3 class="cl-faq__q"><?= htmlspecialchars((string) ($fi['q'] ?? '')) ?></h3>
          <p class="cl-faq__a"><?= nl2br(htmlspecialchars((string) ($fi['a'] ?? ''))) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($testimonialItems !== []): ?>
    <section class="cl-rise" aria-labelledby="cl-quotes-title">
      <p class="cl-kicker">Témoignages</p>
      <h2 id="cl-quotes-title" class="cl-h2">Ce qu’en disent les membres</h2>
      <div class="cl-quotes">
        <?php foreach ($testimonialItems as $qi): ?>
          <?php if (!is_array($qi)) { continue; } ?>
        <blockquote class="cl-quotes__card">
          <p class="cl-quotes__text"><?= nl2br(htmlspecialchars((string) ($qi['text'] ?? ''))) ?></p>
          <footer class="cl-quotes__foot">
            <span class="cl-quotes__av" aria-hidden="true"><?= htmlspecialchars((string) ($qi['initials'] ?? '·')) ?></span>
            <div>
              <div class="cl-quotes__name"><?= htmlspecialchars((string) ($qi['name'] ?? '')) ?></div>
              <?php if (trim((string) ($qi['meta'] ?? '')) !== ''): ?>
              <div class="cl-quotes__meta cl-mono"><?= htmlspecialchars((string) $qi['meta']) ?></div>
              <?php endif; ?>
            </div>
          </footer>
        </blockquote>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($partnerItems !== []): ?>
    <section class="cl-partners cl-rise" aria-label="Unités alliées et partenaires">
      <p class="cl-partners__label">Unités alliées &amp; partenaires</p>
      <div class="cl-partners__grid">
        <?php foreach ($partnerItems as $partner): ?>
          <?php if (!is_string($partner) || trim($partner) === '') { continue; } ?>
        <div class="cl-ph cl-partners__item"><?= htmlspecialchars(mb_strtoupper(trim($partner))) ?></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section id="contact" class="cl-cta cl-rise" aria-labelledby="cl-cta-title">
      <div class="cl-cta__copy">
        <p class="cl-kicker cl-kicker--on-dark"><?= htmlspecialchars(mb_strtoupper($ctaKicker !== '' ? $ctaKicker : ($isLocked ? 'Contact' : 'Recrutement'))) ?></p>
        <h2 id="cl-cta-title" class="cl-h2 cl-h2--on-dark">
          <?php if ($ctaTitle !== ''): ?>
            <?= htmlspecialchars($ctaTitle) ?>
          <?php elseif ($primaryCta === 'rejoindre' || $primaryCta === 'candidater'): ?>
            Prêt à nous rejoindre ?
          <?php elseif ($primaryCta === 'contacter'): ?>
            Une question ? Contactez-nous
          <?php else: ?>
            <?= htmlspecialchars($name) ?>
          <?php endif; ?>
        </h2>
        <p class="cl-cta__lead">
          <?php if ($ctaBody !== ''): ?>
            <?= nl2br(htmlspecialchars($ctaBody)) ?>
          <?php elseif ($accessLabel !== ''): ?>
            <?= htmlspecialchars($accessLabel) ?>
          <?php elseif ($communityCode !== '' && !$isLocked): ?>
            Code communauté disponible pour les candidats. Réponse sous quelques jours en moyenne.
          <?php else: ?>
            Écrivez-nous ou rejoignez le canal Discord pour en savoir plus.
          <?php endif; ?>
        </p>
        <?php if ($communityCode !== '' && !$isLocked): ?>
        <div class="cl-cta__code">
          <span>Code communauté</span>
          <strong class="cl-mono" id="public-community-code"><?= htmlspecialchars($communityCode) ?></strong>
          <button type="button" class="cl-btn cl-btn--ghost" data-copy-code="<?= htmlspecialchars($communityCode, ENT_QUOTES, 'UTF-8') ?>">Copier</button>
        </div>
        <?php endif; ?>
      </div>
      <div class="cl-cta__actions">
        <?php if ($ctaPrimaryLabel !== null && $ctaPrimaryHref !== null): ?>
        <a href="<?= htmlspecialchars($ctaPrimaryHref) ?>" class="cl-btn cl-btn--accent comspec-analytics-cta" data-comspec-zone="pied_page" data-comspec-cta="<?= htmlspecialchars((string) $primaryCta) ?>"><?= htmlspecialchars(mb_strtoupper($navCtaLabel ?? $ctaPrimaryLabel)) ?></a>
        <?php endif; ?>
        <?php if ($discordUrl !== ''): ?>
        <a href="<?= htmlspecialchars($discordUrl) ?>" target="_blank" rel="noopener noreferrer" class="cl-btn cl-btn--ghost">Discord</a>
        <?php endif; ?>
        <?php if ($contactEmail !== ''): ?>
        <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="cl-btn cl-btn--ghost">Écrire un e-mail</a>
        <?php endif; ?>
        <?php if ($showForumCta): ?>
        <a href="<?= htmlspecialchars($forumUrl) ?>" class="cl-btn cl-btn--ghost">Forum</a>
        <?php endif; ?>
      </div>
      <?php if ($contactFormEnabled): ?>
      <form method="post" action="<?= htmlspecialchars(url('c/' . rawurlencode((string) $slug) . '/contact')) ?>" class="cl-contact-form">
        <?= \App\Core\Csrf::field() ?>
        <label>
          <span>Votre e-mail</span>
          <input type="email" name="from_email" required>
        </label>
        <label>
          <span>Message</span>
          <textarea name="body" rows="3" required></textarea>
        </label>
        <button type="submit" class="cl-btn cl-btn--accent">Envoyer</button>
      </form>
      <?php endif; ?>
    </section>
  </div>

  <footer class="cl-footer">
    <div class="cl-footer__inner">
      <div class="cl-footer__brand"><?= htmlspecialchars(mb_strtoupper($name)) ?> · Propulsé par Athena</div>
      <div class="cl-footer__links">
        <a href="<?= htmlspecialchars(url('communities')) ?>">Registre</a>
        <a href="<?= htmlspecialchars(url('mentions-legales')) ?>">Mentions légales</a>
        <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>">Données personnelles</a>
        <a href="<?= htmlspecialchars(url('cookies')) ?>">Cookies</a>
        <a href="#contact">Contact</a>
      </div>
    </div>
  </footer>
</div>
<script>
(function () {
  var btn = document.querySelector('[data-copy-code]');
  if (btn && navigator.clipboard) {
    btn.addEventListener('click', function () {
      var t = btn.getAttribute('data-copy-code') || '';
      navigator.clipboard.writeText(t).then(function () {
        btn.textContent = 'Copié';
        setTimeout(function () { btn.textContent = 'Copier'; }, 2000);
      });
    });
  }
  var rf = document.getElementById('roster-filter');
  if (rf) {
    rf.addEventListener('input', function () {
      var q = (rf.value || '').toLowerCase().trim();
      document.querySelectorAll('tr.roster-row').forEach(function (tr) {
        var hay = (tr.getAttribute('data-roster-search') || '');
        tr.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }
})();
</script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/community-landing-media.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
