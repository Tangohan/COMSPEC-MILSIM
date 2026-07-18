<?php
declare(strict_types=1);
/**
 * Liens vers le hub juridique (pied de page, bandeaux).
 * Variable optionnelle : $legal_link_class (classes Tailwind pour chaque lien).
 */
$legal_link_class = $legal_link_class ?? 'text-slate-600 hover:text-emerald-700 font-medium';
$lc = htmlspecialchars($legal_link_class, ENT_QUOTES, 'UTF-8');
$legalSite = url('legal/site');
?>
<a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>" class="<?= $lc ?>">Politique &amp; conditions</a>
<a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>#mentions" class="<?= $lc ?>">Mentions légales</a>
<a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>#cgu" class="<?= $lc ?>">CGU</a>
<a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>#cgv" class="<?= $lc ?>">CGV</a>
<a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>#rgpd" class="<?= $lc ?>">Données personnelles</a>
<a href="<?= htmlspecialchars($legalSite, ENT_QUOTES, 'UTF-8') ?>#cookies" class="<?= $lc ?>">Cookies</a>
<a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $lc ?>">Exercer vos droits</a>
<button type="button" data-cookie-preferences class="<?= $lc ?> bg-transparent border-0 p-0 cursor-pointer underline-offset-2 hover:underline">Préférences cookies</button>
