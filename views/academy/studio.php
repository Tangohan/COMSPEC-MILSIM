<?php
/**
 * Academy Studio (maquettes « Studio Accueil » et « Studio v3 »).
 * Accueil : formations de la communauté en cartes (couverture, état, préparation), filtres, archivées, corbeille.
 * Éditeur : structure à gauche, étape au centre, réglages à droite ; une langue à la fois (FR / EN), un seul
 * formulaire (chaque action enregistre d'abord le brouillon). Partage, archivage et suppression : fenêtres dédiées.
 */
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyStudio;

require __DIR__ . '/init.php';
$customTopbar = true;
require __DIR__ . '/header.php';
require __DIR__ . '/icons.php';
$stages = AcademyCurriculum::STAGES;
$csrf = \App\Core\Csrf::field();
$role = $role ?? 'owner';
$staff = (bool) ($staff ?? false);
/* Créer un cours et régler son partage : droits Academy distincts (écran « Droits d'accès »), par défaut ceux de l'encadrement. */
$canCreate = (bool) ($canCreate ?? $staff);
$canShare = (bool) ($canShare ?? $staff);
$sharing = $sharing ?? null;
$sharingReady = (bool) ($sharingReady ?? false);
$lifecycleReady = (bool) ($lifecycleReady ?? false);
$sharedWithMe = $sharedWithMe ?? [];
$builtInCourses = $builtInCourses ?? [];
$warnNotices = ['studio_not_ready', 'studio_delete_has_learners', 'studio_delete_confirm', 'studio_lifecycle_pending', 'share_invalid_member', 'share_invalid_link', 'locked', 'not_found'];
$noticeClass = static fn (string $notice): string => in_array($notice, $warnNotices, true) || (str_starts_with($notice, 'slides_') && !in_array($notice, ['slides_imported', 'slides_updated'], true)) ? 'warn' : 'ok';
$coverUrl = static fn (?string $cover): string => asset_url('assets/images/academy/cover-' . (in_array($cover, AcademyStudio::COVERS, true) ? $cover : 'night') . '.webp');
$crestOf = static function (string $title): string {
    $words = preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: ['A'];
    return mb_strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1)));
};
$ago = static function (?string $date) use ($t): string {
    $time = $date !== null ? strtotime($date) : false;
    if ($time === false) {
        return '';
    }
    $minutes = max(0, intdiv(time() - $time, 60));
    return match (true) {
        $minutes < 1 => $t('ago_now'),
        $minutes < 60 => sprintf($t('ago_minutes'), $minutes),
        $minutes < 1440 => sprintf($t('ago_hours'), intdiv($minutes, 60)),
        $minutes < 10080 => sprintf($t('ago_days'), max(1, intdiv($minutes, 1440))),
        default => sprintf($t('ago_date'), date('d/m/Y', $time)),
    };
};
$langSwitch = static function () use ($h, $lang, $t): void { ?>
<nav class="lang" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr', 'en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav>
<?php };
?>
<?php if ($course === null): /* ============ Accueil du Studio ============ */
$active = array_values(array_filter($studioCourses, static fn (array $c): bool => empty($c['archived']) && empty($c['deleted_at'])));
$archived = array_values(array_filter($studioCourses, static fn (array $c): bool => !empty($c['archived']) && empty($c['deleted_at'])));
$trashed = array_values(array_filter($studioCourses, static fn (array $c): bool => !empty($c['deleted_at'])));
$published = count(array_filter($active, static fn (array $c): bool => !empty($c['published'])));
$chapterTotal = array_sum(array_map(static fn (array $c): int => count($c['chapters'] ?? []), $active));
$view = in_array($studioView ?? '', ['archives', 'corbeille'], true) && $staff ? $studioView : 'actives';
$card = static function (array $c, string $mode) use ($h, $t, $local, $link, $csrf, $coverUrl, $crestOf, $ago, $stages, $staff, $sharedWithMe): void {
    $builtIn = $mode === 'builtin';
    $title = $local($c['title']) ?: $t('untitled_course');
    $status = $builtIn ? 'builtin' : (!empty($c['deleted_at']) ? 'trash' : (!empty($c['archived']) ? 'archived' : (!empty($c['published']) ? 'pub' : (isset($c['replaces']) ? 'version' : 'draft'))));
    $chapters = $c['chapters'] ?? [];
    $issues = AcademyStudio::issues($c);
    $byChapter = [];
    foreach ($issues as $issue) {
        $byChapter[$issue['chapter'] ?? -1] = true;
    }
    $editUrl = $link('studio') . '?course=' . rawurlencode($c['key']);
    $sharedRole = $sharedWithMe[$c['key']] ?? null;
    $openUrl = $sharedRole === 'viewer' ? $link('classroom') . '?course=' . rawurlencode($c['key']) . '&preview=1' : $editUrl;
    $updated = isset($c['updated_at']) ? strtotime((string) $c['updated_at']) : false; ?>
<article class="sth-card" data-course-card data-status="<?= $status ?>" data-title="<?= $h(mb_strtolower($title . ' ' . $local($c['summary']))) ?>" data-updated="<?= (int) $updated ?>" data-chapters="<?= count($chapters) ?>">
<?php if ($builtIn): ?><div class="cv" style="--img:url('<?= $h($coverUrl($c['cover'] ?? 'night')) ?>')"><?php else: ?><a class="cv" href="<?= $h($openUrl) ?>" style="--img:url('<?= $h($coverUrl($c['cover'] ?? null)) ?>')" tabindex="-1" aria-hidden="true"><?php endif ?>
<span class="pill p-<?= $status ?>"><i></i><?= $h($t('status_' . $status)) ?></span>
<span class="cr"><?= $h($crestOf($title)) ?></span>
<span class="lv"><?= count($chapters) ?> <?= $h(mb_strtolower($t(count($chapters) === 1 ? 'chapter' : 'chapters'))) ?> · <?= count($chapters) * count($stages) ?> <?= $h($t('steps_word')) ?><?php if (!empty($c['duration_minutes'])): ?> · <?= (int) $c['duration_minutes'] ?> min<?php endif ?></span>
<?= $builtIn ? '</div>' : '</a>' ?>
<div class="cb"><h3><?php if ($builtIn): ?><?= $h($title) ?><?php else: ?><a href="<?= $h($openUrl) ?>"><?= $h($title) ?></a><?php endif ?></h3>
<p><?= $local($c['summary']) !== '' ? $h($local($c['summary'])) : '<i>' . $h($t('no_summary')) . '</i>' ?></p>
<div class="ready" title="<?= $h($t('chapter_readiness')) ?>"><?php foreach ($chapters as $ci => $unused): ?><i class="<?= isset($byChapter[$ci]) ? 'todo' : 'ok' ?>"></i><?php endforeach ?></div>
<div class="mt"><?php if ($builtIn): ?><span><?= $h($t('built_in_full')) ?></span><?php elseif ($issues): ?><span class="todo"><?= count($issues) ?> <?= $h($t(count($issues) === 1 ? 'studio_issue' : 'studio_issues')) ?></span><?php else: ?><span class="ok">✓ <?= $h($t('studio_ready')) ?></span><?php endif ?>
<?php if (!empty($c['learners'])): ?><span><?= (int) $c['learners'] ?> <?= $h($t((int) $c['learners'] === 1 ? 'learner_one' : 'learner_many')) ?></span><?php endif ?>
<?php if ($sharedRole): ?><span><?= $h($t('shared_as_' . $sharedRole)) ?></span><?php endif ?></div></div>
<div class="cf"><small><?= $builtIn ? 'Athena' : ($status === 'trash' ? $h(sprintf($t('trash_until'), date('d/m/Y', (int) strtotime((string) $c['deleted_at']) + \App\Repositories\AcademyRepository::TRASH_DAYS * 86400))) : $h($ago($c['updated_at'] ?? null))) ?></small>
<?php if ($builtIn): ?><form method="post" action="<?= $h($link('action/studio_duplicate')) ?>"><?= $csrf ?><input type="hidden" name="course" value="<?= $h($c['key']) ?>"><button class="btn"><?= $h($t('studio_copy_builtin')) ?></button></form>
<?php elseif ($status === 'trash' || $status === 'archived'): ?><form method="post" action="<?= $h($link('action/' . ($status === 'trash' ? 'studio_trash_restore' : 'studio_unarchive'))) ?>"><?= $csrf ?><input type="hidden" name="course" value="<?= $h($c['key']) ?>"><button class="btn dark"><?= $h($t('restore')) ?></button></form>
<?php else: ?>
<?php if ($staff): ?><details class="sth-menu"><summary class="mi" title="<?= $h($t('more_actions')) ?>"><span aria-hidden="true">⋯</span><span class="sr-only"><?= $h($t('more_actions')) ?> · <?= $h($title) ?></span></summary><div class="menu">
<a href="<?= $h($link('classroom') . '?course=' . rawurlencode($c['key']) . '&preview=1') ?>"><?= $h($t('learner_preview')) ?></a>
<a href="<?= $h($editUrl . '&share=1') ?>"><?= $h($t('share')) ?></a>
<form method="post" action="<?= $h($link('action/studio_duplicate')) ?>"><?= $csrf ?><input type="hidden" name="course" value="<?= $h($c['key']) ?>"><button><?= $h($t('duplicate')) ?></button></form>
<?php if ($status === 'pub'): ?><form method="post" action="<?= $h($link('action/studio_revise')) ?>"><?= $csrf ?><input type="hidden" name="course" value="<?= $h($c['key']) ?>"><button><?= $h($t('studio_revise')) ?></button></form><?php endif ?>
<hr><form method="post" action="<?= $h($link('action/studio_archive')) ?>"><?= $csrf ?><input type="hidden" name="course" value="<?= $h($c['key']) ?>"><button><?= $h($t('archive')) ?></button></form>
<?php if (empty($c['learners'])): ?><details class="del"><summary><?= $h($t('delete')) ?>…</summary><form method="post" action="<?= $h($link('action/studio_delete')) ?>"><?= $csrf ?><input type="hidden" name="course" value="<?= $h($c['key']) ?>"><label><?= $h(sprintf($t('delete_type_name'), $local($c['title']))) ?><input class="in" name="confirm_title" required autocomplete="off"></label><button class="btn red"><?= $h($t('delete_to_trash')) ?></button></form></details>
<?php else: ?><p class="menu-note"><?= $h($t('delete_has_learners_short')) ?></p><?php endif ?>
</div></details><?php endif ?>
<a class="btn dark" href="<?= $h($openUrl) ?>"><?= $h($t($sharedRole === 'viewer' ? 'studio_preview' : ($status === 'pub' ? 'studio_open' : 'studio_edit'))) ?></a>
<?php endif ?></div>
</article>
<?php };
?>
<header class="ast-top">
<a class="tb icon" href="<?= $h($link($staff || $canCreate ? 'instructor' : 'dashboard')) ?>" title="<?= $h($t('back_academy')) ?>"><svg class="i"><use href="#ai-back"/></svg><span class="sr-only"><?= $h($t('back_academy')) ?></span></a>
<span class="wm">ACADEMY <span>STUDIO</span></span>
<span class="sp"></span>
<a class="tb" href="<?= $h($link('classroom')) ?>"><?= $h($t('classroom')) ?></a>
<?php $langSwitch(); ?>
</header>
<main id="main" class="sth">
<?php if ($notice): ?><p class="ast-notice ast-notice--<?= $noticeClass($notice) ?>" role="status"><?= $h($t($notice)) ?></p><?php endif ?>
<section class="sth-hero" style="--img:url('<?= $h($coverUrl('fog')) ?>')">
<div class="hin"><div><p class="kk"><i></i>Academy<?= !empty($tenant['name']) ? ' · ' . $h($tenant['name']) : '' ?></p><h1>Studio</h1><p><?= $h($t('studio_intro')) ?></p><p><a href="<?= $h(url('documentation/qualifications')) ?>" data-qualification-guides><?= $h($t('studio_how')) ?></a></p></div>
<?php if ($canCreate): ?><button class="hb1" type="button" data-open-dialog="sth-pick">+ <?= $h($t('studio_new')) ?></button><?php endif ?></div>
<dl class="hst"><div><dt><?= $h($t('stat_courses')) ?></dt><dd><?= count($active) ?></dd></div><div><dt><?= $h($t('stat_published')) ?></dt><dd class="ok"><?= $published ?></dd></div><div><dt><?= $h($t('stat_drafts')) ?></dt><dd class="dr"><?= count($active) - $published ?></dd></div><div><dt><?= $h($t('stat_chapters')) ?></dt><dd><?= $chapterTotal ?></dd></div></dl>
</section>
<div class="sth-bar">
<?php $kindActive = 'course'; $kindCounts = ['course' => count($active)] + ($credentialCounts ?? []); $kindCanCreate = $canCreate; $kindCourseDialog = $canCreate; require __DIR__ . '/studio_kinds.php'; ?>
<nav class="sth-tabs" aria-label="<?= $h($t('studio_filter')) ?>">
<?php if ($view === 'actives'): ?><button type="button" class="on" aria-pressed="true" data-filter="all"><?= $h($t('filter_all')) ?><em><?= count($active) ?></em></button><button type="button" aria-pressed="false" data-filter="draft"><?= $h($t('filter_drafts')) ?><em><?= count($active) - $published ?></em></button><button type="button" aria-pressed="false" data-filter="pub"><?= $h($t('filter_published')) ?><em><?= $published ?></em></button>
<?php else: ?><a href="<?= $h($link('studio')) ?>"><?= $h($t('filter_all')) ?><em><?= count($active) ?></em></a><?php endif ?>
<?php if ($staff && $lifecycleReady): ?><a href="<?= $h($link('studio') . '?vue=archives') ?>"<?= $view === 'archives' ? ' class="on" aria-current="page"' : '' ?>><?= $h($t('filter_archived')) ?><em><?= count($archived) ?></em></a><a href="<?= $h($link('studio') . '?vue=corbeille') ?>"<?= $view === 'corbeille' ? ' class="on" aria-current="page"' : '' ?>><?= $h($t('filter_trash')) ?><em><?= count($trashed) ?></em></a><?php endif ?>
</nav>
<label class="sth-search"><svg class="i"><use href="#ai-search"/></svg><input type="search" data-studio-search placeholder="<?= $h($t('studio_search')) ?>" aria-label="<?= $h($t('studio_search')) ?>"></label>
<span class="sp"></span>
<select class="sth-sel" data-studio-sort aria-label="<?= $h($t('studio_sort')) ?>"><option value="updated"><?= $h($t('sort_updated')) ?></option><option value="title"><?= $h($t('sort_title')) ?></option><option value="chapters"><?= $h($t('sort_chapters')) ?></option></select>
</div>
<?php if ($view === 'archives'): ?><p class="sth-hint"><?= $h($t('archived_help')) ?></p><?php elseif ($view === 'corbeille'): ?><p class="sth-hint"><?= $h(sprintf($t('trash_help'), \App\Repositories\AcademyRepository::TRASH_DAYS)) ?></p><?php endif ?>
<div class="sth-grid" data-studio-grid>
<?php foreach (['actives' => $active, 'archives' => $archived, 'corbeille' => $trashed][$view] as $item) { $card($item, $view); } ?>
<?php if ($view === 'actives' && $canCreate): ?><button type="button" class="sth-new" data-open-dialog="sth-new" data-new-card><span class="pl">+</span><b><?= $h($t('studio_new_course')) ?></b><span><?= $h($t('studio_new_hint')) ?></span></button><?php endif ?>
<?php if ($view !== 'actives' && ['archives' => $archived, 'corbeille' => $trashed][$view] === []): ?><p class="sth-empty"><b><?= $h($t($view === 'archives' ? 'archived_empty' : 'trash_empty')) ?></b></p><?php endif ?>
<p class="sth-empty" data-studio-empty hidden><b><?= $h($t('studio_search_empty')) ?></b><?= $h($t('studio_search_empty_help')) ?></p>
</div>
<?php if ($view === 'actives' && $builtInCourses): ?>
<div class="sth-sec"><h2><?= $h($t('built_in_heading')) ?></h2><p><?= $h($t('built_in_intro')) ?></p></div>
<div class="sth-grid"><?php foreach ($builtInCourses as $item) { $card($item, 'builtin'); } ?></div>
<?php endif ?>
</main>
<?php if ($canCreate): ?>
<dialog class="ast-dialog" id="sth-new" aria-labelledby="sth-new-title"<?= !empty($openNewCourse) ? ' data-autoshow' : '' ?>><form method="post" action="<?= $h($link('action/studio_new')) ?>"><?= $csrf ?><input type="hidden" name="course" value="academy">
<div class="mh"><div><p class="k"><?= $h($t('studio_new_course')) ?></p><h2 id="sth-new-title"><?= $h($t('new_course_heading')) ?></h2></div><button class="mx" type="button" data-close-dialog aria-label="<?= $h($t('close')) ?>">×</button></div>
<div class="mb">
<label class="fl"><span class="lab"><?= $h($t('new_course_title')) ?></span><input class="in" name="new_title" maxlength="200" required placeholder="<?= $h($t('new_course_title_ph')) ?>"></label>
<label class="fl"><span class="lab"><?= $h($t('new_course_summary')) ?></span><textarea class="in" name="new_summary" rows="3" maxlength="2000" placeholder="<?= $h($t('new_course_summary_ph')) ?>"></textarea></label>
<fieldset class="fl"><legend class="lab"><?= $h($t('studio_cover')) ?></legend><div class="covs"><?php foreach (AcademyStudio::COVERS as $index => $cover): ?><label style="--img:url('<?= $h($coverUrl($cover)) ?>')"><input type="radio" name="new_cover" value="<?= $cover ?>"<?= $index === 0 ? ' checked' : '' ?>><span class="sr-only"><?= $h($t('studio_cover_' . $cover)) ?></span></label><?php endforeach ?></div></fieldset>
<label class="fl sth-private"><input type="checkbox" name="new_private" value="1"> <span><b><?= $h($t('private_draft')) ?></b> · <?= $h($t('private_draft_help')) ?></span></label>
</div>
<div class="mf"><span class="h"><?= $h($t('new_course_draft')) ?></span><button class="btn" type="button" data-close-dialog><?= $h($t('cancel')) ?></button><button class="btn dark"><?= $h($t('create_and_open')) ?></button></div>
</form></dialog>
<?php endif ?>

<?php else: /* ============ Éditeur ============ */
$key = (string) $course['key'];
$builtIn = !empty($course['built_in']);
$isArchived = !empty($course['archived']);
$readOnly = $builtIn || !empty($course['published']) || $isArchived;
$chapters = $course['chapters'];
$selC = 0;
$selS = 0;
if (preg_match('/\A(\d{1,2})\.(\d)\z/', (string) ($viewStep ?? ''), $m) && isset($chapters[(int) $m[1]]) && (int) $m[2] < count($stages)) {
    [$selC, $selS] = [(int) $m[1], (int) $m[2]];
}
$issuesByStep = [];
foreach ($issues as $issue) {
    $issuesByStep[($issue['chapter'] ?? 0) . '.' . $issue['stage']][] = $issue;
}
[$frTotal, $enDone] = AcademyStudio::translation($course);
$enPercent = $frTotal > 0 ? (int) floor($enDone * 100 / $frTotal) : 100;
$editLang = ($editLang ?? 'fr') === 'en' ? 'en' : 'fr';
$v = static fn (?array $text, string $language): string => (string) ($text[$language] ?? '');
$stepUrl = static fn (int $c, int $s): string => $link('studio') . '?course=' . rawurlencode($key) . '&step=' . $c . '.' . $s . ($editLang === 'en' ? '&edit=en' : '');
$previewUrl = $link('classroom') . '?course=' . rawurlencode($key) . '&preview=1';
$title = $local($course['title']) ?: $t('untitled_course');
$statusKey = $builtIn ? 'builtin' : ($isArchived ? 'archived' : (!empty($course['published']) ? 'pub' : (isset($course['replaces']) ? 'version' : 'draft')));
$canPublish = $staff && !$readOnly;
$fieldId = 0;
/** Barre d'outils du texte riche : elle insère la syntaxe AcademyRichText dans le champ de la langue affichée. */
$richBar = static function () use ($h, $t, $lang): void {
    $tool = static fn (string $cmd, string $label, string $glyph): string => '<button type="button" class="rte-b" data-rte-cmd="' . $cmd . '" title="' . $h($label) . '" aria-label="' . $h($label) . '">' . (str_starts_with($glyph, '#') ? '<svg class="i" aria-hidden="true"><use href="' . $glyph . '"/></svg>' : '<span aria-hidden="true">' . $glyph . '</span>') . '</button>'; ?>
<div class="rte-bar" role="toolbar" aria-label="<?= $h($t('rte_toolbar')) ?>">
<?= $tool('bold', $t('rte_bold'), '<b>B</b>') ?><?= $tool('italic', $t('rte_italic'), '<i>I</i>') ?><?= $tool('heading', $t('rte_heading'), 'H') ?>
<span class="rte-sep" aria-hidden="true"></span>
<?= $tool('list', $t('rte_list'), '#ai-list') ?><?= $tool('numbered', $t('rte_numbered'), '#ai-olist') ?><?= $tool('quote', $t('rte_quote'), '#ai-quote') ?><?= $tool('rule', $t('rte_rule'), '#ai-rule') ?>
<span class="rte-sep" aria-hidden="true"></span>
<label class="rte-sel"><span class="sr-only"><?= $h($t('rte_callout')) ?></span><select data-rte-callout title="<?= $h($t('rte_callout')) ?>"><option value=""><?= $h($t('rte_callout')) ?>…</option>
<?php foreach (['note' => 'note', 'tip' => 'astuce', 'warning' => 'attention', 'example' => 'exemple', 'analysis' => 'analyse'] as $kind => $keyword): ?><option value="<?= $lang === 'en' ? $kind : $keyword ?>"><?= $h($t('rt_' . $kind)) ?></option><?php endforeach ?></select></label>
<?= $tool('radio', $t('rte_radio'), '#ai-radio') ?><?= $tool('steps', $t('rte_steps'), '#ai-steps') ?><?= $tool('log', $t('rte_log'), '#ai-code') ?><?= $tool('table', $t('rte_table'), '#ai-table') ?>
<span class="rte-sep" aria-hidden="true"></span>
<?= $tool('image', $t('rte_image'), '#ai-image') ?><?= $tool('link', $t('rte_link'), '#ai-link') ?>
<span class="sp"></span>
<button type="button" class="rte-b rte-pvt" data-rte-toggle aria-pressed="false"><?= $h($t('rte_preview')) ?></button>
</div>
<?php };
/** Aide repliable : syntaxe disponible (les exemples restent en syntaxe brute). */
$richHelp = static function () use ($h, $t): void {
    $rows = [
        ['## ' . $t('rte_ph_heading'), 'rte_help_heading'], ['**' . $t('rte_ph_bold') . '** *' . $t('rte_ph_italic') . '*', 'rte_help_bold'], ['`9-line`', 'rte_help_code'],
        ["- " . $t('rte_ph_item') . "\n1. " . $t('rte_ph_item'), 'rte_help_list'], ['> ' . $t('rte_ph_quote'), 'rte_help_quote'], ['---', 'rte_help_rule'],
        ['[' . $t('rte_ph_link') . '](https://…)', 'rte_help_link'], [':::' . mb_strtolower($t('rt_tip')) . "\n" . $t('rte_ph_callout') . "\n:::", 'rte_help_callout'],
        [":::transmission\nVoodoo 1 : " . $t('rte_ph_message') . "\nBastion : " . $t('rte_ph_message') . "\n:::", 'rte_help_radio'],
        [":::" . mb_strtolower($t('rt_steps')) . "\n1. " . $t('rte_ph_step') . "\n:::", 'rte_help_steps'], ["```\n" . $t('rte_ph_log') . "\n```", 'rte_help_log'],
        ['| ' . $t('rte_ph_col') . ' | ' . $t('rte_ph_col') . " |\n|---|---|\n| " . $t('rte_ph_cell') . ' | ' . $t('rte_ph_cell') . ' |', 'rte_help_table'],
        ['![' . $t('rte_ph_caption') . '](uploads/…)', 'rte_help_image'],
    ]; ?>
<details class="rte-help"><summary><?= $h($t('rte_help')) ?></summary><p class="h"><?= $h($t('rte_help_intro')) ?></p>
<dl><?php foreach ($rows as [$syntax, $key]): ?><div><dt><code translate="no"><?= nl2br($h($syntax)) ?></code></dt><dd><?= $h($t($key)) ?></dd></div><?php endforeach ?></dl></details>
<?php };
/** Champ bilingue : une langue visible à la fois (bascule FR / EN de la barre), le texte français sert d'indice en anglais.
 *  $richText : barre d'outils, aperçu en direct (rendu de la salle de cours) et, pour les grands textes, aide de syntaxe. */
$bi = static function (string $name, ?array $value, string $label, int $rows = 0, string $help = '', string $placeholder = '', bool $richText = false) use ($h, $v, &$fieldId, $richBar, $richHelp, $t, $copy): void {
    $fieldId++;
    $fr = $v($value, 'fr');
    $compact = $rows < 6; ?>
<div class="fl full<?= $richText ? ' rte' . ($compact ? ' rte--compact' : '') : '' ?>" data-bi<?= $richText ? ' data-rte' : '' ?>><label class="lab" for="f<?= $fieldId ?>-fr"><?= $h($label) ?></label><?php if ($help !== ''): ?><span class="h"><?= $h($help) ?></span><?php endif ?>
<?php if ($richText) { $richBar(); } ?>
<?php if ($richText): ?><div class="rte-split"><div class="rte-src"><?php endif ?>
<?php foreach (['fr', 'en'] as $language): $text = $v($value, $language); $missing = $language === 'en' && $fr !== '' && $text === '';
    $hint = $language === 'en' && $fr !== '' ? 'FR : ' . mb_substr(preg_replace('/\s+/u', ' ', $fr) ?? '', 0, 160) : $placeholder;
    $attributes = 'class="in' . ($missing ? ' is-missing' : '') . '" id="f' . $fieldId . '-' . $language . '" name="' . $h($name) . '[' . $language . ']" lang="' . $language . '" data-lang-field="' . $language . '" placeholder="' . $h($hint) . '"'; ?>
<?php if ($rows > 0): ?><textarea <?= $attributes ?> rows="<?= $rows ?>" maxlength="20000"<?= $richText ? ' data-rte-input' : '' ?>><?= $h($text) ?></textarea><?php else: ?><input <?= $attributes ?> value="<?= $h($text) ?>" maxlength="2000"><?php endif ?>
<?php endforeach ?>
<?php if ($richText): ?></div><div class="rte-pv" data-rte-pane<?= $compact ? ' hidden' : '' ?>><p class="k"><?= $h($t('rte_preview_title')) ?></p>
<?php foreach (['fr', 'en'] as $language): $text = $v($value, $language); ?><div class="lesson-text rt" lang="<?= $language ?>" data-lang-field="<?= $language ?>" data-rte-preview aria-live="polite"><?= $text !== '' ? \App\Services\Academy\AcademyRichText::render($text, $copy) : '<p class="rte-empty">' . $h($t('rte_preview_empty')) . '</p>' ?></div><?php endforeach ?>
</div></div><?php if (!$compact) { $richHelp(); } endif ?>
</div>
<?php };
$questionFields = static function (string $name, ?array $question, string $label) use ($bi, $h, $v, $t): void {
    $question ??= [];
    $answer = (int) ($question['answer'] ?? 0);
    $bi($name . '[question]', $question['question'] ?? null, $label, 2); ?>
<fieldset class="fl full ast-options"><legend class="lab"><?= $h($t('studio_options')) ?></legend><span class="h"><?= $h($t('studio_options_help')) ?></span>
<?php for ($i = 0; $i < 3; $i++): ?><div class="opt"><label class="lt" title="<?= $h($t('mark_correct')) ?>"><input type="radio" name="<?= $h($name) ?>[answer]" value="<?= $i ?>"<?= $answer === $i ? ' checked' : '' ?>><span aria-hidden="true"><?= ['A', 'B', 'C'][$i] ?></span><span class="sr-only"><?= $h($t('mark_correct')) ?> <?= ['A', 'B', 'C'][$i] ?></span></label>
<div class="bd"><?php foreach (['fr', 'en'] as $language): $fr = $v($question['options'][$i] ?? null, 'fr'); $text = $v($question['options'][$i] ?? null, $language); ?><input class="in<?= $language === 'en' && $fr !== '' && $text === '' ? ' is-missing' : '' ?>" name="<?= $h($name) ?>[options][<?= $i ?>][<?= $language ?>]" value="<?= $h($text) ?>" maxlength="2000" lang="<?= $language ?>" data-lang-field="<?= $language ?>" aria-label="<?= $h($t('answer_choice')) ?> <?= ['A', 'B', 'C'][$i] ?> (<?= strtoupper($language) ?>)" placeholder="<?= $h($language === 'en' && $fr !== '' ? 'FR : ' . $fr : $t('answer_choice_ph')) ?>"><?php endforeach ?></div></div><?php endfor ?>
</fieldset>
<?php $bi($name . '[feedback]', $question['feedback'] ?? null, $t('studio_feedback'), 2, '', '', true);
};
/** Liste de critères : une ligne par critère, retrait par ×, ligne vide révélée par « Ajouter ». */
$criteriaFields = static function (string $name, array $criteria, int $chapter, string $stage) use ($h, $v, $t): void { ?>
<div class="fl full ast-list"><span class="lab"><?= $h($t('validation_criteria')) ?></span><span class="h"><?= $h($t('studio_criteria_help')) ?></span>
<?php foreach (array_merge($criteria, [null]) as $index => $criterion): ?><div class="row<?= $criterion === null ? ' is-slot' : '' ?>"<?= $criterion === null ? ' data-slot' : '' ?>><span class="nb"><?= $index + 1 ?>.</span><?php foreach (['fr', 'en'] as $language): $fr = $v($criterion, 'fr'); ?><input class="in<?= $language === 'en' && $fr !== '' && $v($criterion, 'en') === '' ? ' is-missing' : '' ?>" name="<?= $h($name) ?>[<?= $index ?>][<?= $language ?>]" value="<?= $h($v($criterion, $language)) ?>" maxlength="2000" lang="<?= $language ?>" data-lang-field="<?= $language ?>" aria-label="<?= $h($t('criterion')) ?> <?= $index + 1 ?> (<?= strtoupper($language) ?>)" placeholder="<?= $h($language === 'en' && $fr !== '' ? 'FR : ' . $fr : $t('criterion_ph')) ?>"><?php endforeach ?>
<?php if ($criterion !== null): ?><button class="x" name="op" value="remove_criterion:<?= $chapter ?>:<?= $stage ?>:<?= $index ?>" title="<?= $h($t('remove')) ?>" aria-label="<?= $h($t('remove')) ?> · <?= $h($t('criterion')) ?> <?= $index + 1 ?>">×</button><?php endif ?></div><?php endforeach ?>
<button type="button" class="add" data-reveal-slot hidden>+ <?= $h($t('studio_add_criterion')) ?></button></div>
<?php };
$stepSummary = static function (array $chapter, int $s) use ($t): string {
    return match ($s) {
        0 => ($pages = array_sum(array_map(static fn (array $r): int => (int) ($r['pages'] ?? 0), $chapter['resources'] ?? []))) > 0 ? $pages . ' ' . $t('slides_count') : $t('sub_theory'),
        2 => $t(isset($chapter['guided']) ? 'studio_custom' : 'studio_reference'),
        3 => $t(isset($chapter['simulation']) ? 'studio_custom' : 'studio_reference'),
        4 => ($count = count(AcademyCurriculum::examQuestions($chapter))) . ' ' . ($count === 1 ? mb_strtolower($t('question_number')) : $t('questions_label')) . ' · ' . (int) ceil(AcademyCurriculum::examSeconds($chapter) / 60) . ' min',
        5 => $t(isset($chapter['debrief']) ? 'studio_custom' : 'studio_default'),
        6 => $t(isset($chapter['validation']) ? 'studio_custom' : 'studio_default'),
        default => $t('sub_demonstration'),
    };
};
$stepCount = count($chapters) * count($stages);
$slideTotal = $questionTotal = 0;
foreach ($chapters as $chapter) {
    $slideTotal += array_sum(array_map(static fn (array $r): int => (int) ($r['pages'] ?? 0), $chapter['resources'] ?? []));
    $questionTotal += count(AcademyCurriculum::examQuestions($chapter));
}
$deckFigure = static function (array $resource) use ($h, $t, $link, $local): void { $deckUrl = $link('media/' . rawurlencode((string) $resource['file'])); ?>
<figure class="acr-deck" data-academy-deck data-src="<?= $h($deckUrl) ?>" data-lib="<?= $h(asset_url('assets/vendor/pdfjs/pdf.js')) ?>" data-worker="<?= $h(asset_url('assets/vendor/pdfjs/pdf.worker.min.js')) ?>" data-label-of="<?= $h($t('chapter_of')) ?>">
<div class="sv"><canvas data-deck-canvas aria-label="<?= $h($local($resource['title'])) ?>"></canvas><p class="acr-deck__status" data-deck-status><?= $h($t('slides_loading')) ?> <a href="<?= $h($deckUrl) ?>" target="_blank" rel="noopener"><?= $h($t('open_slides')) ?></a></p><button class="nav p" type="button" data-deck-prev aria-label="<?= $h($t('slide_prev')) ?>"></button><button class="nav n" type="button" data-deck-next aria-label="<?= $h($t('slide_next')) ?>"></button></div>
<div class="dc"><button class="ib" type="button" data-deck-prev aria-label="<?= $h($t('slide_prev')) ?>"><svg class="i"><use href="#ai-left"/></svg></button><span class="cnt" data-deck-count>1 / <?= (int) ($resource['pages'] ?? 1) ?></span><button class="ib" type="button" data-deck-next aria-label="<?= $h($t('slide_next')) ?>"><svg class="i"><use href="#ai-right"/></svg></button><div class="pb" data-deck-bar></div><button class="ib" type="button" data-deck-full aria-label="<?= $h($t('fullscreen')) ?>"><svg class="i"><use href="#ai-full"/></svg></button></div>
<div class="thumbs" data-deck-thumbs></div>
</figure>
<?php };
?>
<header class="ast-top">
<a class="tb icon" href="<?= $h($link('studio')) ?>" title="<?= $h($t('studio_all_courses')) ?>"><svg class="i"><use href="#ai-back"/></svg><span class="sr-only"><?= $h($t('studio_all_courses')) ?></span></a>
<span class="wm">ACADEMY <span>STUDIO</span></span><span class="sep" aria-hidden="true"></span>
<button class="ctitle" type="button" data-open-course-tab title="<?= $h($t('studio_course_settings')) ?>"><span class="cr" aria-hidden="true"><?= $h($crestOf($title)) ?></span><span><b><?= $h($title) ?></b><small><?= count($chapters) ?> <?= $h(mb_strtolower($t(count($chapters) === 1 ? 'chapter' : 'chapters'))) ?> · <?= $stepCount ?> <?= $h($t('steps_word')) ?></small></span><svg class="i cog" aria-hidden="true"><use href="#ai-cog"/></svg></button>
<span class="pill p-<?= $statusKey ?>"><i></i><?= $h($t('status_' . $statusKey)) ?></span>
<span class="sp"></span>
<button class="tb icon" type="button" data-academy-theme data-to-dark="<?= $h($t('theme_to_dark')) ?>" data-to-light="<?= $h($t('theme_to_light')) ?>" aria-pressed="false" title="<?= $h($t('theme_to_dark')) ?>"><svg class="i th-moon" aria-hidden="true"><use href="#ai-moon"/></svg><svg class="i th-sun" aria-hidden="true"><use href="#ai-sun"/></svg><span class="sr-only" data-theme-label><?= $h($t('theme_to_dark')) ?></span></button>
<div class="langsw" role="group" aria-label="<?= $h($t('edit_language')) ?>"><?php foreach (['fr' => 'FR', 'en' => 'EN'] as $code => $label): ?><a href="<?= $h($link('studio') . '?course=' . rawurlencode($key) . '&step=' . $selC . '.' . $selS . ($code === 'en' ? '&edit=en' : '')) ?>" data-edit-lang="<?= $code ?>" aria-pressed="<?= $editLang === $code ? 'true' : 'false' ?>"><?= $label ?><?php if ($code === 'en'): ?><small><?= $enPercent ?> %</small><?php endif ?></a><?php endforeach ?></div>
<?php if (!$readOnly): ?><span class="sv-st" data-save-state data-dirty="<?= $h($t('studio_unsaved')) ?>" aria-live="polite"><?= $h($t('studio_saved')) ?></span>
<details class="ast-issues"><summary class="tb <?= $issues ? 'warn' : 'okc' ?>"><?php if ($issues): ?><svg class="i"><use href="#ai-alert"/></svg><?= count($issues) ?> <?= $h($t(count($issues) === 1 ? 'studio_issue' : 'studio_issues')) ?><?php else: ?>✓ <?= $h($t('studio_ready')) ?><?php endif ?></summary>
<div class="pop"><?php if ($issues): ?><p class="k"><?= $h($t('studio_before_publish')) ?></p><ul><?php foreach ($issues as $issue): $c = (int) ($issue['chapter'] ?? 0); ?><li><a href="<?= $h($stepUrl($c, $issue['stage'])) ?>" data-step-link="<?= $c ?>.<?= $issue['stage'] ?>"<?= $issue['chapter'] === null ? ' data-open-course-tab' : '' ?>><b><?= $issue['chapter'] === null ? $h($t('studio_course_settings')) : $h($t('chapter')) . ' ' . ($c + 1) . ' · ' . $h($t($stages[$issue['stage']])) ?></b><?= $h($t($issue['issue'])) ?></a></li><?php endforeach ?></ul><?php else: ?><p class="okp"><?= $h($t('studio_ready_text')) ?></p><?php endif ?></div></details><?php endif ?>
<?php if ($canShare && !$builtIn): ?><button class="tb" type="button" data-open-dialog="ast-share"><?= $h($t('share')) ?></button><?php endif ?>
<?php if (!$builtIn): ?><a class="tb" href="<?= $h($previewUrl) ?>"><svg class="i"><use href="#ai-eye"/></svg><?= $h($t('studio_preview')) ?></a><?php endif ?>
<?php if ($isArchived && $staff): ?><button class="tb pri" form="ast-unarchive"><?= $h($t('restore')) ?></button>
<?php elseif ($readOnly && $staff): ?><button class="tb pri" form="ast-revise"><?= $h($t($builtIn ? 'studio_copy_builtin' : 'studio_revise')) ?></button>
<?php elseif (!$readOnly): ?><button class="tb" form="ast-form"><?= $h($t('studio_save')) ?></button>
<?php if ($canPublish): ?><button class="tb pri" type="submit" form="ast-form" formaction="<?= $h($link('action/studio_publish')) ?>" data-open-dialog="ast-publish"><?= $h($t('studio_publish')) ?></button><?php else: ?><span class="tb ghost" title="<?= $h($t('publish_staff_only')) ?>"><?= $h($t('publish_staff_only_short')) ?></span><?php endif ?><?php endif ?>
</header>
<form id="ast-revise" method="post" action="<?= $h($link($builtIn ? 'action/studio_duplicate' : 'action/studio_revise')) ?>" hidden><?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"></form>
<form id="ast-unarchive" method="post" action="<?= $h($link('action/studio_unarchive')) ?>" hidden><?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"></form>
<form id="ast-archive" method="post" action="<?= $h($link('action/studio_archive')) ?>" hidden><?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"></form>
<form id="ast-delete" method="post" action="<?= $h($link('action/studio_delete')) ?>" hidden><?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"></form>
<form id="ast-cred-link" method="post" action="<?= $h($link('action/studio_cred_link')) ?>" hidden><?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"><input type="hidden" name="return" value="course"></form>
<form id="ast-cred-unlink" method="post" action="<?= $h($link('action/studio_cred_unlink')) ?>" hidden><?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"><input type="hidden" name="return" value="course"></form>
<form id="ast-form" class="ast-form" method="post" action="<?= $h($link('action/studio_save')) ?>" data-studio-form data-lang="<?= $editLang ?>">
<?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"><input type="hidden" name="step" value="<?= $selC ?>.<?= $selS ?>" data-step-input><input type="hidden" name="edit_lang" value="<?= $editLang ?>" data-edit-lang-input>
<?php $lock = $readOnly ? ' disabled' : ''; /* Lecture seule : champs désactivés, navigation et zone sensible restent actives. */ ?>
<div class="ast-app">
<nav class="col left" aria-label="<?= $h($t('studio_structure')) ?>"><fieldset class="ast-fs"<?= $lock ?>>
<div class="lh"><span class="k"><?= $h($t('studio_structure')) ?></span><span class="ast-hint"><?= $h($t('studio_reorder_help')) ?></span></div>
<?php foreach ($chapters as $ci => $chapter): ?>
<div class="ch"><div class="chh"><span class="n"><?= $ci + 1 ?></span>
<?php foreach (['fr', 'en'] as $language): $fr = $v($chapter['title'], 'fr'); ?><input class="cht<?= $language === 'en' && $fr !== '' && $v($chapter['title'], 'en') === '' ? ' is-missing' : '' ?>" name="draft[chapters][<?= $ci ?>][title][<?= $language ?>]" value="<?= $h($v($chapter['title'], $language)) ?>" maxlength="2000" lang="<?= $language ?>" data-lang-field="<?= $language ?>" aria-label="<?= $h($t('studio_chapter_title')) ?> <?= $ci + 1 ?> (<?= strtoupper($language) ?>)" placeholder="<?= $h($language === 'en' && $fr !== '' ? $fr : $t('untitled_chapter')) ?>"><?php endforeach ?>
<button class="mini" name="op" value="up:<?= $ci ?>" title="<?= $h($t('studio_move_up')) ?>"<?= $ci === 0 ? ' disabled' : '' ?>><svg class="i"><use href="#ai-up"/></svg><span class="sr-only"><?= $h($t('studio_move_up')) ?></span></button>
<button class="mini" name="op" value="down:<?= $ci ?>" title="<?= $h($t('studio_move_down')) ?>"<?= $ci === count($chapters) - 1 ? ' disabled' : '' ?>><svg class="i"><use href="#ai-down"/></svg><span class="sr-only"><?= $h($t('studio_move_down')) ?></span></button>
<button class="mini dg" name="op" value="remove_chapter:<?= $ci ?>" title="<?= $h($t('remove_chapter')) ?>" data-confirm="<?= $h($t('studio_confirm_remove')) ?>"<?= count($chapters) === 1 ? ' disabled' : '' ?>><svg class="i"><use href="#ai-trash"/></svg><span class="sr-only"><?= $h($t('remove_chapter')) ?></span></button></div>
<ol class="ls"><?php foreach ($stages as $si => $stageName): $hasIssue = isset($issuesByStep[$ci . '.' . $si]); ?>
<li><a class="l<?= $ci === $selC && $si === $selS ? ' on' : '' ?>" href="<?= $h($stepUrl($ci, $si)) ?>" data-step-link="<?= $ci ?>.<?= $si ?>"<?= $ci === $selC && $si === $selS ? ' aria-current="true"' : '' ?>><span class="ic t-<?= $stageName ?>"><svg class="i"><use href="#ai-<?= $stageName ?>"/></svg></span><span><b><?= $h($t($stageName)) ?></b><small><?= $h($stepSummary($chapter, $si)) ?></small></span><?php if ($hasIssue): ?><span class="iss" title="<?= $h($t('studio_issue')) ?>"></span><?php endif ?></a></li>
<?php endforeach ?></ol></div>
<?php endforeach ?>
<button class="addc" name="op" value="add_chapter"<?= count($chapters) >= AcademyStudio::MAX_CHAPTERS ? ' disabled' : '' ?>>+ <?= $h($t('add_chapter')) ?></button>
<div class="lf"><span><?= $stepCount ?> <?= $h($t('steps_word')) ?></span><span><?= $h($t('studio_shortcut')) ?></span></div>
</fieldset></nav>
<main id="main" class="col center">
<p class="enbar" data-en-only><span><?= $h(sprintf($t('en_bar'), $enPercent)) ?></span><a href="<?= $h($link('studio') . '?course=' . rawurlencode($key) . '&step=' . $selC . '.' . $selS) ?>" data-edit-lang="fr"><?= $h($t('back_to_french')) ?></a></p>
<?php if ($notice): ?><p class="ast-notice ast-notice--<?= $noticeClass($notice) ?>" role="status"><?= $h($t($notice)) ?></p><?php endif ?>
<?php if ($readOnly): ?><p class="ast-notice ast-notice--info"><?= $h($t($builtIn ? 'studio_builtin_readonly' : ($isArchived ? 'studio_archived_readonly' : 'studio_published_readonly'))) ?></p><?php endif ?>
<fieldset class="ast-fs"<?= $lock ?>>
<?php foreach ($chapters as $ci => $chapter): $base = 'draft[chapters][' . $ci . ']'; $chapterTitle = $local($chapter['title']) ?: $t('untitled_chapter'); ?>
<?php foreach ($stages as $si => $stageName): ?>
<section class="ast-panel" data-step-panel="<?= $ci ?>.<?= $si ?>"<?= $ci === $selC && $si === $selS ? '' : ' hidden' ?> aria-labelledby="ast-h-<?= $ci ?>-<?= $si ?>">
<div class="chd"><span class="ic t-<?= $stageName ?>"><svg class="i"><use href="#ai-<?= $stageName ?>"/></svg></span><div><small><?= $h($t('chapter')) ?> <?= $ci + 1 ?> · <?= $h(mb_strtolower($t('step_label'))) ?> <?= $si + 1 ?> / <?= count($stages) ?></small><h1 id="ast-h-<?= $ci ?>-<?= $si ?>"><?= $h($t($stageName)) ?> — <?= $h($chapterTitle) ?></h1></div>
<?php if (!$builtIn): ?><a class="btn" href="<?= $h($previewUrl) ?>"><svg class="i"><use href="#ai-eye"/></svg><?= $h($t('learner_preview')) ?></a><?php endif ?></div>
<?php if (isset($issuesByStep[$ci . '.' . $si]) && !$readOnly): ?><ul class="chk"><?php foreach ($issuesByStep[$ci . '.' . $si] as $issue): ?><li><?php if ($issue['chapter'] === null): ?><button type="button" data-open-course-tab><svg class="i"><use href="#ai-alert"/></svg><?= $h($t($issue['issue'])) ?> · <u><?= $h($t('studio_course_settings')) ?></u></button><?php else: ?><span><svg class="i"><use href="#ai-alert"/></svg><?= $h($t($issue['issue'])) ?></span><?php endif ?></li><?php endforeach ?></ul><?php endif ?>
<div class="form">
<?php if ($si === 0): $decks = array_filter($chapter['resources'] ?? [], static fn (array $r): bool => isset($r['file'])); ?>
<?php if ($decks): ?><div class="ast-stage"><?php foreach ($decks as $deck) { $deckFigure($deck); } ?></div>
<?php elseif (!$readOnly): ?>
<div class="ast-gs"><div><p class="kk"><svg class="i"><use href="#ai-theory"/></svg><?= $h($t('studio_import_title')) ?></p><h2><?= $h($t('gs_hero_title')) ?></h2><p><?= $h($t('studio_import_help')) ?></p></div>
<div class="ast-gs__row"><input class="in" type="url" name="slides_url_here[<?= $ci ?>]" inputmode="url" maxlength="512" placeholder="https://docs.google.com/presentation/d/…" autocomplete="off" aria-label="<?= $h($t('studio_import_link')) ?>"><button class="hb1" name="slides_here" value="<?= $ci ?>" formaction="<?= $h($link('action/studio_import')) ?>"><?= $h($t('studio_import_button')) ?></button></div>
<p class="h"><?= $h($t('studio_import_limits')) ?></p></div>
<?php endif ?>
<div class="fc"><h3><?= $h($t('studio_lesson')) ?></h3><p><?= $h($t('studio_theory_help')) ?></p><div class="fg"><?php $bi($base . '[theory]', $chapter['theory'], $t('theory'), 14, '', '', true); ?></div></div>
<?php if ($decks): ?><div class="fc"><h3><?= $h($t('studio_slides')) ?> <span class="k"><?= count($decks) ?></span></h3><p><?= $h($t('studio_slides_help')) ?></p><div class="ast-items">
<?php foreach ($decks as $ri => $resource): $mediaUrl = $link('media/' . rawurlencode((string) $resource['file'])); ?>
<div class="item"><span class="ic t-theory"><svg class="i"><use href="#ai-theory"/></svg></span><div class="grow"><input type="hidden" name="<?= $h($base) ?>[resources][<?= $ri ?>][file]" value="<?= $h($resource['file']) ?>">
<?php $bi($base . '[resources][' . $ri . '][title]', $resource['title'], $t('resource_title')); ?>
<small><?= (int) ($resource['pages'] ?? 0) ?> <?= $h($t('slides_count')) ?><?php if (!empty($resource['imported_at'])): ?> · <?= $h($t('studio_imported_on')) ?> <?= $h(date('d/m/Y H:i', (int) $resource['imported_at'])) ?><?php endif ?> · <a href="<?= $h($mediaUrl) ?>" target="_blank" rel="noopener"><?= $h($t('open_slides')) ?></a></small></div>
<div class="acts"><?php if (!empty($resource['source'])): ?><button class="ib" name="reimport_url" value="<?= $h($resource['source']) ?>" formaction="<?= $h($link('action/studio_import')) ?>" title="<?= $h($t('studio_reimport')) ?>"><svg class="i"><use href="#ai-refresh"/></svg><span><?= $h($t('update')) ?></span></button><a class="ib" href="<?= $h($resource['source']) ?>" target="_blank" rel="noopener noreferrer" title="<?= $h($t('studio_open_google')) ?>"><svg class="i"><use href="#ai-link"/></svg><span class="sr-only"><?= $h($t('studio_open_google')) ?></span></a><?php endif ?>
<button class="ib dg" name="op" value="remove_resource:<?= $ci ?>:<?= $ri ?>" data-confirm="<?= $h($t('studio_confirm_remove_slides')) ?>" title="<?= $h($t('remove')) ?>"><svg class="i"><use href="#ai-trash"/></svg><span class="sr-only"><?= $h($t('remove')) ?></span></button></div></div>
<?php endforeach ?></div></div><?php endif ?>
<div class="fc"><h3><?= $h($t('resources_label_v3')) ?></h3><p><?= $h($t('studio_resources_help')) ?></p><div class="ast-items">
<?php $links = array_filter($chapter['resources'] ?? [], static fn (array $r): bool => !isset($r['file'])); $nextIndex = count($chapter['resources'] ?? []);
foreach ($links + [$nextIndex => null] as $ri => $resource): ?>
<div class="item<?= $resource === null ? ' is-slot' : '' ?>"<?= $resource === null ? ' data-slot' : '' ?>><label class="type"><span class="sr-only"><?= $h($t('resource_type')) ?></span><select class="in" name="<?= $h($base) ?>[resources][<?= $ri ?>][type]"><?php foreach (['document', 'slides', 'diagram', 'audio'] as $type): ?><option value="<?= $type ?>"<?= ($resource['type'] ?? 'document') === $type ? ' selected' : '' ?>><?= $h($t($type)) ?></option><?php endforeach ?></select></label>
<div class="grow"><?php if (isset($resource['body'])): ?><p class="h"><?= $h($t('studio_inline_resource')) ?></p><?php foreach (['fr', 'en'] as $language): ?><input type="hidden" name="<?= $h($base) ?>[resources][<?= $ri ?>][body][<?= $language ?>]" value="<?= $h($v($resource['body'], $language)) ?>"><?php endforeach ?><?php endif ?>
<?php $bi($base . '[resources][' . $ri . '][title]', $resource['title'] ?? null, $t('resource_title'), 0, '', $t('resource_title_ph')); ?>
<?php if (!isset($resource['body'])) { $bi($base . '[resources][' . $ri . '][url]', $resource['url'] ?? null, $t('resource_link'), 0, '', 'https://…'); } ?></div>
<?php if ($resource !== null): ?><div class="acts"><button class="ib dg" name="op" value="remove_resource:<?= $ci ?>:<?= $ri ?>" title="<?= $h($t('remove')) ?>"><svg class="i"><use href="#ai-trash"/></svg><span class="sr-only"><?= $h($t('remove')) ?></span></button></div><?php endif ?></div>
<?php endforeach ?></div><button type="button" class="add" data-reveal-slot hidden>+ <?= $h($t('add_resource')) ?></button></div>
<?php elseif ($si === 1): ?>
<div class="fc"><h3><?= $h($t('demonstration')) ?></h3><p><?= $h($t('studio_demo_help')) ?></p><div class="fg"><?php $bi($base . '[demonstration]', $chapter['demonstration'], $t('demonstration'), 14, '', '', true); ?></div></div>
<?php elseif ($si === 2): ?>
<div class="fc"><h3><?= $h($t('studio_reference_question')) ?></h3><p><?= $h($t('studio_reference_help')) ?></p><div class="fg"><?php $questionFields($base, $chapter, $t('question_label')); ?></div></div>
<details class="fc ast-optional"<?= isset($chapter['guided']) ? ' open' : '' ?>><summary><h3><?= $h($t('studio_custom_guided')) ?></h3><span><?= $h($t('studio_optional')) ?></span></summary><p><?= $h($t('studio_guided_help')) ?></p><div class="fg">
<?php $bi($base . '[guided][brief]', $chapter['guided']['brief'] ?? null, $t('studio_case_brief'), 5, '', '', true); ?>
<?php $bi($base . '[guided][hint]', $chapter['guided']['hint'] ?? null, $t('hint_title'), 2, $t('hint_help'), '', true); ?>
<?php $questionFields($base . '[guided]', $chapter['guided'] ?? null, $t('question_label')); ?>
</div></details>
<?php elseif ($si === 3): ?>
<details class="fc ast-optional"<?= isset($chapter['simulation']) ? ' open' : '' ?>><summary><h3><?= $h($t('simulation')) ?></h3><span><?= $h($t('studio_optional')) ?></span></summary><p><?= $h($t('studio_simulation_help')) ?></p><div class="fg">
<?php $bi($base . '[simulation][brief]', $chapter['simulation']['brief'] ?? null, $t('studio_case_brief'), 5, '', '', true); ?>
<?php $questionFields($base . '[simulation]', $chapter['simulation'] ?? null, $t('question_label')); ?>
</div></details>
<?php elseif ($si === 4): $assessment = $chapter['assessment'] ?? null; ?>
<div class="fc"><h3><?= $h($t('assessment')) ?></h3><p><?= $h($t('studio_assessment_help')) ?></p><div class="fg">
<?php $bi($base . '[assessment][instructions]', $assessment['instructions'] ?? null, $t('studio_instructions'), 3, '', '', true); ?>
<label class="fl"><span class="lab"><?= $h($t('studio_minutes')) ?></span><input class="in" type="number" min="1" max="120" name="<?= $h($base) ?>[assessment][minutes]" value="<?= (int) ceil(($assessment['seconds'] ?? 300) / 60) ?>"></label>
<label class="fl"><span class="lab"><?= $h(rtrim($t('passing_score'), ' :')) ?> (%)</span><input class="in" type="number" min="1" max="100" name="<?= $h($base) ?>[assessment][pass_percent]" value="<?= (int) ($assessment['pass_percent'] ?? 80) ?>"></label>
</div></div>
<?php foreach (array_merge($assessment['questions'] ?? [], [null]) as $qi => $question): ?>
<div class="fc qc<?= $question === null ? ' is-slot' : '' ?>"<?= $question === null ? ' data-slot' : '' ?>><div class="qh"><b><?= $h($t('question_number')) ?> <?= $qi + 1 ?></b><span class="sp"></span><?php if ($question !== null): ?><button class="mini dg" name="op" value="remove_question:<?= $ci ?>:<?= $qi ?>" title="<?= $h($t('studio_remove_question')) ?>"><svg class="i"><use href="#ai-trash"/></svg><span class="sr-only"><?= $h($t('studio_remove_question')) ?></span></button><?php endif ?></div><div class="fg"><?php $questionFields($base . '[assessment][questions][' . $qi . ']', $question, $t('question_label')); ?></div></div>
<?php endforeach ?>
<button type="button" class="add wide" data-reveal-slot hidden>+ <?= $h($t('add_question')) ?></button>
<?php elseif ($si === 5): ?>
<div class="fc"><h3><?= $h($t('debrief')) ?></h3><p><?= $h($t('studio_debrief_help')) ?></p><div class="fg">
<?php $bi($base . '[debrief][prompt]', $chapter['debrief']['prompt'] ?? null, $t('studio_debrief_prompt'), 4, '', '', true); ?>
<?php $criteriaFields($base . '[debrief][criteria]', $chapter['debrief']['criteria'] ?? [], $ci, 'debrief'); ?>
</div></div>
<?php else: ?>
<div class="fc"><h3><?= $h($t('validation')) ?></h3><p><?= $h($t('studio_validation_help')) ?></p><div class="fg">
<?php $bi($base . '[validation][brief]', $chapter['validation']['brief'] ?? null, $t('studio_validation_brief'), 4, '', '', true); ?>
<?php $criteriaFields($base . '[validation][criteria]', $chapter['validation']['criteria'] ?? [], $ci, 'validation'); ?>
</div></div>
<?php endif ?>
</div>
</section>
<?php endforeach; endforeach ?>
</fieldset>
</main>
<aside class="col right" aria-label="<?= $h($t('studio_inspector')) ?>">
<div class="itabs" role="tablist"><button type="button" role="tab" aria-selected="true" data-itab="step"><?= $h($t('studio_tab_step')) ?></button><button type="button" role="tab" aria-selected="false" data-itab="course"><?= $h($t('studio_tab_course')) ?></button></div>
<div data-ipanel="step" role="tabpanel"><fieldset class="ast-fs"<?= $lock ?>>
<?php if (!$readOnly): ?><div class="isec gsimp"><p class="k"><?= $h($t('studio_import_title')) ?></p>
<label class="fl"><span class="lab"><?= $h($t('studio_import_link')) ?></span><input class="in" type="url" name="slides_url" inputmode="url" maxlength="512" placeholder="https://docs.google.com/presentation/d/…" autocomplete="off"><span class="h"><?= $h($t('studio_import_help')) ?></span></label>
<label class="fl"><span class="lab"><?= $h($t('studio_import_target')) ?></span><select class="in" name="slides_target"><option value="new"><?= $h($t('studio_import_new_chapter')) ?></option><?php foreach ($chapters as $ci => $chapter): ?><option value="<?= $ci ?>"<?= $ci === $selC ? ' selected' : '' ?>><?= $h($t('chapter')) ?> <?= $ci + 1 ?> · <?= $h($t('theory')) ?></option><?php endforeach ?></select></label>
<button class="ib solid" formaction="<?= $h($link('action/studio_import')) ?>"><?= $h($t('studio_import_button')) ?></button>
<p class="h"><?= $h($t('studio_import_limits')) ?></p></div><?php endif ?>
<div class="isec"><p class="k"><?= $h($t('studio_learner_view')) ?></p><?php foreach ($stages as $si => $stageName): ?><p class="h" data-step-tip="<?= $si ?>"<?= $si === $selS ? '' : ' hidden' ?>><?= $h($t('studio_tip_' . $stageName)) ?></p><?php endforeach ?></div>
<div class="isec"><p class="k"><?= $h($t('studio_translation')) ?></p><div class="meter" role="img" aria-label="<?= $h(sprintf($t('en_progress'), $enPercent)) ?>"><i style="width:<?= $enPercent ?>%"></i></div><p class="h"><?= $h(sprintf($t('en_progress_text'), $enDone, $frTotal)) ?></p></div>
</fieldset></div>
<div data-ipanel="course" role="tabpanel" hidden><fieldset class="ast-fs"<?= $lock ?>>
<div class="isec"><p class="k"><?= $h($t('studio_course_settings')) ?></p>
<?php $bi('draft[title]', $course['title'], $t('title_label')); ?>
<?php $bi('draft[summary]', $course['summary'], $t('summary_label'), 3); ?>
<?php $bi('draft[license]', $course['license'] ?? null, $t('studio_license'), 2, $t('studio_license_help')); ?>
<div class="fg"><label class="fl"><span class="lab"><?= $h($t('course_version')) ?></span><input class="in" name="draft[version]" value="<?= $h($course['version'] ?? '') ?>" maxlength="32" pattern="[A-Za-z0-9._-]{1,32}"></label>
<label class="fl"><span class="lab"><?= $h($t('estimated_duration')) ?> (min)</span><input class="in" type="number" min="5" max="1440" name="draft[duration_minutes]" value="<?= isset($course['duration_minutes']) ? (int) $course['duration_minutes'] : '' ?>"></label></div>
</div>
<div class="isec"><p class="k"><?= $h($t('studio_cover')) ?></p><p class="h"><?= $h($t('studio_cover_help')) ?></p><div class="imgs"><?php foreach (AcademyStudio::COVERS as $cover): ?><label style="--img:url('<?= $h($coverUrl($cover)) ?>')"><input type="radio" name="draft[cover]" value="<?= $cover ?>"<?= ($course['cover'] ?? 'night') === $cover ? ' checked' : '' ?>><span class="sr-only"><?= $h($t('studio_cover_' . $cover)) ?></span></label><?php endforeach ?></div></div>
</fieldset>
<?php if (!empty($courseCredentials)): $cc = $courseCredentials; /* Ce que le cours délivre à la réussite (fiches du référentiel). */ ?>
<div class="isec ast-delivers"><p class="k"><?= $h($t('course_delivers')) ?></p><p class="h"><?= $h($t('course_delivers_help')) ?></p>
<?php if ($cc['linked'] === []): ?><p class="h"><i><?= $h($t('course_delivers_none')) ?></i></p><?php endif ?>
<ul class="cred-courses"><?php foreach ($cc['linked'] as $cred): ?><li><a href="<?= $h($link('studio') . '?fiche=' . (int) $cred['id']) ?>"><b><?= $h($cred['name']) ?></b> <small><?= $h($t('kind_' . $cred['kind'])) ?></small></a>
<?php if ($cc['canManage'] && $cc['rulesReady']): ?><button class="ib dg" form="ast-cred-unlink" name="cred_id" value="<?= (int) $cred['id'] ?>" title="<?= $h($t('cred_unlink')) ?>"><svg class="i"><use href="#ai-x"/></svg><span class="sr-only"><?= $h($t('cred_unlink')) ?></span></button><?php endif ?></li><?php endforeach ?></ul>
<?php if ($cc['canManage'] && $cc['rulesReady']): ?>
<?php if ($cc['available'] !== []): ?><label class="fl"><span class="lab"><?= $h($t('course_delivers_add')) ?></span><select class="in" name="cred_id" form="ast-cred-link"><?php foreach ($cc['available'] as $cred): ?><option value="<?= (int) $cred['id'] ?>"><?= $h($cred['name']) ?> · <?= $h($t('kind_' . $cred['kind'])) ?></option><?php endforeach ?></select></label>
<button class="ib" form="ast-cred-link"><?= $h($t('cred_link')) ?></button><?php endif ?>
<p class="h"><a href="<?= $h($link('studio') . '?nouveau=brevet&cours=' . rawurlencode($key)) ?>">+ <?= $h($t('course_delivers_new_brevet')) ?></a> · <a href="<?= $h($link('studio') . '?nouveau=qualification&cours=' . rawurlencode($key)) ?>">+ <?= $h($t('course_delivers_new_qual')) ?></a></p>
<?php endif ?></div>
<?php endif ?>
<?php if ($staff && !$builtIn): ?>
<div class="isec danger"><p class="k"><?= $h($t('danger_zone')) ?></p>
<?php if (!$lifecycleReady): ?><p class="h"><?= $h($t('studio_lifecycle_pending')) ?></p>
<?php else: ?>
<?php if (!$isArchived): ?><p class="h"><?= $h($t('archive_help')) ?></p><button class="ib" type="submit" form="ast-archive"><?= $h($t('archive')) ?></button><?php endif ?>
<?php if ($learnerCount > 0): ?><p class="h"><?= $h(sprintf($t('delete_has_learners'), $learnerCount)) ?></p>
<?php else: ?><label class="fl"><span class="lab"><?= $h(sprintf($t('delete_type_name'), $local($course['title']) ?: $title)) ?></span><input class="in" name="confirm_title" form="ast-delete" autocomplete="off" required></label><button class="ib dg" type="submit" form="ast-delete"><?= $h($t('delete_to_trash')) ?></button><?php endif ?>
<?php endif ?></div>
<?php endif ?>
</div>
</aside>
</div>
<?php if ($canPublish): ?>
<dialog class="ast-dialog" id="ast-publish" aria-labelledby="ast-publish-title">
<div class="mh"><div><p class="k"><?= $h($t(isset($course['replaces']) ? 'publish_update' : 'publish_first')) ?></p><h2 id="ast-publish-title"><?= $h(sprintf($t('publish_heading'), $title)) ?></h2></div><button class="mx" type="button" data-close-dialog aria-label="<?= $h($t('close')) ?>">×</button></div>
<div class="mb"><dl class="sum"><div><dt><?= $h($t('stat_chapters')) ?></dt><dd><?= count($chapters) ?></dd></div><div><dt><?= $h($t('stat_steps')) ?></dt><dd><?= $stepCount ?></dd></div><div><dt><?= $h($t('stat_slides')) ?></dt><dd><?= $slideTotal ?></dd></div><div><dt><?= $h($t('stat_questions')) ?></dt><dd><?= $questionTotal ?></dd></div></dl>
<p class="k"><?= $h($t('quality_check')) ?></p>
<?php if ($issues): ?><ul class="chk"><?php foreach ($issues as $issue): $c = (int) ($issue['chapter'] ?? 0); ?><li><a href="<?= $h($stepUrl($c, $issue['stage'])) ?>" data-step-link="<?= $c ?>.<?= $issue['stage'] ?>" data-close-dialog><svg class="i"><use href="#ai-alert"/></svg><span><b><?= $issue['chapter'] === null ? $h($t('studio_course_settings')) : $h($t('chapter')) . ' ' . ($c + 1) . ' · ' . $h($t($stages[$issue['stage']])) ?></b> · <?= $h($t($issue['issue'])) ?></span></a></li><?php endforeach ?></ul>
<?php else: ?><p class="okp">✓ <?= $h($t('studio_ready_text')) ?></p><?php endif ?>
<p class="h"><?= $h($t(isset($course['replaces']) ? 'publish_update_note' : 'publish_first_note')) ?></p></div>
<div class="mf"><span class="sp"></span><button class="btn" type="button" data-close-dialog><?= $h($t('cancel')) ?></button><button class="btn dark" formaction="<?= $h($link('action/studio_publish')) ?>"<?= $issues ? ' disabled' : '' ?>><?= $h($t('studio_publish')) ?></button></div>
</dialog>
<?php endif ?>
</form>
<?php /* Éditeur de texte riche : réglages lus par academy-richtext.js (aperçu côté serveur, envoi d'image, libellés traduits). */
$rteLabels = [];
foreach (['ph_bold', 'ph_italic', 'ph_heading', 'ph_item', 'ph_quote', 'ph_callout', 'ph_message', 'ph_link', 'ph_caption', 'ph_step', 'ph_log', 'ph_col', 'ph_cell', 'link_prompt', 'preview_error', 'preview_empty', 'uploading', 'upload_failed', 'url_refused'] as $rteKey) {
    $rteLabels[$rteKey] = $t('rte_' . $rteKey);
} ?>
<div hidden data-rte-config data-preview-url="<?= $h($link('action/studio_preview')) ?>" data-image-url="<?= $h($link('action/studio_image')) ?>" data-course="<?= $h($key) ?>" data-hosts="<?= $h(implode(' ', \App\Services\Academy\AcademyRichText::IMAGE_HOSTS)) ?>" data-callsigns="<?= $h('Voodoo 1|Bastion') ?>" data-labels="<?= $h((string) json_encode($rteLabels, JSON_UNESCAPED_UNICODE)) ?>"></div>
<?php if (!$readOnly): ?>
<dialog class="ast-dialog rte-dialog" id="rte-image" aria-labelledby="rte-image-title">
<div class="mh"><div><p class="k"><?= $h($t('rte_image')) ?></p><h2 id="rte-image-title"><?= $h($t('rte_image_title')) ?></h2></div><button class="mx" type="button" data-close-dialog aria-label="<?= $h($t('close')) ?>">×</button></div>
<div class="mb">
<label class="fl"><span class="lab"><?= $h($t('rte_image_upload')) ?></span><input class="in rte-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-rte-file><span class="h"><?= $h($t('rte_image_limits')) ?></span></label>
<p class="rte-status" data-rte-status role="status" aria-live="polite"></p>
<p class="k"><?= $h($t('rte_image_library')) ?></p>
<div class="rte-lib" data-rte-library><?php foreach ($mediaImages ?? [] as $image): ?><button type="button" class="rte-thumb" data-rte-pick="<?= $h($image['path']) ?>" aria-pressed="false" title="<?= $h($t('rte_choose_image')) ?>"><img src="<?= $h($image['url']) ?>" alt="" loading="lazy"><span class="sr-only"><?= $h($t('rte_choose_image')) ?> · <?= $h(basename($image['path'])) ?></span></button><?php endforeach ?></div>
<p class="h" data-rte-none<?= !empty($mediaImages) ? ' hidden' : '' ?>><?= $h($t('rte_image_none')) ?></p>
<label class="fl"><span class="lab"><?= $h($t('rte_image_url')) ?></span><input class="in" type="url" inputmode="url" maxlength="2000" data-rte-src placeholder="https://upload.wikimedia.org/…" autocomplete="off"><span class="h"><?= $h($t('rte_image_hosts')) ?> <?= $h(implode(', ', \App\Services\Academy\AcademyRichText::IMAGE_HOSTS)) ?></span></label>
<label class="fl"><span class="lab"><?= $h($t('rte_image_caption')) ?></span><input class="in" maxlength="300" data-rte-caption placeholder="<?= $h($t('rte_ph_caption')) ?>"></label>
</div>
<div class="mf"><span class="sp"></span><button class="btn" type="button" data-close-dialog><?= $h($t('cancel')) ?></button><button class="btn dark" type="button" data-rte-insert-image><?= $h($t('rte_insert')) ?></button></div>
</dialog>
<?php endif ?>
<?php if ($canShare && !$builtIn) { require __DIR__ . '/studio_share.php'; } ?>
<?php endif ?>
<?php require __DIR__ . '/disclaimer.php'; ?>
</body></html>
