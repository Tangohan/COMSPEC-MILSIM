<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $interestCase */
/** @var list<array<string,mixed>> $hypotheses */
/** @var list<array<string,mixed>> $proposals */
$c = $interestCase;
$hypotheses = is_array($hypotheses ?? null) ? $hypotheses : [];
$proposals = is_array($proposals ?? null) ? $proposals : [];
$canManage = (bool) ($canManage ?? false);
$priority = (string) ($c['interest_level'] ?? '');
$priorityBadge = $priority === 'critique' ? 'badge--red' : ($priority === 'prioritaire' ? 'badge--amber' : '');
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/interet')) ?>">Dossiers d’intérêt</a> /
    <strong><?= $h($c['reference_code'] ?? '') ?></strong>
</div>

<header class="interest-hero">
    <div class="interest-hero__main">
        <div class="interest-hero__kicker">
            <span class="interest-hero__ref"><?= $h($c['reference_code'] ?? '') ?></span>
            <span class="badge badge--amber">Dossier d’intérêt</span>
            <span class="badge">Validation humaine obligatoire</span>
        </div>
        <h1><?= $h($c['temporary_designation'] ?? 'Sujet non désigné') ?></h1>
        <p class="interest-hero__lead">
            Cible potentielle — hypothèse de travail, pas une identité confirmée.
            <?= !empty($c['suspected_alias']) ? 'Alias signalé : ' . $h($c['suspected_alias']) . '.' : 'Aucun alias renseigné.' ?>
        </p>
        <dl class="interest-facts">
            <div>
                <dt>État</dt>
                <dd><?= $h($c['status_label'] ?? '—') ?></dd>
            </div>
            <div>
                <dt>Responsable</dt>
                <dd><?= $h($c['origin_operator'] ?? 'Cellule SSE') ?></dd>
            </div>
            <div>
                <dt>Priorité</dt>
                <dd><span class="badge <?= $h($priorityBadge) ?>"><?= $h($c['interest_label'] ?? '—') ?></span></dd>
            </div>
            <div>
                <dt>Confiance dossier</dt>
                <dd><?= $h($c['confidence_label'] ?? '—') ?></dd>
            </div>
        </dl>
    </div>
    <aside class="interest-hero__side">
        <p class="interest-hero__side-label">Suite d’instruction</p>
        <div class="interest-hero__actions">
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles')) ?>">Graphe</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/chronologie')) ?>">Chronologie</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/croisements')) ?>">Croisements</a>
            <?php if ($canManage): ?>
                <a class="btn" href="<?= $h(url('atak/sse/toiles/nouveau')) ?>">Ouvrir une investigation</a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/interet')) ?>">Retour à la file</a>
        </div>
        <div class="interest-hero__source">
            <strong>Source</strong>
            <span><?= $h($c['source_label'] ?? 'Source terrain') ?></span>
            <?php if (!empty($c['source_reliability'])): ?>
                <em><?= $h($c['source_reliability']) ?></em>
            <?php endif; ?>
        </div>
    </aside>
</header>

<div class="security-notice">
    <div class="security-notice-code">HITL</div>
    <div>
        <strong>Aucune corrélation ne devient un fait sans validation opérateur</strong>
        <span>Les propositions automatiques restent des hypothèses jusqu’à confirmation, maintien séparé ou analyse complémentaire.</span>
    </div>
</div>

<div class="interest-grid">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.01</span> Hypothèses de travail</div>
            <div class="panel-meta"><?= count($hypotheses) ?></div>
        </div>
        <div class="panel-body interest-hypotheses">
            <?php if ($hypotheses === []): ?>
                <p class="muted">Aucune hypothèse consignée pour le moment.</p>
            <?php else: ?>
                <?php foreach ($hypotheses as $hyp): ?>
                    <?php $conf = (int) ($hyp['confidence'] ?? 0); ?>
                    <article class="interest-hypo">
                        <header>
                            <strong><?= $h($hyp['code'] ?? 'H') ?></strong>
                            <span class="interest-hypo__conf"><?= $conf ?> %</span>
                        </header>
                        <p><?= $h($hyp['text'] ?? '') ?></p>
                        <div class="interest-hypo__bar" aria-hidden="true"><i style="--w:<?= max(8, min(100, $conf)) ?>%"></i></div>
                        <?php if (!empty($hyp['pros']) || !empty($hyp['cons'])): ?>
                            <ul class="interest-hypo__notes">
                                <?php foreach (($hyp['pros'] ?? []) as $pro): ?>
                                    <li class="is-plus">+ <?= $h($pro) ?></li>
                                <?php endforeach; ?>
                                <?php foreach (($hyp['cons'] ?? []) as $con): ?>
                                    <li class="is-minus">− <?= $h($con) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.02</span> Raisonnement analytique</div>
        </div>
        <div class="panel-body">
            <div class="interest-reason">
                <div>
                    <h3>Éléments favorables</h3>
                    <p><?= nl2br($h((string) ($c['analysis_facts'] ?: ($c['observed_elements'] ?? 'Non renseigné')))) ?></p>
                </div>
                <div>
                    <h3>Éléments défavorables / contradictions</h3>
                    <p><?= nl2br($h((string) ($c['analysis_contradictions'] ?: 'Aucune contradiction formelle'))) ?></p>
                </div>
                <div>
                    <h3>Questions restantes</h3>
                    <p><?= nl2br($h((string) ($c['analysis_questions'] ?: '—'))) ?></p>
                </div>
                <div>
                    <h3>Recommandation</h3>
                    <p><?= nl2br($h((string) ($c['recommendations'] ?: 'Poursuivre la collecte et valider les rapprochements.'))) ?></p>
                    <p class="muted">Dernière révision : <?= $h($c['updated_at'] ?? $c['created_at'] ?? '—') ?> — <?= $h($c['origin_operator'] ?? 'Analyste') ?></p>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="interest-grid interest-grid--secondary">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.03</span> Croisements proposés</div>
            <div class="panel-meta"><?= count($proposals) ?></div>
        </div>
        <div class="panel-body">
            <?php foreach ($proposals as $p): ?>
                <article class="interest-cross">
                    <strong><?= $h($p['title'] ?? 'Corrélation proposée') ?></strong>
                    <p><?= $h($p['detail'] ?? '') ?></p>
                    <?php if ((int) ($p['score'] ?? 0) > 0): ?>
                        <span class="muted">Score indicatif : <?= (int) $p['score'] ?> % — à confirmer</span>
                    <?php endif; ?>
                    <div class="interest-cross__actions">
                        <span class="btn btn--ghost btn--sm" title="Bientôt disponible">Confirmer</span>
                        <span class="btn btn--ghost btn--sm" title="Bientôt disponible">Maintenir séparé</span>
                        <span class="btn btn--ghost btn--sm" title="Bientôt disponible">Analyse complémentaire</span>
                    </div>
                </article>
            <?php endforeach; ?>
            <p class="sse-note">Les décisions de rapprochement seront journalisées dès le branchement de la validation.</p>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.04</span> Identité observée &amp; provenance</div>
        </div>
        <div class="panel-body">
            <dl class="interest-facts interest-facts--dense">
                <div>
                    <dt>Alias supposé</dt>
                    <dd><?= $h($c['suspected_alias'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Sexe apparent</dt>
                    <dd><?= $h($c['apparent_sex'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Âge estimé</dt>
                    <dd><?= $h($c['estimated_age_range'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Nationalité</dt>
                    <dd><?= $h($c['suspected_nationality'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Affiliation</dt>
                    <dd><?= $h($c['suspected_affiliation'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Acquisition</dt>
                    <dd><?= $h($c['acquisition_at'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Mission</dt>
                    <dd><?= $h($c['mission_label'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Création</dt>
                    <dd><?= $h($c['created_at'] ?: '—') ?></dd>
                </div>
            </dl>
            <div class="interest-blocks">
                <div>
                    <h3>Motif d’ouverture</h3>
                    <p><?= nl2br($h((string) ($c['opening_reason'] ?: '—'))) ?></p>
                </div>
                <div>
                    <h3>Observations</h3>
                    <p><?= nl2br($h((string) ($c['observed_elements'] ?: '—'))) ?></p>
                </div>
                <div>
                    <h3>Risque opérationnel</h3>
                    <p><?= nl2br($h((string) ($c['operational_risk'] ?: '—'))) ?></p>
                </div>
                <div>
                    <h3>Besoins de collecte</h3>
                    <p><?= nl2br($h((string) ($c['collection_needs'] ?: '—'))) ?></p>
                </div>
            </div>
        </div>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
