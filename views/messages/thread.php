<?php
declare(strict_types=1);

/** @var array<string, mixed> $msgThread */
/** @var list<array<string, mixed>> $msgMessages */
/** @var int $msgCurrentUserId */
use App\Support\MessagingThreadPresenter as MsgP;

$thread = $msgThread ?? [];
$messages = $msgMessages ?? [];
$msgxThreads = $msgThreads ?? [];
$msgxUserId = (int) ($msgCurrentUserId ?? 0);
$threadId = (int) ($thread['id'] ?? 0);
$msgxActiveId = $threadId;
$msgxComposeActive = false;
$subject = trim((string) ($thread['subject'] ?? '')) ?: 'Conversation';
$days = MsgP::groupMessages($messages, $msgxUserId, isset($msgLastReadAt) ? (string) $msgLastReadAt : null);

$others = [];
foreach (($msgParticipants ?? []) as $p) {
    $pid = (int) ($p['user_id'] ?? 0);
    if ($pid === $msgxUserId || $pid <= 0) {
        continue;
    }
    $others[] = trim((string) ($p['display_name'] ?? '')) ?: 'Participant';
}
$shown = array_slice($others, 0, 3);
$participantsLine = $others === []
    ? 'Vous seul pour l’instant'
    : 'Vous, ' . implode(', ', $shown) . (count($others) > 3 ? ' et ' . (count($others) - 3) . ' autre' . (count($others) - 3 > 1 ? 's' : '') : '');
$peer = MsgP::peerLabel($thread + ['creator_name' => ''], $msgxUserId);
foreach ($msgxThreads as $row) {
    if ((int) ($row['id'] ?? 0) === $threadId) {
        $peer = MsgP::peerLabel($row, $msgxUserId);
        break;
    }
}
$seed = $peer === 'Encadrement' ? 'staff' : (int) ($thread['created_by_user_id'] ?? 0);
?>
<div class="msgx" data-msgx>
    <?php require __DIR__ . '/partials/flash.php'; ?>
    <div class="msgx-frame is-detail">
        <?php require __DIR__ . '/partials/inbox.php'; ?>

        <section class="msgx-pane msgx-pane--chat" aria-labelledby="msgx-pane-title">
            <header class="msgx-pane__head">
                <a class="msgx-back" href="<?= htmlspecialchars(url('messages'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour aux conversations"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m12.5 4.5-6 5.5 6 5.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                <span class="msgx-avatar<?= $peer === 'Encadrement' ? ' msgx-avatar--staff' : '' ?>" style="--h:<?= MsgP::hue($seed) ?>" aria-hidden="true"><?php if ($peer === 'Encadrement'): ?><svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5 16 5v4.5c0 3.7-2.5 6.5-6 8-3.5-1.5-6-4.3-6-8V5l6-2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg><?php else: ?><?= htmlspecialchars(MsgP::initials($peer), ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span>
                <div class="msgx-pane__titles">
                    <h2 id="msgx-pane-title"><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p title="<?= htmlspecialchars(implode(', ', $others), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($participantsLine, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="msgx-count"><?= count($messages) ?> message<?= count($messages) !== 1 ? 's' : '' ?></span>
            </header>

            <div class="msgx-stream" data-msg-stream role="log" aria-label="Messages de la conversation">
                <p class="msgx-privacy msgx-privacy--center"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="4.5" y="8" width="11" height="8" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 8V6a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.5"/></svg> Échange réservé aux participants de ce fil</p>
                <?php if ($days === []): ?>
                    <div class="msgx-placeholder msgx-placeholder--inline"><p>Aucun message dans ce fil pour l’instant. Écrivez le premier ci-dessous.</p></div>
                <?php endif; ?>
                <?php foreach ($days as $day): ?>
                    <div class="msgx-day" role="separator"><span><?= htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <?php foreach ($day['groups'] as $g): ?>
                        <?php if (!empty($g['unread_before'])): ?><div class="msgx-unread-mark" id="msgx-unread" data-msg-unread><span>Nouveaux messages</span></div><?php endif; ?>
                        <div class="msgx-group<?= $g['mine'] ? ' is-mine' : '' ?>">
                            <?php if (!$g['mine']): ?><span class="msgx-avatar msgx-avatar--sm" style="--h:<?= (int) $g['hue'] ?>" aria-hidden="true"><?= htmlspecialchars((string) $g['initials'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            <div class="msgx-group__body">
                                <div class="msgx-group__meta"><strong><?= htmlspecialchars((string) $g['name'], ENT_QUOTES, 'UTF-8') ?></strong><time><?= htmlspecialchars((string) $g['time'], ENT_QUOTES, 'UTF-8') ?></time></div>
                                <?php foreach ($g['messages'] as $m): ?>
                                    <div class="msgx-bubble" title="<?= htmlspecialchars((string) $m['full_time'], ENT_QUOTES, 'UTF-8') ?>"><?= nl2br(htmlspecialchars((string) $m['body'], ENT_QUOTES, 'UTF-8'), false) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <form class="msgx-composer" method="post" action="<?= htmlspecialchars(url('messages/' . $threadId . '/reply'), ENT_QUOTES, 'UTF-8') ?>" data-msg-form data-msg-draft="thread-<?= $threadId ?>">
                <?= \App\Core\Csrf::field() ?>
                <label class="msgx-sr" for="msg-reply-body">Votre réponse</label>
                <div class="msgx-composer__box">
                    <textarea id="msg-reply-body" name="body" rows="1" maxlength="4000" required placeholder="Écrire une réponse…" data-msg-body data-msg-autosize></textarea>
                    <button type="submit" class="msgx-send" aria-label="Envoyer la réponse" data-msg-submit><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 12h14" stroke="currentColor" stroke-width="1.8"/></svg></button>
                </div>
                <div class="msgx-hint"><span><kbd>Ctrl</kbd> + <kbd>Entrée</kbd> pour envoyer · <kbd>Entrée</kbd> pour aller à la ligne</span><span><b data-msg-count>0</b>/4000</span></div>
            </form>
        </section>
    </div>
</div>
