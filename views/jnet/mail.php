<?php
declare(strict_types=1);
/**
 * Messagerie d’unité JNET — boîtes, liste des fils et lecture.
 *
 * @var string $mailBox
 * @var list<array<string,mixed>> $mailBoxes
 * @var list<array<string,mixed>> $mailThreads
 * @var array<string,mixed>|null $mailSelected
 * @var list<array<string,mixed>> $mailMessages
 * @var list<array<string,mixed>> $mailParticipants
 * @var int $mailCurrentUserId
 * @var string $mailFullUrl
 * @var bool $mailCanSend
 * @var array<string,array{label:string,hint:string}> $mailPrecedences
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$box = (string) ($mailBox ?? 'reception');
$boxes = is_array($mailBoxes ?? null) ? $mailBoxes : [];
$threads = is_array($mailThreads ?? null) ? $mailThreads : [];
$selected = is_array($mailSelected ?? null) ? $mailSelected : null;
$messages = is_array($mailMessages ?? null) ? $mailMessages : [];
$participants = is_array($mailParticipants ?? null) ? $mailParticipants : [];
$uid = (int) ($mailCurrentUserId ?? 0);
$fullUrl = (string) ($mailFullUrl ?? url('messages'));
$canSend = !empty($mailCanSend);
$precedences = is_array($mailPrecedences ?? null) ? $mailPrecedences : [];
$selectedId = (int) ($selected['id'] ?? 0);

$precedenceLabel = static function (?string $key) use ($precedences): string {
    $key = (string) ($key ?? 'routine');

    return (string) ($precedences[$key]['label'] ?? 'Routine');
};
$stamp = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }
    $ts = strtotime($value);

    return $ts ? date('d/m/Y à H\hi', $ts) : $value;
};
$boxQuery = $box !== 'reception' ? '&boite=' . rawurlencode($box) : '';

$senders = [];
foreach ($participants as $p) {
    $senders[(int) ($p['user_id'] ?? 0)] = (string) ($p['display_name'] ?? '');
}
$recipientNames = [];
$viaGroups = [];
foreach ($participants as $p) {
    if ((string) ($p['recipient_kind'] ?? 'to') === 'sender') {
        continue;
    }
    $name = trim((string) ($p['display_name'] ?? ''));
    if ($name !== '') {
        $recipientNames[] = $name;
    }
    $via = trim((string) ($p['via_label'] ?? ''));
    if ($via !== '') {
        $viaGroups[$via] = true;
    }
}
?>
<div class="jnet-mail">
    <nav class="jnet-mail__folders" aria-label="Boîtes de messagerie">
        <div class="jnet-panel__head"><h2>Messagerie</h2></div>
        <?php if ($canSend): ?>
            <a class="jnet-mail__compose" href="<?= $h(url('jnet/courrier/nouveau')) ?>">Rédiger un message</a>
        <?php endif; ?>
        <?php foreach ($boxes as $b): ?>
            <?php $key = (string) ($b['key'] ?? ''); $count = (int) ($b['count'] ?? 0); ?>
            <a class="<?= $key === $box ? 'is-active' : '' ?>" href="<?= $h((string) ($b['href'] ?? '#')) ?>">
                <span><?= $h((string) ($b['label'] ?? '')) ?></span>
                <?php if ($count > 0): ?><i><?= $count ?></i><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <form method="post" action="<?= $h(url('jnet/courrier/lu')) ?>" class="jnet-mail__folder-form">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit">Tout marquer comme lu</button>
        </form>
        <a href="<?= $h($fullUrl) ?>">Ouvrir dans Athena</a>
    </nav>

    <section class="jnet-mail__list" aria-label="Fils de discussion">
        <div class="jnet-panel__head">
            <h2><?= $h(($box === 'envoyes' ? 'Messages envoyés' : ($box === 'non-lus' ? 'Non lus' : 'Réception'))) ?></h2>
            <span class="jnet-meta"><?= count($threads) ?> fil<?= count($threads) > 1 ? 's' : '' ?></span>
        </div>
        <?php if ($threads === []): ?>
            <p class="jnet-empty" style="padding:1rem;">
                <?= $box === 'non-lus' ? 'Tout est lu.' : 'Aucun message dans cette boîte pour le moment.' ?>
            </p>
        <?php else: ?>
            <?php foreach ($threads as $t): ?>
                <?php
                $tid = (int) ($t['id'] ?? 0);
                $unread = !empty($t['has_unread']);
                $prec = (string) ($t['precedence'] ?? 'routine');
                $summary = trim((string) ($t['recipients_summary'] ?? ''));
                ?>
                <a class="<?= $tid === $selectedId ? 'is-active' : '' ?><?= $unread ? ' is-unread' : '' ?>"
                   href="<?= $h(url('jnet/courrier') . '?fil=' . $tid . $boxQuery) ?>">
                    <span class="jnet-mail__row">
                        <strong><?= $h((string) ($t['subject'] ?? 'Sans objet')) ?></strong>
                        <?php if ($prec !== 'routine'): ?>
                            <em class="jnet-prec jnet-prec--<?= $h($prec) ?>"><?= $h($precedenceLabel($prec)) ?></em>
                        <?php endif; ?>
                    </span>
                    <small><?= $h(mb_strimwidth(preg_replace('/\s+/u', ' ', (string) ($t['last_preview'] ?? '')) ?? '', 0, 92, '…', 'UTF-8')) ?></small>
                    <small class="jnet-mail__row-meta">
                        <?= $h($stamp((string) ($t['updated_at'] ?? ''))) ?>
                        · <?= (int) ($t['participant_count'] ?? 0) ?> participant<?= (int) ($t['participant_count'] ?? 0) > 1 ? 's' : '' ?>
                        <?php if ($summary !== ''): ?> · <?= $h($summary) ?><?php endif; ?>
                    </small>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="jnet-mail__read" aria-label="Lecture du message">
        <?php if ($selected === null): ?>
            <p class="jnet-empty" style="padding:1rem;">Sélectionnez un message dans la liste pour le lire.</p>
        <?php else: ?>
            <?php $prec = (string) ($selected['precedence'] ?? 'routine'); ?>
            <div class="jnet-mail__read-head">
                <h2><?= $h((string) ($selected['subject'] ?? 'Message')) ?></h2>
                <dl class="jnet-mail__headers">
                    <div>
                        <dt>Expéditeur</dt>
                        <dd><?= $h($senders[(int) ($selected['created_by_user_id'] ?? 0)] ?? 'Membre de l’unité') ?></dd>
                    </div>
                    <div>
                        <dt>Destinataires</dt>
                        <dd>
                            <?php if ($viaGroups !== []): ?>
                                <?= $h(implode(', ', array_keys($viaGroups))) ?>
                                <?php if (count($recipientNames) > 0): ?>
                                    <span class="jnet-muted">(<?= count($recipientNames) ?> personne<?= count($recipientNames) > 1 ? 's' : '' ?>)</span>
                                <?php endif; ?>
                            <?php elseif ($recipientNames !== []): ?>
                                <?= $h(implode(', ', array_slice($recipientNames, 0, 8))) ?>
                                <?php if (count($recipientNames) > 8): ?>
                                    <span class="jnet-muted">+ <?= count($recipientNames) - 8 ?> autre<?= count($recipientNames) - 8 > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Urgence</dt>
                        <dd><span class="jnet-prec jnet-prec--<?= $h($prec) ?>"><?= $h($precedenceLabel($prec)) ?></span></dd>
                    </div>
                    <div>
                        <dt>Dernière activité</dt>
                        <dd><?= $h($stamp((string) ($selected['updated_at'] ?? ''))) ?></dd>
                    </div>
                </dl>
                <div class="jnet-mail__actions">
                    <a class="jnet-btn" href="<?= $h(url('messages/' . $selectedId)) ?>">Ouvrir dans Athena</a>
                    <?php if ($canSend): ?>
                        <a class="jnet-btn" href="<?= $h(url('jnet/courrier/nouveau') . '?repondre=' . $selectedId) ?>">Nouveau message lié</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="jnet-mail__body">
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $fromMe = (int) ($msg['sender_user_id'] ?? 0) === $uid;
                    $who = $fromMe ? 'Vous' : (string) ($msg['display_name'] ?? 'Correspondant');
                    ?>
                    <article class="jnet-mail-msg<?= $fromMe ? ' is-mine' : '' ?>">
                        <p class="jnet-mail-msg__meta"><?= $h($who) ?> · <?= $h($stamp((string) ($msg['created_at'] ?? ''))) ?></p>
                        <p><?= $h((string) ($msg['body'] ?? '')) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($canSend): ?>
                <form class="jnet-mail__reply" method="post" action="<?= $h(url('jnet/courrier/' . $selectedId . '/reponse')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="jnet-reply">Répondre à tous les participants</label>
                    <textarea id="jnet-reply" name="body" rows="4" required
                              placeholder="Votre réponse sera visible par l’ensemble des participants du fil."></textarea>
                    <div class="jnet-mail__reply-actions">
                        <button class="jnet-btn jnet-btn--solid" type="submit">Envoyer la réponse</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="jnet-empty" style="padding:0 1rem 1rem;">L’envoi de messages est momentanément indisponible pour votre compte.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
