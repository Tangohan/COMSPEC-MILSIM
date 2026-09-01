<?php
declare(strict_types=1);

$current = (string) ($rhShortcutCurrent ?? '');
$h = $h ?? static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$items = [
    [
        'id' => 'documents',
        'href' => effectifs_workspace_url('documents-rh'),
        'title' => 'Documents RH',
        'hint' => 'Coffre du dossier',
    ],
    [
        'id' => 'mobilite',
        'href' => effectifs_workspace_url('mobilite'),
        'title' => 'Mobilité',
        'hint' => 'Mouvements internes',
    ],
    [
        'id' => 'vivier',
        'href' => effectifs_workspace_url('vivier'),
        'title' => 'Vivier',
        'hint' => 'Succession',
    ],
    [
        'id' => 'alertes',
        'href' => effectifs_workspace_url('alertes'),
        'title' => 'Alertes RH',
        'hint' => 'Ce qui demande un suivi',
    ],
    [
        'id' => 'doublons',
        'href' => effectifs_workspace_url('doublons'),
        'title' => 'Fiches jumelles',
        'hint' => 'Valeurs identiques entre dossiers',
    ],
];
?>
<nav class="eff-rh-shortcuts" aria-label="Autres pages du dossier RH">
    <?php foreach ($items as $item): ?>
        <?php if ($item['id'] === $current) {
            continue;
        } ?>
        <a class="eff-rh-shortcuts__item" href="<?= $h((string) $item['href']) ?>">
            <strong><?= $h((string) $item['title']) ?></strong>
            <span><?= $h((string) $item['hint']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
