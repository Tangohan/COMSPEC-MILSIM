<?php
declare(strict_types=1);

/**
 * ORBAT — page unique (organigramme, annuaire, tableur) : tout se modifie ici, en fenêtres modales.
 *
 * Le rendu et les interactions sont dans public/assets/js/orbat-app.js (données : #orbat-boot) ;
 * le style dans public/assets/css/orbat-app.css ; les libellés dans lang/{fr,en}/orbat.php.
 *
 * @var array<string, mixed>|null $orbatRosterData
 * @var bool $orbatCanManage
 * @var list<array{id: int, label: string}> $orbatCommanderOptions
 * @var string $orbatCsrfToken
 * @var bool $orbatRecruitmentHub Gestionnaire de la communauté : invitation de membres depuis l’ORBAT
 * @var string $orbatOpen Fenêtre à ouvrir au chargement (unite, groupe, equipe, membre)
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rosterData = $orbatRosterData ?? null;
$canManage = (bool) ($orbatCanManage ?? false);
$hub = !empty($orbatRecruitmentHub);
$orbatLocale = function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
$T = require base_path('lang/fr/orbat.php');
if ($orbatLocale === 'en' && is_file(base_path('lang/en/orbat.php'))) {
    $T = array_replace($T, require base_path('lang/en/orbat.php'));
}
$structTypes = [];
foreach (\App\Support\UnitStructTypes::forTenant((int) \App\Core\Session::get('tenant_id')) as $row) {
    $structTypes[] = ['id' => $row['id'], 'label' => $row['label']];
}
$boot = [
    'base' => rtrim(url(''), '/'),
    'csrf' => (string) ($orbatCsrfToken ?? ''),
    'canManage' => $canManage,
    'hub' => $hub,
    'open' => (string) ($orbatOpen ?? ''),
    'roster' => $rosterData,
    'commanders' => $canManage ? array_values($orbatCommanderOptions ?? []) : [],
    'structTypes' => $structTypes,
    'symbols' => \App\Support\UnitMilitarySymbol::options($orbatLocale),
    'i18n' => $T,
    'msUrl' => asset_url('assets/vendor/milsymbol/milsymbol.js'),
    'personnelBase' => url('personnel/'),
    'fullSheetBase' => url('back-office/organisation/unites/'),
];
$bootJson = json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$exportUrl = url('orbat/export');
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/orbat-app.css')) ?>">

<div class="orb" id="orb" data-view="tree">
    <header class="orb-oh">
        <div>
            <div class="orb-kk"><i aria-hidden="true"></i><?= $h($T['eyebrow']) ?></div>
            <h1><?= $h($T['title']) ?></h1>
        </div>
        <div class="orb-stats" id="orb-stats" aria-live="polite"></div>
        <div class="orb-oacts">
            <?php if ($canManage): ?>
            <a class="orb-link-sm" href="<?= $h(url('back-office/organisation/qualite-donnees')) ?>"><?= $h($T['data_quality']) ?></a>
            <?php endif; ?>
            <?php if ($hub): ?>
            <a class="orb-link-sm" href="<?= $h(url('back-office/organisation/catalogue')) ?>"><?= $h($T['catalog']) ?></a>
            <button type="button" class="orb-hb2" id="orb-invite-btn"><?= $h($T['invite_member']) ?></button>
            <?php endif; ?>
            <a class="orb-hb2" id="orb-export" href="<?= $h($exportUrl) ?>" data-base="<?= $h($exportUrl) ?>" target="_blank" rel="noopener"><?= $h($T['export_pdf']) ?></a>
            <?php if ($canManage): ?>
            <button type="button" class="orb-hb1" id="orb-new">+ <?= $h($T['new_unit']) ?></button>
            <?php endif; ?>
        </div>
    </header>

    <div class="orb-tb" role="toolbar" aria-label="<?= $h($T['title']) ?>">
        <div class="orb-seg" id="orb-vw">
            <button type="button" class="on" data-v="tree" aria-pressed="true"><?= $h($T['view_tree']) ?></button>
            <button type="button" data-v="directory" aria-pressed="false"><?= $h($T['view_directory']) ?></button>
            <button type="button" data-v="sheet" aria-pressed="false"><?= $h($T['view_sheet']) ?></button>
        </div>
        <label class="orb-dsr">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only"><?= $h($T['search_label']) ?></span>
            <input id="orb-q" type="search" autocomplete="off" placeholder="<?= $h($T['search_placeholder']) ?>">
            <em id="orb-qn" aria-live="polite"></em><kbd>/</kbd>
        </label>
        <div class="orb-fc" id="orb-fc" aria-label="<?= $h($T['f_status']) ?>"></div>
        <?php if ($canManage): ?>
        <label class="sr-only" for="orb-as"><?= $h($T['preview_as']) ?></label>
        <select class="orb-tsel" id="orb-as" title="<?= $h($T['preview_as']) ?>">
            <option value=""><?= $h($T['preview_as'] . ' : ' . $T['preview_admin']) ?></option>
            <option value="member"><?= $h($T['preview_as'] . ' : ' . $T['preview_member']) ?></option>
            <option value="cadre"><?= $h($T['preview_as'] . ' : ' . $T['preview_cadre']) ?></option>
            <option value="command"><?= $h($T['preview_as'] . ' : ' . $T['preview_command']) ?></option>
        </select>
        <?php endif; ?>
        <button type="button" class="orb-tbtn" id="orb-exp"><?= $h($T['expand_all']) ?></button>
        <button type="button" class="orb-tbtn" id="orb-col"><?= $h($T['collapse_all']) ?></button>
        <label class="sr-only" for="orb-lang"><?= $h($T['sheet_lang']) ?></label>
        <select class="orb-tsel" id="orb-lang" title="<?= $h($T['sheet_lang']) ?>">
            <option value=""><?= $h($T['sheet_lang_unit']) ?></option>
            <?php foreach (\App\Support\CommunityLanguages::codes() as $code): ?>
            <option value="<?= $h($code) ?>"><?= $h(\App\Support\CommunityLanguages::flag($code) . ' ' . \App\Support\CommunityLanguages::nativeLabel($code)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="orb-tbtn" id="orb-fs" title="<?= $h($T['fullscreen']) ?>" aria-label="<?= $h($T['fullscreen']) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
        </button>
        <button type="button" class="orb-tbtn" id="orb-help" title="<?= $h($T['shortcuts']) ?>" aria-label="<?= $h($T['shortcuts']) ?>">?</button>
    </div>

    <div class="orb-stage">
        <div class="orb-cv" id="orb-cv">
            <div class="orb-world" id="orb-world">
                <svg class="orb-edges" id="orb-edges" aria-hidden="true"><path id="orb-edges-base" d=""/><path id="orb-edges-on" class="on" d=""/></svg>
            </div>
        </div>
        <div class="orb-empty" id="orb-empty" hidden>
            <h2><?= $h($T['empty_title']) ?></h2>
            <p><?= $h($T['empty_text']) ?></p>
            <?php if ($canManage): ?>
            <button type="button" class="orb-hb1" id="orb-empty-new">+ <?= $h($T['empty_create']) ?></button>
            <?php else: ?>
            <p><a class="orb-link-sm" href="<?= $h(url('dashboard')) ?>"><?= $h($T['empty_back']) ?></a></p>
            <?php endif; ?>
        </div>
        <div class="orb-hud">
            <div class="orb-zc">
                <button type="button" id="orb-zo" aria-label="<?= $h($T['zoom_out']) ?>">−</button>
                <span id="orb-zv">100 %</span>
                <button type="button" id="orb-zi" aria-label="<?= $h($T['zoom_in']) ?>">+</button>
                <button type="button" class="fit" id="orb-zf"><?= $h($T['fit']) ?></button>
            </div>
            <span class="orb-hint"><?= $h($canManage ? $T['hint_manage'] : $T['hint']) ?></span>
        </div>
        <div class="orb-dir" id="orb-dir"></div>
        <div class="orb-sheet" id="orb-sheet"></div>
        <aside class="orb-dr" id="orb-dr" aria-hidden="true" aria-label="<?= $h($T['menu_open']) ?>"></aside>
    </div>
</div>

<div class="orb-cm" id="orb-cm" role="menu" aria-hidden="true"></div>
<div class="orb-ov" id="orb-ov" aria-hidden="true"><div class="orb-mdl" id="orb-mdl" role="dialog" aria-modal="true"></div></div>
<div class="orb-toast" id="orb-toast" role="status" aria-live="polite"></div>

<?php if ($hub && isset($roles, $roleMatrix)): ?>
<div id="orb-invite" hidden>
    <form method="post" action="<?= $h(url('back-office/users/store')) ?>" class="space-y-4">
        <h2><?= $h($T['invite_member']) ?></h2>
        <p class="text-sm">Un e-mail lui envoie un lien (valable 48 h) pour créer son mot de passe et compléter son profil. Pour l’envoyer plutôt sur Discord, passez par Effectifs › Ajouter un membre.</p>
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="invite" value="1">
        <input type="hidden" name="duration_hours" value="48">
        <input type="hidden" name="send_email" value="1">
        <?php
        $fieldIdPrefix = 'orb-user-';
        $matrixRootId = 'orb-role-matrix-wrap';
        $organizationRoleLabelMode = $organizationRoleLabelMode ?? \App\Support\OrganizationRoleLabels::MODE_FR;
        $grades = is_array($grades ?? null) ? $grades : [];
        $gradeCategories = is_array($gradeCategories ?? null) ? $gradeCategories : [];
        require base_path('views/admin/organization/partials/user_invite_form_fields.php');
        ?>
        <div class="orb-acts">
            <button type="button" class="orb-btn" data-mclose><?= $h($T['cancel']) ?></button>
            <button type="submit" class="orb-btn orb-btn--dark"><?= $h($T['invite_member']) ?></button>
        </div>
    </form>
</div>
<?php endif; ?>

<script type="application/json" id="orbat-boot"><?= $bootJson !== false ? $bootJson : '{}' ?></script>
<script src="<?= $h(asset_url('assets/js/nato-sidc-icons.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/orbat-app.js')) ?>" defer></script>
