<?php
declare(strict_types=1);
// Progression d'un membre sur les prérequis d'un emploi : $holderPrereq = {met, total, progress, complete}.
$meterTotal = (int) ($holderPrereq['total'] ?? 0);
$meterMet = (int) ($holderPrereq['met'] ?? 0);
?>
<span class="eff-prereq-meter"><?php if (!empty($holderPrereq['waived'])): ?><span class="eff-prereq-state is-waived">Dérogation</span><?php elseif (!empty($holderPrereq['complete'])): ?><span class="eff-prereq-state is-ok">Prérequis remplis</span><?php else: ?><span class="eff-prereq-state is-missing">Prérequis manquants</span><?php endif ?><progress max="<?= max(1, $meterTotal) ?>" value="<?= $meterMet ?>" aria-label="<?= $h($meterMet . ' / ' . $meterTotal) ?>"></progress><span><?= $meterMet ?> / <?= $meterTotal ?></span></span>
