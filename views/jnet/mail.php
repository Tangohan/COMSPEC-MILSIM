<?php
/** @var list<array<string,mixed>> $mailThreads */
/** @var array<string,mixed>|null $mailSelected */
/** @var list<array<string,mixed>> $mailMessages */
/** @var int $mailCurrentUserId */
/** @var string $mailFullUrl */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$threads = is_array($mailThreads ?? null) ? $mailThreads : [];
$selected = is_array($mailSelected ?? null) ? $mailSelected : null;
$messages = is_array($mailMessages ?? null) ? $mailMessages : [];
$uid = (int) ($mailCurrentUserId ?? 0);
$fullUrl = (string) ($mailFullUrl ?? url('messages'));
$selectedId = (int) ($selected['id'] ?? 0);
?>
<div class="jnet-mail">
    <nav class="jnet-mail__folders" aria-label="Dossiers">
        <div class="jnet-panel__head"><h2>Boîtes</h2></div>
        <a class="is-active" href="<?= $h(url('jnet/courrier')) ?>">Réception</a>
        <a href="<?= $h($fullUrl) ?>">Messagerie complète</a>
        <a href="<?= $h(url('jnet/applications')) ?>">Autres applications</a>
    </nav>

    <section class="jnet-mail__list" aria-label="Messages">
        <div class="jnet-panel__head">
            <h2>Messages</h2>
            <span class="jnet-meta"><?= count($threads) ?> affiché<?= count($threads) > 1 ? 's' : '' ?></span>
        </div>
        <?php if ($threads === []): ?>
            <p class="jnet-empty" style="padding:1rem;">Aucun message pour le moment.</p>
        <?php else: ?>
            <?php foreach ($threads as $t): ?>
                <?php
                $tid = (int) ($t['id'] ?? 0);
                $unread = !empty($t['has_unread']);
                ?>
                <a class="<?= $tid === $selectedId ? 'is-active' : '' ?>" href="<?= $h(url('jnet/courrier') . '?fil=' . $tid) ?>">
                    <strong><?= $h((string) ($t['subject'] ?? 'Sans objet')) ?><?= $unread ? ' · non lu' : '' ?></strong>
                    <small><?= $h(mb_strimwidth((string) ($t['last_preview'] ?? ''), 0, 90, '…', 'UTF-8')) ?></small>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="jnet-mail__read" aria-label="Lecture">
        <?php if ($selected === null): ?>
            <p class="jnet-empty" style="padding:1rem;">Sélectionnez un message pour le lire.</p>
        <?php else: ?>
            <div class="jnet-mail__read-head">
                <h2><?= $h((string) ($selected['subject'] ?? 'Message')) ?></h2>
                <div class="jnet-mail__actions">
                    <a class="jnet-btn" href="<?= $h(url('messages/' . $selectedId)) ?>">Répondre</a>
                    <a class="jnet-btn" href="<?= $h($fullUrl) ?>">Ouvrir dans Athena</a>
                </div>
            </div>
            <div class="jnet-mail__body">
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $fromMe = (int) ($msg['sender_user_id'] ?? 0) === $uid;
                        $who = $fromMe ? 'Vous' : (string) ($msg['display_name'] ?? $msg['sender_display_name'] ?? 'Correspondant');
                    $when = (string) ($msg['created_at'] ?? '');
                    ?>
                    <article class="jnet-mail-msg">
                        <p class="jnet-mail-msg__meta"><?= $h($who) ?> · <?= $h($when) ?></p>
                        <p><?= $h((string) ($msg['body'] ?? '')) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
