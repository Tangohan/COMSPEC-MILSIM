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
$reelsHref = url('c/' . rawurlencode($slug) . '/reels');
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

$countLabel = $mediaCount === 0
    ? 'Aucun média'
    : ($mediaCount === 1 ? '1 média' : $mediaCount . ' médias');
?>
<style>
/* Filet page galerie — héros / CTA / empty (complète community-landing.css) */
.community-media-page{font-family:'Archivo',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
.community-landing__media--page{--cl-media-gap:.5rem;padding:0 0 4.5rem;overflow:visible;background:radial-gradient(ellipse 85% 55% at 50% -8%,color-mix(in srgb,var(--cl-primary,#12d18e) 18%,transparent),transparent 58%),linear-gradient(180deg,#0c1116 0%,#070a0c 42%,#050708 100%);color:#f4f7f8}
.community-media-page__hero{position:relative;padding:2rem 0 1.75rem;border-bottom:1px solid rgba(255,255,255,.06);overflow:hidden}
.community-media-page__hero-glow{position:absolute;inset:auto auto -40% -10%;width:min(42rem,90vw);height:min(28rem,70vw);pointer-events:none;background:radial-gradient(circle,color-mix(in srgb,var(--cl-primary,#12d18e) 22%,transparent),transparent 68%);filter:blur(8px);opacity:.85}
.community-media-page__hero-inner{position:relative;z-index:1}
.community-media-page__hero-grid{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1.5rem 2rem;align-items:end}
.community-media-page__kicker{margin:0;font-size:.68rem;font-weight:800;letter-spacing:.28em;text-transform:uppercase;color:var(--cl-accent,#12d18e)}
.community-media-page__title{margin:.55rem 0 0;font-size:clamp(2rem,4.5vw,3.15rem);font-weight:900;letter-spacing:-.035em;line-height:1.05;color:#fff}
.community-media-page__lead{margin:.85rem 0 0;max-width:38rem;font-size:.95rem;font-weight:500;line-height:1.65;color:rgba(244,247,248,.68)}
.community-media-page__actions{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.35rem}
.community-media-page__cta{min-height:2.65rem;padding:0 1.15rem;gap:.45rem;border-radius:.2rem;font-size:.7rem}
.community-media-page__hero-aside{display:flex;flex-direction:column;align-items:flex-end;gap:.45rem;text-align:right;padding:1rem 1.15rem;border:1px solid rgba(255,255,255,.1);background:linear-gradient(160deg,color-mix(in srgb,var(--cl-primary,#12d18e) 12%,rgba(255,255,255,.04)),rgba(255,255,255,.03));min-width:9.5rem}
.community-media-page__count{margin:0;font-size:.72rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--cl-accent,#12d18e)}
.community-media-page__aside-hint{margin:0;max-width:11rem;font-size:.75rem;font-weight:500;line-height:1.4;color:rgba(244,247,248,.5)}
.community-media-page__body{padding-top:1.75rem}
.community-media-page__empty{display:flex;flex-direction:column;align-items:center;gap:.65rem;width:min(36rem,calc(100% - 2.5rem));margin:.75rem auto 0;padding:3rem 1.5rem;text-align:center;border:1px dashed color-mix(in srgb,var(--cl-primary,#12d18e) 28%,rgba(255,255,255,.12));border-radius:.35rem;color:rgba(244,247,248,.68);background:rgba(12,17,22,.55)}
.community-media-page__empty-icon{display:grid;place-items:center;width:3.5rem;height:3.5rem;margin-bottom:.35rem;border-radius:999px;color:var(--cl-accent,#12d18e);background:color-mix(in srgb,var(--cl-primary,#12d18e) 14%,transparent);border:1px solid color-mix(in srgb,var(--cl-primary,#12d18e) 35%,rgba(255,255,255,.1))}
.community-media-page__empty-title{margin:0;font-size:1.05rem;font-weight:800;letter-spacing:-.02em;color:#fff}
.community-media-page__empty-link{margin:.85rem 0 0}
@media (max-width:760px){.community-media-page__hero-grid{grid-template-columns:1fr}.community-media-page__hero-aside{align-items:flex-start;text-align:left;width:fit-content}.community-media-page__aside-hint{max-width:none}}
@media (max-width:640px){.community-media-page__title{font-size:clamp(1.75rem,9vw,2.35rem)}.community-media-page__cta{flex:1 1 auto;justify-content:center}}
</style>
<div class="community-landing community-media-page"<?= $clStyle !== '' ? ' style="' . htmlspecialchars($clStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
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
        <header class="community-media-page__hero">
            <div class="community-media-page__hero-glow" aria-hidden="true"></div>
            <div class="community-landing__media-inner community-media-page__hero-inner">
                <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="community-media-page__back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <span>Retour à <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></span>
                </a>

                <div class="community-media-page__hero-grid">
                    <div class="community-media-page__hero-copy">
                        <p class="community-media-page__kicker">Galerie</p>
                        <h1 id="medias-page-title" class="community-media-page__title">Images &amp; vidéos</h1>
                        <p class="community-media-page__lead">
                            <?php if ($mediaCount === 0): ?>
                                Les publications visuelles de <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?> apparaîtront ici.
                            <?php elseif ($mediaCount === 1): ?>
                                Cliquez sur le média pour l’afficher en grand. Fermez avec la croix, Échap, ou un clic en dehors.
                            <?php else: ?>
                                Cliquez sur un média pour l’afficher en grand. Naviguez avec les flèches ; fermez avec la croix, Échap, ou un clic en dehors.
                            <?php endif; ?>
                        </p>

                        <div class="community-media-page__actions">
                            <?php if ($mediaCount > 0): ?>
                            <a href="<?= htmlspecialchars($reelsHref, ENT_QUOTES, 'UTF-8') ?>" class="community-landing__cta community-landing__cta--primary community-media-page__cta">
                                Voir le fil
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="community-landing__cta community-landing__cta--ghost community-media-page__cta">
                                Retour à la communauté
                            </a>
                        </div>
                    </div>

                    <aside class="community-media-page__hero-aside" aria-label="Résumé de la galerie">
                        <p class="community-media-page__count" aria-live="polite"><?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="community-media-page__aside-hint">
                            <?php if ($mediaCount === 0): ?>
                                Rien à afficher pour le moment
                            <?php elseif ($mediaCount === 1): ?>
                                Affichage plein écran au clic
                            <?php else: ?>
                                Grille · un média par écran dans le fil
                            <?php endif; ?>
                        </p>
                    </aside>
                </div>
            </div>
        </header>

        <div class="community-landing__media-shell community-media-page__body">
            <?php if ($items === []): ?>
            <div class="community-landing__media-empty community-media-page__empty" role="status">
                <span class="community-media-page__empty-icon" aria-hidden="true">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 16l-5.5-5.5L8 18"/></svg>
                </span>
                <p class="community-media-page__empty-title">Aucun média publié pour le moment</p>
                <p>Les images et vidéos partagées par la communauté apparaîtront ici dès qu’elles seront disponibles.</p>
                <p class="community-media-page__empty-link">
                    <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="community-landing__cta community-landing__cta--ghost community-media-page__cta">Retour à la fiche communauté</a>
                </p>
            </div>
            <?php else: ?>
            <div class="community-landing__gallery community-landing__gallery--<?= htmlspecialchars($galleryLayout, ENT_QUOTES, 'UTF-8') ?> community-media-page__gallery" data-media-gallery>
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
                        $hasCaption = $mtitle !== '' || $mcap !== '';
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
                            <?php if ($hasCaption): ?>
                            <div class="community-media-page__tile-caption" aria-hidden="true">
                                <?php if ($mtitle !== ''): ?><p class="community-media-page__tile-title"><?= htmlspecialchars($mtitle) ?></p><?php endif; ?>
                                <?php if ($mcap !== ''): ?><p class="community-media-page__tile-text"><?= htmlspecialchars($mcap) ?></p><?php endif; ?>
                            </div>
                            <?php endif; ?>
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
                        <?php if ($hasCaption): ?>
                        <div class="community-landing__caption community-media-page__caption-sr">
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
<script defer src="<?= htmlspecialchars(asset_url('assets/js/community-landing-media.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
