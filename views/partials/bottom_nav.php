<?php
/**
 * Navigation basse mobile — 5 slots.
 * Affiché uniquement si body.athena-has-bottom-nav (utilisateurs connectés, hors shells admin lourds).
 */
$bnPath = function_exists('navigation_current_path') ? (string) navigation_current_path() : '/';
$bnActive = static function (string $needle) use ($bnPath): bool {
    if ($needle === '/' || $needle === '') {
        return $bnPath === '/' || $bnPath === '';
    }
    return $bnPath === $needle || str_starts_with($bnPath, $needle . '/');
};
$items = [
    ['label' => 'Accueil', 'path' => 'hub', 'match' => 'hub', 'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z'],
    ['label' => 'Manœuvres', 'path' => 'manoeuvres', 'match' => 'manoeuvres', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5'],
    ['label' => 'Messages', 'path' => 'boite-reception', 'match' => 'boite-reception', 'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
    ['label' => 'Doctrine', 'path' => 'documents', 'match' => 'documents', 'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
    ['label' => 'Ma fiche', 'path' => 'personnel/me', 'match' => 'personnel/me', 'icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
];
?>
<nav class="athena-bottom-nav" aria-label="Navigation principale mobile">
    <?php foreach ($items as $it): ?>
        <?php
        $href = url($it['path']);
        $match = (string) $it['match'];
        $active = $bnActive($match) || ($match === 'manoeuvres' && ($bnActive('pointage') || $bnActive('evenements')));
        if ($match === 'personnel/me') {
            // La fiche est aussi atteignable par /dossier-operateur et /effectifs.
            $active = $active || $bnActive('dossier-operateur') || $bnActive('effectifs');
        }
        if ($match === 'boite-reception') {
            $active = $bnActive('boite-reception') || $bnActive('activite') || $bnActive('messages');
        }
        ?>
        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"<?= $active ? ' aria-current="page" class="is-active"' : '' ?>>
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?= htmlspecialchars($it['icon'], ENT_QUOTES, 'UTF-8') ?>"/></svg>
            <?= htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php endforeach; ?>
</nav>
<button type="button" class="athena-fab" data-portal-command-palette-open aria-label="Commandes rapides">
    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
</button>
