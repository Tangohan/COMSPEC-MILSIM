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
$slug = $tenant['slug'] ?? '';
$name = $tenant['name'] ?? '';
$cp = $communityProfile ?? \App\Services\Community\TenantCommunityProfileService::getPublicViewModel($communityConfig ?? [], (string) ($tenant['slug'] ?? ''));
$sv = is_array($showcaseVm ?? null) ? $showcaseVm : [];
$publicUnits = is_array($publicUnits ?? null) ? $publicUnits : [];
$publicRosterRows = is_array($publicRosterRows ?? null) ? $publicRosterRows : [];
$unitMemberCounts = is_array($unitMemberCounts ?? null) ? $unitMemberCounts : [];
$commanderNames = is_array($commanderNames ?? null) ? $commanderNames : [];
$hasMembershipInTenant = $hasMembershipInTenant ?? false;
$communityCode = trim((string) ($tenant['community_code'] ?? ''));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$recruitmentPublishedOpenings = is_array($recruitmentPublishedOpenings ?? null) ? $recruitmentPublishedOpenings : [];
/* Profils « Bureau des effectifs » / « Carte ATAK » : on rejoint par Discord, jamais par un formulaire du site. */
$discordOnlyRecruit = \App\Services\Community\TenantTypeConfig::recruitsOnDiscordOnly((string) ($tenant['tenant_type'] ?? 'full'));
if ($discordOnlyRecruit) {
    $recruitmentPublishedOpenings = [];
}
$recruitmentProspectionRef = trim((string) ($recruitmentProspectionRef ?? ''));
$recruitmentListUpdatedAt = trim((string) ($recruitmentListUpdatedAt ?? ''));
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
$commandChain = is_array($sv['commandChain'] ?? null) ? $sv['commandChain'] : [];
$militarySections = is_array($cp['militarySections'] ?? null) ? $cp['militarySections'] : [];
$mods = is_array($sv['publicModules'] ?? null) ? $sv['publicModules'] : [];
$publicMission = trim((string) ($sv['publicMission'] ?? ''));
$publicDoctrine = trim((string) ($sv['publicDoctrine'] ?? ''));
$contactIntro = trim((string) ($cp['contactIntro'] ?? ''));
$publicModuleCatalog = [
    'documents' => [
        'label' => 'Documents',
        'desc' => 'Doctrine, consignes et ressources partagées une fois intégré à la communauté.',
    ],
    'events' => [
        'label' => 'Événements',
        'desc' => 'Manœuvres, sessions et rendez-vous à venir — consultez l’agenda public ci-dessous.',
    ],
    'roster' => [
        'label' => 'Effectifs publics',
        'desc' => 'Aperçu des opérateurs qui ont choisi d’apparaître sur la fiche publique.',
    ],
    'training' => [
        'label' => 'Formations',
        'desc' => 'Parcours et modules pour monter en compétence après l’intégration.',
    ],
];
$enabledPublicModules = [];
foreach ($publicModuleCatalog as $moduleKey => $moduleMeta) {
    if (empty($mods[$moduleKey])) {
        continue;
    }
    $enabledPublicModules[$moduleKey] = $moduleMeta;
}

$heroHeadline = trim((string) ($sv['heroHeadline'] ?? ''));
if ($heroHeadline === '') {
    $heroHeadline = $name;
}
$heroLead = trim((string) ($sv['heroSubtitle'] ?? ''));
if ($heroHeadline === '') {
    $heroHeadline = $name;
}

$aboutTitle = trim((string) ($sv['aboutTitle'] ?? ''));
if ($aboutTitle === '' || mb_strtolower($aboutTitle) === 'qui nous sommes') {
    $aboutTitle = $name !== '' ? ('Découvrir ' . $name) : 'Notre communauté';
}
$aboutBody = trim((string) ($sv['aboutBody'] ?? ''));
if ($aboutBody === '') {
    $aboutBody = trim((string) ($cp['simpleBody'] ?? ''));
}
if ($aboutBody === '') {
    $aboutBody = trim((string) ($cp['welcomeText'] ?? ''));
}
if ($aboutBody === '') {
    $aboutBody = 'Une communauté milsim structurée : opérations régulières, progression individuelle et un accueil soigné pour les nouveaux membres.';
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
$highlightTitle = trim((string) ($sv['highlightTitle'] ?? ''));
$highlightBody = trim((string) ($sv['highlightBody'] ?? ''));
$highlightStyle = in_array(($sv['highlightStyle'] ?? 'info'), ['info', 'warning', 'success'], true)
    ? (string) $sv['highlightStyle']
    : 'info';
$showHighlight = $highlightTitle !== '' || $highlightBody !== '';
$scheduleLabel = trim((string) ($sv['scheduleLabel'] ?? ''));
$scheduleBody = trim((string) ($sv['scheduleBody'] ?? ''));
$showSchedule = $scheduleLabel !== '' || $scheduleBody !== '';
$socialWebsite = trim((string) ($sv['socialWebsite'] ?? ''));
$socialYoutube = trim((string) ($sv['socialYoutube'] ?? ''));
$socialTeamspeak = trim((string) ($sv['socialTeamspeak'] ?? ''));
$socialDiscord = trim((string) ($sv['socialDiscord'] ?? ($discordUrl ?? '')));
$stickyCtaEnabled = !array_key_exists('stickyCtaEnabled', $sv) || !empty($sv['stickyCtaEnabled']);
$socialLinks = [];
if ($socialDiscord !== '') {
    $socialLinks[] = ['label' => 'Discord', 'href' => $socialDiscord, 'external' => true];
}
if ($socialWebsite !== '') {
    $socialLinks[] = ['label' => 'Site', 'href' => $socialWebsite, 'external' => true];
}
if ($socialYoutube !== '') {
    $socialLinks[] = ['label' => 'YouTube', 'href' => $socialYoutube, 'external' => true];
}
if ($socialTeamspeak !== '') {
    $tsHref = preg_match('#^(https?|ts3server):#i', $socialTeamspeak)
        ? $socialTeamspeak
        : ('ts3server://' . ltrim($socialTeamspeak, '/'));
    $socialLinks[] = ['label' => 'TeamSpeak', 'href' => $tsHref, 'external' => true];
}

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
if ($discordOnlyRecruit && $discordUrl !== '') {
    $enlistUrl = $discordUrl;
}
$mediasUrl = url('c/' . rawurlencode((string) $slug) . '/medias');
$reelsUrl = url('c/' . rawurlencode((string) $slug) . '/reels');

/* CTA hero / nav : libellés distincts (rejoindre ≠ candidater ≠ contacter). */
$ctaPrimaryLabel = match ($primaryCta) {
    'rejoindre' => 'Rejoindre',
    'candidater' => 'Candidater',
    'contacter' => 'Nous contacter',
    default => null,
};
$ctaPrimaryHref = match ($primaryCta) {
    'rejoindre', 'candidater' => $enlistUrl,
    'contacter' => '#contact',
    default => null,
};
if ($ctaPrimaryLabel === null || $ctaPrimaryHref === null) {
    if (!$isLocked) {
        $primaryCta = $publicAudience === 'platform' ? 'candidater' : 'rejoindre';
        $ctaPrimaryLabel = $primaryCta === 'candidater' ? 'Candidater' : 'Rejoindre';
        $ctaPrimaryHref = $enlistUrl;
    } elseif ($hasContactCta) {
        $primaryCta = 'contacter';
        $ctaPrimaryLabel = 'Nous contacter';
        $ctaPrimaryHref = '#contact';
    } else {
        $primaryCta = 'medias';
        $ctaPrimaryLabel = 'Voir les médias';
        $ctaPrimaryHref = ($publicMediaItems !== [] || $publicMediaCollections !== []) ? '#medias' : $mediasUrl;
    }
}
$ctaPrimaryLabel = trim((string) $ctaPrimaryLabel);
if ($ctaPrimaryLabel === '') {
    $ctaPrimaryLabel = !$isLocked ? 'Rejoindre' : 'En savoir plus';
}
if ($discordOnlyRecruit && in_array((string) $primaryCta, ['rejoindre', 'candidater'], true)) {
    $ctaPrimaryLabel = 'Rejoindre sur Discord';
}
$ctaPrimaryHint = $discordOnlyRecruit && in_array((string) $primaryCta, ['rejoindre', 'candidater'], true)
    ? 'Le recrutement se fait sur notre Discord, pas sur ce site : présentez-vous dans le salon d’accueil.'
    : null;
$ctaPrimaryHint ??= match ((string) $primaryCta) {
    'rejoindre' => 'Accès membres : code communauté ou inscription.',
    'candidater' => 'Déposez une candidature pour intégrer l’unité.',
    'contacter' => 'Écrivez à l’état-major sans engagement.',
    'medias' => 'Parcourez les publications publiques.',
    default => '',
};
$navCtaLabel = match ((string) $primaryCta) {
    'rejoindre' => 'Rejoindre',
    'candidater' => 'Candidater',
    'contacter' => 'Contacter',
    'medias' => 'Médias',
    default => $ctaPrimaryLabel,
};

$liveUnitCount = count($publicUnits);
$liveOpeningCount = count($recruitmentPublishedOpenings);
$liveMediaCount = count($publicMediaItems);
$liveEventCount = count($publicUpcomingEvents);
$liveRosterCount = count($publicRosterRows);
$effectifStat = trim((string) ($stats['effectif'] ?? ''));
$unitesStat = trim((string) ($stats['unites'] ?? ''));
$activiteStat = trim((string) ($stats['activite'] ?? ''));
$theatreStat = trim((string) ($stats['theatre'] ?? ''));
if ($effectifStat === '' && $liveRosterCount > 0) {
    $effectifStat = (string) $liveRosterCount;
}
if ($unitesStat === '' && $liveUnitCount > 0) {
    $unitesStat = (string) $liveUnitCount;
}

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
        if ($hv === '') {
            continue;
        }
        $heroFacts[] = ['v' => $hv, 'k' => $hk !== '' ? $hk : '—'];
    }
}
if ($heroFacts === []) {
    if ($effectifStat !== '') {
        $heroFacts[] = ['v' => $effectifStat, 'k' => 'Membres actifs'];
    }
    if ($unitesStat !== '') {
        $heroFacts[] = ['v' => $unitesStat, 'k' => 'Unités publiques'];
    }
    if ($activiteStat !== '' && $activiteStat !== '—') {
        $heroFacts[] = ['v' => $activiteStat, 'k' => 'Présence (30 j)'];
    }
    if ($theatreStat !== '') {
        $heroFacts[] = ['v' => $theatreStat, 'k' => 'Théâtre principal'];
    }
    if ($liveOpeningCount > 0) {
        $heroFacts[] = ['v' => (string) $liveOpeningCount, 'k' => 'Offres ouvertes'];
    }
    if ($liveMediaCount > 0 && count($heroFacts) < 4) {
        $heroFacts[] = ['v' => (string) $liveMediaCount, 'k' => 'Médias publiés'];
    }
    if ($liveEventCount > 0 && count($heroFacts) < 4) {
        $heroFacts[] = ['v' => (string) $liveEventCount, 'k' => 'À venir'];
    }
}
$heroFacts = array_slice($heroFacts, 0, 4);

$statCards = [];
$pushStat = static function (array &$cards, string $label, string $value, string $note, int $pct): void {
    $value = trim($value);
    if ($value === '' || $value === '—') {
        return;
    }
    foreach ($cards as $existing) {
        if (($existing['label'] ?? '') === $label) {
            return;
        }
    }
    $cards[] = [
        'label' => $label,
        'value' => $value,
        'note' => $note,
        'pct' => max(8, min(100, $pct)),
    ];
};
$pushStat($statCards, 'Effectif', $effectifStat, 'Membres actifs', 82);
if ($activiteStat !== '' && $activiteStat !== '—') {
    $actNum = (int) preg_replace('/\D+/', '', $activiteStat);
    $pushStat($statCards, 'Présence', $activiteStat, 'Sur 30 jours', $actNum ?: 60);
}
$pushStat($statCards, 'Unités', $unitesStat, 'Visibles sur la vitrine', $liveUnitCount > 0 ? min(100, 20 + $liveUnitCount * 15) : 70);
if ($liveOpeningCount > 0) {
    $pushStat($statCards, 'Offres ouvertes', (string) $liveOpeningCount, 'Recrutement publié', min(100, 25 + $liveOpeningCount * 20));
}
if ($liveMediaCount > 0) {
    $pushStat($statCards, 'Médias', (string) $liveMediaCount, 'Images et vidéos publiées', min(100, 20 + $liveMediaCount * 12));
}
if ($liveEventCount > 0) {
    $pushStat($statCards, 'Agenda', (string) $liveEventCount, 'Prochains rendez-vous', min(100, 30 + $liveEventCount * 12));
}
if ($theatreStat !== '') {
    $pushStat($statCards, 'Référence', $theatreStat, 'Théâtre / fuseau', 55);
}
if ($foundedYearSetting !== '' && count($statCards) < 2) {
    $pushStat($statCards, 'Fondée', $foundedYearSetting, 'Année de création', 40);
}
/* Évite une rangée cassée à 1 carte : on n’affiche le bandeau que s’il a du sens. */
if (count($statCards) === 1 && $liveOpeningCount === 0 && $liveMediaCount === 0 && $liveUnitCount === 0) {
    $statCards = [];
}
$statCards = array_slice($statCards, 0, 5);
$statCols = max(1, count($statCards));

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

/* Contraste CTA : si l’accent tenant est trop sombre, forcer texte clair (évite bouton « vide »). */
$clAccentOn = '#04231a';
$clAccentBg = $brandAccent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandAccent) ? $brandAccent : '';
if ($clAccentBg !== '') {
    $hx = ltrim($clAccentBg, '#');
    $r = hexdec(substr($hx, 0, 2));
    $g = hexdec(substr($hx, 2, 2));
    $b = hexdec(substr($hx, 4, 2));
    $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    if ($luma < 0.45) {
        $clAccentOn = '#ffffff';
    }
    /* Accent quasi-noir → revenir au vert vitrine pour rester lisible */
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
$showcaseBackUrl = trim((string) ($showcaseBackUrl ?? ''));
if ($showcaseBackUrl === '') {
    $showcaseBackUrl = url('communities');
}

/* —— Mise en page personnalisable (PublicPageBuilder) et édition en place —— */
$pageLayout = is_array($pageLayout ?? null)
    ? $pageLayout
    : \App\Services\Community\PublicPageBuilder::resolve(is_array($communityConfig ?? null) ? $communityConfig : []);
$pageEditor = is_array($pageEditor ?? null) ? $pageEditor : [];
$pbCanEdit = !empty($pageEditor['canEdit']);
$pbEditing = $pbCanEdit && !empty($pageEditor['editing']);
$pbHeader = $pageLayout['header'];
$pbSections = [];
foreach ($pageLayout['blocks'] as $pbBlk) {
    if ($pbBlk['type'] === 'section') {
        $pbSections[$pbBlk['key']] = $pbBlk;
    }
}
unset($pbBlk);
/* Titre de section : texte propre à la communauté, sinon libellé par défaut. */
$clT = static function (string $section, string $prop, string $default) use ($pbSections): string {
    $v = trim((string) ($pbSections[$section]['props'][$prop] ?? ''));

    return $v !== '' ? $v : $default;
};
/* Attribut d’édition en place (vide hors mode édition). */
$clE = static fn (string $attr): string => $pbEditing ? ' ' . $attr . ' translate="no"' : '';
$clEs = static fn (string $section, string $prop): string => $pbEditing
    ? ' data-cl-sprop="' . htmlspecialchars($section . '.' . $prop, ENT_QUOTES, 'UTF-8') . '" translate="no"'
    : '';
$clF = static fn (string $field): string => $pbEditing
    ? ' data-cl-field="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '" translate="no"'
    : '';
$clUp = static fn (string $s): string => $pbEditing ? $s : mb_strtoupper($s);
$pbTagline = trim((string) ($pbHeader['tagline'] ?? ''));
$pbNavSub = $pbTagline !== '' ? $pbTagline : $navSub;
$pbNavCtaLabel = trim((string) ($pbHeader['cta_label'] ?? '')) !== '' ? (string) $pbHeader['cta_label'] : (string) $navCtaLabel;
$pbNavCtaHref = trim((string) ($pbHeader['cta_url'] ?? '')) !== '' ? (string) $pbHeader['cta_url'] : (string) $ctaPrimaryHref;
$pbPageTheme = (string) ($pageLayout['theme'] ?? 'light');
?>
<div id="top" class="community-public-vitrine community-landing cl-vitrine" data-pb-theme="<?= htmlspecialchars($pbPageTheme, ENT_QUOTES, 'UTF-8') ?>"<?= $pbEditing ? ' data-cl-editing="1"' : '' ?><?= $clStyle !== '' ? ' style="' . htmlspecialchars($clStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<?php
foreach (['community-page-builder', 'community-orbat-styles'] as $pbCssName) {
    $pbCssFile = base_path('public/assets/css/' . $pbCssName . '.css');
    if (is_readable($pbCssFile)) {
        echo '<style id="' . $pbCssName . '-css">' . (string) file_get_contents($pbCssFile) . '</style>';
    }
}
unset($pbCssFile, $pbCssName);
?>
<?php if ($pbPageTheme === 'auto'): ?>
<script>(function(){var r=document.currentScript.parentNode,m=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)');function a(){r.classList.toggle('pb-dark',!!(m&&m.matches));}a();if(m&&m.addEventListener){m.addEventListener('change',a);}})();</script>
<?php endif; ?>

  <?php ob_start(); ?>
  <header class="cl-nav pb-nav" role="banner" data-cl-nav
    data-pb-header-style="<?= htmlspecialchars((string) $pbHeader['style'], ENT_QUOTES, 'UTF-8') ?>"
    data-pb-header-layout="<?= htmlspecialchars((string) $pbHeader['layout'], ENT_QUOTES, 'UTF-8') ?>"
    data-pb-show-back="<?= !empty($pbHeader['show_back']) ? '1' : '0' ?>"
    data-pb-show-nav="<?= !empty($pbHeader['show_nav']) ? '1' : '0' ?>"
    data-pb-show-badges="<?= !empty($pbHeader['show_badges']) ? '1' : '0' ?>">
    <div class="cl-nav__start">
      <a href="<?= htmlspecialchars($showcaseBackUrl, ENT_QUOTES, 'UTF-8') ?>" class="cl-back pb-nav__back" aria-label="Retour">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
        <span>Retour</span>
      </a>
      <a class="cl-nav__brand pb-nav__brand" href="#top" aria-label="Haut de page">
        <?php if ($brandLogo !== ''): ?>
        <img class="cl-nav__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" data-img-fallback="logo" data-img-label="Emblème indisponible">
        <?php else: ?>
        <span class="cl-nav__mark" aria-hidden="true"><?= htmlspecialchars($nameInitials) ?></span>
        <?php endif; ?>
        <span class="cl-nav__titles">
          <span class="cl-nav__name"><?= name_blur_html($name) ?></span>
          <span class="cl-nav__sub" <?= $pbTagline !== '' && !$pbEditing ? ' translate="no"' : '' ?><?= $clE('data-cl-hprop="tagline" data-placeholder="Sous-titre de l’en-tête"') ?>><?= htmlspecialchars($pbEditing ? $pbNavSub : mb_strtoupper($pbNavSub)) ?></span>
        </span>
      </a>
    </div>
    <nav class="cl-nav__links pb-nav__links" aria-label="Sections de la vitrine"><!--pb-nav-links--></nav>
    <div class="pb-nav__end">
      <?php if ($publicAudience !== 'platform' && !empty($sv['recruitmentBadgeOpen']) && !$isLocked): ?>
      <span class="pb-nav__badge"><span class="cl-pulse" aria-hidden="true"></span>Recrutement ouvert</span>
      <?php endif; ?>
      <a href="<?= htmlspecialchars($pbNavCtaHref) ?>" class="cl-btn cl-btn--accent cl-nav__cta comspec-analytics-cta" data-comspec-zone="vitrine_nav" data-comspec-cta="<?= htmlspecialchars((string) $primaryCta) ?>"<?= $clE('data-cl-hprop="cta_label"') ?>><?= htmlspecialchars($pbEditing ? $pbNavCtaLabel : mb_strtoupper($pbNavCtaLabel)) ?></a>
      <button type="button" class="pb-nav__toggle" aria-expanded="false" aria-controls="pb-nav-drawer" aria-label="Ouvrir le menu" data-pb-nav-toggle>
        <span></span><span></span><span></span>
      </button>
    </div>
    <div class="pb-nav__drawer" id="pb-nav-drawer" hidden data-pb-nav-drawer>
      <nav aria-label="Menu de la vitrine"><!--pb-nav-links--></nav>
      <a href="<?= htmlspecialchars($pbNavCtaHref) ?>" class="cl-btn cl-btn--accent cl-btn--block"><?= htmlspecialchars(mb_strtoupper($pbNavCtaLabel)) ?></a>
    </div>
  </header>

  <?php if ($flashSuccess): ?>
  <div class="cl-flash cl-flash--ok"><?= htmlspecialchars($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
  <div class="cl-flash cl-flash--err"><?= htmlspecialchars($flashError) ?></div>
  <?php endif; ?>

  <?php $pbTop = (string) ob_get_clean(); $pbBuf = []; ob_start(); ?>
  <section class="cl-hero cl-rise" aria-labelledby="cl-hero-title">
    <div class="cl-hero__media" aria-hidden="true">
      <?php
      $heroRendered = false;
      $heroSectionImage = trim((string) ($pbSections['hero']['props']['image'] ?? ''));
      if ($heroSectionImage !== '') {
          echo '<img src="' . htmlspecialchars(\App\Services\Community\PublicPageBuilder::imageUrl($heroSectionImage), ENT_QUOTES, 'UTF-8') . '" alt="">';
          $heroRendered = true;
      }
      if (!$heroRendered && is_array($heroMediaItem)) {
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
        <?php if ($publicAudience !== 'platform' && $discordOnlyRecruit && !$isLocked): ?>
        <div class="cl-pill cl-pill--live"><span class="cl-pulse" aria-hidden="true"></span><span>RECRUTEMENT SUR DISCORD</span></div>
        <?php elseif ($publicAudience !== 'platform' && !empty($sv['recruitmentBadgeOpen']) && !$isLocked): ?>
        <div class="cl-pill cl-pill--live">
          <span class="cl-pulse" aria-hidden="true"></span>
          <span><?= htmlspecialchars(mb_strtoupper($recruitSessionLabel !== '' ? ('Recrutement ouvert · ' . $recruitSessionLabel) : 'Recrutement ouvert')) ?></span>
        </div>
        <?php elseif ($publicAudience !== 'platform' && $isLocked): ?>
        <div class="cl-pill cl-pill--muted">Recrutement fermé</div>
        <?php elseif ($accessLabel !== ''): ?>
        <div class="cl-pill cl-pill--live"><span class="cl-pulse" aria-hidden="true"></span><span><?= htmlspecialchars(mb_strtoupper($accessLabel)) ?></span></div>
        <?php endif; ?>

        <h1 id="cl-hero-title" class="cl-hero__title"<?= $clF('public_hero_headline') ?>><?= $pbEditing ? nl2br(htmlspecialchars($heroHeadline)) : nl2br(name_blur_html($heroHeadline)) ?></h1>
        <?php if (($heroLead !== '' && $heroLead !== $heroHeadline) || $pbEditing): ?>
        <p class="cl-hero__lead"<?= $clF('public_hero_subtitle') ?><?= $clE('data-placeholder="Phrase d’accroche"') ?>><?= nl2br(htmlspecialchars($heroLead !== $heroHeadline ? $heroLead : '')) ?></p>
        <?php endif; ?>

        <div class="cl-hero__actions">
          <a href="<?= htmlspecialchars((string) $ctaPrimaryHref) ?>" class="cl-btn cl-btn--accent comspec-analytics-cta" data-comspec-zone="vitrine_hero" data-comspec-cta="<?= htmlspecialchars((string) $primaryCta) ?>"><?= htmlspecialchars(mb_strtoupper((string) $ctaPrimaryLabel)) ?></a>
          <?php if (in_array((string) $primaryCta, ['rejoindre', 'candidater'], true) && $hasContactCta): ?>
          <a href="#contact" class="cl-btn cl-btn--ghost comspec-analytics-cta" data-comspec-zone="vitrine_hero" data-comspec-cta="contacter">Nous contacter</a>
          <?php elseif ($discordUrl !== ''): ?>
          <a href="<?= htmlspecialchars($discordUrl) ?>" target="_blank" rel="noopener noreferrer" class="cl-btn cl-btn--ghost">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14c1 1.4 6 1.4 7 0M9.5 10.5v.01M14.5 10.5v.01"/></svg>
            Discord
          </a>
          <?php elseif ($showMedia && (string) $primaryCta !== 'medias'): ?>
          <a href="#medias" class="cl-btn cl-btn--ghost">Voir les médias</a>
          <?php endif; ?>
        </div>
        <?php $ctaPrimaryHint = $clT('hero', 'lead', $ctaPrimaryHint); ?>
        <?php if ($ctaPrimaryHint !== '' || $pbEditing): ?>
        <p class="cl-hero__cta-hint"<?= $clEs('hero', 'lead') ?>><?= htmlspecialchars($ctaPrimaryHint) ?></p>
        <?php endif; ?>
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

  <?php $pbBuf['hero'] = (string) ob_get_clean(); ob_start(); ?>
  <?php if ($showHighlight || $showSchedule || $socialLinks !== []): ?>
  <div class="cl-signals cl-rise" aria-label="Informations clés">
    <div class="cl-wrap cl-signals__inner">
      <?php if ($showHighlight): ?>
      <aside class="cl-alert cl-alert--<?= htmlspecialchars($highlightStyle, ENT_QUOTES, 'UTF-8') ?>" role="status">
        <span class="cl-alert__mark" aria-hidden="true"></span>
        <div class="cl-alert__body">
          <?php if ($highlightTitle !== ''): ?>
          <p class="cl-alert__kicker"<?= $clF('public_highlight_title') ?>><?= htmlspecialchars($clUp($highlightTitle)) ?></p>
          <?php endif; ?>
          <?php if ($highlightBody !== ''): ?>
          <p class="cl-alert__text"<?= $clF('public_highlight_body') ?>><?= nl2br(htmlspecialchars($highlightBody)) ?></p>
          <?php endif; ?>
        </div>
      </aside>
      <?php endif; ?>

      <?php if ($showSchedule): ?>
      <div class="cl-schedule">
        <p class="cl-schedule__kicker"<?= $clF('public_schedule_label') ?>><?= htmlspecialchars($clUp($scheduleLabel !== '' ? $scheduleLabel : 'Créneaux')) ?></p>
        <?php if ($scheduleBody !== ''): ?>
        <p class="cl-schedule__text"<?= $clF('public_schedule_body') ?>><?= htmlspecialchars($scheduleBody) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($socialLinks !== []): ?>
      <nav class="cl-social" aria-label="Présence en ligne">
        <?php foreach ($socialLinks as $sl): ?>
        <a
          class="cl-social__link"
          href="<?= htmlspecialchars((string) $sl['href'], ENT_QUOTES, 'UTF-8') ?>"
          <?php if (!empty($sl['external'])): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
        ><?= htmlspecialchars((string) $sl['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php $pbBuf['signals'] = (string) ob_get_clean(); ob_start(); ?>
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
        <p class="cl-kicker cl-kicker--on-dark"<?= $clEs('video', 'kicker') ?>><?= htmlspecialchars($clT('video', 'kicker', 'En images')) ?></p>
        <h2 id="cl-video-title" class="cl-h2 cl-h2--on-dark"<?= $clF('public_video_title') ?>><?= htmlspecialchars($pvTitle !== '' ? $pvTitle : 'À quoi ressemble une soirée chez nous') ?></h2>
        <?php if ($pvCap !== '' || $pbEditing): ?>
        <p class="cl-video__lead"<?= $clF('public_video_body') ?><?= $clE('data-placeholder="Légende de la vidéo"') ?>><?= nl2br(htmlspecialchars($pvCap)) ?></p>
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

  <?php $pbBuf['video'] = (string) ob_get_clean(); ob_start(); ?>
  <?php if ($statCards !== []): ?>
  <div class="cl-stats">
    <div class="cl-stats__grid<?= $statCols < 3 ? ' cl-stats__grid--compact' : '' ?>" style="--cl-stats-cols:<?= (int) $statCols ?>" data-cols="<?= (int) $statCols ?>">
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

  <?php $pbBuf['stats'] = (string) ob_get_clean(); ?>

    <?php ob_start(); ?>
    <section id="presentation" class="cl-about cl-rise" aria-labelledby="cl-about-title">
      <div class="cl-about__text">
        <p class="cl-kicker"<?= $clEs('about', 'kicker') ?>><?= htmlspecialchars($clT('about', 'kicker', 'Qui nous sommes')) ?></p>
        <h2 id="cl-about-title" class="cl-h2"<?= $clF('public_about_title') ?>><?= $pbEditing ? htmlspecialchars($aboutTitle) : name_blur_html($aboutTitle) ?></h2>
        <?php if ($aboutBody !== ''): ?>
        <p class="cl-prose"<?= $clF('public_about_body') ?>><?= nl2br(htmlspecialchars($aboutBody)) ?></p>
        <?php endif; ?>
        <?php if ($aboutBodySecondary !== '' || $pbEditing): ?>
        <p class="cl-prose"<?= $clF('public_about_body_secondary') ?><?= $clE('data-placeholder="Second paragraphe (facultatif)"') ?>><?= nl2br(htmlspecialchars($aboutBodySecondary)) ?></p>
        <?php endif; ?>
        <?php if ($expectations !== '' && $prereqItems === []): ?>
        <p class="cl-prose"><?= nl2br(htmlspecialchars($expectations)) ?></p>
        <?php endif; ?>
        <?php if ($pitchPoints !== []): ?>
        <div class="cl-pitch"<?= $pbEditing && is_array($sv['pitch'] ?? null) && $sv['pitch'] !== [] ? ' data-cl-list="public_pitch"' : '' ?>>
          <?php foreach ($pitchPoints as $pp): ?>
          <div class="cl-pitch__item" data-cl-item>
            <div class="cl-pitch__t"<?= $clE('data-cl-sub="title"') ?>><?= htmlspecialchars($pp['t']) ?></div>
            <?php if ($pp['b'] !== '' || $pbEditing): ?>
            <div class="cl-pitch__b"<?= $clE('data-cl-sub="body"') ?>><?= nl2br(htmlspecialchars($pp['b'])) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="cl-about__gallery">
        <?php
        $gal = [];
        foreach (array_slice($galleryImages, 0, 3) as $gItem) {
            if (!is_array($gItem)) {
                continue;
            }
            $gUrl = \App\Support\CommunityMediaDetails::publicUrl(isset($gItem['storage_path']) ? (string) $gItem['storage_path'] : null);
            if (!$gUrl) {
                continue;
            }
            $gal[] = ['url' => $gUrl, 'title' => trim((string) ($gItem['title'] ?? ''))];
        }
        if ($gal === [] && is_array($heroMediaItem) && ($heroMediaItem['media_kind'] ?? '') === 'image') {
            $gUrl = \App\Support\CommunityMediaDetails::publicUrl(isset($heroMediaItem['storage_path']) ? (string) $heroMediaItem['storage_path'] : null);
            if ($gUrl) {
                $gal[] = ['url' => $gUrl, 'title' => trim((string) ($heroMediaItem['title'] ?? ''))];
            }
        }
        if ($gal === [] && $brandBanner !== '') {
            $gal[] = ['url' => $brandBanner, 'title' => 'Bannière de la communauté'];
        }
        for ($gi = 0; $gi < 3; $gi++):
            $slot = $gal[$gi] ?? null;
            $shotClass = 'cl-about__shot' . ($gi === 0 ? ' cl-about__shot--wide' : '');
        ?>
          <?php if (is_array($slot)): ?>
          <figure class="<?= htmlspecialchars($shotClass) ?>">
            <img src="<?= htmlspecialchars($slot['url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($slot['title'] !== '' ? $slot['title'] : 'Image de la communauté') ?>" loading="lazy">
          </figure>
          <?php else: ?>
          <div class="cl-ph <?= htmlspecialchars($shotClass) ?>" aria-hidden="true"><?= $gi === 0 ? 'Galerie' : '—' ?></div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    </section>

  <?php $pbBuf['about'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($publicMission !== '' || $publicDoctrine !== '' || $specialties !== []): ?>
    <section id="mission" class="cl-rise" aria-labelledby="cl-mission-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker"<?= $clEs('mission', 'kicker') ?>><?= htmlspecialchars($clT('mission', 'kicker', 'Identité opérationnelle')) ?></p>
          <h2 id="cl-mission-title" class="cl-h2"<?= $clEs('mission', 'title') ?>><?= htmlspecialchars($clT('mission', 'title', 'Mission & doctrine')) ?></h2>
        </div>
        <?php if ($publicDoctrine !== ''): ?>
        <p class="cl-section-aside"><strong>Doctrine :</strong> <span<?= $clF('public_doctrine') ?>><?= htmlspecialchars($publicDoctrine) ?></span></p>
        <?php endif; ?>
      </div>
      <?php if ($publicMission !== ''): ?>
      <p class="cl-prose"<?= $clF('public_mission') ?>><?= nl2br(htmlspecialchars($publicMission)) ?></p>
      <?php endif; ?>
      <?php if ($specialties !== []): ?>
      <div class="cl-chips" aria-label="Spécialités">
        <?php foreach ($specialties as $specialty): ?>
          <?php if (!is_string($specialty) || trim($specialty) === '') { continue; } ?>
        <span class="cl-chip"><?= htmlspecialchars(trim($specialty)) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

  <?php $pbBuf['mission'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($enabledPublicModules !== []): ?>
    <section id="modules" class="cl-rise" aria-labelledby="cl-modules-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker"<?= $clEs('modules', 'kicker') ?>><?= htmlspecialchars($clT('modules', 'kicker', 'Portail Athena')) ?></p>
          <h2 id="cl-modules-title" class="cl-h2"<?= $clEs('modules', 'title') ?>><?= htmlspecialchars($clT('modules', 'title', 'Features & accès')) ?></h2>
        </div>
        <p class="cl-section-aside"<?= $clEs('modules', 'lead') ?>><?= htmlspecialchars($clT('modules', 'lead', 'Ce que la communauté met à disposition — et comment y accéder.')) ?></p>
      </div>
      <div class="cl-feature-grid" aria-label="Modules et accès publics">
        <?php foreach ($enabledPublicModules as $moduleKey => $moduleMeta):
          $moduleLabel = (string) ($moduleMeta['label'] ?? '');
          $moduleDesc = (string) ($moduleMeta['desc'] ?? '');
          $moduleHref = null;
          $moduleCta = 'En savoir plus';
          if ($moduleKey === 'events' && $showAgenda) {
              $moduleHref = '#agenda';
              $moduleCta = 'Voir l’agenda';
          } elseif ($moduleKey === 'roster' && $showRoster) {
              $moduleHref = '#roster';
              $moduleCta = 'Voir les effectifs';
          } elseif ($moduleKey === 'documents' || $moduleKey === 'training') {
              $moduleHref = !$isLocked ? $enlistUrl : '#contact';
              $moduleCta = !$isLocked
                  ? ($primaryCta === 'candidater' ? 'Candidater pour y accéder' : 'Rejoindre pour y accéder')
                  : 'Nous contacter';
          }
          ?>
        <article class="cl-feature-card">
          <h3 class="cl-feature-card__title"><?= htmlspecialchars($moduleLabel) ?></h3>
          <?php if ($moduleDesc !== ''): ?>
          <p class="cl-feature-card__desc"><?= htmlspecialchars($moduleDesc) ?></p>
          <?php endif; ?>
          <?php if ($moduleHref !== null): ?>
          <a class="cl-feature-card__link" href="<?= htmlspecialchars($moduleHref) ?>"><?= htmlspecialchars($moduleCta) ?></a>
          <?php else: ?>
          <p class="cl-feature-card__meta">Accessible aux membres</p>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php $pbBuf['modules'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($commandChain !== []): ?>
    <section id="commandement" class="cl-rise" aria-labelledby="cl-command-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker"<?= $clEs('command', 'kicker') ?>><?= htmlspecialchars($clT('command', 'kicker', 'Encadrement')) ?></p>
          <h2 id="cl-command-title" class="cl-h2"<?= $clEs('command', 'title') ?>><?= htmlspecialchars($clT('command', 'title', 'Chaîne de commandement')) ?></h2>
        </div>
      </div>
      <div class="cl-units">
        <?php foreach ($commandChain as $command): ?>
          <?php
          if (!is_array($command)) { continue; }
          $commandRole = trim((string) ($command['role_label'] ?? ''));
          $commandName = trim((string) ($command['display_name'] ?? ''));
          $commandHint = trim((string) ($command['hint'] ?? ''));
          if ($commandRole === '' && $commandName === '' && $commandHint === '') { continue; }
          ?>
        <article class="cl-unit">
          <?php if ($commandRole !== ''): ?><div class="cl-unit__code"><?= htmlspecialchars(mb_strtoupper($commandRole)) ?></div><?php endif; ?>
          <?php if ($commandName !== ''): ?><h3 class="cl-unit__name"><?= htmlspecialchars($commandName) ?></h3><?php endif; ?>
          <?php if ($commandHint !== ''): ?><p class="cl-unit__desc"><?= nl2br(htmlspecialchars($commandHint)) ?></p><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php $pbBuf['command'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($showUnits): ?>
    <?php
      /* ORBAT public : un seul balisage, mis en forme par le style choisi dans l’éditeur
         (data-pb-orbat-* sur la section, voir community-orbat-styles.css) : l’aperçu change sans recharger. */
      $orbLang = \App\Support\PublicOrbat::language(is_array($communityConfig ?? null) ? $communityConfig : []);
      $orbL = \App\Support\PublicOrbat::labels($orbLang);
      $publicUnitsById = [];
      $publicUnitChildIds = [];
      foreach ($publicUnits as $puRow) {
          if (!is_array($puRow)) {
              continue;
          }
          $puId = (int) ($puRow['id'] ?? 0);
          if ($puId < 1) {
              continue;
          }
          $publicUnitsById[$puId] = $puRow;
          $publicUnitChildIds[$puId] = $publicUnitChildIds[$puId] ?? [];
      }
      $publicUnitRoots = [];
      foreach ($publicUnitsById as $puId => $puRow) {
          $parentId = (int) ($puRow['parent_id'] ?? 0);
          if ($parentId > 0 && isset($publicUnitsById[$parentId])) {
              $publicUnitChildIds[$parentId][] = $puId;
          } else {
              $publicUnitRoots[] = $puId;
          }
      }
      $publicOrbatHasTree = false;
      foreach ($publicUnitChildIds as $childList) {
          if ($childList !== []) {
              $publicOrbatHasTree = true;
              break;
          }
      }
      $orbCommanders = \App\Support\PublicOrbat::commanders(
          (int) ($tenant['id'] ?? 0),
          array_map(static fn (array $u): int => (int) ($u['commander_user_id'] ?? 0), array_values($publicUnitsById))
      );
      $orbUnitTone = static function (array $unit): string {
          $tone = trim((string) ($unit['public_accent_color'] ?? ''));
          if (preg_match('/^#[0-9A-Fa-f]{6}$/', $tone)) {
              return $tone;
          }
          $typeRaw = strtolower((string) ($unit['type'] ?? ''));
          if (str_contains($typeRaw, 'support') || str_contains($typeRaw, 'log')) {
              return '#c98a12';
          }
          if (str_contains($typeRaw, 'instruct') || str_contains($typeRaw, 'école') || str_contains($typeRaw, 'ecole')) {
              return '#1e6fbf';
          }

          return '#0b8a5c';
      };
      /* Style « liste par branche » : une seule unité racine → ses sous-unités font les branches. */
      $orbBranchDepth = count($publicUnitRoots) === 1 && $publicOrbatHasTree ? 1 : 0;
      $renderPublicUnitCard = null;
      $renderPublicUnitCard = static function (int $unitId, int $depth = 0, string $branchTone = '', string $parentName = '') use (
          &$renderPublicUnitCard,
          $publicUnitsById,
          $publicUnitChildIds,
          $unitMemberCounts,
          $commanderNames,
          $orbCommanders,
          $orbUnitTone,
          $orbL,
          $orbLang,
          $orbBranchDepth,
          $slug
      ): void {
          $unit = $publicUnitsById[$unitId] ?? null;
          if (!is_array($unit)) {
              return;
          }
          $uid = (int) ($unit['id'] ?? 0);
          $mc = (int) ($unitMemberCounts[$uid] ?? 0);
          $blurb = trim((string) ($unit['public_blurb'] ?? ''));
          $code = trim((string) ($unit['code'] ?? ''));
          $unitName = (string) ($unit['name'] ?? '');
          $unitSlugForLink = trim((string) ($unit['slug'] ?? ''));
          $cmdId = (int) ($unit['commander_user_id'] ?? 0);
          $cmdName = $cmdId > 0 ? ($commanderNames[$cmdId] ?? '') : '';
          $cmdGrade = $cmdName !== '' ? (string) ($orbCommanders[$cmdId]['grade'] ?? '') : '';
          $cmdPortrait = $cmdName !== '' ? ($orbCommanders[$cmdId]['portrait'] ?? null) : null;
          $capacity = isset($unit['public_capacity']) && $unit['public_capacity'] !== null && $unit['public_capacity'] !== ''
              ? (int) $unit['public_capacity']
              : null;
          $openSlotsRaw = $unit['public_open_slots'] ?? null;
          $openSlotsLabel = null;
          $slotsTone = 'ok';
          if ($openSlotsRaw !== null && $openSlotsRaw !== '') {
              $os = (int) $openSlotsRaw;
              if ($os === -1) {
                  $openSlotsLabel = $orbL['open'];
                  $slotsTone = 'info';
              } elseif ($os === 0) {
                  $openSlotsLabel = $orbL['full'];
                  $slotsTone = 'warn';
              } else {
                  $openSlotsLabel = $os . ' ' . ($os > 1 ? $orbL['slots_n'] : $orbL['slot']);
                  $slotsTone = $os <= 2 ? 'warn' : 'ok';
              }
          }
          $strengthLabel = $capacity !== null && $capacity > 0
              ? $mc . ' / ' . $capacity
              : ($mc . ' ' . ($mc > 1 ? $orbL['members'] : $orbL['member']));
          $strengthPct = $capacity !== null && $capacity > 0 ? (int) max(0, min(100, round($mc * 100 / $capacity))) : null;
          $tone = $orbUnitTone($unit);
          $branchTone = $branchTone !== '' ? $branchTone : $tone;
          $typeLabel = trim((string) ($unit['type'] ?? ''));
          if ($typeLabel !== '' && class_exists('App\\Support\\OrbatSheetLabels') && method_exists('App\\Support\\OrbatSheetLabels', 'typeLabel')) {
              try {
                  $typeLabel = (string) \App\Support\OrbatSheetLabels::typeLabel($typeLabel, $orbLang) ?: $typeLabel;
              } catch (\Throwable) {
              }
          }
          $childIds = $publicUnitChildIds[$uid] ?? [];
          $unitFoundedRaw = trim((string) ($unit['public_founded_on'] ?? ''));
          $unitFoundedDisp = '';
          if ($unitFoundedRaw !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $unitFoundedRaw, $ufm)) {
              $unitFoundedDisp = ((int) $ufm[3]) . '/' . $ufm[2] . '/' . $ufm[1];
          }
          $unitHref = $unitSlugForLink !== '' ? url('c/' . rawurlencode((string) $slug) . '/unite/' . rawurlencode($unitSlugForLink)) : '';
          $cardStyle = '--cl-unit-tone:' . $tone . ';--orb-branch:' . $branchTone . ';--orb-depth:' . $depth . ';--orb-rel:' . max(0, $depth - $orbBranchDepth);
          $branchAttr = $depth === $orbBranchDepth ? ' data-branch-head="1"' : ($depth < $orbBranchDepth ? ' data-branch-root="1"' : '');
          ?>
      <li class="cl-orbat-node" data-depth="<?= (int) $depth ?>"<?= $branchAttr ?>>
        <article class="cl-unit<?= $depth > 0 ? ' cl-unit--child' : '' ?>" style="<?= htmlspecialchars($cardStyle, ENT_QUOTES, 'UTF-8') ?>">
          <div class="orb-top">
            <span class="orb-badge" aria-hidden="true"><?= htmlspecialchars(\App\Support\PublicOrbat::insignia($code, name_mask_text($unitName))) ?></span>
            <div class="orb-titles">
              <div class="cl-unit__code"><?= htmlspecialchars(mb_strtoupper($code !== '' ? $code : ($typeLabel !== '' ? $typeLabel : $orbL['unit']))) ?></div>
              <h3 class="cl-unit__name"><?= name_blur_html($unitName) ?></h3>
              <?php if ($parentName !== ''): ?>
              <p class="orb-parent"><?= htmlspecialchars($orbL['under']) ?> <?= name_blur_html($parentName) ?></p>
              <?php endif; ?>
            </div>
            <span class="orb-count">
              <span class="orb-count__n cl-mono"><?= htmlspecialchars($strengthLabel) ?></span>
              <?php if ($openSlotsLabel !== null): ?>
              <span class="orb-count__slots cl-badge cl-badge--<?= htmlspecialchars($slotsTone) ?>"><?= htmlspecialchars($openSlotsLabel) ?></span>
              <?php endif; ?>
            </span>
          </div>
          <?php if ($blurb !== ''): ?>
          <p class="cl-unit__desc"><?= nl2br(htmlspecialchars($blurb)) ?></p>
          <?php endif; ?>
          <?php if ($cmdName !== ''): ?>
          <div class="orb-cmd">
            <span class="orb-cmd__face"><?php if ($cmdPortrait): ?><img src="<?= htmlspecialchars((string) $cmdPortrait, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async"><?php else: ?><?= htmlspecialchars(mb_strtoupper(mb_substr($cmdName, 0, 1))) ?><?php endif; ?></span>
            <span class="orb-cmd__txt">
              <span class="orb-cmd__k"><?= htmlspecialchars($orbL['leader']) ?></span>
              <span class="orb-cmd__v"><?php if ($cmdGrade !== ''): ?><span class="orb-cmd__grade"><?= htmlspecialchars($cmdGrade) ?></span> <?php endif; ?><?= htmlspecialchars($cmdName) ?></span>
            </span>
          </div>
          <?php elseif ($cmdId < 1 && $blurb === ''): ?>
          <p class="cl-unit__desc cl-unit__desc--vacant"><?= htmlspecialchars($orbL['vacant']) ?></p>
          <?php endif; ?>
          <div class="cl-unit__meta orb-meta--strength">
            <span><?= htmlspecialchars($orbL['strength']) ?></span>
            <span class="cl-mono"><?= htmlspecialchars($strengthLabel) ?></span>
          </div>
          <?php if ($strengthPct !== null): ?>
          <div class="orb-bar" role="img" aria-label="<?= htmlspecialchars($orbL['strength'] . ' ' . $strengthLabel, ENT_QUOTES, 'UTF-8') ?>"><span style="width:<?= (int) $strengthPct ?>%"></span></div>
          <?php endif; ?>
          <?php if ($unitFoundedDisp !== ''): ?>
          <div class="cl-unit__meta orb-meta--founded">
            <span><?= htmlspecialchars($orbL['founded']) ?></span>
            <span class="cl-mono"><?= htmlspecialchars($unitFoundedDisp) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($openSlotsLabel !== null): ?>
          <div class="cl-unit__meta orb-meta--slots">
            <span><?= htmlspecialchars($orbL['slots']) ?></span>
            <span class="cl-badge cl-badge--<?= htmlspecialchars($slotsTone) ?>"><?= htmlspecialchars($openSlotsLabel) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($unitHref !== ''): ?>
          <a class="cl-unit__link" href="<?= htmlspecialchars($unitHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orbL['record']) ?></a>
          <?php endif; ?>
        </article>
        <?php if ($childIds !== []): ?>
        <ul class="cl-orbat-children" aria-label="<?= htmlspecialchars($orbL['subunits'] . ' ' . name_mask_text($unitName), ENT_QUOTES, 'UTF-8') ?>">
          <?php foreach ($childIds as $childId): ?>
            <?php $renderPublicUnitCard((int) $childId, $depth + 1, $branchTone, $unitName); ?>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </li>
          <?php
      };
    ?>
    <section id="organisation" class="cl-rise" aria-labelledby="cl-org-title"<?= $orbLang !== 'fr' ? ' data-orb-lang="' . htmlspecialchars($orbLang, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker"<?= $clEs('organisation', 'kicker') ?>><?= htmlspecialchars($clT('organisation', 'kicker', 'Organisation')) ?></p>
          <h2 id="cl-org-title" class="cl-h2"<?= $clF('public_sections_title') ?>><?= htmlspecialchars($sectionsTitle !== '' ? $sectionsTitle : (count($publicUnits) === 1 ? 'Une unité structurée' : (count($publicUnits) . ' unités, une communauté'))) ?></h2>
        </div>
        <p class="cl-section-aside"<?= $clF('public_sections_lead') ?>><?= htmlspecialchars($sectionsLead !== '' ? $sectionsLead : ($publicOrbatHasTree ? 'Hiérarchie publique des unités et places ouvertes.' : 'Les places ouvertes sont indiquées par unité.')) ?></p>
      </div>
      <div class="orb-scroll">
      <ul class="cl-units<?= $publicOrbatHasTree ? ' cl-units--orbat' : '' ?>" aria-label="<?= htmlspecialchars($orbL['chain'], ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($publicUnitRoots as $rootId): ?>
          <?php $renderPublicUnitCard((int) $rootId, 0); ?>
        <?php endforeach; ?>
      </ul>
      </div>
    </section>
    <?php endif; ?>

  <?php $pbBuf['organisation'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($showAgenda): ?>
    <section id="agenda" class="cl-rise" aria-labelledby="cl-agenda-title">
      <p class="cl-kicker"<?= $clEs('agenda', 'kicker') ?>><?= htmlspecialchars($clT('agenda', 'kicker', 'Agenda public')) ?></p>
      <h2 id="cl-agenda-title" class="cl-h2"<?= $clEs('agenda', 'title') ?>><?= htmlspecialchars($clT('agenda', 'title', 'Les prochains rendez-vous')) ?></h2>
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

  <?php $pbBuf['agenda'] = (string) ob_get_clean(); ob_start(); ?>
    <section id="recrutement" class="cl-recruit cl-rise" aria-labelledby="cl-recruit-title">
      <div class="cl-recruit__prereq">
        <p class="cl-kicker"<?= $clEs('recruitment', 'kicker') ?>><?= htmlspecialchars($clT('recruitment', 'kicker', 'Ce qu’il faut')) ?></p>
        <h2 id="cl-recruit-title" class="cl-h2"<?= $clEs('recruitment', 'title') ?>><?= htmlspecialchars($clT('recruitment', 'title', 'Prérequis')) ?></h2>
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
        <p class="cl-kicker"<?= $clEs('recruitment', 'kicker2') ?>><?= htmlspecialchars($clT('recruitment', 'kicker2', 'Comment ça se passe')) ?></p>
        <h2 class="cl-h2"<?= $clEs('recruitment', 'title2') ?>><?= htmlspecialchars($clT('recruitment', 'title2', 'De la candidature au terrain')) ?></h2>
        <ol class="cl-steps"<?= $pbEditing ? ' data-cl-list="public_process_steps"' : '' ?>>
          <?php foreach ($recruitSteps as $st): ?>
          <li class="cl-steps__item" data-cl-item<?= !empty($st['accent']) ? ' data-cl-item-hl="1"' : '' ?>>
            <div class="cl-steps__rail">
              <span class="cl-steps__n<?= !empty($st['accent']) ? ' cl-steps__n--accent' : '' ?>"><?= htmlspecialchars($st['n']) ?></span>
              <span class="cl-steps__line" aria-hidden="true"></span>
            </div>
            <div class="cl-steps__body">
              <div class="cl-steps__head">
                <span class="cl-steps__t"<?= $clE('data-cl-sub="title"') ?>><?= htmlspecialchars($st['t']) ?></span>
                <span class="cl-steps__delay cl-mono"<?= $clE('data-cl-sub="delay"') ?>><?= htmlspecialchars($st['delay']) ?></span>
              </div>
              <p class="cl-steps__b"<?= $clE('data-cl-sub="body"') ?>><?= htmlspecialchars($st['b']) ?></p>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

  <?php $pbBuf['recruitment'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($showOpenings): ?>
    <section id="carrieres" class="cl-rise cl-openings bg-slate-50 relative" aria-labelledby="cl-openings-title">
      <div class="community-showcase-grain pointer-events-none absolute inset-0" aria-hidden="true"></div>
      <div class="relative max-w-7xl mx-auto px-6">
        <div class="cl-openings__head flex flex-col md:flex-row justify-between items-end pb-6 border-b-2 border-slate-200">
          <div>
            <h4 class="text-emerald-600 text-xs font-black uppercase tracking-[0.3em] mb-2">Direction des ressources humaines</h4>
            <h2 id="cl-openings-title" class="text-4xl sm:text-5xl font-black uppercase italic tracking-tighter text-slate-900">Prospection <span class="text-emerald-600">opérationnelle</span></h2>
          </div>
          <div class="text-right font-mono text-[10px] text-slate-400 uppercase hidden md:block mt-4 md:mt-0">
            <?php
            $docLine = $recruitmentProspectionRef !== '' ? htmlspecialchars($recruitmentProspectionRef, ENT_QUOTES, 'UTF-8') : 'Tableau des offres';
            $ts = $recruitmentListUpdatedAt !== '' ? strtotime($recruitmentListUpdatedAt) : false;
            $when = $ts ? date('d/m/Y H:i', $ts) : date('d/m/Y H:i');
            ?>
            Document mis à jour le : <?= htmlspecialchars($when, ENT_QUOTES, 'UTF-8') ?><br>
            Réf. affichée : <?= $docLine ?>
          </div>
        </div>
        <div class="grid grid-cols-1 gap-4 cl-openings__list">
          <?php foreach ($recruitmentPublishedOpenings as $ro): ?>
            <?php
              $pc = \App\Services\Recruitment\RecruitmentOpeningPresentation::personnelCategoryLabel((string) ($ro['personnel_category'] ?? 'other'));
              $arm = \App\Services\Recruitment\RecruitmentOpeningPresentation::armDomainLabel(isset($ro['arm_domain']) ? (string) $ro['arm_domain'] : null);
              $ref = (string) ($ro['reference_public'] ?? '');
              $sum = trim((string) ($ro['summary'] ?? ''));
              if ($sum === '') {
                  $sum = trim(strip_tags((string) ($ro['description'] ?? '')));
                  if (mb_strlen($sum) > 220) {
                      $sum = mb_substr($sum, 0, 217) . '…';
                  }
              }
              $avisSlug = (string) ($ro['public_page_slug'] ?? '');
              $detailUrl = $avisSlug !== ''
                  ? url('c/' . rawurlencode((string) $slug) . '/avis/' . rawurlencode($avisSlug))
                  : $enlistUrl;
            ?>
          <div class="bg-white border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col md:flex-row">
            <div class="bg-slate-900 text-white p-6 flex flex-col justify-center items-center md:w-48 text-center border-r border-slate-200">
              <span class="text-[10px] font-black uppercase tracking-widest opacity-60 mb-2">Catégorie</span>
              <span class="text-lg font-black italic uppercase leading-tight"><?= htmlspecialchars($pc, ENT_QUOTES, 'UTF-8') ?></span>
              <div class="mt-4 px-3 py-1 bg-emerald-600 text-[9px] font-bold uppercase tracking-wide"><?= htmlspecialchars($arm, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="p-8 flex-grow">
              <div class="flex justify-between items-start mb-4 gap-4 flex-wrap">
                <div>
                  <h3 class="text-2xl font-black uppercase italic text-slate-900"><?= htmlspecialchars((string) ($ro['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                  <p class="text-sm font-bold text-emerald-700 uppercase tracking-tight mt-1"><?= htmlspecialchars((string) ($ro['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="text-right shrink-0">
                  <span class="text-[10px] font-mono bg-slate-100 px-2 py-1 rounded">Réf. <?= htmlspecialchars($ref !== '' ? $ref : '—', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
              <?php if ($sum !== ''): ?>
              <p class="text-sm text-slate-600 leading-relaxed mb-6"><?= nl2br(htmlspecialchars($sum, ENT_QUOTES, 'UTF-8')) ?></p>
              <?php endif; ?>
              <div class="flex flex-wrap gap-6 border-t border-slate-100 pt-6">
                <?php if (trim((string) ($ro['employment_contract_label'] ?? '')) !== ''): ?>
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span class="text-[10px] font-bold uppercase text-slate-500"><?= htmlspecialchars((string) $ro['employment_contract_label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
                <?php if (trim((string) ($ro['employment_context_label'] ?? '')) !== ''): ?>
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                  <span class="text-[10px] font-bold uppercase text-slate-500"><?= htmlspecialchars((string) $ro['employment_context_label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="p-6 bg-slate-50 border-t md:border-t-0 md:border-l border-slate-100 flex flex-col items-stretch justify-center gap-2.5 min-w-[200px] cl-openings__actions">
              <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="cl-btn cl-btn--light cl-btn--block">Voir la fiche</a>
              <?php if (!$isLocked): ?>
              <?php
                $openingCtaLabel = ($primaryCta === 'rejoindre') ? 'Rejoindre' : 'Candidater';
                $openingCtaKind = ($primaryCta === 'rejoindre') ? 'rejoindre' : 'candidater';
              ?>
              <a href="<?= htmlspecialchars(url('c/' . rawurlencode((string) $slug) . '/enlistment?ouverture=' . (int) ($ro['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="cl-btn cl-btn--accent cl-btn--block comspec-analytics-cta" data-comspec-zone="liste_postes" data-comspec-cta="<?= htmlspecialchars($openingCtaKind, ENT_QUOTES, 'UTF-8') ?>" data-comspec-opening="<?= (int) ($ro['id'] ?? 0) ?>"><?= htmlspecialchars($openingCtaLabel) ?></a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

  <?php $pbBuf['openings'] = (string) ob_get_clean(); ob_start(); ?>
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
          <p class="cl-kicker"<?= $clEs('media', 'kicker') ?>><?= htmlspecialchars($clT('media', 'kicker', 'Photos récentes')) ?></p>
          <h2 id="medias-title" class="cl-h2"<?= $clEs('media', 'title') ?>><?= htmlspecialchars($clT('media', 'title', 'Nos dernières opérations')) ?></h2>
        </div>
        <div class="cl-media__actions">
          <a class="cl-btn cl-btn--accent" href="<?= htmlspecialchars($reelsUrl, ENT_QUOTES, 'UTF-8') ?>">Voir le fil</a>
          <?php
          // Badge « images » vers la galerie : seulement des photos sans zone floutée (visages, plaques…).
          $mediaBadgeItems = [];
          foreach ($galleryImages as $gbItem) {
              if (\App\Support\CommunityMediaDetails::parseBlurRegions($gbItem['blur_regions_json'] ?? null) !== []) {
                  continue;
              }
              $gbUrl = \App\Support\CommunityMediaDetails::publicUrl(isset($gbItem['storage_path']) ? (string) $gbItem['storage_path'] : null);
              if ($gbUrl) {
                  $mediaBadgeItems[] = ['src' => $gbUrl];
              }
              if (count($mediaBadgeItems) >= 3) {
                  break;
              }
          }
          ?>
          <?php if (count($mediaBadgeItems) >= 2): ?>
          <?php
          $ibItems = $mediaBadgeItems;
          $ibCount = $mediaCount;
          $ibLabel = $mediaCount === 1 ? 'média' : 'médias';
          $ibHref = $mediasUrl;
          $ibTone = 'light';
          $ibAria = 'Ouvrir la galerie complète';
          require base_path('views/partials/images_badge.php');
          unset($ibItems, $ibCount, $ibLabel, $ibHref, $ibTone, $ibAria);
          ?>
          <?php else: ?>
          <a class="cl-btn cl-btn--light" href="<?= htmlspecialchars($mediasUrl, ENT_QUOTES, 'UTF-8') ?>">Galerie complète</a>
          <?php endif; ?>
        </div>
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

  <?php $pbBuf['media'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($showRoster): ?>
    <section id="roster" class="cl-rise" aria-labelledby="cl-roster-title">
      <div class="cl-section-head">
        <div>
          <p class="cl-kicker"<?= $clEs('roster', 'kicker') ?>><?= htmlspecialchars($clT('roster', 'kicker', 'Effectifs publics')) ?></p>
          <h2 id="cl-roster-title" class="cl-h2"<?= $clEs('roster', 'title') ?>><?= htmlspecialchars($clT('roster', 'title', 'Membres visibles')) ?></h2>
        </div>
        <label class="cl-search">
          <span class="sr-only">Filtrer le roster</span>
          <input type="search" id="roster-filter" autocomplete="off" placeholder="Filtrer (indicatif, grade, unité…)">
        </label>
      </div>
      <?php if ($publicRosterRows === []): ?>
      <p class="cl-prose">Aucun membre n’a encore choisi d’apparaître sur la page publique.</p>
      <?php else: ?>
      <div class="cl-table-wrap cl-table-wrap--roster">
        <table class="cl-table cl-table--roster">
          <thead>
            <tr>
              <th>Indicatif</th>
              <th>Grade</th>
              <th>Fonction</th>
              <th>Unité</th>
              <th class="cl-table__right">Statut</th>
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
              <td class="cl-table__right"><span class="cl-badge cl-badge--pill cl-badge--<?= $st === 'active' ? 'ok' : ($st === 'pending' ? 'info' : 'warn') ?>"><?= htmlspecialchars($rosterStatusLabel($st)) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

  <?php $pbBuf['roster'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($faqItems !== []): ?>
    <section id="faq" class="cl-rise" aria-labelledby="cl-faq-title">
      <p class="cl-kicker"<?= $clEs('faq', 'kicker') ?>><?= htmlspecialchars($clT('faq', 'kicker', 'Questions fréquentes')) ?></p>
      <h2 id="cl-faq-title" class="cl-h2"<?= $clEs('faq', 'title') ?>><?= htmlspecialchars($clT('faq', 'title', 'Avant de candidater')) ?></h2>
      <div class="cl-faq"<?= $pbEditing ? ' data-cl-list="public_faq"' : '' ?>>
        <?php foreach ($faqItems as $fi): ?>
          <?php if (!is_array($fi)) { continue; } ?>
        <article class="cl-faq__card" data-cl-item>
          <h3 class="cl-faq__q"<?= $clE('data-cl-sub="q"') ?>><?= htmlspecialchars((string) ($fi['q'] ?? '')) ?></h3>
          <p class="cl-faq__a"<?= $clE('data-cl-sub="a"') ?>><?= nl2br(htmlspecialchars((string) ($fi['a'] ?? ''))) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php $pbBuf['faq'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($testimonialItems !== []): ?>
    <section id="temoignages" class="cl-rise" aria-labelledby="cl-quotes-title">
      <p class="cl-kicker"<?= $clEs('testimonials', 'kicker') ?>><?= htmlspecialchars($clT('testimonials', 'kicker', 'Témoignages')) ?></p>
      <h2 id="cl-quotes-title" class="cl-h2"<?= $clEs('testimonials', 'title') ?>><?= htmlspecialchars($clT('testimonials', 'title', 'Ce qu’en disent les membres')) ?></h2>
      <div class="cl-quotes"<?= $pbEditing ? ' data-cl-list="public_testimonials"' : '' ?>>
        <?php foreach ($testimonialItems as $qi): ?>
          <?php if (!is_array($qi)) { continue; } ?>
        <blockquote class="cl-quotes__card" data-cl-item>
          <p class="cl-quotes__text"<?= $clE('data-cl-sub="text"') ?>><?= nl2br(htmlspecialchars((string) ($qi['text'] ?? ''))) ?></p>
          <footer class="cl-quotes__foot">
            <span class="cl-quotes__av" aria-hidden="true"><?= htmlspecialchars((string) ($qi['initials'] ?? '·')) ?></span>
            <div>
              <div class="cl-quotes__name"<?= $clE('data-cl-sub="name"') ?>><?= htmlspecialchars((string) ($qi['name'] ?? '')) ?></div>
              <?php if (trim((string) ($qi['meta'] ?? '')) !== '' || $pbEditing): ?>
              <div class="cl-quotes__meta cl-mono"<?= $clE('data-cl-sub="meta"') ?>><?= htmlspecialchars((string) ($qi['meta'] ?? '')) ?></div>
              <?php endif; ?>
            </div>
          </footer>
        </blockquote>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php $pbBuf['testimonials'] = (string) ob_get_clean(); ob_start(); ?>
    <?php if ($partnerItems !== []): ?>
    <section class="cl-partners cl-rise" aria-label="Unités alliées et partenaires">
      <p class="cl-partners__label"<?= $clEs('partners', 'kicker') ?>><?= htmlspecialchars($clT('partners', 'kicker', 'Unités alliées & partenaires')) ?></p>
      <div class="cl-partners__grid">
        <?php foreach ($partnerItems as $partner): ?>
          <?php if (!is_string($partner) || trim($partner) === '') { continue; } ?>
        <div class="cl-ph cl-partners__item"><?= htmlspecialchars(mb_strtoupper(trim($partner))) ?></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php $pbBuf['partners'] = (string) ob_get_clean(); ob_start(); ?>
    <section id="contact" class="cl-cta cl-rise" aria-labelledby="cl-cta-title">
      <div class="cl-cta__copy">
        <p class="cl-kicker cl-kicker--on-dark"<?= $clF('public_cta_kicker') ?>><?= htmlspecialchars($clUp($ctaKicker !== '' ? $ctaKicker : ($isLocked ? 'Contact' : 'Recrutement'))) ?></p>
        <h2 id="cl-cta-title" class="cl-h2 cl-h2--on-dark"<?= $clF('public_cta_title') ?>>
          <?php if ($ctaTitle !== ''): ?>
            <?= htmlspecialchars($ctaTitle) ?>
          <?php elseif ($primaryCta === 'rejoindre' || $primaryCta === 'candidater'): ?>
            Prêt à nous rejoindre ?
          <?php elseif ($primaryCta === 'contacter'): ?>
            Une question ? Contactez-nous
          <?php else: ?>
            <?= name_blur_html($name) ?>
          <?php endif; ?>
        </h2>
        <p class="cl-cta__lead"<?= $clF('public_cta_body') ?>>
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
        <?php $pbShowIntro = $pbEditing; if ($contactIntro !== '') { $pbShowIntro = true; } ?>
        <?php if ($pbShowIntro): ?>
        <p class="cl-cta__lead"<?= $clF('contact_intro') ?><?= $clE('data-placeholder="Mot d’accueil pour le contact (facultatif)"') ?>><?= nl2br(htmlspecialchars($contactIntro)) ?></p>
        <?php endif; ?>
        <?php if ($communityCode !== '' && !$isLocked): ?>
        <div class="cl-cta__code">
          <span>Code communauté</span>
          <strong class="cl-mono" id="public-community-code"><?= htmlspecialchars($communityCode) ?></strong>
          <button type="button" class="cl-btn cl-btn--ghost" data-copy-code="<?= htmlspecialchars($communityCode, ENT_QUOTES, 'UTF-8') ?>">Copier</button>
        </div>
        <?php endif; ?>
      </div>
      <div class="cl-cta__actions">
        <a href="<?= htmlspecialchars((string) $ctaPrimaryHref) ?>" class="cl-btn cl-btn--accent comspec-analytics-cta" data-comspec-zone="pied_page" data-comspec-cta="<?= htmlspecialchars((string) $primaryCta) ?>"><?= htmlspecialchars(mb_strtoupper((string) ($navCtaLabel ?: $ctaPrimaryLabel))) ?></a>
        <?php if ($discordUrl !== ''): ?>
        <a href="<?= htmlspecialchars($discordUrl) ?>" target="_blank" rel="noopener noreferrer" class="cl-btn cl-btn--ghost">Discord</a>
        <?php endif; ?>
        <?php if ($contactEmail !== ''): ?>
        <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="cl-btn cl-btn--ghost">Écrire un e-mail</a>
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
    <?php $pbBuf['contact'] = (string) ob_get_clean(); ?>

  <?php require base_path('views/community/partials/page_sections.php'); ?>

  <footer class="cl-footer">
    <div class="cl-footer__inner">
      <div class="cl-footer__brand"><?= name_blur_html(mb_strtoupper($name)) ?> · Propulsé par Athena</div>
      <div class="cl-footer__links">
        <a href="<?= htmlspecialchars(url('communities')) ?>">Registre</a>
        <a href="<?= htmlspecialchars(url('mentions-legales')) ?>">Mentions légales</a>
        <a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>">Données personnelles</a>
        <a href="<?= htmlspecialchars(url('cookies')) ?>">Cookies</a>
        <a href="#contact">Contact</a>
      </div>
    </div>
  </footer>

  <?php if ($stickyCtaEnabled && !$isLocked && !empty($ctaPrimaryHref)): ?>
  <div class="cl-sticky-cta" data-cl-sticky-cta hidden>
    <div class="cl-sticky-cta__inner">
      <div class="cl-sticky-cta__copy">
        <p class="cl-sticky-cta__kicker"><?= name_blur_html(mb_strtoupper($name)) ?></p>
        <p class="cl-sticky-cta__title"><?= htmlspecialchars((string) ($ctaPrimaryLabel ?? 'Rejoindre')) ?></p>
      </div>
      <a href="<?= htmlspecialchars((string) $ctaPrimaryHref) ?>" class="cl-btn cl-btn--accent comspec-analytics-cta" data-comspec-zone="vitrine_sticky" data-comspec-cta="<?= htmlspecialchars((string) $primaryCta) ?>"><?= htmlspecialchars(mb_strtoupper((string) ($ctaPrimaryLabel ?? 'Rejoindre'))) ?></a>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($pbCanEdit) { require base_path('views/community/partials/page_editor.php'); } ?>
</div>
<script>
  (function () {
  /* En-tête : état au défilement, menu mobile, lien de la section en cours. */
  var nav = document.querySelector('[data-cl-nav]');
  if (nav) {
    var onScroll = function () { nav.classList.toggle('is-scrolled', window.scrollY > 24); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    var tgl = nav.querySelector('[data-pb-nav-toggle]');
    var drawer = nav.querySelector('[data-pb-nav-drawer]');
    if (tgl && drawer) {
      tgl.addEventListener('click', function () {
        var open = drawer.hidden;
        drawer.hidden = !open;
        tgl.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      drawer.addEventListener('click', function (e) {
        if (e.target.closest('a')) { drawer.hidden = true; tgl.setAttribute('aria-expanded', 'false'); }
      });
    }
    var links = Array.prototype.slice.call(nav.querySelectorAll('.pb-nav__links a[href^="#"]'));
    if (links.length && 'IntersectionObserver' in window) {
      var byId = {};
      links.forEach(function (a) {
        var t = document.getElementById(a.getAttribute('href').slice(1));
        if (t) { byId[t.id] = a; }
      });
      var spy = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting && byId[en.target.id]) {
            links.forEach(function (a) { a.classList.remove('is-active'); });
            byId[en.target.id].classList.add('is-active');
          }
        });
      }, { rootMargin: '-45% 0px -50% 0px' });
      Object.keys(byId).forEach(function (id) { spy.observe(document.getElementById(id)); });
    }
  }
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
  var sticky = document.querySelector('[data-cl-sticky-cta]');
  var hero = document.querySelector('.cl-hero');
  if (sticky && hero && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        sticky.hidden = entry.isIntersecting;
      });
    }, { threshold: 0.15 });
    io.observe(hero);
  } else if (sticky) {
    sticky.hidden = false;
  }
})();
</script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/community-landing-media.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
