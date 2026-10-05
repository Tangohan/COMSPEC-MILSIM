<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * @var array<string,mixed> $note
 * @var array<string,mixed>|null $linkedCase
 * @var list<array{id:int,label:string}> $openCases
 * @var array<string,string> $statusOptions
 * @var int $attachmentsMax
 * @var bool $canManage
 * @var bool $canWrite
 */
$note = is_array($note ?? null) ? $note : [];
$attachments = is_array($note['attachments'] ?? null) ? $note['attachments'] : [];
$openCases = is_array($openCases ?? null) ? $openCases : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$attachmentsMax = (int) ($attachmentsMax ?? 4);
$canManage = (bool) ($canManage ?? false);
$canWrite = (bool) ($canWrite ?? false);
$noteId = (int) ($note['id'] ?? 0);
$csrf = \App\Core\Csrf::token();
$tone = static fn (string $code): string => \App\Support\SseFieldNoteCatalog::themeTone($code);
$coords = [];
if (!empty($note['grid_reference'])) {
    $coords[] = 'Repère ' . (string) $note['grid_reference'];
}
if ($note['lat'] !== null && $note['lng'] !== null) {
    $coords[] = sprintf('%.5f / %.5f', (float) $note['lat'], (float) $note['lng']);
}
if ($note['pos_x'] !== null && $note['pos_y'] !== null) {
    $coords[] = sprintf('Position jeu %.0f / %.0f', (float) $note['pos_x'], (float) $note['pos_y']);
}
?>
<?php
$classCode = \App\Repositories\SseCaseRepository::normalizeClassification((string) ($note['classification'] ?? 'interne'));
$classLabel = mb_strtoupper(\App\Services\Sse\SseRedactionService::levelLabel($classCode), 'UTF-8');
$reliability = [
    'A' => 'Source sûre', 'B' => 'Source habituellement sûre', 'C' => 'Source assez sûre',
    'D' => 'Source pas toujours sûre', 'E' => 'Source peu sûre', 'F' => 'Fiabilité inconnue',
];
$credibility = [
    1 => 'Information confirmée', 2 => 'Information probable', 3 => 'Information possible',
    4 => 'Information douteuse', 5 => 'Information improbable', 6 => 'Véracité inconnue',
];
$rel = strtoupper((string) ($note['source_reliability'] ?? 'C'));
$cred = (int) ($note['info_credibility'] ?? 3);
$srcCode = (string) ($note['intel_source'] ?? '');
$srcLabel = (string) ($note['intel_source_label'] ?? '');
$paragraphs = preg_split("/\R{2,}/u", trim((string) ($note['body'] ?? ''))) ?: [];
$canOpenIntake = !$canManage ? false : \App\Controllers\Admin\AdminIntelIntakeController::allowed();
?>
<div class="sse-reading-tools">
    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/fiches')) ?>">← File des fiches</a>
    <div class="sse-reading-tools__right">
        <?php if ($canOpenIntake): ?>
            <a class="btn btn--ghost" href="<?= $h(url('back-office/remontees/fiche/' . $noteId)) ?>">Suivi dans Remontées</a>
        <?php endif; ?>
        <button type="button" class="btn btn--ghost" onclick="window.print()">Imprimer</button>
    </div>
</div>

<article class="sse-reading sse-reading--<?= $h($classCode) ?>" aria-label="Fiche de renseignement <?= $h($note['reference_code'] ?? '') ?>">
    <div class="sse-reading__band"><span><?= $h($classLabel) ?></span><span><?= $h($note['reference_code'] ?? '') ?></span></div>

    <header class="sse-reading__head">
        <div>
            <p class="sse-reading__org">Bureau SSE · Renseignement</p>
            <p class="sse-reading__kind"><?= $h($note['note_kind'] ?? '') ?> — <?= $h($note['note_kind_label'] ?? '') ?></p>
            <h1 class="sse-reading__title"><?= $h(trim((string) ($note['title'] ?? '')) !== '' ? $note['title'] : 'Fiche ' . ($note['reference_code'] ?? '')) ?></h1>
            <div class="sse-note-badges">
                <?php foreach (($note['themes'] ?? []) as $themeCode): ?>
                    <span class="sse-note-badge sse-note-badge--<?= $h($tone((string) $themeCode)) ?>"><?= $h(\App\Support\SseFieldNoteCatalog::themeLabel((string) $themeCode)) ?></span>
                <?php endforeach; ?>
                <?php if (($note['urgency'] ?? '') !== 'routine'): ?>
                    <span class="sse-note-badge sse-note-badge--warning"><?= $h($note['urgency_label'] ?? '') ?></span>
                <?php endif; ?>
                <?php if (!empty($note['redacted'])): ?>
                    <span class="sse-note-badge sse-note-badge--neutral">Passages caviardés</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="sse-reading__grade" title="<?= $h(($reliability[$rel] ?? '') . ' · ' . ($credibility[$cred] ?? '')) ?>">
            <strong><?= $h($rel . $cred) ?></strong>
            <span>Cotation</span>
        </div>
    </header>

    <dl class="sse-reading__facts">
        <div><dt>Constat</dt><dd><?= $h($note['observed_date_label'] ?? '') ?> à <?= $h($note['observed_time_label'] ?? '') ?></dd></div>
        <div><dt>Lieu</dt><dd><?= $h(($note['place_label'] ?? '') !== '' ? $note['place_label'] : 'Non précisé') ?></dd></div>
        <div><dt>Position</dt><dd><?= $coords === [] ? 'Aucune position transmise' : $h(implode(' · ', $coords)) ?></dd></div>
        <div><dt>Recueil</dt><dd><?= $srcCode === '' ? 'Non précisé' : $h($srcCode . ($srcLabel !== '' ? ' — ' . $srcLabel : '')) ?></dd></div>
        <div><dt>Source</dt><dd><?= $h($rel . ' · ' . ($reliability[$rel] ?? '')) ?></dd></div>
        <div><dt>Information</dt><dd><?= $h($cred . ' · ' . ($credibility[$cred] ?? '')) ?></dd></div>
        <div><dt>Urgence</dt><dd><?= $h($note['urgency_label'] ?? '') ?></dd></div>
        <div><dt>État</dt><dd><?= $h($note['status_label'] ?? '') ?></dd></div>
    </dl>

    <section class="sse-reading__body" aria-label="Renseignement">
        <h2>Renseignement</h2>
        <?php foreach ($paragraphs as $para): ?>
            <p><?= nl2br($h($para), false) ?></p>
        <?php endforeach; ?>
        <?php if ($paragraphs === []): ?><p class="muted">Texte vide.</p><?php endif; ?>
    </section>

    <?php if ($attachments !== []): ?>
        <section class="sse-reading__atts" aria-label="Pièces jointes">
            <h2>Pièces jointes <span><?= count($attachments) ?>/<?= $attachmentsMax ?></span></h2>
            <ul>
                <?php foreach ($attachments as $i => $attachment): $blur = !empty($attachment['blurred']); ?>
                    <li class="<?= $blur ? 'is-blurred' : '' ?>">
                        <figure>
                            <?php if (!empty($attachment['is_image']) && !empty($attachment['url'])): ?>
                                <?php if ($blur && !$canManage): ?>
                                    <span class="sse-reading__img"><img src="<?= $h($attachment['url']) ?>" alt="Pièce jointe floutée"></span>
                                <?php else: ?>
                                    <a class="sse-reading__img" href="<?= $h($attachment['url']) ?>" target="_blank" rel="noopener"><img src="<?= $h($attachment['url']) ?>" alt="<?= $h($attachment['caption'] ?? 'Pièce jointe') ?>"></a>
                                <?php endif; ?>
                            <?php elseif (!empty($attachment['url'])): ?>
                                <a class="sse-reading__doc" href="<?= $h($attachment['url']) ?>" target="_blank" rel="noopener">Ouvrir le document</a>
                            <?php endif; ?>
                            <figcaption>
                                <strong>PJ <?= $i + 1 ?></strong> · <?= $h($attachment['kind_label'] ?? '') ?><?= !empty($attachment['caption']) ? ' — ' . $h($attachment['caption']) : '' ?>
                                <?php if ($blur): ?><br><em>Floutée par le bureau</em><?php endif; ?>
                            </figcaption>
                            <?php if ($canWrite): ?>
                                <form method="post"
                                      action="<?= $h(url('atak/sse/fiches/' . $noteId . '/pieces/' . (int) ($attachment['id'] ?? 0) . '/supprimer')) ?>"
                                      onsubmit="return confirm('Retirer cette pièce jointe de la fiche ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                    <button class="btn btn--ghost" type="submit">Retirer</button>
                                </form>
                            <?php endif; ?>
                        </figure>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <footer class="sse-reading__foot">
        <div><span>Rédacteur</span><strong><?= $h($note['author_label'] ?? 'Inconnu') ?></strong><?= !empty($note['author_unit']) ? ' · ' . $h($note['author_unit']) : '' ?></div>
        <div><span>Saisie</span><strong><?= $h($note['origin_label'] ?? '') ?></strong></div>
        <div><span>Dossier</span><strong>
            <?php if (is_array($linkedCase ?? null) && !empty($linkedCase['id'])): ?>
                <a href="<?= $h(url('atak/sse/dossiers/' . (int) $linkedCase['id'])) ?>"><?= $h($linkedCase['reference_code'] ?? '') ?></a>
            <?php else: ?>Aucun<?php endif; ?>
        </strong></div>
    </footer>
    <?php if (!empty($note['triage_note'])): ?>
        <p class="sse-reading__triage"><span>Suivi analyste</span> <?= $h($note['triage_note']) ?></p>
    <?php endif; ?>

    <div class="sse-reading__band sse-reading__band--bottom"><span><?= $h($classLabel) ?></span><span>Accès limité aux personnels habilités</span></div>
</article>

<?php if ($canWrite && count($attachments) < $attachmentsMax): ?>
    <section class="panel" style="margin-top:14px">
        <div class="panel-header"><div class="panel-title">Ajouter une pièce jointe</div><div class="panel-meta"><?= count($attachments) ?>/<?= $attachmentsMax ?></div></div>
        <div class="panel-body">
            <form method="post" action="<?= $h(url('atak/sse/fiches/' . $noteId . '/pieces')) ?>"
                  enctype="multipart/form-data" class="sse-filter-row">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <label class="sr-only" for="fiche-piece">Ajouter une pièce jointe</label>
                <input id="fiche-piece" type="file" name="pieces[]" multiple
                       accept="image/*,application/pdf,text/plain">
                <label class="sr-only" for="fiche-piece-caption">Légende</label>
                <input id="fiche-piece-caption" type="text" name="caption" maxlength="255"
                       placeholder="Légende (facultative)">
                <button class="btn" type="submit">Joindre</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php if ($canManage): ?>
    <div class="iw-tower-grid" style="margin-top:14px">
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">R.04</span> Suivi de la fiche</div>
            </div>
            <div class="panel-body">
                <form method="post" action="<?= $h(url('atak/sse/fiches/' . $noteId . '/suivi')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <label class="sr-only" for="fiche-suivi">Nouvel état</label>
                    <select id="fiche-suivi" name="status">
                        <?php foreach ($statusOptions as $code => $label): ?>
                            <option value="<?= $h($code) ?>" <?= ($note['status'] ?? '') === $code ? 'selected' : '' ?>>
                                <?= $h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="sr-only" for="fiche-suivi-note">Commentaire de suivi</label>
                    <input id="fiche-suivi-note" type="text" name="triage_note" maxlength="400"
                           value="<?= $h($note['triage_note'] ?? '') ?>"
                           placeholder="Ce que vous en faites, en une phrase">
                    <button class="btn" type="submit">Enregistrer le suivi</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">R.05</span> Rattachement</div>
            </div>
            <div class="panel-body">
                <form method="post" action="<?= $h(url('atak/sse/fiches/' . $noteId . '/rattachement')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <label class="sr-only" for="fiche-dossier">Dossier de rattachement</label>
                    <select id="fiche-dossier" name="case_id">
                        <option value="0">Aucun dossier</option>
                        <?php foreach ($openCases as $case): ?>
                            <option value="<?= (int) $case['id'] ?>"
                                <?= (int) ($note['case_id'] ?? 0) === (int) $case['id'] ? 'selected' : '' ?>>
                                <?= $h($case['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Rattacher</button>
                </form>
                <p class="muted" style="margin-top:10px">
                    Rattacher une fiche la fait apparaître dans le dossier concerné, sans
                    rien conclure sur les personnes qui y sont citées.
                </p>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
