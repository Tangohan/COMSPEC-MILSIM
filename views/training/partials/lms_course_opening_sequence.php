<?php
declare(strict_types=1);
/** @var int $lmsOpeningCourseId */
/** @var string $lmsOpeningTitle */
/** @var string $lmsOpeningBannerSrc */
/** @var string $lmsOpeningCtaMode lesson|scroll_inscription */
/** @var string $lmsOpeningLessonUrl */
/** @var string $lmsOpeningLoaderImageSrc */
/** @var string $lmsOpeningLoaderTitle */
/** @var string $lmsOpeningLoaderBody */

$lmsOpeningCourseId = (int) ($lmsOpeningCourseId ?? 0);
$lmsOpeningTitle = (string) ($lmsOpeningTitle ?? '');
$lmsOpeningBannerSrc = (string) ($lmsOpeningBannerSrc ?? '');
$lmsOpeningCtaMode = (string) ($lmsOpeningCtaMode ?? 'scroll_inscription');
$lmsOpeningLessonUrl = (string) ($lmsOpeningLessonUrl ?? '');
$lmsOpeningLoaderImageSrc = (string) ($lmsOpeningLoaderImageSrc ?? '');
$lmsOpeningLoaderTitle = trim((string) ($lmsOpeningLoaderTitle ?? ''));
$lmsOpeningLoaderBody = trim((string) ($lmsOpeningLoaderBody ?? ''));
if ($lmsOpeningCourseId < 1) {
    return;
}
$storageKey = 'lms_course_intro_' . $lmsOpeningCourseId;
$configJson = json_encode([
    'storageKey' => $storageKey,
    'ctaMode' => $lmsOpeningCtaMode === 'lesson' ? 'lesson' : 'scroll_inscription',
    'lessonUrl' => $lmsOpeningLessonUrl,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
$bannerUrlCss = json_encode($lmsOpeningBannerSrc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
?>
<div id="lms-course-opening-root" class="lms-course-open-root" hidden>
    <div id="lms-course-opening-loader" class="lms-course-open-loader" role="status" aria-live="polite" aria-busy="true">
        <div class="lms-course-open-loader__panel">
            <?php if ($lmsOpeningLoaderImageSrc !== ''): ?>
            <div class="lms-course-open-loader__slide-media">
                <img src="<?= htmlspecialchars($lmsOpeningLoaderImageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" class="lms-course-open-loader__slide-img" loading="eager" decoding="async">
            </div>
            <?php endif; ?>
            <div class="lms-course-open-loader__icon" aria-hidden="true">
                <svg class="lms-course-open-loader__svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <?php if ($lmsOpeningLoaderTitle !== ''): ?>
            <p class="lms-course-open-loader__title"><?= htmlspecialchars($lmsOpeningLoaderTitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <p class="lms-course-open-loader__text">Préparation du parcours…</p>
            <?php if ($lmsOpeningLoaderBody !== ''): ?>
            <p class="lms-course-open-loader__body"><?= nl2br(htmlspecialchars($lmsOpeningLoaderBody, ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div id="lms-course-opening-intro" class="lms-course-open-intro" role="dialog" aria-modal="true" aria-labelledby="lms-course-opening-title" aria-hidden="true" hidden>
        <div class="lms-course-open-intro__stack">
            <div class="lms-course-open-intro__image" style="background-image: url(<?= $bannerUrlCss ?>);"></div>
            <div class="lms-course-open-intro__content">
                <div class="lms-course-open-intro__copy">
                    <p class="lms-course-open-intro__kicker">Parcours</p>
                    <h2 id="lms-course-opening-title" class="lms-course-open-intro__title"><?= htmlspecialchars($lmsOpeningTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                    <button type="button" id="lms-course-opening-cta" class="lms-course-open-intro__cta">Commencer</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="lms-course-opening-config-json"><?= $configJson ?></script>
<script>
(function () {
    var root = document.getElementById('lms-course-opening-root');
    var loader = document.getElementById('lms-course-opening-loader');
    var intro = document.getElementById('lms-course-opening-intro');
    var cta = document.getElementById('lms-course-opening-cta');
    var cfgEl = document.getElementById('lms-course-opening-config-json');
    if (!root || !loader || !intro || !cta || !cfgEl) return;

    var cfg;
    try {
        cfg = JSON.parse(cfgEl.textContent || '{}');
    } catch (e) {
        return;
    }
    var storageKey = cfg.storageKey || '';
    var ctaMode = cfg.ctaMode || 'scroll_inscription';
    var lessonUrl = cfg.lessonUrl || '';

    function removeAll() {
        document.body.style.overflow = '';
        if (root.parentNode) root.parentNode.removeChild(root);
        if (cfgEl.parentNode) cfgEl.parentNode.removeChild(cfgEl);
    }

    if (!storageKey) {
        removeAll();
        return;
    }

    try {
        if (sessionStorage.getItem(storageKey) === '1') {
            removeAll();
            return;
        }
    } catch (err) {
        removeAll();
        return;
    }

    root.hidden = false;
    loader.hidden = false;
    document.body.style.overflow = 'hidden';

    var reduceMotion = false;
    try {
        reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e2) {}

    var bannerSrc = <?= json_encode($lmsOpeningBannerSrc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var minDelayMs = reduceMotion ? 0 : 400;
    var timeoutMs = 8000;
    var startTs = Date.now();
    var readyFired = false;

    function showIntroFromLoader() {
        if (readyFired) return;
        readyFired = true;
        var elapsed = Date.now() - startTs;
        var wait = Math.max(0, minDelayMs - elapsed);
        function go() {
            loader.setAttribute('aria-busy', 'false');
            loader.classList.add('lms-course-open-loader--out');
            intro.hidden = false;
            intro.setAttribute('aria-hidden', 'false');
            intro.classList.add('lms-course-open-intro--in');
            setTimeout(function () {
                loader.hidden = true;
                cta.focus();
            }, reduceMotion ? 0 : 280);
        }
        if (wait <= 0) go();
        else setTimeout(go, wait);
    }

    if (!bannerSrc) {
        showIntroFromLoader();
    } else {
        var img = new Image();
        img.onload = showIntroFromLoader;
        img.onerror = showIntroFromLoader;
        img.src = bannerSrc;
        setTimeout(showIntroFromLoader, timeoutMs);
    }

    function finishIntro() {
        document.body.style.overflow = '';
        intro.setAttribute('aria-hidden', 'true');
        removeAll();
        var pageTitle = document.getElementById('lms-course-page-title');
        if (pageTitle) {
            pageTitle.setAttribute('tabindex', '-1');
            pageTitle.focus();
        }
    }

    cta.addEventListener('click', function () {
        try {
            sessionStorage.setItem(storageKey, '1');
        } catch (e4) {}
        if (ctaMode === 'lesson' && lessonUrl) {
            window.location.href = lessonUrl;
            return;
        }
        finishIntro();
        var target = document.getElementById('lms-inscription');
        if (target) {
            target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        }
    });
})();
</script>
