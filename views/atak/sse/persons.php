<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $persons */
$total = count($persons);
?>
<div class="breadcrumb">
    Athena / SSE / Renseignement /
    <strong>Personnes</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Fiches terrain // Identités</div>
        <h1>Personnes identifiées</h1>
        <p>
            Fiches terrain issues du scénario. Lecture et rattachement aux dossiers —
            distinctes des dossiers membres de la communauté.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Index personnes</strong>
        Réf. ATH-SSE-PERSONNES
    </div>
</div>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Fiches visibles</div>
        <div class="metric-value"><?= $h(str_pad((string) $total, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Registre terrain</div>
    </div>
    <div class="metric">
        <div class="metric-label">Source</div>
        <div class="metric-value">ATAK</div>
        <div class="metric-detail">Terminal / terrain</div>
    </div>
    <div class="metric">
        <div class="metric-label">Usage</div>
        <div class="metric-value">RP</div>
        <div class="metric-detail">Simulation scénario</div>
    </div>
    <div class="metric">
        <div class="metric-label">Horodatage</div>
        <div class="metric-value"><?= $h(date('H:i')) ?></div>
        <div class="metric-detail">Heure locale</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">02.01</span>
            Registre des personnes
        </div>
        <div class="panel-meta">Fiches terrain // lecture</div>
    </div>

    <?php if ($persons === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">—</div>
                <strong>Aucune fiche enregistrée</strong>
                <p>Les personnes contrôlées depuis le terminal apparaîtront ici.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="sse-record-grid">
            <?php foreach ($persons as $p):
                $med = is_array($p['medical_context'] ?? null) ? $p['medical_context'] : [];
                $sig = is_array($p['signature'] ?? null) ? $p['signature'] : null;
                $samples = is_array($p['biometric_samples'] ?? null) ? $p['biometric_samples'] : [];
                $lesions = is_array($med['lesions'] ?? null) ? $med['lesions'] : [];
                $hits = is_array($p['watchlist'] ?? null) ? $p['watchlist'] : [];
                $statusSlug = (string) ($p['status'] ?? 'civil');
            ?>
                <article class="sse-record" data-status="<?= $h($statusSlug) ?>">
                    <header class="sse-record-head">
                        <div>
                            <span class="record-name"><?= $h($p['display_name'] ?? '') ?></span>
                            <span class="record-sub">
                                Fiche n° <?= $h(str_pad((string) ($p['id'] ?? 0), 4, '0', STR_PAD_LEFT)) ?>
                                <?php if (!empty($p['alias'])): ?>
                                    · alias « <?= $h($p['alias']) ?> »
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="badge badge-status"><?= $h($p['status_label'] ?? '') ?></span>
                    </header>

                    <?php if ($hits !== []): ?>
                        <div class="sse-record-block sse-hit">
                            <div class="sse-block-title">Liste de surveillance</div>
                            <ul class="sse-hit-list">
                                <?php foreach ($hits as $hit):
                                    $e = is_array($hit['entry'] ?? null) ? $hit['entry'] : [];
                                    $name = trim(((string) ($e['first_name'] ?? '')) . ' ' . ((string) ($e['last_name'] ?? '')));
                                    if ($name === '') { $name = (string) ($e['alias'] ?? 'Entrée surveillée'); }
                                ?>
                                    <li>
                                        <span class="sse-hit-score"><?= $h((string) ($hit['score'] ?? 0)) ?>%</span>
                                        <span class="sse-hit-name"><?= $h($name) ?></span>
                                        <span class="sse-muted"><?= $h((string) ($hit['reason'] ?? '')) ?></span>
                                        <?php if (!empty($e['threat_level'])): ?>
                                            <span class="badge"><?= $h($e['threat_level']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="sse-note">Rapprochement nominatif — à confirmer par le commandement.</p>
                        </div>
                    <?php endif; ?>

                    <dl class="sse-record-facts">
                        <?php if (!empty($p['circumstances_label'])): ?>
                            <div><dt>Circonstances</dt><dd><?= $h($p['circumstances_label']) ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($p['nationality'])): ?>
                            <div><dt>Nationalité</dt><dd><?= $h($p['nationality']) ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($p['affiliation'])): ?>
                            <div><dt>Affiliation</dt><dd><?= $h($p['affiliation']) ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($p['grid_reference'])): ?>
                            <div><dt>Grille</dt><dd><?= $h($p['grid_reference']) ?></dd></div>
                        <?php endif; ?>
                    </dl>

                    <?php if ($med !== []): ?>
                        <div class="sse-record-block">
                            <div class="sse-block-title">Constat de terrain</div>
                            <p class="sse-block-body">
                                <strong><?= $h($med['etat_label'] ?? 'Inconnu') ?></strong>
                                <?php if ((int) ($med['pouls'] ?? -1) > 0): ?>
                                    · pouls <?= $h((string) $med['pouls']) ?>/min
                                <?php endif; ?>
                                <?php if ($lesions !== []): ?>
                                    <br><span class="sse-muted">Lésions : <?= $h(implode(', ', $lesions)) ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="sse-note">Constat d’observation — ne remplace pas un bilan médical.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($samples !== []): ?>
                        <div class="sse-record-block">
                            <div class="sse-block-title">Relevés biométriques (simulation)</div>
                            <ul class="sse-sample-list">
                                <?php foreach ($samples as $s):
                                    $q = $s['quality'] === null ? null : (int) $s['quality'];
                                    $qClass = $q === null ? '' : ($q >= 80 ? 'is-good' : ($q >= 60 ? 'is-fair' : 'is-poor'));
                                ?>
                                    <li>
                                        <span class="sse-sample-kind"><?= $h($s['kind_label'] ?? '') ?></span>
                                        <?php if ($q !== null): ?>
                                            <span class="sse-gauge <?= $h($qClass) ?>">
                                                <span style="width: <?= $h((string) $q) ?>%"></span>
                                            </span>
                                            <span class="sse-sample-score"><?= $h((string) $q) ?>%</span>
                                        <?php endif; ?>
                                        <?php if (!empty($s['lab_reference'])): ?>
                                            <span class="sse-muted">réf. <?= $h($s['lab_reference']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <footer class="sse-record-foot">
                        <?php if ($sig !== null): ?>
                            <span class="sse-sig is-signed">
                                Signé ATAK · <?= $h($sig['callsign'] ?? '—') ?>
                                <?php if (!empty($sig['terminal_uid'])): ?>
                                    · terminal <?= $h($sig['terminal_uid']) ?>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="sse-sig">Non signé</span>
                        <?php endif; ?>
                        <span class="record-id"><?= $h($p['created_at'] ?? '') ?></span>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
