<?php
declare(strict_types=1);

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $profiles */
/** @var array{q:string,side:string,category:string} $filters */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
$canEdit = $rights['manage'] && $rights['truth'];
$categories = ['civilian' => 'Civil', 'military' => 'Militaire', 'suspect' => 'Suspect', 'hvt' => 'HVT'];
$sides = ['civ' => 'Civils', 'west' => 'BLUFOR', 'east' => 'OPFOR', 'guer' => 'Indépendants'];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-workshop.css')) . '">';
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <strong>Profils IA</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Préparation // Profils IA</div>
        <h1>Profils IA</h1>
        <p>Une personne prête à être reliée à une IA en jeu, depuis Zeus ou Eden : son identité et ses papiers viennent du registre, un contrôle la reconnaît sur Athena.</p>
    </div>
    <div class="page-reference"><strong><?= count($profiles) ?> profil(s)</strong> Réf. ATH-SSE-IDENT-PRF</div>
</div>

<form class="panel idw-form" method="get" action="<?= $h(url('atak/sse/papiers/profils-ia')) ?>">
    <div class="panel-body idp-statuses">
        <div><label for="f-q">Rechercher</label><input id="f-q" type="search" name="q" value="<?= $h($filters['q']) ?>" placeholder="Nom, libellé"></div>
        <div><label for="f-side">Camp</label><select id="f-side" name="side"><option value="">Tous</option><?php foreach ($sides as $k => $l): ?><option value="<?= $h($k) ?>" <?= $filters['side'] === $k ? 'selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select></div>
        <div><label for="f-cat">Catégorie</label><select id="f-cat" name="category"><option value="">Toutes</option><?php foreach ($categories as $k => $l): ?><option value="<?= $h($k) ?>" <?= $filters['category'] === $k ? 'selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select></div>
        <div><button class="button" type="submit">Filtrer</button></div>
    </div>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Profils</div>
        <?php if ($canEdit): ?><a class="button primary" href="<?= $h(url('atak/sse/papiers/profils-ia/nouveau')) ?>">Nouveau profil</a><?php endif; ?>
    </div>
    <?php if ($profiles === []): ?>
        <div class="panel-body"><p>Aucun profil pour le moment. Créez-en un, ou laissez Zeus générer une identité à la pose de l’IA.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th scope="col">Réf.</th><th scope="col">Libellé</th><th scope="col">Personne</th><th scope="col">Camp</th><th scope="col">Catégorie</th><th scope="col">Papiers</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($profiles as $p): ?>
                <tr>
                    <td><a class="link" href="<?= $h(url('atak/sse/papiers/profils-ia/' . (int) $p['id'])) ?>"><?= $h($p['ref']) ?></a></td>
                    <td><?= $h($p['label']) ?></td>
                    <td><?= $p['identity_id'] > 0 ? $h(trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) : '<em>Générée en jeu</em>' ?></td>
                    <td><?= $h($sides[$p['side']] ?? $p['side']) ?></td>
                    <td><?= $h($categories[$p['category']] ?? $p['category']) ?></td>
                    <td><?= (int) ($p['documents_count'] ?? 0) ?></td>
                    <td>
                        <?php if ($canEdit): ?>
                            <form method="post" action="<?= $h(url('atak/sse/papiers/profils-ia/' . (int) $p['id'] . '/dupliquer')) ?>" style="display:inline"><?= \App\Core\Csrf::field() ?><button class="button" type="submit">Dupliquer</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
