<?php
declare(strict_types=1);

/**
 * Groupes opérationnels — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office.
 *
 * @var list<array<string, mixed>> $groups
 */

$groups = is_array($groups ?? null) ? $groups : [];
$memberCounts = is_array($memberCounts ?? null) ? $memberCounts : [];
$commanders = is_array($commanders ?? null) ? $commanders : [];

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');

// Un groupe est public par défaut : l’absence de colonne vaut « visible ».
$publicComplete = 0;
$publicIncomplete = 0;
$hidden = 0;
foreach ($groups as $g) {
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
    [
        'label' => 'GROUPES',
        'value' => (string) $total,
        'delta' => '',
        'tone' => '#1e4f80',
        'pct' => $total > 0 ? '100%' : '0%',
        'note' => 'dans l’organigramme',
    ],
    [
        'label' => 'VITRINE COMPLÈTE',
        'value' => (string) $publicComplete,
        'delta' => '',
        'tone' => '#0b8a5c',
        'pct' => $pctOf($publicComplete),
        'note' => 'présentation renseignée',
    ],
    [
        'label' => 'PRÉSENTATION À ÉCRIRE',
        'value' => (string) $publicIncomplete,
        'delta' => '',
        'tone' => $publicIncomplete === 0 ? '#0b8a5c' : '#c98a12',
        'pct' => $pctOf($publicIncomplete),
        'note' => 'visibles mais sans texte',
    ],
    [
        'label' => 'MASQUÉS',
        'value' => (string) $hidden,
        'delta' => '',
        'tone' => '#64748b',
        'pct' => $pctOf($hidden),
        'note' => 'hors page publique',
    ],
];
require base_path('views/partials/ath_kpis.php');
?>

<?php
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
        <input type="search" class="bo-grp__search" placeholder="Rechercher un groupe ou un code" aria-label="Rechercher un groupe" data-grp-search>
        <div class="bo-grp__filters" role="group" aria-label="Filtrer par page publique">
            <button type="button" class="bo-grp__chip" aria-pressed="true" data-grp-filter="all">Tous (<?= $total ?>)</button>
            <button type="button" class="bo-grp__chip" aria-pressed="false" data-grp-filter="ok">Visibles (<?= $publicComplete ?>)</button>
            <button type="button" class="bo-grp__chip" aria-pressed="false" data-grp-filter="todo">À compléter (<?= $publicIncomplete ?>)</button>
            <button type="button" class="bo-grp__chip" aria-pressed="false" data-grp-filter="off">Masqués (<?= $hidden ?>)</button>
        </div>
        <div class="bo-grp__bar-actions">
            <a href="<?= $h(url('back-office/organisation/structure')) ?>" class="ath-btn">Hub structure</a>
            <a href="<?= $h(url('back-office/groups/create')) ?>" class="ath-btn ath-btn--solid">Créer un groupe</a>
        </div>
    </div>

    <?php if ($groups === []): ?>
    <div class="bo-grp__empty">
        <strong>Aucun groupe pour l’instant</strong>
        Un groupe rassemble quelques membres : un groupe de combat, une cellule, une équipe d’appui.
        <p style="margin:14px 0 0;"><a href="<?= $h(url('back-office/groups/create')) ?>" class="ath-btn ath-btn--solid">Créer le premier groupe</a></p>
    </div>
    <?php else: ?>
    <div class="bo-grp__grid" data-grp-grid>
        <?php foreach ($groups as $g):
            $id = (int) ($g['id'] ?? 0);
            $isPublic = !array_key_exists('show_on_public_page', $g) || (int) ($g['show_on_public_page'] ?? 0) === 1;
            $blurb = trim((string) ($g['public_blurb'] ?? ''));
            $stateKey = !$isPublic ? 'off' : ($blurb !== '' ? 'ok' : 'todo');
            $stateLabel = ['ok' => 'Visible', 'todo' => 'À compléter', 'off' => 'Masqué'][$stateKey];
            $count = (int) ($memberCounts[$id] ?? 0);
            $cmdId = (int) ($g['commander_user_id'] ?? 0);
            $cmd = $cmdId > 0 ? ($commanders[$cmdId] ?? null) : null;
            $cmdName = $cmd ? trim((string) (($cmd['callsign'] ?? '') !== '' ? $cmd['callsign'] : ($cmd['display_name'] ?? ''))) : '';
            $capacity = (int) ($g['public_capacity'] ?? 0);
            $search = mb_strtolower(trim(((string) ($g['name'] ?? '')) . ' ' . ((string) ($g['code'] ?? '')) . ' ' . $cmdName));
        ?>
        <article class="bo-grp-card" style="--grp-accent: <?= $h($accentOf($g)) ?>" data-grp-card data-state="<?= $stateKey ?>" data-search="<?= $h($search) ?>">
            <div class="bo-grp-card__band" aria-hidden="true"></div>
            <div class="bo-grp-card__body">
                <div class="bo-grp-card__head">
                    <span class="bo-grp-card__badge" aria-hidden="true"><?= $h($badgeOf($g)) ?></span>
                    <div>
                        <h3 class="bo-grp-card__title"><a href="<?= $h(url('back-office/groups/' . $id)) ?>"><?= $h((string) ($g['name'] ?? '—')) ?></a></h3>
                        <p class="bo-grp-card__sub"><?= ($g['code'] ?? '') !== '' && ($g['code'] ?? null) !== null ? 'Code ' . $h((string) $g['code']) : 'Sans code' ?></p>
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
                    <span>Responsable : <b><?= $cmdName !== '' ? $h($cmdName) : 'aucun' ?></b></span>
                </div>
            </div>
            <div class="bo-grp-card__foot">
                <span class="bo-grp-state bo-grp-state--<?= $stateKey ?>"><?= $stateLabel ?></span>
                <span class="bo-grp-card__actions">
                    <a href="<?= $h(url('back-office/groups/' . $id . '/edit')) ?>">Modifier</a>
                </span>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="bo-grp__none" data-grp-none>Aucun groupe ne correspond.</p>
    <?php endif; ?>
</div>
<script>
(function () {
    var root = document.querySelector('[data-bo-groups]');
    if (!root) { return; }
    var input = root.querySelector('[data-grp-search]');
    var chips = root.querySelectorAll('[data-grp-filter]');
    var cards = root.querySelectorAll('[data-grp-card]');
    var none = root.querySelector('[data-grp-none]');
    var state = 'all';
    function apply() {
        var q = (input && input.value || '').trim().toLowerCase();
        var shown = 0;
        cards.forEach(function (c) {
            var ok = (state === 'all' || c.getAttribute('data-state') === state) && (q === '' || (c.getAttribute('data-search') || '').indexOf(q) !== -1);
            c.hidden = !ok;
            if (ok) { shown++; }
        });
        if (none) { none.style.display = shown === 0 ? 'block' : 'none'; }
    }
    chips.forEach(function (b) {
        b.addEventListener('click', function () {
            state = b.getAttribute('data-grp-filter');
            chips.forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
            apply();
        });
    });
    if (input) { input.addEventListener('input', apply); }
})();
</script>
