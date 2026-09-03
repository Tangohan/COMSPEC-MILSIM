<?php
declare(strict_types=1);

/**
 * Navigation commune recentrée sur la fiche Effectifs, point d'entrée RH unique.
 *
 * @var int $memberHubUserId
 * @var 'effectifs'|'account'|'dossier' $memberHubCurrent
 * @var 'lms'|'bo' $memberHubTheme
 */
$memberHubUserId = (int) ($memberHubUserId ?? 0);
$memberHubCurrent = (string) ($memberHubCurrent ?? 'effectifs');
$memberHubTheme = (string) ($memberHubTheme ?? 'lms');
if ($memberHubUserId < 1) {
    return;
}

$hubLinks = [[
    'id' => 'effectifs',
    'label' => 'Fiche Effectifs complète',
    'hint' => 'Compte, identité, affectation et suivi RH au même endroit',
    'href' => effectifs_workspace_url('membres/' . $memberHubUserId),
]];
$mod = $memberHubTheme === 'bo' ? 'member-hub-nav--bo' : 'member-hub-nav--lms';
?>
<nav class="member-hub-nav <?= htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') ?>" aria-label="Espaces du membre">
    <p class="member-hub-nav__lead">Point d’entrée unique : consultez et pilotez toute la situation du membre depuis sa fiche Effectifs.</p>
    <ul class="member-hub-nav__list">
        <?php foreach ($hubLinks as $link): ?>
            <?php $active = $memberHubCurrent === $link['id']; ?>
            <li>
                <a
                    class="member-hub-nav__item<?= $active ? ' is-active' : '' ?>"
                    href="<?= htmlspecialchars((string) $link['href'], ENT_QUOTES, 'UTF-8') ?>"
                    <?= $active ? 'aria-current="page"' : '' ?>
                >
                    <strong><?= htmlspecialchars((string) $link['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars((string) $link['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
