<?php
declare(strict_types=1);
/**
 * Ouverture parcours : loader → préambule → porte d’inscription (attente / auto-inscription) → fiche.
 *
 * @var int $lmsOpeningCourseId
 * @var string $lmsOpeningTitle
 * @var string $lmsOpeningBannerSrc
 * @var string $lmsOpeningCtaMode lesson|open_fiche|enroll_gate
 * @var string $lmsOpeningLessonUrl
 * @var string $lmsOpeningLoaderImageSrc
 * @var string $lmsOpeningLoaderTitle
 * @var string $lmsOpeningLoaderBody
 * @var string $lmsOpeningCtaLabel
 * @var bool $lmsOpeningCanAccessLearning
 * @var bool $lmsOpeningViewerLoggedIn
 * @var bool $lmsOpeningNeedsApproval
 * @var array<string, mixed>|null $lmsOpeningEnrollment
 * @var array{allowed?: bool, messages?: list<string>} $lmsOpeningPolicyEval
 * @var bool $lmsOpeningHasPolicyInfo
 * @var list<array<string, mixed>> $lmsOpeningPreCourses
 * @var list<array<string, mixed>> $lmsOpeningCertCourses
 * @var list<string> $lmsOpeningPolicyFlags
 * @var string $lmsOpeningSlug
 * @var string|null $lmsOpeningFlashOk
 * @var string|null $lmsOpeningFlashErr
 * @var bool $lmsOpeningStaffBypass
 * @var string $lmsOpeningLoginUrl
 * @var string $lmsOpeningEnrollAction
 */

$lmsOpeningCourseId = (int) ($lmsOpeningCourseId ?? 0);
$lmsOpeningTitle = (string) ($lmsOpeningTitle ?? '');
$lmsOpeningBannerSrc = trim((string) ($lmsOpeningBannerSrc ?? ''));
$lmsOpeningCtaMode = (string) ($lmsOpeningCtaMode ?? 'enroll_gate');
$lmsOpeningLessonUrl = (string) ($lmsOpeningLessonUrl ?? '');
$lmsOpeningLoaderImageSrc = (string) ($lmsOpeningLoaderImageSrc ?? '');
$lmsOpeningLoaderTitle = trim((string) ($lmsOpeningLoaderTitle ?? ''));
$lmsOpeningLoaderBody = trim((string) ($lmsOpeningLoaderBody ?? ''));
$lmsOpeningCtaLabel = (string) ($lmsOpeningCtaLabel ?? 'Continuer');
$lmsOpeningCanAccessLearning = !empty($lmsOpeningCanAccessLearning);
$lmsOpeningViewerLoggedIn = !empty($lmsOpeningViewerLoggedIn);
$lmsOpeningNeedsApproval = !empty($lmsOpeningNeedsApproval);
$lmsOpeningStaffBypass = !empty($lmsOpeningStaffBypass);
$lmsOpeningEnrollment = is_array($lmsOpeningEnrollment ?? null) ? $lmsOpeningEnrollment : null;
$lmsOpeningPolicyEval = is_array($lmsOpeningPolicyEval ?? null) ? $lmsOpeningPolicyEval : ['allowed' => false, 'messages' => []];
$lmsOpeningHasPolicyInfo = !empty($lmsOpeningHasPolicyInfo);
$lmsOpeningPreCourses = is_array($lmsOpeningPreCourses ?? null) ? $lmsOpeningPreCourses : [];
$lmsOpeningCertCourses = is_array($lmsOpeningCertCourses ?? null) ? $lmsOpeningCertCourses : [];
$lmsOpeningPolicyFlags = is_array($lmsOpeningPolicyFlags ?? null) ? $lmsOpeningPolicyFlags : [];
$lmsOpeningSlug = (string) ($lmsOpeningSlug ?? '');
$lmsOpeningFlashOk = isset($lmsOpeningFlashOk) ? (string) $lmsOpeningFlashOk : '';
$lmsOpeningFlashErr = isset($lmsOpeningFlashErr) ? (string) $lmsOpeningFlashErr : '';
$lmsOpeningLoginUrl = (string) ($lmsOpeningLoginUrl ?? url('login'));
$lmsOpeningEnrollAction = (string) ($lmsOpeningEnrollAction ?? url('formations/enroll'));

if ($lmsOpeningCourseId < 1) {
    return;
}
if ($lmsOpeningBannerSrc === '' && function_exists('training_course_default_cover_url')) {
    $lmsOpeningBannerSrc = training_course_default_cover_url();
} elseif ($lmsOpeningBannerSrc === '' && function_exists('training_media_url')) {
    $lmsOpeningBannerSrc = training_media_url(null);
}

$enrollStatus = is_array($lmsOpeningEnrollment) ? (string) ($lmsOpeningEnrollment['status'] ?? '') : '';
$isPending = $enrollStatus === 'pending_approval';
$hasEnrollment = is_array($lmsOpeningEnrollment) && $enrollStatus !== '';
$canSelfEnroll = $lmsOpeningViewerLoggedIn && !$hasEnrollment && !empty($lmsOpeningPolicyEval['allowed']);
$enrollBtnLabel = $lmsOpeningNeedsApproval ? 'Demander mon inscription' : 'M’auto-inscrire';

$gateState = 'enroll';
if ($lmsOpeningCanAccessLearning) {
    $gateState = 'open';
} elseif ($isPending) {
    $gateState = 'pending';
} elseif (!$lmsOpeningViewerLoggedIn) {
    $gateState = 'login';
} elseif ($hasEnrollment && !$lmsOpeningCanAccessLearning) {
    $gateState = 'pending';
} elseif (!$canSelfEnroll) {
    $gateState = 'blocked';
}

$storageKeyPreamble = 'lms_course_intro_' . $lmsOpeningCourseId;
$configJson = json_encode([
    'storageKeyPreamble' => $storageKeyPreamble,
    'ctaMode' => $lmsOpeningCtaMode,
    'lessonUrl' => $lmsOpeningLessonUrl,
    'canAccessLearning' => $lmsOpeningCanAccessLearning,
    'staffBypass' => $lmsOpeningStaffBypass,
    'gateState' => $gateState,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div id="lms-course-opening-root" class="lms-course-open-root" hidden>
    <div id="lms-course-opening-loader" class="lms-course-open-loader" role="status" aria-live="polite" aria-busy="true">
        <div class="lms-course-open-loader__panel">
            <?php if ($lmsOpeningLoaderImageSrc !== ''): ?>
            <div class="lms-course-open-loader__slide-media">
                <img src="<?= $h($lmsOpeningLoaderImageSrc) ?>" alt="" class="lms-course-open-loader__slide-img" loading="eager" decoding="async">
            </div>
            <?php endif; ?>
            <div class="lms-course-open-loader__icon" aria-hidden="true">
                <svg class="lms-course-open-loader__svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <?php if ($lmsOpeningLoaderTitle !== ''): ?>
            <p class="lms-course-open-loader__title"><?= $h($lmsOpeningLoaderTitle) ?></p>
            <?php endif; ?>
            <p class="lms-course-open-loader__text">Préparation du parcours…</p>
            <?php if ($lmsOpeningLoaderBody !== ''): ?>
            <p class="lms-course-open-loader__body"><?= nl2br($h($lmsOpeningLoaderBody)) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div id="lms-course-opening-intro" class="lms-course-open-intro" role="dialog" aria-modal="true" aria-labelledby="lms-course-opening-title" aria-hidden="true" hidden>
        <div class="lms-course-open-intro__stack">
            <div class="lms-course-open-intro__image" aria-hidden="true">
                <?php if ($lmsOpeningBannerSrc !== ''): ?>
                <img
                    src="<?= $h($lmsOpeningBannerSrc) ?>"
                    alt=""
                    class="lms-course-open-intro__img"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                    onerror="this.remove()"
                >
                <?php endif; ?>
            </div>
            <div class="lms-course-open-intro__content">
                <div class="lms-course-open-intro__copy">
                    <p class="lms-course-open-intro__kicker">Préambule</p>
                    <h2 id="lms-course-opening-title" class="lms-course-open-intro__title"><?= $h($lmsOpeningTitle) ?></h2>
                    <p class="lms-course-open-intro__lead">Cadre du parcours, puis inscription, puis enchaînement module après module.</p>
                    <button type="button" id="lms-course-opening-cta" class="lms-course-open-intro__cta"><?= $h($lmsOpeningCtaLabel) ?></button>
                </div>
            </div>
        </div>
    </div>

    <div id="lms-course-opening-enroll" class="lms-course-open-intro lms-course-open-enroll" role="dialog" aria-modal="true" aria-labelledby="lms-course-opening-enroll-title" aria-hidden="true" hidden>
        <div class="lms-course-open-intro__stack">
            <div class="lms-course-open-intro__image" aria-hidden="true">
                <?php if ($lmsOpeningBannerSrc !== ''): ?>
                <img src="<?= $h($lmsOpeningBannerSrc) ?>" alt="" class="lms-course-open-intro__img" loading="eager" decoding="async" onerror="this.remove()">
                <?php endif; ?>
            </div>
            <div class="lms-course-open-intro__content">
                <div class="lms-course-open-intro__copy lms-course-open-enroll__copy">
                    <p class="lms-course-open-intro__kicker">Inscription</p>
                    <h2 id="lms-course-opening-enroll-title" class="lms-course-open-intro__title">Accès au parcours</h2>

                    <?php if ($lmsOpeningFlashOk !== ''): ?>
                    <p class="lms-course-open-enroll__flash lms-course-open-enroll__flash--ok" role="status"><?= $h($lmsOpeningFlashOk) ?></p>
                    <?php endif; ?>
                    <?php if ($lmsOpeningFlashErr !== ''): ?>
                    <p class="lms-course-open-enroll__flash lms-course-open-enroll__flash--err" role="alert"><?= $h($lmsOpeningFlashErr) ?></p>
                    <?php endif; ?>

                    <div class="lms-course-open-enroll__grid">
                        <div class="lms-course-open-enroll__prereq">
                            <p class="lms-course-open-enroll__section-label">Prérequis</p>
                            <?php if (!$lmsOpeningHasPolicyInfo): ?>
                            <p class="lms-course-open-enroll__muted">Aucun prérequis de parcours ni condition supplémentaire n’est renseigné pour cette formation.</p>
                            <?php else: ?>
                                <?php if ($lmsOpeningPreCourses !== []): ?>
                                <p class="lms-course-open-enroll__sub">Formations à avoir validées avant</p>
                                <ul class="lms-course-open-enroll__list">
                                    <?php foreach ($lmsOpeningPreCourses as $pc): ?>
                                    <li>
                                        <?php if (($pc['completed'] ?? null) === true): ?>
                                        <span class="lms-course-open-enroll__mark is-ok" title="Validé">✓</span>
                                        <?php elseif (($pc['completed'] ?? null) === false): ?>
                                        <span class="lms-course-open-enroll__mark is-warn" title="À valider">!</span>
                                        <?php else: ?>
                                        <span class="lms-course-open-enroll__mark">·</span>
                                        <?php endif; ?>
                                        <span><?= $h((string) ($pc['title'] ?? '')) ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <?php if ($lmsOpeningCertCourses !== []): ?>
                                <p class="lms-course-open-enroll__sub">Attestation ou validation attendue</p>
                                <ul class="lms-course-open-enroll__list">
                                    <?php foreach ($lmsOpeningCertCourses as $cc): ?>
                                    <li>
                                        <?php if (($cc['completed'] ?? null) === true): ?>
                                        <span class="lms-course-open-enroll__mark is-ok">✓</span>
                                        <?php elseif (($cc['completed'] ?? null) === false): ?>
                                        <span class="lms-course-open-enroll__mark is-warn">!</span>
                                        <?php else: ?>
                                        <span class="lms-course-open-enroll__mark">·</span>
                                        <?php endif; ?>
                                        <span><?= $h((string) ($cc['title'] ?? '')) ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <?php foreach ($lmsOpeningPolicyFlags as $pf): ?>
                                <p class="lms-course-open-enroll__flag"><?= $h((string) $pf) ?></p>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="lms-course-open-enroll__panel" id="lms-inscription">
                            <p class="lms-course-open-enroll__section-label">Inscription au parcours</p>

                            <?php if ($gateState === 'pending' || $isPending): ?>
                            <p class="lms-course-open-enroll__lead">Votre demande d’inscription a bien été enregistrée. Un formateur doit la valider avant que vous puissiez ouvrir les leçons.</p>
                            <p class="lms-course-open-enroll__waiting" role="status">En attente de validation</p>
                            <p class="lms-course-open-enroll__muted">Vous pouvez fermer cette page et y revenir plus tard. Dès validation, la fiche formation s’ouvrira normalement.</p>

                            <?php elseif ($gateState === 'login'): ?>
                            <p class="lms-course-open-enroll__lead">Connectez-vous pour vous inscrire et accéder au contenu du parcours.</p>
                            <a href="<?= $h($lmsOpeningLoginUrl) ?>" class="lms-course-open-intro__cta">Se connecter</a>

                            <?php elseif ($gateState === 'enroll' && $canSelfEnroll): ?>
                            <p class="lms-course-open-enroll__lead">
                                <?= $lmsOpeningNeedsApproval
                                    ? 'L’inscription sera examinée par un formateur avant l’accès aux leçons.'
                                    : 'Inscrivez-vous pour accéder à la fiche formation et démarrer les modules.' ?>
                            </p>
                            <form method="post" action="<?= $h($lmsOpeningEnrollAction) ?>" class="lms-course-open-enroll__form">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="course_id" value="<?= $lmsOpeningCourseId ?>">
                                <input type="hidden" name="course_slug" value="<?= $h($lmsOpeningSlug) ?>">
                                <label class="lms-course-open-enroll__label">
                                    <span>Message de motivation <em>(optionnel)</em></span>
                                    <textarea name="enrollment_motivation" rows="3" maxlength="4000" placeholder="Pourquoi souhaitez-vous suivre cette formation ?"></textarea>
                                </label>
                                <button type="submit" class="lms-course-open-intro__cta"><?= $h($enrollBtnLabel) ?></button>
                            </form>

                            <?php elseif ($lmsOpeningCanAccessLearning): ?>
                            <p class="lms-course-open-enroll__lead">Vous êtes inscrit. Accédez à la fiche formation pour poursuivre.</p>
                            <button type="button" id="lms-course-opening-enter-fiche" class="lms-course-open-intro__cta">Ouvrir la fiche formation</button>

                            <?php else: ?>
                            <p class="lms-course-open-enroll__lead">Inscription indisponible pour le moment.</p>
                            <?php foreach (($lmsOpeningPolicyEval['messages'] ?? []) as $pm): ?>
                            <p class="lms-course-open-enroll__flag lms-course-open-enroll__flag--err"><?= $h((string) $pm) ?></p>
                            <?php endforeach; ?>
                            <?php if ($lmsOpeningStaffBypass): ?>
                            <button type="button" id="lms-course-opening-enter-fiche" class="lms-course-open-intro__cta" style="margin-top:0.85rem;">Voir la fiche (staff)</button>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($lmsOpeningStaffBypass && in_array($gateState, ['pending', 'login', 'enroll'], true)): ?>
                            <p class="lms-course-open-enroll__muted" style="margin-top:1rem;">
                                <button type="button" id="lms-course-opening-staff-bypass" class="lms-course-open-enroll__bypass">Accéder à la fiche sans inscription (staff)</button>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
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
    var enroll = document.getElementById('lms-course-opening-enroll');
    var cta = document.getElementById('lms-course-opening-cta');
    var enterFiche = document.getElementById('lms-course-opening-enter-fiche');
    var staffBypassBtn = document.getElementById('lms-course-opening-staff-bypass');
    var cfgEl = document.getElementById('lms-course-opening-config-json');
    if (!root || !loader || !intro || !enroll || !cta || !cfgEl) return;

    var cfg;
    try {
        cfg = JSON.parse(cfgEl.textContent || '{}');
    } catch (e) {
        return;
    }
    var storageKeyPreamble = cfg.storageKeyPreamble || '';
    var ctaMode = cfg.ctaMode || 'enroll_gate';
    var lessonUrl = cfg.lessonUrl || '';
    var canAccessLearning = !!cfg.canAccessLearning;
    var staffBypass = !!cfg.staffBypass;

    function removeAll() {
        document.body.style.overflow = '';
        document.documentElement.classList.remove('lms-course-open-locked');
        if (root.parentNode) root.parentNode.removeChild(root);
        if (cfgEl.parentNode) cfgEl.parentNode.removeChild(cfgEl);
    }

    function markPreambleSeen() {
        if (!storageKeyPreamble) return;
        try {
            sessionStorage.setItem(storageKeyPreamble, '1');
        } catch (e) {}
    }

    function preambleSeen() {
        if (!storageKeyPreamble) return false;
        try {
            return sessionStorage.getItem(storageKeyPreamble) === '1';
        } catch (e) {
            return false;
        }
    }

    if (!storageKeyPreamble) {
        removeAll();
        return;
    }

    // Accès apprentissage déjà OK + préambule déjà vu → fiche directement.
    if (canAccessLearning && preambleSeen()) {
        removeAll();
        return;
    }

    root.hidden = false;
    document.body.style.overflow = 'hidden';
    document.documentElement.classList.add('lms-course-open-locked');

    var reduceMotion = false;
    try {
        reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e2) {}

    var bannerSrc = <?= json_encode($lmsOpeningBannerSrc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var minDelayMs = reduceMotion ? 0 : 400;
    var timeoutMs = 8000;
    var startTs = Date.now();
    var readyFired = false;

    function showPanel(panel, focusEl) {
        [intro, enroll].forEach(function (el) {
            if (!el) return;
            el.hidden = true;
            el.setAttribute('aria-hidden', 'true');
            el.classList.remove('lms-course-open-intro--in');
        });
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
        panel.classList.add('lms-course-open-intro--in');
        if (focusEl) {
            setTimeout(function () {
                try { focusEl.focus(); } catch (e3) {}
            }, reduceMotion ? 0 : 280);
        }
    }

    function finishToFiche() {
        document.body.style.overflow = '';
        document.documentElement.classList.remove('lms-course-open-locked');
        removeAll();
        var pageTitle = document.getElementById('lms-course-page-title');
        if (pageTitle) {
            pageTitle.setAttribute('tabindex', '-1');
            pageTitle.focus();
        }
    }

    function openAccessAfterPreamble() {
        markPreambleSeen();
        if (ctaMode === 'lesson' && lessonUrl) {
            window.location.href = lessonUrl;
            return;
        }
        finishToFiche();
    }

    function goToEnrollGate() {
        markPreambleSeen();
        loader.hidden = true;
        loader.setAttribute('aria-busy', 'false');
        showPanel(enroll, enroll.querySelector('button, a, textarea, input'));
    }

    function showIntroFromLoader() {
        if (readyFired) return;
        readyFired = true;
        var elapsed = Date.now() - startTs;
        var wait = Math.max(0, minDelayMs - elapsed);
        function go() {
            loader.setAttribute('aria-busy', 'false');
            loader.classList.add('lms-course-open-loader--out');
            // Si préambule déjà vu mais pas encore d’accès → porte d’inscription directement.
            if (preambleSeen() && !canAccessLearning) {
                loader.hidden = true;
                showPanel(enroll, enroll.querySelector('button, a, textarea, input'));
                return;
            }
            // Accès OK mais préambule pas encore vu → préambule puis fiche/leçon.
            if (canAccessLearning) {
                showPanel(intro, cta);
                setTimeout(function () { loader.hidden = true; }, reduceMotion ? 0 : 280);
                return;
            }
            showPanel(intro, cta);
            setTimeout(function () { loader.hidden = true; }, reduceMotion ? 0 : 280);
        }
        if (wait <= 0) go();
        else setTimeout(go, wait);
    }

    loader.hidden = false;

    if (!bannerSrc) {
        showIntroFromLoader();
    } else {
        var img = new Image();
        img.onload = showIntroFromLoader;
        img.onerror = showIntroFromLoader;
        img.src = bannerSrc;
        setTimeout(showIntroFromLoader, timeoutMs);
    }

    cta.addEventListener('click', function () {
        if (canAccessLearning) {
            openAccessAfterPreamble();
            return;
        }
        goToEnrollGate();
    });

    if (enterFiche) {
        enterFiche.addEventListener('click', function () {
            markPreambleSeen();
            if (ctaMode === 'lesson' && lessonUrl) {
                window.location.href = lessonUrl;
                return;
            }
            finishToFiche();
        });
    }

    if (staffBypassBtn) {
        staffBypassBtn.addEventListener('click', function () {
            markPreambleSeen();
            finishToFiche();
        });
    }
})();
</script>
