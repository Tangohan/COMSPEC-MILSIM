<?php
declare(strict_types=1);

/**
 * Groupes opérationnels (et équipes, via $unitKind = 'team') — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. Cartes filtrables (page publique, responsable),
 * recherche, tri et remplissage par rapport à l’effectif maximum.
 *
 * @var list<array<string, mixed>> $groups
 * @var array<int, int> $memberCounts
 * @var array<int, array<string, mixed>> $commanders
 * @var array<int, string> $parentNames
 * @var string|null $unitKind
 */

$groups = is_array($groups ?? null) ? $groups : (is_array($teams ?? null) ? $teams : []);
$memberCounts = is_array($memberCounts ?? null) ? $memberCounts : [];
$commanders = is_array($commanders ?? null) ? $commanders : [];
$parentNames = is_array($parentNames ?? null) ? $parentNames : [];
$ui = require __DIR__ . '/_kind.php';
$base = (string) $ui['base'];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');

$cmdNameOf = static function (array $g) use ($commanders): string {
    $cmdId = (int) ($g['commander_user_id'] ?? 0);
    $cmd = $cmdId > 0 ? ($commanders[$cmdId] ?? null) : null;
    if (!is_array($cmd)) {
        return '';
    }
    $cs = trim((string) ($cmd['callsign'] ?? ''));

    return $cs !== '' ? $cs : trim((string) ($cmd['display_name'] ?? ''));
};

// Une unité est publique par défaut : l’absence de colonne vaut « visible ».
$publicComplete = 0;
$publicIncomplete = 0;
$hidden = 0;
$noLead = 0;
$assigned = 0;
foreach ($groups as $g) {
    $assigned += (int) ($memberCounts[(int) ($g['id'] ?? 0)] ?? 0);
    if ($cmdNameOf($g) === '') {
        $noLead++;
    }
    $isPublic = !array_key_exists('show_on_public_page', $g) || (int) ($g['show_on_public_page'] ?? 0) === 1;
    if (!$isPublic) {
        $hidden++;
        continue;
    }
    if (trim((string) ($g['public_blurb'] ?? '')) !== '') {
        $publicComplete++;
    } else {
        $publicIncomplete++;
    }
}
$total = count($groups);
$pctOf = static fn (int $n): string => $total > 0 ? (string) (int) round($n / $total * 100) . '%' : '0%';
?>
<?php if ($error): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $success) ?></p>
<?php endif; ?>

<?php
$athKpis = [
    ['label' => (string) $ui['kpiTotal'], 'value' => (string) $total, 'tone' => '#1e4f80', 'pct' => $total > 0 ? '100%' : '0%', 'note' => $assigned . ' affectation' . ($assigned > 1 ? 's' : '') . ' de membres'],
    ['label' => 'VITRINE COMPLÈTE', 'value' => (string) $publicComplete, 'tone' => '#0b8a5c', 'pct' => $pctOf($publicComplete), 'note' => 'présentation renseignée'],
    ['label' => 'PRÉSENTATION À ÉCRIRE', 'value' => (string) $publicIncomplete, 'tone' => $publicIncomplete === 0 ? '#0b8a5c' : '#c98a12', 'pct' => $pctOf($publicIncomplete), 'note' => 'visibles mais sans texte'],
    ['label' => 'SANS RESPONSABLE', 'value' => (string) $noLead, 'tone' => $noLead === 0 ? '#0b8a5c' : '#c98a12', 'pct' => $pctOf($noLead), 'note' => $noLead === 0 ? 'chacun a son chef' : 'chef à désigner'],
    ['label' => 'MASQUÉS', 'value' => (string) $hidden, 'tone' => '#64748b', 'pct' => $pctOf($hidden), 'note' => 'hors page publique'],
];
require base_path('views/partials/ath_kpis.php');

$accentOf = static function (array $g): string {
    $c = trim((string) ($g['public_accent_color'] ?? ''));

    return preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : '#1e4f80';
};
$badgeOf = static function (array $g): string {
    $code = trim((string) ($g['code'] ?? ''));
    if ($code !== '') {
        return mb_strtoupper(mb_substr($code, 0, 4));
    }

    return function_exists('user_display_initials') ? user_display_initials((string) ($g['name'] ?? '?'), 2) : mb_strtoupper(mb_substr((string) ($g['name'] ?? '?'), 0, 2));
};
?>
<div class="bo-grp" data-bo-groups>
    <div class="bo-grp__bar">
        <input type="search" class="bo-grp__search" placeholder="Rechercher par nom, code ou responsable" aria-label="Rechercher" data-grp-search>
        <div class="bo-grp__bar-actions">
            <a href="<?= $h(url((string) $ui['other']['href'])) ?>" class="ath-btn"><?= $h((string) $ui['other']['label']) ?></a>
            <a href="<?= $h(url('back-office/organisation/structure')) ?>" class="ath-btn">Organigramme</a>
            <a href="<?= $h(url($base . '/create')) ?>" class="ath-btn ath-btn--solid"><?= $h((string) $ui['create']) ?></a>
        </div>
    </div>
    <?php if ($groups !== []): ?>
    <div class="bo-grp__bar">
        <div class="bo-grp__filters" role="group" aria-label="Filtrer">
            <button type="button" class="bo-grp__chip" aria-pressed="true" data-grp-filter="all">Tous (<?= $total ?>)</button>
            <button type="button" class="bo-grp__chip" aria-pressed="false" data-grp-filter="ok">Visibles (<?= $publicComplete ?>)</button>
            <button type="button" class="bo-grp__chip" aria-pressed="false" data-grp-filter="todo">À compléter (<?= $publicIncomplete ?>)</button>
            <button type="button" class="bo-grp__chip" aria-pressed="false" data-grp-filter="off">Masqués (<?= $hidden ?>)</button>
            <button type="button" class="bo-grp__chip" aria-pressed="false" data-grp-filter="nolead">Sans responsable (<?= $noLead ?>)</button>
        </div>
        <label class="bo-grp__sort">
            <span>Trier par</span>
            <select data-grp-sort>
                <option value="order">Ordre d’affichage</option>
                <option value="name">Nom</option>
                <option value="count">Effectif</option>
                <option value="free">Places libres</option>
            </select>
        </label>
    </div>
    <?php endif; ?>

    <?php if ($groups === []): ?>
    <div class="bo-grp__empty">
        <strong><?= $h((string) $ui['emptyTitle']) ?></strong>
        <?= $h((string) $ui['emptyText']) ?>
        <p style="margin:14px 0 0;"><a href="<?= $h(url($base . '/create')) ?>" class="ath-btn ath-btn--solid"><?= $h((string) $ui['createFirst']) ?></a></p>
    </div>
    <?php else: ?>
    <div class="bo-grp__grid" data-grp-grid>
        <?php foreach (array_values($groups) as $i => $g):
            $id = (int) ($g['id'] ?? 0);
            $isPublic = !array_key_exists('show_on_public_page', $g) || (int) ($g['show_on_public_page'] ?? 0) === 1;
            $blurb = trim((string) ($g['public_blurb'] ?? ''));
            $stateKey = !$isPublic ? 'off' : ($blurb !== '' ? 'ok' : 'todo');
            $stateLabel = ['ok' => 'Visible', 'todo' => 'À compléter', 'off' => 'Masqué'][$stateKey];
            $count = (int) ($memberCounts[$id] ?? 0);
            $cmdName = $cmdNameOf($g);
            $capacity = (int) ($g['public_capacity'] ?? 0);
            $fillPct = $capacity > 0 ? min(100, (int) round($count / $capacity * 100)) : 0;
            $free = $capacity > 0 ? max(0, $capacity - $count) : -1;
            $parentName = (string) ($parentNames[(int) ($g['parent_id'] ?? 0)] ?? '');
            $code = trim((string) ($g['code'] ?? ''));
            $search = mb_strtolower(trim(((string) ($g['name'] ?? '')) . ' ' . $code . ' ' . $cmdName . ' ' . $parentName));
        ?>
        <article class="bo-grp-card" style="--grp-accent: <?= $h($accentOf($g)) ?>" data-grp-card data-state="<?= $stateKey ?>" data-lead="<?= $cmdName === '' ? '0' : '1' ?>"
                 data-search="<?= $h($search) ?>" data-order="<?= (int) $i ?>" data-name="<?= $h(mb_strtolower((string) ($g['name'] ?? ''))) ?>" data-count="<?= $count ?>" data-free="<?= $free ?>">
            <div class="bo-grp-card__band" aria-hidden="true"></div>
            <div class="bo-grp-card__body">
                <div class="bo-grp-card__head">
                    <span class="bo-grp-card__badge" aria-hidden="true"><?= $h($badgeOf($g)) ?></span>
                    <div style="min-width:0;">
                        <h3 class="bo-grp-card__title"><a href="<?= $h(url($base . '/' . $id)) ?>"><?= $h((string) ($g['name'] ?? '—')) ?></a></h3>
                        <p class="bo-grp-card__sub"><?= $code !== '' ? 'Code ' . $h($code) : 'Sans code' ?><?= $parentName !== '' ? ' · rattaché à ' . $h($parentName) : '' ?></p>
                    </div>
                </div>
                <?php if ($blurb !== ''): ?>
                <p class="bo-grp-card__blurb"><?= $h($blurb) ?></p>
                <?php elseif ($isPublic): ?>
                <p class="bo-grp-card__blurb bo-grp-card__blurb--empty">Présentation à écrire : l’encadré public est vide.</p>
                <?php else: ?>
                <p class="bo-grp-card__blurb">Non affiché sur la page publique.</p>
                <?php endif; ?>
                <div class="bo-grp-card__facts">
                    <span><b><?= $count ?></b><?= $capacity > 0 ? ' / ' . $capacity : '' ?> membre<?= $count > 1 ? 's' : '' ?></span>
                    <span>Responsable : <b<?= $cmdName === '' ? ' class="bo-grp-card__nolead"' : '' ?>><?= $cmdName !== '' ? $h($cmdName) : 'à désigner' ?></b></span>
                </div>
                <?php if ($capacity > 0): ?>
                <div class="bo-grp-fill" role="img" aria-label="<?= $count ?> membre(s) sur <?= $capacity ?> places">
                    <span class="bo-grp-fill__bar<?= $count >= $capacity ? ' is-full' : '' ?>" style="width: <?= $fillPct ?>%"></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="bo-grp-card__foot">
                <span class="bo-grp-state bo-grp-state--<?= $stateKey ?>"><?= $stateLabel ?></span>
                <span class="bo-grp-card__actions">
                    <a href="<?= $h(url($base . '/' . $id . '/edit')) ?>">Modifier</a>
                </span>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="bo-grp__none" data-grp-none><?= $h((string) $ui['none']) ?></p>
    <?php endif; ?>
</div>
<script>
(function () {
    var root = document.querySelector('[data-bo-groups]');
    if (!root) { return; }
    var input = root.querySelector('[data-grp-search]');
    var chips = root.querySelectorAll('[data-grp-filter]');
    var grid = root.querySelector('[data-grp-grid]');
    var cards = Array.prototype.slice.call(root.querySelectorAll('[data-grp-card]'));
    var none = root.querySelector('[data-grp-none]');
    var sort = root.querySelector('[data-grp-sort]');
    var state = 'all';
    var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    function apply() {
        var q = norm(input ? input.value.trim() : '');
        var shown = 0;
        cards.forEach(function (c) {
            var okState = state === 'all' || (state === 'nolead' ? c.getAttribute('data-lead') === '0' : c.getAttribute('data-state') === state);
            var ok = okState && (q === '' || norm(c.getAttribute('data-search')).indexOf(q) !== -1);
            c.hidden = !ok;
            if (ok) { shown++; }
        });
        if (none) { none.style.display = shown === 0 ? 'block' : 'none'; }
    }
    function reorder() {
        if (!grid || !sort) { return; }
        var by = sort.value;
        var num = function (c, k) { return parseInt(c.getAttribute(k), 10) || 0; };
        cards.slice().sort(function (a, b) {
            var byOrder = num(a, 'data-order') - num(b, 'data-order');
            if (by === 'name') { return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'), 'fr') || byOrder; }
            if (by === 'count') { return num(b, 'data-count') - num(a, 'data-count') || byOrder; }
            if (by === 'free') {
                // Les unités sans effectif maximum passent en dernier.
                var fa = num(a, 'data-free'), fb = num(b, 'data-free');
                return (fa < 0 ? 1 : 0) - (fb < 0 ? 1 : 0) || fb - fa || byOrder;
            }
            return byOrder;
        }).forEach(function (c) { grid.appendChild(c); });
    }
    chips.forEach(function (b) {
        b.addEventListener('click', function () {
            state = b.getAttribute('data-grp-filter');
            chips.forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
            apply();
        });
    });
    if (input) { input.addEventListener('input', apply); }
    if (sort) { sort.addEventListener('change', reorder); }
})();
</script>
