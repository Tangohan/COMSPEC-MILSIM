<?php
declare(strict_types=1);

/**
 * Organisation › Disponibilité des unités.
 *
 * @var list<array<string, mixed>> $scores Lignes UnitReadinessService (confidentialité déjà appliquée)
 * @var bool $readinessCanOpenUnit Lien vers la fiche unité
 * @var bool $readinessLoadError
 */

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$scores = is_array($scores ?? null) ? $scores : [];
$canOpen = !empty($readinessCanOpenUnit);
$loadError = !empty($readinessLoadError);

$toneOf = static fn (int $s): string => $s >= 75 ? 'ok' : ($s >= 50 ? 'warn' : 'bad');
$toneLabels = ['ok' => 'Prête', 'warn' => 'À surveiller', 'bad' => 'Critique'];
$pct = static fn (int $part, int $total): ?int => $total > 0 ? (int) max(0, min(100, round($part * 100 / $total))) : null;

/** @return array{0: string, 1: string} libellé relatif, date complète */
$activityOf = static function (mixed $raw): array {
    $raw = trim((string) $raw);
    $ts = $raw !== '' && !str_starts_with($raw, '0000') ? strtotime($raw) : false;
    if ($ts === false) {
        return ['Aucune activité', ''];
    }
    $days = (int) floor((time() - $ts) / 86400);
    $label = match (true) {
        $days <= 0 => 'Aujourd’hui',
        $days === 1 => 'Hier',
        $days < 30 => 'Il y a ' . $days . ' jours',
        $days < 60 => 'Il y a 1 mois',
        $days < 365 => 'Il y a ' . intdiv($days, 30) . ' mois',
        default => 'Il y a plus d’un an',
    };

    return [$label, date('d/m/Y', $ts)];
};

$counts = ['ok' => 0, 'warn' => 0, 'bad' => 0];
$sumScore = 0;
$authorized = 0;
$filled = 0;
$vacant = 0;
$qualOk = 0;
$qualTotal = 0;
foreach ($scores as $s) {
    $counts[$toneOf((int) ($s['score'] ?? 0))]++;
    $sumScore += (int) ($s['score'] ?? 0);
    $authorized += (int) ($s['authorized'] ?? 0);
    $filled += (int) ($s['filled'] ?? 0);
    $vacant += (int) ($s['vacant'] ?? 0);
    $qualOk += (int) ($s['qual_ok'] ?? 0);
    $qualTotal += (int) ($s['qual_total'] ?? 0);
}
$total = count($scores);
$avg = $total > 0 ? (int) round($sumScore / $total) : null;
$qualPct = $pct($qualOk, $qualTotal);

$searchIcon = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/readiness-board.css')) ?>">

<div class="rdy" data-rdy>
    <?php if ($loadError): ?>
        <div class="rdy-empty" role="alert">
            <h2>Les scores n’ont pas pu être calculés</h2>
            <p>Un problème temporaire empêche d’afficher la disponibilité des unités. Rechargez la page dans quelques instants.</p>
        </div>
    <?php elseif ($total === 0): ?>
        <div class="rdy-empty">
            <h2>Aucune unité à évaluer pour l’instant</h2>
            <p>Le score apparaît dès qu’une unité existe dans l’organigramme. Créez vos unités et leurs postes pour suivre leur disponibilité.</p>
            <a href="<?= $h(url('orbat')) ?>">Ouvrir l’organigramme</a>
        </div>
    <?php else: ?>
        <ul class="rdy-kpis" aria-label="Vue d’ensemble">
            <li class="rdy-kpi">
                <p class="rdy-kpi__label">Unités suivies</p>
                <p class="rdy-kpi__value"><?= $total ?></p>
                <p class="rdy-kpi__sub"><?= $h(sprintf($counts['bad'] > 1 ? '%d critiques' : '%d critique', (int) $counts['bad'])) ?> · <?= $counts['warn'] ?> à surveiller</p>
            </li>
            <li class="rdy-kpi is-<?= $h($toneOf((int) $avg)) ?>">
                <p class="rdy-kpi__label">Score moyen</p>
                <p class="rdy-kpi__value"><?= (int) $avg ?><small> / 100</small></p>
                <p class="rdy-kpi__sub"><?= $h($toneLabels[$toneOf((int) $avg)]) ?> en moyenne</p>
            </li>
            <li class="rdy-kpi<?= $vacant > 0 ? ' is-warn' : '' ?>">
                <p class="rdy-kpi__label">Postes vacants</p>
                <p class="rdy-kpi__value"><?= $vacant ?></p>
                <p class="rdy-kpi__sub"><?= $authorized > 0 ? $filled . ' pourvus sur ' . $authorized : 'Aucun poste défini' ?></p>
            </li>
            <li class="rdy-kpi<?= $qualPct !== null ? ' is-' . $h($toneOf($qualPct)) : '' ?>">
                <p class="rdy-kpi__label">Qualifications à jour</p>
                <p class="rdy-kpi__value"><?= $qualPct !== null ? $qualPct . '<small> %</small>' : '—' ?></p>
                <p class="rdy-kpi__sub"><?= $qualTotal > 0 ? $qualOk . ' sur ' . $qualTotal : 'Aucune qualification enregistrée' ?></p>
            </li>
        </ul>

        <details class="rdy-help">
            <summary>Comment le score est-il calculé ?</summary>
            <div class="rdy-help__body">
                <p>Le score sur 100 combine trois éléments déjà présents dans l’application, sans nouvelle saisie :</p>
                <ul class="rdy-help__weights">
                    <li>Postes pourvus · 45 %</li>
                    <li>Qualifications à jour · 40 %</li>
                    <li>Activité récente · 15 %</li>
                </ul>
                <p>À partir de 75 l’unité est prête, entre 50 et 74 elle est à surveiller, en dessous de 50 elle est critique.</p>
            </div>
        </details>

        <div class="rdy-toolbar" role="search">
            <label class="rdy-search">
                <span class="sr-only">Rechercher une unité</span>
                <?= $searchIcon ?>
                <input type="search" data-rdy-q placeholder="Rechercher une unité…" autocomplete="off">
            </label>
            <div class="rdy-chips" role="group" aria-label="Filtrer par état">
                <button type="button" class="rdy-chip" data-rdy-filter="all" aria-pressed="true">Toutes <b><?= $total ?></b></button>
                <?php foreach ($toneLabels as $k => $lab): ?>
                    <button type="button" class="rdy-chip rdy-chip--<?= $h($k) ?>" data-rdy-filter="<?= $h($k) ?>" aria-pressed="false"<?= $counts[$k] === 0 ? ' disabled' : '' ?>>
                        <span class="rdy-chip__dot" aria-hidden="true"></span><?= $h($lab) ?> <b><?= $counts[$k] ?></b>
                    </button>
                <?php endforeach; ?>
            </div>
            <label class="rdy-sort">
                Trier par
                <select data-rdy-sort>
                    <option value="score-asc">Score, du plus faible</option>
                    <option value="score-desc">Score, du plus élevé</option>
                    <option value="vacant">Postes vacants</option>
                    <option value="name">Nom de l’unité</option>
                </select>
            </label>
        </div>
        <p class="rdy-count" data-rdy-count aria-live="polite"><?= $total ?> unité<?= $total > 1 ? 's' : '' ?></p>

        <div class="rdy-board">
            <div class="rdy-head" aria-hidden="true">
                <span>Unité</span><span>Score</span><span>Postes pourvus</span><span>Qualifications à jour</span><span>Dernière activité</span>
            </div>
            <ol class="rdy-list" data-rdy-list>
                <?php foreach ($scores as $i => $s):
                    $score = (int) ($s['score'] ?? 0);
                    $tone = $toneOf($score);
                    $uid = (int) ($s['unit_id'] ?? 0);
                    $name = trim((string) ($s['name'] ?? '')) ?: 'Unité';
                    $parent = trim((string) ($s['parent_name'] ?? ''));
                    $anonymized = !empty($s['anonymized']);
                    $conf = is_array($s['confidential'] ?? null) ? $s['confidential'] : null;
                    $sAuth = (int) ($s['authorized'] ?? 0);
                    $sFilled = (int) ($s['filled'] ?? 0);
                    $sVacant = (int) ($s['vacant'] ?? 0);
                    $fillPct = $pct($sFilled, $sAuth);
                    $sQualOk = (int) ($s['qual_ok'] ?? 0);
                    $sQualTotal = (int) ($s['qual_total'] ?? 0);
                    $sQualPct = $pct($sQualOk, $sQualTotal);
                    [$actLabel, $actDate] = $activityOf($s['last_activity'] ?? null);
                    ?>
                    <li class="rdy-row is-<?= $h($tone) ?>"
                        data-rdy-row
                        data-tone="<?= $h($tone) ?>"
                        data-score="<?= $score ?>"
                        data-vacant="<?= $sVacant ?>"
                        data-order="<?= (int) $i ?>"
                        data-name="<?= $h(mb_strtolower($name . ' ' . $parent, 'UTF-8')) ?>">
                        <div class="rdy-cell rdy-cell--unit">
                            <span class="rdy-unit">
                                <?php if ($anonymized && $conf !== null): ?>
                                    <?= \App\Support\ConfidentialDisplay::renderPayload($conf) ?>
                                <?php else: ?>
                                    <?php if ($canOpen && $uid > 0): ?>
                                        <a href="<?= $h(url('back-office/organisation/unites/' . $uid)) ?>"><?= $h($name) ?></a>
                                    <?php else: ?>
                                        <span><?= $h($name) ?></span>
                                    <?php endif; ?>
                                    <?php if ($conf !== null): ?><?= \App\Support\ConfidentialDisplay::renderPayload($conf, true) ?><?php endif; ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($parent !== ''): ?><span class="rdy-unit__parent">Rattachée à <?= $h($parent) ?></span><?php endif; ?>
                        </div>
                        <div class="rdy-cell">
                            <span class="rdy-cell__k">Score</span>
                            <span class="rdy-score">
                                <span class="rdy-score__n" aria-label="Score <?= $score ?> sur 100"><?= $score ?></span>
                                <span class="rdy-score__t"><?= $h($toneLabels[$tone]) ?></span>
                            </span>
                        </div>
                        <div class="rdy-cell">
                            <span class="rdy-cell__k">Postes pourvus</span>
                            <?php if ($sAuth > 0): ?>
                                <span class="rdy-metric"><b><?= $sFilled ?></b><small>/ <?= $sAuth ?></small><?php if ($sVacant > 0): ?><span class="rdy-vacant"><?= $h(sprintf($sVacant > 1 ? '%d vacants' : '%d vacant', (int) $sVacant)) ?></span><?php endif; ?></span>
                                <span class="rdy-bar is-<?= $h($toneOf((int) $fillPct)) ?>" aria-hidden="true"><i style="width: <?= (int) $fillPct ?>%"></i></span>
                            <?php else: ?>
                                <span class="rdy-muted"><?= $sFilled > 0 ? $sFilled . ' membre' . ($sFilled > 1 ? 's' : '') . ', aucun poste défini' : 'Aucun poste défini' ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rdy-cell">
                            <span class="rdy-cell__k">Qualifications à jour</span>
                            <?php if ($sQualTotal > 0): ?>
                                <span class="rdy-metric"><b><?= $sQualOk ?></b><small>/ <?= $sQualTotal ?> · <?= (int) $sQualPct ?> %</small></span>
                                <span class="rdy-bar is-<?= $h($toneOf((int) $sQualPct)) ?>" aria-hidden="true"><i style="width: <?= (int) $sQualPct ?>%"></i></span>
                            <?php else: ?>
                                <span class="rdy-muted">Aucune qualification</span>
                            <?php endif; ?>
                        </div>
                        <div class="rdy-cell">
                            <span class="rdy-cell__k">Dernière activité</span>
                            <span class="rdy-metric"<?= $actDate !== '' ? ' title="' . $h($actDate) . '"' : '' ?>><?= $actDate !== '' ? $h($actLabel) : '<span class="rdy-muted">' . $h($actLabel) . '</span>' ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="rdy-empty" data-rdy-none hidden>
            <h2>Aucune unité ne correspond</h2>
            <p>Modifiez la recherche ou le filtre pour retrouver vos unités.</p>
            <button type="button" class="rdy-reset" data-rdy-reset>Afficher toutes les unités</button>
        </div>
    <?php endif; ?>
</div>

<?php if (!$loadError && $total > 0): ?>
<script>
(function () {
    var root = document.querySelector('[data-rdy]');
    if (!root) return;
    var list = root.querySelector('[data-rdy-list]');
    var rows = Array.prototype.slice.call(root.querySelectorAll('[data-rdy-row]'));
    var q = root.querySelector('[data-rdy-q]');
    var sort = root.querySelector('[data-rdy-sort]');
    var chips = Array.prototype.slice.call(root.querySelectorAll('[data-rdy-filter]'));
    var count = root.querySelector('[data-rdy-count]');
    var none = root.querySelector('[data-rdy-none]');
    var board = root.querySelector('.rdy-board');
    var tone = 'all';
    var norm = function (v) { return String(v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var num = function (el, k) { return parseInt(el.getAttribute('data-' + k), 10) || 0; };
    function apply() {
        var needle = norm(q.value).trim();
        var shown = 0;
        rows.forEach(function (r) {
            var ok = (tone === 'all' || r.getAttribute('data-tone') === tone) && (!needle || norm(r.getAttribute('data-name')).indexOf(needle) !== -1);
            r.hidden = !ok;
            if (ok) shown++;
        });
        var mode = sort.value;
        rows.slice().sort(function (a, b) {
            if (mode === 'name') return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'), 'fr');
            if (mode === 'vacant') return num(b, 'vacant') - num(a, 'vacant') || num(a, 'score') - num(b, 'score');
            if (mode === 'score-desc') return num(b, 'score') - num(a, 'score') || num(a, 'order') - num(b, 'order');
            return num(a, 'score') - num(b, 'score') || num(a, 'order') - num(b, 'order');
        }).forEach(function (r) { list.appendChild(r); });
        count.textContent = shown + (shown > 1 ? ' unités' : ' unité') + (shown !== rows.length ? ' sur ' + rows.length : '');
        none.hidden = shown !== 0;
        board.hidden = shown === 0;
    }
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            tone = c.getAttribute('data-rdy-filter');
            chips.forEach(function (x) { x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); });
            apply();
        });
    });
    q.addEventListener('input', apply);
    sort.addEventListener('change', apply);
    root.querySelector('[data-rdy-reset]').addEventListener('click', function () {
        q.value = '';
        tone = 'all';
        chips.forEach(function (x) { x.setAttribute('aria-pressed', x.getAttribute('data-rdy-filter') === 'all' ? 'true' : 'false'); });
        apply();
        q.focus();
    });
    apply();
})();
</script>
<?php endif; ?>
