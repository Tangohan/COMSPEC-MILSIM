<?php
declare(strict_types=1);
/** Passage aux fichiers : sous-navigation du registre, bandeau SIMULATION et onglets de la section. */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$filesTab = (string) ($filesTab ?? 'consultation');
require dirname(__DIR__) . '/papers/_subnav.php';
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-files.css')) . '">';
$filesTabs = [
    'consultation' => ['', 'Consultation'],
    'journal' => ['journal', 'Journal des consultations'],
    'mentions' => ['mentions', 'Mentions'],
    'seek' => ['seek', 'Fiches SEEK II'],
];
?>
<div class="fq-sim" role="note">
    <strong>SIMULATION</strong>
    <span>Fichiers fictifs : seules les données du registre de votre communauté sont consultées. Aucune base réelle n’est interrogée.</span>
</div>
<nav class="fq-tabs" aria-label="Passage aux fichiers">
    <?php foreach ($filesTabs as $key => [$path, $label]): ?>
        <a class="<?= $filesTab === $key ? 'is-active' : '' ?>" href="<?= $h(url('atak/sse/fichiers' . ($path !== '' ? '/' . $path : ''))) ?>" <?= $filesTab === $key ? 'aria-current="page"' : '' ?>><?= $h($label) ?></a>
    <?php endforeach; ?>
</nav>
