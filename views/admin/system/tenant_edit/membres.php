<?php
declare(strict_types=1);
/**
 * Onglet Membres : filtre, recherche, tri et pagination sur place (GET, lecture seule).
 * Mêmes définitions de « compte » que l'annuaire et la page des comptes.
 */
$ml = is_array($platformMemberList ?? null) ? $platformMemberList : [];
$mFilter = (string) ($ml['filter'] ?? 'all');
$mQ = (string) ($ml['q'] ?? '');
$mSort = (string) ($ml['sort'] ?? 'name');
$mDir = (string) ($ml['dir'] ?? 'asc');
$mPage = (int) ($ml['page'] ?? 1);
$mPages = (int) ($ml['pages'] ?? 1);
$mTotal = (int) ($ml['total'] ?? 0);
$mLink = static function (array $over = []) use ($editUrl, $mFilter, $mQ, $mSort, $mDir, $mPage): string {
    $qs = array_merge(['filter' => $mFilter, 'q' => $mQ, 'sort' => $mSort, 'dir' => $mDir, 'page' => $mPage], $over);
    $qs = array_filter($qs, static fn ($v, $k) => $v !== '' && !($k === 'filter' && $v === 'all') && !($k === 'page' && (int) $v <= 1) && !($k === 'sort' && $v === 'name') && !($k === 'dir' && $v === 'asc'), ARRAY_FILTER_USE_BOTH);

    return $editUrl . '?' . http_build_query(['onglet' => 'membres'] + $qs);
};
$mStatus = ['active' => 'Actif', 'pending_verification' => 'En attente', 'inactive' => 'Inactif'];
?>
<section id="membres" class="pa-card scroll-mt-24">
    <p class="pa-card__kicker">Membres</p>
    <h2 class="pa-card__title">Comptes de la communauté (<?= $mTotal ?>)</h2>
    <div class="pa-sheet__flags" role="group" aria-label="Filtrer les comptes">
        <?php foreach (($platformMemberLabels ?? []) as $key => $label): ?>
            <a class="pa-sheet__flag<?= $key === $mFilter ? ' is-on' : '' ?>" href="<?= $h($mLink(['filter' => $key, 'page' => 1])) ?>"<?= $key === $mFilter ? ' aria-current="true"' : '' ?>><?= $h((string) $label) ?> (<?= (int) (($platformMemberCounts ?? [])[$key] ?? 0) ?>)</a>
        <?php endforeach; ?>
    </div>
    <form class="pa-sheet__bar" method="get" action="<?= $h($editUrl) ?>">
        <input type="hidden" name="onglet" value="membres">
        <input type="hidden" name="filter" value="<?= $h($mFilter) ?>">
        <input type="hidden" name="sort" value="<?= $h($mSort) ?>">
        <input type="hidden" name="dir" value="<?= $h($mDir) ?>">
        <label class="pa-sheet__field pa-sheet__field--grow">
            Rechercher
            <input type="search" name="q" value="<?= $h($mQ) ?>" maxlength="100" placeholder="Nom, indicatif, matricule, e-mail" autocomplete="off">
        </label>
        <button class="pa-btn pa-btn--line" type="submit">Rechercher</button>
    </form>
    <div class="pa-sheet__scroll">
        <table class="pa-sheet__grid">
            <thead>
                <tr>
                    <?php foreach (['name' => 'Nom', 'role' => 'Rôle', 'status' => 'Statut', 'login' => 'Dernière connexion'] as $key => $label): ?>
                        <?php $next = ($mSort === $key && $mDir === 'asc') ? 'desc' : 'asc'; ?>
                        <th aria-sort="<?= $mSort === $key ? ($mDir === 'asc' ? 'ascending' : 'descending') : 'none' ?>"><a class="pa-sheet__sort" href="<?= $h($mLink(['sort' => $key, 'dir' => $next, 'page' => 1])) ?>"><?= $h($label) ?></a></th>
                    <?php endforeach; ?>
                    <th>Fiche</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($platformMemberRows ?? []) === []): ?>
                    <tr><td colspan="5" class="pa-empty">Aucun compte ne correspond.</td></tr>
                <?php endif; ?>
                <?php foreach (($platformMemberRows ?? []) as $mr): ?>
                    <?php $st = (string) ($mr['status'] ?? ''); ?>
                    <tr>
                        <td class="pa-sheet__name"><?= $h((string) ($mr['display_name'] ?? '')) ?></td>
                        <td><?= $h((string) (($mr['role_name'] ?? '') !== '' ? $mr['role_name'] : '—')) ?></td>
                        <td><span class="pa-pill pa-pill--<?= $st === 'active' ? 'mint' : ($st === 'pending_verification' ? 'amber' : 'slate') ?>"><?= $h($mStatus[$st] ?? $st) ?></span></td>
                        <td><?= $h($fmtDate($mr['last_login_at'] ?? null)) ?></td>
                        <td><a class="pa-count-link" href="<?= $h(url('admin/users/' . (int) ($mr['id'] ?? 0) . '/edit')) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($mPages > 1): ?>
        <nav class="pa-sheet__flags" aria-label="Pagination">
            <?php if ($mPage > 1): ?><a class="pa-sheet__flag" href="<?= $h($mLink(['page' => $mPage - 1])) ?>">Précédent</a><?php endif; ?>
            <span class="pa-sheet__flag is-on">Page <?= $mPage ?> sur <?= $mPages ?></span>
            <?php if ($mPage < $mPages): ?><a class="pa-sheet__flag" href="<?= $h($mLink(['page' => $mPage + 1])) ?>">Suivant</a><?php endif; ?>
        </nav>
    <?php endif; ?>
    <p class="pa-card__help"><a href="<?= $h((string) ($platformMembersUrl ?? '#')) ?>">Page complète des comptes, avec export CSV</a></p>
</section>
