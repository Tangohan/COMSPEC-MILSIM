<?php
declare(strict_types=1);
// Onglets communs du Workshop des langues et de l'atelier de traduction (/traduire-atak/workshop, /traduire-atak).
$tabActive = isset($tabActive) && is_string($tabActive) ? $tabActive : 'workshop';
$tabs = [
    'workshop' => [url('traduire-atak/workshop'), __('site.atakw_tab_workshop')],
    'atelier' => [url('traduire-atak'), __('site.atakw_tab_atelier')],
    'steam' => [url('traduire-atak') . '#workshop', __('site.atakw_tab_steam')],
];
?>
<nav class="atakw-tabs" aria-label="<?= htmlspecialchars(__('site.atakw_tabs_label'), ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach ($tabs as $key => [$href, $label]): ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="atakw-tab<?= $key === $tabActive ? ' is-active' : '' ?>"<?= $key === $tabActive ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
</nav>
