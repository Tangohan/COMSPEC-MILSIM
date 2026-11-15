<?php
/**
 * Bouton « Épingler » / « Désépingler » d'un élément, pour ceux qui gèrent les épingles (publier au niveau de l'organisation).
 * Le serveur revérifie le droit, la visibilité de l'élément et le plafond ; ceci n'est qu'une commodité.
 *
 * @var bool $canPin
 * @var string $salon salon de retour (validé par le serveur)
 * @var string $pinType type de l'élément (echange, renseignement, cible, unite, page)
 * @var string $pinTarget identifiant de l'élément
 * @var int $pinId identifiant de l'épingle si l'élément est déjà épinglé, sinon 0
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<?php if (!empty($canPin)): ?>
    <?php if ((int) $pinId > 0): ?>
        <form class="jc-pinform" method="post" action="<?= $h(url('jnet/commun/epingles/' . (int) $pinId . '/retirer')) ?>" data-jc-pin>
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="salon" value="<?= $h($salon) ?>">
            <button type="submit" class="jc-tool is-on" aria-pressed="true" title="Retirer cet élément des épinglés"><?= JnetCommonRules::icon('pin') ?><span class="jc-tool__t">Désépingler</span></button>
        </form>
    <?php else: ?>
        <form class="jc-pinform" method="post" action="<?= $h(url('jnet/commun/epingles')) ?>" data-jc-pin>
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="salon" value="<?= $h($salon) ?>">
            <input type="hidden" name="type" value="<?= $h($pinType) ?>">
            <input type="hidden" name="id" value="<?= $h($pinTarget) ?>">
            <button type="submit" class="jc-tool" aria-pressed="false" title="Épingler cet élément dans l’espace commun"><?= JnetCommonRules::icon('pin') ?><span class="jc-tool__t">Épingler</span></button>
        </form>
    <?php endif; ?>
<?php endif; ?>
