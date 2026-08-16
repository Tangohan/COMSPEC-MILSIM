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
$isArchived = (string) ($document['status'] ?? '') === 'archive';
$marks = \App\Support\SseDocumentMarkings::forDocument($document, $unitLabel);
?>
<div class="sse-doc-paper-chrome" data-sse-doc-paper>
    <article class="sse-doc-paper" aria-label="Aperçu du document">
        <div class="sse-doc-paper__banner">
            <span>(Classification de sécurité)</span>
            <strong><?= $h($classUpper) ?></strong>
            <span>Exemplaire <?= (int) $marks['copy_index'] ?>/<?= (int) $marks['copy_total'] ?></span>
        </div>
        <div class="sse-doc-paper__watermark" aria-hidden="true"><?= $h($classUpper) ?></div>

        <div class="sse-doc-paper__inner">
            <div class="sse-doc-paper__control">
                <table class="sse-doc-paper__routing">
                    <caption>Acheminement</caption>
                    <thead>
                        <tr>
                            <th scope="col">N°</th>
                            <th scope="col">Destinataire</th>
                            <th scope="col">Date</th>
                            <th scope="col">Visa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marks['routing'] as $row): ?>
                            <tr<?= $row['holder'] === '' ? ' class="is-empty"' : '' ?>>
                                <td><?= (int) $row['slot'] ?></td>
                                <td><?= $h($row['holder']) ?></td>
                                <td><?= $h($row['date']) ?></td>
                                <td class="sse-doc-paper__visa"><?= $h($row['initials']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="sse-doc-paper__ctrlbox">
                    <p><span>Contrôle n°</span><strong><?= $h($marks['control_number']) ?></strong></p>
                    <p><span>Exemplaire</span><strong><?= (int) $marks['copy_index'] ?> sur <?= (int) $marks['copy_total'] ?></strong></p>
                    <p><span>Registre</span><strong><?= $h($marks['registry_number']) ?></strong></p>
                    <p><span>Pages</span><strong><?= (int) $marks['pages'] ?></strong></p>
                </div>
            </div>

            <div class="sse-doc-paper__caveat">
                <p class="sse-doc-paper__caveat-main"><?= $h($marks['channel']) ?></p>
                <p class="sse-doc-paper__caveat-note">
                    L’accès est limité aux personnels habilités et inscrits au registre du bureau SSE.
                </p>
                <ul class="sse-doc-paper__caveat-tags">
                    <?php foreach ($marks['caveats'] as $caveat): ?>
                        <li><?= $h($caveat) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <header class="sse-doc-paper__envelope">
                <div class="sse-doc-paper__org">
                    <p>ATHENA · COMPSEC</p>
                    <p class="sse-doc-paper__unit">UNITÉ : <?= $h($unitLabel) ?></p>
                    <p>SECTION : <?= $h($sectionLabel) ?></p>
                    <p class="sse-doc-paper__refno">N° <?= $h($ref) ?> / SSE / <?= $h(mb_strtoupper($typeLabel, 'UTF-8')) ?></p>
                </div>
                <div class="sse-doc-paper__seal" aria-hidden="true">
                    <span class="sse-doc-paper__seal-ring"></span>
                    <span class="sse-doc-paper__seal-core"><?= $h($marks['seal_initials']) ?></span>
                    <span class="sse-doc-paper__seal-top">BUREAU SSE</span>
                    <span class="sse-doc-paper__seal-bottom">RENSEIGNEMENT</span>
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

            <section class="sse-doc-paper__notices" aria-label="Mentions réglementaires">
                <h2 class="sse-doc-paper__section">Instructions de traitement</h2>
                <p class="sse-doc-paper__p">
                    Le présent document est enregistré au registre du bureau SSE sous le numéro
                    <strong><?= $h($marks['registry_number']) ?></strong>. Il est remis contre visa et doit être
                    conservé en armoire fermée dès qu’il n’est plus en cours d’exploitation. Toute consultation
                    hors du poste de travail habilité fait l’objet d’une inscription au bordereau d’acheminement
                    ci-dessus, y compris lorsqu’elle est brève.
                </p>
                <p class="sse-doc-paper__p">
                    En cas de perte, de reproduction non autorisée ou de doute sur l’intégrité de l’exemplaire,
                    l’officier de sécurité est prévenu sans délai et le numéro de contrôle
                    <strong><?= $h($marks['control_number']) ?></strong> lui est communiqué. Les passages masqués
                    correspondent à des éléments dont l’origine reste protégée : ils ne peuvent être reconstitués
                    ni commentés à l’oral en dehors du canal indiqué en tête de document.
                </p>

                <h2 class="sse-doc-paper__section">Diffusion et reproduction</h2>
                <p class="sse-doc-paper__p">
                    La diffusion est strictement limitée aux destinataires figurant au bordereau. La reproduction,
                    même partielle, la photographie de l’écran et la recopie manuscrite des paragraphes classifiés
                    sont interdites sans accord écrit du chef de bureau. Une version de diffusion élargie peut être
                    produite : elle porte alors un caviardage validé et une référence propre, distincte de celle
                    du présent exemplaire.
                </p>
                <ul class="sse-doc-paper__list">
                    <li>Transmission numérique : uniquement par messagerie de service chiffrée.</li>
                    <li>Transmission physique : double enveloppe, l’enveloppe intérieure portant la classification.</li>
                    <li>Extraction vers un support amovible : interdite sauf autorisation nominative.</li>
                </ul>

                <h2 class="sse-doc-paper__section">Déclassification et destruction</h2>
                <p class="sse-doc-paper__p">
                    Révision de classification prévue le <strong><?= $h($marks['declassify_on']) ?></strong>, sauf
                    prorogation motivée. Durée de conservation : <strong><?= $h($marks['destruction_delay']) ?></strong>
                    à compter de la clôture du dossier rattaché. La destruction est réalisée par broyage en présence
                    de deux personnels habilités, puis portée au registre avec le numéro d’exemplaire.
                </p>
            </section>

            <section class="sse-doc-paper__auth" aria-label="Authentification du document">
                <figure class="sse-doc-paper__fp">
                    <div class="sse-doc-paper__fp-plate">
                        <?= $marks['fingerprint_svg'] ?>
                    </div>
                    <figcaption>
                        <strong><?= $h($marks['fingerprint_id']) ?></strong>
                        <span>Empreinte d’archivage — index droit</span>
                        <span>Relevé au dépôt de l’exemplaire</span>
                    </figcaption>
                </figure>

                <div class="sse-doc-paper__hashes">
                    <p class="sse-doc-paper__hash-title">Empreintes d’intégrité</p>
                    <dl>
                        <dt>Condensat du document</dt>
                        <dd class="sse-doc-paper__hash"><?= $h($marks['integrity_groups']) ?></dd>
                        <dt>Sceau d’enveloppe</dt>
                        <dd class="sse-doc-paper__hash"><?= $h($marks['envelope_hash']) ?></dd>
                        <dt>Somme de contrôle</dt>
                        <dd class="sse-doc-paper__hash"><?= $h($marks['checksum']) ?></dd>
                        <dt>Algorithme</dt>
                        <dd><?= $h($marks['algorithm']) ?></dd>
                    </dl>
                    <p class="sse-doc-paper__hash-note">
                        <?php if ($livePreview): ?>
                            Les empreintes sont recalculées à chaque enregistrement du document.
                        <?php else: ?>
                            Vérifier la concordance des empreintes avant toute exploitation opérationnelle.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="sse-doc-paper__stamps" aria-hidden="true">
                    <span class="sse-doc-paper__stamp sse-doc-paper__stamp--class"><?= $h($classUpper) ?></span>
                    <?php if ($isArchived): ?>
                        <span class="sse-doc-paper__stamp sse-doc-paper__stamp--archive">Versé aux archives</span>
                    <?php elseif ($isValidated): ?>
                        <span class="sse-doc-paper__stamp sse-doc-paper__stamp--ok">Original signé</span>
                    <?php else: ?>
                        <span class="sse-doc-paper__stamp sse-doc-paper__stamp--draft">Projet — ne pas diffuser</span>
                    <?php endif; ?>
                    <span class="sse-doc-paper__stamp sse-doc-paper__stamp--copy">
                        Exemplaire <?= (int) $marks['copy_index'] ?> / <?= (int) $marks['copy_total'] ?>
                    </span>
                </div>
            </section>

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

        <div class="sse-doc-paper__banner sse-doc-paper__banner--bottom">
            <span>Contrôle <?= $h($marks['control_number']) ?></span>
            <strong><?= $h($classUpper) ?></strong>
            <span>Page 1 sur <?= (int) $marks['pages'] ?></span>
        </div>
    </article>
</div>
