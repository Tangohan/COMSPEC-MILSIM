<?php
declare(strict_types=1);
/**
 * Widget Cerbère : bouton rond fixé en bas à droite de toutes les pages du portail SSE, et tiroir à droite (feuille plein écran
 * sur téléphone). Inclus UNE fois, par views/atak/sse/_layout.php. Le contenu du tiroir est chargé à l'ouverture
 * (SseCerbereWidgetController, views/atak/sse/_cerbere_panel.php) ; le badge, lui, est calculé ici, sans requête de plus.
 *
 * @var array{enabled: bool, ctx: array<string, string>, badge: array{count: int, tone: string, label: string, aria: string}, url: string, quiet: bool} $sseCerbere
 * @var string $currentPath
 */
$cwH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cwBadge = $sseCerbere['badge'];
$cwCtx = $sseCerbere['ctx'];
$cwPath = (string) (\App\Services\Sse\Cerbere\SseCerbereContext::sanitizePage((string) $currentPath) ?? 'atak/sse/accueil');
?>
<div class="cw<?= $sseCerbere['quiet'] ? ' cw--quiet' : '' ?>" data-cw data-cw-url="<?= $cwH($sseCerbere['url']) ?>" data-cw-page="<?= $cwH($cwPath) ?>" hidden>
    <button type="button" class="cw-fab" data-cw-open aria-haspopup="dialog" aria-controls="cw-drawer" aria-expanded="false"
            title="Cerbère">
        <span class="sr-only">Ouvrir Cerbère</span>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3.2 19 6.4v5.3c0 4.3-2.8 7.4-7 9.1-4.2-1.7-7-4.8-7-9.1V6.4z"/><circle cx="12" cy="11.4" r="2.5"/><path d="M8.6 11.4C9.6 9.6 10.7 8.9 12 8.9s2.4.7 3.4 2.5"/></svg>
        <span class="cw-badge cw-badge--<?= $cwH($cwBadge['tone']) ?>" data-cw-badge data-count="<?= (int) $cwBadge['count'] ?>"<?= $cwBadge['count'] < 1 ? ' hidden' : '' ?>><?= $cwH($cwBadge['label']) ?></span>
        <?php if ($cwBadge['count'] > 0): ?><span class="sr-only">à trier sur cette page</span><?php endif; ?>
    </button>
    <template data-cw-t="loading"><p class="cw-empty" role="status">Chargement de Cerbère…</p></template>
    <template data-cw-t="error"><div class="cw-empty" role="alert"><p>Cerbère n’a pas pu charger ce contenu.</p><p><button type="button" class="cw-btn" data-cw-retry>Réessayer</button></p></div></template>
    <dialog class="cw-drawer" id="cw-drawer" aria-labelledby="cw-title">
        <div class="cw-panel">
            <header class="cw-head">
                <div class="cw-head__text">
                    <h2 id="cw-title" tabindex="-1">Cerbère</h2>
                    <p class="cw-ctx">Vous êtes sur : <?= $cwH($cwCtx['label']) ?></p>
                </div>
                <button type="button" class="cw-close" data-cw-close aria-label="Fermer Cerbère" title="Fermer">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </header>
            <div class="cw-body" data-cw-body aria-live="polite" aria-busy="false"></div>
            <footer class="cw-foot">
                <p class="cw-foot__text">Ce bouton peut se masquer pour vous seul ; il se remet depuis la page « Cerbères ».</p>
                <button type="button" class="cw-btn" data-cw-hide>Masquer le bouton Cerbère</button>
            </footer>
        </div>
    </dialog>
</div>
