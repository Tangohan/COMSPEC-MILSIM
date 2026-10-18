<?php
declare(strict_types=1);
/**
 * Visionneuse d’une adresse web (Google Docs / Sheets / Slides / Drawings, draw.io, PDF, vidéo…)
 * avec bouton « Ouvrir dans un nouvel onglet ». Si le site ne s’intègre pas de façon connue,
 * on tente quand même l’affichage quand $viewerForceFrame est vrai, sinon on montre le lien seul.
 *
 * @var string $viewerUrl
 * @var string|null $viewerTitle
 * @var bool|null $viewerForceFrame
 * @var bool|null $viewerCompact
 */
$viewerUrl = trim((string) ($viewerUrl ?? ''));
$viewerTitle = trim((string) ($viewerTitle ?? ''));
$viewerForceFrame = !empty($viewerForceFrame);
$viewerCompact = !empty($viewerCompact);
if ($viewerUrl === '') {
    return;
}
$vEmbed = function_exists('training_url_viewer_embed_src') ? training_url_viewer_embed_src($viewerUrl) : null;
$vHref = function_exists('training_lms_resource_external_href') ? training_lms_resource_external_href($viewerUrl) : null;
if ($vHref === null && $vEmbed !== null && str_starts_with($viewerUrl, '/')) {
    $vHref = $viewerUrl;
}
$vFrame = $vEmbed;
if ($vFrame === null && $viewerForceFrame && $vHref !== null && preg_match('#^https://#i', $viewerUrl)) {
    $vFrame = $viewerUrl;
}
$vProvider = function_exists('training_url_viewer_provider_label') ? training_url_viewer_provider_label($viewerUrl) : 'Lien';
$vGuessed = $vEmbed === null;
?>
<div class="lms-url-viewer<?= $viewerCompact ? ' lms-url-viewer--compact' : '' ?>">
    <div class="lms-url-viewer__bar">
        <span class="lms-url-viewer__provider"><?= htmlspecialchars($vProvider, ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($viewerTitle !== ''): ?>
        <span class="lms-url-viewer__title"><?= htmlspecialchars($viewerTitle, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <?php if ($vHref !== null): ?>
        <a class="lms-url-viewer__open" href="<?= htmlspecialchars($vHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Ouvrir dans un nouvel onglet ↗</a>
        <?php endif; ?>
    </div>
    <?php if ($vFrame !== null): ?>
    <div class="lms-url-viewer__frame">
        <iframe src="<?= htmlspecialchars($vFrame, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($viewerTitle !== '' ? $viewerTitle : $vProvider, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
    <p class="lms-url-viewer__hint">
        <?= $vGuessed
            ? 'Si rien ne s’affiche, ce site refuse l’affichage intégré : utilisez « Ouvrir dans un nouvel onglet ».'
            : 'Si le document reste vide, vérifiez qu’il est partagé en lecture (« Tous les utilisateurs disposant du lien »).' ?>
    </p>
    <?php elseif ($vHref === null): ?>
    <p class="lms-url-viewer__hint">Ce lien n’est pas utilisable. Demandez au formateur de le corriger.</p>
    <?php endif; ?>
</div>
