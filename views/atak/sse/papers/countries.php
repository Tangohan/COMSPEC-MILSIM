<?php
declare(strict_types=1);

use App\Support\SseIdCountryCatalog as K;
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $countries */
/** @var array<string,int> $used */
$canManage = !empty($canManage);
require __DIR__ . '/_subnav.php';
$sseExtraStyles .= '<link rel="stylesheet" href="' . $h(asset_url('assets/css/back-office-member-papers.css')) . '">';
?>

<div class="page-heading">
    <div>
        <h1>Pays émetteurs</h1>
        <p>Les pays qui délivrent les papiers de jeu : nom, code, format des numéros, langues, univers. Par défaut, des pays imaginaires ; une communauté qui joue un univers réaliste peut ajouter des pays réels stylisés. Un pays qui a déjà émis des papiers s’archive au lieu d’être supprimé.</p>
    </div>
</div>

<form class="toolbar" method="get" action="<?= $h(url('atak/sse/papiers/pays')) ?>">
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" type="search" value="<?= $h($q ?? '') ?>" placeholder="Code, nom, autorité…">
    </div>
    <div class="toolbar-actions">
        <button class="btn" type="submit">Appliquer</button>
        <?php if ($canManage): ?>
            <a class="btn" href="<?= $h(url('atak/sse/papiers/pays/nouveau')) ?>">Nouveau pays</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/pays/reels')) ?>">Ajouter des pays réels (simulation)</a>
        <?php endif; ?>
    </div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Registre des pays</div>
        <div class="panel-meta"><?= count($countries) ?> pays</div>
    </div>
    <?php if ($countries === []): ?>
        <div class="empty-state"><div class="empty-state-inner"><strong>Aucun pays</strong><p>Aucun pays ne correspond à cette recherche.</p></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pays</th><th>Code</th><th>Univers</th><th>Papiers délivrés</th><th>Format du numéro</th><th>Papiers</th><th>État</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($countries as $c): ?>
                    <tr>
                        <td>
                            <?php if (!empty($c['real_sim'])): ?>
                                <img class="idm-flag" src="<?= $h((string) \App\Support\SseIdRealCountryCatalog::flagDataUri((string) $c['code'])) ?>" alt="">
                            <?php elseif ($c['flag_path'] !== null): ?>
                                <img class="idm-flag" src="<?= $h(url($c['flag_path'])) ?>" alt="">
                            <?php elseif ($c['color_primary'] !== null): ?>
                                <span class="idm-swatch" aria-hidden="true"><span style="background:<?= $h($c['color_primary']) ?>"></span><span style="background:<?= $h($c['color_secondary'] ?? $c['color_primary']) ?>"></span></span>
                            <?php endif; ?>
                            <strong><?= $h($c['name']) ?></strong>
                            <?php if (!empty($c['real_sim'])): ?> <span class="badge badge--red" title="Pays réel stylisé, univers réaliste-simulation : papiers de jeu marqués SIMULATION">Réel stylisé · simulation</span><?php elseif ($c['fictional']): ?> <span class="badge badge--amber">Fictif</span><?php endif; ?>
                        </td>
                        <td translate="no"><?= $h($c['code']) ?></td>
                        <td><?= $h(C::label(K::THEMES, $c['theme'])) ?></td>
                        <td><?= $h(implode(', ', array_map(static fn (string $t): string => C::label(C::DOC_TYPES, $t), $c['doc_types']))) ?></td>
                        <td translate="no" style="font-family:monospace"><?= $h($c['number_format']) ?></td>
                        <td><?= (int) ($used[$c['code']] ?? 0) ?></td>
                        <td><?= $c['active'] ? 'Actif' : 'Archivé' ?></td>
                        <td>
                            <?php if ($canManage): ?>
                                <a class="btn-open" href="<?= $h(url('atak/sse/papiers/pays/' . (int) $c['id'])) ?>">Modifier</a>
                                <form method="post" action="<?= $h(url('atak/sse/papiers/pays/' . (int) $c['id'] . '/archiver')) ?>" style="display:inline">
                                    <?= \App\Core\Csrf::field() ?>
                                    <?php if (!$c['active']): ?><input type="hidden" name="restore" value="1"><?php endif; ?>
                                    <button class="btn btn--ghost" type="submit"><?= $c['active'] ? 'Archiver' : 'Rétablir' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
