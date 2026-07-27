<?php
declare(strict_types=1);

/**
 * Fil vertical public (Reels) — un média par écran, défilement accroché.
 *
 * @var array<string,mixed> $tenant
 * @var list<array<string,mixed>> $reelsFeedItems
 * @var array<string,mixed>|null $tenantBranding
 */

$slug = (string) ($tenant['slug'] ?? '');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$items = is_array($reelsFeedItems ?? null) ? $reelsFeedItems : [];
$backHref = url('c/' . rawurlencode($slug));
$galleryHref = url('c/' . rawurlencode($slug) . '/medias');
$mediaCount = count($items);
$mediaLikesEnabled = !empty($mediaLikesEnabled);
$mediaViewerCanLike = !empty($mediaViewerCanLike);
$mediaLikeCsrf = \App\Core\Csrf::token();
$mediaLoginUrl = url('login');

$tenantBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];
$brandPrimary = trim((string) ($tenantBranding['primary_color'] ?? ''));
$brandAccent = trim((string) ($tenantBranding['accent_color'] ?? ''));
$clStyle = '';
if ($brandPrimary !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandPrimary)) {
    $clStyle .= '--cr-tenant-primary:' . $brandPrimary . ';';
}
if ($brandAccent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandAccent)) {
    $clStyle .= '--cr-tenant-accent:' . $brandAccent . ';';
}
?>
<div
    class="community-reels"
    <?= $clStyle !== '' ? ' style="' . htmlspecialchars($clStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
    data-reels-root
    <?php if ($mediaLikesEnabled): ?>
    data-media-likes="1"
    data-media-likes-csrf="<?= htmlspecialchars($mediaLikeCsrf, ENT_QUOTES, 'UTF-8') ?>"
    data-media-likes-auth="<?= $mediaViewerCanLike ? '1' : '0' ?>"
    data-media-likes-login="<?= htmlspecialchars($mediaLoginUrl, ENT_QUOTES, 'UTF-8') ?>"
    <?php endif; ?>
>
    <header class="community-reels__top">
        <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="community-reels__back" aria-label="Retour à <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <span><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <p class="community-reels__brand">Fil média</p>
        <a href="<?= htmlspecialchars($galleryHref, ENT_QUOTES, 'UTF-8') ?>" class="community-reels__gallery-link">Galerie</a>
    </header>

    <?php if ($items === []): ?>
    <div class="community-reels__empty">
        <p class="community-reels__empty-title">Rien à faire défiler pour l’instant</p>
        <p class="community-reels__empty-text">Les images et vidéos publiées par la communauté apparaîtront ici, une par écran.</p>
        <a class="community-reels__empty-cta" href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>">Retour à la fiche communauté</a>
    </div>
    <?php else: ?>
    <div class="community-reels__scroller" data-reels-scroller tabindex="0" aria-label="Fil média de <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($items as $index => $mi): ?>
            <?php
            $mk = (string) ($mi['media_kind'] ?? 'image');
            $mtitle = trim((string) ($mi['title'] ?? ''));
            $mcap = trim((string) ($mi['caption'] ?? ''));
            $murl = \App\Support\CommunityMediaDetails::publicUrl(isset($mi['storage_path']) ? (string) $mi['storage_path'] : null);
            $membed = \App\Support\CommunityMediaDetails::embedUrl(isset($mi['external_url']) ? (string) $mi['external_url'] : null);
            $regions = \App\Support\CommunityMediaDetails::parseBlurRegions($mi['blur_regions_json'] ?? null);
            $poster = \App\Support\CommunityMediaDetails::publicUrl(isset($mi['poster_path']) ? (string) $mi['poster_path'] : null)
                ?? \App\Support\CommunityMediaDetails::publicUrl(isset($mi['thumbnail_path']) ? (string) $mi['thumbnail_path'] : null);
            $mediaItemId = (int) ($mi['id'] ?? 0);
            $likesCount = (int) ($mi['likes_count'] ?? 0);
            $likedByViewer = !empty($mi['liked_by_viewer']);
            $likeUrl = $mediaItemId > 0
                ? url('c/' . rawurlencode($slug) . '/medias/' . $mediaItemId . '/like')
                : '';
            $alt = $mtitle !== '' ? $mtitle : ($mk === 'image' ? 'Image de la communauté' : 'Vidéo de la communauté');
            $isVideo = $mk === 'short_video' && $murl;
            $isEmbed = $mk === 'long_video' && $membed;
            $isImage = $mk === 'image' && $murl;
            ?>
        <article
            class="community-reels__slide"
            data-reels-slide
            data-media-kind="<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>"
            data-slide-index="<?= (int) $index ?>"
            <?php if ($mediaLikesEnabled && $mediaItemId > 0): ?>
            data-media-id="<?= $mediaItemId ?>"
            data-like-count="<?= $likesCount ?>"
            data-liked="<?= $likedByViewer ? '1' : '0' ?>"
            data-like-url="<?= htmlspecialchars($likeUrl, ENT_QUOTES, 'UTF-8') ?>"
            <?php endif; ?>
        >
            <div class="community-reels__stage">
                <?php if ($isVideo): ?>
                <video
                    class="community-reels__media community-reels__media--video"
                    src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>"
                    <?php if ($poster): ?>poster="<?= htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                    playsinline
                    muted
                    loop
                    preload="<?= $index === 0 ? 'auto' : 'metadata' ?>"
                    data-reels-video
                ></video>
                <?php elseif ($isImage): ?>
                <div class="community-reels__blur-host">
                    <img
                        class="community-reels__media community-reels__media--image"
                        src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>"
                        <?php if ($index > 0): ?>loading="lazy"<?php endif; ?>
                    >
                    <?php foreach ($regions as $reg): ?>
                    <span class="community-reels__blur-patch" style="left:<?= htmlspecialchars((string) $reg['x']) ?>%;top:<?= htmlspecialchars((string) $reg['y']) ?>%;width:<?= htmlspecialchars((string) $reg['w']) ?>%;height:<?= htmlspecialchars((string) $reg['h']) ?>%;"></span>
                    <?php endforeach; ?>
                </div>
                <?php elseif ($isEmbed): ?>
                <div class="community-reels__embed-wrap" data-reels-embed-wrap data-embed-src="<?= htmlspecialchars($membed, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="community-reels__embed-placeholder" aria-hidden="true"></div>
                    <p class="community-reels__embed-hint">Vidéo externe — appuyez pour lancer</p>
                </div>
                <?php else: ?>
                <div class="community-reels__placeholder" aria-hidden="true"></div>
                <?php endif; ?>
            </div>

            <div class="community-reels__overlay" aria-hidden="true"></div>

            <aside class="community-reels__actions">
                <?php if ($isVideo): ?>
                <button type="button" class="community-reels__action community-reels__mute" data-reels-mute aria-pressed="true" aria-label="Activer le son">
                    <svg class="community-reels__icon community-reels__icon--muted" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.5 9.5v5h3.2L12 18.8V5.2L7.7 9.5H4.5zm11.2 1.1 1.4-1.4 1.4 1.4 1.4-1.4-1.4-1.4 1.4-1.4-1.4-1.4-1.4 1.4-1.4-1.4-1.4 1.4 1.4 1.4-1.4 1.4 1.4 1.4z"/></svg>
                    <svg class="community-reels__icon community-reels__icon--unmuted" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.5 9.5v5h3.2L12 18.8V5.2L7.7 9.5H4.5zM15.2 8.3a4.2 4.2 0 0 1 0 7.4v-1.7a2.5 2.5 0 0 0 0-4V8.3zm0-3.2v1.7a5.9 5.9 0 0 1 0 10.7v1.7a7.6 7.6 0 0 0 0-14.1z"/></svg>
                </button>
                <?php endif; ?>
                <?php if ($mediaLikesEnabled && $mediaItemId > 0): ?>
                <button
                    type="button"
                    class="community-reels__action community-reels__like<?= $likedByViewer ? ' is-liked' : '' ?>"
                    data-media-like
                    aria-pressed="<?= $likedByViewer ? 'true' : 'false' ?>"
                    aria-label="<?= $likedByViewer ? 'Retirer mon j’aime' : 'J’aime ce média' ?>"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.2-4.35-9.6-8.4C.6 9.3 2.1 5.7 5.7 5.1c1.95-.3 3.75.6 4.8 2.1 1.05-1.5 2.85-2.4 4.8-2.1 3.6.6 5.1 4.2 3.3 7.5C19.2 16.65 12 21 12 21z" fill="currentColor"/></svg>
                    <span class="community-reels__like-count" data-like-count-label><?= $likesCount > 0 ? (int) $likesCount : '' ?></span>
                </button>
                <?php endif; ?>
            </aside>

            <div class="community-reels__meta">
                <?php if ($mtitle !== ''): ?>
                <h2 class="community-reels__title"><?= htmlspecialchars($mtitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <?php endif; ?>
                <?php if ($mcap !== ''): ?>
                <p class="community-reels__caption"><?= htmlspecialchars($mcap, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($mtitle === '' && $mcap === ''): ?>
                <p class="community-reels__caption community-reels__caption--soft"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="community-reels__hint" data-reels-hint>Faites défiler pour voir la suite</p>
    <?php endif; ?>
</div>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/community-reels.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
