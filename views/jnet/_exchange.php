<?php
declare(strict_types=1);
/**
 * Un échange JNET. Attend $ex (array décoré par JnetSpaceService) et $exBack (URL de retour).
 * $exVariant : 'card' (défaut), 'row' (flux commun : heure à gauche) ou 'chat' (fil interne d’une unité).
 * $exPerspective : 'down' (« de l’unité émettrice »), 'up' (« par l’auteur ») ou '' (émetteur → destinataires).
 * @var array<string, mixed> $ex
 * @var string $exBack
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$variant = in_array($exVariant ?? 'card', ['card', 'row', 'chat'], true) ? (string) ($exVariant ?? 'card') : 'card';
$perspective = (string) ($exPerspective ?? '');
$from = (array) ($ex['from'] ?? []);
$to = (array) ($ex['to'] ?? []);
$kind = preg_replace('/[^a-z_]/', '', (string) ($ex['kind'] ?? 'info')) ?: 'info';
$fromAccent = (string) ($from['accent'] ?? '');
$fromStyle = $fromAccent !== '' ? ' style="--jn-unit: ' . $h($fromAccent) . '"' : '';
$body = trim((string) ($ex['body'] ?? ''));
$link = (string) ($ex['link'] ?? '');
$authorPhoto = $ex['authorPhoto'] ?? null;
$authorHref = (string) ($ex['authorHref'] ?? '');
$authorTag = $authorHref !== '' ? 'a href="' . $h($authorHref) . '"' : 'span';
$authorClose = $authorHref !== '' ? 'a' : 'span';
$exFace = '<span class="jn-ex__face" aria-hidden="true">'
    . (is_string($authorPhoto) && $authorPhoto !== '' ? '<img src="' . $h($authorPhoto) . '" alt="">' : $h((string) ($ex['authorInitials'] ?? '')))
    . '</span>';
$readCount = (int) ($ex['readCount'] ?? 0);
$audience = (int) ($ex['audience'] ?? 0);
$ackComplete = $audience > 0 && $readCount >= $audience;
$pending = max(0, $audience - $readCount);
?>
<article class="jn-ex jn-ex--<?= $h($kind) ?> jn-ex--<?= $h($variant) ?>">
    <?php if ($variant === 'row'): ?>
        <time class="jn-ex__clock" title="<?= $h((string) ($ex['when'] ?? '')) ?>"><?= $h((string) ($ex['stamp'] ?? '')) ?></time>
    <?php elseif ($variant === 'chat'): ?>
        <<?= $authorTag ?> class="jn-ex__avatar"><?= $exFace ?></<?= $authorClose ?>>
    <?php endif; ?>
    <div class="jn-ex__main">
        <?php if ($variant === 'chat'): ?>
            <header class="jn-ex__meta">
                <<?= $authorTag ?> class="jn-ex__who"><b><?= $h((string) ($ex['author'] ?? '')) ?></b></<?= $authorClose ?>>
                <span class="jn-ex__dot" aria-hidden="true">·</span>
                <a class="jn-ex__from" href="<?= $h((string) ($from['href'] ?? '#')) ?>"><?= $h((string) ($from['label'] ?? '')) ?></a>
                <span class="jn-ex__dot" aria-hidden="true">·</span>
                <time class="jn-ex__stamp" title="<?= $h((string) ($ex['when'] ?? '')) ?>"><?= $h((string) ($ex['stamp'] ?? '')) ?></time>
            </header>
        <?php else: ?>
            <header class="jn-ex__meta">
                <span class="jn-tag jn-tag--<?= $h($kind) ?>"><?= $h((string) ($ex['kindLabel'] ?? '')) ?></span>
                <?php if ($perspective === 'down'): ?>
                    <span class="jn-ex__origin">de <a href="<?= $h((string) ($from['href'] ?? '#')) ?>"<?= $fromStyle ?>><?= $h((string) ($from['label'] ?? '')) ?></a></span>
                <?php elseif ($perspective === 'up'): ?>
                    <span class="jn-ex__origin">par <b><?= $h((string) ($ex['author'] ?? '')) ?></b></span>
                <?php else: ?>
                    <a class="jn-unitchip" href="<?= $h((string) ($from['href'] ?? '#')) ?>"<?= $fromStyle ?>><?= $h((string) ($from['label'] ?? '')) ?></a>
                    <span class="jn-ex__arrow" aria-label="vers">→</span>
                    <span class="jn-ex__to">
                        <?php foreach ($to as $i => $t): ?><?= $i > 0 ? ' · ' : '' ?><?= $h((string) ($t['label'] ?? '')) ?><?php endforeach; ?>
                    </span>
                <?php endif; ?>
                <?php if ($variant !== 'row'): ?>
                    <time class="jn-ex__time" title="<?= $h((string) ($ex['when'] ?? '')) ?>"><?= $h((string) ($ex['stamp'] ?? '')) ?></time>
                <?php endif; ?>
            </header>
        <?php endif; ?>
        <h3 class="jn-ex__title"><?php if ($variant === 'chat' && $kind !== 'info'): ?><span class="jn-tag jn-tag--<?= $h($kind) ?>"><?= $h((string) ($ex['kindLabel'] ?? '')) ?></span><?php endif; ?><?= $h((string) ($ex['title'] ?? '')) ?></h3>
        <?php if ($body !== ''): ?>
            <p class="jn-ex__body"><?= nl2br($h($body)) ?></p>
        <?php endif; ?>
        <?php
        $showAuthor = $variant === 'card' && $perspective !== 'up';
        $hasFoot = $showAuthor || $link !== '' || !empty($ex['requiresAck']) || ($perspective === 'up' && $to !== []);
        ?>
        <?php if ($hasFoot): ?>
            <footer class="jn-ex__foot">
                <?php if ($showAuthor): ?>
                    <<?= $authorTag ?> class="jn-ex__author"><?= $exFace ?><b><?= $h((string) ($ex['author'] ?? '')) ?></b></<?= $authorClose ?>>
                <?php endif; ?>
                <?php if ($perspective === 'up' && $to !== []): ?>
                    <span class="jn-ex__dest">Vers <?php foreach ($to as $i => $t): ?><?= $i > 0 ? ' · ' : '' ?><?= $h((string) ($t['label'] ?? '')) ?><?php endforeach; ?></span>
                <?php endif; ?>
                <?php if ($link !== ''): ?>
                    <a class="jn-ex__link" href="<?= $h(url($link)) ?>">Ouvrir le lien</a>
                <?php endif; ?>
                <?php if (!empty($ex['requiresAck'])): ?>
                    <span class="jn-ex__ack<?= $ackComplete ? ' is-complete' : '' ?>">
                        <span aria-hidden="true">✓</span>
                        <?php if ($ackComplete): ?>
                            Lu par tous (<?= $audience ?>)
                        <?php else: ?>
                            <?php if ($pending > 0): ?>
                                Lu par <?= $readCount ?> / <?= $audience ?> — <?= $pending ?> en attente
                            <?php else: ?>
                                Lu par <?= $readCount ?> / <?= $audience ?>
                            <?php endif; ?>
                        <?php endif; ?>
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
        <?php endif; ?>
    </div>
</article>
