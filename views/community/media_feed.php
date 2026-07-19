<?php
declare(strict_types=1);

/**
 * Galerie médias publique — grille / masonry, ouverture au clic dans une lightbox.
 *
 * @var array<string,mixed> $tenant
 * @var list<array<string,mixed>> $mediaFeedItems
 * @var array<string,mixed>|null $tenantBranding
 */

$slug = (string) ($tenant['slug'] ?? '');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$items = is_array($mediaFeedItems ?? null) ? $mediaFeedItems : [];
$backHref = url('c/' . rawurlencode($slug));
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
    $clStyle .= '--cl-tenant-primary:' . $brandPrimary . ';';
}
if ($brandAccent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandAccent)) {
    $clStyle .= '--cl-tenant-accent:' . $brandAccent . ';';
}

/* Page dédiée : toujours une grille (pas de carrousel horizontal). */
$galleryLayout = 'featured';
if ($mediaCount >= 4) {
    $galleryLayout = 'masonry';
} elseif ($mediaCount >= 2) {
    $galleryLayout = 'cluster';
}
?>
<div class="community-landing community-media-page -mx-4 sm:-mx-6 lg:-mx-8"<?= $clStyle !== '' ? ' style="' . htmlspecialchars($clStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <section
        id="medias"
        class="community-landing__media community-landing__media--page"
        aria-labelledby="medias-page-title"
        data-media-count="<?= (int) $mediaCount ?>"
        data-media-layout="<?= htmlspecialchars($galleryLayout, ENT_QUOTES, 'UTF-8') ?>"
        <?php if ($mediaLikesEnabled): ?>
        data-media-likes="1"
        data-media-likes-csrf="<?= htmlspecialchars($mediaLikeCsrf, ENT_QUOTES, 'UTF-8') ?>"
        data-media-likes-auth="<?= $mediaViewerCanLike ? '1' : '0' ?>"
        data-media-likes-login="<?= htmlspecialchars($mediaLoginUrl, ENT_QUOTES, 'UTF-8') ?>"
        <?php endif; ?>
    >
        <div class="community-landing__media-shell">
            <div class="community-landing__media-inner">
                <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="community-media-page__back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <span>Retour à <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></span>
                </a>

                <div class="community-landing__media-head">
                    <div class="community-landing__media-intro">
                        <p class="community-landing__section-kicker">Galerie</p>
                        <h1 id="medias-page-title" class="community-landing__section-title">Images &amp; vidéos</h1>
                        <p class="community-landing__section-lead">
                            <?php if ($mediaCount === 0): ?>
                                Aucun média publié pour le moment.
                            <?php else: ?>
                                Cliquez sur un média pour l’afficher en grand. Fermez avec la croix, Échap, ou un clic en dehors.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($items === []): ?>
            <div class="community-landing__media-empty">
                <p>Les images et vidéos publiées par la communauté apparaîtront ici.</p>
                <p class="community-media-page__empty-link">
                    <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>">Retour à la fiche communauté →</a>
                </p>
            </div>
            <?php else: ?>
            <div class="community-landing__gallery community-landing__gallery--<?= htmlspecialchars($galleryLayout, ENT_QUOTES, 'UTF-8') ?>" data-media-gallery>
                <div class="community-landing__gallery-track">
                    <?php foreach ($items as $mi): ?>
                        <?php
                        $mk = (string) ($mi['media_kind'] ?? 'image');
                        $mtitle = trim((string) ($mi['title'] ?? ''));
                        $mcap = trim((string) ($mi['caption'] ?? ''));
                        $murl = \App\Support\CommunityMediaDetails::publicUrl(isset($mi['storage_path']) ? (string) $mi['storage_path'] : null);
                        $membed = \App\Support\CommunityMediaDetails::embedUrl(isset($mi['external_url']) ? (string) $mi['external_url'] : null);
                        $regions = \App\Support\CommunityMediaDetails::parseBlurRegions($mi['blur_regions_json'] ?? null);
                        $wide = $mk === 'long_video' || !empty($mi['is_hero']);
                        $kindLabel = \App\Support\CommunityMediaDetails::kindLabel($mk);
                        $itemClass = 'community-landing__gallery-item';
                        if ($wide) {
                            $itemClass .= ' community-landing__gallery-item--wide';
                        }
                        if ($mk === 'long_video') {
                            $itemClass .= ' community-landing__gallery-item--video';
                        }
                        $canLightbox = ($mk === 'image' && $murl) || ($mk === 'short_video' && $murl) || ($mk === 'long_video' && $membed);
                        $lightboxAlt = $mtitle !== '' ? $mtitle : ($mk === 'image' ? 'Image de la communauté' : 'Vidéo de la communauté');
                        $lightboxAria = 'Agrandir' . ($mtitle !== '' ? ' : ' . $mtitle : ' le média');
                        $mediaItemId = (int) ($mi['id'] ?? 0);
                        $likesCount = (int) ($mi['likes_count'] ?? 0);
                        $likedByViewer = !empty($mi['liked_by_viewer']);
                        $likeUrl = $mediaItemId > 0
                            ? url('c/' . rawurlencode($slug) . '/medias/' . $mediaItemId . '/like')
                            : '';
                        ?>
                    <article
                        class="<?= htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') ?>"
                        <?php if ($canLightbox): ?>
                        data-lightbox-trigger
                        data-lightbox-kind="<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>"
                        <?php if ($murl): ?>data-lightbox-src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                        <?php if ($membed): ?>data-lightbox-embed="<?= htmlspecialchars($membed, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                        data-lightbox-title="<?= htmlspecialchars($mtitle, ENT_QUOTES, 'UTF-8') ?>"
                        data-lightbox-caption="<?= htmlspecialchars($mcap, ENT_QUOTES, 'UTF-8') ?>"
                        data-lightbox-alt="<?= htmlspecialchars($lightboxAlt, ENT_QUOTES, 'UTF-8') ?>"
                        tabindex="0"
                        role="button"
                        aria-haspopup="dialog"
                        aria-label="<?= htmlspecialchars($lightboxAria, ENT_QUOTES, 'UTF-8') ?>"
                        <?php endif; ?>
                        <?php if ($mediaLikesEnabled && $mediaItemId > 0): ?>
                        data-media-id="<?= $mediaItemId ?>"
                        data-like-count="<?= $likesCount ?>"
                        data-liked="<?= $likedByViewer ? '1' : '0' ?>"
                        data-like-url="<?= htmlspecialchars($likeUrl, ENT_QUOTES, 'UTF-8') ?>"
                        <?php endif; ?>
                    >
                        <div class="community-landing__gallery-frame">
                            <?php if ($mk === 'image' && $murl): ?>
                            <div class="community-landing__blur-host">
                                <img src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($lightboxAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" data-img-fallback="media" data-img-label="Image de la communauté indisponible">
                                <?php foreach ($regions as $reg): ?>
                                <span class="community-landing__blur-patch" style="left:<?= htmlspecialchars((string) $reg['x']) ?>%;top:<?= htmlspecialchars((string) $reg['y']) ?>%;width:<?= htmlspecialchars((string) $reg['w']) ?>%;height:<?= htmlspecialchars((string) $reg['h']) ?>%;"></span>
                                <?php endforeach; ?>
                            </div>
                            <?php elseif ($mk === 'short_video' && $murl): ?>
                            <video src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>" playsinline muted preload="metadata" tabindex="-1" aria-hidden="true"></video>
                            <span class="community-landing__gallery-play" aria-hidden="true">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                            </span>
                            <?php elseif ($mk === 'long_video' && $membed): ?>
                            <div class="community-landing__gallery-placeholder community-landing__gallery-placeholder--video" aria-hidden="true"></div>
                            <span class="community-landing__gallery-play" aria-hidden="true">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg>
                            </span>
                            <?php else: ?>
                            <div class="community-landing__gallery-placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                            <span class="community-landing__gallery-kind"><?= htmlspecialchars($kindLabel) ?></span>
                            <?php if ($mediaLikesEnabled && $mediaItemId > 0): ?>
                            <button
                                type="button"
                                class="community-landing__like-btn<?= $likedByViewer ? ' is-liked' : '' ?>"
                                data-media-like
                                aria-pressed="<?= $likedByViewer ? 'true' : 'false' ?>"
                                aria-label="<?= $likedByViewer ? 'Retirer mon j’aime' : 'J’aime ce média' ?>"
                            >
                                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.2-4.35-9.6-8.4C.6 9.3 2.1 5.7 5.7 5.1c1.95-.3 3.75.6 4.8 2.1 1.05-1.5 2.85-2.4 4.8-2.1 3.6.6 5.1 4.2 3.3 7.5C19.2 16.65 12 21 12 21z" fill="currentColor"/></svg>
                                <span class="community-landing__like-count" data-like-count-label><?= $likesCount > 0 ? (int) $likesCount : '' ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($mtitle !== '' || $mcap !== ''): ?>
                        <div class="community-landing__caption">
                            <?php if ($mtitle !== ''): ?><p class="community-landing__caption-title"><?= htmlspecialchars($mtitle) ?></p><?php endif; ?>
                            <?php if ($mcap !== ''): ?><p class="community-landing__caption-text"><?= htmlspecialchars($mcap) ?></p><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
.community-media-page__back {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0 0 1.5rem;
    padding: 0.45rem 0.85rem 0.45rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.05);
    color: #e2e8f0;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-decoration: none;
    transition: background 180ms ease, border-color 180ms ease;
}
.community-media-page__back:hover {
    background: color-mix(in srgb, var(--cl-primary, #10b981) 22%, rgba(255, 255, 255, 0.06));
    border-color: color-mix(in srgb, var(--cl-primary, #10b981) 45%, rgba(255, 255, 255, 0.2));
}
.community-landing__media--page {
    min-height: calc(100vh - 8rem);
    padding-top: 2.5rem;
    padding-bottom: 4rem;
}
.community-media-page__empty-link {
    margin: 1rem 0 0;
}
.community-media-page__empty-link a {
    color: var(--cl-accent, #34d399);
    font-weight: 700;
    text-decoration: none;
}
.community-media-page__empty-link a:hover {
    text-decoration: underline;
}
</style>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/community-landing-media.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
