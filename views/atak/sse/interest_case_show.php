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
$constitutedCase = is_array($constitutedCase ?? null) ? $constitutedCase : null;
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
        <?php if ($constitutedCase !== null): ?>
            <div class="interest-hero__next">
                <strong>Dossier constitué</strong>
                <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) $constitutedCase['id'])) ?>">
                    <?= $h($constitutedCase['reference_code'] ?? '') ?> — <?= $h($constitutedCase['title'] ?? '') ?>
                </a>
                <span>L’instruction se poursuit dans le dossier ; ce dossier d’intérêt en garde la genèse.</span>
            </div>
        <?php elseif ($canManage): ?>
            <form class="interest-hero__next" method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/constituer')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <strong>Passer au dossier</strong>
                <span>Ouvre un dossier reprenant le motif, les observations et les faits déjà consignés ici.</span>
                <button class="btn" type="submit">Constituer le dossier</button>
            </form>
        <?php endif; ?>
        <div class="interest-hero__actions">
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles')) ?>">Graphe</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/chronologie')) ?>">Chronologie</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/croisements')) ?>">Croisements</a>
            <?php if ($canManage): ?>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles/nouveau')) ?>">Ouvrir une investigation</a>
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
            <?php if ($proposals === []): ?>
                <p class="muted">
                    Aucun rapprochement automatique sur ce dossier. Poursuivez la collecte,
                    ou reliez les éléments à la main depuis les croisements.
                </p>
            <?php endif; ?>

            <?php foreach ($proposals as $p): ?>
                <?php
                $decision = is_array($p['decision'] ?? null) ? $p['decision'] : null;
                $decisionKey = (string) ($decision['decision'] ?? '');
                $stateClass = match ($decisionKey) {
                    'confirme' => ' is-confirmed',
                    'separe' => ' is-separate',
                    'complement' => ' is-further',
                    default => '',
                };
                ?>
                <article class="interest-cross<?= $stateClass ?>">
                    <strong><?= $h($p['person_name'] ?? 'Identité') ?> ↔ <?= $h($p['entry_name'] ?? 'entrée surveillée') ?></strong>
                    <p><?= $h($p['reason'] ?? '') ?></p>
                    <?php if ((int) ($p['score'] ?? 0) > 0): ?>
                        <span class="muted">
                            Score indicatif : <?= (int) $p['score'] ?> %<?= $decision === null ? ' — à confirmer' : '' ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($decision !== null): ?>
                        <p class="interest-cross__verdict">
                            <strong><?= $h($decision['decision_label'] ?? '') ?></strong>
                            <span>
                                par <?= $h($decision['author_label'] ?? 'un analyste') ?>
                                <?php
                                $when = (string) ($decision['updated_at'] ?? $decision['created_at'] ?? '');
                                $stamp = $when !== '' ? strtotime($when) : false;
                                ?>
                                <?= $stamp ? 'le ' . $h(date('d/m/Y \à H\hi', $stamp)) : '' ?>
                            </span>
                            <?php if (!empty($decision['note'])): ?>
                                <em><?= $h($decision['note']) ?></em>
                            <?php endif; ?>
                        </p>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/croisements')) ?>" class="interest-cross__form">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="person_id" value="<?= (int) ($p['person_id'] ?? 0) ?>">
                                <input type="hidden" name="entry_id" value="<?= (int) ($p['entry_id'] ?? 0) ?>">
                                <input type="hidden" name="decision" value="reouvrir">
                                <button class="btn btn--ghost btn--sm" type="submit">Revenir sur la décision</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($canManage): ?>
                        <form method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/croisements')) ?>" class="interest-cross__form">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="person_id" value="<?= (int) ($p['person_id'] ?? 0) ?>">
                            <input type="hidden" name="entry_id" value="<?= (int) ($p['entry_id'] ?? 0) ?>">
                            <input type="hidden" name="score" value="<?= (int) ($p['score'] ?? 0) ?>">
                            <input type="hidden" name="reason" value="<?= $h($p['reason'] ?? '') ?>">
                            <label class="interest-cross__note-label" for="note-<?= (int) ($p['person_id'] ?? 0) ?>-<?= (int) ($p['entry_id'] ?? 0) ?>">
                                Sur quoi repose votre décision
                            </label>
                            <input class="interest-cross__note" type="text" maxlength="255"
                                   id="note-<?= (int) ($p['person_id'] ?? 0) ?>-<?= (int) ($p['entry_id'] ?? 0) ?>"
                                   name="note" placeholder="Élément qui tranche : photo, déclaration, biométrie…">
                            <div class="interest-cross__actions">
                                <button class="btn btn--sm" type="submit" name="decision" value="confirme">Confirmer</button>
                                <button class="btn btn--ghost btn--sm" type="submit" name="decision" value="separe">Maintenir séparé</button>
                                <button class="btn btn--ghost btn--sm" type="submit" name="decision" value="complement">Analyse complémentaire</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="muted">Décision réservée aux opérateurs habilités.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($proposals !== []): ?>
                <p class="sse-note">Chaque décision est journalisée avec son auteur, son horodatage et sa justification.</p>
            <?php endif; ?>
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
