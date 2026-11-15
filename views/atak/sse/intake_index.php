<?php
declare(strict_types=1);
ob_start();

use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakePresenter as P;

/**
 * Bureau SSE — file des remontées (comptes rendus et fiches) à prendre en compte, avec les mêmes pastilles que le
 * back-office. Une fiche s'ouvre sur sa page (onglet « Suivi »), un compte rendu sur sa page de suivi.
 *
 * @var bool $intakeReady
 * @var list<array<string, mixed>> $intakeItems
 * @var array<string, int> $intakeBadgeCounts
 * @var array{open: int, closed: int, mine: int} $intakeCounts
 * @var string $intakeTab
 * @var string $intakeBadge
 * @var string $intakeSort
 * @var string $intakeDir
 * @var string $intakeQuery
 * @var bool $intakeBackOffice
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = is_array($intakeItems ?? null) ? $intakeItems : [];
$tab = (string) ($intakeTab ?? 'open');
$badgeFilter = (string) ($intakeBadge ?? '');
$sort = (string) ($intakeSort ?? 'recu');
$dir = (string) ($intakeDir ?? 'desc');
$q = (string) ($intakeQuery ?? '');
$link = static function (array $over) use ($tab, $badgeFilter, $sort, $dir, $q): string {
    $p = array_filter(array_merge(['etat' => $tab === 'open' ? '' : $tab, 'pastille' => $badgeFilter, 'tri' => $sort === 'recu' ? '' : $sort, 'ordre' => $dir === 'desc' ? '' : $dir, 'q' => $q], $over), static fn ($v): bool => $v !== '' && $v !== null);

    return url('atak/sse/remontees' . ($p !== [] ? '?' . http_build_query($p) : ''));
};
$sortLink = static function (string $key, string $label) use ($link, $sort, $dir, $h): string {
    $active = $sort === $key;

    return '<a href="' . $h($link(['tri' => $key, 'ordre' => $active && $dir === 'desc' ? 'asc' : 'desc'])) . '" title="Trier par ' . $h(mb_strtolower($label)) . '">' . $h($label) . ($active ? ' <span aria-hidden="true">' . ($dir === 'asc' ? '↑' : '↓') . '</span>' : '') . '</a>';
};
$chipTone = ['danger' => 'critical', 'warn' => 'warning', 'caution' => 'caution'];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiche-rens.css')) . '">';
?>
<div class="fnr">
    <div class="page-heading">
        <div>
            <h1>Remontées</h1>
            <p>Comptes rendus et fiches arrivés du terrain : qui les a pris en compte, depuis quand, et ce qui attend encore.</p>
        </div>
        <?php if (!empty($intakeBackOffice)): ?>
            <a class="btn btn--ghost" href="<?= $h(url('back-office/remontees')) ?>">Ouvrir le back-office Remontées</a>
        <?php endif; ?>
    </div>

    <?php if (empty($intakeReady)): ?>
        <p class="muted">Le suivi des remontées arrive avec la mise à jour de la base.</p>
    <?php else: ?>
    <ul class="fnr-queue-tabs" aria-label="État">
        <?php foreach (['open' => ['Ouvertes', $intakeCounts['open'] ?? 0], 'mine' => ['À moi', $intakeCounts['mine'] ?? 0], 'closed' => ['Traitées', $intakeCounts['closed'] ?? 0], 'all' => ['Toutes', ($intakeCounts['open'] ?? 0) + ($intakeCounts['closed'] ?? 0)]] as $k => [$label, $n]): ?>
            <li><a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= $h($link(['etat' => $k === 'open' ? '' : $k])) ?>"<?= $tab === $k ? ' aria-current="true"' : '' ?>><?= $h($label) ?> <strong><?= (int) $n ?></strong></a></li>
        <?php endforeach; ?>
        <?php foreach (IntelIntakeDesk::BADGE_FILTERS as $k => $label): $n = (int) ($intakeBadgeCounts[$k] ?? 0); if ($n === 0 && $badgeFilter !== $k) { continue; } ?>
            <li><a class="<?= $badgeFilter === $k ? 'is-active' : '' ?>" href="<?= $h($link(['pastille' => $badgeFilter === $k ? '' : $k])) ?>"<?= $badgeFilter === $k ? ' aria-current="true"' : '' ?>><?= $h($label) ?> <strong><?= $n ?></strong></a></li>
        <?php endforeach; ?>
    </ul>

    <section class="panel fnr-queue">
        <div class="panel-body">
            <form method="get" action="<?= $h(url('atak/sse/remontees')) ?>" class="sse-filter-row sse-desk-filters" role="search">
                <?php if ($tab !== 'open'): ?><input type="hidden" name="etat" value="<?= $h($tab) ?>"><?php endif; ?>
                <label class="sr-only" for="rm-q">Rechercher une remontée</label>
                <input id="rm-q" type="search" name="q" value="<?= $h($q) ?>" placeholder="Référence, texte ou indicatif">
                <button class="btn" type="submit">Rechercher</button>
                <?php if ($q !== '' || $badgeFilter !== ''): ?><a class="btn btn--ghost" href="<?= $h(url('atak/sse/remontees')) ?>">Tout afficher</a><?php endif; ?>
            </form>
        </div>
        <?php if ($items === []): ?>
            <div class="panel-body"><p class="muted">Aucune remontée pour ces critères.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Remontée</th>
                            <th scope="col"><?= $sortLink('etat', 'État') ?> · <?= $sortLink('urgence', 'Urgence') ?></th>
                            <th scope="col"><?= $sortLink('emetteur', 'Émetteur') ?></th>
                            <th scope="col"><?= $sortLink('recu', 'Reçue') ?></th>
                            <th scope="col"><?= $sortLink('attente', 'Attente') ?></th>
                            <th scope="col"><?= $sortLink('traitant', 'Traitant') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): $href = $it['source'] === 'fiche' ? url('atak/sse/fiches/' . (int) $it['id']) . '#suivi' : url('atak/sse/remontees/cr/' . (int) $it['id']); ?>
                            <tr>
                                <td>
                                    <a class="fnr-queue__title" href="<?= $h($href) ?>" translate="no"><?= $h($it['title'] !== '' ? $it['title'] : $it['type_label']) ?></a>
                                    <span class="fnr-queue__ref"><span class="fnr-mono"><?= $h($it['ref']) ?></span> · <?= $h($it['type_label']) ?></span>
                                </td>
                                <td>
                                    <ul class="fnr-chips">
                                        <?php foreach ($it['badges'] as $b): if (in_array($b['key'], ['origine', 'lectures'], true)) { continue; } ?>
                                            <li class="fnr-chip<?= isset($chipTone[$b['tone']]) ? ' fnr-chip--' . $h($chipTone[$b['tone']]) : '' ?>"<?= $b['title'] !== '' ? ' title="' . $h($b['title']) . '"' : '' ?>><?= $h($b['label']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td translate="no"><?= $h($it['author']) ?></td>
                                <td title="<?= $h(P::when((string) $it['at'])) ?>"><?= $h(P::ago((string) $it['at'])) ?></td>
                                <td class="<?= $it['delay']['late'] ? 'is-late' : '' ?>"><?= $h($it['delay']['label']) ?></td>
                                <td translate="no"><?= $it['assignee_user_id'] !== null ? $h($it['assignee_label']) : '<span class="muted" translate="yes">Personne</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
