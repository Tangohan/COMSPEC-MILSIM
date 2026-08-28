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
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Fiches</div>
        <h1><?= $h($note['reference_code'] ?? 'Fiche') ?></h1>
        <?php if (trim((string) ($note['title'] ?? '')) !== ''): ?>
            <p class="sse-note-title"><?= $h($note['title']) ?></p>
        <?php endif; ?>
        <p>
            <?= $h($note['note_kind_label'] ?? '') ?> —
            constat du <?= $h($note['observed_date_label'] ?? '') ?> à <?= $h($note['observed_time_label'] ?? '') ?>,
            rédigée par <?= $h($note['author_label'] ?? 'auteur inconnu') ?>
            (<?= $h($note['origin_label'] ?? '') ?>).
        </p>
        <div class="sse-note-badges" style="margin-top:10px">
            <?php foreach (($note['themes'] ?? []) as $themeCode): ?>
                <span class="sse-note-badge sse-note-badge--<?= $h($tone((string) $themeCode)) ?>">
                    <?= $h((string) $themeCode) ?> · <?= $h(\App\Support\SseFieldNoteCatalog::themeLabel((string) $themeCode)) ?>
                </span>
            <?php endforeach; ?>
            <span class="sse-note-badge sse-note-badge--kind"><?= $h($note['note_kind'] ?? '') ?></span>
            <?php if (($note['urgency'] ?? '') !== 'routine'): ?>
                <span class="sse-note-badge sse-note-badge--warning"><?= $h($note['urgency_label'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="page-reference">
        <strong><?= $h($note['status_label'] ?? '') ?></strong>
        <?= $h($note['place_label'] ?? 'Lieu non précisé') ?>
    </div>
</div>

<div class="iw-tower-grid" style="margin-top:14px">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">R.01</span> Renseignement</div>
            <div class="panel-meta"><?= (int) ($note['body_length'] ?? 0) ?> caractères</div>
        </div>
        <div class="panel-body">
            <p class="sse-note-body"><?= $h($note['body'] ?? '') ?></p>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">R.02</span> Contexte</div>
        </div>
        <div class="panel-body">
            <dl class="sse-def-list">
                <dt>Date de l’événement</dt>
                <dd><?= $h($note['observed_date_label'] ?? '') ?> à <?= $h($note['observed_time_label'] ?? '') ?></dd>
                <dt>Lieu</dt>
                <dd><?= $h($note['place_label'] ?? 'Non précisé') ?></dd>
                <dt>Coordonnées</dt>
                <dd><?= $coords === [] ? 'Aucune position transmise' : $h(implode(' · ', $coords)) ?></dd>
                <dt>Urgence</dt>
                <dd><?= $h($note['urgency_label'] ?? '') ?></dd>
                <dt>Recueil</dt>
                <dd><?php
                    $srcCode = (string) ($note['intel_source'] ?? '');
                    $srcLabel = (string) ($note['intel_source_label'] ?? '');
                    if ($srcCode === '') {
                        echo 'Non précisé';
                    } else {
                        echo $h($srcCode . ($srcLabel !== '' ? ' — ' . $srcLabel : ''));
                    }
                ?></dd>
                <dt>Origine de la saisie</dt>
                <dd><?= $h($note['origin_label'] ?? '') ?></dd>
                <dt>Unité</dt>
                <dd><?= $h($note['author_unit'] ?? 'Non précisée') ?></dd>
                <dt>Dossier rattaché</dt>
                <dd>
                    <?php if (is_array($linkedCase ?? null) && !empty($linkedCase['id'])): ?>
                        <a href="<?= $h(url('atak/sse/dossiers/' . (int) $linkedCase['id'])) ?>">
                            <?= $h($linkedCase['reference_code'] ?? '') ?> — <?= $h($linkedCase['title'] ?? '') ?>
                        </a>
                    <?php else: ?>
                        Aucun pour l’instant
                    <?php endif; ?>
                </dd>
            </dl>
            <?php if (!empty($note['triage_note'])): ?>
                <p class="muted" style="margin-top:12px">
                    Suivi analyste : <?= $h($note['triage_note']) ?>
                </p>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">R.03</span> Pièces jointes</div>
        <div class="panel-meta"><?= count($attachments) ?>/<?= $attachmentsMax ?></div>
    </div>
    <div class="panel-body">
        <?php if ($attachments === []): ?>
            <p class="muted">Aucune pièce jointe.</p>
        <?php else: ?>
            <ul class="sse-note-gallery">
                <?php foreach ($attachments as $attachment): ?>
                    <li>
                        <figure>
                            <?php if (!empty($attachment['is_image']) && !empty($attachment['url'])): ?>
                                <a href="<?= $h($attachment['url']) ?>" target="_blank" rel="noopener">
                                    <img src="<?= $h($attachment['url']) ?>" alt="<?= $h($attachment['caption'] ?? 'Pièce jointe') ?>">
                                </a>
                            <?php elseif (!empty($attachment['url'])): ?>
                                <a class="btn-open" href="<?= $h($attachment['url']) ?>" target="_blank" rel="noopener">
                                    Ouvrir le document
                                </a>
                            <?php endif; ?>
                            <figcaption>
                                <?= $h($attachment['kind_label'] ?? '') ?>
                                <?php if (!empty($attachment['original_name'])): ?>
                                    — <?= $h($attachment['original_name']) ?>
                                <?php endif; ?>
                                <?php if (!empty($attachment['author_label'])): ?>
                                    <br>Jointe par <?= $h($attachment['author_label']) ?>
                                <?php endif; ?>
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
        <?php endif; ?>

        <?php if ($canWrite && count($attachments) < $attachmentsMax): ?>
            <form method="post" action="<?= $h(url('atak/sse/fiches/' . $noteId . '/pieces')) ?>"
                  enctype="multipart/form-data" class="sse-filter-row" style="margin-top:16px">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <label class="sr-only" for="fiche-piece">Ajouter une pièce jointe</label>
                <input id="fiche-piece" type="file" name="pieces[]" multiple
                       accept="image/*,application/pdf,text/plain">
                <label class="sr-only" for="fiche-piece-caption">Légende</label>
                <input id="fiche-piece-caption" type="text" name="caption" maxlength="255"
                       placeholder="Légende (facultative)">
                <button class="btn" type="submit">Joindre</button>
            </form>
        <?php endif; ?>
    </div>
</section>

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

<p style="margin-top:14px">
    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/fiches')) ?>">Revenir à la file des fiches</a>
</p>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
