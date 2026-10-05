<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $msgThreads */
$msgxThreads = $msgThreads ?? [];
$msgxUserId = (int) ($msgCurrentUserId ?? 0);
$msgxActiveId = 0;
$recipientsOk = (bool) ($msgRecipientsConfigured ?? true);
$recipientCount = (int) ($msgRecipientCount ?? 0);
$msgxComposeActive = (bool) ($msgComposeOpen ?? false);
// Sur téléphone, le formulaire ne remplace la liste que s’il a été demandé explicitement.
$composeRequested = (bool) ($msgComposeRequested ?? false);
?>
<div class="msgx" data-msgx>
    <?php require __DIR__ . '/partials/flash.php'; ?>
    <div class="msgx-frame<?= $composeRequested && $msgxThreads !== [] ? ' is-detail' : '' ?><?= $msgxThreads === [] ? ' is-empty' : '' ?>">
        <?php require __DIR__ . '/partials/inbox.php'; ?>

        <section class="msgx-pane" aria-labelledby="msgx-pane-title">
            <?php if ($msgxComposeActive): ?>
                <header class="msgx-pane__head">
                    <a class="msgx-back" href="<?= htmlspecialchars(url('messages'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour aux conversations"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m12.5 4.5-6 5.5 6 5.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                    <div class="msgx-pane__titles">
                        <h2 id="msgx-pane-title">Nouveau message</h2>
                        <p>Une demande, une question, une absence à signaler : l’encadrement vous répond ici.</p>
                    </div>
                </header>

                <?php if ($recipientsOk): ?>
                <form class="msgx-compose" id="msg-compose" method="post" action="<?= htmlspecialchars(url('messages'), ENT_QUOTES, 'UTF-8') ?>" data-msg-form data-msg-draft="new">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="msgx-field msgx-field--inline">
                        <span class="msgx-field__label">À</span>
                        <span class="msgx-recipient">
                            <span class="msgx-avatar msgx-avatar--staff msgx-avatar--xs" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5 16 5v4.5c0 3.7-2.5 6.5-6 8-3.5-1.5-6-4.3-6-8V5l6-2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span>
                            Encadrement de la communauté
                            <?php if ($recipientCount > 0): ?><small><?= $recipientCount ?> personne<?= $recipientCount > 1 ? 's' : '' ?> habilitée<?= $recipientCount > 1 ? 's' : '' ?></small><?php endif; ?>
                        </span>
                    </div>
                    <label class="msgx-field">
                        <span class="msgx-field__label">Objet <em>facultatif</em></span>
                        <input type="text" name="subject" maxlength="255" placeholder="Ex. Absence à l’opération du 12" autocomplete="off" data-msg-subject>
                        <small class="msgx-field__help">Sans objet, votre message complète votre dernière demande restée sans réponse (moins de 2 h), s’il y en a une.</small>
                    </label>
                    <label class="msgx-field msgx-field--grow">
                        <span class="msgx-field__label">Message</span>
                        <textarea name="body" rows="8" maxlength="4000" required placeholder="Expliquez votre demande avec les informations utiles : dates, opération, unité…" data-msg-body></textarea>
                    </label>
                    <div class="msgx-compose__foot">
                        <span class="msgx-hint"><span><span class="msgx-kbd-hint"><kbd>Ctrl</kbd> + <kbd>Entrée</kbd> pour envoyer · </span><b data-msg-count>0</b>/4000</span></span>
                        <button type="submit" class="msgx-btn msgx-btn--primary msgx-btn--lg" data-msg-submit>
                            Envoyer
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 12h14" stroke="currentColor" stroke-width="1.8"/></svg>
                        </button>
                    </div>
                    <p class="msgx-privacy"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="4.5" y="8" width="11" height="8" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 8V6a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.5"/></svg> Visible uniquement par vous et les personnes habilitées de l’encadrement.</p>
                </form>
                <?php else: ?>
                <div class="msgx-placeholder">
                    <span class="msgx-empty-icon msgx-empty-icon--warn" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 4 3 20h18L12 4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 10v4.5M12 17v.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                    <h3>Envoi indisponible pour le moment</h3>
                    <p>Personne n’est encore désigné pour recevoir les messages internes de cette communauté. Passez par le forum ou contactez directement un responsable.</p>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="msgx-placeholder">
                    <span class="msgx-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M20 15a3 3 0 0 1-3 3H8l-4 3V7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v8Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                    <h2 id="msgx-pane-title">Choisissez une conversation</h2>
                    <p>Ouvrez un échange dans la liste pour le lire et y répondre, ou écrivez à l’encadrement.</p>
                    <?php if ($recipientsOk): ?><a class="msgx-btn msgx-btn--primary" href="<?= htmlspecialchars(url('messages') . '?nouveau=1', ENT_QUOTES, 'UTF-8') ?>">Nouveau message</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
