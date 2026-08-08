<?php
declare(strict_types=1);
/**
 * Rendu papier officiel d’un document SSE (inspiré Courrier / CERBERE).
 *
 * Attendu :
 * - $document (array) : title, body, reference_code, classification_label, status_label,
 *   document_type_label, author_label, case_reference, created_at, updated_at, validated_at
 * - $unitLabel (string, optionnel)
 * - $sectionLabel (string, optionnel) — défaut Bureau SSE
 * - $livePreview (bool) — si true, corps injecté via #sse-doc-paper-body
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$document = is_array($document ?? null) ? $document : [];
$unitLabel = trim((string) ($unitLabel ?? \App\Core\Session::get('tenant_name') ?? \App\Core\Session::get('community_name') ?? ''));
if ($unitLabel === '') {
    $unitLabel = 'Unité Athena';
}
$sectionLabel = trim((string) ($sectionLabel ?? 'Bureau SSE — Renseignement'));
$livePreview = (bool) ($livePreview ?? false);
$classLabel = (string) ($document['classification_label'] ?? 'Confidentiel');
$classUpper = mb_strtoupper($classLabel, 'UTF-8');
$ref = (string) ($document['reference_code'] ?? 'DOC-····-····');
$title = (string) ($document['title'] ?? '');
$author = (string) ($document['author_label'] ?? 'Rédacteur');
$typeLabel = (string) ($document['document_type_label'] ?? 'Document');
$statusLabel = (string) ($document['status_label'] ?? 'Brouillon');
$caseRef = (string) ($document['case_reference'] ?? '');
$dateSrc = (string) ($document['validated_at'] ?? $document['updated_at'] ?? $document['created_at'] ?? '');
$dateFr = $dateSrc !== '' ? date('d/m/Y', strtotime($dateSrc) ?: time()) : date('d/m/Y');
$bodyHtml = $livePreview
    ? ''
    : \App\Repositories\SseDocumentRepository::bodyToHtml((string) ($document['body'] ?? ''));
$isValidated = in_array((string) ($document['status'] ?? ''), ['valide', 'archive'], true);
?>
<div class="sse-doc-paper-chrome" data-sse-doc-paper>
    <article class="sse-doc-paper" aria-label="Aperçu du document">
        <div class="sse-doc-paper__banner">Classification — <?= $h($classLabel) ?></div>
        <div class="sse-doc-paper__watermark" aria-hidden="true"><?= $h($classUpper) ?></div>

        <div class="sse-doc-paper__inner">
            <header class="sse-doc-paper__envelope">
                <div class="sse-doc-paper__org">
                    <p>ATHENA · COMPSEC</p>
                    <p class="sse-doc-paper__unit">UNITÉ : <?= $h($unitLabel) ?></p>
                    <p>SECTION : <?= $h($sectionLabel) ?></p>
                    <p class="sse-doc-paper__refno">N° <?= $h($ref) ?> / SSE / <?= $h(mb_strtoupper($typeLabel, 'UTF-8')) ?></p>
                </div>
                <p class="sse-doc-paper__date">Le <?= $h($dateFr) ?></p>
            </header>

            <div class="sse-doc-paper__meta-right">
                <p><strong><?= $h($author) ?></strong></p>
                <p><?= $h($typeLabel) ?> · <?= $h($statusLabel) ?></p>
            </div>

            <div class="sse-doc-paper__refs">
                <?php if ($title !== ''): ?>
                    <p><span>OBJET</span> : <?= $h($title) ?></p>
                <?php else: ?>
                    <p><span>OBJET</span> : <em class="sse-doc-paper__muted">(intitulé à renseigner)</em></p>
                <?php endif; ?>
                <p><span>RÉFÉRENCE</span> : <?= $h($ref) ?></p>
                <?php if ($caseRef !== ''): ?>
                    <p><span>DOSSIER</span> : <?= $h($caseRef) ?></p>
                <?php endif; ?>
            </div>

            <div class="sse-doc-paper__body" id="sse-doc-paper-body">
                <?= $bodyHtml !== '' ? $bodyHtml : '<p class="sse-doc-paper__muted">Le corps du document apparaîtra ici.</p>' ?>
            </div>

            <footer class="sse-doc-paper__sign <?= $isValidated ? 'is-signed' : 'is-draft' ?>">
                <p class="sse-doc-paper__sign-title">Signature</p>
                <?php if ($isValidated): ?>
                    <div class="sse-doc-paper__sign-stamp">Document validé</div>
                    <p class="sse-doc-paper__sign-name"><?= $h($author) ?></p>
                    <?php if (!empty($document['validated_at'])): ?>
                        <p class="sse-doc-paper__sign-meta">Validé le <?= $h(date('d/m/Y H:i', strtotime((string) $document['validated_at']) ?: time())) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sse-doc-paper__sign-box">Signature numérique</div>
                    <p class="sse-doc-paper__sign-name"><?= $h($author) ?></p>
                    <p class="sse-doc-paper__sign-meta">En attente de validation</p>
                <?php endif; ?>
            </footer>
        </div>
    </article>
</div>
