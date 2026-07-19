<?php
declare(strict_types=1);

/**
 * Feed média plein écran façon TikTok/Instagram — une diapositive par écran, défilement vertical
 * en scroll-snap (images, vidéos courtes en lecture auto au survol de l'écran, vidéos longues
 * en embed YouTube/Vimeo).
 *
 * @var array<string,mixed> $tenant
 * @var list<array<string,mixed>> $mediaFeedItems
 */

$slug = (string) ($tenant['slug'] ?? '');
$tenantName = trim((string) ($tenant['name'] ?? 'Communauté'));
$items = is_array($mediaFeedItems ?? null) ? $mediaFeedItems : [];
$backHref = url('c/' . rawurlencode($slug));
?>
<div class="media-feed">
    <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="media-feed__back">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        <span><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></span>
    </a>

    <?php if ($items === []): ?>
    <div class="media-feed__empty">
        <p>Aucun média publié pour le moment.</p>
        <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>">Retour à la fiche communauté →</a>
    </div>
    <?php else: ?>
    <div class="media-feed__scroller" data-media-feed>
        <?php foreach ($items as $i => $mi): ?>
            <?php
            $mk = (string) ($mi['media_kind'] ?? 'image');
            $mtitle = trim((string) ($mi['title'] ?? ''));
            $mcap = trim((string) ($mi['caption'] ?? ''));
            $murl = \App\Support\CommunityMediaDetails::publicUrl(isset($mi['storage_path']) ? (string) $mi['storage_path'] : null);
            $membed = \App\Support\CommunityMediaDetails::embedUrl(isset($mi['external_url']) ? (string) $mi['external_url'] : null);
            $regions = \App\Support\CommunityMediaDetails::parseBlurRegions($mi['blur_regions_json'] ?? null);
            $kindLabel = \App\Support\CommunityMediaDetails::kindLabel($mk);
            ?>
        <section class="media-feed__slide" data-media-slide data-kind="<?= htmlspecialchars($mk, ENT_QUOTES, 'UTF-8') ?>" aria-label="Média <?= (int) $i + 1 ?> sur <?= count($items) ?>">
            <div class="media-feed__frame">
                <?php if ($mk === 'image' && $murl): ?>
                    <img src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($mtitle !== '' ? $mtitle : 'Image de la communauté', ENT_QUOTES, 'UTF-8') ?>" loading="lazy" data-img-fallback="media" data-img-label="Image indisponible">
                    <?php foreach ($regions as $reg): ?>
                    <span class="media-feed__blur" style="left:<?= htmlspecialchars((string) $reg['x']) ?>%;top:<?= htmlspecialchars((string) $reg['y']) ?>%;width:<?= htmlspecialchars((string) $reg['w']) ?>%;height:<?= htmlspecialchars((string) $reg['h']) ?>%;"></span>
                    <?php endforeach; ?>
                <?php elseif ($mk === 'short_video' && $murl): ?>
                    <video src="<?= htmlspecialchars($murl, ENT_QUOTES, 'UTF-8') ?>" playsinline muted loop preload="metadata" data-feed-video></video>
                    <button type="button" class="media-feed__mute" data-feed-mute aria-label="Activer / couper le son" aria-pressed="true">
                        <svg data-icon-muted width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15" stroke-linecap="round"/><line x1="17" y1="9" x2="23" y2="15" stroke-linecap="round"/></svg>
                        <svg data-icon-unmuted width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" hidden><path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z"/><path stroke-linecap="round" d="M15.5 8.5a5 5 0 010 7M19 5a10 10 0 010 14"/></svg>
                    </button>
                <?php elseif ($mk === 'long_video' && $membed): ?>
                    <iframe src="<?= htmlspecialchars($membed, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($mtitle !== '' ? $mtitle : 'Vidéo de la communauté', ENT_QUOTES, 'UTF-8') ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <?php else: ?>
                    <div class="media-feed__placeholder" aria-hidden="true"></div>
                <?php endif; ?>
                <div class="media-feed__veil" aria-hidden="true"></div>
            </div>

            <div class="media-feed__overlay">
                <span class="media-feed__kind"><?= htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($mtitle !== ''): ?><p class="media-feed__title"><?= htmlspecialchars($mtitle, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php if ($mcap !== ''): ?><p class="media-feed__caption"><?= htmlspecialchars($mcap, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
    <div class="media-feed__hint" data-media-hint aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M6 13l6 6 6-6"/></svg>
        <span>Défiler</span>
    </div>
    <?php endif; ?>
</div>

<style>
.media-feed {
    position: fixed;
    inset: 0;
    z-index: 40;
    background: #000;
}
.media-feed__back {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 5;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem 0.5rem 0.6rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(6px);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.media-feed__empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: #fff;
    text-align: center;
    padding: 2rem;
}
.media-feed__empty a { color: #34d399; font-weight: 700; text-decoration: none; }

.media-feed__scroller {
    height: 100%;
    overflow-y: auto;
    scroll-snap-type: y mandatory;
    scrollbar-width: none;
}
.media-feed__scroller::-webkit-scrollbar { display: none; }

.media-feed__slide {
    position: relative;
    height: 100vh;
    height: 100dvh;
    scroll-snap-align: start;
    scroll-snap-stop: always;
    display: flex;
    align-items: flex-end;
}
.media-feed__frame {
    position: absolute;
    inset: 0;
    background: #050505;
}
.media-feed__frame img,
.media-feed__frame video,
.media-feed__frame iframe {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border: 0;
    background: #050505;
}
.media-feed__frame video { object-fit: cover; }
.media-feed__placeholder {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 20%, rgba(16, 185, 129, 0.25), transparent 45%), linear-gradient(160deg, #020617, #0f172a 60%, #022c22);
}
.media-feed__veil {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.75) 0%, rgba(0, 0, 0, 0) 40%);
    pointer-events: none;
}
.media-feed__blur {
    position: absolute;
    backdrop-filter: blur(18px);
    background: rgba(15, 23, 42, 0.35);
    pointer-events: none;
}
.media-feed__mute {
    position: absolute;
    right: 1rem;
    bottom: 6.5rem;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(0, 0, 0, 0.45);
    color: #fff;
    cursor: pointer;
}

.media-feed__overlay {
    position: relative;
    z-index: 3;
    width: 100%;
    padding: 1.25rem 1.25rem 3rem;
    color: #fff;
}
.media-feed__kind {
    display: inline-flex;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    font-size: 0.625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.media-feed__title { margin: 0.6rem 0 0; font-size: 1.05rem; font-weight: 900; letter-spacing: -0.01em; }
.media-feed__caption { margin: 0.35rem 0 0; font-size: 0.875rem; line-height: 1.4; color: rgba(255, 255, 255, 0.85); max-width: 32rem; }

.media-feed__hint {
    position: absolute;
    left: 50%;
    bottom: 1rem;
    transform: translateX(-50%);
    z-index: 4;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    animation: media-feed-bounce 1.6s ease-in-out infinite;
    pointer-events: none;
}
@keyframes media-feed-bounce {
    0%, 100% { transform: translate(-50%, 0); opacity: 0.8; }
    50% { transform: translate(-50%, 6px); opacity: 0.35; }
}
@media (prefers-reduced-motion: reduce) {
    .media-feed__hint { animation: none; }
}
</style>
<script>
(function () {
    var scroller = document.querySelector('[data-media-feed]');
    var hint = document.querySelector('[data-media-hint]');
    if (!scroller) return;

    var videos = Array.prototype.slice.call(scroller.querySelectorAll('[data-feed-video]'));

    function pauseAll(except) {
        videos.forEach(function (v) {
            if (v !== except) { v.pause(); }
        });
    }

    if ('IntersectionObserver' in window && videos.length) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var video = entry.target;
                if (entry.isIntersecting && entry.intersectionRatio > 0.6) {
                    pauseAll(video);
                    video.play().catch(function () {});
                } else {
                    video.pause();
                }
            });
        }, { threshold: [0, 0.6, 1] });
        videos.forEach(function (v) { io.observe(v); });
    }

    scroller.querySelectorAll('[data-feed-mute]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var slide = btn.closest('[data-media-slide]');
            var video = slide ? slide.querySelector('[data-feed-video]') : null;
            if (!video) return;
            video.muted = !video.muted;
            btn.setAttribute('aria-pressed', video.muted ? 'true' : 'false');
            var mutedIcon = btn.querySelector('[data-icon-muted]');
            var unmutedIcon = btn.querySelector('[data-icon-unmuted]');
            if (mutedIcon) mutedIcon.hidden = !video.muted;
            if (unmutedIcon) unmutedIcon.hidden = video.muted;
        });
    });

    if (hint) {
        var hideHint = function () { hint.style.display = 'none'; scroller.removeEventListener('scroll', hideHint); };
        scroller.addEventListener('scroll', hideHint, { passive: true });
        if (scroller.querySelectorAll('[data-media-slide]').length < 2) { hint.style.display = 'none'; }
    }
})();
</script>
