<?php
declare(strict_types=1);

/** @var string $searchQuery */
/** @var list<array<string, mixed>> $searchAccounts */
/** @var list<array<string, mixed>> $searchCommunities */
/** @var string|null $searchError */
/** @var bool $searchTooShort */
$q = (string) ($searchQuery ?? '');
$accounts = is_array($searchAccounts ?? null) ? $searchAccounts : [];
$communities = is_array($searchCommunities ?? null) ? $searchCommunities : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$searched = $q !== '' && empty($searchTooShort);
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-hero__kicker">Administration du site</p>
            <h1 class="pa-hero__title">Recherche</h1>
            <p class="pa-hero__lead">Retrouvez un compte ou une communauté sur tout le site, puis ouvrez directement l’outil qui convient.</p>
            <form class="pa-search" method="get" action="<?= $h(url('admin/recherche')) ?>" role="search">
                <label for="pa-search-q" class="ath-sr-only">Rechercher un compte ou une communauté</label>
                <input id="pa-search-q" name="q" type="search" value="<?= $h($q) ?>" autocomplete="off" spellcheck="false" autofocus
                       placeholder="E-mail, pseudo, indicatif, identifiant Steam, nom ou code de communauté, numéro…">
                <button type="submit" class="pa-btn pa-btn--solid">Rechercher</button>
            </form>
        </header>

        <div class="pa-panel">
            <?php if (!empty($searchError)): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $searchError) ?></p>
            <?php elseif (!empty($searchTooShort)): ?>
                <p class="pa-flash pa-flash--warn">Saisissez au moins deux caractères, ou un numéro.</p>
            <?php elseif ($q === ''): ?>
                <p class="pa-empty-state">Un e-mail complet ou un identifiant Steam donne le résultat le plus précis. Un numéro cherche aussi la fiche qui porte ce numéro.</p>
            <?php endif; ?>

            <?php if ($searched && empty($searchError)): ?>
            <section class="pa-card" aria-labelledby="search-accounts">
                <div class="pa-card__head">
                    <div>
                        <h2 id="search-accounts" class="pa-card__title">Comptes <span class="pa-num"><?= count($accounts) ?></span></h2>
                    </div>
                </div>
                <?php if ($accounts === []): ?>
                    <p class="pa-empty-state">Aucun compte ne correspond à « <?= $h($q) ?> ».</p>
                <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table pa-table--search">
                        <thead><tr><th scope="col">Personne</th><th scope="col">Communauté</th><th scope="col">État</th><th scope="col">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <?php
                            $id = (int) $a['id'];
                            $name = (string) ($a['display_name'] ?: ($a['callsign'] ?: $a['email']));
                            $meta = array_values(array_filter([
                                (string) $a['email'],
                                $a['callsign'] !== '' ? 'indicatif ' . $a['callsign'] : '',
                                $a['steam_id'] !== '' ? 'Steam ' . $a['steam_id'] : '',
                                'n° ' . $id,
                            ]));
                            ?>
                            <tr>
                                <td><span class="pa-name"><?= $h($name) ?></span><span class="pa-sub"><?= $h(implode(' · ', $meta)) ?></span></td>
                                <td><?= $h((string) ($a['community'] ?: '—')) ?></td>
                                <td><span class="pa-pill <?= $a['state'] === 'Compte actif' ? 'pa-pill--mint' : 'pa-pill--slate' ?>"><?= $h((string) $a['state']) ?></span></td>
                                <td><div class="pa-actions">
                                    <a class="pa-btn pa-btn--sm pa-btn--line" href="<?= $h(url('admin/users/' . $id . '/edit')) ?>">Ouvrir le compte</a>
                                    <?php if ((string) $a['email'] !== ''): ?>
                                    <a class="pa-btn pa-btn--sm pa-btn--ghost" href="<?= $h(url('admin/users/person') . '?email=' . rawurlencode((string) $a['email'])) ?>">Tous ses comptes</a>
                                    <?php endif; ?>
                                    <?php if ((string) $a['steam_id'] !== ''): ?>
                                    <a class="pa-btn pa-btn--sm pa-btn--ghost" href="<?= $h(url('admin/parc-atak') . '?q=' . rawurlencode((string) $a['steam_id'])) ?>">Ses ATAK</a>
                                    <?php endif; ?>
                                    <a class="pa-btn pa-btn--sm pa-btn--ghost" href="<?= $h(url('admin/audit') . '?user_id=' . $id) ?>">Journal</a>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <section class="pa-card" aria-labelledby="search-communities">
                <div class="pa-card__head">
                    <div>
                        <h2 id="search-communities" class="pa-card__title">Communautés <span class="pa-num"><?= count($communities) ?></span></h2>
                    </div>
                </div>
                <?php if ($communities === []): ?>
                    <p class="pa-empty-state">Aucune communauté ne correspond à « <?= $h($q) ?> ».</p>
                <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table pa-table--search">
                        <thead><tr><th scope="col">Communauté</th><th scope="col">Code</th><th scope="col">État</th><th scope="col">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($communities as $c): ?>
                            <?php $id = (int) $c['id']; ?>
                            <tr>
                                <td><span class="pa-name"><?= $h((string) $c['name']) ?></span><span class="pa-sub"><?= $h((string) $c['slug']) ?> · n° <?= $id ?></span></td>
                                <td><?= $h((string) ($c['community_code'] ?: '—')) ?></td>
                                <td><span class="pa-pill <?= $c['archived'] ? 'pa-pill--amber' : 'pa-pill--mint' ?>"><?= $c['archived'] ? 'Archivée' : 'Active' ?></span></td>
                                <td><div class="pa-actions">
                                    <a class="pa-btn pa-btn--sm pa-btn--line" href="<?= $h(url('admin/tenants/' . $id . '/edit')) ?>">Ouvrir la fiche</a>
                                    <a class="pa-btn pa-btn--sm pa-btn--ghost" href="<?= $h(url('admin/system/tenants/' . $id . '/intervention')) ?>">Intervenir</a>
                                    <a class="pa-btn pa-btn--sm pa-btn--ghost" href="<?= $h(url('admin/parc-atak') . '?communaute=' . $id) ?>">Ses ATAK</a>
                                    <a class="pa-btn pa-btn--sm pa-btn--ghost" href="<?= $h(url('admin/audit') . '?tenant_id=' . $id) ?>">Journal</a>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>
