<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $person */
/** @var array<string,mixed> $objectMeta */
/** @var array<string,int> $relationCounts */
/** @var array<string,mixed> $confidence */
/** @var list<array<string,mixed>> $timeline */
/** @var array<string,mixed>|null $reasoning */
/** @var list<array<string,mixed>> $provenance */
/** @var bool $canManage */
/** @var array<string,mixed> $terrain */
$terrain = is_array($terrain ?? null) ? $terrain : [];
$idn = (string) ($objectMeta['ref'] ?? ('IDN-' . str_pad((string) ((int) ($person['id'] ?? 0)), 5, '0', STR_PAD_LEFT)));
$photo = is_array($person['primary_photo'] ?? null) ? $person['primary_photo'] : null;
$photoUrl = $photo ? (string) ($photo['url'] ?? '') : '';
$pid = (int) ($person['id'] ?? 0);
?>
<div class="breadcrumb">
    Athena / SSE / Objets /
    <a class="link" href="<?= $h(url('atak/sse/identites')) ?>">Identités</a> /
    <strong><?= $h($idn) ?></strong>
</div>

<div class="iw-object-head">
    <?php if ($photoUrl !== ''): ?>
        <img class="iw-object-photo" src="<?= $h($photoUrl) ?>" alt="Photographie faciale">
    <?php else: ?>
        <div class="iw-object-photo" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="iw-object-meta">
        <div class="iw-object-id"><?= $h($idn) ?></div>
        <h1><?= $h($person['display_name'] ?? 'Identité non établie') ?></h1>
        <p class="muted">
            Statut : <?= $h($person['status_label'] ?? 'Non confirmée') ?>
            · Dernière observation : <?= $h($objectMeta['last_seen'] ?? ($person['updated_at'] ?? $person['created_at'] ?? '—')) ?>
        </p>
        <div class="iw-object-tags">
            <?php if (($objectMeta['priority'] ?? '—') !== '—' && ($objectMeta['priority'] ?? '') !== ''): ?>
                <span class="badge badge--amber">Priorité <?= $h((string) $objectMeta['priority']) ?></span>
            <?php endif; ?>
            <?php if (($confidence['global'] ?? null) !== null): ?>
                <span class="badge">Qualité relevés <?= (int) $confidence['global'] ?> %</span>
            <?php endif; ?>
            <?php if (($objectMeta['classification'] ?? '—') !== '—' && ($objectMeta['classification'] ?? '') !== ''): ?>
                <span class="badge badge--gray"><?= $h((string) $objectMeta['classification']) ?></span>
            <?php endif; ?>
            <?php if (!empty($terrain['identity_tier_label'])): ?>
                <span class="badge"><?= $h($terrain['identity_tier_label']) ?></span>
            <?php endif; ?>
            <?php if (!empty($terrain['biometric_samples'])): ?>
                <span class="badge">Relevé biométrique</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($terrain['subject_id']) || !empty($terrain['seek_stage_label'])): ?>
            <p class="muted" style="margin-top:8px">
                <?php if (!empty($terrain['subject_id'])): ?>
                    Identifiant sujet : <strong><?= $h($terrain['subject_id']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($terrain['seek_stage_label'])): ?>
                    · Étape SEEK : <?= $h($terrain['seek_stage_label']) ?>
                <?php endif; ?>
                <?php if (($terrain['acquisition_quality_avg'] ?? null) !== null): ?>
                    · Qualité acquisition : <?= (int) $terrain['acquisition_quality_avg'] ?> %
                    (<?= $h($terrain['acquisition_quality_label'] ?? '') ?>)
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="iw-object-actions">
        <a class="iw-btn" href="<?= $h(url('atak/sse/toiles')) ?>">Ouvrir dans le graphe</a>
        <a class="iw-btn" href="<?= $h(url('atak')) ?>">Afficher sur la carte</a>
        <?php if ($canManage): ?>
            <a class="iw-btn iw-btn--solid" href="<?= $h(url('atak/sse/toiles/nouveau')) ?>">Ajouter à une investigation</a>
        <?php endif; ?>
        <a class="iw-btn" href="<?= $h(url('atak/sse/chronologie?objet=person&id=' . $pid)) ?>">Chronologie</a>
        <a class="iw-btn" href="<?= $h(url('atak/sse/croisements')) ?>">Comparer / croiser</a>
    </div>
</div>

<div class="iw-confidence" style="margin-bottom:12px">
    <div><span>Fiabilité source</span><strong><?= $h((string) ($confidence['source'] ?? '—')) ?></strong></div>
    <div><span>Crédibilité info</span><strong><?= $h((string) ($confidence['credibility'] ?? '—')) ?></strong></div>
    <div><span>Qualité technique</span><strong><?= ($confidence['technical'] ?? null) !== null ? ((int) $confidence['technical'] . ' %') : '—' ?></strong></div>
    <div><span>Corroboration</span><strong><?= ($confidence['corroboration'] ?? null) !== null ? ((int) $confidence['corroboration'] . ' %') : '—' ?></strong></div>
</div>

<div class="iw-panels">
    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">I.01</span> Identité déclarée</div></div>
        <div class="panel-body">
            <dl class="iw-kv">
                <dt>Nom / prénom</dt><dd><?= $h(trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? '')) ?: '—') ?></dd>
                <dt>Alias</dt><dd><?= $h($person['alias'] ?? '—') ?></dd>
                <dt>Sexe apparent</dt><dd><?= $h($person['sex_apparent'] ?? '—') ?></dd>
                <dt>Âge estimé</dt><dd><?= $h((string) ($person['age_estimated'] ?? '—')) ?></dd>
                <dt>Nationalité</dt><dd><?= $h($person['nationality'] ?? '—') ?></dd>
                <dt>Langue</dt><dd><?= $h($person['language_spoken'] ?? '—') ?></dd>
                <dt>Affiliation</dt><dd><?= $h($person['affiliation'] ?? '—') ?></dd>
                <dt>Document</dt><dd><?= !empty($person['id_document_present']) ? $h(($person['id_document_type'] ?? 'Document') . ' ' . ($person['id_document_number'] ?? '')) : 'Non présenté' ?></dd>
            </dl>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">I.02</span> Biométrie</div></div>
        <div class="panel-body">
            <dl class="iw-kv">
                <?php
                $bioMissing = static fn (string $v): bool => in_array($v, ['—', 'Absente', 'Non relevé', 'Non relevées'], true);
                $photoLabel = $photoUrl !== '' ? 'Disponible' : 'Absente';
                $printsLabel = (string) ($objectMeta['bio_prints'] ?? 'Non relevées');
                $irisLabel = (string) ($objectMeta['bio_iris'] ?? 'Non relevé');
                $dnaLabel = (string) ($objectMeta['bio_dna'] ?? 'Non relevé');
                $qualityLabel = ($confidence['technical'] ?? null) !== null ? ((int) $confidence['technical'] . ' %') : '—';
                $terminalLabel = (string) ($objectMeta['terminal'] ?? '—');
                $operatorLabel = trim((string) ($person['submitter_callsign'] ?? $objectMeta['collector'] ?? '')) ?: '—';
                ?>
                <dt>Photo visage</dt><dd class="<?= $bioMissing($photoLabel) ? 'iw-kv__empty' : 'iw-kv__ok' ?>"><?= $h($photoLabel) ?></dd>
                <dt>Empreintes</dt><dd class="<?= $bioMissing($printsLabel) ? 'iw-kv__empty' : 'iw-kv__ok' ?>"><?= $h($printsLabel) ?></dd>
                <dt>Iris</dt><dd class="<?= $bioMissing($irisLabel) ? 'iw-kv__empty' : 'iw-kv__ok' ?>"><?= $h($irisLabel) ?></dd>
                <dt>ADN</dt><dd class="<?= $bioMissing($dnaLabel) ? 'iw-kv__empty' : 'iw-kv__ok' ?>"><?= $h($dnaLabel) ?></dd>
                <dt>Qualité capture</dt><dd class="<?= $bioMissing($qualityLabel) ? 'iw-kv__empty' : 'iw-kv__ok' ?>"><?= $h($qualityLabel) ?></dd>
                <dt>Terminal</dt><dd class="<?= $bioMissing($terminalLabel) ? 'iw-kv__empty' : 'iw-kv__ok' ?>"><?= $h($terminalLabel) ?></dd>
                <dt>Opérateur</dt><dd class="<?= $bioMissing($operatorLabel) ? 'iw-kv__empty' : 'iw-kv__ok' ?>"><?= $h($operatorLabel) ?></dd>
            </dl>
            <?php
            $bioSamples = is_array($terrain['biometric_samples'] ?? null) ? $terrain['biometric_samples'] : [];
            if ($bioSamples !== []):
            ?>
                <ul class="iw-feed" style="margin-top:12px">
                    <?php foreach ($bioSamples as $sample): ?>
                        <li>
                            <span>
                                <strong><?= $h($sample['kind_label'] ?? 'Relevé') ?></strong>
                                — qualité <?= $h((string) ($sample['quality'] ?? '—')) ?> %
                                (<?= $h($sample['quality_label'] ?? 'Non mesurée') ?>)
                                <?php if (!empty($sample['laterality_label']) && ($sample['laterality_label'] ?? '—') !== '—'): ?>
                                    · <?= $h($sample['laterality_label']) ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">I.03</span> Évaluation</div></div>
        <div class="panel-body">
            <div class="iw-reason">
                <h3>Conclusion proposée</h3>
                <p><?= $h($reasoning['conclusion'] ?? 'Identité à confirmer — éléments partiels.') ?></p>
                <p><strong>Confiance globale :</strong> <?= $h($reasoning['confidence_label'] ?? '—') ?><?php if (($confidence['global'] ?? null) !== null): ?> — <?= (int) $confidence['global'] ?> %<?php endif; ?></p>
                <ul>
                    <?php foreach (($reasoning['pros'] ?? []) as $p): ?>
                        <li class="plus">+ <?= $h($p) ?></li>
                    <?php endforeach; ?>
                    <?php foreach (($reasoning['cons'] ?? []) as $c): ?>
                        <li class="minus">− <?= $h($c) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="muted" style="margin-top:8px">Dernière révision : <?= $h($reasoning['revised_at'] ?? date('d/m/Y H:i') . 'Z') ?> — <?= $h($reasoning['analyst'] ?? 'Cellule SSE') ?></p>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">I.04</span> Relations principales</div></div>
        <div class="panel-body">
            <div class="iw-rel-grid">
                <?php foreach ($relationCounts as $label => $n): ?>
                    <div><span><?= $h($label) ?></span><strong><?= (int) $n ?></strong></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<div class="iw-panels" style="margin-top:10px">
    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">I.05</span> Chronologie liée</div></div>
        <div class="panel-body">
            <?php if ($timeline === []): ?>
                <p class="muted">Aucun événement consolidé.</p>
            <?php else: ?>
                <ul class="iw-feed">
                    <?php foreach ($timeline as $ev): ?>
                        <li>
                            <time><?= $h($ev['at'] ?? '') ?></time>
                            <span>
                                <strong><?= $h($ev['title'] ?? '') ?></strong>
                                — <?= $h($ev['detail'] ?? '') ?>
                                <?php if (!empty($ev['kind'])): ?>
                                    <span class="badge badge--gray"><?= $h($ev['kind']) ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">I.06</span> Provenance & audit</div></div>
        <div class="panel-body">
            <div class="iw-provenance">
                <dl class="iw-kv">
                    <dt>Source primaire</dt><dd><?= $h($objectMeta['source'] ?? '—') ?></dd>
                    <dt>Collecteur</dt><dd><?= $h($objectMeta['collector'] ?? ($person['submitter_callsign'] ?? '—')) ?></dd>
                    <dt>Import</dt><dd><?= $h($objectMeta['import'] ?? '—') ?></dd>
                    <dt>Intégrité</dt><dd><?= $h($objectMeta['integrity'] ?? '—') ?></dd>
                    <dt>Classification</dt><dd><?= $h($objectMeta['classification'] ?? '—') ?></dd>
                </dl>
                <?php if ($provenance !== []): ?>
                    <hr class="sse-sep">
                    <?php foreach ($provenance as $row): ?>
                        <div style="margin-bottom:6px">
                            <time><?= $h($row['at'] ?? '') ?></time>
                            — <?= $h($row['text'] ?? '') ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="sse-note" style="margin-top:10px">
                Distinction obligatoire : fait · déclaration · observation · corrélation automatique · hypothèse · conclusion validée.
                Une corrélation algorithmique n’est jamais un fait confirmé.
            </p>
        </div>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
