<?php
declare(strict_types=1);

/**
 * Éditeur du bloc personnalisé de l'accueil (/back-office/accueil/bloc) : le même éditeur en place que la vitrine
 * publique (community-page-editor.js), avec la bibliothèque de blocs de l'accueil.
 *
 * @var array{blocks: list<array<string, mixed>>} $blocksLayout
 * @var array<string, mixed> $blocksConfig
 * @var array<int, array<string, mixed>> $blocksSheets
 */
$bbH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bbRender = static function (array $pbBlock, array $pbSheets): void {
    $pbEditing = true;
    require base_path('views/community/partials/page_block.php');
};
?>
<div class="pil bo-blocks-page" data-pilotage>
    <p class="bsh-page__back"><a class="pil-link" href="<?= $bbH(url('back-office')) ?>">← Retour au tableau de bord</a></p>
    <div class="bo-blocks" data-cl-editing="1">
        <div class="cl-body bo-blocks__stack">
            <?php foreach ($blocksLayout['blocks'] as $bbBlock): ?>
                <?php $bbRender($bbBlock, $blocksSheets); ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script type="application/json" id="pb-editor-config"><?= json_encode($blocksConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="pbe-dock" role="toolbar" aria-label="Édition du bloc" data-pbe-dock>
    <div class="pbe-dock__status" aria-live="polite"><span class="pbe-dot pbe-dot--saved" data-pbe-dot></span><span data-pbe-status>Enregistré</span></div>
    <span class="pbe-sep" aria-hidden="true"></span>
    <button type="button" class="pbe-btn" data-pbe-action="library">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        <span class="pbe-btn__txt">Ajouter un bloc</span>
    </button>
    <span class="pbe-sep" aria-hidden="true"></span>
    <button type="button" class="pbe-btn pbe-btn--primary" data-pbe-action="publish">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
        <span class="pbe-btn__txt">Terminé</span>
    </button>
</div>
<div class="pbe-scrim" hidden data-pbe-scrim></div>
<aside class="pbe-panel" aria-label="Réglages" aria-hidden="true" data-pbe-panel>
    <div class="pbe-panel__head">
        <div>
            <p class="pbe-panel__kicker" data-pbe-panel-kicker></p>
            <h2 class="pbe-panel__title" data-pbe-panel-title></h2>
        </div>
        <button type="button" class="pbe-icon" data-pbe-action="close" aria-label="Fermer le panneau">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
    <div class="pbe-panel__body" data-pbe-panel-body></div>
</aside>
<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-pbe-file>
<script defer src="<?= $bbH(asset_url('assets/js/community-page-editor-home.js')) ?>"></script>
<script defer src="<?= $bbH(asset_url('assets/js/community-page-editor.js')) ?>"></script>
