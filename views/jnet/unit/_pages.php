<?php
declare(strict_types=1);
/**
 * Rubrique « Pages » : la liste des pages écrites ensemble par les membres de l'unité, en lignes de registre.
 *
 * @var array<string, mixed> $usw
 * @var string $base
 * @var string $label
 */
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitWorkspaceService as WS;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pages = is_array($usw['pages'] ?? null) ? $usw['pages'] : [];
$canPost = !empty($usw['canPost']);
$pad2 = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
$icon = static fn (string $d): string => '<svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
$sortable = $canPost && count($pages) > 1;
?>
<header class="us-page">
    <h1 class="ja-disp ja-disp--md">Écrites ensemble.</h1>
    <p>Chaque membre peut créer une page, la modifier, y poser une tâche, un sondage ou un commentaire.</p>
    <?php if ($canPost): ?>
        <form class="uw-newpage" method="post" action="<?= $h($base . '/pages') ?>">
            <?= \App\Core\Csrf::field() ?>
            <button class="ja-b1 sm" type="submit"><?= $icon('M12 5v14M5 12h14') ?>Nouvelle page</button>
        </form>
    <?php endif; ?>
</header>
<?php if (!$canPost): ?>
    <p class="us-note">Vous consultez cet espace sans en avoir l’écriture : les pages sont en lecture seule.</p>
<?php endif; ?>
<?php if ($pages === []): ?>
    <div class="uw-pages-empty">
        <p>Aucune page pour l’instant.</p>
        <?php if ($canPost): ?>
            <form method="post" action="<?= $h($base . '/pages') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button class="ja-b2 sm" type="submit">Créer la première page</button>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($sortable): ?><p class="uw-note uw-js-only" hidden>Glissez une page par la poignée pour la déplacer, ou utilisez les flèches.</p><?php endif; ?>
    <div class="ja-rows uw-pagerows"<?= $sortable ? ' data-uw-reorder="' . $h($base . '/pages/ordre') . '" data-uw-csrf="' . $h(\App\Core\Csrf::token()) . '"' : '' ?>>
        <?php foreach ($pages as $i => $p): ?>
            <?php $blocks = count(WS::blocksFromJson((string) $p['blocks'])); $comments = (int) $p['comment_count']; ?>
            <div class="uw-pagerow" data-uw-page="<?= (int) $p['id'] ?>">
                <?php if ($sortable): ?><span class="uw-grip uw-js-only" draggable="true" aria-hidden="true" hidden title="Glisser pour déplacer"><?= $icon('M9 6v.01M15 6v.01M9 12v.01M15 12v.01M9 18v.01M15 18v.01') ?></span><?php endif; ?>
                <a class="ja-row" href="<?= $h($base . '?vue=page&p=' . (int) $p['id'] . '&direct=1') ?>">
                    <span class="n"><?= $pad2($i + 1) ?></span>
                    <span class="t"><b><?= $h((string) ($p['title'] !== '' ? $p['title'] : 'Sans titre')) ?></b><small>Modifiée par <?= $h((string) ($p['updated_by_label'] ?? 'un membre')) ?> · <?= $h(JnetSpaceService::relativeTime((string) $p['updated_at'])) ?></small></span>
                    <span class="m"><?= $blocks ?> bloc<?= $blocks > 1 ? 's' : '' ?><?= $comments > 0 ? ' · ' . $comments . ' commentaire' . ($comments > 1 ? 's' : '') : '' ?></span>
                    <span class="ar" aria-hidden="true"><svg class="ja-i" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </a>
                <?php if ($sortable): ?>
                    <form class="uw-order" method="post" action="<?= $h($base . '/pages/' . (int) $p['id'] . '/deplacer') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" name="dir" value="up" aria-label="Monter la page" title="Monter la page"<?= $i === 0 ? ' disabled' : '' ?>><?= $icon('m6 15 6-6 6 6') ?></button>
                        <button type="submit" name="dir" value="down" aria-label="Descendre la page" title="Descendre la page"<?= $i === count($pages) - 1 ? ' disabled' : '' ?>><?= $icon('m6 9 6 6 6-6') ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
