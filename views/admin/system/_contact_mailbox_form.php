<?php
declare(strict_types=1);

use App\Core\Csrf;

/**
 * Formulaire d'envoi de la boîte de contact (réponse sous un fil, ou nouveau message).
 * Variables attendues : $cmBase (adresse de l'écran), $cmThreadId, $cmReplyTo (id du message cité), $cmDraftId,
 * $cmValues (to, cc, subject, body), $cmLimits (count, mb), $cmFormId.
 */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$cmThreadId = (int) ($cmThreadId ?? 0);
$cmReplyTo = (int) ($cmReplyTo ?? 0);
$cmDraftId = (int) ($cmDraftId ?? 0);
$cmValues = is_array($cmValues ?? null) ? $cmValues : [];
$cmLimits = is_array($cmLimits ?? null) ? $cmLimits : ['count' => 5, 'mb' => 5];
$cmFormId = (string) ($cmFormId ?? 'cm-form');
?>
<form class="pa-card" id="<?= $h($cmFormId) ?>" method="post" action="<?= $h($cmBase . '/envoyer') ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="thread_id" value="<?= $cmThreadId ?>">
    <input type="hidden" name="reply_to_message_id" value="<?= $cmReplyTo ?>">
    <input type="hidden" name="draft_id" value="<?= $cmDraftId ?>">
    <h2 class="pa-card__title"><?= $cmThreadId > 0 ? 'Répondre' : 'Écrire un message' ?></h2>

    <div class="cm-field">
        <label for="<?= $h($cmFormId) ?>-to">Destinataire</label>
        <input id="<?= $h($cmFormId) ?>-to" type="text" name="to" maxlength="500" required autocomplete="off" value="<?= $h((string) ($cmValues['to'] ?? '')) ?>">
        <p class="cm-hint">Une ou plusieurs adresses séparées par une virgule.</p>
    </div>
    <div class="cm-field">
        <label for="<?= $h($cmFormId) ?>-cc">Copie (facultatif)</label>
        <input id="<?= $h($cmFormId) ?>-cc" type="text" name="cc" maxlength="500" autocomplete="off" value="<?= $h((string) ($cmValues['cc'] ?? '')) ?>">
    </div>
    <div class="cm-field">
        <label for="<?= $h($cmFormId) ?>-subject">Objet</label>
        <input id="<?= $h($cmFormId) ?>-subject" type="text" name="subject" maxlength="200" required value="<?= $h((string) ($cmValues['subject'] ?? '')) ?>">
    </div>
    <div class="cm-field">
        <label for="<?= $h($cmFormId) ?>-body">Message</label>
        <textarea id="<?= $h($cmFormId) ?>-body" name="body" rows="14" maxlength="20000" required><?= $h((string) ($cmValues['body'] ?? '')) ?></textarea>
        <p class="cm-hint">Texte brut. La signature et la citation du message d’origine sont modifiables.</p>
    </div>
    <div class="cm-field">
        <label for="<?= $h($cmFormId) ?>-files">Pièces jointes (facultatif)</label>
        <input id="<?= $h($cmFormId) ?>-files" type="file" name="attachments[]" multiple>
        <p class="cm-hint"><?= (int) $cmLimits['count'] ?> fichiers au plus, <?= (int) $cmLimits['mb'] ?> Mo chacun. Les fichiers exécutables sont refusés.</p>
    </div>

    <div class="pa-actions">
        <button type="submit" class="pa-btn pa-btn--ink">Envoyer</button>
        <button type="submit" class="pa-btn pa-btn--line" formaction="<?= $h($cmBase . '/brouillon') ?>" formenctype="application/x-www-form-urlencoded" formnovalidate>Enregistrer le brouillon</button>
    </div>
</form>
<?php if ($cmDraftId > 0 && $cmThreadId > 0): ?>
    <form method="post" action="<?= $h($cmBase . '/fil/' . $cmThreadId . '/action') ?>" style="margin-top:0.5rem;">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="discard_draft">
        <button type="submit" class="pa-btn pa-btn--line pa-btn--sm">Abandonner le brouillon</button>
    </form>
<?php endif; ?>
