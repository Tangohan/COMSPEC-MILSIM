<?php
declare(strict_types=1);
/**
 * Un échange JNET (carte). Attend $ex (array décoré par JnetSpaceService) et $exBack (URL de retour).
 * @var array<string, mixed> $ex
 * @var string $exBack
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$from = (array) ($ex['from'] ?? []);
$to = (array) ($ex['to'] ?? []);
$kind = preg_replace('/[^a-z_]/', '', (string) ($ex['kind'] ?? 'info')) ?: 'info';
$fromAccent = (string) ($from['accent'] ?? '');
$body = trim((string) ($ex['body'] ?? ''));
$link = (string) ($ex['link'] ?? '');
?>
<article class="jn-ex jn-ex--<?= $h($kind) ?>">
    <header class="jn-ex__meta">
        <span class="jn-tag jn-tag--<?= $h($kind) ?>"><?= $h((string) ($ex['kindLabel'] ?? '')) ?></span>
        <a class="jn-unitchip" href="<?= $h((string) ($from['href'] ?? '#')) ?>"<?= $fromAccent !== '' ? ' style="--jn-unit: ' . $h($fromAccent) . '"' : '' ?>><?= $h((string) ($from['label'] ?? '')) ?></a>
        <span class="jn-ex__arrow" aria-label="vers">→</span>
        <span class="jn-ex__to">
            <?php foreach ($to as $i => $t): ?><?= $i > 0 ? ' · ' : '' ?><?= $h((string) ($t['label'] ?? '')) ?><?php endforeach; ?>
        </span>
        <time class="jn-ex__time" title="<?= $h((string) ($ex['when'] ?? '')) ?>"><?= $h((string) ($ex['stamp'] ?? '')) ?></time>
    </header>
    <h3 class="jn-ex__title"><?= $h((string) ($ex['title'] ?? '')) ?></h3>
    <?php if ($body !== ''): ?>
        <p class="jn-ex__body"><?= nl2br($h($body)) ?></p>
    <?php endif; ?>
    <footer class="jn-ex__foot">
        <?php
        $authorPhoto = $ex['authorPhoto'] ?? null;
        $authorHref = (string) ($ex['authorHref'] ?? '');
        ?>
        <<?= $authorHref !== '' ? 'a href="' . $h($authorHref) . '"' : 'span' ?> class="jn-ex__author">
            <span class="jn-ex__face" aria-hidden="true"><?php if (is_string($authorPhoto) && $authorPhoto !== ''): ?><img src="<?= $h($authorPhoto) ?>" alt=""><?php else: ?><?= $h((string) ($ex['authorInitials'] ?? '')) ?><?php endif; ?></span>
            <b><?= $h((string) ($ex['author'] ?? '')) ?></b>
        </<?= $authorHref !== '' ? 'a' : 'span' ?>>
        <?php if ($link !== ''): ?>
            <a class="jn-ex__link" href="<?= $h(url($link)) ?>">Ouvrir le lien</a>
        <?php endif; ?>
        <?php if (!empty($ex['requiresAck'])): ?>
            <span class="jn-ex__ack<?= (int) $ex['audience'] > 0 && (int) $ex['readCount'] >= (int) $ex['audience'] ? ' is-complete' : '' ?>">
                Lu par <?= (int) $ex['readCount'] ?> / <?= (int) $ex['audience'] ?>
            </span>
            <?php if (empty($ex['readByMe'])): ?>
                <form method="post" action="<?= $h(url('jnet/echanges/' . (int) $ex['id'] . '/lu')) ?>" class="jn-ex__ackform">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="back" value="<?= $h((string) ($exBack ?? '')) ?>">
                    <button type="submit" class="jn-btn jn-btn--small">Accuser réception</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </footer>
</article>
