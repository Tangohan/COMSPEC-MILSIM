<?php
/**
 * Ma situation › Mon avancement — parcours de grade, conditions, demandes, avis, historique.
 * Formulaires et champs inchangés (volontaire, demande, affectation) : seule la présentation change.
 */
require dirname(__DIR__) . '/advancement/_helpers.php';
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$panel = is_array($panel ?? null) ? $panel : [];
$current = is_array($panel['current'] ?? null) ? $panel['current'] : null;
$history = is_array($panel['history'] ?? null) ? $panel['history'] : [];
$offer = is_array($panel['offer'] ?? null) ? $panel['offer'] : null;
$next = is_array($panel['next'] ?? null) ? $panel['next'] : null;
$opinions = is_array($panel['opinions'] ?? null) ? $panel['opinions'] : [];
$mobilityRequests = is_array($mobilityRequests ?? null) ? $mobilityRequests : [];
$units = is_array($assignmentUnits ?? null) ? $assignmentUnits : [];
$mobilityReady = !empty($mobilityReady);
$typeLabels = is_array($mobilityTypeLabels ?? null) ? $mobilityTypeLabels : [];
$statusLabels = is_array($mobilityStatusLabels ?? null) ? $mobilityStatusLabels : [];
$targetGroups = is_array($assignmentTargets ?? null) ? $assignmentTargets : ['postes' => [], 'aav' => [], 'offres' => []];
$kindLabels = \App\Services\Personnel\AssignmentTargetCatalog::KIND_LABELS;

$renderTargets = static function (string $id) use ($h, $targetGroups): void {
    $hasAny = ($targetGroups['postes'] ?? []) !== [] || ($targetGroups['aav'] ?? []) !== [] || ($targetGroups['offres'] ?? []) !== [];
    echo '<div class="mav-field"><label for="' . $h($id) . '">Poste, AAV ou offre</label>';
    echo '<select id="' . $h($id) . '" name="target_ref">';
    echo '<option value="">— Choisir —</option>';
    if (!$hasAny) {
        echo '<option value="" disabled>Aucun poste, AAV ni offre disponible</option>';
    } else {
        $groups = ['postes' => 'Postes', 'aav' => 'AAV · Appels à volontaire', 'offres' => 'Offres'];
        foreach ($groups as $key => $label) {
            $rows = is_array($targetGroups[$key] ?? null) ? $targetGroups[$key] : [];
            if ($rows === []) {
                continue;
            }
            echo '<optgroup label="' . $h($label) . '">';
            foreach ($rows as $row) {
                echo '<option value="' . $h((string) ($row['value'] ?? '')) . '">' . $h((string) ($row['label'] ?? '')) . '</option>';
            }
            echo '</optgroup>';
        }
    }
    echo '</select></div>';
};
$via = [
    'initial' => 'Grade initial',
    'anciennete' => 'Ancienneté',
    'choix' => 'Au choix',
    'exception' => 'Passage exceptionnel',
];
$modeLabel = [
    'automatic' => 'Automatique à l’ancienneté',
    'choice' => 'Au choix, sur demande',
    'both' => 'Automatique ou au choix',
    'none' => 'Aucune voie ouverte',
];
$format = static function (string $iso) use ($h): string {
    $ts = strtotime(substr($iso, 0, 10));

    return $ts !== false ? $h(date('d/m/Y', $ts)) : $h($iso);
};
$abbr = static function (?array $g): string {
    if ($g === null) {
        return '—';
    }
    $code = trim((string) ($g['short_label'] ?? $g['code'] ?? $g['grade_code'] ?? ''));
    if ($code === '') {
        $label = trim((string) ($g['label'] ?? $g['grade_label'] ?? ''));
        $code = mb_strtoupper(mb_substr($label, 0, 3));
    }

    return mb_substr($code, 0, 6);
};

$pendingAssignment = false;
$pendingAdvancement = false;
$pendingCount = 0;
foreach ($mobilityRequests as $row) {
    if ((string) ($row['status'] ?? '') !== 'pending') {
        continue;
    }
    $pendingCount++;
    $t = (string) ($row['request_type'] ?? '');
    if (in_array($t, ['assignment', 'unit_change', 'job_application'], true)) {
        $pendingAssignment = true;
    }
    if (in_array($t, ['advancement', 'career_wish'], true)) {
        $pendingAdvancement = true;
    }
}

$conditions = $next !== null && is_array($next['conditions'] ?? null) ? $next['conditions'] : [];
$condMet = count(array_filter($conditions, static fn ($c): bool => !empty($c['met'])));
$monthsIn = $next !== null ? (int) ($next['months_in_grade'] ?? 0) : 0;
$monthsReq = $next !== null && isset($next['months_required']) && $next['months_required'] !== null && $next['months_required'] !== ''
    ? (int) $next['months_required'] : null;
$progress = $monthsReq !== null && $monthsReq > 0 ? min(100, (int) round($monthsIn * 100 / $monthsReq)) : null;
$canAsk = $next !== null && !empty($next['choice']) && empty($next['seniority_eligible']);
$pendingOpinions = count(array_filter($opinions, static fn ($o): bool => !empty($o['pending'])));

// État du parcours, en une phrase.
$state = ['tone' => 'muted', 'text' => 'Aucune voie d’avancement n’est ouverte pour l’instant.'];
if ($next === null) {
    $state = ['tone' => 'muted', 'text' => $current === null
        ? 'Votre grade apparaîtra ici dès qu’il sera renseigné sur votre fiche.'
        : 'Pas de grade suivant sur l’échelle de votre filière.'];
} elseif (!empty($next['seniority_eligible'])) {
    $state = ['tone' => 'ok', 'text' => 'Conditions réunies : le passage est automatique, sans demande.'];
} elseif ($canAsk && !empty($next['already_volunteered'])) {
    $state = ['tone' => 'info', 'text' => 'Votre candidature au tableau d’avancement est enregistrée.'];
} elseif ($canAsk && $pendingAdvancement) {
    $state = ['tone' => 'info', 'text' => 'Votre demande d’avancement est en cours d’examen.'];
} elseif ($canAsk) {
    $state = ['tone' => 'warn', 'text' => !empty($next['campaign_id'])
        ? 'Une campagne est ouverte : vous pouvez déposer votre candidature.'
        : 'Vous pouvez demander l’avancement au choix.'];
} elseif (!empty($next['automatic'])) {
    $state = ['tone' => 'muted', 'text' => (string) ($next['seniority_reason'] ?? 'L’avancement automatique n’est pas encore acquis.')];
}

$statusTone = static fn (string $s): string => match ($s) {
    'approved', 'applied' => 'ok',
    'pending' => 'warn',
    'rejected' => 'bad',
    default => 'muted',
};
?>
<div class="mav">
    <?php if (!empty($success)): ?><p class="mav-flash mav-flash--ok" role="status"><?= $h($success) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="mav-flash mav-flash--bad" role="alert"><?= $h($error) ?></p><?php endif; ?>

    <section class="mav-summary" aria-label="Votre parcours">
        <article class="mav-hero">
            <p class="mav-hero__kicker">Votre parcours <?= adv_info('Grade actuel', 'Ligne d’historique ouverte : elle n’est jamais réécrite. Un nouveau grade ajoute une ligne et clôture la précédente.') ?></p>
            <div class="mav-ladder">
                <div class="mav-rung">
                    <span class="mav-rung__badge" aria-hidden="true"><?= $h($abbr($current)) ?></span>
                    <span class="mav-rung__text">
                        <span class="mav-rung__label">Grade actuel</span>
                        <strong class="mav-rung__name"><?= $current !== null ? $h((string) ($current['label'] ?? '')) : 'Non renseigné' ?></strong>
                        <?php if ($current !== null): ?>
                        <span class="mav-rung__meta">Depuis le <?= $format((string) ($current['obtained_at'] ?? '')) ?> · <?= $h($via[(string) ($current['obtained_via'] ?? '')] ?? (string) ($current['obtained_via'] ?? '')) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <span class="mav-ladder__arrow" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
                <div class="mav-rung mav-rung--next<?= $next === null ? ' is-empty' : '' ?>">
                    <span class="mav-rung__badge" aria-hidden="true"><?= $next !== null ? $h($abbr($next)) : '—' ?></span>
                    <span class="mav-rung__text">
                        <span class="mav-rung__label">Prochain grade</span>
                        <strong class="mav-rung__name"><?= $next !== null ? $h((string) ($next['grade_label'] ?? '')) : 'Aucun' ?></strong>
                        <?php if ($next !== null): ?>
                        <span class="mav-rung__meta"><?= $h($modeLabel[(string) ($next['mode'] ?? 'none')] ?? '') ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if ($progress !== null): ?>
            <div class="mav-progress">
                <div class="mav-progress__head">
                    <span>Temps de grade</span>
                    <strong><?= $monthsIn ?> / <?= $monthsReq ?> mois</strong>
                </div>
                <div class="mav-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $monthsReq ?>" aria-valuenow="<?= min($monthsIn, $monthsReq) ?>" aria-label="Mois passés dans le grade">
                    <span style="width: <?= $progress ?>%"></span>
                </div>
                <?php if (!empty($next['due_on'])): ?>
                <p class="mav-progress__due">Échéance le <?= $format((string) $next['due_on']) ?></p>
                <?php endif; ?>
            </div>
            <?php elseif ($next !== null && !empty($next['due_on'])): ?>
            <p class="mav-progress__due">Échéance le <?= $format((string) $next['due_on']) ?></p>
            <?php endif; ?>

            <p class="mav-state mav-state--<?= $h($state['tone']) ?>"><?= $h($state['text']) ?></p>

            <?php if ($canAsk && empty($next['already_volunteered']) && !$pendingAdvancement): ?>
            <div class="mav-hero__foot">
                <a class="mav-hero__cta" href="#mav-demande">Demander l’avancement <span aria-hidden="true">→</span></a>
            </div>
            <?php endif; ?>
        </article>

        <div class="mav-stats">
            <div class="mav-stat">
                <span class="mav-stat__label">Conditions</span>
                <span class="mav-stat__value"><?= $conditions !== [] ? $condMet . ' / ' . count($conditions) : '—' ?></span>
                <span class="mav-stat__note"><?= $conditions === [] ? 'Aucune condition à afficher.' : ($condMet === count($conditions) ? 'Toutes remplies.' : 'Remplies pour le prochain grade.') ?></span>
            </div>
            <div class="mav-stat">
                <span class="mav-stat__label">Temps de grade</span>
                <span class="mav-stat__value"><?= $next !== null ? $monthsIn . ' mois' : '—' ?></span>
                <span class="mav-stat__note"><?= $monthsReq !== null ? 'Sur ' . $monthsReq . ' mois requis.' : 'Pas de durée minimale indiquée.' ?></span>
            </div>
            <div class="mav-stat">
                <span class="mav-stat__label">Demandes en cours</span>
                <span class="mav-stat__value"><?= $pendingCount ?></span>
                <span class="mav-stat__note"><?= count($mobilityRequests) ?> au total.</span>
            </div>
            <div class="mav-stat">
                <span class="mav-stat__label">Avis de commandement</span>
                <span class="mav-stat__value"><?= count($opinions) ?></span>
                <span class="mav-stat__note"><?= $pendingOpinions > 0 ? $pendingOpinions . ' en attente d’avis.' : ($opinions === [] ? 'Aucun pour l’instant.' : 'Tous rendus.') ?></span>
            </div>
        </div>
    </section>

    <?php if ($next !== null): ?>
    <section class="mav-card" aria-labelledby="mav-cond-title">
        <header class="mav-card__head">
            <div>
                <p class="mav-card__kicker">Prochain grade</p>
                <h2 id="mav-cond-title" class="mav-card__title">Conditions pour devenir <?= $h((string) ($next['grade_label'] ?? '')) ?></h2>
            </div>
            <span class="mav-pill mav-pill--<?= !empty($next['automatic']) ? 'ok' : 'muted' ?>"><?= $h($modeLabel[(string) ($next['mode'] ?? 'none')] ?? '') ?></span>
        </header>
        <?php if ($conditions === []): ?>
            <p class="mav-empty">Aucune condition particulière n’est fixée pour ce grade.</p>
        <?php else: ?>
            <ul class="mav-conditions">
                <?php foreach ($conditions as $cond): ?>
                <?php $met = !empty($cond['met']); ?>
                <li class="mav-cond<?= $met ? ' is-met' : '' ?>">
                    <span class="mav-cond__icon" aria-hidden="true">
                        <?php if ($met): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                        <?php endif; ?>
                    </span>
                    <span class="mav-cond__text">
                        <strong><?= $h((string) ($cond['label'] ?? '')) ?></strong>
                        <span><?= $h((string) ($cond['detail'] ?? '')) ?></span>
                    </span>
                    <span class="sr-only"><?= $met ? '(remplie)' : '(non remplie)' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canAsk): ?>
        <div id="mav-demande" class="mav-action">
            <?php if (!empty($next['already_volunteered'])): ?>
                <p class="mav-note mav-note--info">Votre candidature au tableau d’avancement est déjà enregistrée.</p>
            <?php elseif (!empty($next['campaign_id'])): ?>
                <h3 class="mav-action__title">Candidater au tableau d’avancement</h3>
                <form method="post" class="mav-form" action="<?= $h(url('back-office/ma-situation/avancement/' . (int) $next['campaign_id'] . '/volontaire')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="mav-form__grid">
                        <?php $renderTargets('mav-target-campaign'); ?>
                        <div class="mav-field mav-field--wide">
                            <label for="mav-notes-campaign">Motivation <span class="mav-opt">(facultatif)</span></label>
                            <textarea id="mav-notes-campaign" name="notes" rows="3" maxlength="2000" placeholder="Précisez un poste ou un souhait d’affectation."></textarea>
                        </div>
                    </div>
                    <label class="mav-check"><input type="checkbox" name="mobility_requested" value="1"> Je demande aussi une mobilité</label>
                    <div class="mav-form__foot"><button class="ath-btn ath-btn--solid" type="submit">Demander l’avancement</button></div>
                </form>
            <?php else: ?>
                <h3 class="mav-action__title">Demander l’avancement au choix</h3>
                <form method="post" class="mav-form" action="<?= $h(url('back-office/ma-situation/avancement/demande')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="target_label" value="<?= $h((string) ($next['grade_label'] ?? '')) ?>">
                    <div class="mav-form__grid">
                        <?php $renderTargets('mav-target-request'); ?>
                        <div class="mav-field mav-field--wide">
                            <label for="mav-notes-request">Motivation</label>
                            <textarea id="mav-notes-request" name="notes" rows="3" maxlength="2000" placeholder="Pourquoi demander ce grade maintenant ?"<?= $pendingAdvancement ? ' disabled' : '' ?>></textarea>
                        </div>
                    </div>
                    <?php if ($pendingAdvancement): ?>
                        <p class="mav-note mav-note--info">Une demande d’avancement est déjà en attente.</p>
                    <?php else: ?>
                        <div class="mav-form__foot"><button class="ath-btn ath-btn--solid" type="submit">Demander l’avancement</button></div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
        <?php elseif (empty($next['automatic'])): ?>
            <p class="mav-note">Aucune voie d’avancement n’est ouverte pour ce grade.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($offer !== null && $next === null): ?>
    <section class="mav-card mav-card--accent">
        <?php if (!empty($offer['is_eligible'])): ?>
            <h2 class="mav-card__title">Vous êtes éligible à l’avancement au grade de <?= $h((string) ($offer['grade_label'] ?? '')) ?></h2>
        <?php else: ?>
            <h2 class="mav-card__title">Campagne ouverte pour <?= $h((string) ($offer['grade_label'] ?? 'le grade suivant')) ?></h2>
            <p class="mav-note"><?= $h((string) ($offer['eligibility_reason'] ?? 'Les conditions ne sont pas réunies.')) ?></p>
        <?php endif; ?>
        <?php if (!empty($offer['already_volunteered'])): ?>
            <p class="mav-note mav-note--info">Votre candidature est déjà enregistrée.</p>
        <?php elseif (!empty($offer['is_eligible'])): ?>
            <form method="post" class="mav-form" action="<?= $h(url('back-office/ma-situation/avancement/' . (int) $offer['campaign_id'] . '/volontaire')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <div class="mav-form__grid"><?php $renderTargets('mav-target-offer'); ?></div>
                <div class="mav-form__foot"><button class="ath-btn ath-btn--solid" type="submit">Me porter volontaire</button></div>
            </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="mav-grid">
        <section class="mav-card" aria-labelledby="mav-aff-title">
            <header class="mav-card__head">
                <div>
                    <p class="mav-card__kicker">Mobilité</p>
                    <h2 id="mav-aff-title" class="mav-card__title">Demande d’affectation</h2>
                </div>
            </header>
            <p class="mav-lead">Choisissez un poste, un AAV (appel à volontaire) ou une offre. L’encadrement traite la demande dans la mobilité interne.</p>
            <?php if (!$mobilityReady): ?>
                <p class="mav-note">Les demandes d’affectation ne sont pas encore disponibles.</p>
            <?php elseif ($pendingAssignment): ?>
                <p class="mav-note mav-note--info">Une demande d’affectation est déjà en attente.</p>
            <?php else: ?>
                <details class="mav-disclosure">
                    <summary>Déposer une demande d’affectation</summary>
                    <form method="post" class="mav-form" action="<?= $h(url('back-office/ma-situation/avancement/affectation')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="mav-form__grid">
                            <?php $renderTargets('mav-target-assign'); ?>
                            <?php if ($units !== []): ?>
                            <div class="mav-field">
                                <label for="mav-unit">Unité visée</label>
                                <select id="mav-unit" name="target_unit_id">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?= (int) ($unit['id'] ?? 0) ?>"><?= $h((string) ($unit['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="mav-field mav-field--wide">
                                <label for="mav-precision">Précision <span class="mav-opt">(facultatif)</span></label>
                                <input id="mav-precision" type="text" name="target_label" maxlength="200" placeholder="Complément si la liste ne suffit pas">
                            </div>
                            <div class="mav-field mav-field--wide">
                                <label for="mav-motivation">Motivation</label>
                                <textarea id="mav-motivation" name="motivation" rows="3" maxlength="2000" placeholder="Pourquoi cette affectation ?"></textarea>
                            </div>
                        </div>
                        <div class="mav-form__foot"><button class="ath-btn ath-btn--solid" type="submit">Déposer la demande d’affectation</button></div>
                    </form>
                </details>
            <?php endif; ?>

            <h3 class="mav-subtitle">Mes demandes</h3>
            <?php if ($mobilityRequests === []): ?>
                <p class="mav-empty">Aucune demande déposée.</p>
            <?php else: ?>
                <ul class="mav-list">
                    <?php foreach ($mobilityRequests as $row): ?>
                    <?php
                    $st = (string) ($row['status'] ?? '');
                    $kind = (string) ($row['target_kind'] ?? '');
                    $tl = trim((string) ($row['target_label'] ?? ''));
                    $meta = array_filter([
                        $kind !== '' && isset($kindLabels[$kind]) ? $kindLabels[$kind] : '',
                        $tl,
                        !empty($row['created_at']) ? 'déposée le ' . date('d/m/Y', (int) strtotime((string) $row['created_at'])) : '',
                    ]);
                    ?>
                    <li class="mav-list__item">
                        <span class="mav-list__text">
                            <strong><?= $h((string) ($typeLabels[$row['request_type'] ?? ''] ?? $row['request_type'] ?? '')) ?></strong>
                            <?php if ($meta !== []): ?><span><?= $h(implode(' · ', $meta)) ?></span><?php endif; ?>
                        </span>
                        <span class="mav-pill mav-pill--<?= $statusTone($st) ?>"><?= $h((string) ($statusLabels[$st] ?? $st)) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="mav-card" aria-labelledby="mav-op-title">
            <header class="mav-card__head">
                <div>
                    <p class="mav-card__kicker">Commandement</p>
                    <h2 id="mav-op-title" class="mav-card__title">Avis de commandement</h2>
                </div>
            </header>
            <?php if ($opinions === []): ?>
                <div class="mav-empty mav-empty--box">
                    <strong>Aucun avis pour l’instant</strong>
                    <span>Ils apparaissent dès qu’une commission se prononce sur votre candidature.</span>
                </div>
            <?php else: ?>
                <ul class="mav-list">
                    <?php foreach ($opinions as $row): ?>
                    <li class="mav-list__item mav-list__item--stack">
                        <span class="mav-list__text">
                            <strong><?= $h((string) ($row['grade_label'] ?? 'Avancement')) ?><?php if ((int) ($row['year'] ?? 0) > 0): ?> · <?= (int) $row['year'] ?><?php endif; ?></strong>
                            <span>Décision : <?= $h((string) ($row['decision_label'] ?? '—')) ?></span>
                            <?php if (trim((string) ($row['notes'] ?? '')) !== ''): ?>
                                <span class="mav-quote"><?= $h((string) $row['notes']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="mav-pill mav-pill--<?= !empty($row['pending']) ? 'warn' : 'ok' ?>"><?= !empty($row['pending']) ? 'Avis non encore rendu' : $h((string) ($row['opinion_label'] ?? '—')) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="mav-card" aria-labelledby="mav-hist-title">
        <header class="mav-card__head">
            <div>
                <p class="mav-card__kicker">Carrière</p>
                <h2 id="mav-hist-title" class="mav-card__title">Historique des grades <?= adv_info('Historique', 'Chaque promotion (initial, ancienneté, choix ou exception) reste une ligne. On corrige en ajoutant, pas en écrasant.') ?></h2>
            </div>
        </header>
        <?php if ($history === []): ?>
            <p class="mav-empty">Aucune ligne pour l’instant.</p>
        <?php else: ?>
            <ol class="mav-history">
                <?php foreach (array_reverse($history) as $row): ?>
                <?php $open = empty($row['ends_at']); ?>
                <li class="mav-history__item<?= $open ? ' is-current' : '' ?>">
                    <span class="mav-history__dot" aria-hidden="true"></span>
                    <span class="mav-history__body">
                        <strong><?= $h((string) ($row['label'] ?? '')) ?></strong>
                        <span><?= $format((string) ($row['obtained_at'] ?? '')) ?> — <?= $open ? 'en cours' : $format((string) $row['ends_at']) ?></span>
                    </span>
                    <span class="mav-pill mav-pill--<?= $open ? 'ok' : 'muted' ?>"><?= $h($via[(string) ($row['obtained_via'] ?? '')] ?? 'Attribution') ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
