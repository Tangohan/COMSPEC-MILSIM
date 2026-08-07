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
        <div class="page-heading-overline">Objets // Identités</div>
        <h1>Identités</h1>
        <p>
            Chaque fiche est un objet réutilisable : dossiers, toiles, chronologie et carte
            s’appuient sur le même registre — pas un formulaire isolé.
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
                $iq = is_array($p['identity_query'] ?? null) ? $p['identity_query'] : [];
                $custody = is_array($p['custody'] ?? null) ? $p['custody'] : [];
                $photo = is_array($p['primary_photo'] ?? null) ? $p['primary_photo'] : null;
                $photoUrl = $photo !== null ? (string) ($photo['url'] ?? '') : '';
                $statusSlug = (string) ($p['status'] ?? 'civil');
            ?>
                <article class="sse-record" data-status="<?= $h($statusSlug) ?>">
                    <header class="sse-record-head">
                        <a class="link" href="<?= $h(url('atak/sse/identites/' . (int) ($p['id'] ?? 0))) ?>">
                            IDN-<?= $h(str_pad((string) ((int) ($p['id'] ?? 0)), 5, '0', STR_PAD_LEFT)) ?>
                        </a>
                        <?php if ($photoUrl !== ''): ?>
                            <a class="sse-mugshot sse-scan" href="<?= $h($photoUrl) ?>" target="_blank" rel="noopener"
                               title="<?= $h($photo['angle_label'] ?? 'Photographie') ?>">
                                <img src="<?= $h($photoUrl) ?>"
                                     alt="Photographie de <?= $h($p['display_name'] ?? 'la personne') ?>"
                                     loading="lazy">
                            </a>
                        <?php else: ?>
                            <span class="sse-mugshot is-empty" aria-hidden="true">—</span>
                        <?php endif; ?>
                        <div class="sse-record-ident">
                            <a class="record-name link" href="<?= $h(url('atak/sse/identites/' . (int) ($p['id'] ?? 0))) ?>"><?= $h($p['display_name'] ?? '') ?></a>
                            <span class="record-sub">
                                IDN-<?= $h(str_pad((string) ((int) ($p['id'] ?? 0)), 5, '0', STR_PAD_LEFT)) ?>
                                <?php if (!empty($p['alias'])): ?>
                                    · alias « <?= $h($p['alias']) ?> »
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="badge badge-status"><?= $h($p['status_label'] ?? '') ?></span>
                    </header>

                    <?php if ($iq !== []):
                        $iqRes = (string) ($iq['result'] ?? 'none');
                        $iqClass = $iqRes === 'confirmed' ? 'is-confirmed' : ($iqRes === 'possible' ? 'is-possible' : 'is-none');
                        $iqLabel = match ($iqRes) {
                            'confirmed' => 'Correspondance confirmée',
                            'possible' => 'Correspondance possible',
                            default => 'Aucune correspondance',
                        };
                    ?>
                        <div class="sse-record-block sse-idq <?= $h($iqClass) ?>">
                            <div class="sse-block-title">Requête d’identité — terminal</div>
                            <p class="sse-block-body">
                                <strong><?= $h($iqLabel) ?></strong>
                                <?php if ($iqRes !== 'none'): ?>
                                    · <?= $h((string) ($iq['confidence'] ?? '')) ?>&nbsp;%
                                    <?php if (!empty($iq['record_ref'])): ?>
                                        · dossier <?= $h($iq['record_ref']) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                            <p class="sse-note">Verdict rendu par le terminal sur relevés simulés.</p>
                        </div>
                    <?php endif; ?>

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

                    <?php if ($custody !== []): ?>
                        <div class="sse-record-block">
                            <div class="sse-block-title">Chaîne de possession</div>
                            <ol class="sse-custody">
                                <?php foreach ($custody as $ev): ?>
                                    <li>
                                        <span class="sse-custody-when">
                                            <?= $h(substr((string) ($ev['created_at'] ?? ''), 0, 16)) ?>
                                        </span>
                                        <span class="sse-custody-what"><?= $h($ev['type_label'] ?? '') ?></span>
                                        <?php if (!empty($ev['label'])): ?>
                                            <span class="sse-muted"><?= $h($ev['label']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($ev['actor'])): ?>
                                            <span class="sse-custody-who"><?= $h($ev['actor']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>

                    <footer class="sse-record-foot">
                        <a class="iw-btn" href="<?= $h(url('atak/sse/identites/' . (int) ($p['id'] ?? 0))) ?>">Ouvrir la fiche objet</a>
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
