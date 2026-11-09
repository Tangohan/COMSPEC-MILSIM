<?php
/**
 * « Qui peut faire quoi dans l'Academy » : droits de la communauté (créer, modifier, publier, inscrire, valider,
 * délivrer, statistiques), public de chaque cours, aperçu « voir comme ». Réservé aux administrateurs de la communauté
 * et aux responsables de la formation ; chaque enregistrement est revérifié côté serveur et inscrit au journal.
 */
$matrix = $matrix ?? [];
$options = $options ?? ['role' => [], 'job' => [], 'unit' => [], 'user' => []];
$rightsCourses = $rightsCourses ?? [];
$rightsReady = (bool) ($rightsReady ?? false);
$rightsPreview = $rightsPreview ?? null;
$editing = (string) ($editing ?? '');
$editingCourse = (string) ($editingCourse ?? '');
$csrfField = \App\Core\Csrf::field();
$subjectLabel = static function (array $subject) use ($options, $t): string {
    if ($subject['type'] === 'staff') {
        return $t('subject_staff');
    }
    $name = $options[$subject['type']][$subject['id']] ?? null;
    return $name !== null ? $t('subject_prefix_' . $subject['type']) . ' ' . $name : $t('subject_removed');
};
$audienceKey = static function (array $course): string {
    $settings = $course['access']['settings'] ?? ['audience' => 'community', 'private_draft' => false];
    if (empty($course['published']) && empty($course['built_in']) && !empty($settings['private_draft'])) return 'private';
    return ($settings['audience'] ?? 'community') === 'restricted' ? 'restricted' : 'community';
};
?>
<div class="ins acc">
<section class="ins-hero" aria-labelledby="acc-title">
<div class="ins-hero__text"><p class="eyebrow"><?= $h($t('rights_eyebrow')) ?></p>
<h1 id="acc-title"><?= $h($t('rights_heading')) ?></h1>
<p><?= $h($t('rights_intro')) ?></p>
<p class="acc-note"><?= $h($t('rights_defaults_note')) ?></p></div>
<nav class="ins-hero__actions" aria-label="<?= $h($t('ins_shortcuts')) ?>"><a class="button outline" href="<?= $h($link('instructor')) ?>">← <?= $h($t('instructor')) ?></a><a class="button outline" href="<?= $h($link('studio')) ?>"><?= $h($t('open_studio')) ?></a></nav>
</section>
<?php if (!$rightsReady): ?><p class="notice" role="status"><?= $h($t('rights_pending')) ?></p><?php endif ?>

<section class="ins-sec" id="voir-comme" aria-labelledby="acc-preview"><div class="ins-sec__head"><h2 id="acc-preview"><?= $h($t('rights_preview')) ?></h2></div>
<form method="get" action="<?= $h($link('droits')) ?>#voir-comme" class="acc-preview-form"><label><span><?= $h($t('rights_preview_member')) ?></span><select name="apercu" required><option value=""><?= $h($t('share_choose_member')) ?></option><?php foreach ($options['user'] as $id => $name): ?><option value="<?= (int) $id ?>"<?= ($rightsPreview['id'] ?? 0) === $id ? ' selected' : '' ?>><?= $h($name) ?></option><?php endforeach ?></select></label><button class="button small"><?= $h($t('rights_preview_show')) ?></button></form>
<p class="muted"><?= $h($t('rights_preview_help')) ?></p>
<?php if ($rightsPreview !== null): ?>
<div class="acc-preview"><h3><?= $h(sprintf($t('rights_preview_as'), $rightsPreview['name'])) ?></h3>
<ul class="ins-chips"><?php foreach ($rightsPreview['can'] as $capability => $allowed): ?><li class="<?= $allowed ? 'on' : 'off' ?>"><span aria-hidden="true"><?= $allowed ? '✓' : '–' ?></span><?= $h($t('cap_' . $capability)) ?><span class="sr-only"> · <?= $h($t($allowed ? 'ins_allowed' : 'ins_not_allowed')) ?></span></li><?php endforeach ?></ul>
<?php if ($rightsPreview['courses'] !== []): ?><div class="table-scroll"><table class="ins-table"><thead><tr><th scope="col"><?= $h($t('ins_course')) ?></th><th scope="col"><?= $h($t('rights_preview_sees')) ?></th><th scope="col"><?= $h($t('rights_preview_edits')) ?></th><th scope="col"><?= $h($t('rights_preview_teaches')) ?></th></tr></thead><tbody>
<?php foreach ($rightsPreview['courses'] as $row): ?><tr><td data-label="<?= $h($t('ins_course')) ?>"><?= $h($local($row['title']) ?: $t('untitled_course')) ?></td><?php foreach (['see', 'edit', 'teach'] as $flag): ?><td data-label="<?= $h($t(['see' => 'rights_preview_sees', 'edit' => 'rights_preview_edits', 'teach' => 'rights_preview_teaches'][$flag])) ?>"><span class="tag <?= $row[$flag] ? 's-completed' : 's-off' ?>"><?= $h($t($row[$flag] ? 'yes' : 'no')) ?></span></td><?php endforeach ?></tr><?php endforeach ?>
</tbody></table></div><?php endif ?>
</div><?php endif ?>
</section>

<section class="ins-sec" id="droits-communaute" aria-labelledby="acc-matrix"><div class="ins-sec__head"><h2 id="acc-matrix"><?= $h($t('rights_matrix')) ?></h2></div>
<p class="muted"><?= $h($t('rights_matrix_help')) ?></p>
<div class="acc-cards">
<?php foreach ($matrix as $capability => $rule): $isEditing = $editing === $capability && $rightsReady; ?>
<article class="acc-card<?= $isEditing ? ' is-editing' : '' ?>" id="droit-<?= $h($capability) ?>" aria-labelledby="cap-<?= $h($capability) ?>">
<header><h3 id="cap-<?= $h($capability) ?>"><?= $h($t('cap_' . $capability)) ?></h3><span class="tag <?= $rule['configured'] ? 'a-restricted' : 'a-community' ?>"><?= $h($t($rule['configured'] ? 'rights_custom' : 'rights_default')) ?></span></header>
<p><?= $h($t('cap_' . $capability . '_help')) ?></p>
<ul class="acc-who" aria-label="<?= $h($t('rights_who')) ?>"><li class="is-always"><?= $h($t('subject_admins')) ?></li><?php foreach ($rule['subjects'] as $subject): ?><li><?= $h($subjectLabel($subject)) ?></li><?php endforeach ?><?php if ($rule['subjects'] === []): ?><li class="is-none"><?= $h($t('rights_admins_only')) ?></li><?php endif ?></ul>
<?php if ($isEditing): ?>
<form method="post" action="<?= $h($link('action/rights_save')) ?>" class="acc-form"><?= $csrfField ?><input type="hidden" name="course" value="academy"><input type="hidden" name="capability" value="<?= $h($capability) ?>">
<label class="acc-choice"><input type="checkbox" name="staff" value="1"<?= in_array(['type' => 'staff', 'id' => 0], $rule['subjects'], true) ? ' checked' : '' ?>><span><b><?= $h($t('subject_staff')) ?></b><small><?= $h($t('subject_staff_help')) ?></small></span></label>
<?php $pickerOptions = $options; $pickerSelected = $rule['subjects']; $pickerId = 'cap-' . $capability; require __DIR__ . '/access_picker.php'; ?>
<div class="buttons"><button class="button small"><?= $h($t('rights_save')) ?></button><a class="button small outline" href="<?= $h($link('droits')) ?>#droit-<?= $h($capability) ?>"><?= $h($t('cancel')) ?></a></div>
</form>
<?php elseif ($rightsReady): ?>
<div class="acc-actions"><a class="text-link" href="<?= $h($link('droits') . '?modifier=' . $capability) ?>#droit-<?= $h($capability) ?>"><?= $h($t('rights_edit')) ?></a>
<?php if ($rule['configured']): ?><form method="post" action="<?= $h($link('action/rights_reset')) ?>"><?= $csrfField ?><input type="hidden" name="course" value="academy"><input type="hidden" name="capability" value="<?= $h($capability) ?>"><button class="text-link as-button" data-confirm="<?= $h($t('rights_reset_confirm')) ?>"><?= $h($t('rights_reset')) ?></button></form><?php endif ?></div>
<?php endif ?>
</article>
<?php endforeach ?>
</div>
</section>

<section class="ins-sec" id="qui-voit" aria-labelledby="acc-courses"><div class="ins-sec__head"><h2 id="acc-courses"><?= $h($t('rights_courses')) ?></h2></div>
<p class="muted"><?= $h($t('rights_courses_help')) ?></p>
<dl class="acc-legend">
<div><dt><span class="tag a-community"><?= $h($t('audience_community')) ?></span></dt><dd><?= $h($t('audience_community_help')) ?></dd></div>
<div><dt><span class="tag a-restricted"><?= $h($t('audience_restricted')) ?></span></dt><dd><?= $h($t('audience_restricted_help')) ?></dd></div>
<div><dt><span class="tag a-private"><?= $h($t('audience_private')) ?></span></dt><dd><?= $h($t('private_draft_help')) ?></dd></div>
<div><dt><span class="tag a-public"><?= $h($t('audience_public_link')) ?></span></dt><dd><?= $h($t('audience_public_link_help')) ?></dd></div>
<div><dt><span class="tag"><?= $h($t('rights_coauthors')) ?></span></dt><dd><?= $h($t('rights_coauthors_help')) ?></dd></div>
</dl>
<?php if ($rightsCourses === []): ?><div class="ins-empty"><b><?= $h($t('ins_courses_empty')) ?></b></div>
<?php else: ?><ul class="acc-courses">
<?php foreach ($rightsCourses as $course): $audience = $audienceKey($course); $open = $editingCourse === $course['key'] && $rightsReady;
    $names = array_map($subjectLabel, $course['access']['audience'] ?? []); ?>
<li id="cours-<?= $h($course['key']) ?>"<?= $open ? ' class="is-editing"' : '' ?>><div class="acc-course__row"><div><b><?= $h($local($course['title']) ?: $t('untitled_course')) ?></b>
<span class="ins-tags"><span class="tag t-<?= !empty($course['built_in']) ? 'builtin' : (!empty($course['archived']) ? 'archived' : (!empty($course['published']) ? 'pub' : 'draft')) ?>"><?= $h($t('ins_status_' . (!empty($course['built_in']) ? 'builtin' : (!empty($course['archived']) ? 'archived' : (!empty($course['published']) ? 'pub' : 'draft'))))) ?></span><span class="tag a-<?= $audience ?>"><?= $h($t('audience_' . $audience)) ?></span></span>
<?php if ($audience === 'restricted'): ?><small><?= $h($names !== [] ? implode(' · ', $names) : $t('audience_nobody')) ?></small><?php endif ?></div>
<span class="acc-actions"><?php if ($rightsReady && !$open): ?><a class="text-link" href="<?= $h($link('droits') . '?cours=' . rawurlencode($course['key'])) ?>#cours-<?= $h($course['key']) ?>"><?= $h($t('rights_course_edit')) ?></a><?php endif ?>
<?php if (empty($course['built_in'])): ?><a class="text-link" href="<?= $h($link('studio') . '?course=' . rawurlencode($course['key']) . '&share=1') ?>"><?= $h($t('rights_course_share')) ?></a><?php endif ?></span></div>
<?php if ($open): $accessCourse = $course; $accessData = ['options' => $options] + $course['access']; $accessReturn = 'droits'; require __DIR__ . '/course_access_form.php'; endif ?>
</li>
<?php endforeach ?></ul><?php endif ?>
</section>

<?php $autoSet = $autoSettings ?? ['enabled' => false, 'hours' => 72, 'excluded' => []]; ?>
<section class="ins-sec" id="validation-auto" aria-labelledby="acc-auto"><div class="ins-sec__head"><h2 id="acc-auto"><?= $h($t('auto_set_title')) ?></h2></div>
<p class="muted"><?= $h($t('auto_set_help')) ?></p>
<form method="post" action="<?= $h($link('action/auto_validation_save')) ?>" class="acc-form"><?= $csrfField ?><input type="hidden" name="course" value="academy">
<label class="acc-choice"><input type="checkbox" name="enabled" value="1"<?= !empty($autoSet['enabled']) ? ' checked' : '' ?>><span><b><?= $h($t('auto_set_enabled')) ?></b><small><?= $h($t('auto_set_enabled_help')) ?></small></span></label>
<label><span><?= $h($t('auto_set_hours')) ?></span><input type="number" name="hours" min="1" max="720" value="<?= (int) $autoSet['hours'] ?>" required></label>
<fieldset><legend><?= $h($t('auto_set_final')) ?></legend><p class="hint"><?= $h($t('auto_set_final_help')) ?></p>
<?php foreach ($rightsCourses as $autoCourse): ?><label class="acc-choice"><input type="checkbox" name="final_allowed[]" value="<?= $h($autoCourse['key']) ?>"<?= in_array($autoCourse['key'], $autoSet['final_allowed'] ?? [], true) ? ' checked' : '' ?>><span><?= $h($local($autoCourse['title']) ?: $t('untitled_course')) ?></span></label><?php endforeach ?></fieldset>
<fieldset><legend><?= $h($t('auto_set_excluded')) ?></legend><p class="hint"><?= $h($t('auto_set_excluded_help')) ?></p>
<?php foreach ($rightsCourses as $autoCourse): ?><label class="acc-choice"><input type="checkbox" name="excluded[]" value="<?= $h($autoCourse['key']) ?>"<?= in_array($autoCourse['key'], $autoSet['excluded'], true) ? ' checked' : '' ?>><span><?= $h($local($autoCourse['title']) ?: $t('untitled_course')) ?></span></label><?php endforeach ?></fieldset>
<div class="buttons"><button class="button"><?= $h($t('auto_set_save')) ?></button></div></form>
</section>

<section class="ins-sec" aria-labelledby="acc-qualif"><div class="ins-sec__head"><h2 id="acc-qualif"><?= $h($t('rights_qualifications')) ?></h2></div>
<p class="muted"><?= $h($t('rights_qualifications_help')) ?></p>
<div class="buttons"><a class="text-link" href="<?= $h(url('formation/certificates')) ?>"><?= $h($t('certificates_tool')) ?> ↗</a><?php if (!empty($rightsAdmin)): ?><a class="text-link" href="<?= $h(url('back-office/referentiels/qualifications/emetteurs')) ?>"><?= $h($t('rights_issuers_link')) ?> ↗</a><a class="text-link" href="<?= $h(url('back-office/roles-functions')) ?>"><?= $h($t('rights_roles_link')) ?> ↗</a><?php endif ?></div>
</section>
</div>
