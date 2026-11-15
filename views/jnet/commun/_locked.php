<?php
/**
 * Salon réservé au renseignement, porte fermée : une explication, aucun titre, aucun nombre, aucune trace de ce que le salon
 * contiendrait (jamais de fuite d'existence). La porte (membre habilité au bureau SSE et engagement de confidentialité
 * accepté) est décidée par JnetDashboardService::mayReadIntelligence, jamais ici.
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="jc-body">
    <div class="jc-locked" role="note">
        <span class="jc-locked__ic"><?= JnetCommonRules::icon('lock') ?></span>
        <h3 class="jc-locked__t">Salon réservé</h3>
        <p>Ce salon est réservé aux membres habilités au bureau SSE qui ont accepté l’engagement de confidentialité de la session.</p>
        <p>Tant que ces deux conditions ne sont pas remplies, rien du renseignement n’est affiché ici.</p>
        <p><a class="jn-btn jn-btn--small" href="<?= $h(url('atak/sse')) ?>">Ouvrir le portail SSE</a></p>
    </div>
</div>
