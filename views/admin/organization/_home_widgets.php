<?php
declare(strict_types=1);

/**
 * Accueil du back-office en widgets (App\Services\BackOffice\Home\HomeLayout) : grille sans trou, widgets vides en
 * version compacte, mode « Personnaliser » (barre d'édition, poignées, tailles, ajout, réinitialisation).
 * Inclus par views/admin/organization/dashboard.php, dont il reprend les variables ($pil*, $home, $orgProfileGaps…).
 *
 * @var array<string, mixed> $home
 * @var callable(string): string $pilH
 */

use App\Services\BackOffice\Home\HomeLayout;

$hwEdit = !empty($home['customize']);
$hwCanSave = !array_key_exists('can_save', $home) || !empty($home['can_save']);
$hwAllowed = is_array($home['allowed'] ?? null) ? array_values($home['allowed']) : null;
$hwItems = is_array($home['items'] ?? null) ? $home['items'] : HomeLayout::defaults(HomeLayout::ROLE_MANAGER, $hwAllowed);
$hwCatalog = HomeLayout::catalog();
$hwSheets = is_array($home['sheets'] ?? null) ? $home['sheets'] : [];
$hwReadable = [];
foreach (is_array($home['readable'] ?? null) ? $home['readable'] : [] as $hwS) {
    $hwReadable[(int) $hwS['id']] = $hwS;
}
$hwAvail = is_array($home['availability'] ?? null) ? $home['availability'] : null;
$hwSessions = is_array($home['sessions'] ?? null) ? $home['sessions'] : null;
$hwCustom = is_array($home['custom'] ?? null) ? $home['custom'] : null;
$hwGaps = is_array($orgProfileGaps ?? null) ? $orgProfileGaps : ['rows' => [], 'total' => 0, 'error' => null];
$hwGapTotal = (int) ($hwGaps['total'] ?? 0);
$hwGapError = (string) ($hwGaps['error'] ?? '');

// Tableaux lisibles pas encore placés : proposés dans « Ajouter un widget ».
$hwInLayout = array_column($hwItems, 'id');
if ($hwEdit) {
    foreach ($hwReadable as $hwSid => $hwS) {
        if (!in_array('sheet:' . $hwSid, $hwInLayout, true)) {
            $hwItems[] = ['id' => 'sheet:' . $hwSid, 'w' => 12, 'h' => 4, 'hidden' => true];
        }
    }
}

/** Le widget a-t-il une donnée à montrer à cette personne (droit et données chargées) ? */
$hwHas = static function (string $id) use ($pilPipeline, $hwAvail, $hwSessions, $hwCustom, $hwSheets, $hwReadable, $hwEdit, $hwAllowed): bool {
    if (HomeLayout::isSheet($id)) {
        $sid = HomeLayout::sheetId($id);

        return isset($hwSheets[$sid]) || ($hwEdit && isset($hwReadable[$sid]));
    }
    if ($hwAllowed !== null && !in_array($id, $hwAllowed, true)) {
        return false;
    }

    return match ($id) {
        'pipeline' => $pilPipeline !== null,
        'availability' => $hwAvail !== null,
        'sessions' => $hwSessions !== null,
        'custom' => $hwCustom !== null,
        default => true,
    };
};

$hwPortalSum = array_sum(array_map('intval', $pilPortal)) + array_sum(array_map('intval', $pilAtak));
/** Widget vide : version compacte d'une rangée (hors mode « Personnaliser », qui montre la taille choisie). */
$hwEmpty = [
    'operation' => $pilHero === null,
    'decisions' => $pilDecisions['items'] === [],
    'tasks' => $homeTasks === [],
    'participation' => $pilPart['bars'] === [],
    'units' => $pilUnits === [],
    'pipeline' => $pilPipeline !== null && $pilPipeline['people'] === [] && array_sum(array_map(static fn (array $t): int => (int) $t['n'], $pilPipeline['tiles'])) === 0,
    'agenda' => $pilAgenda['items'] === [],
    'feed' => $pilFeed === [],
    'usage' => $pilDays === [] || $hwPortalSum === 0,
    'messages' => $pilMessages === [],
    'gaps' => $hwGapTotal === 0 && $hwGapError === '',
    'custom' => $hwCustom !== null && ($hwCustom['blocks'] ?? []) === [],
    'shortcuts' => $homeShortcuts === [],
    'availability' => $hwAvail !== null && (empty($hwAvail['ready']) || (int) ($hwAvail['total'] ?? 0) === 0),
    'sessions' => $hwSessions !== null && (empty($hwSessions['ready']) || ($hwSessions['rows'] ?? []) === []),
];

$hwLabel = static function (string $id) use ($hwCatalog, $hwReadable, $hwSheets): string {
    if (HomeLayout::isSheet($id)) {
        $sid = HomeLayout::sheetId($id);

        return 'Tableau : ' . (string) ($hwSheets[$sid]['title'] ?? $hwReadable[$sid]['title'] ?? $sid);
    }

    return (string) ($hwCatalog[$id]['label'] ?? $id);
};
$hwHint = static function (string $id) use ($hwCatalog, $hwReadable): string {
    if (HomeLayout::isSheet($id)) {
        $s = $hwReadable[HomeLayout::sheetId($id)] ?? null;

        return $s !== null && empty($s['mine']) ? 'Partagé par un gestionnaire, en lecture seule.' : 'Votre tableau : tri, filtre, formules, import et export CSV.';
    }

    return (string) ($hwCatalog[$id]['hint'] ?? '');
};

// Widgets à rendre : visibles (et, en mode « Personnaliser », aussi les masqués, pour les rajouter sans recharger).
$hwRows = [];
$hwOrder = 0;
foreach ($hwItems as $hwIt) {
    $hwId = (string) ($hwIt['id'] ?? '');
    $hwB = HomeLayout::bounds($hwId);
    if ($hwB === null || !$hwHas($hwId)) {
        continue;
    }
    $hwHidden = !empty($hwIt['hidden']);
    if ($hwHidden && !$hwEdit) {
        continue;
    }
    $hwRows[] = [
        'id' => $hwId,
        'w' => max($hwB['min'][0], min($hwB['max'][0], (int) ($hwIt['w'] ?? $hwB['def'][0]))),
        'h' => max($hwB['min'][1], min($hwB['max'][1], (int) ($hwIt['h'] ?? $hwB['def'][1]))),
        'hidden' => $hwHidden,
        'compact' => !$hwEdit && !empty($hwEmpty[$hwId]),
        'empty' => !empty($hwEmpty[$hwId]),
        'order' => $hwOrder++,
        'bounds' => $hwB,
    ];
}
$hwVisible = array_values(array_filter($hwRows, static fn (array $r): bool => !$r['hidden']));
$hwPlan = HomeLayout::plan($hwVisible, $hwEdit);
$hwById = array_column($hwRows, null, 'id');
$hwRender = [];
foreach ($hwPlan as $hwP) {
    $hwRender[] = $hwById[$hwP['id']] + ['pos' => $hwP];
}
foreach ($hwRows as $hwR) {
    if ($hwR['hidden']) {
        $hwRender[] = $hwR + ['pos' => null];
    }
}
$hwDomId = static fn (string $id): string => 'dw-' . preg_replace('/[^a-z0-9]+/', '-', $id);
?>
<?php if ($pilKpiError !== ''): ?>
<p class="pil-banner pil-banner--warn" role="status"><?= $pilIcon('warn') ?><span class="pil-banner__text"><?= $pilH($pilKpiError) ?></span></p>
<?php endif; ?>

<?php if ($hwEdit): ?>
<section class="dw-bar" id="personnaliser" aria-labelledby="dw-bar-title" data-dw-bar>
    <div class="dw-bar__head">
        <h2 class="dw-bar__title" id="dw-bar-title">Personnaliser le tableau de bord</h2>
        <p class="dw-bar__desc">Glissez les widgets par leur poignée, changez leur taille, masquez-les ou ajoutez-en. Votre disposition ne change que pour vous, dans cette communauté.</p>
    </div>
    <?php if ($hwCanSave): ?>
    <div class="dw-bar__acts">
        <button type="button" class="dw-btn" data-bo-dialog-open="dw-add" data-dw-open-add><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Ajouter un widget</button>
        <form method="post" action="<?= $pilH(url('back-office/accueil/disposition')) ?>" class="dw-bar__form" data-dw-reset-form>
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="reset" value="1">
            <button type="submit" class="dw-btn" data-dw-reset>Réinitialiser la disposition</button>
        </form>
        <a class="dw-btn" href="<?= $pilH(url('back-office')) ?>" data-dw-cancel>Annuler</a>
        <button type="button" class="dw-btn dw-btn--primary" data-dw-save>Enregistrer</button>
    </div>
    <p class="dw-bar__status" role="status" aria-live="polite" data-dw-live></p>
    <noscript><p class="dw-bar__status">Le glisser-déposer et les tailles demandent JavaScript. « Réinitialiser la disposition » reste disponible.</p></noscript>
    <?php else: ?>
    <p class="dw-bar__status dw-bar__status--warn" role="status">Personnalisation indisponible pour le moment (mise à jour de la base en attente) : la disposition par défaut reste affichée.</p>
    <div class="dw-bar__acts"><a class="dw-btn" href="<?= $pilH(url('back-office')) ?>">Fermer</a></div>
    <?php endif; ?>
    <div hidden data-dw-msgs>
        <span data-msg="grab">« %1 » saisi, position %2 sur %3. Flèches pour déplacer, Entrée pour déposer, Échap pour annuler.</span>
        <span data-msg="moved">« %1 » : position %2 sur %3.</span>
        <span data-msg="drop">« %1 » déposé en position %2 sur %3.</span>
        <span data-msg="cancel">Déplacement annulé : « %1 » revient en position %2.</span>
        <span data-msg="size">« %1 » : %2 colonnes sur %3, %4 rangées.</span>
        <span data-msg="limit">« %1 » : taille la plus petite ou la plus grande atteinte.</span>
        <span data-msg="hidden">« %1 » masqué. Il reste disponible dans « Ajouter un widget ».</span>
        <span data-msg="added">« %1 » ajouté en bas du tableau de bord.</span>
        <span data-msg="saving">Enregistrement…</span>
        <span data-msg="saved">Disposition enregistrée.</span>
        <span data-msg="error">La disposition n’a pas pu être enregistrée. Réessayez.</span>
        <span data-msg="reset">Revenir à la disposition par défaut de votre rôle ? Vos réglages seront perdus.</span>
        <span data-msg="leave">Des changements ne sont pas enregistrés.</span>
        <span data-msg="shown">Affiché</span>
        <span data-msg="add">Ajouter</span>
        <span data-msg="none">Aucun widget ne correspond à cette recherche.</span>
    </div>
</section>
<?php endif; ?>

<?php if ($hwVisible === [] && !$hwEdit): ?>
<section class="pil-card pil-empty-home">
    <p class="pil-empty">Tous les widgets sont masqués. <a class="pil-link" href="<?= $pilH(url('back-office') . '?personnaliser=1#personnaliser') ?>">Choisir des widgets à afficher</a></p>
</section>
<?php endif; ?>

<div class="dw-wrap">
<div class="dw-grid<?= $hwEdit ? ' is-editing' : '' ?>" data-dw-grid<?= $hwEdit && $hwCanSave ? ' data-dw-edit data-dw-url="' . $pilH(url('back-office/accueil/disposition')) . '" data-dw-csrf="' . $pilH(\App\Core\Csrf::token()) . '"' : '' ?>>
<?php foreach ($hwRender as $hwR): ?>
<?php
$hwId = $hwR['id'];
$hwPos = $hwR['pos'];
$hwCompact = $hwR['compact'];
$hwKind = HomeLayout::isSheet($hwId) ? 'sheet' : (string) ($hwCatalog[$hwId]['kind'] ?? 'list');
$hwStyle = $hwPos === null ? '' : sprintf(
    '--x:%d;--y:%d;--w:%d;--h:%d;--x6:%d;--y6:%d;--w6:%d;--h6:%d;--x2:%d;--y2:%d;--w2:%d',
    $hwPos['x'], $hwPos['y'], $hwPos['w'], $hwPos['h'], $hwPos['x6'], $hwPos['y6'], $hwPos['w6'], $hwPos['h6'], $hwPos['x2'], $hwPos['y2'], $hwPos['w2']
);
$hwName = $hwLabel($hwId);
$hwDom = $hwDomId($hwId);
?>
<div class="dw dw--<?= $pilH($hwKind) ?><?= $hwCompact ? ' is-compact' : '' ?><?= $hwR['empty'] ? ' is-empty' : '' ?><?= $hwPos !== null && $hwPos['h'] === 1 && !$hwCompact ? ' is-row' : '' ?>" data-widget="<?= $pilH($hwId) ?>" id="<?= $pilH($hwDom) ?>"<?= $hwStyle !== '' ? ' style="' . $pilH($hwStyle) . '"' : '' ?><?= $hwR['hidden'] ? ' hidden' : '' ?><?php if ($hwEdit): ?> data-w="<?= (int) $hwR['w'] ?>" data-h="<?= (int) $hwR['h'] ?>" data-min-w="<?= (int) $hwR['bounds']['min'][0] ?>" data-min-h="<?= (int) $hwR['bounds']['min'][1] ?>" data-max-w="<?= (int) $hwR['bounds']['max'][0] ?>" data-max-h="<?= (int) $hwR['bounds']['max'][1] ?>" data-order="<?= (int) $hwR['order'] ?>" data-label="<?= $pilH($hwName) ?>"<?php endif; ?>>
    <?php if ($hwEdit): ?>
    <div class="dw-edit" role="group" aria-label="Widget « <?= $pilH($hwName) ?> »">
        <button type="button" class="dw-edit__grip" data-dw-grip aria-label="Déplacer « <?= $pilH($hwName) ?> »" aria-describedby="dw-grip-help"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg></button>
        <span class="dw-edit__name"><?= $pilH($hwName) ?></span>
        <span class="dw-edit__size" data-dw-size><?= (int) $hwR['w'] ?> × <?= (int) $hwR['h'] ?></span>
        <button type="button" class="dw-edit__btn dw-edit__btn--move" data-dw-act="up" aria-label="Monter « <?= $pilH($hwName) ?> »"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></button>
        <button type="button" class="dw-edit__btn dw-edit__btn--move" data-dw-act="down" aria-label="Descendre « <?= $pilH($hwName) ?> »"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <button type="button" class="dw-edit__btn" data-dw-sizer aria-expanded="false" aria-controls="<?= $pilH($hwDom . '-size') ?>" aria-label="Taille de « <?= $pilH($hwName) ?> »"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 15v5h-5M4 4l6 6M20 20l-6-6"/></svg></button>
        <button type="button" class="dw-edit__btn" data-dw-act="hide" aria-label="Masquer « <?= $pilH($hwName) ?> »"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        <div class="dw-size" id="<?= $pilH($hwDom . '-size') ?>" hidden>
            <div class="dw-size__row">
                <span class="dw-size__lab">Largeur</span>
                <button type="button" class="dw-edit__btn" data-dw-act="w-" aria-label="Rétrécir « <?= $pilH($hwName) ?> »">−</button>
                <output class="dw-size__val" data-dw-out="w"><?= (int) $hwR['w'] ?></output>
                <button type="button" class="dw-edit__btn" data-dw-act="w+" aria-label="Élargir « <?= $pilH($hwName) ?> »">+</button>
            </div>
            <div class="dw-size__row">
                <span class="dw-size__lab">Hauteur</span>
                <button type="button" class="dw-edit__btn" data-dw-act="h-" aria-label="Raccourcir « <?= $pilH($hwName) ?> »">−</button>
                <output class="dw-size__val" data-dw-out="h"><?= (int) $hwR['h'] ?></output>
                <button type="button" class="dw-edit__btn" data-dw-act="h+" aria-label="Allonger « <?= $pilH($hwName) ?> »">+</button>
            </div>
            <p class="dw-size__help">De <?= (int) $hwR['bounds']['min'][0] ?> à <?= (int) $hwR['bounds']['max'][0] ?> colonnes, de <?= (int) $hwR['bounds']['min'][1] ?> à <?= (int) $hwR['bounds']['max'][1] ?> rangées.</p>
        </div>
    </div>
    <?php endif; ?>
    <?php require base_path('views/admin/organization/_home_widget_body.php'); ?>
    <?php if ($hwEdit): ?>
    <span class="dw-resize" data-dw-resize aria-hidden="true"></span>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div>

<?php if ($hwEdit && $hwCanSave): ?>
<p class="ath-sr-only" id="dw-grip-help">Entrée ou Espace pour saisir le widget, flèches pour le déplacer, Entrée pour le déposer, Échap pour annuler.</p>
<dialog class="bo-dialog dw-drawer" id="dw-add" aria-labelledby="dw-add-title">
    <div class="bo-dialog__form">
        <div class="bo-dialog__head">
            <div class="bo-dialog__titles">
                <h2 class="bo-dialog__title" id="dw-add-title">Ajouter un widget</h2>
                <p class="bo-dialog__sub">Seuls les widgets dont vous pouvez voir les données sont proposés.</p>
            </div>
            <button type="button" class="bo-dialog__close" data-bo-dialog-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="bo-dialog__body">
            <label class="bo-dialog__label" for="dw-add-q">Rechercher</label>
            <input type="search" id="dw-add-q" class="dw-drawer__q" placeholder="Nom ou description du widget" autocomplete="off" data-dw-search>
            <ul class="dw-cat" data-dw-catalog>
                <?php // Widgets à ajouter d'abord, puis ceux déjà affichés. ?>
                <?php foreach (array_merge(array_filter($hwRows, static fn (array $r): bool => $r['hidden']), array_filter($hwRows, static fn (array $r): bool => !$r['hidden'])) as $hwR): ?>
                <?php $hwId = $hwR['id']; $hwB = $hwR['bounds']; ?>
                <li class="dw-cat__it" data-dw-cat="<?= $pilH($hwId) ?>">
                    <span class="dw-cat__pv" aria-hidden="true" style="<?= $pilH('--pw:' . $hwB['def'][0] . ';--ph:' . $hwB['def'][1]) ?>"><i></i></span>
                    <span class="dw-cat__txt"><b><?= $pilH($hwLabel($hwId)) ?></b><small><?= $pilH($hwHint($hwId)) ?></small><small class="dw-cat__dim"><?= (int) $hwB['def'][0] ?> × <?= (int) $hwB['def'][1] ?> par défaut</small></span>
                    <button type="button" class="dw-btn dw-btn--sm" data-dw-add="<?= $pilH($hwId) ?>"<?= $hwR['hidden'] ? '' : ' disabled' ?>><?= $hwR['hidden'] ? 'Ajouter' : 'Affiché' ?></button>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="dw-cat__none" data-dw-none hidden>Aucun widget ne correspond à cette recherche.</p>
            <form method="post" action="<?= $pilH(url('back-office/tableaux')) ?>" class="dw-drawer__new">
                <?= \App\Core\Csrf::field() ?>
                <label class="bo-dialog__label" for="dw-new-sheet">Nouveau tableau</label>
                <p class="bo-dialog__help">Une petite grille à remplir : en-têtes, tri, filtre, formules, import et export CSV.</p>
                <div class="dw-drawer__row">
                    <input id="dw-new-sheet" name="title" maxlength="80" placeholder="Ex. : Matériel à commander" required>
                    <button type="submit" class="dw-btn">Créer le tableau</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
<?php endif; ?>
