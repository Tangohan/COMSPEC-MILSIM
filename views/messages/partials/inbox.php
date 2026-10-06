<?php
declare(strict_types=1);

/**
 * Colonne « conversations » de la messagerie, partagée par la liste et le fil.
 *
 * @var list<array<string, mixed>> $msgxThreads
 * @var int $msgxActiveId
 * @var int $msgxUserId
 * @var bool $msgxComposeActive
 */
use App\Support\MessagingThreadPresenter as MsgP;

$msgxUnreadThreads = count(array_filter($msgxThreads, static fn (array $t): bool => !empty($t['has_unread'])));
?>
<aside class="msgx-inbox" aria-labelledby="msgx-inbox-title">
    <div class="msgx-inbox__head">
        <div class="msgx-inbox__title">
            <h1 id="msgx-inbox-title">Messagerie</h1>
        </div>
        <a class="msgx-btn msgx-btn--primary<?= $msgxComposeActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars(url('messages') . '?nouveau=1', ENT_QUOTES, 'UTF-8') ?>" data-msg-compose>
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <span>Nouveau<span class="msgx-btn__more"> message</span></span>
        </a>
    </div>

    <?php if ($msgxThreads !== []): ?>
    <div class="msgx-inbox__tools">
        <label class="msgx-search">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.7"/><path d="m13 13 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <span class="msgx-sr">Rechercher une conversation</span>
            <input type="search" placeholder="Rechercher un objet, un nom, un mot…" autocomplete="off" data-msg-search>
        </label>
        <div class="msgx-filters" role="group" aria-label="Filtrer les conversations">
            <button type="button" class="msgx-chip is-active" data-msg-filter="all" aria-pressed="true">Toutes <span><?= count($msgxThreads) ?></span></button>
            <button type="button" class="msgx-chip" data-msg-filter="unread" aria-pressed="false">Non lues <span><?= $msgxUnreadThreads ?></span></button>
        </div>
    </div>

    <ul class="msgx-list" data-msg-list>
        <?php foreach ($msgxThreads as $t): ?>
            <?php
            $tid = (int) ($t['id'] ?? 0);
            $subj = trim((string) ($t['subject'] ?? '')) ?: 'Échange avec l’encadrement';
            $peer = MsgP::peerLabel($t, $msgxUserId);
            $preview = trim(preg_replace('/\s+/u', ' ', (string) ($t['last_preview'] ?? '')) ?? '');
            $lastSender = (int) ($t['last_sender_id'] ?? 0);
            $who = '';
            if ($preview !== '' && $lastSender > 0) {
                $who = $lastSender === $msgxUserId ? 'Vous' : (trim((string) ($t['last_sender_name'] ?? '')) ?: 'Encadrement');
            }
            $unread = (int) ($t['unread_count'] ?? 0);
            $when = (string) (($t['last_message_at'] ?? '') ?: ($t['updated_at'] ?? ''));
            $isActive = $tid === $msgxActiveId;
            $seed = $peer === 'Encadrement' ? 'staff' : (int) ($t['created_by_user_id'] ?? 0);
            ?>
            <li data-msg-thread data-unread="<?= $unread > 0 ? '1' : '0' ?>" data-search="<?= htmlspecialchars($subj . ' ' . $peer . ' ' . $preview, ENT_QUOTES, 'UTF-8') ?>">
                <a href="<?= htmlspecialchars(url('messages/' . $tid), ENT_QUOTES, 'UTF-8') ?>" class="msgx-item<?= $unread > 0 ? ' is-unread' : '' ?><?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <span class="msgx-avatar<?= $peer === 'Encadrement' ? ' msgx-avatar--staff' : '' ?>" style="--h:<?= MsgP::hue($seed) ?>" aria-hidden="true"><?php if ($peer === 'Encadrement'): ?><svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5 16 5v4.5c0 3.7-2.5 6.5-6 8-3.5-1.5-6-4.3-6-8V5l6-2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg><?php else: ?><?= htmlspecialchars(MsgP::initials($peer), ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span>
                    <span class="msgx-item__body">
                        <span class="msgx-item__row">
                            <strong class="msgx-item__subject"><?= htmlspecialchars($subj, ENT_QUOTES, 'UTF-8') ?></strong>
                            <time datetime="<?= htmlspecialchars($when !== '' ? date('c', strtotime($when) ?: time()) : '', ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(MsgP::fullTime($when), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(MsgP::listTime($when), ENT_QUOTES, 'UTF-8') ?></time>
                        </span>
                        <span class="msgx-item__peer"><?= htmlspecialchars($peer, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="msgx-item__row">
                            <span class="msgx-item__preview"><?php if ($who !== ''): ?><b><?= htmlspecialchars($who, ENT_QUOTES, 'UTF-8') ?> :</b> <?php endif; ?><?= htmlspecialchars($preview !== '' ? $preview : 'Conversation démarrée', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($unread > 0): ?><span class="msgx-badge" aria-label="<?= $unread ?> message<?= $unread > 1 ? 's' : '' ?> non lu<?= $unread > 1 ? 's' : '' ?>"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
                        </span>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="msgx-list-empty" data-msg-no-results hidden>
        <p><strong>Aucun résultat.</strong> Essayez un autre mot ou affichez toutes les conversations.</p>
    </div>
    <?php else: ?>
    <div class="msgx-list-empty msgx-list-empty--first">
        <span class="msgx-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M20 15a3 3 0 0 1-3 3H8l-4 3V7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v8Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
        <p><strong>Aucune conversation pour l’instant.</strong> Vos échanges avec l’encadrement apparaîtront ici.</p>
    </div>
    <?php endif; ?>
</aside>
