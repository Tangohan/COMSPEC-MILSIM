<?php
/**
 * Studio de l'Academy : qualifications et brevets de la communauté (fiches du référentiel des qualifications).
 * Liste : créations de la communauté mises en avant, catalogue de base (packs pays / OTAN, cours intégrés), archivées.
 * Fiche : identité, classement, validité, brevet remis (insigne, numéro, PDF), conditions d'obtention ; à droite les
 * cours qui y mènent (rattacher / détacher) et les réglages avancés du référentiel.
 */
require __DIR__ . '/init.php';
$customTopbar = true;
require __DIR__ . '/header.php';
require __DIR__ . '/icons.php';
$csrf = \App\Core\Csrf::field();
$kind = in_array($credKind ?? '', ['qualification', 'brevet'], true) ? $credKind : 'qualification';
$isBrevet = $kind === 'brevet';
$credentials = $credentials ?? [];
$studioCourses = $studioCourses ?? [];
$canManage = (bool) ($canManage ?? false);
$canArchive = (bool) ($canArchive ?? false);
$rulesReady = (bool) ($rulesReady ?? false);
$coverUrl = static fn (string $cover): string => asset_url('assets/images/academy/cover-' . $cover . '.webp');
$crestOf = static function (string $title): string {
    $words = preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: ['Q'];
    return mb_strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1)));
};
$warn = ['not_found', 'cred_name_required', 'cred_code_invalid', 'cred_code_taken', 'cred_unavailable', 'cred_rules_pending', 'cred_rule_error', 'cred_badge_failed'];
$courseLabel = static function (string $courseKey) use ($studioCourses, $t): string {
    $c = $studioCourses[$courseKey] ?? null;
    if ($c === null) {
        return $courseKey;
    }
    return $c['title'] . ($c['source'] === 'builtin' ? ' · ' . $t('cred_course_builtin') : ($c['status'] === 'draft' || $c['status'] === 'version' ? ' · ' . $t('cred_course_draft') : ''));
};
$kindCounts = ['qualification' => (int) ($counts['qualification'] ?? 0), 'brevet' => (int) ($counts['brevet'] ?? 0)];
$kindActive = $kind;
$kindCanCreate = $canManage;
$kindCourseDialog = false;
?>
<header class="ast-top">
<a class="tb icon" href="<?= $h($link('studio')) ?>" title="<?= $h($t('studio_all_courses')) ?>"><svg class="i"><use href="#ai-back"/></svg><span class="sr-only"><?= $h($t('studio_all_courses')) ?></span></a>
<span class="wm">ACADEMY <span>STUDIO</span></span>
<span class="sp"></span>
<a class="tb" href="<?= $h($link('classroom')) ?>"><?= $h($t('classroom')) ?></a>
<nav class="lang" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr', 'en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav>
</header>
<main id="main" class="sth sth-cred">
<?php if (!empty($notice)): ?><p class="ast-notice ast-notice--<?= in_array($notice, $warn, true) ? 'warn' : 'ok' ?>" role="status"><?= $h($t($notice)) ?></p><?php endif ?>
<?php if (!empty($credList)): /* ============ Liste ============ */
$ofKind = array_values(array_filter($credentials, static fn (array $c): bool => $c['kind'] === $kind));
$active = array_values(array_filter($ofKind, static fn (array $c): bool => empty($c['archived'])));
$mine = array_values(array_filter($active, static fn (array $c): bool => $c['origin'] === 'community'));
$base = array_values(array_filter($active, static fn (array $c): bool => $c['origin'] !== 'community'));
$archived = array_values(array_filter($ofKind, static fn (array $c): bool => !empty($c['archived'])));
$origin = $credOrigin ?? 'tout';
$tabUrl = static fn (string $o): string => $link('studio') . '?vue=' . ($kind === 'brevet' ? 'brevets' : 'qualifications') . ($o !== 'tout' ? '&origine=' . $o : '');
$card = static function (array $c) use ($h, $t, $link, $crestOf, $canManage): void {
    $url = $link('studio') . '?fiche=' . (int) $c['id'];
    $status = !empty($c['archived']) ? 'archived' : ($c['origin'] === 'community' ? 'pub' : 'builtin'); ?>
<article class="sth-card cred-card<?= $c['origin'] === 'community' ? ' is-mine' : '' ?>" data-course-card data-status="<?= $h($status) ?>" data-title="<?= $h(mb_strtolower($c['name'] . ' ' . $c['code'] . ' ' . $c['short'])) ?>" data-updated="<?= (int) strtotime($c['updated_at'] ?: 'now') ?>" data-chapters="<?= count($c['courses']) ?>">
<a class="cred-head" href="<?= $h($url) ?>" tabindex="-1" aria-hidden="true">
<?php if ($c['badge'] !== ''): ?><img class="cred-badge" src="<?= $h($c['badge']) ?>" alt="" loading="lazy"><?php else: ?><span class="cred-badge cred-badge--crest"><?= $h($crestOf($c['short'] ?: $c['name'])) ?></span><?php endif ?>
<span class="pill p-<?= $h($status) ?>"><i></i><?= $h(!empty($c['archived']) ? $t('cred_archived_pill') : $t('cred_origin_' . $c['origin'])) ?></span>
<code><?= $h($c['code']) ?></code>
</a>
<div class="cb"><h3><a href="<?= $h($url) ?>"><?= $h($c['name']) ?></a></h3>
<p><?= $c['description'] !== '' ? $h($c['description']) : '<i>' . $h($t('no_summary')) . '</i>' ?></p>
<div class="mt"><span class="<?= $c['courses'] ? 'ok' : '' ?>"><?= $h($c['courses'] ? sprintf($t('cred_courses_count'), count($c['courses'])) : $t('cred_no_course')) ?></span>
<span><?= $h($c['permanent'] ? $t('cred_permanent') : sprintf($t('cred_months'), $c['months'])) ?></span>
<?php if ($c['holders'] > 0): ?><span><?= $h(sprintf($t('cred_holders'), $c['holders'])) ?></span><?php endif ?>
<?php if ($c['category'] !== ''): ?><span><?= $h($c['category']) ?></span><?php endif ?></div></div>
<div class="cf"><small><?= $h($c['short']) ?></small><a class="btn dark" href="<?= $h($url) ?>"><?= $h($t($canManage && empty($c['archived']) ? 'cred_edit' : 'cred_open')) ?></a></div>
</article>
<?php };
$shown = ['tout' => $active, 'communaute' => $mine, 'base' => $base, 'archives' => $archived][$origin];
?>
<section class="sth-hero" style="--img:url('<?= $h($coverUrl($isBrevet ? 'hero' : 'night')) ?>')">
<div class="hin"><div><p class="kk"><i></i>Academy<?= !empty($tenant['name']) ? ' · ' . $h($tenant['name']) : '' ?></p><h1><?= $h($t('kind_' . $kind . 's')) ?></h1><p><?= $h($t('cred_intro_' . $kind)) ?></p></div>
<?php if ($canManage): ?><a class="hb1" href="<?= $h($link('studio') . '?nouveau=' . $kind) ?>">+ <?= $h($t('cred_new_' . $kind)) ?></a><?php endif ?></div>
<dl class="hst"><div><dt><?= $h($t('cred_origin_all')) ?></dt><dd><?= count($active) ?></dd></div><div><dt><?= $h($t('cred_origin_mine')) ?></dt><dd class="ok"><?= count($mine) ?></dd></div><div><dt><?= $h($t('cred_origin_base')) ?></dt><dd><?= count($base) ?></dd></div><div><dt><?= $h($t('cred_origin_archived')) ?></dt><dd class="dr"><?= count($archived) ?></dd></div></dl>
</section>
<div class="sth-bar">
<?php require __DIR__ . '/studio_kinds.php'; ?>
<nav class="sth-tabs" aria-label="<?= $h($t('studio_filter')) ?>">
<?php foreach (['tout' => ['cred_origin_all', $active], 'communaute' => ['cred_origin_mine', $mine], 'base' => ['cred_origin_base', $base], 'archives' => ['cred_origin_archived', $archived]] as $o => [$label, $items]): ?><a href="<?= $h($tabUrl($o)) ?>"<?= $origin === $o ? ' class="on" aria-current="page"' : '' ?>><?= $h($t($label)) ?><em><?= count($items) ?></em></a><?php endforeach ?>
</nav>
<label class="sth-search"><svg class="i"><use href="#ai-search"/></svg><input type="search" data-studio-search placeholder="<?= $h($t('cred_search')) ?>" aria-label="<?= $h($t('cred_search')) ?>"></label>
<?php if ($canManage): ?><span class="sp"></span><button class="btn dark" type="button" data-open-dialog="sth-pick">+ <?= $h($t('studio_new')) ?></button><?php endif ?>
</div>
<?php $cq = is_array($credQuota ?? null) ? $credQuota : null; if ($canManage && $cq !== null && !$cq['unlimited']): /* Plafond de la formule : compteur, et la formule qui débloque. */ ?>
<p class="sth-quota<?= $cq['can_add'] ? '' : ' is-full' ?>" role="status"><b><?= (int) $cq['used'] ?> / <?= (int) $cq['limit'] ?></b> <span><?= $h($t('cred_quota_line')) ?></span><?php if ($cq['offer'] !== null): ?> · <span class="sth-plan"><?= $h($t('cred_quota_unlock')) ?> <b translate="no"><?= $h($cq['offer']['name']) ?></b> (<?= $cq['offer']['value'] === 0 ? $h($t('cred_quota_unlimited')) : (int) $cq['offer']['value'] ?>)</span> <a href="<?= $h($cq['upgrade_url']) ?>"><?= $h($t('cred_quota_see')) ?></a><?php endif ?></p>
<?php if (!$cq['can_add']): ?><p class="sth-hint"><?= $h($t('cred_quota_reached')) ?></p><?php endif ?>
<?php endif ?>
<?php if ($origin === 'base'): ?><p class="sth-hint"><?= $h($t('cred_base_help')) ?></p><?php elseif ($origin === 'communaute'): ?><p class="sth-hint"><?= $h($t('cred_mine_help')) ?></p><?php endif ?>
<?php if ($origin === 'tout'): /* Créations de la communauté d'abord, puis le catalogue de base. */ ?>
<div class="sth-sec"><h2><?= $h($t('cred_origin_mine')) ?></h2><p><?= $h($t('cred_mine_help')) ?></p></div>
<div class="sth-grid" data-studio-grid>
<?php foreach ($mine as $item) { $card($item); } ?>
<?php if ($canManage): ?><a class="sth-new" href="<?= $h($link('studio') . '?nouveau=' . $kind) ?>" data-new-card><span class="pl">+</span><b><?= $h($t('cred_new_' . $kind)) ?></b><span><?= $h($t('cred_new_' . $kind . '_hint')) ?></span></a><?php endif ?>
<p class="sth-empty" data-studio-empty hidden><b><?= $h($t('studio_search_empty')) ?></b><?= $h($t('studio_search_empty_help')) ?></p>
</div>
<?php if ($base): ?><div class="sth-sec"><h2><?= $h($t('cred_origin_base')) ?></h2><p><?= $h($t('cred_base_help')) ?></p></div>
<div class="sth-grid" data-studio-grid><?php foreach ($base as $item) { $card($item); } ?><p class="sth-empty" data-studio-empty hidden><b><?= $h($t('studio_search_empty')) ?></b></p></div><?php endif ?>
<?php else: ?>
<div class="sth-grid" data-studio-grid>
<?php foreach ($shown as $item) { $card($item); } ?>
<?php if ($shown === []): ?><p class="sth-empty"><b><?= $h($t('cred_empty')) ?></b> <?= $h($t('cred_empty_help')) ?></p><?php endif ?>
<p class="sth-empty" data-studio-empty hidden><b><?= $h($t('studio_search_empty')) ?></b></p>
</div>
<?php endif ?>

<?php else: /* ============ Fiche ============ */
$e = $editing ?? null;
$d = $e['definition'] ?? [];
$old = is_array($oldForm ?? null) ? $oldForm : [];
$val = static fn (string $field, mixed $default = '') => array_key_exists($field, $old) ? $old[$field] : ($d[$field] ?? $default);
$isNew = $e === null;
$readOnly = !$canManage || !empty($e['archived']);
$lock = $readOnly ? ' disabled' : '';
$cond = $e['conditions'] ?? ['op' => 'all', 'courses' => array_values(array_filter([$prefillCourse ?? ''])), 'qualifications' => [], 'metrics' => []];
$settings = $e['settings'] ?? ['auto_enabled' => 1, 'auto_mode' => 'direct', 'exists' => false];
$metrics = array_values($cond['metrics']);
for ($i = count($metrics); $i < count($cond['metrics']) + \App\Services\Academy\AcademyStudioCredentials::METRIC_SLOTS; $i++) {
    $metrics[] = ['metric' => '', 'scope' => '', 'min' => '', 'window_days' => 0];
}
$otherQuals = array_values(array_filter($credentials, static fn (array $c): bool => empty($c['archived']) && (int) $c['id'] !== (int) ($e['id'] ?? 0)));
$title = $isNew ? $t('cred_new_' . $kind) : (string) $e['name'];
$checked = static fn (bool $on): string => $on ? ' checked' : '';
$kindCounts = null;
?>
<section class="sth-hero sth-hero--slim" style="--img:url('<?= $h($coverUrl($isBrevet ? 'hero' : 'night')) ?>')">
<div class="hin"><div><p class="kk"><i></i><a href="<?= $h($link('studio') . '?vue=' . ($isBrevet ? 'brevets' : 'qualifications')) ?>"><?= $h($t('kind_' . $kind . 's')) ?></a><?php if (!$isNew): ?> · <?= $h($t('cred_origin_' . $e['origin'])) ?><?php endif ?></p><h1><?= $h($title) ?></h1><?php if (!$isNew && $e['code'] !== ''): ?><p><code><?= $h($e['code']) ?></code><?php if (!empty($e['archived'])): ?> · <?= $h($t('cred_archived_pill')) ?><?php endif ?></p><?php endif ?></div>
<?php if (!$readOnly): ?><button class="hb1" type="submit" form="cred-form"><?= $h($t($isNew ? 'cred_create' : 'cred_save')) ?></button><?php endif ?></div>
</section>
<div class="sth-bar"><?php $kindCanCreate = false; require __DIR__ . '/studio_kinds.php'; ?></div>
<div class="cred-layout">
<form id="cred-form" class="cred-form" method="post" action="<?= $h($link('action/studio_cred_save')) ?>" enctype="multipart/form-data">
<?= $csrf ?><input type="hidden" name="course" value="academy"><input type="hidden" name="cred_kind" value="<?= $h($kind) ?>"><input type="hidden" name="cred_id" value="<?= (int) ($e['id'] ?? 0) ?>">
<fieldset class="ast-fs"<?= $lock ?>>
<section class="fc"><h3><?= $h($t('cred_identity')) ?></h3><p><?= $h($t('cred_identity_help')) ?></p><div class="fg">
<label class="fl full"><span class="lab"><?= $h($t('cred_name')) ?></span><input class="in" name="name" required maxlength="150" value="<?= $h($val('name')) ?>" placeholder="<?= $h($t('cred_name_ph')) ?>"></label>
<label class="fl"><span class="lab"><?= $h($t('cred_code')) ?></span><input class="in cred-mono" name="code" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{0,63}" value="<?= $h($val('code')) ?>"<?= $isNew ? '' : ' readonly' ?> autocomplete="off"><span class="h"><?= $h($t($isNew ? 'cred_code_help' : 'cred_code_locked')) ?></span></label>
<label class="fl"><span class="lab"><?= $h($t('cred_short')) ?></span><input class="in" name="short_name" maxlength="40" value="<?= $h($val('short_name')) ?>"><span class="h"><?= $h($t('cred_short_help')) ?></span></label>
<label class="fl full"><span class="lab"><?= $h($t('cred_description')) ?></span><textarea class="in" name="description" rows="3" maxlength="5000"><?= $h($val('description')) ?></textarea><span class="h"><?= $h($t('cred_description_help')) ?></span></label>
</div></section>

<section class="fc"><h3><?= $h($t('cred_classing')) ?></h3><p><?= $h($t('cred_classing_help')) ?></p><div class="fg">
<label class="fl"><span class="lab"><?= $h($t('cred_scope')) ?></span><select class="in" name="qualification_scope"><option value="global"><?= $h($t('cred_scope_global')) ?></option><option value="unit"<?= $val('qualification_scope') === 'unit' ? ' selected' : '' ?>><?= $h($t('cred_scope_unit')) ?></option></select></label>
<label class="fl"><span class="lab"><?= $h($t('cred_issuer')) ?></span><select class="in" name="issuer_id"><option value=""><?= $h($t('cred_issuer_none')) ?></option><?php foreach ($issuers ?? [] as $issuer): ?><option value="<?= (int) $issuer['id'] ?>"<?= (int) $val('issuer_id', $d['default_issuer_id'] ?? 0) === (int) $issuer['id'] ? ' selected' : '' ?>><?= $h($issuer['name'] ?? '') ?></option><?php endforeach ?></select><span class="h"><?= $h($t('cred_issuer_help')) ?></span></label>
<label class="fl"><span class="lab"><?= $h($t('cred_category')) ?></span><select class="in" name="category_id"><option value=""><?= $h($t('cred_category_none')) ?></option><?php foreach ($categories ?? [] as $category): ?><option value="<?= (int) $category['id'] ?>"<?= (int) $val('category_id', 0) === (int) $category['id'] ? ' selected' : '' ?>><?= $h($category['name'] ?? '') ?></option><?php endforeach ?></select></label>
<label class="fl"><span class="lab"><?= $h($t('cred_category_new')) ?></span><input class="in" name="category_new" maxlength="150" list="cred-catalog-categories" value="<?= $h($old['category_new'] ?? '') ?>" autocomplete="off"><span class="h"><?= $h($t('cred_category_new_help')) ?></span></label>
<datalist id="cred-catalog-categories"><?php foreach ($catalogCategories ?? [] as $label): ?><option value="<?= $h($label) ?>"></option><?php endforeach ?></datalist>
<label class="fl full cred-check"><input type="checkbox" name="uses_levels" value="1"<?= $checked(!empty($val('uses_levels', 0))) ?>><span><b><?= $h($t('cred_levels')) ?></b> · <?= $h($t('cred_levels_help')) ?></span></label>
</div></section>

<section class="fc"><h3><?= $h($t('cred_validity')) ?></h3><p><?= $h($t('cred_validity_help')) ?></p><div class="fg">
<label class="fl full cred-check"><input type="checkbox" name="is_permanent" value="1"<?= $checked($isNew ? $isBrevet : !empty($val('is_permanent', 0))) ?>><span><b><?= $h($t('cred_is_permanent')) ?></b> · <?= $h($t('cred_is_permanent_help')) ?></span></label>
<label class="fl"><span class="lab"><?= $h($t('cred_validity_months')) ?></span><input class="in" type="number" min="0" max="600" name="default_validity_months" value="<?= $h($val('default_validity_months')) ?>" placeholder="24"></label>
<label class="fl"><span class="lab"><?= $h($t('cred_alert_days')) ?></span><input class="in" type="number" min="0" max="365" name="alert_before_expiry_days" value="<?= $h($val('alert_before_expiry_days', '30')) ?>"></label>
<label class="fl"><span class="lab"><?= $h($t('cred_currency')) ?></span><input class="in" type="number" min="0" max="3650" name="currency_days" value="<?= $h($val('currency_days')) ?>" placeholder="90"></label>
<label class="fl cred-check"><input type="checkbox" name="renewal_required" value="1"<?= $checked(!empty($val('renewal_required', 0))) ?>><span><b><?= $h($t('cred_renewal')) ?></b> · <?= $h($t('cred_renewal_help')) ?></span></label>
<label class="fl full cred-check"><input type="checkbox" name="requires_exam" value="1"<?= $checked(!empty($val('requires_exam', 0))) ?>><span><b><?= $h($t('cred_exam')) ?></b> · <?= $h($t('cred_exam_help')) ?></span></label>
</div></section>

<?php if ($isBrevet): ?>
<section class="fc"><h3><?= $h($t('cred_brevet_section')) ?></h3><p><?= $h($t('cred_brevet_section_help')) ?></p><div class="fg">
<label class="fl"><span class="lab"><?= $h($t('cred_template')) ?></span><select class="in" name="certificate_template_id"><option value=""><?= $h($t('cred_template_default')) ?></option><?php foreach ($templates ?? [] as $tpl): ?><option value="<?= (int) $tpl['id'] ?>"<?= (int) $val('certificate_template_id', 0) === (int) $tpl['id'] ? ' selected' : '' ?>><?= $h($tpl['name'] ?? '') ?></option><?php endforeach ?></select></label>
<label class="fl"><span class="lab"><?= $h($t('cred_number_format')) ?></span><input class="in cred-mono" name="certificate_number_format" maxlength="120" value="<?= $h($val('certificate_number_format')) ?>" placeholder="<?= $h(\App\Support\QualificationExamples::DEFAULT_NUMBER_FORMAT) ?>"><span class="h"><?= $h($t('cred_number_help')) ?></span></label>
<label class="fl full"><span class="lab"><?= $h($t('cred_badge')) ?></span><input class="in" type="file" name="badge" accept="image/png,image/jpeg,image/webp"><span class="h"><?= $h($t('cred_badge_help')) ?></span></label>
</div></section>
<?php endif ?>

<section class="fc"><h3><?= $h($t('cred_conditions')) ?></h3><p><?= $h($t('cred_conditions_help')) ?></p>
<?php if (!$rulesReady): ?><p class="ast-notice ast-notice--info"><?= $h($t('cred_rules_pending_note')) ?></p>
<?php elseif (!empty($e['advanced'])): ?><p class="ast-notice ast-notice--info"><?= $h($t('cred_advanced')) ?><?php if (!empty($backOfficeUrl)): ?> <a href="<?= $h($backOfficeUrl) ?>"><?= $h($t('cred_back_office')) ?></a><?php endif ?></p>
<?php else: ?>
<input type="hidden" name="conditions[present]" value="1">
<div class="fg">
<label class="fl"><span class="lab"><?= $h($t('cred_op')) ?></span><select class="in" name="conditions[op]"><option value="all"><?= $h($t('cred_op_all')) ?></option><option value="any"<?= $cond['op'] === 'any' ? ' selected' : '' ?>><?= $h($t('cred_op_any')) ?></option></select></label>
<label class="fl"><span class="lab"><?= $h($t('cred_auto_mode')) ?></span><select class="in" name="auto_mode"><option value="direct"><?= $h($t('cred_auto_direct')) ?></option><option value="propose"<?= ($settings['auto_mode'] ?? '') === 'propose' ? ' selected' : '' ?>><?= $h($t('cred_auto_propose')) ?></option></select></label>
<label class="fl full cred-check"><input type="checkbox" name="auto_enabled" value="1"<?= $checked(empty($settings['exists']) || !empty($settings['auto_enabled'])) ?>><span><b><?= $h($t('cred_auto')) ?></b></span></label>
</div>
<fieldset class="cred-group"><legend><?= $h($t('cred_cond_courses')) ?></legend><p class="h"><?= $h($t('cred_cond_courses_help')) ?></p>
<div class="cred-picks"><?php foreach ($studioCourses as $courseKey => $c): if ($c['status'] === 'archived' && !in_array($courseKey, $cond['courses'], true)) { continue; } ?><label><input type="checkbox" name="conditions[courses][]" value="<?= $h($courseKey) ?>"<?= $checked(in_array($courseKey, $cond['courses'], true)) ?>><span><?= $h($courseLabel((string) $courseKey)) ?></span></label><?php endforeach ?></div></fieldset>
<fieldset class="cred-group"><legend><?= $h($t('cred_cond_quals')) ?></legend><p class="h"><?= $h($t('cred_cond_quals_help')) ?></p>
<div class="cred-picks"><?php foreach ($otherQuals as $q): ?><label><input type="checkbox" name="conditions[qualifications][]" value="<?= (int) $q['id'] ?>"<?= $checked(in_array((int) $q['id'], $cond['qualifications'], true)) ?>><span><?= $h($q['name']) ?><?php if ($q['kind'] === 'brevet'): ?> · <?= $h($t('kind_brevet')) ?><?php endif ?></span></label><?php endforeach ?><?php if ($otherQuals === []): ?><span class="h">—</span><?php endif ?></div></fieldset>
<fieldset class="cred-group"><legend><?= $h($t('cred_cond_metrics')) ?></legend><p class="h"><?= $h($t('cred_cond_metrics_help')) ?></p>
<?php foreach ($metrics as $mi => $m): ?><div class="cred-metric">
<label class="fl"><span class="lab"><?= $h($t('cred_metric')) ?></span><select class="in" name="conditions[metrics][<?= $mi ?>][metric]"><option value=""><?= $h($t('cred_metric_none')) ?></option><?php foreach ($metricOptions ?? [] as $metricKey => $metricLabel): ?><option value="<?= $h($metricKey) ?>"<?= ($m['metric'] ?? '') === $metricKey ? ' selected' : '' ?>><?= $h($metricLabel) ?></option><?php endforeach ?></select></label>
<label class="fl"><span class="lab"><?= $h($t('cred_metric_scope')) ?></span><select class="in" name="conditions[metrics][<?= $mi ?>][scope]"><?php $group = null; foreach ($aircraftOptions ?? [] as [$scopeValue, $scopeLabel, $scopeGroup]): if ($scopeGroup !== $group) { echo $group !== null && $group !== '' ? '</optgroup>' : ''; echo $scopeGroup !== '' ? '<optgroup label="' . $h($scopeGroup) . '">' : ''; $group = $scopeGroup; } ?><option value="<?= $h($scopeValue) ?>"<?= (string) ($m['scope'] ?? '') === (string) $scopeValue ? ' selected' : '' ?>><?= $h($scopeLabel) ?></option><?php endforeach; echo $group !== null && $group !== '' ? '</optgroup>' : ''; ?><?php if (($m['scope'] ?? '') !== '' && !in_array($m['scope'], array_column($aircraftOptions ?? [], 0), true)): ?><option value="<?= $h($m['scope']) ?>" selected><?= $h($m['scope']) ?></option><?php endif ?></select></label>
<label class="fl"><span class="lab"><?= $h($t('cred_metric_min')) ?></span><input class="in" type="number" min="0" step="0.5" name="conditions[metrics][<?= $mi ?>][min]" value="<?= $h(isset($m['min']) && $m['min'] !== '' ? \App\Support\QualificationCriteriaCatalog::formatNumber((float) $m['min']) : '') ?>"></label>
<input type="hidden" name="conditions[metrics][<?= $mi ?>][window_days]" value="<?= (int) ($m['window_days'] ?? 0) ?>">
</div><?php endforeach ?>
</fieldset>
<?php if (!empty($cond['attestations'])): $criterionNames = is_array($criterionNames ?? null) ? $criterionNames : []; ?>
<fieldset class="cred-group"><legend><?= $h($t('cred_cond_attest')) ?></legend><p class="h"><?= $h($t('cred_cond_attest_help')) ?></p>
<div class="cred-picks"><?php foreach (array_values($cond['attestations']) as $ai => $criterion): ?><label><input type="checkbox" name="conditions[attestations][<?= (int) $ai ?>][keep]" value="1" checked><input type="hidden" name="conditions[attestations][<?= (int) $ai ?>][key]" value="<?= $h($criterion['key'] ?? 'other') ?>"><input type="hidden" name="conditions[attestations][<?= (int) $ai ?>][label]" value="<?= $h($criterion['label'] ?? '') ?>"><input type="hidden" name="conditions[attestations][<?= (int) $ai ?>][min]" value="<?= $h((string) ($criterion['min'] ?? 0)) ?>"><span><?php require base_path('views/partials/qualification_criterion.php'); ?></span></label><?php endforeach ?></div></fieldset>
<?php endif ?>
<?php endif ?>
</section>
</fieldset>
<?php if (!$readOnly): ?><div class="cred-submit"><a class="btn" href="<?= $h($link('studio') . '?vue=' . ($isBrevet ? 'brevets' : 'qualifications')) ?>"><?= $h($t('cancel')) ?></a><button class="btn dark"><?= $h($t($isNew ? 'cred_create' : 'cred_save')) ?></button></div><?php endif ?>
</form>

<aside class="cred-side">
<section class="fc cred-preview"><h3><?= $h($t('cred_summary')) ?></h3>
<div class="cred-head cred-head--big"><?php if (!empty($e['badge'])): ?><img class="cred-badge" src="<?= $h($e['badge']) ?>" alt="<?= $h($t('cred_badge_current')) ?>"><?php else: ?><span class="cred-badge cred-badge--crest"><?= $h($crestOf((string) ($e['short'] ?? '') ?: $title)) ?></span><?php endif ?>
<span><b><?= $h($title) ?></b><small><?= $h($t('kind_' . $kind)) ?><?php if (!$isNew): ?> · <?= $h($e['permanent'] ? $t('cred_permanent') : sprintf($t('cred_months'), $e['months'])) ?> · <?= $h(sprintf($t('cred_holders'), $e['holders'])) ?><?php endif ?></small></span></div>
</section>
<?php $detectedList = is_array($detected ?? null) ? $detected : []; if (!$isNew && !$readOnly && $rulesReady && isset($detected)): $criterionNames = is_array($criterionNames ?? null) ? $criterionNames : []; ?>
<section class="fc cred-detected" id="prerequis-detectes"><h3><?= $h($t('cred_detected')) ?><?php if ($detectedList !== []): ?> <span class="pill"><i></i><?= count($detectedList) ?></span><?php endif ?></h3><p><?= $h($t('cred_detected_help')) ?></p>
<?php if ($detectedList === []): ?><p class="h"><?= $h($t('cred_detected_none')) ?></p>
<?php else: ?><form method="post" action="<?= $h($link('action/studio_cred_prereqs')) ?>"><?= $csrf ?><input type="hidden" name="course" value="academy"><input type="hidden" name="cred_kind" value="<?= $h($kind) ?>"><input type="hidden" name="cred_id" value="<?= (int) $e['id'] ?>">
<ul class="cred-detect"><?php foreach ($detectedList as $s): $sid = (string) $s['id']; ?><li class="cred-detect__i cred-detect__i--<?= $h($s['level']) ?>">
<label class="cred-check"><input type="checkbox" name="pick[]" value="<?= $h($sid) ?>"<?= !empty($s['checked']) ? ' checked' : '' ?><?= empty($s['available']) ? ' disabled' : '' ?>><span><?php if ($s['kind'] === 'criterion'): $criterion = $s['criterion']; require base_path('views/partials/qualification_criterion.php'); elseif ($s['kind'] === 'grade'): ?><b>Grade minimum</b> <em translate="no"><?= $h($s['target'] ?? '') ?></em><?php else: ?><b>Qualification à détenir</b> <em translate="no"><?= $h($s['target'] ?? '') ?></em><?php endif ?></span></label>
<?php if ($s['value'] !== null): ?><input class="in" type="number" min="0" step="any" name="value[<?= $h($sid) ?>]" value="<?= $h(\App\Support\QualificationCriteriaCatalog::formatNumber((float) $s['value'])) ?>" aria-label="<?= $h($s['unit']) ?>"<?= empty($s['available']) ? ' disabled' : '' ?>><?php endif ?>
<?php if (trim((string) $s['source']) !== ''): ?><small class="h">« <span translate="no"><?= $h(mb_strimwidth((string) $s['source'], 0, 220, '…')) ?></span> »</small><?php endif ?>
<?php if (trim((string) ($s['note'] ?? '')) !== ''): ?><small class="h"><?= $h($s['note']) ?></small><?php endif ?>
</li><?php endforeach ?></ul>
<button class="btn dark"><?= $h($t('cred_detected_add')) ?></button></form><?php endif ?>
</section>
<?php endif ?>
<section class="fc"><h3><?= $h($t('cred_courses_title')) ?></h3><p><?= $h($t('cred_courses_help')) ?></p>
<?php if ($isNew): ?><p class="h"><?= $h($t('cred_save_first')) ?></p>
<?php else: ?>
<ul class="cred-courses"><?php foreach ($e['courses'] as $courseKey): ?><li><a href="<?= $h($link('studio') . '?course=' . rawurlencode($courseKey)) ?>"><?= $h($courseLabel($courseKey)) ?></a>
<?php if (!$readOnly && $rulesReady && empty($e['advanced'])): ?><form method="post" action="<?= $h($link('action/studio_cred_unlink')) ?>"><?= $csrf ?><input type="hidden" name="course" value="academy"><input type="hidden" name="cred_id" value="<?= (int) $e['id'] ?>"><input type="hidden" name="link_course" value="<?= $h($courseKey) ?>"><button class="ib dg" title="<?= $h($t('cred_unlink')) ?>"><svg class="i"><use href="#ai-x"/></svg><span class="sr-only"><?= $h($t('cred_unlink')) ?></span></button></form><?php endif ?></li>
<?php endforeach ?><?php if ($e['courses'] === []): ?><li class="h"><?= $h($t('cred_no_course')) ?></li><?php endif ?></ul>
<?php $linkable = array_diff_key($studioCourses, array_flip($e['courses'])); ?>
<?php if (!$readOnly && $rulesReady): ?><form class="cred-link" method="post" action="<?= $h($link('action/studio_cred_link')) ?>"><?= $csrf ?><input type="hidden" name="course" value="academy"><input type="hidden" name="cred_id" value="<?= (int) $e['id'] ?>">
<label class="fl"><span class="lab"><?= $h($t('cred_link_course')) ?></span><select class="in" name="link_course" required><?php foreach ($linkable as $courseKey => $c): if ($c['status'] === 'archived') { continue; } ?><option value="<?= $h($courseKey) ?>"><?= $h($courseLabel((string) $courseKey)) ?></option><?php endforeach ?></select></label>
<button class="btn"<?= $linkable === [] ? ' disabled' : '' ?>><?= $h($t('cred_link')) ?></button></form>
<a class="btn" href="<?= $h($link('studio') . '?nouveau-cours=1') ?>">+ <?= $h($t('cred_create_course_for')) ?></a><?php endif ?>
<?php endif ?>
</section>
<?php if (!empty($backOfficeUrl)): ?><section class="fc"><h3><?= $h($t('cred_back_office')) ?></h3><p><?= $h($t('cred_back_office_help')) ?></p><a class="btn" href="<?= $h($backOfficeUrl) ?>"><?= $h($t('cred_back_office')) ?></a></section><?php endif ?>
<?php if (!$isNew && $canArchive && empty($e['archived'])): ?><section class="fc cred-danger"><h3><?= $h($t('danger_zone')) ?></h3><p><?= $h($t('cred_archive_help')) ?></p>
<details class="cred-confirm"><summary class="btn"><?= $h($t('cred_archive')) ?>…</summary><form method="post" action="<?= $h($link('action/studio_cred_archive')) ?>"><?= $csrf ?><input type="hidden" name="course" value="academy"><input type="hidden" name="cred_kind" value="<?= $h($kind) ?>"><input type="hidden" name="cred_id" value="<?= (int) $e['id'] ?>"><p class="h"><?= $h($t('cred_archive_confirm')) ?></p><button class="btn red"><?= $h($t('cred_archive')) ?></button></form></details></section><?php endif ?>
</aside>
</div>
<?php endif ?>
</main>
<?php require __DIR__ . '/disclaimer.php'; ?>
</body></html>
