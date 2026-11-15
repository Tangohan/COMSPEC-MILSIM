<?php
/**
 * Une annonce du salon « Annonces & ordres du jour » : type, origine et destinataires, auteur, titre, texte, pièce ou photo,
 * avancement des accusés de réception et actions (accuser, épingler, relayer). Les données sont celles de l'échange JNET
 * (JnetSpaceService::decorateExchanges) : le droit de lire, d'accuser et d'épingler est revérifié côté serveur.
 *
 * @var array<string, mixed> $ex échange décoré
 * @var bool $canPin
 * @var string $salon
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$kind = preg_replace('/[^a-z_]/', '', (string) ($ex['kind'] ?? 'info')) ?: 'info';
$from = (array) ($ex['from'] ?? []);
$to = (array) ($ex['to'] ?? []);
$body = trim((string) ($ex['body'] ?? ''));
$link = (string) ($ex['link'] ?? '');
$image = (string) ($ex['image'] ?? '');
$protected = !empty($ex['protectedImage']);
$source = preg_replace('/[^a-z_]/', '', (string) ($ex['source'] ?? '')) ?: '';
$audience = (int) ($ex['audience'] ?? 0);
$read = (int) ($ex['readCount'] ?? 0);
$complete = $audience > 0 && $read >= $audience;
$pinned = (int) ($ex['pinId'] ?? 0) > 0;
$linkLabel = match ($source) {
    'recon_image' => 'Ouvrir la photo',
    'recon_note', 'atak_report' => 'Voir sur la carte ATAK',
    default => 'Ouvrir le lien',
};
$authorHref = (string) ($ex['authorHref'] ?? '');
$authorName = '<span translate="no">' . $h((string) ($ex['author'] ?? '')) . '</span>';
$id = (int) ($ex['id'] ?? 0);
?>
<article id="ex-<?= $id ?>" class="jc-an jc-an--<?= $h($kind) ?><?= $pinned ? ' is-pinned' : '' ?>">
    <div class="jc-an__top">
        <span class="jc-pill jc-pill--k-<?= $h($kind) ?>"><?= $h((string) ($ex['kindLabel'] ?? '')) ?></span>
        <?php if ($source !== ''): ?><span class="jc-an__src" title="Envoyé depuis l’ATAK en jeu"><?= $h((string) ($ex['sourceLabel'] ?? '')) ?></span><?php endif; ?>
        <span class="jc-an__scope">
            <a href="<?= $h((string) ($from['href'] ?? '#')) ?>" translate="no"><?= $h((string) ($from['label'] ?? '')) ?></a>
            <span aria-label="vers">→</span>
            <span translate="no"><?php foreach ($to as $i => $t): ?><?= $i > 0 ? ' · ' : '' ?><?= $h((string) ($t['label'] ?? '')) ?><?php endforeach; ?></span>
        </span>
        <?php if ($pinned): ?><span class="jc-pill jc-pill--ok jc-pill--pl"><?= JnetCommonRules::icon('pin') ?>Épinglé</span><?php endif; ?>
        <span class="jc-an__by"><?= $authorHref !== '' ? '<a href="' . $h($authorHref) . '">' . $authorName . '</a>' : $authorName ?> <span aria-hidden="true">·</span> <time title="<?= $h((string) ($ex['stamp'] ?? '')) ?>"><?= $h((string) ($ex['when'] ?? '')) ?></time></span>
    </div>
    <h4 class="jc-an__t"><?= $h((string) ($ex['title'] ?? '')) ?></h4>
    <?php if ($body !== ''): ?><p class="jc-an__p"><?= nl2br($h($body)) ?></p><?php endif; ?>
    <?php if ($image !== ''): ?>
        <a class="jc-an__media" href="<?= $h($link !== '' ? url($link) : $image) ?>" aria-label="Ouvrir la photo en grand"><img src="<?= $h($image) ?>" alt="" loading="lazy" decoding="async"></a>
    <?php elseif ($protected): ?>
        <a class="jc-an__media jc-an__media--protected" href="<?= $h($link !== '' ? url($link) : '#') ?>"><?= JnetCommonRules::icon('lock') ?><span><b>Cliché protégé</b> · ouvrir sur Athena</span></a>
    <?php elseif ($link !== ''): ?>
        <a class="jc-att" href="<?= $h(url($link)) ?>"><span class="jc-att__x"><?= JnetCommonRules::icon('doc') ?></span><span><?= $h($linkLabel) ?></span></a>
    <?php endif; ?>
    <div class="jc-an__ft">
        <?php if (!empty($ex['requiresAck']) && $audience > 0): ?>
            <div class="jc-ackbar<?= $complete ? ' is-complete' : '' ?>">
                <span class="jc-mono"><?= $read ?> / <?= $audience ?></span>
                <span class="jc-ackbar__tr" aria-hidden="true"><i style="width:<?= (int) round(min(1, $read / $audience) * 100) ?>%"></i></span>
                <span><?= $complete ? 'Lu par tous' : 'ont accusé réception' ?></span>
            </div>
        <?php elseif (!empty($ex['requiresAck'])): ?>
            <span class="jc-an__info">Accusé de réception demandé</span>
        <?php else: ?>
            <span class="jc-an__info">Pour information</span>
        <?php endif; ?>
        <div class="jc-an__acts">
            <?php if (!empty($ex['requiresAck'])): ?>
                <?php if (empty($ex['readByMe'])): ?>
                    <form method="post" action="<?= $h(url('jnet/echanges/' . $id . '/lu')) ?>" data-jc-ack>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="back" value="<?= $h(url(JnetCommonRules::path('annonces')) . '#ex-' . $id) ?>">
                        <button type="submit" class="jc-ac jc-ac--prim"><?= JnetCommonRules::icon('check') ?><span>Accuser réception</span></button>
                    </form>
                <?php else: ?>
                    <span class="jc-ac is-on"><?= JnetCommonRules::icon('check') ?><span>Accusé</span></span>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ((string) ($ex['relayHref'] ?? '') !== ''): ?>
                <a class="jc-ac" href="<?= $h((string) $ex['relayHref']) ?>" title="Ouvrir le formulaire de relais de votre unité"><?= JnetCommonRules::icon('arrow') ?><span>Relayer</span></a>
            <?php endif; ?>
            <?php $pinType = 'echange'; $pinTarget = (string) $id; $pinId = (int) ($ex['pinId'] ?? 0); require base_path('views/jnet/commun/_pin.php'); ?>
        </div>
    </div>
</article>
