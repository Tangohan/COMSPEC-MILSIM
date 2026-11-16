<?php
declare(strict_types=1);

/**
 * Back-office › Accès renseignement : bande d'en-tête (titre, une phrase, chiffres clés, actions), puis quatre onglets
 * (codes émis, délivrance, niveaux de diffusion, journal). Délivrance en fenêtre ; le code émis s'affiche une seule fois
 * dans une seconde fenêtre. Composants : back-office-bands.css (.bo-*) et back-office-sse-access.css (.sseacc-*).
 */

use App\Core\Csrf;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseRedactionService;
use App\Support\HtmlTranslator;
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
$maxLevel = isset($maxLevel) && isset(SseRedactionService::LEVELS[(string) $maxLevel]) ? (string) $maxLevel : SseCaseRepository::CLASS_INTERNAL;
$maxRank = SseRedactionService::levelRank($maxLevel);
$clearanceOrigin = trim((string) ($clearanceOrigin ?? ''));
$tabs = ['codes', 'delivrer', 'niveaux', 'journal'];
$activeTab = in_array($activeTab ?? '', $tabs, true) ? (string) $activeTab : 'codes';
$can = static fn (string $slug): bool => function_exists('can') && can($slug);
$canEditRights = $can('atak.sse.grant') || $can('admin.access');
$canSeeAudit = $can('admin.organization') || $can('admin.access');
$pageUrl = url('back-office/renseignement/acces');

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
];
$eventCounts = array_fill_keys(array_keys($eventLabels), 0);
foreach ($actionLog as $row) {
    $t = (string) ($row['event_type'] ?? '');
    if (isset($eventCounts[$t])) {
        $eventCounts[$t]++;
    }
}
$detailLabels = ['session_invite' => 'Session invité', 'session_membre' => 'Session membre'];

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

$ttlOptions = [1, 2, 4, 8, 12, 24, 48, 72];
$sessionOptions = [60 => '1 heure', 120 => '2 heures', 240 => '4 heures', 480 => '8 heures', 1440 => '24 heures'];
$defaultLabel = function_exists('locale') && locale() === 'en' ? 'Temporary access' : 'Accès temporaire';

$tabMeta = [
    'codes' => ['label' => 'Codes émis', 'count' => count($codes)],
    'delivrer' => ['label' => 'Délivrer un code', 'count' => null],
    'niveaux' => ['label' => 'Niveaux de diffusion', 'count' => count($levels)],
    'journal' => ['label' => 'Journal', 'count' => count($actionLog)],
];
// Traduction des textes d'attributs que HtmlTranslator ne relit pas (fenêtre de confirmation).
$tx = static fn (string $fr): string => function_exists('locale') && locale() === 'en' ? HtmlTranslator::translateText($fr) : $fr;
?>
<div class="bo-page sseacc" data-sseacc>
    <header class="bo-band bo-band--hero bo-band--sky sseacc-hero" aria-labelledby="ath-page-title">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__who">
                <h1 class="bo-band__title" id="ath-page-title">Accès renseignement</h1>
                <p class="bo-band__line">Le commandement et les membres autorisés entrent au portail sans code ; un code temporaire ouvre l’accès à un membre habilité de passage ou à un invité.</p>
                <div class="bo-band__actions sseacc-hero__actions">
                    <a class="bo-band__btn bo-band__btn--solid" href="<?= $h(url('atak/sse')) ?>">Ouvrir le portail <span class="bo-band__arrow" aria-hidden="true">→</span></a>
                    <a class="bo-band__btn" href="<?= $h(url('atak/sse/commandement')) ?>">Entrer comme commandement</a>
                    <?php if ($canEditRights): ?>
                    <a class="sseacc-hero__link" href="<?= $h(url('atak/sse/droits')) ?>">Droits d’accès par section</a>
                    <?php endif; ?>
                </div>
            </div>
            <dl class="sseacc-hero__figures" aria-label="Chiffres clés">
                <div class="bo-figure"><dt>Codes émis</dt><dd><strong><?= count($codes) ?></strong></dd></div>
                <div class="bo-figure<?= $statusCounts['active'] > 0 ? ' is-hot' : '' ?>"><dt>Encore valides</dt><dd><strong><?= $statusCounts['active'] ?></strong></dd></div>
                <div class="bo-figure"><dt>Journal récent</dt><dd><strong><?= count($actionLog) ?></strong></dd></div>
            </dl>
        </div>
    </header>

    <div class="sseacc-tabs" role="tablist" aria-label="Sections de la page">
        <?php $i = 0; foreach ($tabMeta as $key => $meta): $i++; $on = $key === $activeTab; ?>
        <a class="sseacc-tabs__tab" role="tab" id="sseacc-tab-<?= $h($key) ?>" href="<?= $h($pageUrl . '?onglet=' . $key) ?>"
           aria-controls="sseacc-panel-<?= $h($key) ?>" aria-selected="<?= $on ? 'true' : 'false' ?>" tabindex="<?= $on ? '0' : '-1' ?>" data-sseacc-tab="<?= $h($key) ?>">
            <span><?= $h($meta['label']) ?></span>
            <?php if ($meta['count'] !== null): ?><span class="sseacc-tabs__count"><?= (int) $meta['count'] ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php /* ——— 01 Codes ——— */ ?>
    <section class="bo-section sseacc-panel" role="tabpanel" id="sseacc-panel-codes" aria-labelledby="sseacc-tab-codes" tabindex="0"<?= $activeTab === 'codes' ? '' : ' hidden' ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 01</span>
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
                                <form method="post" action="<?= $h(url('back-office/renseignement/acces/' . $cid . '/revoquer')) ?>" data-ui-confirm="1" data-ui-confirm-title="<?= $h($tx('Révoquer ce code ?')) ?>" data-ui-confirm-body="<?= $h($tx('Il ne pourra plus être utilisé ; les sessions déjà ouvertes vont jusqu’à leur terme.')) ?>">
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

    <?php /* ——— 02 Délivrer un code ——— */ ?>
    <section class="bo-section sseacc-panel" role="tabpanel" id="sseacc-panel-delivrer" aria-labelledby="sseacc-tab-delivrer" tabindex="0"<?= $activeTab === 'delivrer' ? '' : ' hidden' ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 02</span>
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

    <?php /* ——— 03 Niveaux de diffusion ——— */ ?>
    <section class="bo-section sseacc-panel" role="tabpanel" id="sseacc-panel-niveaux" aria-labelledby="sseacc-tab-niveaux" tabindex="0"<?= $activeTab === 'niveaux' ? '' : ' hidden' ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 03</span>
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

    <?php /* ——— 04 Journal ——— */ ?>
    <section class="bo-section sseacc-panel" role="tabpanel" id="sseacc-panel-journal" aria-labelledby="sseacc-tab-journal" tabindex="0"<?= $activeTab === 'journal' ? '' : ' hidden' ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 04</span>
            <h2 class="bo-section__title">Journal</h2>
            <?php if ($canSeeAudit): ?>
            <div class="bo-section__aside"><a class="sseacc-btn" href="<?= $h(url('back-office/audit')) ?>">Journal d’audit complet</a></div>
            <?php endif; ?>
            <p class="bo-section__desc">Derniers événements d’accès : codes délivrés, utilisés, révoqués et engagements de confidentialité.</p>
        </header>
        <?php if ($actionLog === []): ?>
            <div class="sseacc-empty">
                <p class="sseacc-empty__title">Aucun événement d’accès</p>
                <p class="sseacc-empty__line">Les délivrances, usages et révocations de codes s’inscriront ici.</p>
            </div>
        <?php else: ?>
            <div class="sseacc-toolbar" data-sseacc-filter="journal" hidden>
                <label class="sseacc-select">
                    <span class="sseacc-sr">Type d’événement</span>
                    <select data-sseacc-event>
                        <option value="">Tous les événements</option>
                        <?php foreach ($eventLabels as $type => $label): if ($eventCounts[$type] === 0) { continue; } ?>
                        <option value="<?= $h($type) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
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
                        <th scope="col">Code</th>
                        <th scope="col">Par</th>
                        <th scope="col">Détail</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($actionLog as $row):
                        $type = (string) ($row['event_type'] ?? '');
                        $cid = (int) ($row['code_id'] ?? 0);
                        $codeName = $cid > 0 ? ($codeLabels[$cid] ?? '') : '';
                        $detail = (string) ($row['detail'] ?? '');
                        $detail = $detailLabels[$detail] ?? $detail;
                        if ($detail === $codeName) {
                            $detail = '';
                        }
                        $actor = (string) ($row['actor'] ?? '');
                        ?>
                        <tr data-sseacc-row data-event="<?= $h($type) ?>" data-text="<?= $h(mb_strtolower($codeName . ' ' . $actor . ' ' . $detail)) ?>">
                            <td class="sseacc-num"><?= $h($fmtLogTime($row)) ?></td>
                            <td><span class="sseacc-event sseacc-event--<?= $h($type !== '' ? $type : 'other') ?>"><?= $h($eventLabels[$type] ?? ($row['event_label'] ?? 'Action')) ?></span></td>
                            <td><span class="sseacc-cell-label" aria-hidden="true">Code</span><?= $h($codeName !== '' ? $codeName : '—') ?></td>
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
                <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
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

    <?php if ($issuedPlain !== null): ?>
    <dialog class="bo-dialog sseacc-dialog sseacc-dialog--issued" id="sseacc-issued" aria-labelledby="sseacc-issued-title" open data-sseacc-autoopen>
        <div class="bo-dialog__form">
            <div class="bo-dialog__head">
                <div class="bo-dialog__titles">
                    <h2 class="bo-dialog__title" id="sseacc-issued-title">Code émis</h2>
                    <p class="bo-dialog__sub">À communiquer par un canal sécurisé : il ne sera plus réaffiché.</p>
                </div>
                <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
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
