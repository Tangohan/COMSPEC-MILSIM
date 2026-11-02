<?php
/**
 * « Qui voit ce cours » : toute la communauté (par défaut), ou un public choisi ; brouillon privé tant qu'il n'est pas publié.
 * Utilisé dans la fenêtre « Partager » du Studio et sur l'écran des droits (cours intégrés compris).
 *
 * @var array $accessCourse
 * @var array{ready:bool, settings:array, audience:list<array{type:string,id:int}>, options:array} $accessData
 * @var string $accessReturn '' (Studio) ou 'droits'
 */
$accessReturn = $accessReturn ?? '';
$accessSettings = $accessData['settings'] ?? \App\Repositories\AcademyAccessRepository::defaults();
$accessRestricted = ($accessSettings['audience'] ?? 'community') === 'restricted';
$accessFormId = 'acc-' . preg_replace('/[^a-z0-9-]/', '', (string) $accessCourse['key']);
$accessDraft = empty($accessCourse['published']) && empty($accessCourse['built_in']);
?>
<?php if (empty($accessData['ready'])): ?><p class="acc-help"><?= $h($t('rights_pending')) ?></p>
<?php else: ?>
<form method="post" action="<?= $h($link('action/course_access_save')) ?>" class="acc-form" data-access-form><?= \App\Core\Csrf::field() ?><input type="hidden" name="course" value="<?= $h($accessCourse['key']) ?>"><?php if ($accessReturn !== ''): ?><input type="hidden" name="return" value="<?= $h($accessReturn) ?>"><?php endif ?>
<fieldset class="acc-audience"><legend><?= $h($t('audience_question')) ?></legend>
<label class="acc-choice"><input type="radio" name="audience" value="community"<?= !$accessRestricted ? ' checked' : '' ?> data-audience-choice><span><b><?= $h($t('audience_community')) ?></b><small><?= $h($t('audience_community_help')) ?></small></span></label>
<label class="acc-choice"><input type="radio" name="audience" value="restricted"<?= $accessRestricted ? ' checked' : '' ?> data-audience-choice><span><b><?= $h($t('audience_restricted')) ?></b><small><?= $h($t('audience_restricted_help')) ?></small></span></label>
</fieldset>
<div data-audience-picker<?= !$accessRestricted ? ' data-collapsed' : '' ?>>
<?php $pickerOptions = $accessData['options'] ?? []; $pickerSelected = $accessData['audience'] ?? []; $pickerId = $accessFormId; require __DIR__ . '/access_picker.php'; ?>
</div>
<?php if ($accessDraft): ?><label class="acc-choice acc-private"><input type="checkbox" name="private_draft" value="1"<?= !empty($accessSettings['private_draft']) ? ' checked' : '' ?>><span><b><?= $h($t('private_draft')) ?></b><small><?= $h($t('private_draft_help')) ?></small></span></label><?php endif ?>
<p class="acc-help"><?= $h($t('audience_always')) ?></p>
<button class="<?= $accessReturn === '' ? 'btn dark' : 'button small' ?>"><?= $h($t('audience_save')) ?></button>
</form>
<?php endif ?>
