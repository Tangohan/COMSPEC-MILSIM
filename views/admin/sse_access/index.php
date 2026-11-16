<?php
declare(strict_types=1);

/**
 * Back-office › Accès renseignement : centre de gestion des accès au renseignement. Bande d'en-tête (titre, une phrase,
 * échelle des habilitations, actions), puis huit onglets : vue d'ensemble, personnels, documents, droits SSE et portail,
 * codes émis, délivrance, niveaux de diffusion, journal. Formulaires en fenêtres, fiche d'un membre en tiroir.
 * Composants : back-office-bands.css (.bo-*) et back-office-sse-access.css (.sseacc-* existantes, .sseh-* du centre).
 */

use App\Controllers\Admin\AdminSseAccessController;
use App\Core\Csrf;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseAccessHub;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseRedactionService;
use App\Support\HtmlTranslator;
use App\Support\SseAccessCatalog;
use App\Support\UtcDateTime;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $codes */
/** @var list<array<string,mixed>> $cases */
/** @var list<array<string,mixed>> $actionLog */
$codes = is_array($codes ?? null) ? $codes : [];
$cases = is_array($cases ?? null) ? $cases : [];
$actionLog = is_array($actionLog ?? null) ? $actionLog : [];
$issuedPlain = isset($issuedPlain) && is_string($issuedPlain) && $issuedPlain !== '' ? $issuedPlain : null;
$classificationLabels = is_array($classificationLabels ?? null) ? $classificationLabels : SseCaseRepository::CLASSIFICATION_LABELS;
$canManage = !empty($canManage);
$canPeople = !empty($canPeople);
$canWrite = $canPeople && !empty($canWrite);
$canFiles = !empty($canFiles);
$selfId = (int) ($selfId ?? 0);
$maxLevel = isset($maxLevel) && isset(SseRedactionService::LEVELS[(string) $maxLevel]) ? (string) $maxLevel : SseCaseRepository::CLASS_INTERNAL;
$maxRank = SseRedactionService::levelRank($maxLevel);
$clearanceOrigin = trim((string) ($clearanceOrigin ?? ''));
$tabs = is_array($tabs ?? null) && $tabs !== [] ? array_values($tabs) : array_values(array_diff(AdminSseAccessController::TABS, $canPeople ? [] : AdminSseAccessController::TABS_MANAGE));
$activeTab = in_array($activeTab ?? '', $tabs, true) ? (string) $activeTab : $tabs[0];
$people = is_array($people ?? null) ? $people : null;
$allPeople = $people['rows'] ?? [];
$peopleRows = is_array($peopleRows ?? null) ? $peopleRows : $allPeople;
$filters = is_array($filters ?? null) ? $filters : [];
$overview = is_array($overview ?? null) ? $overview : SseAccessHub::overview($canPeople ? $allPeople : null, $codes, $actionLog, time());
$member = is_array($member ?? null) ? $member : null;
$grantable = is_array($grantable ?? null) ? $grantable : [];
$sections = is_array($sections ?? null) ? $sections : [];
$board = is_array($board ?? null) ? $board : [];
$clearanceRoles = is_array($clearanceRoles ?? null) ? $clearanceRoles : [];
$documents = is_array($documents ?? null) ? $documents : SseAccessHub::documents(0);
$consultations = is_array($consultations ?? null) ? $consultations : null;
$caseLock = $caseLock ?? null;
$workingRedaction = $workingRedaction ?? null;
$casesByLevel = is_array($casesByLevel ?? null) ? $casesByLevel : AdminSseAccessController::casesByLevel($cases);
$lockedForMe = (int) ($lockedForMe ?? 0);
$journal = is_array($journal ?? null) ? $journal : null;
if ($journal === null) {
    // Sans journal unifié (appel direct de la vue) : le journal des codes seul.
    $journal = [];
    foreach ($actionLog as $row) {
        $raw = (string) ($row['created_at'] ?? '');
        $cid0 = (int) ($row['code_id'] ?? 0);
        $journal[] = ['ts' => $raw !== '' ? (int) strtotime($raw) : (int) ($row['ts'] ?? 0), 'action' => (string) ($row['event_type'] ?? ''), 'subject' => '', 'code_id' => $cid0, 'actor' => (string) ($row['actor'] ?? ''), 'detail' => (string) ($row['detail'] ?? '')];
    }
}
$can = static fn (string $slug): bool => function_exists('can') && can($slug);
$canEditRights = $can('atak.sse.grant') || $can('admin.access');
$canSeeAudit = $can('admin.organization') || $can('admin.access');
$pageUrl = url(AdminSseAccessController::BASE);
$tabUrl = static fn (string $tab, array $q = []): string => $pageUrl . '?' . http_build_query(['onglet' => $tab] + $q);

// États des codes : compteurs des filtres.
$statusCounts = ['active' => 0, 'expired' => 0, 'exhausted' => 0, 'revoked' => 0];
foreach ($codes as $c) {
    $st = (string) ($c['status'] ?? (!empty($c['active']) ? 'active' : 'expired'));
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
}
$statusLabels = ['active' => 'Valide', 'expired' => 'Expiré', 'exhausted' => 'Épuisé', 'revoked' => 'Révoqué'];

// Dernier usage de chaque code, lu dans le journal récent (le plus récent d'abord).
$lastUse = [];
$codeLabels = [];
foreach ($codes as $c) {
    $codeLabels[(int) ($c['id'] ?? 0)] = (string) ($c['label'] ?? '');
}
$fmtLogTime = static function (array $row): string {
    $raw = (string) ($row['created_at'] ?? '');
    $ts = $raw !== '' ? strtotime($raw) : (int) ($row['ts'] ?? 0);

    return $ts ? date('d/m/Y H:i', $ts) : ($raw !== '' ? $raw : '—');
};
$fmtDate = static function (?string $raw, bool $time = true): string {
    $ts = $raw !== null && $raw !== '' ? strtotime($raw) : false;

    return $ts ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '—';
};
foreach ($actionLog as $row) {
    $cid = (int) ($row['code_id'] ?? 0);
    if (($row['event_type'] ?? '') === 'redeem' && $cid > 0 && !isset($lastUse[$cid])) {
        $lastUse[$cid] = $fmtLogTime($row);
    }
}

$eventLabels = [
    'issue' => 'Code délivré',
    'redeem' => 'Code utilisé',
    'revoke' => 'Code révoqué',
    'confidentiality_ack' => 'Engagement de confidentialité',
    'member_clearance.set' => 'Habilitation inscrite',
    'member_clearance.remove' => 'Habilitation retirée',
    'rule.allow' => 'Accès accordé',
    'rule.deny' => 'Accès restreint',
    'rule.revoke' => 'Dérogation retirée',
];
$eventTone = ['revoke' => 'revoke', 'issue' => 'issue', 'redeem' => 'redeem', 'member_clearance.set' => 'issue', 'member_clearance.remove' => 'revoke', 'rule.allow' => 'issue', 'rule.deny' => 'revoke', 'rule.revoke' => 'revoke'];
$detailLabels = ['session_invite' => 'Session invité', 'session_membre' => 'Session membre'];
$journalRows = [];
foreach ($journal as $row) {
    if ((string) ($row['subject'] ?? '') === '' && (int) ($row['code_id'] ?? 0) > 0) {
        $row['subject'] = $codeLabels[(int) $row['code_id']] ?? '';
    }
    $detail = (string) ($row['detail'] ?? '');
    $detail = $detailLabels[$detail] ?? (str_starts_with($detail, 'version:') ? '' : $detail);
    if ($detail === (string) ($row['subject'] ?? '')) {
        $detail = '';
    }
    $journalRows[] = ['detail' => $detail] + $row;
}
$journalTypes = [];
$journalSubjects = [];
foreach ($journalRows as $row) {
    $journalTypes[(string) $row['action']] = true;
    if ((string) $row['subject'] !== '') {
        $journalSubjects[(string) $row['subject']] = true;
    }
}
ksort($journalSubjects);

// Échelle de diffusion : du plus large au plus restreint, avec ce que chaque niveau découvre.
$levels = SseRedactionService::LEVELS;
asort($levels);
$levelShort = [
    SseCaseRepository::CLASS_INTERNAL => 'CUI',
    SseCaseRepository::CLASS_COMMAND => 'C',
    SseCaseRepository::CLASS_CONFIDENTIAL => 'S',
    SseCaseRepository::CLASS_RESTRICTED => 'TS',
];
$levelReads = [
    SseCaseRepository::CLASS_INTERNAL => 'Le contenu courant des dossiers ; tout élément sensible reste caviardé.',
    SseCaseRepository::CLASS_COMMAND => 'Ajoute les lieux et les horaires précis de recueil.',
    SseCaseRepository::CLASS_CONFIDENTIAL => 'Ajoute les identités et la biométrie.',
    SseCaseRepository::CLASS_RESTRICTED => 'Tout, y compris la source de chaque renseignement.',
];
$levelHolders = [
    SseCaseRepository::CLASS_INTERNAL => 'Invités sans habilitation',
    SseCaseRepository::CLASS_COMMAND => 'Membres ayant accès au portail',
    SseCaseRepository::CLASS_CONFIDENTIAL => 'Gestion des dossiers',
    SseCaseRepository::CLASS_RESTRICTED => 'Administration du portail, droit d’octroi',
];
$levelLabel = static fn (string $code): string => (string) ($classificationLabels[$code] ?? ($code !== '' ? $code : '—'));
// Nom court d'un niveau, sans le sigle déjà porté par la pastille (« Secret », « Information non classifiée contrôlée »).
$levelName = static fn (string $code): string => trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $levelLabel($code)));
$chip = static fn (string $lvl): string => '<span class="sseacc-level sseacc-level--' . $h($levelShort[$lvl] ?? 'CUI') . '">' . $h($levelShort[$lvl] ?? 'CUI') . '</span>';

$whyLabels = ['command' => 'Commandement', 'role' => 'Rôle', 'code' => 'Code temporaire', 'none' => 'Aucun accès'];
$whyLines = ['command' => 'Attribution des accès', 'role' => 'Accès au portail par son rôle', 'code' => 'Code encore valide', 'none' => 'Ni rôle ni code valide'];
$stateLabels = ['active' => 'Actif', 'restricted' => 'Restreint', 'none' => 'Sans accès'];
$sourceLabels = ['explicit' => 'Habilitation explicite', 'role' => 'Reportée du rôle', 'profile' => 'Fiche opérateur', 'floor' => 'Aucune habilitation'];
$flagLabels = [
    'sans-acces' => 'Sans accès',
    'restreint' => 'Restreints',
    'par-code' => 'Accès par code',
    'echeance' => 'Échéance sous 7 jours',
    'echue' => 'Dérogation échue',
    'sans-habilitation' => 'Sans habilitation',
];
$flagCounts = array_fill_keys(array_keys($flagLabels), 0);
$units = [];
foreach ($allPeople as $p) {
    foreach ($p['flags'] as $f) {
        if (isset($flagCounts[$f])) {
            $flagCounts[$f]++;
        }
    }
    if ($p['unit'] !== '') {
        $units[$p['unit']] = true;
    }
}
ksort($units);
$shownIds = array_flip(array_map(static fn (array $p): int => (int) $p['user_id'], $peopleRows));
$catKeys = array_keys(SseRedactionService::CATEGORIES);

$ttlOptions = [1, 2, 4, 8, 12, 24, 48, 72];
$sessionOptions = [60 => '1 heure', 120 => '2 heures', 240 => '4 heures', 480 => '8 heures', 1440 => '24 heures'];
$defaultLabel = function_exists('locale') && locale() === 'en' ? 'Temporary access' : 'Accès temporaire';

$tabMeta = [
    'ensemble' => ['label' => 'Vue d’ensemble', 'count' => null],
    'personnels' => ['label' => 'Personnels', 'count' => $people !== null ? count($allPeople) : null],
    'documents' => ['label' => 'Documents', 'count' => count($documents)],
    'sse' => ['label' => 'Droits SSE', 'count' => null],
    'codes' => ['label' => 'Codes émis', 'count' => count($codes)],
    'delivrer' => ['label' => 'Délivrer un code', 'count' => null],
    'niveaux' => ['label' => 'Niveaux de diffusion', 'count' => count($levels)],
    'journal' => ['label' => 'Journal', 'count' => count($journalRows)],
];
$index = [];
foreach ($tabs as $i => $t) {
    $index[$t] = sprintf('/ %02d', $i + 1);
}
$panelOpen = static fn (string $key): string => ' role="tabpanel" id="sseacc-panel-' . $key . '" aria-labelledby="sseacc-tab-' . $key . '" tabindex="0"' . ($key === $activeTab ? '' : ' hidden');
// Traduction des textes d'attributs que HtmlTranslator ne relit pas (fenêtre de confirmation).
$tx = static fn (string $fr): string => function_exists('locale') && locale() === 'en' ? HtmlTranslator::translateText($fr) : $fr;
$levelTotal = max(1, array_sum($overview['levels']));
$closeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
?>
<div class="bo-page sseacc sseh" data-sseacc>
    <header class="bo-band bo-band--hero bo-band--sky sseacc-hero sseh-hero" aria-labelledby="ath-page-title">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__who">
                <h1 class="bo-band__title" id="ath-page-title">Accès renseignement</h1>
                <p class="bo-band__line">Le centre de gestion des accès au renseignement : qui entre au portail, à quel niveau, sur quels documents et jusqu’à quand.</p>
                <div class="bo-band__actions sseacc-hero__actions">
                    <a class="bo-band__btn bo-band__btn--solid" href="<?= $h(url('atak/sse')) ?>">Ouvrir le portail <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                    <a class="bo-band__btn" href="<?= $h(url('atak/sse/commandement')) ?>">Entrer comme commandement</a>
                    <?php if ($canEditRights): ?>
                    <a class="sseacc-hero__link" href="<?= $h(url('atak/sse/droits')) ?>">Droits d’accès par section</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sseh-hero__side">
                <dl class="sseacc-hero__figures" aria-label="Chiffres clés">
                    <?php if ($overview['members'] !== null): ?>
                    <div class="bo-figure"><dt>Membres avec accès</dt><dd><strong><?= (int) $overview['with'] ?></strong></dd></div>
                    <?php endif; ?>
                    <div class="bo-figure<?= $overview['codes_valid'] > 0 ? ' is-hot' : '' ?>"><dt>Codes valides</dt><dd><strong><?= (int) $overview['codes_valid'] ?></strong></dd></div>
                    <div class="bo-figure"><dt>Votre plafond</dt><dd><strong><?= $h($levelShort[$maxLevel] ?? 'CUI') ?></strong></dd></div>
                </dl>
                <?php if ($overview['members'] !== null && $overview['members'] > 0): ?>
                <div class="sseh-ladder" role="img" aria-label="<?= $h($tx('Répartition des habilitations')) ?>">
                    <?php foreach ($levels as $lvl => $rank): $n = (int) ($overview['levels'][$lvl] ?? 0); ?>
                    <span class="sseh-ladder__seg sseh-tone--<?= $h($levelShort[$lvl]) ?>" style="--sseh-share: <?= max(0.04, $n / $levelTotal) ?>"><b><?= $h($levelShort[$lvl]) ?></b> <span class="sseacc-num"><?= $n ?></span></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="sseacc-tabs sseh-tabs" role="tablist" aria-label="Sections de la page">
        <?php foreach ($tabs as $key): $meta = $tabMeta[$key]; $on = $key === $activeTab; ?>
        <a class="sseacc-tabs__tab" role="tab" id="sseacc-tab-<?= $h($key) ?>" href="<?= $h($tabUrl($key)) ?>"
           aria-controls="sseacc-panel-<?= $h($key) ?>" aria-selected="<?= $on ? 'true' : 'false' ?>" tabindex="<?= $on ? '0' : '-1' ?>" data-sseacc-tab="<?= $h($key) ?>">
            <span><?= $h($meta['label']) ?></span>
            <?php if ($meta['count'] !== null): ?><span class="sseacc-tabs__count"><?= (int) $meta['count'] ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php /* ——— Vue d'ensemble ——— */ ?>
    <section class="bo-section sseacc-panel"<?= $panelOpen('ensemble') ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['ensemble']) ?></span>
            <h2 class="bo-section__title">Vue d’ensemble</h2>
            <p class="bo-section__desc">Ce qui demande une décision maintenant, puis l’état des accès de la communauté.</p>
        </header>
        <div class="sseh-grid">
            <?php if ($overview['members'] !== null): ?>
            <a class="sseh-tile" href="<?= $h($tabUrl('personnels')) ?>"><strong class="sseh-tile__v"><?= (int) $overview['with'] ?></strong><span class="sseh-tile__l">Membres avec accès</span></a>
            <a class="sseh-tile<?= $overview['without'] > 0 ? ' is-warn' : '' ?>" href="<?= $h($tabUrl('personnels', ['etat' => 'sans-acces'])) ?>"><strong class="sseh-tile__v"><?= (int) $overview['without'] ?></strong><span class="sseh-tile__l">Sans accès</span></a>
            <?php endif; ?>
            <a class="sseh-tile" href="<?= $h($tabUrl('codes')) ?>"><strong class="sseh-tile__v"><?= (int) $overview['codes_valid'] ?></strong><span class="sseh-tile__l">Codes valides</span></a>
            <?php if ($overview['members'] !== null): ?>
            <a class="sseh-tile<?= $overview['members_soon'] > 0 ? ' is-warn' : '' ?>" href="<?= $h($tabUrl('personnels', ['etat' => 'echeance'])) ?>"><strong class="sseh-tile__v"><?= (int) $overview['members_soon'] ?></strong><span class="sseh-tile__l">Échéances sous 7 jours</span></a>
            <?php else: ?>
            <a class="sseh-tile" href="<?= $h($tabUrl('journal')) ?>"><strong class="sseh-tile__v"><?= (int) $overview['recent'] ?></strong><span class="sseh-tile__l">Événements sur 7 jours</span></a>
            <?php endif; ?>

            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-alerts-title">
                <h3 class="sseh-card__title" id="sseh-alerts-title">À traiter</h3>
                <p class="sseh-card__desc">Chaque alerte ouvre la liste concernée, déjà filtrée.</p>
                <?php if ($overview['alerts'] === []): ?>
                <p class="sseh-card__empty">Rien à traiter : aucun accès n’arrive à échéance et aucun code n’attend.</p>
                <?php else: ?>
                <ul class="sseh-alerts">
                    <?php foreach ($overview['alerts'] as $a):
                        $target = match ($a['key']) {
                            'echeance' => $tabUrl('personnels', ['etat' => 'echeance']),
                            'code-sans-habilitation' => $tabUrl('personnels', ['etat' => 'par-code', 'niveau' => SseCaseRepository::CLASS_INTERNAL]),
                            'restreint' => $tabUrl('personnels', ['etat' => 'restreint']),
                            'echue' => $tabUrl('personnels', ['etat' => 'echue']),
                            default => $tabUrl('codes'),
                        };
                        $text = match ($a['key']) {
                            'echeance' => 'membre(s) : une dérogation arrive à échéance sous 7 jours',
                            'code-sans-habilitation' => 'membre(s) sans habilitation entrent par un code encore valide',
                            'restreint' => 'membre(s) ont un accès restreint par une dérogation',
                            'echue' => 'membre(s) gardent une dérogation échue à nettoyer',
                            default => 'code(s) valide(s) jamais utilisé(s)',
                        };
                        ?>
                    <li><a class="sseh-alert sseh-alert--<?= $h($a['tone']) ?>" href="<?= $h($target) ?>"><strong class="sseh-alert__n"><?= (int) $a['n'] ?></strong> <span class="sseh-alert__text"><?= $h($text) ?></span><span class="sseh-alert__go" aria-hidden="true">→</span></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </article>

            <?php if ($overview['members'] !== null): ?>
            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-levels-title">
                <h3 class="sseh-card__title" id="sseh-levels-title">Habilitations par niveau</h3>
                <p class="sseh-card__desc">Plafond de lecture de chaque membre listé, de CUI à Très secret.</p>
                <ul class="sseh-bars">
                    <?php foreach ($levels as $lvl => $rank): $n = (int) ($overview['levels'][$lvl] ?? 0); ?>
                    <li class="sseh-bars__row">
                        <a class="sseh-bars__label" href="<?= $h($tabUrl('personnels', ['niveau' => $lvl])) ?>"><?= $chip($lvl) ?> <span><?= $h($levelLabel($lvl)) ?></span></a>
                        <span class="sseh-bars__track" aria-hidden="true"><span class="sseh-bars__fill sseh-tone--<?= $h($levelShort[$lvl]) ?>" style="--sseh-share: <?= $n / $levelTotal ?>"></span></span>
                        <span class="sseh-bars__n sseacc-num"><?= $n ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </article>
            <?php endif; ?>

            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-recent-title">
                <h3 class="sseh-card__title" id="sseh-recent-title">Derniers événements</h3>
                <p class="sseh-card__desc">Codes, habilitations et dérogations, du plus récent au plus ancien.</p>
                <?php if ($journalRows === []): ?>
                <p class="sseh-card__empty">Aucun événement d’accès pour l’instant.</p>
                <?php else: ?>
                <ol class="sseh-feed">
                    <?php foreach (array_slice($journalRows, 0, 5) as $row): $type = (string) $row['action']; ?>
                    <li class="sseh-feed__it">
                        <span class="sseacc-event sseacc-event--<?= $h($eventTone[$type] ?? 'other') ?>"><?= $h($eventLabels[$type] ?? 'Action') ?></span>
                        <span class="sseh-feed__who"><?= $h($row['subject'] !== '' ? $row['subject'] : ($row['actor'] !== '' ? $row['actor'] : '—')) ?></span>
                        <span class="sseh-feed__when sseacc-num"><?= $h($row['ts'] > 0 ? date('d/m H:i', (int) $row['ts']) : '—') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <a class="sseacc-link" href="<?= $h($tabUrl('journal')) ?>">Tout le journal</a>
                <?php endif; ?>
            </article>

            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-short-title">
                <h3 class="sseh-card__title" id="sseh-short-title">Raccourcis</h3>
                <p class="sseh-card__desc">Les pages qui règlent le reste, sans quitter ce centre plus que nécessaire.</p>
                <ul class="sseh-links">
                    <li><a href="<?= $h(url('atak/sse')) ?>">Portail du renseignement</a></li>
                    <?php if ($canEditRights): ?><li><a href="<?= $h(url('atak/sse/droits')) ?>">Droits d’accès par section</a></li><?php endif; ?>
                    <li><a href="<?= $h(url('atak/sse/fiches/gestion')) ?>">Gestion des fiches</a></li>
                    <?php if ($canFiles): ?><li><a href="<?= $h(url('back-office/renseignement/fichiers')) ?>">Fichiers SSE</a></li><?php endif; ?>
                    <?php if ($canWrite): ?><li><a href="<?= $h(function_exists('effectifs_workspace_url') ? effectifs_workspace_url() : url('back-office')) ?>">Effectifs</a></li><?php endif; ?>
                    <?php if ($canSeeAudit): ?><li><a href="<?= $h(url('back-office/audit')) ?>">Journal d’audit complet</a></li><?php endif; ?>
                </ul>
            </article>
        </div>
    </section>

    <?php if ($canPeople): /* ——— Personnels ——— */ ?>
    <section class="bo-section sseacc-panel sseh-people"<?= $panelOpen('personnels') ?> data-sseh-people>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['personnels']) ?></span>
            <h2 class="bo-section__title">Personnels</h2>
            <p class="bo-section__desc">Chaque membre, son entrée au portail, son habilitation et ses droits ; ouvrez une fiche pour le détail et l’historique.</p>
        </header>
        <?php if ($allPeople === []): ?>
            <div class="sseacc-empty">
                <p class="sseacc-empty__title">Aucun membre à afficher</p>
                <p class="sseacc-empty__line">L’annuaire ne vous montre aucun membre actif de la communauté.</p>
            </div>
        <?php else: ?>
            <form class="sseh-filters" method="get" action="<?= $h($pageUrl) ?>" role="search" data-sseh-filter>
                <input type="hidden" name="onglet" value="personnels">
                <label class="sseacc-search">
                    <span class="sseacc-sr">Rechercher un membre</span>
                    <input type="search" name="q" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Nom, indicatif ou unité" autocomplete="off" data-sseh-f="q">
                </label>
                <?php if ($units !== []): ?>
                <label class="sseacc-select">
                    <span class="sseacc-sr">Unité</span>
                    <select name="unite" data-sseh-f="unit">
                        <option value="">Toutes les unités</option>
                        <?php foreach (array_keys($units) as $u): ?>
                        <option value="<?= $h($u) ?>"<?= ($filters['unite'] ?? '') === $u ? ' selected' : '' ?>><?= $h($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <label class="sseacc-select">
                    <span class="sseacc-sr">Habilitation</span>
                    <select name="niveau" data-sseh-f="level">
                        <option value="">Toutes les habilitations</option>
                        <?php foreach ($levels as $lvl => $rank): ?>
                        <option value="<?= $h($lvl) ?>"<?= ($filters['niveau'] ?? '') === $lvl ? ' selected' : '' ?>><?= $h($levelLabel($lvl)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="sseacc-select">
                    <span class="sseacc-sr">État</span>
                    <select name="etat" data-sseh-f="flag">
                        <option value="">Tous les états</option>
                        <?php foreach ($flagLabels as $f => $fl): ?>
                        <option value="<?= $h($f) ?>"<?= ($filters['etat'] ?? '') === $f ? ' selected' : '' ?>><?= $h($fl) ?> (<?= (int) $flagCounts[$f] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="sseacc-btn" data-sseh-submit>Filtrer</button>
            </form>
            <?php if ($canWrite): ?>
            <div class="sseh-bulk" data-sseh-bulk>
                <p class="sseh-bulk__count" role="status" aria-live="polite"><span data-sseh-count>0</span> <span>membre(s) sélectionné(s)</span></p>
                <div class="sseh-bulk__acts">
                    <button type="button" class="sseacc-btn" data-bo-dialog-open="sseh-clearance" data-sseh-bulk-open aria-haspopup="dialog" disabled>Changer l’habilitation</button>
                    <button type="button" class="sseacc-btn" data-bo-dialog-open="sseh-access" data-sseh-bulk-open data-sseh-effect="allow" aria-haspopup="dialog" disabled>Accorder un accès</button>
                    <button type="button" class="sseacc-btn sseacc-btn--danger" data-bo-dialog-open="sseh-access" data-sseh-bulk-open data-sseh-effect="deny" aria-haspopup="dialog" disabled>Restreindre</button>
                </div>
            </div>
            <?php endif; ?>
            <p class="sseh-shown" role="status" aria-live="polite"><span data-sseh-shown><?= count($peopleRows) ?></span> <span>sur</span> <span><?= count($allPeople) ?></span> <span>membre(s) affiché(s)</span></p>
            <div class="sseacc-tablewrap">
                <table class="sseacc-table sseh-ptable">
                    <thead>
                    <tr>
                        <th scope="col"><?php if ($canWrite): ?><label class="sseh-check"><input type="checkbox" data-sseh-all><span class="sseacc-sr">Tout sélectionner</span></label><?php endif; ?> Membre</th>
                        <th scope="col">Portail</th>
                        <th scope="col">Habilitation</th>
                        <th scope="col">Droits par section</th>
                        <th scope="col">Dernière entrée</th>
                        <th scope="col">État</th>
                        <th scope="col"><span class="sseacc-sr">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allPeople as $p):
                        $uid = (int) $p['user_id'];
                        $lvl = (string) $p['level'];
                        $ficheUrl = $tabUrl('personnels', ['membre' => $uid]);
                        ?>
                        <tr data-sseh-row data-id="<?= $uid ?>" data-name="<?= $h($p['label']) ?>" data-unit="<?= $h($p['unit']) ?>" data-level="<?= $h($lvl) ?>" data-flags="<?= $h(implode(' ', $p['flags'])) ?>" data-text="<?= $h(mb_strtolower($p['label'] . ' ' . $p['unit'])) ?>" class="sseh-row sseh-tone--<?= $h($levelShort[$lvl] ?? 'CUI') ?>"<?= isset($shownIds[$uid]) ? '' : ' hidden' ?>>
                            <td>
                                <div class="sseh-who">
                                    <?php if ($canWrite && !$p['self']): ?><label class="sseh-check"><input type="checkbox" value="<?= $uid ?>" data-sseh-pick><span class="sseacc-sr">Sélectionner <?= $h($p['label']) ?></span></label><?php elseif ($canWrite): ?><span class="sseh-check sseh-check--ph" aria-hidden="true"></span><?php endif; ?>
                                    <div>
                                        <a class="sseh-who__name" href="<?= $h($ficheUrl) ?>" data-sseh-fiche="<?= $uid ?>"><?= $h($p['label']) ?></a>
                                        <span class="sseacc-table__sub"><?= $h($p['unit'] !== '' ? $p['unit'] : 'Sans unité') ?><?php if ($p['self']): ?> · <span>vous</span><?php endif; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="sseacc-cell-label" aria-hidden="true">Portail</span>
                                <span class="sseh-why sseh-why--<?= $h($p['why']) ?>"><?= $h($whyLabels[$p['why']]) ?></span>
                                <span class="sseacc-table__sub"><?= $h($whyLines[$p['why']]) ?></span>
                            </td>
                            <td>
                                <span class="sseacc-cell-label" aria-hidden="true">Habilitation</span>
                                <span class="sseh-lvl"><?= $chip($lvl) ?> <span><?= $h($levelName($lvl)) ?></span></span>
                                <span class="sseacc-table__sub"><?= $h($sourceLabels[$p['level_source']] ?? '') ?></span>
                            </td>
                            <td>
                                <span class="sseacc-cell-label" aria-hidden="true">Droits par section</span>
                                <?php if (!$p['entry']): ?>
                                <span class="sseacc-table__sub">Aucun sans entrée au portail</span>
                                <?php else: ?>
                                <span class="sseh-rights"><span><b class="sseacc-num"><?= (int) $p['rights']['voir'] ?></b> voit</span><span><b class="sseacc-num"><?= (int) $p['rights']['ecrire'] ?></b> écrit</span><span><b class="sseacc-num"><?= (int) $p['rights']['gerer'] ?></b> gère</span></span>
                                <span class="sseacc-table__sub">sur <span class="sseacc-num"><?= (int) $people['sections'] ?></span> sections<?php if ($p['rules'] > 0): ?> · <span class="sseacc-num"><?= (int) $p['rules'] ?></span> dérogation(s)<?php endif; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="sseacc-cell-label" aria-hidden="true">Dernière entrée</span>
                                <span class="sseacc-num"><?= $h($fmtDate($p['last_entry'])) ?></span>
                                <?php if ($p['next_end'] !== null): ?><span class="sseacc-table__sub<?= $p['soon'] > 0 ? ' sseh-soon' : '' ?>">Échéance <span class="sseacc-num"><?= $h($fmtDate($p['next_end'], false)) ?></span></span><?php endif; ?>
                            </td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">État</span><span class="sseacc-state sseh-state--<?= $h($p['state']) ?>"><?= $h($stateLabels[$p['state']]) ?></span></td>
                            <td class="sseacc-table__act">
                                <div class="sseh-acts">
                                    <a class="sseacc-btn" href="<?= $h($ficheUrl) ?>" data-sseh-fiche="<?= $uid ?>">Fiche</a>
                                    <?php if ($canWrite && $p['may_change']): ?>
                                    <button type="button" class="sseacc-btn" data-bo-dialog-open="sseh-clearance" data-sseh-member="<?= $uid ?>" aria-haspopup="dialog">Habilitation</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="sseacc-noresult" data-sseh-noresult<?= $peopleRows === [] ? '' : ' hidden' ?>>Aucun membre ne correspond à ces filtres.</p>
        <?php endif; ?>
        <?php if (($people['anonymized'] ?? 0) > 0): ?>
        <p class="sseacc-note"><span class="sseacc-num"><?= (int) $people['anonymized'] ?></span> <span>membre(s) protégé(s) par l’annuaire ne sont ni nommés ni listés ici.</span></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php /* ——— Documents ——— */ ?>
    <section class="bo-section sseacc-panel"<?= $panelOpen('documents') ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['documents']) ?></span>
            <h2 class="bo-section__title">Documents</h2>
            <div class="bo-section__aside"><a class="sseacc-btn" href="<?= $h(url('atak/sse/fiches/gestion')) ?>">Régler par modèle</a></div>
            <p class="bo-section__desc">Niveau à partir duquel chaque rubrique d’un document émis se lit en clair ; en dessous, elle est caviardée.</p>
        </header>
        <div class="sseacc-tablewrap">
            <table class="sseacc-table sseh-dtable">
                <thead>
                <tr>
                    <th scope="col">Document</th>
                    <?php foreach ($catKeys as $cat): ?><th scope="col"><?= $h(SseRedactionService::categoryLabel($cat)) ?></th><?php endforeach; ?>
                    <th scope="col">Pour l’émettre</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($documents as $d): ?>
                <tr>
                    <th scope="row"><strong class="sseacc-table__main"><?= $h($d['label']) ?></strong><span class="sseacc-table__sub"><?= $h($d['hint']) ?></span></th>
                    <?php foreach ($catKeys as $cat): $lvl = (string) ($d['levels'][$cat] ?? SseRedactionService::requiredLevel($cat)); ?>
                    <td class="sseh-cell sseh-tone--<?= $h($levelShort[$lvl] ?? 'C') ?><?= SseRedactionService::levelRank($lvl) > $maxRank ? ' is-above' : '' ?>">
                        <span class="sseacc-cell-label" aria-hidden="true"><?= $h(SseRedactionService::categoryLabel($cat)) ?></span>
                        <span class="sseh-cell__v"><?= $h($levelShort[$lvl] ?? '') ?></span><span class="sseacc-sr"><?= $h($levelLabel($lvl)) ?></span>
                    </td>
                    <?php endforeach; ?>
                    <td>
                        <span class="sseacc-cell-label" aria-hidden="true">Pour l’émettre</span>
                        <?php if ($d['needs'] === []): ?><span class="sseacc-table__sub">Droits de l’avis de recherche</span><?php else: ?>
                        <ul class="sseh-needs"><?php foreach ($d['needs'] as $n): ?><li><?= $h($n['section']) ?> · <?= $h($n['level']) ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="sseh-sealed">
                    <th scope="row"><strong class="sseacc-table__main">Images des membres</strong><span class="sseacc-table__sub">Portraits, identités et couvertures de service, papiers et clichés des membres.</span></th>
                    <td class="sseh-cell sseh-tone--TS" colspan="<?= count($catKeys) ?>"><span class="sseacc-cell-label" aria-hidden="true">Lecture</span><span class="sseh-cell__row"><span class="sseh-cell__v">TS</span><span class="sseh-cell__line">Fermé par défaut : titulaire, hiérarchie ou accord explicite, en plus de l’habilitation <?= $h(SseMemberImageGate::LABEL) ?>.</span></span></td>
                    <td><span class="sseacc-cell-label" aria-hidden="true">Réglage</span><?php if ($canEditRights): ?><a class="sseacc-link" href="<?= $h(url('atak/sse/droits') . '?section=identites_service') ?>">Accords de la section</a><?php else: ?><span class="sseacc-table__sub">Droits d’accès par section</span><?php endif; ?></td>
                </tr>
                </tbody>
            </table>
        </div>
        <p class="sseacc-note">Une case marquée au-dessus de votre plafond (<?= $h($levelShort[$maxLevel] ?? 'CUI') ?>) vous reste caviardée, comme à tout lecteur de même niveau.</p>

        <div class="sseh-grid sseh-grid--docs">
            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-cases-title">
                <h3 class="sseh-card__title" id="sseh-cases-title">Dossiers par classification</h3>
                <p class="sseh-card__desc">Un dossier se classe à sa création ; le verrou d’ouverture se règle depuis le registre.</p>
                <ul class="sseh-bars">
                    <?php $caseTotal = max(1, array_sum($casesByLevel)); foreach ($levels as $lvl => $rank): $n = (int) ($casesByLevel[$lvl] ?? 0); ?>
                    <li class="sseh-bars__row">
                        <span class="sseh-bars__label"><?= $chip($lvl) ?> <span><?= $h($levelLabel($lvl)) ?></span></span>
                        <span class="sseh-bars__track" aria-hidden="true"><span class="sseh-bars__fill sseh-tone--<?= $h($levelShort[$lvl]) ?>" style="--sseh-share: <?= $n / $caseTotal ?>"></span></span>
                        <span class="sseh-bars__n sseacc-num"><?= $n ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($lockedForMe > 0): ?><p class="sseh-card__foot"><span class="sseacc-num"><?= $lockedForMe ?></span> <span>dossier(s) au-dessus de votre habilitation.</span></p><?php endif; ?>
                <a class="sseacc-link" href="<?= $h(url('atak/sse/dossiers')) ?>">Registre des dossiers</a>
            </article>
            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-consult-title">
                <h3 class="sseh-card__title" id="sseh-consult-title">Dernières consultations des fichiers</h3>
                <p class="sseh-card__desc">Qui a interrogé les fichiers, d’où, et si la recherche a trouvé une fiche.</p>
                <?php if ($consultations === null): ?>
                <p class="sseh-card__empty">Réservé à la gestion des fichiers SSE.</p>
                <?php elseif ($consultations === []): ?>
                <p class="sseh-card__empty">Aucune consultation enregistrée.</p>
                <?php else: ?>
                <ol class="sseh-feed">
                    <?php foreach ($consultations as $q): ?>
                    <li class="sseh-feed__it">
                        <span class="sseacc-event<?= $q['hit'] ? ' sseacc-event--redeem' : '' ?>"><?= $h($q['type']) ?></span>
                        <span class="sseh-feed__who"><?= $h($q['operator'] !== '' ? $q['operator'] : '—') ?> <span class="sseacc-table__sub sseacc-mono"><?= $h($q['qrn']) ?> · <?= $h($q['source']) ?></span></span>
                        <span class="sseh-feed__when sseacc-num"><?= $h($fmtDate($q['created_at'])) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
                <?php if ($canFiles): ?><a class="sseacc-link" href="<?= $h(url('back-office/renseignement/fichiers/journal')) ?>">Journal des consultations</a><?php endif; ?>
            </article>
        </div>
    </section>

    <?php /* ——— Droits SSE et portail ——— */ ?>
    <section class="bo-section sseacc-panel"<?= $panelOpen('sse') ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['sse']) ?></span>
            <h2 class="bo-section__title">Droits SSE et portail</h2>
            <?php if ($canEditRights): ?><div class="bo-section__aside"><a class="sseacc-btn sseacc-btn--primary" href="<?= $h(url('atak/sse/droits')) ?>">Modifier les droits</a></div><?php endif; ?>
            <p class="bo-section__desc">Qui voit, écrit et gère chaque section du portail, en plus des droits d’origine des rôles.</p>
        </header>
        <?php if ($canPeople && $board !== []): ?>
        <div class="sseacc-tablewrap">
            <table class="sseacc-table sseh-btable">
                <thead>
                <tr>
                    <th scope="col">Section</th>
                    <?php foreach (SseAccessCatalog::SIMPLE_ACCESS as $acc => $def): ?><th scope="col"><?= $h($def['label']) ?></th><?php endforeach; ?>
                    <th scope="col"><span class="sseacc-sr">Lien</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($board as $row): ?>
                <tr>
                    <th scope="row"><strong class="sseacc-table__main"><?= $h($row['label']) ?></strong></th>
                    <?php foreach (SseAccessCatalog::SIMPLE_ACCESS as $acc => $def): $chips = $row['columns'][$acc] ?? []; ?>
                    <td>
                        <span class="sseacc-cell-label" aria-hidden="true"><?= $h($def['label']) ?></span>
                        <?php if (empty($row['applies'][$acc])): ?><span class="sseacc-table__sub">Sans objet</span>
                        <?php elseif ($chips === []): ?><span class="sseacc-table__sub">Droits d’origine</span>
                        <?php else: ?>
                        <ul class="sseh-chips">
                            <?php foreach (array_slice($chips, 0, 3) as $c): ?>
                            <li class="sseh-chip<?= $c['effect'] === SseAccessCatalog::EFFECT_DENY ? ' is-deny' : '' ?>"><span class="sseacc-sr"><?= $c['effect'] === SseAccessCatalog::EFFECT_DENY ? 'Refus :' : 'Accord :' ?></span> <?= $h($c['label']) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($chips) > 3): ?><li class="sseh-chip is-more">+<?= count($chips) - 3 ?></li><?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="sseacc-table__act"><?php if ($canEditRights): ?><a class="sseacc-link" href="<?= $h(url('atak/sse/droits') . '?section=' . rawurlencode((string) $row['key'])) ?>">Régler<span class="sseacc-sr"> : <?= $h($row['label']) ?></span></a><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($canPeople): ?>
        <div class="sseacc-empty"><p class="sseacc-empty__title">Aucune section visible</p><p class="sseacc-empty__line">Vos propres droits ne vous montrent aucune section du portail.</p></div>
        <?php endif; ?>

        <div class="sseh-grid sseh-grid--sse">
            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-settings-title">
                <h3 class="sseh-card__title" id="sseh-settings-title">Paramètres d’accès</h3>
                <p class="sseh-card__desc">Réglages de la communauté qui changent ce que chacun lit.</p>
                <dl class="sseh-facts">
                    <div><dt>Verrou d’ouverture des dossiers</dt><dd><?= $caseLock === null ? '—' : ($caseLock ? 'Armé' : 'Désarmé') ?></dd></div>
                    <div><dt>Caviardage des écrans de travail</dt><dd><?= $workingRedaction === null ? '—' : ($workingRedaction ? 'Armé' : 'Désarmé') ?></dd></div>
                    <div><dt>Codes temporaires</dt><dd>1 à 72 h, 1 à 10 usages</dd></div>
                    <div><dt>Votre plafond</dt><dd><?= $chip($maxLevel) ?> <?= $h($levelLabel($maxLevel)) ?></dd></div>
                    <div><dt>Images des membres</dt><dd><?= $h(SseMemberImageGate::LABEL) ?>, fermées par défaut</dd></div>
                </dl>
                <a class="sseacc-link" href="<?= $h(url('atak/sse/dossiers')) ?>">Régler depuis le registre des dossiers</a>
            </article>
            <article class="sseh-card sseh-card--wide" aria-labelledby="sseh-roles-title">
                <h3 class="sseh-card__title" id="sseh-roles-title">Rôles qui portent une habilitation</h3>
                <p class="sseh-card__desc">Niveaux d’accès qui ouvrent le portail ou reportent un plafond de lecture.</p>
                <?php if ($clearanceRoles === []): ?>
                <p class="sseh-card__empty">Aucun rôle de la communauté ne porte de droit SSE.</p>
                <?php else: ?>
                <ul class="sseh-roles">
                    <?php foreach ($clearanceRoles as $r): ?>
                    <li class="sseh-roles__it"><?= $chip((string) $r['level']) ?> <span class="sseh-roles__name"><?= $h($r['name']) ?></span> <span class="sseh-roles__n"><span class="sseacc-num"><?= (int) $r['members'] ?></span> <span>membre(s)</span></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if ($canWrite): ?><a class="sseacc-link" href="<?= $h(function_exists('effectifs_workspace_url') ? effectifs_workspace_url('roles') : url('back-office')) ?>">Rôles et accès</a><?php endif; ?>
            </article>
        </div>
    </section>

    <?php /* ——— Codes ——— */ ?>
    <section class="bo-section sseacc-panel"<?= $panelOpen('codes') ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['codes']) ?></span>
            <h2 class="bo-section__title">Codes émis</h2>
            <div class="bo-section__aside"><?php if ($canManage && $codes !== []): ?><button type="button" class="sseacc-btn sseacc-btn--primary" data-bo-dialog-open="sseacc-issue" aria-haspopup="dialog">Délivrer un code</button><?php endif; ?></div>
            <p class="bo-section__desc">Chaque code ouvre le portail pour une durée et un nombre d’usages limités ; révoquez-le dès qu’il n’a plus lieu d’être.</p>
        </header>
        <?php if ($codes === []): ?>
            <div class="sseacc-empty">
                <p class="sseacc-empty__title">Aucun code délivré</p>
                <p class="sseacc-empty__line">Les membres autorisés n’en ont pas besoin ; délivrez-en un pour un invité ou un membre habilité de passage.</p>
                <?php if ($canManage): ?><button type="button" class="sseacc-btn sseacc-btn--primary" data-bo-dialog-open="sseacc-issue" aria-haspopup="dialog">Délivrer un code</button><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="sseacc-toolbar" data-sseacc-filter="codes" hidden>
                <div class="sseacc-seg" role="group" aria-label="Filtrer par état">
                    <button type="button" class="sseacc-seg__btn" aria-pressed="true" data-sseacc-status="">Tous <span><?= count($codes) ?></span></button>
                    <?php foreach ($statusLabels as $st => $stLabel): ?>
                    <button type="button" class="sseacc-seg__btn" aria-pressed="false" data-sseacc-status="<?= $h($st) ?>"><?= $h($st === 'active' ? 'Valides' : ($st === 'expired' ? 'Expirés' : ($st === 'exhausted' ? 'Épuisés' : 'Révoqués'))) ?> <span><?= $statusCounts[$st] ?></span></button>
                    <?php endforeach; ?>
                </div>
                <label class="sseacc-search">
                    <span class="sseacc-sr">Rechercher un code</span>
                    <input type="search" placeholder="Rechercher un libellé" data-sseacc-search autocomplete="off">
                </label>
            </div>
            <div class="sseacc-tablewrap">
                <table class="sseacc-table">
                    <thead>
                    <tr>
                        <th scope="col">Libellé</th>
                        <th scope="col">Niveau de lecture</th>
                        <th scope="col">Usages</th>
                        <th scope="col">Expiration</th>
                        <th scope="col">État</th>
                        <th scope="col"><span class="sseacc-sr">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($codes as $c):
                        $cid = (int) ($c['id'] ?? 0);
                        $st = (string) ($c['status'] ?? (!empty($c['active']) ? 'active' : 'expired'));
                        $uses = (int) ($c['uses_count'] ?? 0);
                        $expTs = UtcDateTime::timestamp($c['expires_at'] ?? null);
                        $lvl = (string) ($c['clearance_level'] ?? '');
                        $search = mb_strtolower(($c['label'] ?? '') . ' ' . ($c['code_hint'] ?? '') . ' ' . ($c['grant_type_label'] ?? ''));
                        ?>
                        <tr data-sseacc-row data-status="<?= $h($st) ?>" data-text="<?= $h($search) ?>">
                            <td>
                                <strong class="sseacc-table__main"><?= $h($c['label'] ?? '') ?></strong>
                                <span class="sseacc-table__sub"><span><?= $h($c['grant_type_label'] ?? '') ?></span> · <span class="sseacc-mono"><?= $h($c['code_hint'] ?? '') ?></span></span>
                            </td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">Niveau de lecture</span><span class="sseacc-level sseacc-level--<?= $h($levelShort[$lvl] ?? 'CUI') ?>"><?= $h($levelLabel($lvl)) ?></span></td>
                            <td>
                                <span class="sseacc-cell-label" aria-hidden="true">Usages</span>
                                <span class="sseacc-num"><?= $uses ?> / <?= (int) ($c['max_uses'] ?? 1) ?></span>
                                <span class="sseacc-table__sub"><?php if (isset($lastUse[$cid])): ?>Dernier usage <span class="sseacc-num"><?= $h($lastUse[$cid]) ?></span><?php elseif ($uses > 0): ?>Usage antérieur au journal récent<?php else: ?>Jamais utilisé<?php endif; ?></span>
                            </td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">Expiration</span><span class="sseacc-num"><?= $h($expTs !== null ? date('d/m/Y H:i', $expTs) : '—') ?></span></td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">État</span><span class="sseacc-state sseacc-state--<?= $h($st) ?>"><?= $h($statusLabels[$st] ?? ($c['status_label'] ?? '')) ?></span></td>
                            <td class="sseacc-table__act">
                                <?php if ($canManage && !empty($c['active'])): ?>
                                <form method="post" action="<?= $h(url(AdminSseAccessController::BASE . '/' . $cid . '/revoquer')) ?>" data-ui-confirm="1" data-ui-confirm-title="<?= $h($tx('Révoquer ce code ?')) ?>" data-ui-confirm-body="<?= $h($tx('Il ne pourra plus être utilisé ; les sessions déjà ouvertes vont jusqu’à leur terme.')) ?>">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="sseacc-btn sseacc-btn--danger">Révoquer</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="sseacc-noresult" data-sseacc-noresult hidden>Aucun code ne correspond à ce filtre.</p>
        <?php endif; ?>
    </section>

    <?php /* ——— Délivrer un code ——— */ ?>
    <section class="bo-section sseacc-panel"<?= $panelOpen('delivrer') ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['delivrer']) ?></span>
            <h2 class="bo-section__title">Délivrer un code</h2>
            <div class="bo-section__aside"><?php if ($canManage): ?><button type="button" class="sseacc-btn sseacc-btn--primary" data-bo-dialog-open="sseacc-issue" aria-haspopup="dialog">Délivrer un code</button><?php endif; ?></div>
            <p class="bo-section__desc">Trois choix, un récapitulatif, puis le code s’affiche une seule fois : notez-le avant de fermer.</p>
        </header>
        <ol class="sseacc-steps">
            <li class="sseacc-step"><span class="sseacc-step__n" aria-hidden="true">1</span><div><p class="sseacc-step__title">Qui</p><p class="sseacc-step__line">Un libellé pour reconnaître le code, membre habilité ou invité.</p></div></li>
            <li class="sseacc-step"><span class="sseacc-step__n" aria-hidden="true">2</span><div><p class="sseacc-step__title">Niveau de lecture</p><p class="sseacc-step__line">Jusqu’à votre propre habilitation : <b><?= $h($levelLabel($maxLevel)) ?></b>.</p></div></li>
            <li class="sseacc-step"><span class="sseacc-step__n" aria-hidden="true">3</span><div><p class="sseacc-step__title">Validité</p><p class="sseacc-step__line">De 1 à 72 heures, de 1 à 10 usages, éventuellement limité à un dossier.</p></div></li>
        </ol>
        <?php if (!$canManage): ?>
        <p class="sseacc-note">Seul le commandement peut délivrer un code.</p>
        <?php elseif ($clearanceOrigin !== ''): ?>
        <p class="sseacc-note"><?= $h($clearanceOrigin) ?></p>
        <?php endif; ?>
    </section>

    <?php /* ——— Niveaux de diffusion ——— */ ?>
    <section class="bo-section sseacc-panel"<?= $panelOpen('niveaux') ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['niveaux']) ?></span>
            <h2 class="bo-section__title">Niveaux de diffusion</h2>
            <p class="bo-section__desc">Chaque niveau découvre de nouvelles catégories ; ce qui dépasse le niveau du lecteur reste caviardé.</p>
        </header>
        <div class="sseacc-tablewrap">
            <table class="sseacc-table sseacc-table--levels">
                <thead>
                <tr>
                    <th scope="col">Niveau</th>
                    <th scope="col">Ce qu’il donne à lire</th>
                    <th scope="col">Catégories en clair</th>
                    <th scope="col">Par défaut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($levels as $lvl => $rank): ?>
                <tr<?= $lvl === $maxLevel ? ' class="is-cap"' : '' ?>>
                    <th scope="row">
                        <span class="sseacc-level sseacc-level--<?= $h($levelShort[$lvl] ?? 'CUI') ?>"><?= $h($levelLabel($lvl)) ?></span>
                        <?php if ($lvl === $maxLevel): ?><span class="sseacc-table__sub sseacc-cap">Votre plafond</span><?php endif; ?>
                    </th>
                    <td><span class="sseacc-cell-label" aria-hidden="true">Ce qu’il donne à lire</span><?= $h($levelReads[$lvl] ?? '') ?></td>
                    <td>
                        <span class="sseacc-cell-label" aria-hidden="true">Catégories en clair</span>
                        <ul class="sseacc-cats">
                            <?php foreach (SseRedactionService::CATEGORIES as $cat): $open = SseRedactionService::levelRank((string) $cat['level']) <= $rank; ?>
                            <li class="sseacc-cat<?= $open ? ' is-open' : '' ?>"><span class="sseacc-sr"><?= $open ? 'En clair :' : 'Caviardé :' ?></span> <?= $h($cat['label']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td><span class="sseacc-cell-label" aria-hidden="true">Par défaut</span><?= $h($levelHolders[$lvl] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="sseacc-note">
            Le niveau d’un opérateur se règle sur sa fiche effectifs, champ « Niveau de diffusion renseignement ».
            <a class="sseacc-link" href="<?= $h(function_exists('effectifs_workspace_url') ? effectifs_workspace_url() : url('back-office')) ?>">Ouvrir les effectifs</a>
        </p>
    </section>

    <?php /* ——— Journal ——— */ ?>
    <section class="bo-section sseacc-panel"<?= $panelOpen('journal') ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true"><?= $h($index['journal']) ?></span>
            <h2 class="bo-section__title">Journal</h2>
            <?php if ($canSeeAudit): ?>
            <div class="bo-section__aside"><a class="sseacc-btn" href="<?= $h(url('back-office/audit')) ?>">Journal d’audit complet</a></div>
            <?php endif; ?>
            <p class="bo-section__desc">Derniers événements d’accès : codes, engagements de confidentialité, habilitations et dérogations des membres.</p>
        </header>
        <?php if ($journalRows === []): ?>
            <div class="sseacc-empty">
                <p class="sseacc-empty__title">Aucun événement d’accès</p>
                <p class="sseacc-empty__line">Les délivrances, usages et révocations de codes, comme les changements d’habilitation, s’inscriront ici.</p>
            </div>
        <?php else: ?>
            <div class="sseacc-toolbar" data-sseacc-filter="journal" hidden>
                <label class="sseacc-select">
                    <span class="sseacc-sr">Type d’événement</span>
                    <select data-sseacc-event>
                        <option value="">Tous les événements</option>
                        <?php foreach ($eventLabels as $type => $label): if (!isset($journalTypes[$type])) { continue; } ?>
                        <option value="<?= $h($type) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($journalSubjects !== []): ?>
                <label class="sseacc-select">
                    <span class="sseacc-sr">Membre ou code concerné</span>
                    <select data-sseacc-subject>
                        <option value="">Tous les membres et codes</option>
                        <?php foreach (array_keys($journalSubjects) as $s): ?>
                        <option value="<?= $h($s) ?>"><?= $h($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <label class="sseacc-search">
                    <span class="sseacc-sr">Rechercher dans le journal</span>
                    <input type="search" placeholder="Rechercher un auteur ou un code" data-sseacc-search autocomplete="off">
                </label>
            </div>
            <div class="sseacc-tablewrap">
                <table class="sseacc-table sseacc-table--log">
                    <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Événement</th>
                        <th scope="col">Concerne</th>
                        <th scope="col">Par</th>
                        <th scope="col">Détail</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($journalRows as $row):
                        $type = (string) $row['action'];
                        $subject = (string) $row['subject'];
                        $actor = (string) $row['actor'];
                        $detail = (string) $row['detail'];
                        ?>
                        <tr data-sseacc-row data-event="<?= $h($type) ?>" data-subject="<?= $h($subject) ?>" data-text="<?= $h(mb_strtolower($subject . ' ' . $actor . ' ' . $detail)) ?>">
                            <td class="sseacc-num"><?= $h($row['ts'] > 0 ? date('d/m/Y H:i', (int) $row['ts']) : '—') ?></td>
                            <td><span class="sseacc-event sseacc-event--<?= $h($eventTone[$type] ?? 'other') ?>"><?= $h($eventLabels[$type] ?? 'Action') ?></span></td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">Concerne</span><?= $h($subject !== '' ? $subject : '—') ?></td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">Par</span><?= $h($actor !== '' ? $actor : '—') ?></td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">Détail</span><?= $h($detail !== '' ? $detail : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="sseacc-noresult" data-sseacc-noresult hidden>Aucun événement ne correspond à ce filtre.</p>
        <?php endif; ?>
    </section>

    <?php if ($canManage): ?>
    <dialog class="bo-dialog sseacc-dialog" id="sseacc-issue" aria-labelledby="sseacc-issue-title">
        <form method="post" action="<?= $h($pageUrl) ?>" class="bo-dialog__form" data-sseacc-issue>
            <?= Csrf::field() ?>
            <div class="bo-dialog__head">
                <div class="bo-dialog__titles">
                    <h2 class="bo-dialog__title" id="sseacc-issue-title">Délivrer un code</h2>
                    <p class="bo-dialog__sub">Le code s’affichera une seule fois, juste après l’émission.</p>
                </div>
                <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><?= $closeIcon ?></button>
            </div>
            <div class="bo-dialog__body sseacc-dialog__body">
                <fieldset class="sseacc-fs">
                    <legend class="sseacc-fs__title">Qui</legend>
                    <label class="sseacc-field">
                        <span class="sseacc-field__label">Libellé</span>
                        <input type="text" name="label" required maxlength="120" value="<?= $h($defaultLabel) ?>" data-sseacc-recap-src="label">
                        <span class="sseacc-field__help">Nom du destinataire ou de l’occasion, affiché dans la liste des codes.</span>
                    </label>
                    <div class="sseacc-choices" role="radiogroup" aria-label="Type d’accès">
                        <label class="sseacc-choice">
                            <input type="radio" name="grant_type" value="member" checked data-sseacc-recap-src="grant">
                            <span class="sseacc-choice__title">Membre habilité</span>
                            <span class="sseacc-choice__line">Membre connecté dont le rôle ouvre le renseignement ; il lit selon sa propre habilitation.</span>
                        </label>
                        <label class="sseacc-choice">
                            <input type="radio" name="grant_type" value="guest" data-sseacc-recap-src="grant">
                            <span class="sseacc-choice__title">Invité</span>
                            <span class="sseacc-choice__line">Personne sans compte ou d’une autre communauté ; il lit au niveau accordé ci-dessous.</span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="sseacc-fs">
                    <legend class="sseacc-fs__title">Niveau de lecture accordé</legend>
                    <p class="sseacc-field__help">Échelle américaine, du plus large au plus restreint ; plafonnée à votre habilitation (<?= $h($levelShort[$maxLevel] ?? 'CUI') ?>).</p>
                    <div class="sseacc-choices sseacc-choices--levels" role="radiogroup" aria-label="Niveau de lecture accordé">
                        <?php foreach ($levels as $lvl => $rank): $above = $rank > $maxRank; ?>
                        <label class="sseacc-choice<?= $above ? ' is-locked' : '' ?>">
                            <input type="radio" name="clearance_level" value="<?= $h($lvl) ?>"<?= $lvl === SseCaseRepository::CLASS_INTERNAL ? ' checked' : '' ?><?= $above ? ' disabled' : '' ?> data-sseacc-recap-src="level">
                            <span class="sseacc-choice__title"><span class="sseacc-level sseacc-level--<?= $h($levelShort[$lvl] ?? 'CUI') ?>"><?= $h($levelShort[$lvl] ?? '') ?></span> <span class="sseacc-choice__name"><?= $h($levelLabel($lvl)) ?></span></span>
                            <span class="sseacc-choice__line"><?= $above ? 'Au-dessus de votre habilitation.' : $h($levelReads[$lvl] ?? '') ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="sseacc-fs">
                    <legend class="sseacc-fs__title">Validité</legend>
                    <div class="sseacc-grid">
                        <label class="sseacc-field">
                            <span class="sseacc-field__label">Validité du code</span>
                            <select name="ttl_hours" data-sseacc-recap-src="ttl">
                                <?php foreach ($ttlOptions as $hval): ?>
                                <option value="<?= $hval ?>"<?= $hval === 4 ? ' selected' : '' ?>><?= $hval ?> h</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="sseacc-field">
                            <span class="sseacc-field__label">Nombre d’utilisations</span>
                            <select name="max_uses" data-sseacc-recap-src="uses">
                                <?php for ($n = 1; $n <= 10; $n++): ?>
                                <option value="<?= $n ?>"><?= $n ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label class="sseacc-field">
                            <span class="sseacc-field__label">Durée de session après saisie</span>
                            <select name="session_ttl_minutes" data-sseacc-recap-src="session">
                                <?php foreach ($sessionOptions as $min => $minLabel): ?>
                                <option value="<?= $min ?>"<?= $min === 240 ? ' selected' : '' ?>><?= $h($minLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="sseacc-field">
                            <span class="sseacc-field__label">Limiter à un dossier</span>
                            <select name="case_id" data-sseacc-recap-src="case">
                                <option value="">Tous les dossiers</option>
                                <?php foreach ($cases as $case): ?>
                                <option value="<?= (int) ($case['id'] ?? 0) ?>"><?= $h(trim(($case['reference_code'] ?? '') . ' — ' . ($case['title'] ?? ''), ' —')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </fieldset>

                <section class="sseacc-recap" aria-labelledby="sseacc-recap-title">
                    <h3 class="sseacc-fs__title" id="sseacc-recap-title">Récapitulatif</h3>
                    <dl class="sseacc-recap__list">
                        <div><dt>Libellé</dt><dd data-sseacc-recap="label"><?= $h($defaultLabel) ?></dd></div>
                        <div><dt>Type</dt><dd data-sseacc-recap="grant">Membre habilité</dd></div>
                        <div><dt>Niveau</dt><dd data-sseacc-recap="level"><?= $h($levelLabel(SseCaseRepository::CLASS_INTERNAL)) ?></dd></div>
                        <div><dt>Validité</dt><dd data-sseacc-recap="ttl">4 h</dd></div>
                        <div><dt>Usages</dt><dd data-sseacc-recap="uses">1</dd></div>
                        <div><dt>Session</dt><dd data-sseacc-recap="session">4 heures</dd></div>
                        <div><dt>Dossier</dt><dd data-sseacc-recap="case">Tous les dossiers</dd></div>
                    </dl>
                </section>
            </div>
            <div class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Émettre le code</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <?php if ($canWrite): ?>
    <dialog class="bo-dialog sseacc-dialog" id="sseh-clearance" aria-labelledby="sseh-clearance-title">
        <form method="post" action="<?= $h(url(AdminSseAccessController::BASE . '/personnels/habilitation')) ?>" class="bo-dialog__form" data-sseh-targets-form>
            <?= Csrf::field() ?>
            <div class="bo-dialog__head">
                <div class="bo-dialog__titles">
                    <h2 class="bo-dialog__title" id="sseh-clearance-title">Changer l’habilitation</h2>
                    <p class="bo-dialog__sub">Inscrite sur la fiche opérateur ; plafonnée à la vôtre, jamais sur votre propre fiche.</p>
                </div>
                <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><?= $closeIcon ?></button>
            </div>
            <div class="bo-dialog__body sseacc-dialog__body">
                <p class="sseh-targets" aria-live="polite"><span>Membres concernés :</span> <span data-sseh-names>—</span></p>
                <fieldset class="sseacc-fs">
                    <legend class="sseacc-fs__title">Niveau</legend>
                    <div class="sseacc-choices sseacc-choices--levels" role="radiogroup" aria-label="Niveau">
                        <?php foreach ($levels as $lvl => $rank): if ($rank < 1) { continue; } $above = !isset($grantable[$lvl]); ?>
                        <label class="sseacc-choice<?= $above ? ' is-locked' : '' ?>">
                            <input type="radio" name="clearance_level" value="<?= $h($lvl) ?>" required<?= $above ? ' disabled' : '' ?>>
                            <span class="sseacc-choice__title"><?= $chip($lvl) ?> <span class="sseacc-choice__name"><?= $h($levelLabel($lvl)) ?></span></span>
                            <span class="sseacc-choice__line"><?= $above ? 'Au-dessus de votre habilitation.' : $h($levelReads[$lvl] ?? '') ?></span>
                        </label>
                        <?php endforeach; ?>
                        <label class="sseacc-choice">
                            <input type="radio" name="clearance_level" value="" required>
                            <span class="sseacc-choice__title">Retirer l’habilitation de la fiche</span>
                            <span class="sseacc-choice__line">Le membre garde celle que son rôle reporte, s’il en a une.</span>
                        </label>
                    </div>
                </fieldset>
                <label class="sseacc-field">
                    <span class="sseacc-field__label">Motif</span>
                    <textarea name="reason" required minlength="5" maxlength="500" rows="3"></textarea>
                    <span class="sseacc-field__help">Obligatoire, inscrit au journal d’audit avec l’avant et l’après.</span>
                </label>
            </div>
            <div class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Enregistrer</button>
            </div>
        </form>
    </dialog>

    <dialog class="bo-dialog sseacc-dialog" id="sseh-access" aria-labelledby="sseh-access-title">
        <form method="post" action="<?= $h(url(AdminSseAccessController::BASE . '/personnels/acces')) ?>" class="bo-dialog__form" data-sseh-targets-form>
            <?= Csrf::field() ?>
            <input type="hidden" name="effect" value="allow" data-sseh-effect-input>
            <div class="bo-dialog__head">
                <div class="bo-dialog__titles">
                    <h2 class="bo-dialog__title" id="sseh-access-title"><span data-sseh-effect-show="allow">Accorder un accès</span><span data-sseh-effect-show="deny" hidden>Restreindre l’accès</span></h2>
                    <p class="bo-dialog__sub">Dérogation individuelle datée, avec motif ; vous n’accordez que ce que vous possédez.</p>
                </div>
                <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><?= $closeIcon ?></button>
            </div>
            <div class="bo-dialog__body sseacc-dialog__body">
                <p class="sseh-targets" aria-live="polite"><span>Membres concernés :</span> <span data-sseh-names>—</span></p>
                <fieldset class="sseacc-fs">
                    <legend class="sseacc-fs__title">Sections</legend>
                    <div class="sseh-secs">
                        <label class="sseh-sec"><input type="checkbox" name="sections[]" value="*"> <span>Toutes les sections</span></label>
                        <?php foreach ($sections as $s): if ($s['sealed']) { continue; } ?>
                        <label class="sseh-sec"><input type="checkbox" name="sections[]" value="<?= $h($s['key']) ?>"> <span><?= $h($s['label']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="sseacc-fs">
                    <legend class="sseacc-fs__title">Ce que la personne peut faire</legend>
                    <div class="sseacc-choices" role="radiogroup" aria-label="Ce que la personne peut faire">
                        <?php foreach (SseAccessCatalog::SIMPLE_ACCESS as $acc => $def): ?>
                        <label class="sseacc-choice"><input type="radio" name="access" value="<?= $h($acc) ?>" required<?= $acc === 'voir' ? ' checked' : '' ?>><span class="sseacc-choice__title"><?= $h($def['label']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="sseacc-grid">
                    <label class="sseacc-field">
                        <span class="sseacc-field__label">Échéance</span>
                        <input type="date" name="ends_at" min="<?= $h(date('Y-m-d')) ?>">
                        <span class="sseacc-field__help">Vide : jusqu’à retrait.</span>
                    </label>
                </div>
                <label class="sseacc-field">
                    <span class="sseacc-field__label">Motif</span>
                    <textarea name="reason" required minlength="5" maxlength="500" rows="3"></textarea>
                    <span class="sseacc-field__help">Obligatoire, inscrit à l’historique des droits et au journal d’audit.</span>
                </label>
            </div>
            <div class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-bo-dialog-close>Annuler</button>
                <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary">Enregistrer</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <?php if ($canPeople): ?>
    <dialog class="bo-dialog sseh-drawer" id="sseh-member" aria-labelledby="sseh-member-title"<?= $member !== null ? ' open data-sseh-autoopen' : '' ?> data-sseh-drawer data-sseh-src="<?= $h($tabUrl('personnels', ['fragment' => 'fiche'])) ?>" data-sseh-back="<?= $h($tabUrl('personnels')) ?>">
        <div class="sseh-drawer__inner" data-sseh-drawer-body>
            <?php if ($member !== null) {
                $drawerOpen = true;
                require __DIR__ . '/_member_drawer.php';
            } else { ?>
            <div class="bo-dialog__head">
                <div class="bo-dialog__titles"><h2 class="bo-dialog__title" id="sseh-member-title">Fiche du membre</h2></div>
                <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><?= $closeIcon ?></button>
            </div>
            <p class="sseh-drawer__wait" role="status" aria-live="polite">Chargement de la fiche…</p>
            <?php } ?>
        </div>
    </dialog>
    <?php endif; ?>

    <?php if ($issuedPlain !== null): ?>
    <dialog class="bo-dialog sseacc-dialog sseacc-dialog--issued" id="sseacc-issued" aria-labelledby="sseacc-issued-title" open data-sseacc-autoopen>
        <div class="bo-dialog__form">
            <div class="bo-dialog__head">
                <div class="bo-dialog__titles">
                    <h2 class="bo-dialog__title" id="sseacc-issued-title">Code émis</h2>
                    <p class="bo-dialog__sub">À communiquer par un canal sécurisé : il ne sera plus réaffiché.</p>
                </div>
                <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><?= $closeIcon ?></button>
            </div>
            <div class="bo-dialog__body">
                <p class="sseacc-code" role="status" aria-live="polite" aria-atomic="true"><span class="sseacc-sr">Code d’accès :</span> <span class="sseacc-code__value" id="sseacc-code-value" data-sseacc-code><?= $h($issuedPlain) ?></span></p>
                <div class="sseacc-copy">
                    <button type="button" class="sseacc-btn sseacc-btn--primary" data-sseacc-copy="sseacc-code-value">Copier le code</button>
                    <span class="sseacc-copy__done" data-sseacc-copied role="status" aria-live="polite" hidden>Code copié</span>
                </div>
                <p class="bo-dialog__help">Jamais dans un salon public ni en capture d’écran : un message privé ou de vive voix.</p>
            </div>
            <div class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn bo-dialog__btn--primary" data-bo-dialog-close>J’ai transmis le code</button>
            </div>
        </div>
    </dialog>
    <?php endif; ?>
</div>
<script defer src="<?= $h(asset_url('assets/js/back-office-bands.js')) ?>"></script>
<script defer src="<?= $h(asset_url('assets/js/back-office-sse-access.js')) ?>"></script>
