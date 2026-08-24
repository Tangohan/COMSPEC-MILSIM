<?php
declare(strict_types=1);

$report = is_array($aarReport ?? null) ? $aarReport : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$id = (int) ($report['id'] ?? 0);
$ref = (string) ($report['reference_code'] ?? ('AAR-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)));
$operation = trim((string) ($report['operation_label'] ?? $report['mission_title'] ?? ''));
$title = trim((string) ($report['title'] ?? 'Compte rendu post-op'));
$author = trim((string) ($report['author_name'] ?? '—'));
$validator = trim((string) ($report['validator_name'] ?? ''));
$statusLabel = (string) ($report['status_label'] ?? 'En attente');
$summaryHeading = trim((string) ($report['summary_heading'] ?? ''));
$summaryText = trim((string) ($report['summary_text'] ?? ''));
$lessons = trim((string) ($report['lessons_learned'] ?? ''));
$lessonsCtx = trim((string) ($report['lessons_context'] ?? ''));
$conclusion = trim((string) ($report['conclusion_text'] ?? ''));
$strengths = is_array($report['strengths'] ?? null) ? $report['strengths'] : [];
$weaknesses = is_array($report['weaknesses'] ?? null) ? $report['weaknesses'] : [];
$openActions = is_array($report['open_actions'] ?? null) ? $report['open_actions'] : [];
$closedActions = is_array($report['closed_actions'] ?? null) ? $report['closed_actions'] : [];
$pageCount = (int) ($report['page_count'] ?? 1);
$pdfUrl = isset($aarPdfUrl) && is_string($aarPdfUrl) && $aarPdfUrl !== '' ? $aarPdfUrl : null;

$fmtDate = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : $raw;
};

$reportedAt = $fmtDate(isset($report['reported_at']) ? (string) $report['reported_at'] : null);
$validatedAt = $fmtDate(isset($report['validated_at']) ? (string) $report['validated_at'] : null);

$allActions = [];
foreach ($closedActions as $action) {
    if (!is_array($action)) {
        continue;
    }
    $allActions[] = array_merge($action, ['status' => 'closed']);
}
foreach ($openActions as $action) {
    if (!is_array($action)) {
        continue;
    }
    $allActions[] = array_merge($action, ['status' => 'open']);
}

$headTitle = $operation !== '' ? $operation : $title;
?>

<div class="ath-aar-read ath-rise" x-data="{ tab: 'sommaire' }">
    <article class="ath-aar-doc" id="ath-aar-document">
        <header class="ath-aar-doc__head">
            <p class="ath-aar-doc__kicker"><?= $h($ref) ?> · Lecture</p>
            <div class="ath-aar-doc__title-row">
                <h1 class="ath-aar-doc__title">Compte rendu — <?= $h($headTitle) ?></h1>
                <span class="ath-aar-doc__status ath-tag <?= \App\Support\AthUi::tagClass($statusLabel) ?>"><?= $h($statusLabel) ?></span>
            </div>
            <p class="ath-aar-doc__lead">Rapport post-opération<?= $statusLabel === 'Validé' ? ' validé par l’état-major' : '' ?>. Les actions décidées sont suivies jusqu’à leur clôture.</p>
        </header>

        <div class="ath-aar-doc__body">
            <div class="ath-aar-meta">
                <strong>Opération <?= $h($headTitle) ?> — retour d’expérience</strong><br>
                <?= $h($author) ?><?= $operation !== '' ? ' · ' . $h($operation) : '' ?>
                · Déposé le <?= $h($reportedAt) ?>
                <?php if ($validator !== '' && $validatedAt !== '—'): ?>
                    · Validé par <?= $h($validator) ?> le <?= $h($validatedAt) ?>
                <?php endif; ?>
                · <?= (int) $pageCount ?> page<?= $pageCount > 1 ? 's' : '' ?>
            </div>

            <?php
            $customRows = is_array($report['custom_rows'] ?? null) ? $report['custom_rows'] : [];
            $isCustom = !empty($report['is_custom']);
            ?>
            <?php if ($isCustom && $customRows !== []): ?>
            <section class="ath-aar-section" id="section-debrief">
                <div class="ath-aar-section__num">Debriefing</div>
                <h2 class="ath-aar-section__title">Réponses au questionnaire</h2>
                <dl class="ath-aar-qa">
                    <?php foreach ($customRows as $row): ?>
                    <div class="ath-aar-qa__row">
                        <dt><?= $h((string) ($row['label'] ?? '')) ?></dt>
                        <dd><?= $h((string) (($row['empty'] ?? false) ? 'Non renseigné' : ($row['display'] ?? ''))) ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
            </section>
            <?php endif; ?>

            <?php if ($summaryText !== '' || $summaryHeading !== ''): ?>
            <section class="ath-aar-section" id="section-synthese">
                <div class="ath-aar-section__num">1 · Synthèse</div>
                <?php if ($summaryHeading !== ''): ?>
                <h2 class="ath-aar-section__title"><?= $h($summaryHeading) ?></h2>
                <?php else: ?>
                <h2 class="ath-aar-section__title">Synthèse opérationnelle</h2>
                <?php endif; ?>
                <?php if ($summaryText !== ''): ?>
                <p class="ath-aar-section__text"><?= nl2br($h($summaryText)) ?></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($strengths !== []): ?>
            <section class="ath-aar-section" id="section-forces">
                <div class="ath-aar-section__num">2 · Points forts</div>
                <h2 class="ath-aar-section__title">Ce qui a fonctionné</h2>
                <div class="ath-aar-points">
                    <?php foreach ($strengths as $point): ?>
                    <div class="ath-aar-point ath-aar-point--ok"><?= $h((string) $point) ?></div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($weaknesses !== []): ?>
            <section class="ath-aar-section" id="section-faiblesses">
                <div class="ath-aar-section__num">3 · Points faibles</div>
                <h2 class="ath-aar-section__title">Ce qui doit progresser</h2>
                <div class="ath-aar-points">
                    <?php foreach ($weaknesses as $point): ?>
                    <div class="ath-aar-point ath-aar-point--warn"><?= $h((string) $point) ?></div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($lessons !== ''): ?>
            <section class="ath-aar-section" id="section-enseignements">
                <div class="ath-aar-section__num">4 · Enseignements</div>
                <h2 class="ath-aar-section__title"><?= $lessonsCtx !== '' ? 'À retenir pour ' . $h($lessonsCtx) : 'À retenir' ?></h2>
                <p class="ath-aar-section__text"><?= nl2br($h($lessons)) ?></p>
            </section>
            <?php endif; ?>

            <?php if ($conclusion !== ''): ?>
            <section class="ath-aar-section" id="section-conclusion">
                <div class="ath-aar-section__num">5 · Conclusion</div>
                <h2 class="ath-aar-section__title">Avis du commandement</h2>
                <p class="ath-aar-section__text"><?= nl2br($h($conclusion)) ?></p>
            </section>
            <?php endif; ?>

            <?php if ($summaryText === '' && $summaryHeading === '' && $strengths === [] && $weaknesses === [] && $lessons === '' && $conclusion === '' && !($isCustom && $customRows !== [])): ?>
            <section class="ath-aar-section">
                <p class="ath-aar-section__text">Ce compte rendu n’a pas encore de contenu détaillé.</p>
            </section>
            <?php endif; ?>
        </div>
    </article>

    <aside class="ath-aar-panel">
        <div class="ath-aar-tabs" role="tablist">
            <button type="button" class="ath-aar-tab" :class="{ 'is-active': tab === 'sommaire' }" @click="tab = 'sommaire'" role="tab">Sommaire</button>
            <button type="button" class="ath-aar-tab" :class="{ 'is-active': tab === 'actions' }" @click="tab = 'actions'" role="tab">Actions</button>
            <button type="button" class="ath-aar-tab" :class="{ 'is-active': tab === 'historique' }" @click="tab = 'historique'" role="tab">Historique</button>
        </div>

        <div class="ath-aar-panel__body" x-show="tab === 'sommaire'" x-cloak>
            <div class="ath-aar-panel__label">Sommaire</div>
            <nav class="ath-aar-toc" aria-label="Sommaire du compte rendu">
                <?php if ($isCustom && $customRows !== []): ?>
                <a href="#section-debrief">Debriefing</a>
                <?php endif; ?>
                <?php if ($summaryText !== '' || $summaryHeading !== ''): ?>
                <a href="#section-synthese">Synthèse</a>
                <?php endif; ?>
                <?php if ($strengths !== []): ?>
                <a href="#section-forces">Points forts</a>
                <?php endif; ?>
                <?php if ($weaknesses !== []): ?>
                <a href="#section-faiblesses">Points faibles</a>
                <?php endif; ?>
                <?php if ($lessons !== ''): ?>
                <a href="#section-enseignements">Enseignements</a>
                <?php endif; ?>
                <?php if ($conclusion !== ''): ?>
                <a href="#section-conclusion">Conclusion</a>
                <?php endif; ?>
            </nav>
            <?php if ($pdfUrl !== null): ?>
            <a href="<?= $h($pdfUrl) ?>" class="ath-btn ath-btn--solid" style="width:100%;margin-top:16px;box-sizing:border-box;" target="_blank" rel="noopener">Exporter en PDF</a>
            <?php endif; ?>
            <a href="<?= $h(url('back-office/atak/comptes-rendus/' . $id . '/edit')) ?>" class="ath-btn" style="width:100%;margin-top:8px;box-sizing:border-box;">Modifier le compte rendu</a>
            <a href="<?= $h(url('back-office/atak/comptes-rendus')) ?>" class="ath-btn" style="width:100%;margin-top:8px;box-sizing:border-box;">← Retour à la liste</a>
        </div>

        <div class="ath-aar-panel__body" x-show="tab === 'actions'" x-cloak>
            <div class="ath-aar-panel__label">Actions décidées</div>
            <?php if ($allActions === []): ?>
            <p class="ath-aar-section__text" style="margin-top:12px;">Aucune action enregistrée pour ce compte rendu.</p>
            <?php else: ?>
            <div class="ath-aar-actions">
                <?php foreach ($allActions as $action): ?>
                    <?php
                    $isClosed = ($action['status'] ?? '') === 'closed';
                    $owner = trim((string) ($action['owner'] ?? ''));
                    $due = isset($action['due_at']) ? $fmtDate((string) $action['due_at']) : '';
                    $meta = array_filter([$owner !== '' ? $owner : null, $due !== '—' ? $due : null]);
                    ?>
                <div class="ath-aar-action">
                    <div>
                        <div class="ath-aar-action__title"><?= $h((string) ($action['label'] ?? '')) ?></div>
                        <?php if ($meta !== []): ?>
                        <div class="ath-aar-action__meta"><?= $h(implode(' · ', $meta)) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="ath-aar-action__status <?= $isClosed ? 'ath-aar-action__status--closed' : 'ath-aar-action__status--open' ?>">
                        <?= $isClosed ? 'Clos' : 'En cours' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="ath-aar-panel__body" x-show="tab === 'historique'" x-cloak>
            <div class="ath-aar-panel__label">Historique</div>
            <div class="ath-aar-history">
                <div class="ath-aar-history__row">
                    <span class="ath-aar-history__label">Référence</span>
                    <span class="ath-aar-history__val"><?= $h($ref) ?></span>
                </div>
                <div class="ath-aar-history__row">
                    <span class="ath-aar-history__label">Statut</span>
                    <span class="ath-aar-history__val"><?= $h($statusLabel) ?></span>
                </div>
                <div class="ath-aar-history__row">
                    <span class="ath-aar-history__label">Dépôt</span>
                    <span class="ath-aar-history__val"><?= $h($reportedAt) ?></span>
                </div>
                <?php if ($validatedAt !== '—'): ?>
                <div class="ath-aar-history__row">
                    <span class="ath-aar-history__label">Validation</span>
                    <span class="ath-aar-history__val"><?= $h($validatedAt) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($validator !== ''): ?>
                <div class="ath-aar-history__row">
                    <span class="ath-aar-history__label">Relecteur</span>
                    <span class="ath-aar-history__val"><?= $h($validator) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>
