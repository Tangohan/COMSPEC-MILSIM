<?php
declare(strict_types=1);
/**
 * Contenu JNET embarqué dans la coque back-office ATHENA.
 * @var string $jnetInnerView
 * @var string $activeNav
 * @var int $jnetUnreadMail
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$inner = (string) ($jnetInnerView ?? 'jnet.home');
$activeNav = (string) ($activeNav ?? 'home');
$unreadMail = (int) ($jnetUnreadMail ?? 0);
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');

$tabs = [
    ['id' => 'home', 'label' => 'Tableau d’unité', 'path' => 'jnet'],
    ['id' => 'unit', 'label' => 'Fiche d’unité', 'path' => 'jnet/unite'],
    ['id' => 'personnel', 'label' => 'Personnel', 'path' => 'jnet/personnel'],
    ['id' => 'operations', 'label' => 'Opérations', 'path' => 'jnet/operations'],
    ['id' => 'intelligence', 'label' => 'Renseignement', 'path' => 'jnet/renseignement'],
    ['id' => 'targets', 'label' => 'Cibles', 'path' => 'jnet/cibles'],
    ['id' => 'exploitation', 'label' => 'Exploitation', 'path' => 'jnet/exploitation'],
    ['id' => 'library', 'label' => 'Bibliothèque', 'path' => 'jnet/bibliotheque'],
    ['id' => 'inbox', 'label' => 'Messagerie', 'path' => 'jnet/courrier', 'count' => $unreadMail],
    ['id' => 'system', 'label' => 'Système', 'path' => 'jnet/systeme'],
];
?>
<div class="jnet-embed">
    <aside class="jnet-beta jnet-beta--bo" role="note">
        <span class="jnet-beta__tag">Version bêta</span>
        <p>
            L’extranet d’unité est encore en construction : la structure est posée, mais une partie
            des contenus peut être illustrative. Pour la conduite opérationnelle, privilégiez le mur,
            la carte et la messagerie habituels tant que chaque section n’est pas fiabilisée.
        </p>
    </aside>

    <nav class="jnet-bo-tabs" aria-label="Sections de l’extranet d’unité">
        <?php foreach ($tabs as $tab): ?>
            <?php
            $count = (int) ($tab['count'] ?? 0);
            $isActive = $activeNav === ($tab['id'] ?? '');
            ?>
            <a href="<?= $h(url((string) $tab['path'])) ?>" class="jnet-bo-tabs__item<?= $isActive ? ' is-active' : '' ?>">
                <?= $h((string) $tab['label']) ?>
                <?php if ($count > 0): ?><i><?= min(99, $count) ?></i><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($error): ?>
        <div class="jnet-flash jnet-flash--err" role="alert"><?= $h((string) $error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="jnet-flash jnet-flash--ok" role="status"><?= $h((string) $success) ?></div>
    <?php endif; ?>

    <div class="jnet-stage jnet-stage--bo">
        <?php
        $viewFile = base_path('views/' . str_replace('.', '/', $inner) . '.php');
        if (is_file($viewFile)) {
            require $viewFile;
        } else {
            echo '<p class="jnet-empty">Écran indisponible.</p>';
        }
        ?>
    </div>
</div>
