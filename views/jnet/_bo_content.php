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
    ['id' => 'home', 'label' => 'Espace commun', 'path' => 'jnet'],
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
<div class="jnet-embed" data-jn-root>
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
    <div class="jn-loading" role="status" aria-live="polite" hidden>
        <span class="jn-loading__bar" aria-hidden="true"></span>
        <span class="jn-loading__panel"><span class="jn-loading__spin" aria-hidden="true"></span><span data-jn-loading-text>Chargement de l’espace…</span></span>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('[data-jn-root]');
    if (!root) return;
    var box = root.querySelector('.jn-loading');
    var text = root.querySelector('[data-jn-loading-text]');
    function show(message) {
        if (text && message) text.textContent = message;
        box.hidden = false;
        root.classList.add('is-loading');
    }
    function hide() {
        box.hidden = true;
        root.classList.remove('is-loading');
    }
    // Navigation interne : on affiche le chargement tant que la page suivante se construit.
    root.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.target && a.target !== '_self') return;
        var href = a.getAttribute('href') || '';
        if (href.charAt(0) === '#' || a.hasAttribute('download')) return;
        var url;
        try { url = new URL(a.href, location.href); } catch (err) { return; }
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        show(a.closest('.jn-rail, .jn-tree, .jn-crumbs, .jn-cards') ? 'Ouverture de l’espace…' : 'Chargement…');
    });
    root.addEventListener('submit', function (e) {
        var btn = e.target.querySelector('[data-jn-busy]') || e.target.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            if (btn.getAttribute('data-jn-busy')) btn.textContent = btn.getAttribute('data-jn-busy');
        }
        show('Enregistrement…');
    });
    // Retour arrière (cache navigateur) : la page revient sans recharger, on retire le voile.
    window.addEventListener('pageshow', hide);
})();
</script>
