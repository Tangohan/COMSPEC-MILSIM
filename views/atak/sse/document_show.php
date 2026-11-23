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

<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-doc-editor.css')) ?>">
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-doc-themes.css')) ?>">

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
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/pdf')) ?>" style="width:100%">Exporter en PDF</a>
            <button class="btn btn--ghost" type="button" data-copy-body style="width:100%">Copier le corps</button>
        </div>
        <div class="interest-hero__source">
            <strong>Référence</strong>
            <span><?= $h($document['reference_code'] ?? '—') ?></span>
        </div>
    </aside>
</section>

<?php \App\Services\Sse\Links\SseLinksWidget::render('document', (int) ($document['id'] ?? 0)); ?>
<?php \App\Services\Sse\SseIdentifierWidget::render('document', (int) ($document['id'] ?? 0)); ?>
<?php \App\Services\Sse\Attach\SseAttachWindow::section('document', (int) ($document['id'] ?? 0)); ?>

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

<?php
$signatures = is_array($signatures ?? null) ? $signatures : [];
$canSign = (bool) ($canSign ?? false);
$signerName = (string) ($signerName ?? '');
?>
<?php if ($signatures !== [] || $canSign): ?>
<section class="sse-doc-sign panel sse-desk-panel" aria-labelledby="sse-doc-sign-title">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">19.13</span><span id="sse-doc-sign-title">Signature</span></div></div>
    <div class="panel-body">
        <?php if ($signatures !== []): ?>
            <p class="sse-desk-hint"><span aria-hidden="true">&#128274;</span> Ce document est signé : il est verrouillé en lecture seule. Chaque signature porte l’empreinte SHA-256 du contenu au moment de la signature.</p>
        <?php endif; ?>
        <?php if ($canSign): ?>
            <form method="post" action="<?= $h(url('atak/sse/documents/' . (int) $document['id'] . '/signer')) ?>" class="sse-doc-sign__form" id="sse-doc-sign-form">
                <?= \App\Core\Csrf::field() ?>
                <p>Vous signez en tant que <strong><?= $h($signerName !== '' ? $signerName : 'utilisateur connecté') ?></strong>. Une signature est personnelle : personne d’autre ne peut l’apposer à votre place. Après signature, le document ne peut plus être modifié.</p>
                <fieldset class="sse-doc-sign__ink">
                    <legend>Couleur de l’encre</legend>
                    <?php foreach (['#0b1220' => 'Noir', '#1d4ed8' => 'Bleu', '#15803d' => 'Vert'] as $inkHex => $inkName): ?>
                        <label><input type="radio" name="sigpad_ink" value="<?= $h($inkHex) ?>" <?= $inkHex === '#0b1220' ? 'checked' : '' ?>> <?= $h($inkName) ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <?php
                $sigpad = ['id' => 'sse-doc-sigpad', 'name' => 'signature_data_url', 'label' => 'Votre signature', 'required' => true, 'theme' => 'dark', 'background' => 'transparent', 'name_hint' => $signerName];
                require dirname(__DIR__, 2) . '/partials/signature_pad.php';
                ?>
                <button class="btn" type="submit">Signer et verrouiller le document</button>
            </form>
            <script>
            (function () {
                document.querySelectorAll('input[name="sigpad_ink"]').forEach(function (r) {
                    r.addEventListener('change', function () {
                        var pad = window.ComspecSignaturePad && window.ComspecSignaturePad.get('sse-doc-sigpad');
                        if (!pad || !r.checked) { return; }
                        pad.ink = r.value;
                        pad.redraw();
                        pad.refresh(true);
                    });
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="sse-doc-read panel sse-desk-panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">19.12</span>
            Lecture officielle
        </div>
        <div class="panel-meta">
            <?php if (!empty($document['updated_at'])): ?>
                Mis à jour <?= $h($document['updated_at']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php
        $livePreview = false;
        require __DIR__ . '/partials/document_paper.php';
        ?>
        <pre id="sse-doc-body-raw" class="sr-only" aria-hidden="true"><?= $h(\App\Support\SseRichText::isRich((string) ($document['body'] ?? '')) ? \App\Support\SseRichText::plainText((string) $document['body']) : ($document['body'] ?? '')) ?></pre>
    </div>
</section>

<script>
document.querySelectorAll('[data-copy-body]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById('sse-doc-body-raw');
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
