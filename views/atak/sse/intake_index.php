<?php
declare(strict_types=1);
ob_start();

use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakePresenter as P;
use App\Services\Intel\IntelIntakeService;

/**
 * Bureau SSE › Renseignement et enquêtes › Remontées — file des comptes rendus et des fiches, avec leurs pastilles
 * (anciennement au back-office, /back-office/remontees y renvoie). Bande d'en-tête avec les chiffres clés, onglets des
 * vues, filtres (état, source, type, traitant), filtres par pastille (retard, urgence…), colonnes triables ; cartes
 * sur téléphone. Styles : remontees.css (.rmt-*), sse-remontees.css (jetons du bureau), sse-fiches-etiquettes.css (bande).
 *
 * @var bool $intakeReady
 * @var list<array<string, mixed>> $intakeItems lignes passées par IntelIntakeDesk::refine
 * @var array{open: int, closed: int, mine: int, deleted: int, states?: array<string, int>} $intakeCounts
 * @var string $intakeTab
 * @var array{source: string, type: string, q: string, assignee: string} $intakeFilters
 * @var array<string, string> $intakeTypes
 * @var list<array{id: int, label: string}> $intakeMembers
 * @var string $intakeBadge
 * @var array<string, int> $intakeBadgeCounts
 * @var string $intakeSort
 * @var string $intakeDir
 * @var array{todo: int, late: int, week: int, open: int, mine: int} $intakeFigures
 * @var array<string, bool> $intakeRights
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = is_array($intakeItems ?? null) ? $intakeItems : [];
$counts = $intakeCounts ?? ['open' => 0, 'closed' => 0, 'mine' => 0, 'deleted' => 0];
$tab = (string) ($intakeTab ?? 'open');
$f = $intakeFilters ?? ['source' => '', 'type' => '', 'q' => '', 'assignee' => ''];
$members = $intakeMembers ?? [];
$badgeFilter = (string) ($intakeBadge ?? '');
$badgeCounts = is_array($intakeBadgeCounts ?? null) ? $intakeBadgeCounts : [];
$sort = (string) ($intakeSort ?? 'recu');
$dir = (string) ($intakeDir ?? 'desc');

$link = static function (array $over) use ($f, $tab, $badgeFilter, $sort, $dir): string {
    $q = array_filter(array_merge([
        'etat' => $tab, 'source' => $f['source'], 'type' => $f['type'], 'q' => $f['q'], 'attribue' => $f['assignee'],
        'pastille' => $badgeFilter, 'tri' => $sort === 'recu' ? '' : $sort, 'ordre' => $dir === 'desc' ? '' : $dir,
    ], $over), static fn ($v): bool => $v !== '' && $v !== null);

    return url('atak/sse/remontees' . ($q !== [] ? '?' . http_build_query($q) : ''));
};
$sortLink = static function (string $key, string $label) use ($link, $sort, $dir, $h): string {
    $active = $sort === $key;
    $next = $active && $dir === 'desc' ? 'asc' : 'desc';

    return '<a class="rmt-sort' . ($active ? ' is-active' : '') . '" href="' . $h($link(['tri' => $key, 'ordre' => $next])) . '">'
        . $h($label) . ($active ? ' <span aria-hidden="true">' . ($dir === 'asc' ? '↑' : '↓') . '</span><span class="rmt-sr">' . ($dir === 'asc' ? 'ordre croissant' : 'ordre décroissant') . '</span>' : '') . '</a>';
};
$ariaSort = static fn (string $key): string => $sort === $key ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '';
$badge = static fn (array $b): string => '<li class="rmt-badge rmt-badge--' . $h($b['tone']) . '"' . ($b['title'] !== '' ? ' title="' . $h($b['title']) . '"' : '') . '><span class="rmt-badge__dot" aria-hidden="true"></span>' . $h($b['label']) . '</li>';
// Deux lignes de pastilles : ce qui décide (état, retard, urgence), puis le reste.
$split = static function (array $badges): array {
    $main = [];
    $rest = [];
    foreach ($badges as $b) {
        if (in_array($b['key'], ['etat', 'supprimee', 'retard', 'urgence'], true)) {
            $main[] = $b;
        } else {
            $rest[] = $b;
        }
    }

    return [$main, $rest];
};
$hasFilters = $f['q'] !== '' || $f['source'] !== '' || $f['type'] !== '' || $f['assignee'] !== '' || $badgeFilter !== '';
$fig = (is_array($intakeFigures ?? null) ? $intakeFigures : []) + ['todo' => 0, 'late' => 0, 'week' => 0, 'open' => 0, 'mine' => 0];
$canManageIntake = !empty($intakeRights['manage']);
$stateTabs = ['open' => 'Ouvertes', 'closed' => 'Traitées', 'mine' => 'Attribuées à moi', 'all' => 'Tous les états'] + ($canManageIntake ? ['deleted' => 'Supprimées'] : []);
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiches-etiquettes.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/remontees.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-remontees.css')) . '">';
?>
<div class="rmt rmt--sse rmt-list">
    <header class="fng-strip rmt-strip">
        <div class="fng-strip__halo" aria-hidden="true"></div>
        <div class="fng-strip__veil" aria-hidden="true"></div>
        <div class="fng-strip__body">
            <div class="fng-strip__identity">
                <span class="fng-strip__index" aria-hidden="true">R.02</span>
                <div>
                    <h1 class="fng-title">Remontées</h1>
                    <p class="fng-strip__desc">Comptes rendus et fiches de renseignement arrivés du terrain, à prendre en compte dans les délais puis à exploiter.</p>
                </div>
            </div>
            <div class="fng-strip__meta">
                <p class="fng-key"><strong><?= (int) $fig['todo'] ?></strong><span>à traiter</span></p>
                <dl class="fng-figs">
                    <div class="<?= $fig['late'] > 0 ? 'rmt-fig--late' : '' ?>"><dt>en retard</dt><dd><a href="<?= $h(url('atak/sse/remontees?pastille=retard')) ?>"><?= (int) $fig['late'] ?></a></dd></div>
                    <div><dt>traitées cette semaine</dt><dd><?= (int) $fig['week'] ?></dd></div>
                    <div><dt>ouvertes</dt><dd><?= (int) $fig['open'] ?></dd></div>
                    <div><dt>attribuées à moi</dt><dd><a href="<?= $h(url('atak/sse/remontees?etat=mine')) ?>"><?= (int) $fig['mine'] ?></a></dd></div>
                </dl>
            </div>
        </div>
    </header>
    <?php $rmtNav = 'list'; require __DIR__ . '/_intake_nav.php'; ?>

    <?php if (empty($intakeReady)): ?>
        <p class="rmt-notice">Les tables du suivi des remontées ne sont pas encore créées : un administrateur de la plateforme doit lancer la mise à jour de la base.</p>
    <?php else: ?>

    <form class="rmt-filters" method="get" action="<?= $h(url('atak/sse/remontees')) ?>" role="search">
        <div class="rmt-filters__search">
            <label class="rmt-label" for="rmt-q">Rechercher</label>
            <input id="rmt-q" type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Référence, texte, indicatif">
        </div>
        <div>
            <label class="rmt-label" for="rmt-etat">État</label>
            <select id="rmt-etat" name="etat">
                <?php foreach ($stateTabs as $k => $label): ?>
                    <option value="<?= $h($k) ?>"<?= $tab === $k ? ' selected' : '' ?>><?= $h($label) ?> (<?= (int) ($k === 'all' ? $counts['open'] + $counts['closed'] : ($counts[$k] ?? 0)) ?>)</option>
                <?php endforeach; ?>
                <optgroup label="État précis">
                    <?php foreach (IntelIntakeService::STATES as $k => $label): ?>
                        <option value="<?= $h($k) ?>"<?= $tab === $k ? ' selected' : '' ?>><?= $h($label) ?> (<?= (int) ($counts['states'][$k] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div>
            <label class="rmt-label" for="rmt-source">Source</label>
            <select id="rmt-source" name="source">
                <option value="">Toutes les sources</option>
                <option value="cr"<?= $f['source'] === 'cr' ? ' selected' : '' ?>>Comptes rendus</option>
                <option value="fiche"<?= $f['source'] === 'fiche' ? ' selected' : '' ?>>Fiches de renseignement</option>
            </select>
        </div>
        <div>
            <label class="rmt-label" for="rmt-typef">Type</label>
            <select id="rmt-typef" name="type">
                <option value="">Tous les types</option>
                <?php foreach ($intakeTypes as $code => $label): ?>
                    <option value="<?= $h($code) ?>"<?= $f['type'] === $code ? ' selected' : '' ?>><?= $h($code . ' · ' . $label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="rmt-label" for="rmt-attr">Traitant</label>
            <select id="rmt-attr" name="attribue">
                <option value="">Tous les traitants</option>
                <option value="none"<?= $f['assignee'] === 'none' ? ' selected' : '' ?>>Sans traitant</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"<?= $f['assignee'] === (string) $m['id'] ? ' selected' : '' ?>><?= $h($m['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="rmt-filters__go">
            <?php if ($badgeFilter !== ''): ?><input type="hidden" name="pastille" value="<?= $h($badgeFilter) ?>"><?php endif; ?>
            <button type="submit" class="rmt-btn rmt-btn--primary">Filtrer</button>
            <?php if ($hasFilters): ?><a class="rmt-btn" href="<?= $h(url('atak/sse/remontees?etat=' . rawurlencode($tab))) ?>">Effacer</a><?php endif; ?>
        </div>
    </form>

    <ul class="rmt-pills" aria-label="Filtrer par pastille">
        <li><a class="rmt-pill<?= $badgeFilter === '' ? ' is-active' : '' ?>" href="<?= $h($link(['pastille' => ''])) ?>"<?= $badgeFilter === '' ? ' aria-current="true"' : '' ?>>Toutes</a></li>
        <?php foreach (IntelIntakeDesk::BADGE_FILTERS as $k => $label): $n = (int) ($badgeCounts[$k] ?? 0); ?>
            <li><a class="rmt-pill rmt-pill--<?= $h($k) ?><?= $badgeFilter === $k ? ' is-active' : '' ?><?= $n > 0 ? ' is-on' : '' ?>" href="<?= $h($link(['pastille' => $badgeFilter === $k ? '' : $k])) ?>"<?= $badgeFilter === $k ? ' aria-current="true"' : '' ?>><?= $h($label) ?> <strong><?= $n ?></strong></a></li>
        <?php endforeach; ?>
    </ul>

    <?php if ($items === []): ?>
        <div class="rmt-empty">
            <strong>Aucune remontée ici</strong>
            <p><?= $hasFilters ? 'Aucune remontée ne correspond à ces filtres.' : 'Les comptes rendus arrivent du téléphone ATAK, les fiches de l’application FRS et du bureau SSE.' ?></p>
        </div>
    <?php else: ?>
        <div class="rmt-tablewrap">
            <table class="rmt-table">
                <caption class="rmt-sr">Remontées, <?= count($items) ?> ligne(s)</caption>
                <thead>
                    <tr>
                        <th scope="col">Remontée</th>
                        <th scope="col"<?= $ariaSort('etat') ?>><?= $sortLink('etat', 'État') ?> · <?= $sortLink('urgence', 'Urgence') ?></th>
                        <th scope="col"<?= $ariaSort('emetteur') ?>><?= $sortLink('emetteur', 'Émetteur') ?></th>
                        <th scope="col"<?= $ariaSort('recu') ?>><?= $sortLink('recu', 'Reçue') ?></th>
                        <th scope="col"<?= $ariaSort('attente') ?>><?= $sortLink('attente', 'Attente') ?></th>
                        <th scope="col"<?= $ariaSort('traitant') ?>><?= $sortLink('traitant', 'Traitant') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it):
                        $href = url('atak/sse/remontees/' . $it['source'] . '/' . $it['id']);
                        [$main, $rest] = $split($it['badges']);
                    ?>
                        <tr class="<?= $it['delay']['late'] ? 'is-late' : '' ?>">
                            <td>
                                <a class="rmt-row__title" href="<?= $h($href) ?>" translate="no"><?= $h($it['title'] !== '' ? $it['title'] : $it['type_label']) ?></a>
                                <span class="rmt-row__ref"><span class="rmt-mono"><?= $h($it['ref']) ?></span> · <?= $h($it['type'] . ' · ' . $it['type_label']) ?><?= (int) $it['comments'] > 0 ? ' · ' . (int) $it['comments'] . ' commentaire' . ((int) $it['comments'] > 1 ? 's' : '') : '' ?></span>
                                <?php if ($rest !== []): ?><ul class="rmt-badges rmt-badges--rest"><?php foreach ($rest as $b): ?><?= $badge($b) ?><?php endforeach; ?></ul><?php endif; ?>
                            </td>
                            <td><ul class="rmt-badges"><?php foreach ($main as $b): ?><?= $badge($b) ?><?php endforeach; ?></ul></td>
                            <td translate="no"><?= $h($it['author']) ?></td>
                            <td class="rmt-nowrap" title="<?= $h(P::when((string) $it['at'])) ?>"><?= $h(P::ago((string) $it['at'])) ?></td>
                            <td class="rmt-nowrap<?= $it['delay']['late'] ? ' rmt-late' : '' ?>"><?= $h($it['delay']['label']) ?></td>
                            <td translate="no"><?= $it['assignee_user_id'] !== null ? $h($it['assignee_label']) : '<span class="rmt-dim" translate="yes">Personne</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="rmt-cards">
            <?php foreach ($items as $it): $href = url('atak/sse/remontees/' . $it['source'] . '/' . $it['id']); ?>
                <li class="rmt-card<?= $it['delay']['late'] ? ' is-late' : '' ?>">
                    <a class="rmt-row__title" href="<?= $h($href) ?>" translate="no"><?= $h($it['title'] !== '' ? $it['title'] : $it['type_label']) ?></a>
                    <p><span class="rmt-mono"><?= $h($it['ref']) ?></span> · <?= $h($it['type_label']) ?></p>
                    <ul class="rmt-badges"><?php foreach ($it['badges'] as $b): ?><?= $badge($b) ?><?php endforeach; ?></ul>
                    <p><span translate="no"><?= $h($it['author']) ?></span> · <?= $h(P::ago((string) $it['at'])) ?> · <span class="<?= $it['delay']['late'] ? 'rmt-late' : '' ?>"><?= $h($it['delay']['label']) ?></span><?= $it['assignee_user_id'] !== null ? ' · traitant : <span translate="no">' . $h($it['assignee_label']) . '</span>' : '' ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
