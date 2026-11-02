<?php
declare(strict_types=1);

/**
 * Onglets de la page Qualifications : le référentiel de la communauté et le catalogue de référence (brevets et
 * qualifications par pays et OTAN, à importer). $rqTab : 'referentiel' ou 'catalogue'.
 */
$rqTab = (string) ($rqTab ?? 'referentiel');
$rqTabH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$rqTabs = [
    'referentiel' => ['Référentiel de la communauté', url('back-office/referentiels/qualifications')],
    'catalogue' => ['Catalogue de référence', url('back-office/referentiels/qualifications/catalogue')],
];
?>
<nav class="rq-tabs" aria-label="Qualifications">
    <?php foreach ($rqTabs as $key => [$label, $href]): ?>
    <a class="rq-tabs__tab<?= $key === $rqTab ? ' is-on' : '' ?>" href="<?= $rqTabH($href) ?>"<?= $key === $rqTab ? ' aria-current="page"' : '' ?>><?= $rqTabH($label) ?></a>
    <?php endforeach; ?>
</nav>
