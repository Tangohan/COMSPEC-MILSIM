<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $document */
$canManage = (bool) ($canManage ?? false);
$status = (string) ($document['status'] ?? '');
$editable = in_array($status, ['brouillon', 'en_relecture'], true);
$statusClass = match ($status) {
    'brouillon' => 'badge--gray',
    'en_relecture' => 'badge--amber',
    'valide' => '',
    'archive' => 'badge--gray',
    default => 'badge--gray',
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/documents')) ?>">Rédaction</a> /
    <strong><?= $h($document['reference_code'] ?? '') ?></strong>
</div>

<section class="sse-desk-hero" aria-labelledby="sse-doc-show-title">
    <div class="sse-desk-hero__main">
        <div class="sse-desk-hero__kicker">
            <span class="interest-hero__ref"><?= $h($document['reference_code'] ?? 'DOC') ?></span>
            <span class="badge <?= $h($statusClass) ?>"><?= $h($document['status_label'] ?? '') ?></span>
            <span class="badge badge--red"><?= $h($document['classification_label'] ?? '') ?></span>
        </div>
        <h1 id="sse-doc-show-title"><?= $h($document['title'] ?? 'Document') ?></h1>
        <p class="sse-desk-hero__lead">
            <?= $h($document['document_type_label'] ?? 'Document') ?>
            <?php if (!empty($document['author_label'])): ?>
                · Rédigé par <?= $h($document['author_label']) ?>
            <?php endif; ?>
            <?php if (!empty($document['updated_at'])): ?>
                · Mis à jour <?= $h($document['updated_at']) ?>
            <?php endif; ?>
        </p>
        <?php if (!empty($document['case_reference'])): ?>
            <p class="sse-desk-card__meta" style="margin:0">
                Dossier lié —
                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) ($document['case_id'] ?? 0))) ?>">
                    <?= $h($document['case_reference']) ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
    <aside class="sse-desk-hero__side">
        <p class="interest-hero__side-label">Actions</p>
        <div class="interest-hero__actions">
            <?php if ($canManage && $editable): ?>
                <a class="btn" href="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/modifier')) ?>">Modifier</a>
            <?php endif; ?>
            <?php if ($canManage && $status === 'brouillon'): ?>
                <form method="post" action="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/statut')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="status" value="en_relecture">
                    <button class="btn btn--ghost" type="submit" style="width:100%">Soumettre en relecture</button>
                </form>
            <?php endif; ?>
            <?php if ($canManage && in_array($status, ['brouillon', 'en_relecture'], true)): ?>
                <form method="post" action="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/statut')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="status" value="valide">
                    <button class="btn" type="submit" style="width:100%">Valider le document</button>
                </form>
            <?php endif; ?>
            <?php if ($canManage && $status === 'valide'): ?>
                <form method="post" action="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/statut')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="status" value="archive">
                    <button class="btn btn--ghost" type="submit" style="width:100%">Archiver</button>
                </form>
            <?php endif; ?>
            <?php if (!empty($document['case_id'])): ?>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers/' . (int) $document['case_id'] . '/declassification')) ?>">
                    Version de diffusion
                </a>
            <?php endif; ?>
        </div>
        <div class="interest-hero__source">
            <strong>Référence</strong>
            <span><?= $h($document['reference_code'] ?? '—') ?></span>
        </div>
    </aside>
</section>

<div class="security-notice">
    <div class="security-notice-code">SEC-DOC</div>
    <div>
        <strong>Document classifié — <?= $h($document['classification_label'] ?? '') ?></strong>
        <span>
            Ne diffusez pas ce contenu hors canal. Pour une diffusion élargie,
            produisez une version caviardée et faites-la valider.
        </span>
    </div>
</div>

<section class="panel sse-desk-panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">19.12</span>
            Corps du document
        </div>
        <div class="panel-meta">
            <button class="btn btn--ghost btn--sm" type="button" data-copy="#sse-doc-body">Copier le texte</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="sse-desk-paper sse-desk-paper--read">
            <pre class="sse-report" id="sse-doc-body"><?= $h($document['body'] ?? '') ?></pre>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.querySelector(btn.getAttribute('data-copy'));
        if (!el) { return; }
        var done = function () {
            var old = btn.textContent;
            btn.textContent = 'Copié';
            setTimeout(function () { btn.textContent = old; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(el.textContent).then(done);
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = el.textContent;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* ignoré */ }
        document.body.removeChild(ta);
    });
});
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
