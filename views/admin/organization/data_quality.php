<?php
declare(strict_types=1);

/**
 * Qualité des données (back-office) : état des fiches ORBAT, anomalies filtrables, tableau des mouvements,
 * corbeille des postes et versions de l’organigramme (snapshots). Mêmes routes et mêmes champs qu’avant.
 * Comportements : public/assets/js/back-office-data-quality.js. Styles : back-office-data-quality.css.
 */

/** @var array<string, mixed> $summary */
$summary = is_array($summary ?? null) ? $summary : [];
$totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
$anomalies = is_array($summary['anomalies'] ?? null) ? $summary['anomalies'] : [];
$movements = is_array($summary['movements'] ?? null) ? $summary['movements'] : [];
$trash = is_array($summary['trash'] ?? null) ? $summary['trash'] : [];
$snapshots = is_array($summary['snapshots'] ?? null) ? $summary['snapshots'] : [];
$domainModel = is_array($summary['domain_model'] ?? null) ? $summary['domain_model'] : [];
$movementReasonRows = is_array($summary['movement_reasons'] ?? null) ? $summary['movement_reasons'] : [];
$peopleNames = is_array($peopleNames ?? null) ? $peopleNames : [];
$schemaReady = !empty($schemaReady);
$csrfToken = (string) ($csrfToken ?? '');

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$reasonLabels = [];
foreach ($movementReasonRows as $r) {
    $code = (string) ($r['code'] ?? '');
    if ($code !== '') {
        $reasonLabels[$code] = (string) ($r['label'] ?? $code);
    }
}

/* Types d’anomalie détectés par OrbatBilletService::detectAnomalies (codes stables). */
$types = [
    'vacant_billet' => ['label' => 'Postes vacants', 'one' => 'Poste vacant', 'help' => 'Places prévues au tableau d’effectifs sans titulaire.', 'icon' => 'seat'],
    'duplicate_primary' => ['label' => 'Titulaires en trop', 'one' => 'Titulaires en trop', 'help' => 'Plus de titulaires que de places sur un même poste.', 'icon' => 'users'],
    'missing_qualification' => ['label' => 'Qualifications manquantes', 'one' => 'Qualification manquante', 'help' => 'Titulaire sans une qualification exigée par son poste.', 'icon' => 'badge'],
    'unit_without_commander' => ['label' => 'Structures sans responsable', 'one' => 'Structure sans responsable', 'help' => 'Postes clés définis, mais aucun n’est tenu.', 'icon' => 'flag'],
    'assignment_on_inactive_unit' => ['label' => 'Affectations sur structure inactive', 'one' => 'Structure inactive', 'help' => 'Membres encore affectés à une structure archivée ou inactive.', 'icon' => 'archive'],
];
$sevLabels = ['high' => 'Haute', 'medium' => 'Modérée', 'low' => 'Basse'];
$sevRank = ['high' => 0, 'medium' => 1, 'low' => 2];

$nAnomalies = count($anomalies);
$nHigh = (int) ($totals['high'] ?? 0);
$nMedium = (int) ($totals['medium'] ?? 0);
$nLow = (int) ($totals['low'] ?? 0);
$sevTotal = max(1, $nHigh + $nMedium + $nLow);

usort($anomalies, static function (array $a, array $b) use ($sevRank): int {
    return ($sevRank[(string) ($a['severity'] ?? 'medium')] ?? 1) <=> ($sevRank[(string) ($b['severity'] ?? 'medium')] ?? 1);
});

$eventLabels = [
    'billet_occupied' => 'Prise de poste',
    'billet_vacated' => 'Départ de poste',
    'billet_created' => 'Poste créé',
    'billet_restored' => 'Poste restauré',
];
$kindLabels = ['manual' => 'Manuel', 'pre_reorg' => 'Pré-réorganisation', 'scheduled' => 'Programmé'];

$fmtDate = static function (mixed $raw, bool $withTime = true): string {
    $raw = trim((string) $raw);
    if ($raw === '' || str_starts_with($raw, '0000')) {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
};
$person = static function (int $uid) use ($peopleNames): string {
    return $uid > 0 ? (string) ($peopleNames[$uid] ?? ('Membre n° ' . $uid)) : '';
};

$icon = static function (string $name): string {
    $paths = match ($name) {
        'overview' => '<path d="M4 13h6V4H4zM14 20h6v-9h-6zM14 4v4h6V4zM4 20h6v-3H4z"/>',
        'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.01"/>',
        'moves' => '<path d="M7 7h13l-3-3M17 17H4l3 3"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'versions' => '<path d="M3 12a9 9 0 109-9 9 9 0 00-6.4 2.6L3 8"/><path d="M3 3v5h5M12 7v5l3 3"/>',
        'model' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.5 6h7M6 8.5v7M18 8.5v7M8.5 18h7"/>',
        'seat' => '<path d="M6 20v-6h12v6M8 14V6a2 2 0 012-2h4a2 2 0 012 2v8"/>',
        'users' => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8"/>',
        'badge' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
        'flag' => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'archive' => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v12h14V8M10 12h4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'restore' => '<path d="M3 12a9 9 0 109-9 9 9 0 00-6.4 2.6L3 8"/><path d="M3 3v5h5"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
        'chevron' => '<path d="M9 6l6 6-6 6"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="bo-dq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};

$nav = [
    ['id' => 'etat', 'icon' => 'overview', 'title' => 'État des données'],
    ['id' => 'anomalies', 'icon' => 'alert', 'title' => 'Anomalies', 'count' => $nAnomalies, 'tone' => $nHigh > 0 ? 'danger' : ($nAnomalies > 0 ? 'warn' : null)],
    ['id' => 'mouvements', 'icon' => 'moves', 'title' => 'Mouvements', 'count' => count($movements)],
    ['id' => 'corbeille', 'icon' => 'trash', 'title' => 'Corbeille des postes', 'count' => count($trash)],
    ['id' => 'versions', 'icon' => 'versions', 'title' => 'Versions de l’ORBAT', 'count' => count($snapshots)],
    ['id' => 'reperes', 'icon' => 'model', 'title' => 'Modèle des données'],
];
?>
<div class="bo-dq" data-dq>
    <div class="bo-dq__layout">
        <nav class="bo-dq-nav" aria-label="Sections de la page">
            <p class="bo-dq-nav__kicker">Sections</p>
            <ul class="bo-dq-nav__list">
                <?php foreach ($nav as $n): ?>
                <li>
                    <a class="bo-dq-nav__link" href="#dq-<?= $h($n['id']) ?>" data-dq-nav="<?= $h($n['id']) ?>">
                        <?= $icon($n['icon']) ?>
                        <span class="bo-dq-nav__label"><?= $h($n['title']) ?></span>
                        <?php if (isset($n['count'])): ?>
                        <span class="bo-dq-nav__count<?= !empty($n['tone']) ? ' bo-dq-nav__count--' . $h($n['tone']) : '' ?>"><?= (int) $n['count'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="bo-dq__main">
            <?php if (!$schemaReady): ?>
            <div class="bo-dq-alert" role="status">
                <span class="bo-dq-alert__icon"><?= $icon('alert') ?></span>
                <div>
                    <p class="bo-dq-alert__title">Centre pas encore activé</p>
                    <p class="bo-dq-alert__text">Les tables des postes ORBAT n’existent pas encore sur ce serveur. Lancez les migrations pour activer la détection des anomalies, la corbeille et les versions.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- État des données -->
            <section class="bo-dq-section" id="dq-etat" data-dq-section="etat">
                <div class="bo-dq-health bo-dq-health--<?= $nAnomalies === 0 ? 'ok' : ($nHigh > 0 ? 'danger' : 'warn') ?>">
                    <span class="bo-dq-health__icon"><?= $icon($nAnomalies === 0 ? 'check' : 'alert') ?></span>
                    <div class="bo-dq-health__copy">
                        <?php if ($nAnomalies === 0): ?>
                        <p class="bo-dq-health__title">Aucune anomalie détectée</p>
                        <p class="bo-dq-health__text">Postes, titulaires et qualifications sont cohérents avec votre organigramme.</p>
                        <?php else: ?>
                        <?php if ($nAnomalies > 1): ?>
                        <p class="bo-dq-health__title"><?= $nAnomalies ?> anomalies à traiter</p>
                        <?php else: ?>
                        <p class="bo-dq-health__title">1 anomalie à traiter</p>
                        <?php endif; ?>
                        <p class="bo-dq-health__text">
                            <?php if ($nHigh > 0): ?>
                            <span>Dont <?= $nHigh ?> de sévérité haute, à regarder en premier.</span>
                            <?php else: ?>
                            <span>Aucune de sévérité haute.</span>
                            <?php endif; ?>
                            <span>Athena relève des faits, sans juger si une unité est opérationnelle.</span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($nAnomalies > 0): ?>
                <div class="bo-dq-sev" aria-label="Répartition par sévérité">
                    <div class="bo-dq-sev__bar" aria-hidden="true">
                        <?php foreach (['high' => $nHigh, 'medium' => $nMedium, 'low' => $nLow] as $sev => $n): if ($n < 1) { continue; } ?>
                        <span class="bo-dq-sev__seg bo-dq-sev__seg--<?= $sev ?>" style="flex-grow: <?= round($n / $sevTotal * 1000) ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <ul class="bo-dq-sev__legend">
                        <?php foreach (['high' => $nHigh, 'medium' => $nMedium, 'low' => $nLow] as $sev => $n): ?>
                        <li><span class="bo-dq-dot bo-dq-dot--<?= $sev ?>"></span><span><?= $h($sevLabels[$sev]) ?></span> <strong><?= $n ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="bo-dq-types">
                    <?php foreach ($types as $code => $t): $n = (int) ($totals[$code] ?? 0); ?>
                    <a class="bo-dq-type<?= $n > 0 ? ' is-hit' : '' ?>" href="#dq-anomalies" data-dq-pick-type="<?= $h($code) ?>">
                        <span class="bo-dq-type__head">
                            <span class="bo-dq-type__icon"><?= $icon($t['icon']) ?></span>
                            <span class="bo-dq-type__value"><?= $n ?></span>
                        </span>
                        <span class="bo-dq-type__label"><?= $h($t['label']) ?></span>
                        <span class="bo-dq-type__help"><?= $h($t['help']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Anomalies -->
            <section class="bo-dq-section" id="dq-anomalies" data-dq-section="anomalies">
                <header class="bo-dq-section__head">
                    <span class="bo-dq-section__icon"><?= $icon('alert') ?></span>
                    <div>
                        <h2 class="bo-dq-section__title">Anomalies</h2>
                        <p class="bo-dq-section__lead">Détectées automatiquement à chaque visite. Corrigez-les dans l’ORBAT ou sur la fiche concernée, elles disparaissent d’elles-mêmes.</p>
                    </div>
                </header>

                <?php if ($anomalies === []): ?>
                <div class="bo-dq-empty">
                    <span class="bo-dq-empty__icon"><?= $icon('check') ?></span>
                    <p class="bo-dq-empty__title">Rien à signaler</p>
                    <p class="bo-dq-empty__text">Aucune anomalie détectée pour le moment.</p>
                </div>
                <?php else: ?>
                <div class="bo-dq-filters" data-dq-filters>
                    <div class="bo-dq-seg" role="group" aria-label="Filtrer par sévérité">
                        <button type="button" class="bo-dq-seg__btn is-active" data-dq-sev="">Toutes <span><?= $nAnomalies ?></span></button>
                        <?php foreach (['high' => $nHigh, 'medium' => $nMedium, 'low' => $nLow] as $sev => $n): if ($n < 1) { continue; } ?>
                        <button type="button" class="bo-dq-seg__btn" data-dq-sev="<?= $sev ?>"><span class="bo-dq-dot bo-dq-dot--<?= $sev ?>"></span><?= $h($sevLabels[$sev]) ?> <span><?= $n ?></span></button>
                        <?php endforeach; ?>
                    </div>
                    <label class="bo-dq-select">
                        <span class="bo-dq-sr">Type d’anomalie</span>
                        <select data-dq-type>
                            <option value="">Tous les types</option>
                            <?php foreach ($types as $code => $t): if ((int) ($totals[$code] ?? 0) < 1) { continue; } ?>
                            <option value="<?= $h($code) ?>"><?= $h($t['label']) ?> (<?= (int) $totals[$code] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="bo-dq-search">
                        <?= $icon('search') ?>
                        <span class="bo-dq-sr">Rechercher une anomalie</span>
                        <input type="search" class="bo-dq-search__input" placeholder="Poste, structure, membre…" autocomplete="off" data-dq-search>
                    </label>
                </div>

                <ul class="bo-dq-list" data-dq-list>
                    <?php foreach ($anomalies as $a):
                        $code = (string) ($a['code'] ?? '');
                        $sev = (string) ($a['severity'] ?? 'medium');
                        if (!isset($sevLabels[$sev])) {
                            $sev = 'medium';
                        }
                        $meta = is_array($a['meta'] ?? null) ? $a['meta'] : [];
                        $unitId = (int) ($meta['unit_id'] ?? 0);
                        $userId = (int) ($meta['user_id'] ?? 0);
                        $type = $types[$code] ?? ['one' => $code !== '' ? $code : 'Anomalie', 'icon' => 'alert'];
                        $message = (string) ($a['message'] ?? '');
                    ?>
                    <li class="bo-dq-row bo-dq-row--<?= $sev ?>" data-dq-row data-sev="<?= $h($sev) ?>" data-type="<?= $h($code) ?>"
                        data-text="<?= $h(mb_strtolower($message . ' ' . $type['one'] . ' ' . $person($userId))) ?>">
                        <span class="bo-dq-row__icon"><?= $icon($type['icon']) ?></span>
                        <div class="bo-dq-row__body">
                            <p class="bo-dq-row__meta">
                                <span class="bo-dq-pill bo-dq-pill--<?= $sev ?>"><?= $h($sevLabels[$sev]) ?></span>
                                <span class="bo-dq-row__type"><?= $h($type['one']) ?></span>
                            </p>
                            <p class="bo-dq-row__msg"><?= $h($message) ?></p>
                        </div>
                        <div class="bo-dq-row__actions">
                            <?php if ($userId > 0): ?>
                            <a class="bo-dq-link" href="<?= $h(url('personnel/' . $userId)) ?>">Fiche du membre</a>
                            <?php endif; ?>
                            <?php if ($unitId > 0): ?>
                            <a class="bo-dq-link" href="<?= $h(url('back-office/units/' . $unitId . '/edit')) ?>">Structure</a>
                            <?php endif; ?>
                            <a class="bo-dq-link" href="<?= $h(url('orbat')) ?>">ORBAT</a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="bo-dq-noresult" data-dq-noresult hidden>Aucune anomalie ne correspond à ces filtres.</p>
                <button type="button" class="ath-btn bo-dq-more" data-dq-more hidden>Tout afficher (<span data-dq-more-count>0</span> de plus)</button>
                <?php endif; ?>
            </section>

            <!-- Mouvements -->
            <section class="bo-dq-section" id="dq-mouvements" data-dq-section="mouvements">
                <header class="bo-dq-section__head">
                    <span class="bo-dq-section__icon"><?= $icon('moves') ?></span>
                    <div>
                        <h2 class="bo-dq-section__title">Tableau des mouvements</h2>
                        <p class="bo-dq-section__lead">Les 40 derniers évènements du journal de carrière : prises et départs de poste, créations et restaurations.</p>
                    </div>
                </header>
                <?php if ($movements === []): ?>
                <div class="bo-dq-empty bo-dq-empty--quiet">
                    <p class="bo-dq-empty__text">Aucun mouvement enregistré.</p>
                </div>
                <?php else: ?>
                <div class="bo-dq-table-wrap">
                    <table class="bo-dq-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Évènement</th>
                                <th scope="col">Membre</th>
                                <th scope="col">Détail</th>
                                <th scope="col">Motif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $m):
                                $evt = (string) ($m['event_type'] ?? '');
                                $evtLabel = $eventLabels[$evt] ?? ($evt !== '' ? ucfirst(str_replace('_', ' ', $evt)) : '—');
                                $uid = (int) ($m['user_id'] ?? 0);
                                $actorId = (int) ($m['actor_user_id'] ?? 0);
                                $reason = trim((string) ($m['movement_reason'] ?? ''));
                                $tone = match ($evt) {
                                    'billet_occupied' => 'in',
                                    'billet_vacated' => 'out',
                                    'billet_restored', 'billet_created' => 'info',
                                    default => 'neutral',
                                };
                            ?>
                            <tr>
                                <td class="bo-dq-table__date" data-label="Date"><?= $h($fmtDate($m['effective_at'] ?? $m['created_at'] ?? '')) ?></td>
                                <td data-label="Évènement"><span class="bo-dq-evt bo-dq-evt--<?= $tone ?>"><?= $h($evtLabel) ?></span></td>
                                <td data-label="Membre">
                                    <?php if ($uid > 0): ?>
                                    <a class="bo-dq-person" href="<?= $h(url('personnel/' . $uid)) ?>" translate="no"><?= $h($person($uid)) ?></a>
                                    <?php else: ?>
                                    <span class="bo-dq-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Détail"><div>
                                    <span class="bo-dq-table__summary"><?= $h((string) ($m['summary'] ?? '')) ?></span>
                                    <?php if ($actorId > 0 && $actorId !== $uid): ?>
                                    <span class="bo-dq-table__by">par <span translate="no"><?= $h($person($actorId)) ?></span></span>
                                    <?php endif; ?>
                                </div></td>
                                <td data-label="Motif"><?= $reason !== '' ? '<span class="bo-dq-tag">' . $h($reasonLabels[$reason] ?? $reason) . '</span>' : '<span class="bo-dq-muted">—</span>' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <!-- Corbeille -->
            <section class="bo-dq-section" id="dq-corbeille" data-dq-section="corbeille">
                <header class="bo-dq-section__head">
                    <span class="bo-dq-section__icon"><?= $icon('trash') ?></span>
                    <div>
                        <h2 class="bo-dq-section__title">Corbeille des postes</h2>
                        <p class="bo-dq-section__lead">Postes supprimés de l’organigramme. Une restauration les remet en place avec tout leur historique.</p>
                    </div>
                </header>
                <?php if ($trash === []): ?>
                <div class="bo-dq-empty bo-dq-empty--quiet">
                    <p class="bo-dq-empty__text">Aucun poste archivé.</p>
                </div>
                <?php else: ?>
                <ul class="bo-dq-trash">
                    <?php foreach ($trash as $b):
                        $bTitle = trim((string) ($b['title'] ?? ''));
                        $bCode = trim((string) ($b['code'] ?? ''));
                        $bCallsign = trim((string) ($b['org_callsign'] ?? ''));
                        $bWhen = (string) ($b['archived_at'] ?? $b['updated_at'] ?? '');
                    ?>
                    <li class="bo-dq-trash__item">
                        <div class="bo-dq-trash__body">
                            <p class="bo-dq-trash__title" translate="no"><?= $h($bTitle !== '' ? $bTitle : ($bCode !== '' ? $bCode : 'Poste n° ' . (int) ($b['id'] ?? 0))) ?></p>
                            <p class="bo-dq-trash__meta">
                                <?php if (trim((string) ($b['unit_name'] ?? '')) !== ''): ?>
                                <span translate="no"><?= $h((string) $b['unit_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($bCallsign !== ''): ?>
                                <span class="bo-dq-mono" translate="no"><?= $h($bCallsign) ?></span>
                                <?php elseif ($bCode !== '' && $bTitle !== ''): ?>
                                <span class="bo-dq-mono" translate="no"><?= $h($bCode) ?></span>
                                <?php endif; ?>
                                <?php if (trim($bWhen) !== ''): ?>
                                <span>Retiré le <?= $h($fmtDate($bWhen, false)) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <form method="post" action="<?= $h(url('back-office/organisation/qualite-donnees/postes/' . (int) ($b['id'] ?? 0) . '/restaurer')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <button type="submit" class="ath-btn bo-dq-restore"><?= $icon('restore') ?><span>Restaurer</span></button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

            <!-- Versions -->
            <section class="bo-dq-section" id="dq-versions" data-dq-section="versions">
                <header class="bo-dq-section__head">
                    <span class="bo-dq-section__icon"><?= $icon('versions') ?></span>
                    <div>
                        <h2 class="bo-dq-section__title">Versions de l’ORBAT</h2>
                        <p class="bo-dq-section__lead">Enregistrez une photo de l’organigramme (structures, postes, titulaires) avant une réorganisation, pour garder une trace de l’état d’avant.</p>
                    </div>
                </header>
                <div class="bo-dq-versions">
                    <form method="post" action="<?= $h(url('back-office/organisation/qualite-donnees/snapshot')) ?>" class="bo-dq-form">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <p class="bo-dq-form__title"><?= $icon('camera') ?><span>Nouvelle version</span></p>
                        <label class="bo-dq-field">
                            <span class="bo-dq-field__label">Nom de la version</span>
                            <input name="label" type="text" maxlength="200" placeholder="Avant réorg Alpha" class="bo-dq-input">
                            <span class="bo-dq-field__help">Laissé vide : « Snapshot ORBAT ».</span>
                        </label>
                        <div class="bo-dq-field">
                            <span class="bo-dq-field__label" id="dq-kind-label">Type</span>
                            <div class="bo-dq-kinds" role="radiogroup" aria-labelledby="dq-kind-label">
                                <?php foreach ($kindLabels as $k => $kl): ?>
                                <label class="bo-dq-kind">
                                    <input type="radio" name="kind" value="<?= $h($k) ?>" <?= $k === 'manual' ? 'checked' : '' ?>>
                                    <span><?= $h($kl) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <label class="bo-dq-field">
                            <span class="bo-dq-field__label">Date d’effet</span>
                            <input name="effective_at" type="datetime-local" class="bo-dq-input">
                            <span class="bo-dq-field__help">Laissée vide : maintenant. Une date passée photographie l’organigramme tel qu’il était ce jour-là.</span>
                        </label>
                        <label class="bo-dq-field">
                            <span class="bo-dq-field__label">Notes</span>
                            <input name="notes" type="text" maxlength="500" class="bo-dq-input" placeholder="Pourquoi cette version">
                        </label>
                        <button type="submit" class="ath-btn ath-btn--solid bo-dq-form__submit">Enregistrer la version</button>
                    </form>

                    <div class="bo-dq-timeline-wrap">
                        <p class="bo-dq-timeline__kicker">Versions enregistrées</p>
                        <?php if ($snapshots === []): ?>
                        <div class="bo-dq-empty bo-dq-empty--quiet">
                            <p class="bo-dq-empty__text">Aucun snapshot pour l’instant.</p>
                        </div>
                        <?php else: ?>
                        <ol class="bo-dq-timeline">
                            <?php foreach ($snapshots as $s):
                                $kind = (string) ($s['snapshot_kind'] ?? 'manual');
                                $by = (int) ($s['created_by'] ?? 0);
                                $notes = trim((string) ($s['notes'] ?? ''));
                            ?>
                            <li class="bo-dq-timeline__item">
                                <span class="bo-dq-timeline__dot" aria-hidden="true"></span>
                                <div class="bo-dq-timeline__body">
                                    <p class="bo-dq-timeline__title">
                                        <span translate="no"><?= $h((string) ($s['label'] ?? '')) ?></span>
                                        <span class="bo-dq-tag bo-dq-tag--<?= $h($kind) ?>"><?= $h($kindLabels[$kind] ?? $kind) ?></span>
                                    </p>
                                    <p class="bo-dq-timeline__meta">
                                        <span>Effet <?= $h($fmtDate($s['effective_at'] ?? '')) ?></span>
                                        <span>Créée <?= $h($fmtDate($s['created_at'] ?? '')) ?></span>
                                        <?php if ($by > 0): ?>
                                        <span>par <span translate="no"><?= $h($person($by)) ?></span></span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($notes !== ''): ?>
                                    <p class="bo-dq-timeline__notes" translate="no"><?= $h($notes) ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Repères -->
            <section class="bo-dq-section bo-dq-section--soft" id="dq-reperes" data-dq-section="reperes">
                <details class="bo-dq-model">
                    <summary class="bo-dq-model__summary">
                        <span class="bo-dq-section__icon"><?= $icon('model') ?></span>
                        <span class="bo-dq-model__copy">
                            <span class="bo-dq-section__title">Repères : le modèle des données</span>
                            <span class="bo-dq-section__lead">Ce qu’Athena vérifie : personnel, affectation, poste, structure, qualification et historique.</span>
                        </span>
                        <span class="bo-dq-model__chev"><?= $icon('chevron') ?></span>
                    </summary>
                    <p class="bo-dq-model__flow" translate="no">PERSONNEL ↔ AFFECTATION ↔ POSTE ↔ STRUCTURE + QUALIFICATION + HISTORIQUE</p>
                    <div class="bo-dq-model__grid">
                        <?php foreach ($domainModel as $obj): ?>
                        <div class="bo-dq-model__card">
                            <p class="bo-dq-model__label"><?= $h((string) ($obj['label'] ?? '')) ?></p>
                            <p class="bo-dq-model__desc"><?= $h((string) ($obj['description'] ?? '')) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            </section>
        </div>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-data-quality.js')) ?>" defer></script>
