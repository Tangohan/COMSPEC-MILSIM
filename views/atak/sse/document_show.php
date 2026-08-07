<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $document */
$canManage = (bool) ($canManage ?? false);
$editable = in_array((string) ($document['status'] ?? ''), ['brouillon', 'en_relecture'], true);
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/documents')) ?>">Rédaction</a> /
    <strong><?= $h($document['reference_code'] ?? '') ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Produit de renseignement // Document</div>
        <h1><?= $h($document['title'] ?? 'Document') ?></h1>
        <p>
            <?= $h($document['document_type_label'] ?? 'Document') ?>
            · <?= $h($document['classification_label'] ?? '') ?>
            · <?= $h($document['status_label'] ?? '') ?>
            <?php if (!empty($document['author_label'])): ?>
                · Rédigé par <?= $h($document['author_label']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($document['reference_code'] ?? '') ?></strong>
        <?php if (!empty($document['case_reference'])): ?>
            <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) ($document['case_id'] ?? 0))) ?>">
                <?= $h($document['case_reference']) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

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

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">19.12</span>
            Corps du document
        </div>
        <div class="panel-meta">
            <?php if (!empty($document['updated_at'])): ?>
                Mis à jour <?= $h($document['updated_at']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body">
        <pre class="sse-report" id="sse-doc-body"><?= $h($document['body'] ?? '') ?></pre>
        <div class="toolbar-actions" style="margin-top:1rem;gap:.5rem;display:flex;flex-wrap:wrap">
            <button class="btn btn--ghost btn--sm" type="button" data-copy="#sse-doc-body">Copier</button>
            <?php if ($canManage && $editable): ?>
                <a class="btn btn--ghost btn--sm" href="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/modifier')) ?>">Modifier</a>
            <?php endif; ?>
            <?php if ($canManage && ($document['status'] ?? '') === 'brouillon'): ?>
                <form method="post" action="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/statut')) ?>" style="display:inline">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="status" value="en_relecture">
                    <button class="btn btn--ghost btn--sm" type="submit">Soumettre en relecture</button>
                </form>
            <?php endif; ?>
            <?php if ($canManage && in_array(($document['status'] ?? ''), ['brouillon', 'en_relecture'], true)): ?>
                <form method="post" action="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/statut')) ?>" style="display:inline">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="status" value="valide">
                    <button class="btn btn--sm" type="submit">Valider le document</button>
                </form>
            <?php endif; ?>
            <?php if ($canManage && ($document['status'] ?? '') === 'valide'): ?>
                <form method="post" action="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/statut')) ?>" style="display:inline">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="status" value="archive">
                    <button class="btn btn--ghost btn--sm" type="submit">Archiver</button>
                </form>
            <?php endif; ?>
            <?php if (!empty($document['case_id'])): ?>
                <a class="btn btn--ghost btn--sm" href="<?= $h(url('atak/sse/dossiers/' . (int) $document['case_id'] . '/declassification')) ?>">
                    Version de diffusion
                </a>
            <?php endif; ?>
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
