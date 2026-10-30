<?php
$accessState = $accessState ?? 'guest';
$account = $account ?? null;
$tenant = $tenant ?? null;
$legacyCourses = $legacyCourses ?? [];
$legacyEnrollments = $legacyEnrollments ?? [];
$staff = $staff ?? false;
$hasAccess = $accessState === 'active';
$academyCourses = $academyCourses ?? ($hasAccess ? array_values(\App\Services\Academy\AcademyBuiltInCourses::all()) : []);
$academyStates = $academyStates ?? [];
$academyStorageReady = $academyStorageReady ?? true;
$academyLearning = array_filter($academyStates, static fn (string $key): bool => $key !== '__notes', ARRAY_FILTER_USE_KEY);
$primaryHref = match ($accessState) {
    'active' => $link('dashboard'),
    'no_tenant' => url('communities'),
    'unpaid' => url('platform/upgrade?from=academy'),
    'unavailable' => $link(),
    default => url('login?redirect=academy'),
};
$primaryLabel = match ($accessState) {
    'active' => 'enter', 'no_tenant'=>'choose_community', 'unpaid'=>'offers', 'unavailable'=>'reload', default=>'login',
};
$resume = null;
foreach ($legacyEnrollments as $enrollment) {
    if (in_array($enrollment['status'] ?? '', ['assigned','in_progress'], true)) { $resume = $enrollment; break; }
}
if ($hasAccess && $resume) { $primaryHref = url('formations/' . rawurlencode($resume['course_slug'])); $primaryLabel = 'continue'; }
$academyResume = null;
foreach ($academyLearning as $courseKey => $learnerState) {
    if (empty($learnerState['completed'])) {
        $academyResume = $learnerState;
        $primaryHref = $link('classroom') . '?course=' . rawurlencode($courseKey);
        $primaryLabel = 'continue';
        break;
    }
}
?>
<div id="main" class="academy-content academy-public academy-live">
<section class="academy-full-hero" aria-labelledby="academy-title">
<img class="academy-full-hero__image" src="<?= $h(asset_url('assets/images/academy-hero.jpg')) ?>" alt="" fetchpriority="high" decoding="async" width="1818" height="1256">
<div class="academy-full-hero__shade"></div>
<div class="academy-full-hero__content wrap">
<p class="eyebrow">ATHENA / <?= $h($t('tagline')) ?></p>
<h1 id="academy-title" class="hi-display">ATHENA<br><span>ACADEMY.</span></h1>
<p class="academy-hero-intro"><?= $h($t('live_hero')) ?></p>
<div class="buttons"><a class="button" href="<?= $h($primaryHref) ?>"><?= $h($t($primaryLabel)) ?> ↗</a><a class="text-link" href="#academy-workspace"><?= $h($t('your_workspace')) ?> ↓</a></div>
<div class="academy-identity" data-access-state="<?= $h($accessState) ?>">
<span class="academy-identity__state"><i aria-hidden="true"></i><?= $h($t('access_' . $accessState)) ?></span>
<?php if ($account): ?><span><?= $h(($account['display_name'] ?? '') ?: ($account['callsign'] ?? ('#' . (int)$account['id']))) ?></span><?php endif ?>
<?php if ($tenant && ($tenant['slug']??'') !== 'default'): ?><span><?= $h($tenant['name']??$tenant['slug']) ?></span><span><?= $h(strtoupper(\App\Services\Academy\AcademyAccess::planSlug($tenant))) ?></span><?php endif ?>
<span>FR / EN</span></div>
</div>
<div class="academy-hero-foot wrap"><span><?= $h($t('prerelease')) ?></span><span><?= $h($t('not_official')) ?></span><button type="button" data-open-disclaimer><?= $h($t('read_disclaimer')) ?> ↗</button></div>
</section>
<section id="academy-workspace" class="section wrap academy-editorial">
<div class="academy-editorial__intro"><p class="eyebrow"><?= $h($t('your_workspace')) ?></p><h2><?= $h($t($hasAccess ? 'workspace_ready' : 'access_heading')) ?></h2><p><?= $h($t('context_' . $accessState)) ?></p><?php if (!$hasAccess): ?><a class="button" href="<?= $h($primaryHref) ?>"><?= $h($t($primaryLabel)) ?> ↗</a><?php endif ?></div>
<div class="academy-editorial__body">
<?php if ($hasAccess): ?>
<div class="academy-live-stats"><span><strong><?= count($legacyCourses)+count($academyCourses) ?></strong><?= $h($t('published_courses')) ?></span><span><strong><?= count($legacyEnrollments)+count($academyLearning) ?></strong><?= $h($t('enrolled')) ?></span><span><strong><?= count(array_filter($legacyEnrollments,static fn(array $e):bool=>($e['status']??'')==='completed'))+count(array_filter($academyLearning,static fn(array $e):bool=>!empty($e['completed']))) ?></strong><?= $h($t('completed')) ?></span></div>
<?php if ($resume): ?><div class="academy-resume"><p class="eyebrow"><?= $h($t('continue')) ?></p><h3><?= $h($resume['course_title']) ?></h3><div class="progress-label"><span><?= $h($t('progress')) ?></span><b><?= (int)$resume['progress_percent'] ?> %</b></div><progress max="100" value="<?= (int)$resume['progress_percent'] ?>"></progress><a class="text-link" href="<?= $h(url('formations/' . rawurlencode($resume['course_slug']))) ?>"><?= $h($t('continue')) ?> →</a></div><?php endif ?>
<?php require __DIR__ . '/lms_tools.php'; ?>
<?php else: ?><ol class="academy-access-path"><li><span>01</span><div><h3><?= $h($t('account_step')) ?></h3><p><?= $h($t('account_step_text')) ?></p></div></li><li><span>02</span><div><h3><?= $h($t('tenant_step')) ?></h3><p><?= $h($t('tenant_step_text')) ?></p></div></li><li><span>03</span><div><h3><?= $h($t('training_step')) ?></h3><p><?= $h($t('training_step_text')) ?></p></div></li></ol><?php endif ?>
</div></section>
<?php if ($hasAccess): ?><section class="section wrap academy-real-catalogue" id="program"><div class="section-heading"><div><p class="eyebrow"><?= $h($t('catalogue')) ?></p><h2><?= $h($t('your_community_courses')) ?></h2></div><a class="text-link" href="<?= $h($link('catalogue')) ?>"><?= $h($t('all_courses')) ?> ↗</a></div>
<?php if ($academyCourses): ?><ol class="academy-course-list"><?php foreach ($academyCourses as $index=>$course): $courseKey=$course['key']; $learnerState=$academyLearning[$courseKey]??null; ?><li data-course-row><span class="academy-course-number"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><div class="academy-course-copy"><p class="eyebrow"><?= $h($t(!empty($course['built_in'])?'academy_module':'program')) ?></p><a href="<?= $h($link('classroom').'?course='.rawurlencode($courseKey)) ?>"><h3><?= $h($local($course['title'])) ?></h3></a><p><?= $h($local($course['summary'])) ?></p><span class="academy-course-meta"><?= $h($t('seven_stage_course')) ?><?php if(!empty($course['duration_minutes'])): ?> · <?= (int)$course['duration_minutes'] ?> min<?php endif ?><?php if(!empty($course['version'])): ?> · <?= $h($t('course_version')) ?> <?= $h($course['version']) ?><?php endif ?></span><?php if(!empty($course['built_in'])): ?><p class="muted"><?= $h($t('simulation_license')) ?></p><?php endif ?></div><?php if($learnerState): ?><a class="text-link" href="<?= $h($link('classroom').'?course='.rawurlencode($courseKey)) ?>"><?= $h($t(!empty($learnerState['completed'])?'completed':'continue')) ?> ↗</a><?php else: ?><form method="post" action="<?= $h($link('action/enroll')) ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="course" value="<?= $h($courseKey) ?>"><button class="button small" <?= !$academyStorageReady?'disabled':'' ?>><?= $h($t('enroll')) ?> ↗</button></form><?php endif ?></li><?php endforeach ?></ol><?php if(!$academyStorageReady): ?><p class="notice"><?= $h($t('lms_storage_notice')) ?></p><?php endif ?><?php endif ?>
<?php if (!$legacyCourses && !$academyCourses): ?><p class="academy-empty-line"><?= $h($t('no_published_courses')) ?></p><?php elseif($legacyCourses): ?><ol class="academy-course-list"><?php foreach (array_slice($legacyCourses,0,6) as $index=>$course): ?><li><span class="academy-course-number"><?= str_pad((string)($index+count($academyCourses)+1),2,'0',STR_PAD_LEFT) ?></span><a href="<?= $h(url('formations/' . rawurlencode($course['slug']))) ?>"><h3><?= $h($course['title']) ?></h3><p><?= $h(strip_tags($course['short_description']??'')) ?></p></a><span class="academy-course-detail"><?= (int)($course['estimated_minutes']??0) ?> min</span><a class="text-link" href="<?= $h(url('formations/' . rawurlencode($course['slug']))) ?>" aria-label="<?= $h($t('open').' · '.$course['title']) ?>">↗</a></li><?php endforeach ?></ol><?php endif ?></section><?php endif ?>
<section id="method" class="method-section"><div class="wrap section"><p class="eyebrow"><?= $h($t('method_eyebrow')) ?></p><h2><?= $h($t('method_heading')) ?></h2><p class="section-intro"><?= $h($t('method_intro')) ?></p><ol class="method-steps"><?php foreach (\App\Services\Academy\AcademyCurriculum::STAGES as $i=>$step): ?><li><span>0<?= $i+1 ?></span><strong><?= $h($t($step)) ?></strong><i aria-hidden="true">→</i></li><?php endforeach ?></ol></div></section>
<?php require __DIR__ . '/disclaimer.php'; ?>
</div>
