<?php
/**
 * Vitrine : sections natives (rendues dans $pbBuf par show_showcase.php) et blocs de la bibliothèque,
 * dans l’ordre choisi par la communauté. Les sections « contenues » consécutives partagent un .cl-body,
 * comme avant ; les bandeaux pleine largeur le referment.
 *
 * @var array<string, string> $pbBuf
 * @var array<string, mixed> $pageLayout
 * @var bool $pbEditing
 */
use App\Services\Community\PublicPageBuilder;

$pbFullBleed = ['hero', 'signals', 'video', 'stats'];
$pbBoLinks = [
    'organisation' => 'back-office/organisation-effectifs',
    'agenda' => 'back-office/community/presentation',
    'openings' => 'back-office/recruitment/offers',
    'media' => 'back-office/media',
];
/* Menu de l’en-tête : sections visibles dans l’ordre de la page, libellé personnalisé possible. */
$pbAnchors = [
    'about' => ['presentation', 'L’unité', true],
    'mission' => ['mission', 'Mission', false],
    'modules' => ['modules', 'Accès', false],
    'command' => ['commandement', 'Commandement', false],
    'organisation' => ['organisation', 'Organisation', true],
    'agenda' => ['agenda', 'Agenda', true],
    'recruitment' => ['recrutement', 'Recrutement', !$isLocked],
    'openings' => ['carrieres', 'Offres', $isLocked],
    'media' => ['medias', 'Médias', true],
    'roster' => ['roster', 'Effectifs', false],
    'faq' => ['faq', 'FAQ', false],
    'testimonials' => ['temoignages', 'Témoignages', false],
    'contact' => ['contact', 'Contact', false],
];
$pbNavHtml = '';
foreach ($pageLayout['blocks'] as $pbBlk) {
    if (!empty($pbBlk['hidden'])) {
        continue;
    }
    $pbLabel = trim((string) ($pbBlk['nav_label'] ?? ''));
    if ($pbBlk['type'] === 'section') {
        $pbA = $pbAnchors[$pbBlk['key']] ?? null;
        if ($pbA === null || trim((string) ($pbBuf[$pbBlk['key']] ?? '')) === '' || ($pbLabel === '' && !$pbA[2])) {
            continue;
        }
        $pbHref = '#' . $pbA[0];
        $pbLabel = $pbLabel !== '' ? $pbLabel : $pbA[1];
    } else {
        if ($pbLabel === '') {
            continue;
        }
        $pbHref = '#pb-' . $pbBlk['id'];
    }
    $pbNavHtml .= '<a href="' . htmlspecialchars($pbHref, ENT_QUOTES, 'UTF-8') . '" data-pb-nav-for="' . htmlspecialchars((string) $pbBlk['id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($pbLabel) . '</a>';
}
echo str_replace('<!--pb-nav-links-->', $pbNavHtml, (string) ($pbTop ?? ''));
unset($pbAnchors, $pbA, $pbHref, $pbLabel);

$pbFlowOpen = false;
foreach ($pageLayout['blocks'] as $pbBlk) {
    if (!empty($pbBlk['hidden']) && !$pbEditing) {
        continue;
    }
    $pbIsSection = $pbBlk['type'] === 'section';
    if ($pbIsSection) {
        $pbKey = (string) $pbBlk['key'];
        $pbHtml = trim((string) ($pbBuf[$pbKey] ?? ''));
        if ($pbHtml === '' && !$pbEditing) {
            continue;
        }
        $pbFull = in_array($pbKey, $pbFullBleed, true);
    } else {
        $pbFull = ($pbBlk['style']['width'] ?? '') === 'full';
    }
    if ($pbFull && $pbFlowOpen) {
        echo "  </div>\n";
        $pbFlowOpen = false;
    } elseif (!$pbFull && !$pbFlowOpen) {
        echo "  <div class=\"cl-body pb-flow\">\n";
        $pbFlowOpen = true;
    }
    if (!$pbIsSection) {
        $pbBlock = $pbBlk;
        require base_path('views/community/partials/page_block.php');
        continue;
    }
    $pbAttr = ' class="pb-block pb-section pb-sec-' . htmlspecialchars($pbKey, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-pb-id="' . htmlspecialchars((string) $pbBlk['id'], ENT_QUOTES, 'UTF-8') . '" data-pb-type="section"'
        . ' data-pb-key="' . htmlspecialchars($pbKey, ENT_QUOTES, 'UTF-8') . '"';
    foreach ($pbBlk['style'] as $pbSk => $pbSv) {
        $pbAttr .= ' data-pb-' . htmlspecialchars(str_replace('_', '-', (string) $pbSk), ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $pbSv, ENT_QUOTES, 'UTF-8') . '"';
    }
    if (!empty($pbBlk['hidden'])) {
        $pbAttr .= ' data-pb-hidden="1"';
    }
    if ($pbHtml === '') {
        $pbAttr .= ' data-pb-empty="1"';
    }
    echo '  <div' . $pbAttr . '>';
    if ($pbHtml !== '') {
        echo $pbHtml;
    } else {
        $pbLink = url($pbBoLinks[$pbKey] ?? 'back-office/community/presentation');
        echo '<div class="pb-ghost"><strong>' . htmlspecialchars(PublicPageBuilder::SECTIONS[$pbKey], ENT_QUOTES, 'UTF-8') . '</strong>'
            . '<span>Section vide pour l’instant : elle s’affichera dès qu’elle aura du contenu.</span>'
            . '<a href="' . htmlspecialchars($pbLink, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Renseigner dans le back-office</a></div>';
    }
    echo "</div>\n";
}
if ($pbFlowOpen) {
    echo "  </div>\n";
}
unset($pbBlk, $pbBlock, $pbIsSection, $pbKey, $pbHtml, $pbFull, $pbAttr, $pbSk, $pbSv, $pbLink, $pbFlowOpen);
