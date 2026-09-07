<?php
declare(strict_types=1);

/** @var array<string, mixed> $msgThread */
/** @var list<array<string, mixed>> $msgMessages */
/** @var int $msgCurrentUserId */
$thread = $msgThread ?? [];
$messages = $msgMessages ?? [];
$currentUid = (int) ($msgCurrentUserId ?? 0);
$threadId = (int) ($thread['id'] ?? 0);
$subject = trim((string) ($thread['subject'] ?? '')) ?: 'Conversation';
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<main class="msg-workspace msg-workspace--thread">
    <nav class="msg-breadcrumb" aria-label="Fil d’Ariane"><a href="<?= htmlspecialchars(url('messages')) ?>"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m12.5 4.5-6 5.5 6 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg> Toutes les conversations</a></nav>

    <?php if ($err): ?><div class="msg-alert msg-alert--error" role="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="msg-alert msg-alert--success" role="status"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <section class="msg-chat">
        <header class="msg-chat__header">
            <span class="msg-chat__avatar"><?= htmlspecialchars(function_exists('mb_substr') ? mb_strtoupper(mb_substr($subject, 0, 1)) : strtoupper(substr($subject, 0, 1)), ENT_QUOTES, 'UTF-8') ?><i></i></span>
            <div><p class="msg-section-kicker">Conversation interne</p><h1><?= htmlspecialchars($subject) ?></h1><span><i></i> Encadrement connecté au canal</span></div>
            <div class="msg-chat__count"><?= count($messages) ?> message<?= count($messages) !== 1 ? 's' : '' ?></div>
        </header>

        <div class="msg-chat__stream" data-msg-stream>
            <div class="msg-chat__privacy"><span><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="4.5" y="8" width="11" height="8" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M7 8V6a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.4"/></svg> Échange réservé aux participants habilités</span></div>
            <?php foreach ($messages as $index => $m): ?>
                <?php
                $body = (string) ($m['body'] ?? '');
                $senderId = (int) ($m['sender_user_id'] ?? 0);
                $isMine = $currentUid > 0 && $senderId === $currentUid;
                $name = trim((string) ($m['display_name'] ?? '')) ?: (string) ($m['email'] ?? 'Participant');
                if ($isMine) { $name = 'Vous'; }
                $when = (string) ($m['created_at'] ?? '');
                $timestamp = $when !== '' ? strtotime($when) : false;
                $dt = $timestamp ? date('d/m/Y · H:i', $timestamp) : $when;
                $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1)) : strtoupper(substr($name, 0, 1));
                ?>
                <article class="msg-bubble-row<?= $isMine ? ' is-mine' : '' ?>">
                    <?php if (!$isMine): ?><span class="msg-bubble-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    <div class="msg-bubble">
                        <div class="msg-bubble__meta"><strong><?= htmlspecialchars($name) ?></strong><time><?= htmlspecialchars($dt) ?></time></div>
                        <div class="msg-bubble__text"><?= nl2br(htmlspecialchars($body), false) ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <footer class="msg-reply">
            <form method="post" action="<?= htmlspecialchars(url('messages/' . $threadId . '/reply')) ?>" data-msg-form>
                <?= \App\Core\Csrf::field() ?>
                <label for="msg-reply-body">Votre réponse</label>
                <div class="msg-reply__box">
                    <textarea id="msg-reply-body" name="body" rows="3" maxlength="4000" required placeholder="Écrivez votre réponse…" data-msg-body></textarea>
                    <button type="submit" aria-label="Envoyer la réponse"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M7 12h14" stroke="currentColor" stroke-width="1.7"/></svg></button>
                </div>
                <div class="msg-reply__hint"><span>Entrée pour aller à la ligne</span><span><b data-msg-count>0</b> / 4000</span></div>
            </form>
        </footer>
    </section>
</main>
