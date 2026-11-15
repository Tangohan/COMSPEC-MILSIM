<?php
declare(strict_types=1);
/**
 * Bloc « Liés à » d'un contenu JNET (échange, page, tâche, opération, cible) et bouton « Lier à… ».
 *
 * Chaque lien a déjà été contrôlé côté serveur avec les droits de CE lecteur sur l'objet lié (JnetLinkService) : un objet
 * qu'il ne voit pas arrive ici sans titre ni détail et s'affiche « Lien masqué ». Sans JavaScript, « Lier à… » mène à une
 * page de recherche (jnet/liens/nouveau) ; avec, le script ouvre la même recherche en fenêtre (jnet_links.js).
 *
 * @var array<string, mixed> $linkBlock ref, items, canAdd, space, newUrl, searchUrl, createUrl, removeBase, types
 * @var string $linkMode 'block' (section avec titre) ou 'inline' (ligne sous un échange ou une tâche)
 * @var string $linkUid suffixe d'identifiant unique
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lb = is_array($linkBlock ?? null) ? $linkBlock : null;
if ($lb === null) {
    return;
}
$mode = ($linkMode ?? 'block') === 'inline' ? 'inline' : 'block';
$uid = preg_replace('/[^a-z0-9_-]/i', '', (string) ($linkUid ?? 'x')) ?: 'x';
$items = is_array($lb['items'] ?? null) ? $lb['items'] : [];
$canAdd = !empty($lb['canAdd']);
$ref = (string) ($lb['ref'] ?? '');
$remove = static function (array $it) use ($h, $lb): string {
    if (empty($it['canRemove'])) {
        return '';
    }

    return '<form class="jl-rm" method="post" action="' . $h(rtrim((string) $lb['removeBase'], '/') . '/' . (int) $it['id'] . '/supprimer') . '" data-jl-remove>'
        . \App\Core\Csrf::field()
        . '<button class="jl-rm__b" type="submit" aria-label="Retirer ce lien" title="Retirer ce lien"><svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg></button></form>';
};
$addAttrs = 'href="' . $h((string) ($lb['newUrl'] ?? '#')) . '" data-jl-add data-jl-ref="' . $h($ref) . '" data-jl-space="' . (int) ($lb['space'] ?? 0) . '" data-jl-types="' . $h((string) ($lb['types'] ?? '')) . '" data-jl-search="' . $h((string) ($lb['searchUrl'] ?? '')) . '" data-jl-create="' . $h((string) ($lb['createUrl'] ?? '')) . '" data-jl-csrf="' . $h(\App\Core\Csrf::token()) . '"';
?>
<?php if ($mode === 'inline'): ?>
<div class="jl jl--inline" data-jl-block role="group" aria-label="Liés à">
    <?php if ($items !== []): ?>
        <span class="jl__lead">Liés à</span>
        <ul class="jl-chips" data-jl-list>
            <?php foreach ($items as $it): ?>
                <li class="jl-chip<?= !empty($it['hidden']) ? ' is-hidden' : '' ?>" data-jl-item>
                    <?php if (!empty($it['hidden'])): ?>
                        <span class="jl-chip__t">Lien masqué</span>
                    <?php else: ?>
                        <a class="jl-chip__t" href="<?= $h((string) $it['href']) ?>" title="<?= $h((string) $it['typeLabel']) ?>"><span class="jl-chip__k"><?= $h((string) $it['typeLabel']) ?></span><?= $h((string) $it['title']) ?></a>
                    <?php endif; ?>
                    <?= $remove($it) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($canAdd): ?><a class="uw-link jl__add" <?= $addAttrs ?>>Lier à…</a><?php endif; ?>
</div>
<?php else: ?>
<?php
/** Une ligne de lien : visible (type, titre, auteur) ou masquée (rien d'autre que « Lien masqué »). */
$row = static function (array $it) use ($h, $remove): string {
    if (!empty($it['hidden'])) {
        return '<li class="jl-row is-hidden" data-jl-item><span class="jl-row__t"><b>Lien masqué</b></span>' . $remove($it) . '</li>';
    }
    $meta = trim((string) ($it['by'] ?? '') . ((string) ($it['when'] ?? '') !== '' ? ' · ' . (string) $it['when'] : ''), ' ·');

    return '<li class="jl-row" data-jl-item><a class="jl-row__a" href="' . $h((string) $it['href']) . '"><span class="jl-row__k">' . $h((string) $it['typeLabel']) . '</span>'
        . '<span class="jl-row__t"><b>' . $h((string) $it['title']) . '</b>' . ((string) ($it['sub'] ?? '') !== '' ? '<small>' . $h((string) $it['sub']) . '</small>' : '') . '</span></a>'
        . '<span class="jl-row__m">' . $h($meta) . '</span>' . $remove($it) . '</li>';
};
$overview = is_array($lb['overview'] ?? null) ? $lb['overview'] : null;
$title = (string) ($lb['title'] ?? 'Liés à');
?>
<section class="jl<?= $overview !== null ? ' jl--overview' : '' ?>" data-jl-block aria-labelledby="jl-title-<?= $h($uid) ?>">
    <header class="jl__head">
        <h2 id="jl-title-<?= $h($uid) ?>" class="jl__title"><?= $h($title) ?></h2>
        <?php if ($canAdd): ?><a class="ja-b2 sm jl__add" <?= $addAttrs ?>>Lier à…</a><?php endif; ?>
    </header>
    <?php if ($items === []): ?>
        <p class="uw-note jl__empty" data-jl-empty><?= $h((string) ($lb['empty'] ?? 'Rien n’est lié pour l’instant.')) ?><?= $canAdd ? ' Reliez un ordre, une page, une tâche, une opération ou une unité.' : '' ?></p>
        <ul class="jl-list" data-jl-list hidden></ul>
    <?php elseif ($overview !== null): ?>
        <?php foreach ((array) $overview['groups'] as $key => $groupItems): ?>
            <h3 class="jl-group"><?= $h((string) (\App\Services\Jnet\JnetLinkRules::GROUPS[$key] ?? 'Autres')) ?> <span class="ja-mono"><?= count($groupItems) ?></span></h3>
            <ul class="jl-list" data-jl-list>
                <?php foreach ($groupItems as $it): ?><?= $row($it) ?><?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
        <?php if ((array) $overview['hidden'] !== []): ?>
            <h3 class="jl-group">Liens masqués <span class="ja-mono"><?= count((array) $overview['hidden']) ?></span></h3>
            <ul class="jl-list" data-jl-list>
                <?php foreach ((array) $overview['hidden'] as $it): ?><?= $row($it) ?><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <ul class="jl-list" data-jl-list>
            <?php foreach ($items as $it): ?><?= $row($it) ?><?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>
