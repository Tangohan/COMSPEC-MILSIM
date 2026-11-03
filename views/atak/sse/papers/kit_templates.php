<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;
use App\Support\SseIdWorkshopCatalog as W;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $templates */
/** @var array{view:bool,manage:bool,truth:bool} $rights */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/papiers')) ?>">Identités &amp; documents</a> / <strong>Kits types</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Préparation // Kits documentaires types</div>
        <h1>Kits documentaires types</h1>
        <p>Un kit type réunit les papiers d’un profil (civil, militaire, diplomate, journaliste, contractor, agent infiltré…) : pays, types de papiers, identité qui les porte, champs pré-remplis, téléphone et adresse simulés, éléments de légende. Affecté à des membres, il crée leurs papiers, leur kit de mission et leur brief de couverture.</p>
    </div>
    <div class="page-reference"><strong><?= count($templates) ?> kit(s)</strong> Réf. ATH-SSE-IDENT-KIT</div>
</div>

<?php if ($rights['manage']): ?>
    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Créer un kit type</div><div class="panel-meta">Partir d’un modèle, puis l’adapter</div></div>
        <div class="panel-body idp-actions">
            <?php foreach (W::KIT_PRESETS as $key => $preset): ?>
                <?php if ($key === 'undercover' && !$rights['truth']) { continue; } ?>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/kits-types/nouveau') . '?modele=' . $key) ?>"><?= $h(W::KIT_CATEGORIES[$key]) ?></a>
            <?php endforeach; ?>
            <a class="btn" href="<?= $h(url('atak/sse/papiers/kits-types/nouveau')) ?>">Kit vierge</a>
        </div>
    </section>
<?php endif; ?>

<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Kits de la communauté</div></div>
    <div class="panel-body">
        <?php if ($templates === []): ?>
            <p class="muted">Aucun kit type pour l’instant.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kit</th><th>Catégorie</th><th>Pays</th><th>Papiers</th><th>Identité</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr<?= $t['archived'] ? ' class="muted"' : '' ?>>
                            <td><strong><?= $h($t['name']) ?></strong> <code translate="no"><?= $h($t['ref']) ?></code><?php if ($t['archived']): ?> <span class="badge">Archivé</span><?php endif; ?></td>
                            <td><?= $h($t['category_label']) ?></td>
                            <td><?= $h($t['country_code'] ?? '—') ?></td>
                            <td><?= $h(implode(' · ', array_map(static fn (string $x): string => C::label(C::DOC_TYPES, $x), $t['doc_types']))) ?></td>
                            <td><?= $h($t['identity_mode_label']) ?></td>
                            <td class="idp-actions">
                                <a class="btn-open" href="<?= $h(url('atak/sse/papiers/kits-types/' . (int) $t['id'])) ?>">Ouvrir</a>
                                <?php if ($rights['manage']): ?>
                                    <form method="post" action="<?= $h(url('atak/sse/papiers/kits-types/' . (int) $t['id'] . '/archiver')) ?>">
                                        <?= \App\Core\Csrf::field() ?>
                                        <?php if ($t['archived']): ?><input type="hidden" name="restore" value="1"><?php endif; ?>
                                        <button class="btn btn--ghost" type="submit"><?= $t['archived'] ? 'Rétablir' : 'Archiver' ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
