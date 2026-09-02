<?php
declare(strict_types=1);

$doctrineRows = $doctrineRows ?? [];
$categories = $categories ?? [];
$doctrineCategory = $doctrineCategory ?? null;
$search = (string) ($search ?? '');
$doctrineQuick = (string) ($doctrineQuick ?? '');
$canManage = !empty($canManage);
$baseListUrl = url('documents') . '?category=' . (int) ($doctrineCategory['id'] ?? 0);
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/doctrine-referential.css'), ENT_QUOTES, 'UTF-8') ?>">

<div class="doctrine-ref min-h-screen bg-slate-100 text-slate-900" data-doctrine-ref>
    <div class="mx-auto max-w-[1800px] px-4 py-8 sm:px-6 lg:px-8">
        <section class="doctrine-ref__hero">
            <p class="doctrine-ref__kicker">ATHENA · Documentation de référence</p>
            <h1 class="doctrine-ref__title">Référentiel doctrinal</h1>
            <p class="doctrine-ref__lead">
                Registre institutionnel des doctrines publiées : références officielles, versions, diffusion et prise en compte.
            </p>
            <?php if ($canManage): ?>
            <div class="doctrine-ref__hero-actions">
                <a href="<?= url('back-office/documents/doctrine/create') ?>" class="doctrine-ref__btn doctrine-ref__btn--primary">Nouvelle doctrine</a>
                <a href="<?= url('back-office/documents/nomenclature') ?>" class="doctrine-ref__btn">Nomenclature</a>
                <a href="<?= url('back-office/documents/compliance') ?>" class="doctrine-ref__btn">Suivi des prises en compte</a>
            </div>
            <?php endif; ?>
        </section>

        <section class="doctrine-ref__toolbar">
            <nav class="doctrine-ref__type-nav" aria-label="Types de documents">
                <a href="<?= url('documents') ?>" class="doctrine-ref__type-link">Tous</a>
                <?php foreach ($categories as $cat): ?>
                    <?php $cid = (int) ($cat['id'] ?? 0); ?>
                    <a href="<?= url('documents') . '?category=' . $cid ?>"
                       class="doctrine-ref__type-link<?= ($doctrineCategory !== null && $cid === (int) ($doctrineCategory['id'] ?? 0)) ? ' is-active' : '' ?>">
                        <?= htmlspecialchars((string) ($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form method="get" action="<?= htmlspecialchars(url('documents'), ENT_QUOTES, 'UTF-8') ?>" class="doctrine-ref__search">
                <?php if ($doctrineCategory !== null): ?>
                <input type="hidden" name="category" value="<?= (int) ($doctrineCategory['id'] ?? 0) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Référence, titre, domaine…" class="doctrine-ref__search-input">
                <button type="submit" class="doctrine-ref__btn doctrine-ref__btn--primary">Rechercher</button>
            </form>
        </section>

        <section class="doctrine-ref__quick">
            <?php
            $quickLinks = [
                '' => 'Tous',
                'action' => 'À prendre en compte',
                'current' => 'À jour',
                'new' => 'Nouveaux',
                'archived' => 'Archivés',
            ];
            foreach ($quickLinks as $key => $label):
                $href = $baseListUrl . ($key !== '' ? '&doctrine_filter=' . rawurlencode($key) : '');
                if ($search !== '') {
                    $href .= '&q=' . rawurlencode($search);
                }
            ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="doctrine-ref__quick-link<?= $doctrineQuick === $key ? ' is-active' : '' ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </section>

        <section class="doctrine-ref__table-wrap">
            <table class="doctrine-ref__table" data-doctrine-table>
                <thead>
                    <tr>
                        <th data-sort="reference">Référence</th>
                        <th data-sort="title">Document</th>
                        <th data-sort="domain">Domaine</th>
                        <th data-sort="version">Version</th>
                        <th data-sort="authority">Autorité</th>
                        <th data-sort="diffusion">Diffusion</th>
                        <th data-sort="published">Publication</th>
                        <th data-sort="updated">Dernière modif.</th>
                        <th>Exigence</th>
                        <th data-sort="status">Mon statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($doctrineRows === []): ?>
                    <tr><td colspan="11" class="doctrine-ref__empty">Aucune doctrine publiée ne correspond aux filtres.</td></tr>
                    <?php else: ?>
                    <?php foreach ($doctrineRows as $row): ?>
                    <?php
                    $badge = $row['status_badge'] ?? [];
                    $tone = (string) ($badge['tone'] ?? 'neutral');
                    $pub = (string) ($row['published_at'] ?? '');
                    $upd = (string) ($row['updated_at'] ?? '');
                    ?>
                    <tr class="doctrine-ref__row" data-tone="<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>">
                        <td class="doctrine-ref__cell-ref"><code><?= htmlspecialchars((string) ($row['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['domain'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['authority'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['diffusion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $pub !== '' ? htmlspecialchars(date('d/m/Y', strtotime($pub)), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td><?= $upd !== '' ? htmlspecialchars(date('d/m/Y', strtotime($upd)), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td><?= !empty($row['reading_required']) ? 'Oui' : 'Info' ?></td>
                        <td><span class="doctrine-ref__badge doctrine-ref__badge--<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($badge['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><a href="<?= htmlspecialchars((string) ($row['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="doctrine-ref__link">Consulter</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <div class="doctrine-ref__mobile-list md:hidden">
            <?php foreach ($doctrineRows as $row): ?>
            <?php $badge = $row['status_badge'] ?? []; ?>
            <article class="doctrine-ref__card">
                <code class="doctrine-ref__card-ref"><?= htmlspecialchars((string) ($row['reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                <h2 class="doctrine-ref__card-title"><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="doctrine-ref__card-meta"><?= htmlspecialchars((string) ($row['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($badge['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <a href="<?= htmlspecialchars((string) ($row['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="doctrine-ref__btn doctrine-ref__btn--primary">Consulter</a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/doctrine-referential.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
