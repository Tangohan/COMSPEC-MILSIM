<?php
/**
 * Salon « Épinglés » : tous les éléments épinglés dans l'ordre choisi, avec la date et l'auteur de l'épinglage. Chaque élément
 * est revérifié avec les droits du lecteur : s'il ne le voit pas, « Élément masqué » (sans titre ni type). Monter, descendre et
 * retirer sont réservés à qui publie au niveau de l'organisation ; le glisser-déposer (jnet_commun.js) est facultatif.
 *
 * @var array<string, mixed> $cm
 * @var list<array<string, mixed>> $pins
 * @var bool $canPin
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$max = (int) ($cm['pinsMax'] ?? 25);
$count = count($pins);
$salon = 'epingles';
$moveForm = static function (int $id, string $dir, string $label, string $icon, bool $disabled) use ($h): string {
    return '<form method="post" action="' . $h(url('jnet/commun/epingles/' . $id . '/deplacer')) . '" data-jc-pin>' . \App\Core\Csrf::field()
        . '<input type="hidden" name="salon" value="epingles"><input type="hidden" name="sens" value="' . $h($dir) . '">'
        . '<button type="submit" class="jc-tool jc-tool--ic"' . ($disabled ? ' disabled' : '') . ' title="' . $h($label) . '">' . JnetCommonRules::icon($icon) . '<span class="ja-sr">' . $h($label) . '</span></button></form>';
};
?>
<div class="jc-body">
    <?php if (empty($cm['pinsReady'])): ?>
        <div class="jc-empty">
            <p><strong>Les épingles ne sont pas encore activées.</strong></p>
            <p>Un administrateur doit appliquer les migrations.</p>
        </div>
    <?php else: ?>
        <p class="jc-counter"><b><?= $count ?></b> sur <?= $max ?> épinglés</p>
        <?php if ($pins === []): ?>
            <div class="jc-empty">
                <p><strong>Rien d’épinglé pour le moment.</strong></p>
                <p><?= $canPin ? 'Utilisez « Épingler » sur une annonce, un renseignement, une cible ou une unité : l’élément apparaîtra ici et en haut de son salon.' : 'L’encadrement qui publie pour toute l’organisation peut épingler des annonces, renseignements, cibles, unités et pages.' ?></p>
            </div>
        <?php else: ?>
            <ol class="jc-pins" data-jc-pins data-jc-order-url="<?= $h(url('jnet/commun/epingles/ordre')) ?>"<?= $canPin ? ' data-jc-sortable' : '' ?>>
                <?php foreach ($pins as $i => $p): ?>
                    <li class="jc-pin<?= empty($p['visible']) ? ' is-masked' : '' ?>" data-jc-pin-id="<?= (int) $p['id'] ?>"<?= $canPin ? ' draggable="true"' : '' ?>>
                        <?php if ($canPin): ?><span class="jc-pin__grip" aria-hidden="true" title="Glisser pour changer l’ordre"><?= JnetCommonRules::icon('grip') ?></span><?php endif; ?>
                        <span class="jc-pin__rank" aria-hidden="true"><?= $i + 1 ?></span>
                        <div class="jc-pin__main">
                            <?php if (!empty($p['visible'])): ?>
                                <span class="jc-tag"><?= $h((string) $p['typeLabel']) ?></span>
                                <a class="jc-pin__t" href="<?= $h((string) $p['href']) ?>"><?= $h((string) $p['title']) ?></a>
                                <?php if (trim((string) $p['sub']) !== ''): ?><span class="jc-pin__sub"><?= $h((string) $p['sub']) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="jc-pin__t is-masked"><?= JnetCommonRules::icon('lock') ?><span>Élément masqué</span></span>
                                <span class="jc-pin__sub">Vous n’avez pas accès à cet élément.</span>
                            <?php endif; ?>
                            <?php $pinnedTs = strtotime((string) $p['at']) ?: 0; ?>
                            <span class="jc-pin__by"><span>Épinglé</span>
                                <?php if ((string) $p['by'] !== ''): ?><span>par</span> <b translate="no"><?= $h((string) $p['by']) ?></b><?php endif; ?>
                                <?php if ($pinnedTs > 0): ?><span>le</span> <time datetime="<?= $h((string) $p['at']) ?>"><?= $h(date('d/m/Y', $pinnedTs)) ?></time><?php endif; ?></span>
                        </div>
                        <?php if ($canPin): ?>
                            <div class="jc-pin__tools">
                                <?= $moveForm((int) $p['id'], 'haut', 'Monter', 'up', $i === 0) ?>
                                <?= $moveForm((int) $p['id'], 'bas', 'Descendre', 'down', $i === $count - 1) ?>
                                <form method="post" action="<?= $h(url('jnet/commun/epingles/' . (int) $p['id'] . '/retirer')) ?>" data-jc-pin>
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="salon" value="epingles">
                                    <button type="submit" class="jc-tool is-on" title="Retirer cet élément des épinglés"><?= JnetCommonRules::icon('pin') ?><span class="jc-tool__t">Désépingler</span></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($canPin && $count < $max): ?>
            <div class="jc-finder" data-jc-finder data-url="<?= $h(url('jnet/liens/recherche')) ?>" data-action="<?= $h(url('jnet/commun/epingles')) ?>" data-label="Épingler" hidden>
                <h2 class="jc-finder__t">Épingler une unité ou une page</h2>
                <label class="jc-search">
                    <span class="ja-sr">Chercher une unité ou une page à épingler</span>
                    <?= JnetCommonRules::icon('search') ?>
                    <input type="search" placeholder="Nom de l’unité ou titre de la page" autocomplete="off" data-jc-finder-q>
                </label>
                <ul class="jc-finder__list" data-jc-finder-list></ul>
                <span class="jc-finder__csrf" hidden><?= \App\Core\Csrf::field() ?></span>
            </div>
        <?php elseif ($canPin): ?>
            <p class="jc-note">Vous avez atteint la limite de <?= $max ?> éléments épinglés : retirez-en un avant d’en ajouter.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
