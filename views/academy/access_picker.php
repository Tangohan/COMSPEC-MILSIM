<?php
/**
 * Choix des bénéficiaires d'un droit ou du public d'un cours : rôles d'accès, fonctions, unités (sous-unités comprises),
 * membres précis. Cases à cocher (jamais de saisie libre) ; un filtre aide dans les longues listes.
 *
 * @var array<string, array<int,string>> $pickerOptions
 * @var list<array{type:string,id:int}> $pickerSelected
 * @var string $pickerId
 */
$pickerSelected = $pickerSelected ?? [];
$pickerChecked = [];
foreach ($pickerSelected as $subject) {
    $pickerChecked[$subject['type'] . ':' . $subject['id']] = true;
}
$pickerGroups = ['role' => ['roles', 'subject_roles', 'subject_roles_help'], 'job' => ['jobs', 'subject_jobs', 'subject_jobs_help'],
    'unit' => ['units', 'subject_units', 'subject_units_help'], 'user' => ['users', 'subject_users', 'subject_users_help']];
?>
<div class="acc-picker">
<?php foreach ($pickerGroups as $type => [$field, $label, $help]): $choices = $pickerOptions[$type] ?? []; $selectedCount = count(array_filter(array_keys($choices), static fn (int $id): bool => isset($pickerChecked[$type . ':' . $id]))); ?>
<fieldset class="acc-group"><legend><?= $h($t($label)) ?><?php if ($selectedCount): ?> <span class="acc-count"><?= $selectedCount ?></span><?php endif ?></legend>
<p class="acc-help"><?= $h($t($help)) ?></p>
<?php if ($choices === []): ?><p class="acc-help"><i><?= $h($t('subject_none_available')) ?></i></p>
<?php else: ?>
<?php if (count($choices) > 8): ?><label class="acc-filter"><span class="sr-only"><?= $h($t('subject_filter')) ?> · <?= $h($t($label)) ?></span><input type="search" data-access-filter placeholder="<?= $h($t('subject_filter')) ?>"></label><?php endif ?>
<div class="acc-list" data-access-list><?php foreach ($choices as $id => $name): $checked = isset($pickerChecked[$type . ':' . $id]); ?>
<label class="acc-item"><input type="checkbox" name="<?= $field ?>[]" value="<?= (int) $id ?>"<?= $checked ? ' checked' : '' ?>><span><?= $h($name !== '' ? $name : '#' . $id) ?></span></label><?php endforeach ?></div>
<?php endif ?>
</fieldset>
<?php endforeach ?>
</div>
