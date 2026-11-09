<?php
declare(strict_types=1);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$tn = is_array($accTenant ?? null) ? $accTenant : ['id' => 0, 'name' => ''];
$rows = is_array($accRows ?? null) ? $accRows : [];
$counts = is_array($accCounts ?? null) ? $accCounts : [];
$labels = is_array($accFilterLabels ?? null) ? $accFilterLabels : [];
$filter = (string) ($accFilter ?? 'all');
$q = (string) ($accSearch ?? '');
$sort = (string) ($accSort ?? 'name');
$dir = (string) ($accDir ?? 'asc');
$page = (int) ($accPage ?? 1);
$pages = (int) ($accPages ?? 1);
$total = (int) ($accTotal ?? 0);
$base = 'admin/tenants/' . (int) $tn['id'] . '/comptes';
$link = static function (array $over = []) use ($base, $filter, $q, $sort, $dir, $page): string {
    $qs = array_merge(['filter' => $filter, 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'page' => $page], $over);
    $qs = array_filter($qs, static fn ($v, $k) => $v !== '' && !($k === 'filter' && $v === 'all') && !($k === 'page' && (int) $v <= 1), ARRAY_FILTER_USE_BOTH);

    return url($base) . ($qs === [] ? '' : '?' . http_build_query($qs));
};
$statusLabels = ['active' => 'Actif', 'pending_verification' => 'En attente', 'inactive' => 'Inactif'];
$fmt = static function (mixed $raw): string {
    $t = ($raw !== null && $raw !== '') ? strtotime((string) $raw) : false;

    return $t ? date('d/m/Y H:i', $t) : '—';
};
?>
<div class="pa pa--wide">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Comptes
            </p>
            <h1 class="pa-hero__title">Comptes de <?= $h((string) $tn['name']) ?></h1>
            <p class="pa-hero__lead"><?= $h((string) ($accFilterLabel ?? '')) ?> : <?= $total ?> compte(s).</p>
            <?php if ($q !== ''): ?>
                <p class="pa-hero__lead">Recherche : « <?= $h($q) ?> »</p>
            <?php endif; ?>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Retour aux communautés</a>
                <a class="pa-btn pa-btn--line" href="<?= $h($link(['export' => 'csv', 'page' => 1])) ?>">Exporter en CSV</a>
            </div>
        </header>
        <div class="pa-panel">
            <div class="pa-sheet__flags" role="group" aria-label="Filtrer les comptes">
                <?php foreach ($labels as $key => $label): ?>
                    <a class="pa-sheet__flag<?= $key === $filter ? ' is-on' : '' ?>" href="<?= $h($link(['filter' => $key, 'page' => 1])) ?>"<?= $key === $filter ? ' aria-current="true"' : '' ?>><?= $h($label) ?> (<?= (int) ($counts[$key] ?? 0) ?>)</a>
                <?php endforeach; ?>
            </div>
            <form class="pa-sheet__bar" method="get" action="<?= $h(url($base)) ?>">
                <input type="hidden" name="filter" value="<?= $h($filter) ?>">
                <input type="hidden" name="sort" value="<?= $h($sort) ?>">
                <input type="hidden" name="dir" value="<?= $h($dir) ?>">
                <label class="pa-sheet__field pa-sheet__field--grow">
                    Rechercher
                    <input type="search" name="q" value="<?= $h($q) ?>" maxlength="100" placeholder="Nom, indicatif, matricule, e-mail" autocomplete="off">
                </label>
                <button class="pa-btn pa-btn--line" type="submit">Rechercher</button>
            </form>
            <div class="pa-sheet__scroll">
                <table class="pa-sheet__grid">
                    <thead>
                        <tr>
                            <?php
                            $heads = ['name' => 'Nom', 'role' => 'Rôle', 'status' => 'Statut', 'login' => 'Dernière connexion', 'created' => 'Création'];
                            foreach ($heads as $key => $label):
                                $next = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
                                ?>
                                <th aria-sort="<?= $sort === $key ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>"><a class="pa-sheet__sort" href="<?= $h($link(['sort' => $key, 'dir' => $next, 'page' => 1])) ?>"><?= $h($label) ?></a></th>
                                <?php if ($key === 'name'): ?><th>Indicatif · matricule</th><th>E-mail</th><?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="7" class="pa-empty">Aucun compte ne correspond.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $st = (string) ($r['status'] ?? '');
                            $idBits = trim((string) ($r['callsign'] ?? '') . ' · ' . (string) ($r['member_number'] ?? ''), ' ·');
                            ?>
                            <tr>
                                <td class="pa-sheet__name"><?= $h((string) ($r['display_name'] ?? '')) ?></td>
                                <td><?= $h($idBits !== '' ? $idBits : '—') ?></td>
                                <td><?= $h((string) ($r['email'] ?? '')) ?></td>
                                <td><?= $h((string) (($r['role_name'] ?? '') !== '' ? $r['role_name'] : '—')) ?></td>
                                <td><span class="pa-pill pa-pill--<?= $st === 'active' ? 'mint' : ($st === 'pending_verification' ? 'amber' : 'slate') ?>"><?= $h($statusLabels[$st] ?? $st) ?></span></td>
                                <td><?= $h($fmt($r['last_login_at'] ?? null)) ?></td>
                                <td><?= $h($fmt($r['created_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="pa-sheet__flags" aria-label="Pagination">
                    <?php if ($page > 1): ?><a class="pa-sheet__flag" href="<?= $h($link(['page' => $page - 1])) ?>">Précédent</a><?php endif; ?>
                    <span class="pa-sheet__flag is-on">Page <?= $page ?> sur <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="pa-sheet__flag" href="<?= $h($link(['page' => $page + 1])) ?>">Suivant</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
