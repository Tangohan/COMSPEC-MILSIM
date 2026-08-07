<?php
declare(strict_types=1);
/** Sous-navigation Atelier de préparation. */
$devSubnav = (string) ($devSubnav ?? 'hub');
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$items = [
    'hub' => ['', 'Vue d’ensemble'],
    'modeles' => ['modeles', 'Modèles de mission'],
];
?>
<nav class="lab-subnav" aria-label="Sections de l’atelier de préparation">
    <div class="lab-subnav__group">
        <span class="lab-subnav__group-label">Atelier</span>
        <div class="lab-subnav__links" role="list">
            <?php foreach ($items as $key => [$path, $label]): ?>
                <?php
                $href = $path === ''
                    ? url('atak/sse/dev')
                    : url('atak/sse/dev/' . $path);
                $active = $devSubnav === $key ? ' is-active' : '';
                ?>
                <a class="lab-subnav__link<?= $active ?>" href="<?= $h($href) ?>" role="listitem"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
