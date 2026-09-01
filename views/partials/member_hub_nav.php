<?php
declare(strict_types=1);

/**
 * Navigation commune : fiche Effectifs, compte, dossier personnel.
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

$hubLinks = [
    [
        'id' => 'effectifs',
        'label' => 'Fiche Effectifs',
        'hint' => 'Situation, ancienneté, unité',
        'href' => effectifs_workspace_url('membres/' . $memberHubUserId),
    ],
    [
        'id' => 'account',
        'label' => 'Compte',
        'hint' => 'Connexion, rôles, grade',
        'href' => url('back-office/users/' . $memberHubUserId . '/edit'),
    ],
    [
        'id' => 'dossier',
        'label' => 'Dossier personnel',
        'hint' => 'Identité opérationnelle',
        'href' => url('personnel/' . $memberHubUserId . '/edit'),
    ],
];
$mod = $memberHubTheme === 'bo' ? 'member-hub-nav--bo' : 'member-hub-nav--lms';
?>
<nav class="member-hub-nav <?= htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') ?>" aria-label="Espaces du membre">
    <p class="member-hub-nav__lead">Trois écrans, un même membre : la fiche pour le quotidien RH, le compte pour l’accès, le dossier pour l’identité opérationnelle.</p>
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
