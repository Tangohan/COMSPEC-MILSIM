<?php
declare(strict_types=1);

/**
 * Assistant rédactionnel (formules Courrier, réutilisé pour la doctrine).
 *
 * @var string $assistantTarget
 * @var string $assistantInsertMode html|markdown
 * @var bool $assistantLocked
 * @var int $assistantDocId
 */

$assistantTarget = (string) ($assistantTarget ?? 'body-rendered');
$assistantInsertMode = (string) ($assistantInsertMode ?? 'html');
if ($assistantInsertMode !== 'markdown') {
    $assistantInsertMode = 'html';
}
$assistantLocked = !empty($assistantLocked);
$assistantDocId = (int) ($assistantDocId ?? 0);
$assistantApi = url('courrier/api/snippets');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/writing-assistant.css'), ENT_QUOTES, 'UTF-8') ?>">
<aside class="writing-assistant" aria-labelledby="writing-assistant-title">
    <h3 id="writing-assistant-title" class="writing-assistant__title">Assistant rédactionnel</h3>
    <p class="writing-assistant__hint">Cliquez pour insérer à la position du curseur, dans le corps du document.</p>
    <?php if (!$assistantLocked): ?>
    <div class="writing-assistant__toolbar" id="courrier-insert-toolbar">
        <button type="button" class="writing-assistant__chip courrier-insert-btn" data-kind="para">Paragraphe</button>
        <button type="button" class="writing-assistant__chip courrier-insert-btn" data-kind="alinea">Alinéa</button>
    </div>
    <?php endif; ?>
    <div
        id="courrier-snippets-root"
        class="writing-assistant__list"
        data-writing-assistant
        data-locked="<?= $assistantLocked ? '1' : '0' ?>"
        data-doc-id="<?= $assistantDocId > 0 ? $assistantDocId : '' ?>"
        data-target="<?= htmlspecialchars($assistantTarget, ENT_QUOTES, 'UTF-8') ?>"
        data-insert-mode="<?= htmlspecialchars($assistantInsertMode, ENT_QUOTES, 'UTF-8') ?>"
        data-api="<?= htmlspecialchars($assistantApi, ENT_QUOTES, 'UTF-8') ?>"
    >
        <p class="writing-assistant__muted">Chargement des formules…</p>
    </div>
</aside>
<script>
window.COURRIER_SNIPPETS_API = window.COURRIER_SNIPPETS_API || <?= json_encode($assistantApi, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/courrier-editor.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
