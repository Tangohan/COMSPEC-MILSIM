<?php
declare(strict_types=1);
/** Sous-navigation Exploitation numérique — onglets discrets, pas des CTA. */
$labSubnav = (string) ($labSubnav ?? 'hub');
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$labNavGroups = [
    'Travail' => [
        'hub' => ['', 'Vue d’ensemble'],
        'queue' => ['a-exploiter', 'À exploiter'],
        'supports' => ['supports', 'Supports'],
        'acquisitions' => ['acquisitions', 'Acquisitions'],
    ],
    'Analyse' => [
        'artefacts' => ['artefacts', 'Artefacts'],
        'analyses' => ['analyses', 'Signaux'],
        'communications' => ['communications', 'Communications'],
        'chronologies' => ['chronologies', 'Chronologie'],
    ],
    'Sortie' => [
        'rapports' => ['rapports', 'Rapports'],
    ],
];
?>
<nav class="lab-subnav" aria-label="Sections de l’exploitation numérique">
    <?php foreach ($labNavGroups as $groupLabel => $items): ?>
        <div class="lab-subnav__group">
            <span class="lab-subnav__group-label"><?= $h($groupLabel) ?></span>
            <div class="lab-subnav__links" role="list">
                <?php foreach ($items as $key => [$path, $label]): ?>
                    <?php
                    $href = $path === ''
                        ? url('atak/sse/exploitation-numerique')
                        : url('atak/sse/exploitation-numerique/' . $path);
                    $active = $labSubnav === $key;
                    ?>
                    <a
                        role="listitem"
                        class="lab-subnav__link<?= $active ? ' is-active' : '' ?>"
                        href="<?= $h($href) ?>"
                        <?= $active ? 'aria-current="page"' : '' ?>
                    ><?= $h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</nav>
