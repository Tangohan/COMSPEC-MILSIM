<?php
/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $assessments */
/** @var list<array<string,mixed>> $intelGaps */
/** @var list<array<string,mixed>> $analyticalDecisions */
/** @var list<array<string,mixed>> $caseLinks */
/** @var list<array{id:int,reference_code:string,title:string}> $linkableCases */
/** @var list<array<string,mixed>> $contextualSuggestions */
/** @var list<array<string,mixed>> $gapPresets */
/** @var array<string,array<string|int,string>> $analyticalCatalog */
/** @var string $executiveBrief */
/** @var bool $canManage */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$caseUrl = url('atak/sse/dossiers/' . (int) ($case['id'] ?? 0));
$cat = $analyticalCatalog ?? [];
$assessments = is_array($assessments ?? null) ? $assessments : [];
$intelGaps = is_array($intelGaps ?? null) ? $intelGaps : [];
$analyticalDecisions = is_array($analyticalDecisions ?? null) ? $analyticalDecisions : [];
$caseLinks = is_array($caseLinks ?? null) ? $caseLinks : [];
$linkableCases = is_array($linkableCases ?? null) ? $linkableCases : [];
$contextualSuggestions = is_array($contextualSuggestions ?? null) ? $contextualSuggestions : [];
$gapPresets = is_array($gapPresets ?? null) ? $gapPresets : [];
$executiveBrief = (string) ($executiveBrief ?? '');
?>

<section id="synthese-exec" class="panel sse-ana-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.08</span> Synthèse exécutive</div>
        <div class="panel-header__end">
            <div class="panel-meta">Générée depuis les données structurées</div>
            <?php $sectionKey = '01.08'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <pre class="sse-ana-brief"><?= $h($executiveBrief) ?></pre>
    </div>
</section>

<section id="suggestions" class="panel sse-ana-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.09</span> Mentions proposées</div>
        <div class="panel-header__end">
            <div class="panel-meta">Générateur contextuel</div>
            <?php $sectionKey = '01.09'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($contextualSuggestions === []): ?>
            <p class="sse-note muted">Aucune alerte contextuelle pour l’instant. Les suggestions apparaissent selon l’état du dossier.</p>
        <?php else: ?>
            <ul class="sse-ana-suggest">
                <?php foreach ($contextualSuggestions as $sug): ?>
                    <li class="sse-ana-suggest__item sse-ana-suggest__item--<?= $h($sug['urgency'] ?? 'low') ?>">
                        <strong><?= $h($sug['title'] ?? '') ?></strong>
                        <span class="muted"><?= $h($sug['reason'] ?? '') ?></span>
                        <?php if (!empty($sug['snippet'])): ?>
                            <em><?= $h($sug['snippet']) ?></em>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section id="analyse" class="panel sse-ana-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.10</span> Appréciation analytique</div>
        <div class="panel-header__end">
            <div class="panel-meta">FAIT → SOURCE → RECOUPEMENT → APPRÉCIATION → CONFIANCE → HYPOTHÈSE</div>
            <?php $sectionKey = '01.10'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($assessments === []): ?>
            <p class="sse-note muted">Aucune appréciation structurée. Consignez le raisonnement plutôt qu’une conclusion seule.</p>
        <?php else: ?>
            <div class="sse-ana-cards">
                <?php foreach ($assessments as $a): ?>
                    <?php if (($a['status'] ?? '') === 'superseded') continue; ?>
                    <article class="sse-ana-card">
                        <header>
                            <span class="sse-ana-card__banner"><?= $h($a['banner'] ?? '') ?></span>
                            <span class="sse-ana-card__hyp"><?= $h($a['hypothesis_code'] ?? 'H1') ?></span>
                        </header>
                        <?php if (!empty($a['subject_label'])): ?>
                            <p class="sse-ana-card__subj"><?= $h($a['subject_label']) ?></p>
                        <?php endif; ?>
                        <dl class="sse-ana-chain">
                            <div><dt>Fait</dt><dd><?= nl2br($h($a['fact_text'] ?? '')) ?></dd></div>
                            <div><dt>Source</dt><dd><?= $h($a['source_origin_label'] ?? '') ?> · <?= $h($a['rating_label'] ?? '') ?></dd></div>
                            <?php if (!empty($a['corroboration_text'])): ?>
                                <div><dt>Recoupement</dt><dd><?= nl2br($h($a['corroboration_text'])) ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Appréciation</dt><dd><?= nl2br($h($a['assessment_text'] ?? '')) ?></dd></div>
                            <div><dt>Confiance</dt><dd><?= $h($a['confidence_label'] ?? '') ?> — <?= nl2br($h($a['confidence_justification'] ?? '')) ?></dd></div>
                            <?php if (!empty($a['hypothesis_text'])): ?>
                                <div><dt>Hypothèse</dt><dd><?= nl2br($h($a['hypothesis_text'])) ?></dd></div>
                            <?php endif; ?>
                        </dl>
                        <footer class="sse-ana-card__foot">
                            <span><?= $h($a['temporality_label'] ?? '') ?><?php if (!empty($a['temporality_date'])): ?> <?= $h(date('d/m/Y', strtotime((string) $a['temporality_date']) ?: time())) ?><?php endif; ?></span>
                            <?php if (!empty($a['urgency_label'])): ?><span class="sse-ana-tag"><?= $h($a['urgency_label']) ?></span><?php endif; ?>
                            <?php if (!empty($a['divergence_label'])): ?><span class="sse-ana-tag sse-ana-tag--warn"><?= $h($a['divergence_label']) ?></span><?php endif; ?>
                            <span class="muted">v<?= (int) ($a['version'] ?? 1) ?> · <?= $h($a['author_label'] ?? '') ?></span>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <details class="sse-ana-form">
                <summary>Nouvelle appréciation</summary>
                <form method="post" action="<?= $h($caseUrl . '/appreciations') ?>" class="sse-ana-grid">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Objet <input name="subject_label" type="text" maxlength="200" placeholder="Ex. Fréquentation du site"></label>
                    <label class="sse-ana-span2">Fait constaté <textarea name="fact_text" required rows="3" placeholder="Ce qui est établi, sans interprétation"></textarea></label>
                    <label>Origine du renseignement
                        <select name="source_origin">
                            <?php foreach (($cat['origins'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Fiabilité de la source
                        <select name="source_reliability">
                            <?php foreach (($cat['reliability'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>" <?= $k === 'F' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Crédibilité de l’information
                        <select name="info_credibility">
                            <?php foreach (($cat['credibility'] ?? []) as $k => $lab): ?>
                                <option value="<?= (int) $k ?>" <?= (int) $k === 6 ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="sse-ana-span2">Recoupement <textarea name="corroboration_text" rows="2" placeholder="Éléments indépendants, ou « source unique »"></textarea></label>
                    <label class="sse-ana-span2">Appréciation <textarea name="assessment_text" required rows="3" placeholder="Lecture analytique du fait"></textarea></label>
                    <label>Niveau de confiance
                        <select name="confidence" required>
                            <?php foreach (($cat['confidence'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>" <?= $k === 'modere' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="sse-ana-span2">Justification de la confiance <textarea name="confidence_justification" required rows="2" placeholder="Obligatoire"></textarea></label>
                    <label>Hypothèse
                        <select name="hypothesis_code">
                            <?php foreach (($cat['hypotheses'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="sse-ana-span2">Énoncé de l’hypothèse <textarea name="hypothesis_text" rows="2"></textarea></label>
                    <label>Temporalité
                        <select name="temporality">
                            <?php foreach (($cat['temporality'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Date associée <input name="temporality_date" type="date"></label>
                    <label>Urgence
                        <select name="urgency">
                            <?php foreach (($cat['urgency'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Divergence
                        <select name="divergence_code">
                            <?php foreach (($cat['divergences'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Relecteur <input name="reviewer_label" type="text" maxlength="160"></label>
                    <label>Validateur <input name="validator_label" type="text" maxlength="160"></label>
                    <div class="sse-ana-span2"><button class="btn" type="submit">Consigner l’appréciation</button></div>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>

<section id="lacunes" class="panel sse-ana-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.11</span> Lacunes et besoins</div>
        <div class="panel-header__end">
            <div class="panel-meta">Ce qui reste à déterminer</div>
            <?php $sectionKey = '01.11'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($intelGaps === []): ?>
            <p class="sse-note muted">Aucune lacune ni besoin enregistré.</p>
        <?php else: ?>
            <ul class="sse-ana-gaps">
                <?php foreach ($intelGaps as $g): ?>
                    <li class="sse-ana-gap sse-ana-gap--<?= $h($g['priority'] ?? 'normale') ?>">
                        <strong><?= $h($g['banner'] ?? $g['title'] ?? '') ?></strong>
                        <p><?= nl2br($h($g['body'] ?? '')) ?></p>
                        <?php if (!empty($g['confirmation_criterion'])): ?>
                            <p class="muted">Critère : <?= $h($g['confirmation_criterion']) ?></p>
                        <?php endif; ?>
                        <footer>
                            <span><?= $h($g['status_label'] ?? '') ?></span>
                            <?php if (!empty($g['assignee_label'])): ?><span><?= $h($g['assignee_label']) ?></span><?php endif; ?>
                            <?php if (!empty($g['due_at'])): ?><span>Échéance <?= $h(date('d/m/Y', strtotime((string) $g['due_at']) ?: time())) ?></span><?php endif; ?>
                            <?php if ($canManage && in_array(($g['status'] ?? ''), ['ouvert', 'en_cours'], true)): ?>
                                <form method="post" action="<?= $h($caseUrl . '/lacunes/' . (int) $g['id'] . '/statut') ?>" class="sse-ana-inline">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="status" value="satisfait">
                                    <button class="btn btn--ghost btn--sm" type="submit">Marquer satisfait</button>
                                </form>
                            <?php endif; ?>
                        </footer>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <?php if ($gapPresets !== []): ?>
                <p class="sse-ana-presets-label">Mentions types</p>
                <div class="sse-ana-presets">
                    <?php foreach ($gapPresets as $p): ?>
                        <button type="button" class="btn btn--ghost btn--sm" data-gap-preset="<?= $h($p['content'] ?? '') ?>" data-gap-title="<?= $h($p['label'] ?? '') ?>"><?= $h($p['label'] ?? '') ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <details class="sse-ana-form">
                <summary>Ajouter une lacune ou un besoin</summary>
                <form method="post" action="<?= $h($caseUrl . '/lacunes') ?>" class="sse-ana-grid" id="sse-gap-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Type
                        <select name="kind" id="sse-gap-kind">
                            <?php foreach (($cat['gapKinds'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Priorité
                        <select name="priority">
                            <?php foreach (($cat['gapPriorities'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>" <?= $k === 'normale' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="sse-ana-span2">Titre <input name="title" id="sse-gap-title" type="text" required maxlength="220"></label>
                    <label class="sse-ana-span2">Détail <textarea name="body" id="sse-gap-body" required rows="3"></textarea></label>
                    <label class="sse-ana-span2">Critère de confirmation <textarea name="confirmation_criterion" rows="2"></textarea></label>
                    <label>Hypothèse liée
                        <select name="linked_hypothesis">
                            <option value="">—</option>
                            <?php foreach (($cat['hypotheses'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Responsable <input name="assignee_label" type="text" maxlength="160"></label>
                    <label>Échéance <input name="due_at" type="date"></label>
                    <div class="sse-ana-span2"><button class="btn" type="submit">Enregistrer</button></div>
                </form>
            </details>
            <script>
            (function () {
                document.querySelectorAll('[data-gap-preset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var title = document.getElementById('sse-gap-title');
                        var body = document.getElementById('sse-gap-body');
                        var kind = document.getElementById('sse-gap-kind');
                        if (title) title.value = btn.getAttribute('data-gap-title') || '';
                        if (body) body.value = btn.getAttribute('data-gap-preset') || '';
                        if (kind) {
                            var t = (btn.getAttribute('data-gap-title') || '').toLowerCase();
                            kind.value = t.indexOf('besoin') >= 0 ? 'besoin' : (t.indexOf('crit') >= 0 ? 'critere' : 'lacune');
                        }
                        var form = document.getElementById('sse-gap-form');
                        if (form && form.closest('details')) form.closest('details').open = true;
                    });
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</section>

<section id="decisions" class="panel sse-ana-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.12</span> Registre des décisions</div>
        <div class="panel-header__end">
            <div class="panel-meta">Les anciennes conclusions ne sont jamais écrasées</div>
            <?php $sectionKey = '01.12'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($analyticalDecisions === []): ?>
            <p class="sse-note muted">Aucune décision analytique consignée.</p>
        <?php else: ?>
            <div class="sse-ana-table-wrap">
                <table class="sse-ana-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Décision</th>
                            <th>Avant</th>
                            <th>Après</th>
                            <th>Motif</th>
                            <th>Analyste</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analyticalDecisions as $d): ?>
                            <tr>
                                <td><?= $h($d['decided_at_label'] ?? '') ?></td>
                                <td><?= $h($d['domain_label'] ?? '') ?><?php if (!empty($d['subject_label'])): ?><br><em><?= $h($d['subject_label']) ?></em><?php endif; ?></td>
                                <td><?= $h($d['value_before'] ?? '—') ?></td>
                                <td><strong><?= $h($d['value_after'] ?? '') ?></strong></td>
                                <td><?= $h($d['reason'] ?? '') ?></td>
                                <td><?= $h($d['author_label'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <details class="sse-ana-form">
                <summary>Consigne une décision</summary>
                <form method="post" action="<?= $h($caseUrl . '/decisions') ?>" class="sse-ana-grid">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Domaine
                        <select name="decision_domain">
                            <?php foreach (($cat['decisionDomains'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Objet <input name="subject_label" type="text" maxlength="220" placeholder="Ex. Rattachement au site"></label>
                    <label>Avant <input name="value_before" type="text" maxlength="160" placeholder="Ex. Supposé"></label>
                    <label>Après <input name="value_after" type="text" required maxlength="160" placeholder="Ex. Probable"></label>
                    <label class="sse-ana-span2">Motif <textarea name="reason" required rows="2"></textarea></label>
                    <div class="sse-ana-span2"><button class="btn" type="submit">Porter au registre</button></div>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>

<section id="relations" class="panel sse-ana-panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01.13</span> Relations entre dossiers</div>
        <div class="panel-header__end">
            <div class="panel-meta">Parent, dérivé, connexe, source, doublon — avec conservation des références</div>
            <?php $sectionKey = '01.13'; require __DIR__ . '/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($caseLinks === []): ?>
            <p class="sse-note muted">Aucune relation avec un autre dossier.</p>
        <?php else: ?>
            <ul class="sse-ana-links">
                <?php foreach ($caseLinks as $l): ?>
                    <li>
                        <span class="sse-ana-tag"><?= $h($l['relation_label'] ?? '') ?></span>
                        <a href="<?= $h(url('atak/sse/dossiers/' . (int) ($l['related_case_id'] ?? 0))) ?>">
                            <?= $h($l['related_ref'] ?? '') ?> — <?= $h($l['related_title'] ?? '') ?>
                        </a>
                        <?php if (!empty($l['former_reference'])): ?>
                            <em class="muted">réf. antérieure <?= $h($l['former_reference']) ?></em>
                        <?php endif; ?>
                        <?php if (!empty($l['note'])): ?><span><?= $h($l['note']) ?></span><?php endif; ?>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= $h($caseUrl . '/relations-dossiers/' . (int) $l['id'] . '/supprimer') ?>" class="sse-ana-inline" onsubmit="return confirm('Retirer cette relation ?');">
                                <?= \App\Core\Csrf::field() ?>
                                <button class="btn btn--ghost btn--sm" type="submit">Retirer</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <details class="sse-ana-form">
                <summary>Lier un autre dossier</summary>
                <form method="post" action="<?= $h($caseUrl . '/relations-dossiers') ?>" class="sse-ana-grid">
                    <?= \App\Core\Csrf::field() ?>
                    <label>Dossier lié
                        <select name="related_case_id" required>
                            <option value="">Choisir un dossier…</option>
                            <?php foreach ($linkableCases as $lc): ?>
                                <option value="<?= (int) ($lc['id'] ?? 0) ?>">
                                    <?= $h(trim(($lc['reference_code'] ?? '') . ' — ' . ($lc['title'] ?? ''), ' —')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Nature du lien
                        <select name="relation_type">
                            <?php foreach (($cat['relationTypes'] ?? []) as $k => $lab): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Ancienne référence conservée <input name="former_reference" type="text" maxlength="64" placeholder="Après fusion / dissociation"></label>
                    <label class="sse-ana-span2">Note <input name="note" type="text" maxlength="500"></label>
                    <?php if ($linkableCases === []): ?>
                        <p class="sse-ana-span2 muted">Aucun autre dossier disponible dans votre périmètre pour établir un lien.</p>
                    <?php else: ?>
                        <div class="sse-ana-span2"><button class="btn" type="submit">Enregistrer la relation</button></div>
                    <?php endif; ?>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>
