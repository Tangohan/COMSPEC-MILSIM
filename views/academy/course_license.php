<?php if (!empty($licenseState['completed']) && !empty($licenseState['license'])): $licence=$licenseState['license']; ?>
<section class="academy-license" aria-label="<?= $h($t('simulation_license')) ?>">
<p class="eyebrow">ATHENA ACADEMY · <?= $h($t('simulation_license')) ?></p>
<h3><?= $h($local($licenseState['snapshot']['title'])) ?></h3>
<p class="academy-license__number"><?= $h($licence['number']) ?></p>
<dl><div><dt><?= $h($t('learner')) ?></dt><dd>#<?= (int)($licenseState['learner_id']??\App\Core\Session::get('user_id')) ?></dd></div><div><dt><?= $h($t('date')) ?></dt><dd><?= $h((new DateTimeImmutable('@'.(int)$licence['issued_at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i')) ?></dd></div><div><dt><?= $h($t('validated_by')) ?></dt><dd>#<?= (int)$licence['validated_by'] ?></dd></div><div><dt><?= $h($t('course_version')) ?></dt><dd><?= $h($licence['course_version']) ?></dd></div></dl>
<p><?= $h($t('license_scope')) ?></p><button class="button outline small" type="button" data-print><?= $h($t('print_license')) ?> ↗</button>
</section>
<?php endif ?>
