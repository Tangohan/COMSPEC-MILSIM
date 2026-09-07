<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $msgThreads */
$threads = $msgThreads ?? [];
$recipientsOk = (bool) ($msgRecipientsConfigured ?? true);
$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
$unreadCount = count(array_filter($threads, static fn (array $t): bool => !empty($t['has_unread'])));
$formatDate = static function (string $value): string {
    $timestamp = strtotime($value);
    if (!$timestamp) {
        return '';
    }
    if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
        return 'Aujourd’hui, ' . date('H:i', $timestamp);
    }
    if (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Hier, ' . date('H:i', $timestamp);
    }
    return date('d/m/Y', $timestamp);
};
?>
<main class="msg-workspace">
    <header class="msg-hero">
        <div class="msg-hero__glow" aria-hidden="true"></div>
        <div class="msg-hero__content">
            <div class="msg-eyebrow"><span></span> Canal interne sécurisé</div>
            <h1>Vos échanges,<br><em>au même endroit.</em></h1>
            <p>Contactez directement l’encadrement de votre communauté et gardez le fil de chaque demande.</p>
            <div class="msg-stats" aria-label="Résumé de la messagerie">
                <div><strong><?= count($threads) ?></strong><span>conversation<?= count($threads) !== 1 ? 's' : '' ?></span></div>
                <div><strong><?= $unreadCount ?></strong><span>non lue<?= $unreadCount !== 1 ? 's' : '' ?></span></div>
                <div class="msg-stats__status"><i></i><span><?= $recipientsOk ? 'Canal disponible' : 'Canal indisponible' ?></span></div>
            </div>
        </div>
        <button class="msg-primary-action" type="button" data-msg-compose aria-controls="msg-compose">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            Nouveau message
        </button>
    </header>

    <?php if ($err): ?><div class="msg-alert msg-alert--error" role="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="msg-alert msg-alert--success" role="status"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if (!$recipientsOk): ?>
        <div class="msg-alert msg-alert--warning" role="alert"><strong>Envoi temporairement indisponible.</strong> Aucun destinataire habilité n’est configuré. Utilisez le forum ou contactez un responsable.</div>
    <?php endif; ?>

    <div class="msg-grid">
        <section class="msg-inbox" aria-labelledby="msg-inbox-title">
            <div class="msg-section-head">
                <div>
                    <p class="msg-section-kicker">Boîte de réception</p>
                    <h2 id="msg-inbox-title">Conversations</h2>
                </div>
                <?php if ($threads !== []): ?>
                <label class="msg-search">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6"/><path d="m13 13 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <span class="sr-only">Rechercher une conversation</span>
                    <input type="search" placeholder="Rechercher…" data-msg-search>
                </label>
                <?php endif; ?>
            </div>

            <?php if ($threads === []): ?>
                <div class="msg-empty">
                    <div class="msg-empty__icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 15a3 3 0 0 1-3 3H8l-4 3V7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v8Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
                    <h3>La voie est libre</h3>
                    <p>Vous n’avez encore aucune conversation. Lancez votre première demande à l’encadrement.</p>
                    <?php if ($recipientsOk): ?><button type="button" data-msg-compose>Écrire un message</button><?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="msg-thread-list" data-msg-list>
                    <?php foreach ($threads as $t): ?>
                        <?php
                        $id = (int) ($t['id'] ?? 0);
                        $subj = trim((string) ($t['subject'] ?? '')) ?: 'Échange avec l’encadrement';
                        $preview = trim((string) ($t['last_preview'] ?? ''));
                        $hasUnread = !empty($t['has_unread']);
                        $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($subj, 0, 1)) : strtoupper(substr($subj, 0, 1));
                        $date = $formatDate((string) ($t['updated_at'] ?? ''));
                        ?>
                        <li data-msg-thread data-search="<?= htmlspecialchars($subj . ' ' . $preview, ENT_QUOTES, 'UTF-8') ?>">
                            <a href="<?= htmlspecialchars(url('messages/' . $id)) ?>" class="msg-thread<?= $hasUnread ? ' is-unread' : '' ?>">
                                <span class="msg-thread__avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?><i></i></span>
                                <span class="msg-thread__body">
                                    <span class="msg-thread__line"><strong><?= htmlspecialchars($subj) ?></strong><time><?= htmlspecialchars($date) ?></time></span>
                                    <span class="msg-thread__line"><span class="msg-thread__preview"><?= htmlspecialchars($preview !== '' ? $preview : 'Conversation démarrée') ?></span><?php if ($hasUnread): ?><b class="msg-unread-dot"><span class="sr-only">Non lu</span></b><?php endif; ?></span>
                                </span>
                                <svg class="msg-thread__arrow" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m7.5 4.5 5.5 5.5-5.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="msg-no-results" data-msg-no-results hidden>Aucune conversation ne correspond à cette recherche.</div>
            <?php endif; ?>
        </section>

        <aside class="msg-compose" id="msg-compose">
            <div class="msg-compose__head">
                <span class="msg-compose__icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 16.5-.8 4.3 4.3-.8L19 8.5 15.5 5 4 16.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m13.8 6.7 3.5 3.5" stroke="currentColor" stroke-width="1.6"/></svg></span>
                <div><p class="msg-section-kicker">Nouveau</p><h2>Écrire à l’encadrement</h2></div>
            </div>
            <p class="msg-compose__intro">Votre demande sera transmise aux personnes habilitées de votre communauté.</p>
            <?php if ($recipientsOk): ?>
                <form method="post" action="<?= htmlspecialchars(url('messages')) ?>" data-msg-form>
                    <?= \App\Core\Csrf::field() ?>
                    <label>Objet <span>optionnel</span><input type="text" name="subject" maxlength="255" placeholder="Ex. Disponibilité opération"></label>
                    <label>Message <textarea name="body" rows="7" maxlength="4000" required placeholder="Décrivez votre demande avec les informations utiles…" data-msg-body></textarea></label>
                    <div class="msg-compose__footer"><span><b data-msg-count>0</b> / 4000</span><button type="submit">Transmettre <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 10h13M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div>
                </form>
                <p class="msg-compose__note"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 16 5v4.5c0 3.7-2.5 6.5-6 8-3.5-1.5-6-4.3-6-8V5l6-2.5Z" stroke="currentColor" stroke-width="1.4"/><path d="m7.5 10 1.7 1.7 3.5-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg> Visible uniquement par vous et les rôles habilités.</p>
            <?php else: ?><p class="msg-compose__disabled">Le formulaire sera disponible dès qu’un destinataire aura été désigné.</p><?php endif; ?>
        </aside>
    </div>
</main>
