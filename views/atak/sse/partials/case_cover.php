<?php
declare(strict_types=1);
/**
 * Chemise (page de garde) d’un dossier SSE, rendue comme une pièce d’archive :
 * bandeaux de classification, bloc de contrôle, consignes, registre de
 * consultation, empreintes d’intégrité, sceau machine (QR) et tampons.
 *
 * Attendu :
 * - $case (array) : reference_code, title, classification_label, status_label, summary…
 * - $coverStats (array<string,int|string>, optionnel) : compteurs affichés au dos de la chemise
 * - $coverUnit (string, optionnel)
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$case = is_array($case ?? null) ? $case : [];
$coverStats = is_array($coverStats ?? null) ? $coverStats : [];
$coverUnit = trim((string) ($coverUnit ?? \App\Core\Session::get('tenant_name') ?? ''));
if ($coverUnit === '') {
    $coverUnit = 'Unité Athena';
}

$caseRef = (string) ($case['reference_code'] ?? 'AFF-····-····');
$caseTitle = (string) ($case['title'] ?? 'Dossier sans intitulé');
$classLabel = (string) ($case['classification_label'] ?? 'Confidentiel');
$classUpper = mb_strtoupper($classLabel, 'UTF-8');
$statusLabel = (string) ($case['status_label'] ?? 'En cours');
$statusKey = (string) ($case['status'] ?? '');
$summary = trim((string) ($case['summary'] ?? ''));
$openedSrc = (string) ($case['created_at'] ?? '');
$openedFr = $openedSrc !== '' ? date('d/m/Y', strtotime($openedSrc) ?: time()) : '—';
$updatedSrc = (string) ($case['updated_at'] ?? '');
$updatedFr = $updatedSrc !== '' ? date('d/m/Y', strtotime($updatedSrc) ?: time()) : $openedFr;
$isClosed = in_array($statusKey, ['clos', 'archive', 'cloture'], true);

$marks = \App\Support\SseDocumentMarkings::forDocument([
    'id' => (int) ($case['id'] ?? 0),
    'reference_code' => $caseRef,
    'title' => $caseTitle,
    'body' => $summary,
    'classification' => (string) ($case['classification'] ?? ''),
    'created_at' => $openedSrc,
    'updated_at' => $updatedSrc,
], $coverUnit);
$classCode = \App\Repositories\SseCaseRepository::normalizeClassification((string) ($case['classification'] ?? 'encadrement'));
?>
<section class="sse-case-cover" aria-label="Chemise du dossier">
    <div class="sse-doc-paper-chrome" data-classification="<?= $h($classCode) ?>">
        <article class="sse-doc-paper sse-doc-paper--<?= $h($classCode) ?>" data-classification="<?= $h($classCode) ?>">
            <div class="sse-doc-paper__banner">
                <span>(Classification de sécurité)</span>
                <strong><?= $h($classUpper) ?></strong>
                <span>Exemplaire <?= (int) $marks['copy_index'] ?>/<?= (int) $marks['copy_total'] ?></span>
            </div>
            <div class="sse-doc-paper__watermark" aria-hidden="true"><?= $h($classUpper) ?></div>

            <div class="sse-doc-paper__inner">
                <div class="sse-doc-paper__control">
                    <table class="sse-doc-paper__routing">
                        <caption>Registre de consultation</caption>
                        <thead>
                            <tr>
                                <th scope="col">N°</th>
                                <th scope="col">Consultant</th>
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
                        <p><span>Registre</span><strong><?= $h($marks['registry_number']) ?></strong></p>
                        <p><span>Ouvert le</span><strong><?= $h($openedFr) ?></strong></p>
                        <p><span>Mouvement</span><strong><?= $h($updatedFr) ?></strong></p>
                    </div>
                </div>

                <div class="sse-doc-paper__caveat">
                    <p class="sse-doc-paper__caveat-main"><?= $h($marks['channel']) ?></p>
                    <p class="sse-doc-paper__caveat-note">
                        Chemise à ne pas dissocier de ses pièces jointes. Toute sortie du local sécurisé
                        est portée au registre de consultation ci-dessus.
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
                        <p class="sse-doc-paper__unit">UNITÉ : <?= $h($coverUnit) ?></p>
                        <p>SECTION : Bureau SSE — Renseignement</p>
                        <p class="sse-doc-paper__refno">DOSSIER N° <?= $h($caseRef) ?></p>
                    </div>
                    <div class="sse-doc-paper__seal" aria-hidden="true">
                        <span class="sse-doc-paper__seal-ring"></span>
                        <span class="sse-doc-paper__seal-core"><?= $h($marks['seal_initials']) ?></span>
                        <span class="sse-doc-paper__seal-top">BUREAU SSE</span>
                        <span class="sse-doc-paper__seal-bottom">DOSSIERS</span>
                    </div>
                    <p class="sse-doc-paper__date">Le <?= $h($updatedFr) ?></p>
                </header>

                <h1 class="sse-doc-paper__doc-title">Chemise de dossier</h1>
                <p class="sse-case-cover__title"><?= $h($caseTitle) ?></p>

                <div class="sse-case-cover__facts">
                    <div><span>Statut</span><strong><?= $h($statusLabel) ?></strong></div>
                    <div><span>Classification</span><strong><?= $h($classLabel) ?></strong></div>
                    <div><span>Habilitation</span><strong><?= $isClosed ? 'Consultation sur demande' : 'Besoin d’en connaître' ?></strong></div>
                    <div><span>Code d’ouverture</span><strong><?= !empty($case['has_unlock_code']) ? 'Exigé' : 'Non exigé' ?></strong></div>
                    <?php foreach ($coverStats as $label => $value): ?>
                        <div><span><?= $h($label) ?></span><strong><?= $h((string) $value) ?></strong></div>
                    <?php endforeach; ?>
                </div>

                <h2 class="sse-doc-paper__section">Objet du dossier</h2>
                <div class="sse-doc-paper__body">
                    <?php if ($summary !== ''): ?>
                        <?= \App\Repositories\SseDocumentRepository::bodyToHtml($summary) ?>
                    <?php else: ?>
                        <p class="sse-doc-paper__muted">
                            Aucune synthèse n’a encore été portée à la chemise. Le premier rédacteur y consigne
                            l’origine de l’affaire, le périmètre retenu et les limites d’exploitation admises.
                        </p>
                    <?php endif; ?>
                </div>

                <section class="sse-doc-paper__notices" aria-label="Consignes de manipulation">
                    <h2 class="sse-doc-paper__section">Consignes de manipulation</h2>
                    <p class="sse-doc-paper__p">
                        Le dossier <strong><?= $h($caseRef) ?></strong> regroupe des pièces de niveaux de
                        protection différents. Le niveau retenu pour l’ensemble est celui de la pièce la plus
                        protégée : <strong><?= $h($classLabel) ?></strong>. Aucune pièce ne peut être extraite,
                        photographiée ou citée isolément sans reprendre cette mention. Les notes manuscrites
                        prises à partir du dossier héritent de la même classification jusqu’à leur destruction.
                    </p>
                    <p class="sse-doc-paper__p">
                        Les passages masqués protègent l’origine du renseignement, non son contenu : ils ne
                        peuvent être devinés à voix haute ni reconstitués par recoupement en réunion élargie.
                        Toute demande de levée de caviardage passe par une version de diffusion, produite et
                        validée séparément, portant sa propre référence.
                    </p>
                    <ul class="sse-doc-paper__list">
                        <li>Consultation : au poste habilité, chemise complète, sans dissociation des pièces.</li>
                        <li>Reproduction : interdite sans accord écrit du chef de bureau, exemplaire par exemplaire.</li>
                        <li>Restitution : le jour même, contre visa au registre de consultation.</li>
                        <li>Incident : signalement immédiat à l’officier de sécurité avec le numéro de contrôle.</li>
                    </ul>

                    <h2 class="sse-doc-paper__section">Conservation</h2>
                    <p class="sse-doc-paper__p">
                        Révision de classification prévue le <strong><?= $h($marks['declassify_on']) ?></strong>.
                        Conservation <strong><?= $h($marks['destruction_delay']) ?></strong> après clôture, puis
                        destruction par broyage en présence de deux personnels habilités, portée au registre.
                    </p>
                </section>

                <section class="sse-doc-paper__auth" aria-label="Authentification du dossier">
                    <?php
                    $ws = is_array($marks['workstation'] ?? null) ? $marks['workstation'] : [];
                    $wsId = (string) ($ws['id'] ?? '');
                    $wsHost = (string) ($ws['host'] ?? '');
                    $wsIp = (string) ($ws['ip'] ?? '');
                    $wsFp = (string) ($ws['fingerprint'] ?? '');
                    $wsQr = (string) ($ws['qr_html'] ?? '');
                    ?>
                    <figure class="sse-doc-paper__fp sse-doc-paper__fp--qr">
                        <div class="sse-doc-paper__fp-plate sse-doc-paper__fp-plate--qr">
                            <?= $wsQr !== '' ? $wsQr : '' ?>
                        </div>
                        <figcaption>
                            <strong><?= $h($wsId !== '' ? $wsId : 'QR-······') ?></strong>
                            <span>Sceau poste de travail</span>
                            <span><?= $h($wsHost !== '' ? $wsHost : 'SSE-WS') ?> · <?= $h($wsIp !== '' ? $wsIp : '—') ?></span>
                        </figcaption>
                    </figure>

                    <div class="sse-doc-paper__hashes">
                        <p class="sse-doc-paper__hash-title">Empreintes d’intégrité</p>
                        <dl>
                            <dt>Condensat du dossier</dt>
                            <dd class="sse-doc-paper__hash"><?= $h($marks['integrity_groups']) ?></dd>
                            <dt>Sceau d’enveloppe</dt>
                            <dd class="sse-doc-paper__hash"><?= $h($marks['envelope_hash']) ?></dd>
                            <dt>Empreinte machine</dt>
                            <dd class="sse-doc-paper__hash"><?= $h($wsFp !== '' ? $wsFp : '—') ?></dd>
                            <dt>Somme de contrôle</dt>
                            <dd class="sse-doc-paper__hash"><?= $h($marks['checksum']) ?></dd>
                            <dt>Algorithme</dt>
                            <dd><?= $h($marks['algorithm']) ?></dd>
                        </dl>
                        <p class="sse-doc-paper__hash-note">
                            Le QR encode le poste, son adresse réseau et l’empreinte machine relevés à
                            l’ouverture. Il ne remplace pas un relevé biométrique de personne.
                            Les empreintes changent à chaque modification de la synthèse : un écart
                            signale une reprise non enregistrée.
                        </p>
                    </div>

                    <div class="sse-doc-paper__stamps" aria-hidden="true">
                        <span class="sse-doc-paper__stamp sse-doc-paper__stamp--class"><?= $h($classUpper) ?></span>
                        <?php if ($isClosed): ?>
                            <span class="sse-doc-paper__stamp sse-doc-paper__stamp--archive">Dossier clos</span>
                        <?php else: ?>
                            <span class="sse-doc-paper__stamp sse-doc-paper__stamp--ok">Dossier ouvert</span>
                        <?php endif; ?>
                        <span class="sse-doc-paper__stamp sse-doc-paper__stamp--copy">
                            Exemplaire <?= (int) $marks['copy_index'] ?> / <?= (int) $marks['copy_total'] ?>
                        </span>
                    </div>
                </section>
            </div>

            <div class="sse-doc-paper__banner sse-doc-paper__banner--bottom">
                <span>Contrôle <?= $h($marks['control_number']) ?></span>
                <strong><?= $h($classUpper) ?></strong>
                <span>Chemise — page de garde</span>
            </div>
        </article>
    </div>
</section>
