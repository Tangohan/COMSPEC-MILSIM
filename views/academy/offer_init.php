<?php
/**
 * Libellés et petits rendus partagés par le catalogue, les fiches et le tableau de bord Academy
 * (données : App\Services\Academy\AcademyOffer). Les textes sont en français ; l'anglais vient de lang/en/site_auto.php.
 *
 * @var callable $h
 * @var callable $link
 */
$offer = is_array($offer ?? null) ? $offer : ['items' => [], 'totals' => []];
$offerItems = $offer['items'] ?? [];
$offerFilters = is_array($offerFilters ?? null) ? $offerFilters : \App\Services\Academy\AcademyOffer::filters([]);
$oxTabs = [
    'tout' => 'Tout',
    'formations' => 'Formations',
    'brevets' => 'Brevets et licences',
    'qualifications' => 'Qualifications',
    'parcours' => 'Parcours',
    'competences' => 'Compétences',
    'emetteurs' => 'Émetteurs',
];
$oxTabHelp = [
    'tout' => 'Tout ce que vous pouvez suivre et obtenir, au même endroit.',
    'formations' => 'Les cours à suivre en ligne : formations ATHENA Academy et formations de votre communauté.',
    'brevets' => 'Les qualifications qui donnent un brevet ou une licence, et les formations qui délivrent une licence.',
    'qualifications' => 'Les qualifications de votre communauté, quel que soit leur mode d’obtention, et le catalogue de référence des qualifications réelles par pays et de l’OTAN.',
    'parcours' => 'Des étapes enchaînées pour atteindre une compétence ou une fonction.',
    'competences' => 'Le référentiel des compétences et ce qui permet de les acquérir.',
    'emetteurs' => 'Les écoles, centres et unités qui délivrent les qualifications.',
];
$oxKinds = ['course' => 'Formation', 'qualification' => 'Qualification', 'reference' => 'Qualification de référence', 'pathway' => 'Parcours', 'competency' => 'Compétence', 'issuer' => 'Émetteur'];
$oxOrigins = [
    'referentiel' => 'Déjà dans mon référentiel',
    'communaute' => 'Créations de la communauté',
    'catalogue' => 'Catalogue de référence',
];
$oxOriginHelp = [
    'referentiel' => 'Qualifications réelles importées du catalogue de référence dans le référentiel de votre communauté.',
    'communaute' => 'Qualifications et brevets créés par votre communauté, dans le référentiel ou le Studio.',
    'catalogue' => 'Qualifications et brevets réels par pays et de l’OTAN, prêts à être importés dans votre référentiel.',
];
$oxNatures = ['brevet' => 'Brevet', 'qualification' => 'Qualification', 'cursus' => 'Cursus et sélection', 'licence' => 'Licence'];
$oxCanImport = !empty($offerCanImport);
$oxCountry = static function (string $code, string $label, bool $native = false) use ($h): string {
    if ($code === '') {
        return '';
    }
    $nativeName = \App\Support\QualificationCatalog\CatalogCountry::native($code);
    return '<span class="acx-country">' . \App\Support\QualificationCatalog\CatalogCountry::flag($code) . '<span translate="no">' . $h($label !== '' ? $label : $code) . '</span>'
        . ($native && $nativeName !== '' && $nativeName !== $label ? ' <span class="acx-country__native" translate="no" lang="' . $h(strtolower($code === 'NATO' ? 'en' : ($code === 'US' ? 'en' : ($code === 'CN' ? 'zh' : $code)))) . '">' . $h($nativeName) . '</span>' : '') . '</span>';
};
$oxStates = [
    'held' => 'Acquis',
    'expiring' => 'Expire bientôt',
    'expired' => 'Expirée',
    'renew' => 'À renouveler',
    'progress' => 'En cours',
    'pending' => 'En attente de validation',
    'eligible' => 'Conditions remplies',
    'available' => 'Disponible',
    'missing' => 'Prérequis manquants',
    'blocked' => 'Non disponible pour vous',
];
$oxModes = [
    'course' => 'Formation en ligne',
    'manual' => 'Délivrée par un instructeur',
    'academy' => 'En réussissant une formation',
    'auto' => 'Automatique (activité en jeu)',
    'pilot' => 'Cursus pilote (heures de vol)',
];
$oxStateGroups = ['acquis' => 'Acquis', 'en-cours' => 'En cours', 'disponible' => 'Disponible', 'prerequis' => 'Prérequis manquants'];
$oxSorts = ['pertinence' => 'Pertinence', 'nom' => 'Nom (A → Z)', 'etat' => 'État pour moi', 'duree' => 'Durée', 'pays' => 'Pays', 'niveau' => 'Niveau (du plus élevé)'];
$oxSources = ['athena' => 'ATHENA', 'community' => 'Ma communauté', 'reference' => 'Catalogue de référence'];
$oxLevelNames = function_exists('training_course_level_labels_fr') ? training_course_level_labels_fr() : [];
$oxLevel = static function (string $level) use ($h, $oxLevelNames): string {
    if (str_starts_with($level, 'levels:')) {
        return '<span>' . (int) substr($level, 7) . '</span> <span>niveaux</span>';
    }
    if (str_starts_with($level, 'ref:')) {
        return '<span>' . $h(\App\Support\QualificationCatalog\QualificationCatalogExplorer::levelLabel(substr($level, 4), 'fr')) . '</span>';
    }
    return $h($oxLevelNames[$level] ?? $level);
};
$oxDuration = static function (?int $min): string {
    if ($min === null || $min <= 0) {
        return '';
    }
    return $min < 60 ? $min . ' min' : intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) : '');
};
$oxValidity = static function (array $validity) use ($h): string {
    if (in_array('permanent', $validity, true)) {
        return '<span>Permanente</span>';
    }
    if (in_array('licence', $validity, true)) {
        return '<span>Licence de simulation</span>';
    }
    if (isset($validity['months'])) {
        return '<span>Valable</span> ' . (int) $validity['months'] . ' <span>mois</span>';
    }
    if (isset($validity['days'])) {
        return '<span>Valable</span> ' . (int) $validity['days'] . ' <span>jours</span>';
    }
    return '';
};
$oxDate = static function (mixed $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable(substr($raw, 0, 10)))->format('d/m/Y');
    } catch (\Throwable) {
        return '';
    }
};
$oxFiche = static fn (string $id): string => $link('catalogue') . '?fiche=' . rawurlencode($id);
$oxUrl = static function (array $changes = []) use ($link, $offerFilters): string {
    // Changer un filtre ramène à la première page.
    $q = array_merge($offerFilters, ['page' => ''], $changes);
    $q = array_filter($q, static fn ($v, $k): bool => $v !== '' && !($k === 'onglet' && $v === 'tout') && !($k === 'tri' && $v === 'pertinence'), ARRAY_FILTER_USE_BOTH);
    return $link('catalogue') . ($q !== [] ? '?' . http_build_query($q) : '');
};
$oxSymbol = static function (string $symbol): string {
    $paths = [
        'course' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H19v14H5.5A1.5 1.5 0 0 0 4 19.5z"/><path d="M4 19.5A1.5 1.5 0 0 0 5.5 21H19v-3"/><path d="M8 8h7M8 11h5"/>',
        'licence' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16.2c.6-1.6 1.8-2.4 3.2-2.4s2.6.8 3.2 2.4M14.5 10h3.5M14.5 13h2.5"/>',
        'brevet' => '<circle cx="12" cy="9" r="5.5"/><path d="m8.8 13.6-1.3 7.4 4.5-2.6 4.5 2.6-1.3-7.4"/><path d="m10 9 1.4 1.4L14.2 7.6"/>',
        'qualification' => '<path d="M12 3 4.5 6v5.5c0 4.4 3.1 8.2 7.5 9.5 4.4-1.3 7.5-5.1 7.5-9.5V6z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/>',
        'pathway' => '<circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M8 18h6a3 3 0 0 0 0-6h-4a3 3 0 0 1 0-6h6"/>',
        'competency' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'issuer' => '<path d="M3 21h18M5 21V10M19 21V10M9 21v-6h6v6"/><path d="m2.5 10 9.5-6 9.5 6z"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'alert' => '<path d="M12 4 2.8 20h18.4z"/><path d="M12 10v4.5M12 17.2v.3"/>',
    ];
    return '<svg class="acx-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$symbol] ?? $paths['course']) . '</svg>';
};
$oxBadge = static function (array $it) use ($h, $oxSymbol): string {
    if (($it['badge'] ?? '') !== '') {
        return '<span class="acx-badge acx-badge--img"><img src="' . $h($it['badge']) . '" alt="" loading="lazy" width="48" height="48"></span>';
    }
    return '<span class="acx-badge acx-badge--' . $h($it['symbol']) . '">' . $oxSymbol((string) $it['symbol']) . '</span>';
};
$oxStatePill = static function (array $it) use ($h, $oxStates): string {
    $state = (string) ($it['state'] ?? 'none');
    if ($it['kind'] === 'reference') {
        return '<span class="acx-state acx-state--reference">' . $h('Catalogue de référence') . '</span>';
    }
    if (!isset($oxStates[$state])) {
        return '';
    }
    if ($it['kind'] === 'course' && $state === 'held') {
        $label = 'Terminée';
    } elseif ($it['kind'] === 'pathway' && $state === 'held') {
        $label = 'Terminé';
    } else {
        $label = $oxStates[$state];
    }
    return '<span class="acx-state acx-state--' . $h($state) . '">' . $h($label) . '</span>';
};
$oxBar = static function (?int $pct, string $label = '') use ($h): string {
    if ($pct === null) {
        return '';
    }
    $pct = max(0, min(100, $pct));
    return '<div class="acx-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $pct . '"' . ($label !== '' ? ' aria-label="' . $h($label) . '"' : '') . '><i style="width:' . max(2, $pct) . '%"></i></div>';
};
