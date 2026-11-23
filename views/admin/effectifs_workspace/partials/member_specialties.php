<?php
declare(strict_types=1);
use App\Support\MilitarySpecialtyLabels;
$spPanel = is_array($memberSpecialties ?? null) ? $memberSpecialties : ['ready' => false, 'rows' => [], 'url' => ''];
?>
<article class="eff-fm-card" id="specialites-militaires">
    <div class="eff-fm-card__head"><h3>Spécialités militaires</h3></div>
    <p class="eff-fm-lead">Un code identifie un métier dans une armée. Son niveau de maîtrise est distinct du grade, de l’emploi et des qualifications comme JTAC.</p>
    <?php if (!$spPanel['ready']): ?><p>La saisie structurée des spécialités sera disponible après la mise à jour du site.</p>
    <?php elseif ($spPanel['rows'] === []): ?><p>Aucune spécialité inscrite au dossier.</p>
    <?php else: ?><ul><?php foreach ($spPanel['rows'] as $spRow): ?><li><strong><?= $h(MilitarySpecialtyLabels::title($spRow)) ?></strong> · <?= $h(MilitarySpecialtyLabels::status((string) $spRow['status'])) ?><?php if (($spRow['level_code'] ?? '') !== '' && $spRow['level_code'] !== null): ?> · Niveau <?= $h($spRow['level_code']) ?> : <?= $h($spRow['level_label_fr'] ?? '') ?><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($spPanel['url'] !== ''): ?><p><a class="eff-btn" href="<?= $h($spPanel['url']) ?>">Consulter les codes et les explicatifs<?= !empty($canEditProfiles) ? ' / gérer ce dossier' : '' ?></a></p><?php endif; ?>
</article>
