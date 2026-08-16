<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $interestCase */
/** @var list<array<string,mixed>> $hypotheses */
/** @var list<array<string,mixed>> $proposals */
/** @var list<array<string,mixed>> $journalUpdates */
/** @var array{destinataires?: list<array<string,mixed>>, interdits?: list<array<string,mixed>>} $acl */
/** @var list<array<string,mixed>> $tenantMembers */
/** @var array<string,string> $statuses */
/** @var array<string, array{blocked?: bool, remaining_seconds?: int, label?: string, human?: string}> $cooldowns */
$c = $interestCase;
$hypotheses = is_array($hypotheses ?? null) ? $hypotheses : [];
$proposals = is_array($proposals ?? null) ? $proposals : [];
$journalUpdates = is_array($journalUpdates ?? null) ? $journalUpdates : [];
$acl = is_array($acl ?? null) ? $acl : ['destinataires' => [], 'interdits' => []];
$tenantMembers = is_array($tenantMembers ?? null) ? $tenantMembers : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
$cooldowns = is_array($cooldowns ?? null) ? $cooldowns : [];
$constitutedCase = is_array($constitutedCase ?? null) ? $constitutedCase : null;
$canManage = (bool) ($canManage ?? false);
$priority = (string) ($c['interest_level'] ?? '');
$priorityBadge = $priority === 'critique' ? 'badge--red' : ($priority === 'prioritaire' ? 'badge--amber' : '');
$statusKey = (string) ($c['status'] ?? '');
$stampStatus = match (true) {
    in_array($statusKey, ['archive', 'sans_suite'], true) => 'archive',
    in_array($statusKey, ['identite_consolidee', 'correspondance_probable'], true) => 'ok',
    in_array($statusKey, ['identite_infirme'], true) => 'deny',
    in_array($statusKey, ['en_validation'], true) => 'validation',
    in_array($statusKey, ['brouillon', 'signalement_recu'], true) => 'draft',
    default => 'open',
};
$destIds = array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $acl['destinataires'] ?? []);
$denyIds = array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $acl['interdits'] ?? []);
$fmtWhen = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '';
    }
    $stamp = strtotime($raw);

    return $stamp ? date('d/m/Y \à H\hi', $stamp) : $raw;
};
$memberLabel = static function (array $m): string {
    $name = trim((string) ($m['display_name'] ?? ''));
    $cs = trim((string) ($m['callsign'] ?? ''));
    if ($name === '') {
        return $cs !== '' ? $cs : 'Membre';
    }

    return $cs !== '' ? ($name . ' (' . $cs . ')') : $name;
};
$cd = static function (string $key) use ($cooldowns): array {
    return is_array($cooldowns[$key] ?? null) ? $cooldowns[$key] : [];
};
$cdBlocked = static fn (string $key): bool => !empty($cd($key)['blocked']);
$cdHuman = static fn (string $key): string => (string) ($cd($key)['human'] ?? '');
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/interet')) ?>">Dossiers d’intérêt</a> /
    <strong><?= $h($c['reference_code'] ?? '') ?></strong>
</div>

<header class="interest-hero interest-hero--enriched">
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
        <?php if (!empty($c['description'])): ?>
            <p class="interest-hero__synopsis"><?= nl2br($h((string) $c['description'])) ?></p>
        <?php endif; ?>
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
        <div class="interest-stamps" aria-hidden="true">
            <span class="interest-stamp interest-stamp--class">Pré-SSE</span>
            <span class="interest-stamp interest-stamp--<?= $h($stampStatus) ?>"><?= $h($c['status_label'] ?? 'Ouvert') ?></span>
            <span class="interest-stamp interest-stamp--hitl">Validation humaine</span>
            <?php if ($priority === 'critique' || $priority === 'prioritaire'): ?>
                <span class="interest-stamp interest-stamp--prio"><?= $h($c['interest_label'] ?? '') ?></span>
            <?php endif; ?>
        </div>
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
                <?php if ($cdBlocked('constitute')): ?>
                    <p class="interest-cooldown"><?= $h($cdHuman('constitute')) ?></p>
                    <button class="btn" type="submit" disabled>Constituer le dossier</button>
                <?php else: ?>
                    <button class="btn" type="submit">Constituer le dossier</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        <div class="interest-hero__actions">
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles')) ?>">Graphe</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/chronologie')) ?>">Chronologie</a>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/croisements')) ?>">Croisements</a>
            <?php if ($canManage): ?>
                <?php if ($cdBlocked('open_mesh')): ?>
                    <button class="btn btn--ghost" type="button" disabled title="<?= $h($cdHuman('open_mesh')) ?>">Ouvrir une investigation</button>
                    <p class="interest-cooldown interest-cooldown--compact"><?= $h($cdHuman('open_mesh')) ?></p>
                <?php else: ?>
                    <form method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/investigation')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button class="btn btn--ghost" type="submit">Ouvrir une investigation</button>
                    </form>
                <?php endif; ?>
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

<?php if ($canManage): ?>
<section class="panel interest-ops" id="pilotage">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">P.00</span> Pilotage du dossier</div>
    </div>
    <div class="panel-body interest-ops__grid">
        <form method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/etat')) ?>" class="interest-ops__block">
            <?= \App\Core\Csrf::field() ?>
            <h3>État d’instruction</h3>
            <label for="interest-status">Nouvel état</label>
            <select id="interest-status" name="status">
                <?php foreach ($statuses as $sk => $sl): ?>
                    <option value="<?= $h($sk) ?>" <?= $sk === $statusKey ? 'selected' : '' ?>><?= $h($sl) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($cdBlocked('status')): ?>
                <p class="interest-cooldown"><?= $h($cdHuman('status')) ?></p>
            <?php elseif ($cdBlocked('publish')): ?>
                <p class="interest-cooldown"><?= $h($cdHuman('publish')) ?></p>
            <?php endif; ?>
            <div class="interest-ops__row">
                <button class="btn btn--sm" type="submit" <?= $cdBlocked('status') ? 'disabled' : '' ?>>Enregistrer l’état</button>
            </div>
            <p class="muted">Le passage en « En validation » impose un délai plus long avant une nouvelle soumission.</p>
        </form>

        <div class="interest-ops__block interest-ops__block--meta">
            <h3>Horodatage</h3>
            <dl class="interest-facts interest-facts--dense">
                <div>
                    <dt>Ouverture</dt>
                    <dd><?= $h($fmtWhen((string) ($c['created_at'] ?? '')) ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Dernière révision</dt>
                    <dd><?= $h($fmtWhen((string) ($c['updated_at'] ?? '')) ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Acquisition</dt>
                    <dd><?= $h($fmtWhen((string) ($c['acquisition_at'] ?? '')) ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Destinataires</dt>
                    <dd><?= count($acl['destinataires'] ?? []) > 0 ? count($acl['destinataires']) . ' nommé(s)' : 'Cellule SSE (large)' ?></dd>
                </div>
            </dl>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="interest-grid">
    <section class="panel" id="description">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.01</span> Description du dossier</div>
        </div>
        <div class="panel-body">
            <?php if ($canManage): ?>
                <form method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/description')) ?>" class="interest-desc-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="interest-description">Synthèse opérationnelle</label>
                    <textarea id="interest-description" name="description" rows="5" maxlength="8000"
                              placeholder="Contexte, enjeux, périmètre de collecte…"><?= $h((string) ($c['description'] ?? '')) ?></textarea>
                    <button class="btn btn--sm" type="submit">Enregistrer la description</button>
                </form>
            <?php else: ?>
                <p><?= nl2br($h((string) ($c['description'] ?: 'Aucune description rédigée pour le moment.'))) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel" id="journal">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.02</span> Mises à jour</div>
            <div class="panel-meta"><?= count($journalUpdates) ?></div>
        </div>
        <div class="panel-body">
            <?php if ($canManage): ?>
                <form method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/mise-a-jour')) ?>" class="interest-journal-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="interest-update-body">Nouvelle mise à jour</label>
                    <textarea id="interest-update-body" name="body" rows="3" maxlength="4000" required
                              placeholder="Faits nouveaux, décision, piste à suivre…"></textarea>
                    <button class="btn btn--sm" type="submit">Ajouter au journal</button>
                </form>
            <?php endif; ?>
            <?php if ($journalUpdates === []): ?>
                <p class="muted">Aucune mise à jour consignée pour le moment.</p>
            <?php else: ?>
                <ol class="interest-journal">
                    <?php foreach ($journalUpdates as $entry): ?>
                        <li>
                            <header>
                                <strong><?= $h($entry['author_label'] ?? 'Analyste') ?></strong>
                                <time datetime="<?= $h((string) ($entry['created_at'] ?? '')) ?>">
                                    <?= $h($fmtWhen((string) ($entry['created_at'] ?? '')) ?: '—') ?>
                                </time>
                            </header>
                            <p><?= nl2br($h((string) ($entry['body'] ?? ''))) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($canManage): ?>
<section class="panel" id="diffusion">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">P.03</span> Diffusion &amp; accès</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/interet/' . (int) ($c['id'] ?? 0) . '/diffusion')) ?>" class="interest-acl-form">
            <?= \App\Core\Csrf::field() ?>
            <div class="interest-acl-grid">
                <fieldset>
                    <legend>Destinataires</legend>
                    <p class="muted">S’il y a au moins un destinataire, seuls ces membres (et le créateur du dossier) peuvent consulter la fiche. Sans destinataire, la cellule SSE y a accès.</p>
                    <div class="interest-acl-list">
                        <?php if ($tenantMembers === []): ?>
                            <p class="muted">Aucun membre actif à proposer pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($tenantMembers as $m): ?>
                                <?php $uid = (int) ($m['id'] ?? 0); if ($uid < 1) continue; ?>
                                <label class="interest-acl-item">
                                    <input type="checkbox" name="destinataires[]" value="<?= $uid ?>"
                                        <?= in_array($uid, $destIds, true) ? 'checked' : '' ?>>
                                    <span><?= $h($memberLabel($m)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Accès interdit nominativement</legend>
                    <p class="muted">Ces personnes ne peuvent pas ouvrir ce dossier, même si elles font partie de la cellule.</p>
                    <div class="interest-acl-list">
                        <?php if ($tenantMembers === []): ?>
                            <p class="muted">Aucun membre actif à proposer pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($tenantMembers as $m): ?>
                                <?php $uid = (int) ($m['id'] ?? 0); if ($uid < 1) continue; ?>
                                <label class="interest-acl-item interest-acl-item--deny">
                                    <input type="checkbox" name="interdits[]" value="<?= $uid ?>"
                                        <?= in_array($uid, $denyIds, true) ? 'checked' : '' ?>>
                                    <span><?= $h($memberLabel($m)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </fieldset>
            </div>
            <button class="btn" type="submit">Enregistrer la diffusion</button>
        </form>
    </div>
</section>
<?php else: ?>
    <?php if (($acl['destinataires'] ?? []) !== [] || ($acl['interdits'] ?? []) !== []): ?>
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.03</span> Diffusion</div>
        </div>
        <div class="panel-body interest-acl-readonly">
            <?php if (($acl['destinataires'] ?? []) !== []): ?>
                <div>
                    <h3>Destinataires</h3>
                    <ul>
                        <?php foreach ($acl['destinataires'] as $row): ?>
                            <li><?= $h($row['member_label'] ?? 'Membre') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (($acl['interdits'] ?? []) !== []): ?>
                <div>
                    <h3>Accès interdit</h3>
                    <ul>
                        <?php foreach ($acl['interdits'] as $row): ?>
                            <li><?= $h($row['member_label'] ?? 'Membre') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
<?php endif; ?>

<div class="interest-grid">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.04</span> Hypothèses de travail</div>
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
            <div class="panel-title"><span class="panel-index">P.05</span> Raisonnement analytique</div>
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
                    <p class="muted">Dernière révision : <?= $h($fmtWhen((string) ($c['updated_at'] ?? $c['created_at'] ?? '')) ?: '—') ?> — <?= $h($c['origin_operator'] ?? 'Analyste') ?></p>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="interest-grid interest-grid--secondary">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">P.06</span> Croisements proposés</div>
            <div class="panel-meta"><?= count($proposals) ?></div>
        </div>
        <div class="panel-body">
            <?php if ($cdBlocked('cross_decide') && $canManage): ?>
                <p class="interest-cooldown"><?= $h($cdHuman('cross_decide')) ?></p>
            <?php endif; ?>
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
                            Score indicatif : <?= (int) ($p['score'] ?? 0) ?> %<?= $decision === null ? ' — à confirmer' : '' ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($decision !== null): ?>
                        <p class="interest-cross__verdict">
                            <strong><?= $h($decision['decision_label'] ?? '') ?></strong>
                            <span>
                                par <?= $h($decision['author_label'] ?? 'un analyste') ?>
                                <?php $when = $fmtWhen((string) ($decision['updated_at'] ?? $decision['created_at'] ?? '')); ?>
                                <?= $when !== '' ? 'le ' . $h($when) : '' ?>
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
                                <button class="btn btn--ghost btn--sm" type="submit" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Revenir sur la décision</button>
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
                                   name="note" placeholder="Élément qui tranche : photo, déclaration, biométrie…"
                                   <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>
                            <div class="interest-cross__actions">
                                <button class="btn btn--sm" type="submit" name="decision" value="confirme" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Confirmer</button>
                                <button class="btn btn--ghost btn--sm" type="submit" name="decision" value="separe" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Maintenir séparé</button>
                                <button class="btn btn--ghost btn--sm" type="submit" name="decision" value="complement" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Analyse complémentaire</button>
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
            <div class="panel-title"><span class="panel-index">P.07</span> Identité observée &amp; provenance</div>
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
                    <dd><?= $h($fmtWhen((string) ($c['acquisition_at'] ?? '')) ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Mission</dt>
                    <dd><?= $h($c['mission_label'] ?: '—') ?></dd>
                </div>
                <div>
                    <dt>Création</dt>
                    <dd><?= $h($fmtWhen((string) ($c['created_at'] ?? '')) ?: '—') ?></dd>
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
