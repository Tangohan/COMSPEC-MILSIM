<?php
declare(strict_types=1);
ob_start();
/**
 * Fiche d'un dossier d'intérêt : en-tête (état, priorité, classification, actions) et sept onglets (synthèse, analyse, éléments
 * liés, historique, pièces et documents, discussion, accès). Tout ce qui est affiché a déjà été filtré par SseInterestCaseService.
 * @var array<string,mixed> $interestCase
 * @var string $activeTab
 * @var list<array<string,mixed>> $links
 * @var list<array<string,mixed>> $history
 * @var list<array<string,mixed>> $updates
 * @var list<array{key:string,label:string,needs_reason:bool}> $next_statuses
 * @var \App\Services\Sse\SseInterestActor $actor
 */
$form = is_array($form ?? null) ? $form : ['modal' => '', 'errors' => [], 'old' => []];
require __DIR__ . '/interest/_helpers.php';
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $diH(asset_url('assets/css/sse-dossiers-interet.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $diH(asset_url('assets/js/sse-dossiers-interet.js')) . '"></script>';
$c = $interestCase;
$cid = (int) $c['id'];
$base = url('atak/sse/interet/' . $cid);
$caps = is_array($capabilities ?? null) ? $capabilities : ['columns' => false, 'history' => false, 'links' => false, 'watchers' => false, 'acl' => false];
$diCaps = $caps;
$isTrashed = !empty($c['deleted_at']);
$canWrite = $actor->canWrite && !$isTrashed;
$updates = is_array($updates ?? null) ? $updates : [];
$links = is_array($links ?? null) ? $links : [];
$history = is_array($history ?? null) ? $history : [];
$acl = is_array($acl ?? null) ? $acl : ['destinataires' => [], 'interdits' => []];
$hypotheses = is_array($hypotheses ?? null) ? $hypotheses : [];
$proposals = is_array($proposals ?? null) ? $proposals : [];
$cooldowns = is_array($cooldowns ?? null) ? $cooldowns : [];
$members = is_array($members ?? null) ? $members : [];
$tenantMembers = is_array($tenantMembers ?? null) ? $tenantMembers : [];
$constitutedCase = is_array($constitutedCase ?? null) ? $constitutedCase : null;
$next_statuses = is_array($next_statuses ?? null) ? $next_statuses : [];
$purge_blockers = is_array($purge_blockers ?? null) ? $purge_blockers : [];
$jnetCount = (int) ($jnetCount ?? 0);
$cdBlocked = static fn (string $key): bool => !empty(($cooldowns[$key] ?? [])['blocked']);
$cdHuman = static fn (string $key): string => (string) (($cooldowns[$key] ?? [])['human'] ?? '');
$destIds = array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $acl['destinataires'] ?? []);
$denyIds = array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $acl['interdits'] ?? []);
$memberLabel = static fn (array $m): string => (string) ($m['label'] ?? 'Membre');
$docLinks = array_values(array_filter($links, static fn (array $l): bool => in_array($l['type'], ['document', 'fiche', 'case'], true)));
$tabs = [
    'synthese' => ['Synthèse', null],
    'analyse' => ['Analyse', null],
    'liens' => ['Éléments liés', count($links)],
    'historique' => ['Historique', (int) ($history_total ?? count($history))],
    'pieces' => ['Pièces et documents', count($docLinks) + ($constitutedCase !== null ? 1 : 0) + ($jnetCount > 0 ? 1 : 0)],
    'discussion' => ['Discussion', count($updates)],
    'acces' => ['Accès', null],
];
$canExportPdf = $actor->canExport && !$isTrashed;
$unarchiveVersion = (int) $c['version'];
?>
<div class="di" data-di-root>
    <a class="di-back" href="<?= $diH(url('atak/sse/interet')) ?>">← Dossiers d’intérêt</a>

    <?php if (!empty($degraded) && $actor->canWrite): ?>
        <div class="di-alert di-alert--warn" role="status">
            <div>
                <strong>Mise à niveau de la base en attente</strong>
                <p>Certaines fonctions (responsable, échéance, classification, corbeille, historique détaillé, éléments liés) s’activeront dès que l’étape « Dossiers d’intérêt SSE » aura été lancée par un administrateur.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isTrashed): ?>
        <div class="di-alert di-alert--bad" role="status">
            <div>
                <strong>Ce dossier est à la corbeille</strong>
                <dl class="di-facts">
                    <div><dt>Supprimé le</dt><dd><?= $diH($diDate($c['deleted_at'])) ?></dd></div>
                    <div><dt>Par</dt><dd><?= $diH($deleted_by_label ?? '—') ?></dd></div>
                    <div><dt>Motif</dt><dd><?= $diH($c['delete_reason'] ?? '—') ?></dd></div>
                    <div><dt>Suppression définitive dans</dt><dd><?= (int) ($days_left ?? 0) ?> jour(s)</dd></div>
                </dl>
                <div class="di-alert__actions">
                    <?php if ($actor->canDelete): ?>
                        <form method="post" action="<?= $diH($base . '/restaurer') ?>"><?= \App\Core\Csrf::field() ?><button type="submit" class="di-btn di-btn--primary"><?= $diIcon('restore') ?><span>Restaurer le dossier</span></button></form>
                    <?php endif; ?>
                    <?php if ($actor->canManage): ?>
                        <button type="button" class="di-btn di-btn--danger" data-di-open="di-purge"><?= $diIcon('trash') ?><span>Supprimer définitivement</span></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <header class="di-hero di-hero--<?= $diH(strtolower((string) ($c['priority_code'] ?? 'P4'))) ?>">
        <div class="di-hero__top">
            <div class="di-hero__title">
                <p class="di-hero__ref"><span class="di-mono"><?= $diH($c['reference_code']) ?></span><span>ouvert le <?= $diH($diDate($c['created_at'])) ?><?php if (!empty($creator_label)): ?> par <?= $diH($creator_label) ?><?php endif; ?></span></p>
                <h1><?= $diH($c['temporary_designation']) ?></h1>
                <div class="di-hero__badges">
                    <?= $diStatus($c) ?>
                    <?= $diPrio($c) ?>
                    <span class="di-tag di-tag--class di-tag--class-<?= $diH($c['classification'] ?? 'interne') ?>"><?= $diH($c['classification_label'] ?? '') ?></span>
                    <?php if (!empty($c['type_label'])): ?><span class="di-tag"><?= $diH($c['type_label']) ?></span><?php endif; ?>
                    <?php foreach ($c['tag_list'] as $tag): ?><span class="di-tag di-tag--soft">#<?= $diH($tag) ?></span><?php endforeach; ?>
                </div>
                <p class="di-hero__note">Hypothèse de travail : aucune identité confirmée sans validation humaine.</p>
            </div>
            <div class="di-hero__actions">
                <?php if ($canWrite): ?>
                    <div class="di-actions di-actions--main">
                        <?php if ($next_statuses !== []): ?><button type="button" class="di-btn di-btn--primary" data-di-open="di-status"><?= $diIcon('flow') ?><span>Changer l’état</span></button><?php endif; ?>
                        <button type="button" class="di-btn" data-di-open="di-edit"><?= $diIcon('edit') ?><span>Modifier</span></button>
                    </div>
                <?php endif; ?>
                <div class="di-actions di-actions--more">
                    <?php if (!$isTrashed && !empty($caps['watchers']) && $actor->userId !== null): ?>
                        <form method="post" action="<?= $diH($base . '/abonnement') ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="watch" value="<?= !empty($watching) ? '0' : '1' ?>">
                            <button type="submit" class="di-btn di-btn--ghost" aria-pressed="<?= !empty($watching) ? 'true' : 'false' ?>"><?= $diIcon('eye') ?><span><?= !empty($watching) ? 'Ne plus suivre' : 'Suivre' ?></span></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canExportPdf): ?><a class="di-btn di-btn--ghost" href="<?= $diH($base . '/pdf') ?>"><?= $diIcon('pdf') ?><span>PDF</span></a><?php endif; ?>
                    <?php if (!$isTrashed && $c['status'] === 'archive' && $actor->canDelete): ?>
                        <form method="post" action="<?= $diH($base . '/desarchiver') ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="version" value="<?= $unarchiveVersion ?>"><button type="submit" class="di-btn di-btn--ghost"><?= $diIcon('restore') ?><span>Sortir des archives</span></button></form>
                    <?php endif; ?>
                    <?php if (!$isTrashed && $actor->canDelete && !empty($caps['columns'])): ?>
                        <button type="button" class="di-btn di-btn--danger di-btn--quiet" data-di-open="di-trash"><?= $diIcon('trash') ?><span>Supprimer</span></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <dl class="di-facts di-facts--tiles">
            <div><dt>Responsable</dt><dd><?= !empty($c['assignee_label']) ? $diH($c['assignee_label']) : '<span class="di-muted-text">Non attribué</span>' ?></dd></div>
            <div><dt>Échéance</dt><dd><?php if (!empty($c['due_at'])): ?><span class="<?= !empty($c['is_overdue']) ? 'di-late' : '' ?>"><?= $diH($diDate($c['due_at'], 'd/m/Y')) ?><?= !empty($c['is_overdue']) ? ' · en retard' : '' ?></span><?php else: ?><span class="di-muted-text">Aucune</span><?php endif; ?></dd></div>
            <div><dt>Confiance dans le signalement</dt><dd><?= $diH($c['confidence_label']) ?></dd></div>
            <div><dt>Dernière activité</dt><dd><?= $diH($diDate($c['updated_at'])) ?></dd></div>
            <div><dt>Version</dt><dd><?= (int) $c['version'] ?></dd></div>
        </dl>
    </header>

    <?php \App\Services\Sse\Links\SseLinksWidget::render('interest', (int) ($c['id'] ?? 0)); ?>
    <?php \App\Services\Sse\Attach\SseAttachWindow::section('interest', (int) ($c['id'] ?? 0)); ?>

    <div class="di-tabs" role="tablist" aria-label="Sections du dossier" data-di-tabs>
        <?php foreach ($tabs as $key => [$label, $count]): ?>
            <a class="di-tab<?= $activeTab === $key ? ' is-active' : '' ?>" role="tab" id="di-tab-<?= $key ?>" aria-controls="di-panel-<?= $key ?>" aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>" href="<?= $diH($base . '?onglet=' . $key) ?>" data-di-tab="<?= $key ?>">
                <?= $diH($label) ?><?php if ($count !== null): ?><span class="di-count"><?= (int) $count ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Synthèse -->
    <section class="di-pane" role="tabpanel" id="di-panel-synthese" aria-labelledby="di-tab-synthese" <?= $activeTab === 'synthese' ? '' : 'hidden' ?>>
        <div class="di-grid">
            <div>
                <section class="di-panel" id="description">
                    <div class="di-panel__head"><h2>Motif d’ouverture</h2><p>Pourquoi ce dossier existe et ce qui a été observé au départ.</p></div>
                    <div class="di-rich"><?= \App\Support\SseInterestMarkup::toHtml((string) $c['opening_reason']) ?: '<p class="di-muted-text">Aucun motif renseigné (brouillon).</p>' ?></div>
                </section>
                <section class="di-panel">
                    <div class="di-panel__head"><h2>Description</h2><p>Contexte, enjeux et périmètre de collecte.</p></div>
                    <div class="di-rich"><?= \App\Support\SseInterestMarkup::toHtml((string) ($c['description'] ?? '')) ?: '<p class="di-muted-text">Aucune description rédigée pour le moment.</p>' ?></div>
                    <?php if ($canWrite): ?><p class="di-mt-12"><button type="button" class="di-btn" data-di-open="di-edit"><?= $diIcon('edit') ?><span>Modifier la description</span></button></p><?php endif; ?>
                </section>
            </div>
            <div>
                <section class="di-panel">
                    <div class="di-panel__head"><h2>Identité observée et provenance</h2><p>Ce qui est signalé, sans valeur d’identification.</p></div>
                    <dl class="di-facts di-facts--panel">
                        <div><dt>Alias supposé</dt><dd><?= $diV($c['suspected_alias']) ?></dd></div>
                        <div><dt>Affiliation</dt><dd><?= $diV($c['suspected_affiliation']) ?></dd></div>
                        <div><dt>Sexe apparent</dt><dd><?= $diV($c['apparent_sex']) ?></dd></div>
                        <div><dt>Âge estimé</dt><dd><?= $diV($c['estimated_age_range']) ?></dd></div>
                        <div><dt>Nationalité</dt><dd><?= $diV($c['suspected_nationality']) ?></dd></div>
                        <div><dt>Mission</dt><dd><?= $diV($c['mission_label']) ?></dd></div>
                        <div><dt>Acquisition</dt><dd><?= $diV($diDate((string) ($c['acquisition_at'] ?? ''))) ?></dd></div>
                        <div><dt>Source</dt><dd><?= $diV($c['source_label']) ?><?= !empty($c['source_reliability']) ? ' (' . $diH($c['source_reliability']) . ')' : '' ?></dd></div>
                    </dl>
                </section>
                <section class="di-panel">
                    <div class="di-panel__head"><h2>Suite d’instruction</h2><p>Passer du dossier d’intérêt à un dossier validé, ou ouvrir une investigation.</p></div>
                    <?php if ($constitutedCase !== null): ?>
                        <p><strong>Dossier constitué</strong> : <a href="<?= $diH(url('atak/sse/dossiers/' . (int) $constitutedCase['id'])) ?>"><?= $diH($constitutedCase['reference_code'] ?? '') ?> — <?= $diH($constitutedCase['title'] ?? '') ?></a></p>
                        <p class="di-sub">L’instruction se poursuit dans le dossier ; ce dossier d’intérêt en garde la genèse.</p>
                    <?php elseif ($canWrite): ?>
                        <div class="di-actions">
                            <form method="post" action="<?= $diH($base . '/constituer') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <button class="di-btn di-btn--primary" type="submit" <?= $cdBlocked('constitute') ? 'disabled' : '' ?>>Constituer le dossier</button>
                            </form>
                            <form method="post" action="<?= $diH($base . '/investigation') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <button class="di-btn" type="submit" <?= $cdBlocked('open_mesh') ? 'disabled' : '' ?>>Ouvrir une investigation</button>
                            </form>
                        </div>
                        <?php if ($cdBlocked('constitute')): ?><p class="di-sub di-mt-8"><?= $diH($cdHuman('constitute')) ?></p><?php endif; ?>
                        <p class="di-sub di-mt-8">« Constituer » reprend le motif, les observations et les faits déjà consignés ici dans un nouveau dossier.</p>
                    <?php else: ?>
                        <p class="di-muted-text">Aucun dossier n’a encore été constitué à partir de celui-ci.</p>
                    <?php endif; ?>
                </section>
                <section class="di-panel">
                    <div class="di-panel__head"><h2>Signature du signalement</h2><p>Qui a signé l’ouverture, et quand.</p></div>
                    <?php $signedBy = trim((string) ($c['signed_by_label'] ?? '')); $signedAt = (string) ($c['signed_at'] ?? ''); ?>
                    <?php if ($signedBy !== '' || $signedAt !== ''): ?>
                        <p><strong><?= $diH($signedBy !== '' ? $signedBy : ($c['origin_operator'] ?? 'Analyste')) ?></strong> — signé le <?= $diH($diDate($signedAt) ?: $diDate($c['created_at'])) ?>.</p>
                    <?php else: ?>
                        <p class="di-muted-text"><?php if ($c['status'] === 'brouillon'): ?>Brouillon : non signé.<?php else: ?>Signalement antérieur : signature non consignée.<?php endif; ?></p>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </section>

    <!-- Analyse -->
    <section class="di-pane" role="tabpanel" id="di-panel-analyse" aria-labelledby="di-tab-analyse" <?= $activeTab === 'analyse' ? '' : 'hidden' ?>>
        <section class="di-panel">
            <div class="di-panel__head"><h2>Hypothèses de travail</h2><p>Chaque hypothèse reste à confirmer ; la confiance affichée est indicative.</p></div>
            <div class="di-cards">
                <?php foreach ($hypotheses as $hyp): $conf = (int) ($hyp['confidence'] ?? 0); ?>
                    <article class="di-card">
                        <h3><?= $diH($hyp['code'] ?? 'H') ?> · <?= $conf ?> %</h3>
                        <div class="di-bar" aria-hidden="true"><i style="--w:<?= max(8, min(100, $conf)) ?>%"></i></div>
                        <p><?= $diH($hyp['text'] ?? '') ?></p>
                        <?php foreach (($hyp['pros'] ?? []) as $p): ?><p class="di-sub">+ <?= $diH($p) ?></p><?php endforeach; ?>
                        <?php foreach (($hyp['cons'] ?? []) as $p): ?><p class="di-sub">− <?= $diH($p) ?></p><?php endforeach; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="di-panel">
            <div class="di-panel__head"><h2>Raisonnement analytique</h2><p>Faits, hypothèses, contradictions, questions, collecte, risque et recommandations.</p></div>
            <div class="di-cards">
                <?php foreach ([
                    ['Faits établis', $c['analysis_facts'] ?: ($c['observed_elements'] ?? ''), 'Non renseigné'],
                    ['Hypothèses', $c['analysis_assumptions'] ?? '', 'Aucune hypothèse formelle'],
                    ['Contradictions', $c['analysis_contradictions'] ?? '', 'Aucune contradiction formelle'],
                    ['Questions restantes', $c['analysis_questions'] ?? '', '—'],
                    ['Besoins de collecte', $c['collection_needs'] ?? '', '—'],
                    ['Risque opérationnel', $c['operational_risk'] ?? '', '—'],
                ] as [$title, $text, $empty]): ?>
                    <article class="di-card"><h3><?= $diH($title) ?></h3><p><?= nl2br($diH(trim((string) $text) !== '' ? $text : $empty)) ?></p></article>
                <?php endforeach; ?>
                <article class="di-card di-card--wide"><h3>Recommandations de contrôle</h3><p><?= nl2br($diH(trim((string) ($c['recommendations'] ?? '')) !== '' ? $c['recommendations'] : 'Poursuivre la collecte et valider les rapprochements.')) ?></p></article>
            </div>
        </section>
        <section class="di-panel">
            <div class="di-panel__head"><h2>Rapprochements proposés par Cerbère</h2><p>Des propositions à trancher par un opérateur : chaque décision est consignée avec son auteur et sa justification.</p></div>
            <?php if ($cdBlocked('cross_decide') && $canWrite): ?><p class="di-sub"><?= $diH($cdHuman('cross_decide')) ?></p><?php endif; ?>
            <?php if ($proposals === []): ?>
                <p class="di-muted-text">Aucun rapprochement automatique sur ce dossier. Poursuivez la collecte, ou reliez les éléments à la main depuis les croisements.</p>
            <?php endif; ?>
            <?php foreach ($proposals as $p): $decision = is_array($p['decision'] ?? null) ? $p['decision'] : null; $pk = (int) $p['person_id'] . '-' . (int) $p['entry_id']; ?>
                <article class="di-cross">
                    <strong><?= $diH($p['person_name'] ?? 'Identité') ?> ↔ <?= $diH($p['entry_name'] ?? 'entrée surveillée') ?></strong>
                    <p><?= $diH($p['reason'] ?? '') ?></p>
                    <?php if ((int) ($p['score'] ?? 0) > 0): ?><p class="di-sub">Score indicatif : <?= (int) $p['score'] ?> %<?= $decision === null ? ' — à confirmer' : '' ?></p><?php endif; ?>
                    <?php if ($decision !== null): ?>
                        <p><strong><?= $diH($decision['decision_label'] ?? '') ?></strong> <span class="di-sub">par <?= $diH($decision['author_label'] ?? 'un analyste') ?></span></p>
                        <?php if (!empty($decision['note'])): ?><p class="di-sub"><?= $diH($decision['note']) ?></p><?php endif; ?>
                        <?php if ($canWrite): ?>
                            <form method="post" action="<?= $diH($base . '/croisements') ?>" class="di-cross__actions">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="person_id" value="<?= (int) $p['person_id'] ?>"><input type="hidden" name="entry_id" value="<?= (int) $p['entry_id'] ?>"><input type="hidden" name="decision" value="reouvrir">
                                <button class="di-btn" type="submit" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Revenir sur la décision</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($canWrite): ?>
                        <form method="post" action="<?= $diH($base . '/croisements') ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="person_id" value="<?= (int) $p['person_id'] ?>"><input type="hidden" name="entry_id" value="<?= (int) $p['entry_id'] ?>">
                            <input type="hidden" name="score" value="<?= (int) ($p['score'] ?? 0) ?>"><input type="hidden" name="reason" value="<?= $diH($p['reason'] ?? '') ?>">
                            <div class="di-field">
                                <label for="note-<?= $diH($pk) ?>">Sur quoi repose votre décision</label>
                                <input class="di-input" id="note-<?= $diH($pk) ?>" type="text" name="note" maxlength="255" placeholder="Élément qui tranche : photo, déclaration, biométrie…" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>
                            </div>
                            <div class="di-cross__actions di-mt-8">
                                <button class="di-btn di-btn--primary" type="submit" name="decision" value="confirme" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Confirmer</button>
                                <button class="di-btn" type="submit" name="decision" value="separe" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Maintenir séparé</button>
                                <button class="di-btn" type="submit" name="decision" value="complement" <?= $cdBlocked('cross_decide') ? 'disabled' : '' ?>>Analyse complémentaire</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="di-sub">Décision réservée aux opérateurs habilités.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </section>

    <!-- Éléments liés -->
    <section class="di-pane" role="tabpanel" id="di-panel-liens" aria-labelledby="di-tab-liens" <?= $activeTab === 'liens' ? '' : 'hidden' ?>>
        <section class="di-panel">
            <div class="di-panel__head">
                <div class="di-head di-mb-0">
                    <div class="di-head__text"><h2>Éléments liés</h2><p class="di-lead">Personnes, identités, sites, véhicules, transmissions, fiches et documents rattachés à ce dossier. Le titre et l’accès de chaque élément sont revérifiés pour vous.</p></div>
                    <?php if ($canWrite && !empty($caps['links'])): ?><div class="di-head__actions"><button type="button" class="di-btn di-btn--primary" data-di-open="di-link"><?= $diIcon('link') ?><span>Lier un élément</span></button></div><?php endif; ?>
                </div>
            </div>
            <?php if (empty($caps['links'])): ?>
                <p class="di-muted-text">Le rattachement d’éléments sera disponible après la mise à jour de la base.</p>
            <?php elseif ($links === []): ?>
                <div class="di-empty">
                    <h2>Aucun élément lié</h2>
                    <p>Reliez à ce dossier les personnes, sites, véhicules, transmissions ou fiches qui s’y rapportent : ils apparaîtront ici et dans l’historique.</p>
                    <?php if ($canWrite): ?><div class="di-empty__actions"><button type="button" class="di-btn di-btn--primary" data-di-open="di-link"><?= $diIcon('link') ?><span>Lier un élément</span></button></div><?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="di-links">
                    <?php foreach ($links as $l): ?>
                        <li>
                            <div class="di-links__main">
                                <span class="di-links__kind"><?= $diH($l['type_label']) ?></span>
                                <?php if (!empty($l['href'])): ?>
                                    <a class="di-links__label" href="<?= $diH($l['href']) ?>"><?= $diH($l['label']) ?></a>
                                <?php else: ?>
                                    <span class="di-links__label<?= !empty($l['masked']) || !empty($l['missing']) ? ' is-masked' : '' ?>"><?= $diH($l['label']) ?></span>
                                <?php endif; ?>
                                <?php if ($l['hint'] !== ''): ?><span class="di-sub"> · <?= $diH($l['hint']) ?></span><?php endif; ?>
                                <?php if (!empty($l['note'])): ?><p class="di-sub"><?= $diH($l['note']) ?></p><?php endif; ?>
                            </div>
                            <?php if ($canWrite): ?>
                                <button type="button" class="di-btn" data-di-unlink="<?= (int) $l['id'] ?>" data-di-unlink-name="<?= $diH($l['label']) ?>">Retirer</button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </section>

    <!-- Historique -->
    <section class="di-pane" role="tabpanel" id="di-panel-historique" aria-labelledby="di-tab-historique" <?= $activeTab === 'historique' ? '' : 'hidden' ?>>
        <section class="di-panel">
            <div class="di-panel__head"><h2>Historique des modifications</h2><p>Qui a changé quoi et quand, avec l’ancienne et la nouvelle valeur de chaque champ.</p></div>
            <?php if (empty($caps['history'])): ?>
                <p class="di-muted-text">L’historique détaillé sera disponible après la mise à jour de la base. Les changements d’état restent lisibles dans la discussion.</p>
            <?php elseif ($history === []): ?>
                <p class="di-muted-text">Aucun événement consigné pour le moment.</p>
            <?php else: ?>
                <ol class="di-timeline">
                    <?php foreach ($history as $h): ?>
                        <li class="is-<?= $diH($h['action']) ?>">
                            <div class="di-timeline__head"><strong><?= $diH($h['actor']) ?></strong><span><?= $diH($h['action_label']) ?><?= $h['field_label'] !== '' && $h['action'] !== 'update' && $h['action'] !== 'classification' ? ' · ' . $diH($h['field_label']) : '' ?></span><time class="di-sub" datetime="<?= $diH($h['at']) ?>"><?= $diH($diDate($h['at'])) ?><?= $h['version'] ? ' · v' . (int) $h['version'] : '' ?></time></div>
                            <?php if (in_array($h['action'], ['update', 'classification', 'status', 'archive', 'unarchive'], true)): ?>
                                <dl class="di-diff">
                                    <dt><?= $diH($h['field_label'] ?: 'Valeur') ?></dt>
                                    <dd><?php if ($h['old'] !== null): ?><span class="is-old"><?= $diH($h['old']) ?></span> → <?php endif; ?><span class="is-new"><?= $h['new'] !== null ? $diH($h['new']) : 'vide' ?></span></dd>
                                </dl>
                            <?php elseif (in_array($h['action'], ['link_add', 'link_remove'], true)): ?>
                                <p><?= $diH($h['new'] ?? $h['old'] ?? '') ?></p>
                            <?php elseif ($h['action'] === 'create' && $h['new']): ?>
                                <p><?= $diH($h['new']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($h['reason'])): ?><p class="di-reason">Motif : <?= $diH($h['reason']) ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ((int) ($history_total ?? 0) > count($history)): ?>
                    <p><a class="di-btn" href="<?= $diH($base . '?onglet=historique&historique=tout') ?>">Afficher la suite de l’historique (<?= (int) $history_total - count($history) ?>)</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </section>

    <!-- Pièces et documents -->
    <section class="di-pane" role="tabpanel" id="di-panel-pieces" aria-labelledby="di-tab-pieces" <?= $activeTab === 'pieces' ? '' : 'hidden' ?>>
        <section class="di-panel">
            <div class="di-panel__head"><h2>Pièces et documents</h2><p>Documents émis, fiches de renseignement, dossier constitué et comptes rendus JNET qui se rapportent à ce dossier.</p></div>
            <?php if ($docLinks === [] && $constitutedCase === null && $jnetCount < 1): ?>
                <div class="di-empty">
                    <h2>Aucune pièce rattachée</h2>
                    <p>Liez une fiche de renseignement, un document émis ou un dossier depuis l’onglet « Éléments liés » ; ils seront regroupés ici.</p>
                    <div class="di-empty__actions"><a class="di-btn" href="<?= $diH($base . '?onglet=liens') ?>">Aller aux éléments liés</a></div>
                </div>
            <?php else: ?>
                <ul class="di-links">
                    <?php foreach ($docLinks as $l): ?>
                        <li><div class="di-links__main"><span class="di-links__kind"><?= $diH($l['type_label']) ?></span><?= !empty($l['href']) ? '<a class="di-links__label" href="' . $diH($l['href']) . '">' . $diH($l['label']) . '</a>' : '<span class="di-links__label is-masked">' . $diH($l['label']) . '</span>' ?><?= $l['hint'] !== '' ? '<span class="di-sub"> · ' . $diH($l['hint']) . '</span>' : '' ?></div></li>
                    <?php endforeach; ?>
                    <?php if ($constitutedCase !== null): ?>
                        <li><div class="di-links__main"><span class="di-links__kind">Dossier constitué</span><a class="di-links__label" href="<?= $diH(url('atak/sse/dossiers/' . (int) $constitutedCase['id'])) ?>"><?= $diH($constitutedCase['reference_code'] ?? '') ?> — <?= $diH($constitutedCase['title'] ?? '') ?></a></div></li>
                    <?php endif; ?>
                    <?php if ($jnetCount > 0): ?>
                        <li><div class="di-links__main"><span class="di-links__kind">JNET</span><a class="di-links__label" href="<?= $diH(url('jnet/cibles/di-' . $cid)) ?>">Cité dans <?= $jnetCount ?> lien(s) JNET</a></div></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canExportPdf): ?><p class="di-mt-16"><a class="di-btn" href="<?= $diH($base . '/pdf') ?>"><?= $diIcon('pdf') ?><span>Exporter la fiche en PDF</span></a></p><?php endif; ?>
        </section>
    </section>

    <!-- Discussion -->
    <section class="di-pane" role="tabpanel" id="di-panel-discussion" aria-labelledby="di-tab-discussion" <?= $activeTab === 'discussion' ? '' : 'hidden' ?>>
        <section class="di-panel" id="journal">
            <div class="di-panel__head"><h2>Discussion et notes</h2><p>Faits nouveaux, décisions et pistes : visibles de tous ceux qui peuvent lire ce dossier. Le responsable et les abonnés sont prévenus.</p></div>
            <?php if ($canWrite): ?>
                <form method="post" action="<?= $diH($base . '/mise-a-jour') ?>" class="di-field<?= $diErr('body') !== '' ? ' is-invalid' : '' ?>" data-field="body" novalidate>
                    <?= \App\Core\Csrf::field() ?>
                    <label for="di-note">Nouvelle note</label>
                    <textarea class="di-textarea" id="di-note" name="body" rows="3" maxlength="4000" required placeholder="Faits nouveaux, décision, piste à suivre…"><?= $diH($diOld('body', '')) ?></textarea>
                    <?= $diFieldError('body') ?>
                    <div><button type="submit" class="di-btn di-btn--primary">Ajouter la note</button></div>
                </form>
            <?php endif; ?>
            <?php if ($updates === []): ?>
                <p class="di-muted-text di-mt-16 di-hollow">Aucune note pour le moment.</p>
            <?php else: ?>
                <ol class="di-notes di-mt-20">
                    <?php foreach ($updates as $u): $author = (string) ($u['author_label'] ?? 'Analyste'); ?>
                        <li class="di-note">
                            <span class="di-note__avatar" aria-hidden="true"><?= $diH(mb_strtoupper(mb_substr(trim($author) !== '' ? trim($author) : 'A', 0, 1))) ?></span>
                            <div class="di-note__body">
                                <div class="di-note__head"><strong><?= $diH($author) ?></strong><time class="di-sub" datetime="<?= $diH($u['created_at'] ?? '') ?>"><?= $diH($diDate((string) ($u['created_at'] ?? ''))) ?></time></div>
                                <p><?= nl2br($diH($u['body'] ?? '')) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </section>

    <!-- Accès -->
    <section class="di-pane" role="tabpanel" id="di-panel-acces" aria-labelledby="di-tab-acces" <?= $activeTab === 'acces' ? '' : 'hidden' ?>>
        <section class="di-panel">
            <div class="di-panel__head"><h2>Qui peut ouvrir ce dossier</h2><p>La classification fixe le niveau d’habilitation requis ; la diffusion peut le restreindre à des personnes nommées.</p></div>
            <dl class="di-facts di-facts--tiles">
                <div><dt>Classification</dt><dd><?= $diClass($c) ?></dd></div>
                <div><dt>Niveau requis</dt><dd><?= $diH(\App\Services\Sse\SseClearanceService::whoCanOpen((string) $c['classification'])) ?></dd></div>
                <div><dt>Abonnés</dt><dd><?= (int) ($watchers_count ?? 0) ?> personne(s)</dd></div>
                <div><dt>Diffusion</dt><dd><?php if (count($acl['destinataires'] ?? []) > 0): ?><?= count($acl['destinataires']) ?> destinataire(s) nommé(s)<?php else: ?>Cellule SSE habilitée<?php endif; ?></dd></div>
            </dl>
        </section>
        <?php if ($actor->canManage && !$isTrashed): ?>
            <section class="di-panel" id="diffusion">
                <div class="di-panel__head"><h2>Diffusion et accès nominatifs</h2><p>S’il y a au moins un destinataire, seuls ces membres et le créateur peuvent ouvrir le dossier. Un accès interdit prime sur tout.</p></div>
                <form method="post" action="<?= $diH($base . '/diffusion') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="di-acl">
                        <fieldset>
                            <legend>Destinataires</legend>
                            <div class="di-acl__list">
                                <?php if ($tenantMembers === []): ?><p class="di-muted-text">Aucun membre actif à proposer.</p><?php endif; ?>
                                <?php foreach ($tenantMembers as $m): ?>
                                    <label class="di-check"><input type="checkbox" name="destinataires[]" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $destIds, true) ? 'checked' : '' ?>><span><?= $diH($memberLabel($m)) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Accès interdit</legend>
                            <div class="di-acl__list">
                                <?php foreach ($tenantMembers as $m): ?>
                                    <label class="di-check"><input type="checkbox" name="interdits[]" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $denyIds, true) ? 'checked' : '' ?>><span><?= $diH($memberLabel($m)) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    </div>
                    <p class="di-mt-16"><button type="submit" class="di-btn di-btn--primary">Enregistrer la diffusion</button></p>
                </form>
            </section>
        <?php elseif (($acl['destinataires'] ?? []) !== [] || ($acl['interdits'] ?? []) !== []): ?>
            <section class="di-panel">
                <div class="di-panel__head"><h2>Diffusion</h2><p>Les personnes nommées pour ce dossier.</p></div>
                <?php if (($acl['destinataires'] ?? []) !== []): ?><p><strong>Destinataires</strong> : <?= $diH(implode(', ', array_map(static fn (array $r): string => (string) ($r['member_label'] ?? 'Membre'), $acl['destinataires']))) ?></p><?php endif; ?>
                <?php if (($acl['interdits'] ?? []) !== []): ?><p><strong>Accès interdit</strong> : <?= $diH(implode(', ', array_map(static fn (array $r): string => (string) ($r['member_label'] ?? 'Membre'), $acl['interdits']))) ?></p><?php endif; ?>
            </section>
        <?php endif; ?>
    </section>

    <?php if ($canWrite): ?>
        <?php require __DIR__ . '/interest/_dialog_edit.php'; ?>
        <?php if ($next_statuses !== []): require __DIR__ . '/interest/_dialog_status.php'; endif; ?>
        <?php if (!empty($caps['links'])): require __DIR__ . '/interest/_dialog_links.php'; endif; ?>
    <?php endif; ?>
    <?php if (!empty($caps['columns']) && ($actor->canDelete || $actor->canManage)): require __DIR__ . '/interest/_dialog_delete.php'; endif; ?>
    <div hidden data-di-labels>
        <span data-k="searching">Recherche…</span><span data-k="noresult">Aucun résultat</span><span data-k="required">Ce champ est obligatoire.</span>
        <span data-k="reasonreq">obligatoire</span><span data-k="reasonopt">facultatif</span><span data-k="error">La recherche n’a pas abouti. Réessayez.</span>
        <span data-k="short">Tapez 2 lettres au moins.</span><span data-k="remove">Retirer</span>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
