<?php
declare(strict_types=1);
/**
 * Liens vers les pages légales (pied de page, bandeaux).
 * Variable optionnelle : $legal_link_class (classes Tailwind pour chaque lien).
 */
$legal_link_class = $legal_link_class ?? 'text-slate-600 hover:text-emerald-700 font-medium';
$lc = htmlspecialchars($legal_link_class, ENT_QUOTES, 'UTF-8');
?>
<a href="<?= htmlspecialchars(url('mentions-legales')) ?>" class="<?= $lc ?>">Mentions légales</a>
<a href="<?= htmlspecialchars(url('cgu')) ?>" class="<?= $lc ?>">CGU</a>
<a href="<?= htmlspecialchars(url('cgv')) ?>" class="<?= $lc ?>">CGV</a>
<a href="<?= htmlspecialchars(url('donnees-personnelles')) ?>" class="<?= $lc ?>">Données personnelles</a>
<a href="<?= htmlspecialchars(url('cookies')) ?>" class="<?= $lc ?>">Cookies</a>
<a href="<?= htmlspecialchars(url('demande-donnees')) ?>" class="<?= $lc ?>">Exercer vos droits</a>
<button type="button" data-cookie-preferences class="<?= $lc ?> bg-transparent border-0 p-0 cursor-pointer underline-offset-2 hover:underline">Préférences cookies</button>
