/**
 * Accueil public Athena — carrousel hero (images / vidéos, son) et formulaire newsletter.
 * Extrait de views/home/index.php pour être mis en cache ; chargé en defer.
 * Attend window.hiI18n (libellés) défini en ligne dans la vue.
 */
(function () {
    var hiI18n = window.hiI18n || {};
    (function heroMedia() {
        var KEY = 'athena_immersive_v1';
        var VOL_KEY = 'athena_immersive_vol';
        var IMAGE_INTERVAL_MS = 6000;
        var VIDEO_FALLBACK_MS = 30000;
        var VIDEO_DECODE_MIN_MS = 14000;
        var VIDEO_DECODE_MAX_MS = 40000;
        var dlg = document.getElementById('immersive-consent');
        var btnLater = document.getElementById('btn-enable-immersive');
        var imageRoot = document.getElementById('heroImageSlides');
        var videoRoot = document.getElementById('heroVideoSlides');
        var mediaClock = document.getElementById('timestamp');
        var mediaProgress = document.getElementById('hero-media-progress');
        var mediaProgressFill = document.getElementById('hero-media-progress-fill');
        var imageSlides = imageRoot ? imageRoot.querySelectorAll('.slide') : [];
        var candidateSlides = videoRoot ? Array.prototype.slice.call(videoRoot.querySelectorAll('[data-hero-video-slide]')) : [];
        var videoSlides = [];
        var dots = document.querySelectorAll('#hero-dots .hi-media-dot');
        var toggleBtn = document.getElementById('hero-av-toggle');
        var muteBtn = document.getElementById('hero-av-mute');
        var volInput = document.getElementById('hero-av-volume');
        var iconPlay = toggleBtn ? toggleBtn.querySelector('.hi-av__icon--play') : null;
        var iconStop = toggleBtn ? toggleBtn.querySelector('.hi-av__icon--stop') : null;
        var iconSpeaker = muteBtn ? muteBtn.querySelector('.hi-av__icon--speaker') : null;
        var iconMuted = muteBtn ? muteBtn.querySelector('.hi-av__icon--muted') : null;
        var reducedMotion = false;
        try {
            reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (e) {}
        var mode = 'images';
        var current = 0;
        var imageTimer = null;
        var videoSafetyTimer = null;
        var videoDecodeTimer = null;
        var playToken = 0;
        var rotationPaused = false;
        var lastVol = 0.55;
        var saved = null;
        var withSound = false;
        var imageSlideStartedAt = Date.now();

        try { saved = localStorage.getItem(KEY); } catch (e) {}
        // Ancien « off » = refus du son immersif, pas refus des vidéos muted.
        if (saved === 'off') {
            saved = 'silent';
            try { localStorage.setItem(KEY, 'silent'); } catch (e) {}
        }
        try {
            var storedVol = localStorage.getItem(VOL_KEY);
            if (storedVol !== null) lastVol = Math.min(1, Math.max(0, parseFloat(storedVol) || 0));
        } catch (e) {}

        function activeVideo() {
            if (!videoSlides.length) return null;
            var slide = videoSlides[current] || videoSlides[0];
            return slide ? slide.querySelector('[data-hero-video]') : null;
        }

        function formatMediaClock(seconds) {
            if (!isFinite(seconds) || seconds < 0) return '--:--';
            var total = Math.floor(seconds);
            var m = Math.floor(total / 60);
            var s = total % 60;
            return m.toString().padStart(2, '0') + ':' + s.toString().padStart(2, '0');
        }

        function setMediaProgress(ratio) {
            var pct = 0;
            if (isFinite(ratio) && ratio > 0) {
                pct = Math.max(0, Math.min(1, ratio)) * 100;
            }
            if (mediaProgressFill) {
                var prev = parseFloat(mediaProgressFill.dataset.pct || '0') || 0;
                var jumpingBack = pct + 1.5 < prev;
                if (jumpingBack) {
                    mediaProgressFill.style.transition = 'none';
                } else {
                    mediaProgressFill.style.transition = '';
                }
                mediaProgressFill.style.transform = 'scaleX(' + (pct / 100) + ')';
                mediaProgressFill.dataset.pct = String(pct);
                if (jumpingBack) {
                    // Force reflow so the next tick can animate again.
                    void mediaProgressFill.offsetWidth;
                    mediaProgressFill.style.transition = '';
                }
            }
            if (mediaProgress) {
                mediaProgress.setAttribute('aria-valuenow', String(Math.round(pct)));
                mediaProgress.classList.toggle('is-active', pct > 0.5);
            }
        }

        function updateMediaClock() {
            if (mode === 'videos') {
                var video = activeVideo();
                if (!video) {
                    if (mediaClock) mediaClock.textContent = '--:-- / --:--';
                    setMediaProgress(0);
                    return;
                }
                var cur = video.currentTime || 0;
                var dur = (isFinite(video.duration) && video.duration > 0) ? video.duration : 0;
                if (mediaClock) {
                    mediaClock.textContent = formatMediaClock(cur) + ' / ' + (dur > 0 ? formatMediaClock(dur) : '--:--');
                }
                setMediaProgress(dur > 0 ? cur / dur : 0);
                return;
            }
            if (!imageSlides.length) {
                if (mediaClock) mediaClock.textContent = '--:-- / --:--';
                setMediaProgress(0);
                return;
            }
            var elapsed = Math.min(IMAGE_INTERVAL_MS / 1000, Math.max(0, (Date.now() - imageSlideStartedAt) / 1000));
            var total = IMAGE_INTERVAL_MS / 1000;
            if (mediaClock) {
                mediaClock.textContent = formatMediaClock(elapsed) + ' / ' + formatMediaClock(total);
            }
            setMediaProgress(total > 0 ? elapsed / total : 0);
        }

        function syncDots() {
            var count = mode === 'videos' ? videoSlides.length : imageSlides.length;
            for (var i = 0; i < dots.length; i++) {
                var on = i === current && i < count;
                dots[i].classList.toggle('is-active', on);
                dots[i].hidden = i >= count;
            }
        }

        function updateImageSlide(index) {
            if (!imageSlides.length) return;
            var img = imageSlides[current] ? imageSlides[current].querySelector('img') : null;
            if (img) img.style.transform = 'scale(1)';
            if (imageSlides[current]) {
                imageSlides[current].classList.replace('opacity-100', 'opacity-0');
            }
            current = (index + imageSlides.length) % imageSlides.length;
            if (imageSlides[current]) {
                imageSlides[current].classList.replace('opacity-0', 'opacity-100');
            }
            img = imageSlides[current] ? imageSlides[current].querySelector('img') : null;
            if (img) img.style.transform = 'scale(1.08)';
            imageSlideStartedAt = Date.now();
            syncDots();
            updateMediaClock();
        }

        function clearVideoSafety() {
            if (videoSafetyTimer) {
                clearTimeout(videoSafetyTimer);
                videoSafetyTimer = null;
            }
            if (videoDecodeTimer) {
                clearTimeout(videoDecodeTimer);
                videoDecodeTimer = null;
            }
        }

        function stopImageSlider() {
            if (imageTimer) {
                clearInterval(imageTimer);
                imageTimer = null;
            }
        }

        function startImageSlider() {
            stopImageSlider();
            if (reducedMotion || imageSlides.length < 2) return;
            imageTimer = setInterval(function () {
                updateImageSlide(current + 1);
            }, IMAGE_INTERVAL_MS);
        }

        function setImageStandby(standby) {
            if (!imageRoot) return;
            imageRoot.classList.toggle('hi-hero-images--standby', !!standby);
            if (standby) {
                imageRoot.classList.remove('hi-hero-images--waiting');
                stopImageSlider();
                imageSlides.forEach(function (s) {
                    s.classList.add('opacity-0');
                    s.classList.remove('opacity-100');
                });
            } else if (imageSlides.length) {
                imageSlides.forEach(function (s, i) {
                    s.classList.toggle('opacity-100', i === current);
                    s.classList.toggle('opacity-0', i !== current);
                });
            }
        }

        function setImagesWaiting(waiting) {
            if (!imageRoot) return;
            if (waiting) {
                imageRoot.classList.add('hi-hero-images--waiting');
                imageRoot.classList.remove('hi-hero-images--standby');
            } else {
                imageRoot.classList.remove('hi-hero-images--waiting');
            }
        }

        function pauseAllVideosExcept(keep) {
            videoSlides.forEach(function (slide) {
                var v = slide.querySelector('[data-hero-video]');
                if (!v || v === keep) return;
                try { v.pause(); } catch (e) {}
                setClipAudible(v, false);
                try { v.currentTime = 0; } catch (e2) {}
            });
        }

        function videoHasPaintedFrame(video) {
            return !!(video && video.videoWidth > 0 && video.readyState >= 2);
        }

        function decodeTimeoutForVideo(video) {
            if (!video) return VIDEO_DECODE_MIN_MS;
            var slide = video.closest ? video.closest('[data-hero-video-slide]') : null;
            var bytes = slide ? parseInt(slide.getAttribute('data-bytes') || '0', 10) : 0;
            if (!bytes || isNaN(bytes)) return 18000;
            var estimated = Math.round((bytes / (1024 * 1024)) * 1200);
            return Math.min(VIDEO_DECODE_MAX_MS, Math.max(VIDEO_DECODE_MIN_MS, estimated));
        }

        function scheduleDecodeWatch(token, video, slide, startedAt) {
            if (videoDecodeTimer) {
                clearTimeout(videoDecodeTimer);
                videoDecodeTimer = null;
            }
            if (!startedAt) startedAt = Date.now();
            var budget = decodeTimeoutForVideo(video);
            videoDecodeTimer = setTimeout(function () {
                videoDecodeTimer = null;
                if (token !== playToken || mode !== 'videos' || rotationPaused) return;
                if (!video) return;
                if (videoHasPaintedFrame(video)) {
                    revealVideoFrame(video);
                    return;
                }
                var elapsed = Date.now() - startedAt;
                if (elapsed < budget && (video.readyState >= 1 || video.networkState === 2)) {
                    scheduleDecodeWatch(token, video, slide, startedAt);
                    return;
                }
                handleVideoError(slide);
            }, Math.min(4000, budget));
        }

        function revealVideoFrame(video) {
            if (!video || mode !== 'videos') return false;
            if (!videoHasPaintedFrame(video)) return false;
            if (videoRoot) {
                videoRoot.classList.remove('hi-hero-videos--idle');
                videoRoot.setAttribute('aria-hidden', 'false');
            }
            setImagesWaiting(false);
            setImageStandby(true);
            applyAudioToActive();
            return true;
        }

        function desiredVolume() {
            return lastVol > 0 ? lastVol : 0.55;
        }

        /** Mute / unmute + attribut HTML (certains navigateurs restent muets si l’attribut `muted` reste). */
        function setClipAudible(video, audible) {
            if (!video) return;
            if (audible) {
                video.muted = false;
                video.removeAttribute('muted');
                try { video.volume = desiredVolume(); } catch (e) {}
            } else {
                video.muted = true;
                video.setAttribute('muted', '');
                try { video.volume = 0; } catch (e2) {}
            }
        }

        function applyAudioToActive() {
            videoSlides.forEach(function (slide, i) {
                var v = slide.querySelector('[data-hero-video]');
                if (!v) return;
                setClipAudible(v, i === current && withSound);
            });
        }

        /**
         * Débloque le son pendant un geste utilisateur.
         * Obligatoire : 3 balises <video> distinctes — chaque clip doit être
         * « unlock » ici, sinon le carrousel redevient muet au slide suivant
         * (autoplay policy navigateur).
         */
        function unlockSoundFromUserGesture(vol) {
            if (typeof vol === 'number' && isFinite(vol) && vol > 0) {
                lastVol = Math.min(1, Math.max(0, vol));
            } else if (!(lastVol > 0)) {
                lastVol = 0.55;
            }
            withSound = true;
            persistVol(lastVol);
            try { localStorage.setItem(KEY, 'full'); } catch (e) {}

            videoSlides.forEach(function (slide) {
                var v = slide.querySelector('[data-hero-video]');
                if (!v) return;
                v.muted = false;
                v.removeAttribute('muted');
                try { v.volume = lastVol; } catch (e2) {}
            });
            applyAudioToActive();

            var active = activeVideo();
            if (active && mode === 'videos') {
                var playPromise = active.play();
                if (playPromise && playPromise.catch) {
                    playPromise.catch(function () {});
                }
            }
            if (btnLater) btnLater.classList.add('hidden');
            syncAvUi();
        }

        function armFrameReveal(video, token) {
            if (!video) return;

            function tryReveal() {
                if (token !== playToken || mode !== 'videos') return true;
                return revealVideoFrame(video);
            }

            if (tryReveal()) return;

            if (typeof video.requestVideoFrameCallback === 'function') {
                try {
                    video.requestVideoFrameCallback(function () {
                        tryReveal();
                    });
                } catch (e) {}
            }

            var attempts = 0;
            var poll = setInterval(function () {
                attempts++;
                if (token !== playToken || tryReveal() || attempts > 40) {
                    clearInterval(poll);
                }
            }, 100);
        }

        function syncAvUi() {
            var video = activeVideo();
            var playing = !!(mode === 'videos' && video && !video.paused);
            if (toggleBtn) {
                toggleBtn.setAttribute('data-state', playing ? 'playing' : 'stopped');
                toggleBtn.setAttribute('aria-label', playing ? hiI18n.pauseVideo : hiI18n.playVideo);
                if (iconPlay) iconPlay.hidden = playing;
                if (iconStop) iconStop.hidden = !playing;
            }
            if (!video) {
                if (muteBtn) {
                    muteBtn.setAttribute('aria-pressed', 'true');
                    muteBtn.setAttribute('aria-label', hiI18n.unmute);
                    if (iconSpeaker) iconSpeaker.hidden = true;
                    if (iconMuted) iconMuted.hidden = false;
                }
                updateMediaClock();
                return;
            }
            var muted = video.muted || video.volume === 0;
            var vol = muted ? 0 : video.volume;
            if (volInput) {
                volInput.value = String(vol);
                volInput.style.setProperty('--hi-av-pct', Math.round(vol * 100) + '%');
                volInput.setAttribute('aria-valuenow', String(Math.round(vol * 100)));
            }
            if (muteBtn) {
                muteBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
                muteBtn.setAttribute('aria-label', muted ? hiI18n.unmute : hiI18n.mute);
                if (iconSpeaker) iconSpeaker.hidden = muted;
                if (iconMuted) iconMuted.hidden = !muted;
            }
            updateMediaClock();
        }

        function persistVol(vol) {
            try { localStorage.setItem(VOL_KEY, String(vol)); } catch (e) {}
        }

        function applyVolume(vol, unmute) {
            vol = Math.min(1, Math.max(0, vol));
            if (vol > 0) {
                unlockSoundFromUserGesture(vol);
                return;
            }
            withSound = false;
            persistVol(lastVol > 0 ? lastVol : 0.55);
            try { localStorage.setItem(KEY, 'silent'); } catch (e) {}
            applyAudioToActive();
            syncAvUi();
        }

        function scheduleVideoAdvance(token, video) {
            if (videoSlides.length < 2) return;
            clearTimeout(videoSafetyTimer);
            videoSafetyTimer = null;
            // Filet de sécurité : la fin naturelle passe par l’événement `ended`.
            var remainingMs = VIDEO_FALLBACK_MS;
            if (video && isFinite(video.duration) && video.duration > 0) {
                remainingMs = Math.max(1500, video.duration * 1000 + 1000);
            }
            videoSafetyTimer = setTimeout(function () {
                videoSafetyTimer = null;
                if (token !== playToken || rotationPaused || mode !== 'videos') return;
                playVideoAt(current + 1);
            }, remainingMs);
        }

        function playVideoAt(index, opts) {
            opts = opts || {};
            if (!videoSlides.length) return;
            clearVideoSafety();
            var token = ++playToken;
            var next = (index + videoSlides.length) % videoSlides.length;
            var nextSlide = videoSlides[next];
            var nextVideo = nextSlide ? nextSlide.querySelector('[data-hero-video]') : null;

            pauseAllVideosExcept(nextVideo);
            videoSlides.forEach(function (slide, i) {
                slide.classList.toggle('is-active', i === next);
            });
            current = next;
            syncDots();

            if (!nextVideo) {
                updateMediaClock();
                return;
            }

            // Autoplay policy : démarrer muet, puis restaurer si déjà unlock.
            setClipAudible(nextVideo, false);

            if (rotationPaused) {
                syncAvUi();
                return;
            }

            pruneUnplayableSources(nextVideo);

            // Tant qu’aucune frame n’est peinte, garder le JPG visible au-dessus
            // (uniquement au premier affichage — pas entre deux clips).
            if (!videoHasPaintedFrame(nextVideo) && imageRoot && !imageRoot.classList.contains('hi-hero-images--standby')) {
                setImagesWaiting(true);
            }

            function afterPlayStarted() {
                if (token !== playToken || mode !== 'videos') return;
                armFrameReveal(nextVideo, token);
                if (withSound) setClipAudible(nextVideo, true);
                else applyAudioToActive();
                scheduleVideoAdvance(token, nextVideo);
                syncAvUi();
            }

            if (opts.reset !== false) {
                try {
                    if (nextVideo.currentTime > 0.05) nextVideo.currentTime = 0;
                } catch (e) {}
            }

            var playPromise = nextVideo.play();
            if (playPromise && playPromise.then) {
                playPromise.then(function () {
                    if (token !== playToken) return;
                    afterPlayStarted();
                }).catch(function () {
                    if (token !== playToken) return;
                    // Échec autoplay : rester muet pour rejouer, sans effacer le choix son.
                    setClipAudible(nextVideo, false);
                    nextVideo.play().then(function () {
                        if (token !== playToken) return;
                        afterPlayStarted();
                    }).catch(function () {
                        if (token !== playToken) return;
                        handleVideoError(nextSlide);
                    });
                });
            } else {
                afterPlayStarted();
            }

            scheduleDecodeWatch(token, nextVideo, nextSlide);

            syncAvUi();
        }

        function onVideoEnded(event) {
            if (mode !== 'videos' || rotationPaused || videoSlides.length < 2) return;
            if (event && event.target !== activeVideo()) return;
            playVideoAt(current + 1);
        }

        function handleVideoError(slide) {
            if (!slide) return;
            var idx = videoSlides.indexOf(slide);
            if (idx === -1) return;
            var doomed = slide.querySelector('[data-hero-video]');
            if (doomed) {
                try { doomed.pause(); } catch (e) {}
                setClipAudible(doomed, false);
            }
            videoSlides.splice(idx, 1);
            slide.hidden = true;
            slide.classList.remove('is-active');
            if (!videoSlides.length) {
                enableImageMode();
                return;
            }
            if (current >= videoSlides.length) current = 0;
            playVideoAt(current, { reset: true });
        }

        function enableVideoMode(soundOn) {
            if (!videoSlides.length || reducedMotion) {
                enableImageMode();
                return;
            }
            if (soundOn) {
                withSound = true;
                try { localStorage.setItem(KEY, 'full'); } catch (e) {}
            } else {
                withSound = false;
                try { localStorage.setItem(KEY, 'silent'); } catch (e) {}
            }
            mode = 'videos';
            stopImageSlider();
            if (videoRoot) {
                videoRoot.classList.remove('hi-hero-videos--idle');
                videoRoot.setAttribute('aria-hidden', 'false');
            }
            // JPG visibles jusqu’à la 1re frame (évite le fond noir).
            setImagesWaiting(true);
            setImageStandby(false);
            rotationPaused = false;
            if (videoSlides.length === 1) {
                var only = videoSlides[0].querySelector('[data-hero-video]');
                if (only) only.loop = true;
            } else {
                videoSlides.forEach(function (slide) {
                    var v = slide.querySelector('[data-hero-video]');
                    if (v) v.loop = false;
                });
            }
            current = 0;
            playVideoAt(0, { reset: true });
            if (btnLater) {
                if (withSound) btnLater.classList.add('hidden');
                else btnLater.classList.remove('hidden');
            }
            syncAvUi();
        }

        function enableImageMode() {
            mode = 'images';
            playToken++;
            clearVideoSafety();
            rotationPaused = false;
            pauseAllVideosExcept(null);
            videoSlides.forEach(function (slide) {
                slide.classList.remove('is-active');
            });
            if (videoRoot) {
                videoRoot.classList.add('hi-hero-videos--idle');
                videoRoot.setAttribute('aria-hidden', 'true');
            }
            setImagesWaiting(false);
            setImageStandby(false);
            current = 0;
            imageSlideStartedAt = Date.now();
            updateImageSlide(0);
            startImageSlider();
            syncAvUi();
        }

        function togglePlayback() {
            if (!videoSlides.length) return;
            if (mode !== 'videos') {
                enableVideoMode(saved === 'full');
                return;
            }
            var video = activeVideo();
            if (!video) return;
            if (video.paused) {
                rotationPaused = false;
                var token = playToken;
                // Autoplay policy : démarrer muet, puis réappliquer le son déjà unlock.
                setClipAudible(video, false);
                var playPromise = video.play();
                if (playPromise && playPromise.then) {
                    playPromise.then(function () {
                        if (token !== playToken) return;
                        armFrameReveal(video, token);
                        if (withSound) setClipAudible(video, true);
                        else applyAudioToActive();
                        scheduleVideoAdvance(token, video);
                        syncAvUi();
                    }).catch(function () {
                        syncAvUi();
                    });
                } else {
                    armFrameReveal(video, token);
                    if (withSound) setClipAudible(video, true);
                    else applyAudioToActive();
                    scheduleVideoAdvance(token, video);
                    syncAvUi();
                }
                return;
            }
            rotationPaused = true;
            clearVideoSafety();
            video.pause();
            syncAvUi();
        }

        window.nextSlide = function () {
            if (mode === 'videos' && videoSlides.length) {
                playVideoAt(current + 1);
                return;
            }
            updateImageSlide(current + 1);
        };
        window.prevSlide = function () {
            if (mode === 'videos' && videoSlides.length) {
                playVideoAt(current - 1);
                return;
            }
            updateImageSlide(current - 1);
        };

        function canPlayMime(mime) {
            if (!mime) return true;
            try {
                var probe = document.createElement('video');
                var answer = probe.canPlayType(mime);
                if (answer === 'probably' || answer === 'maybe') return true;
                // codecs incomplets (ex. codecs="avc1") → "" alors que video/mp4 est OK
                var base = String(mime).split(';')[0].trim();
                if (base && base !== mime) {
                    var baseAnswer = probe.canPlayType(base);
                    return baseAnswer === 'probably' || baseAnswer === 'maybe';
                }
                return false;
            } catch (e) {
                return true;
            }
        }

        function pruneUnplayableSources(video) {
            if (!video) return false;
            var sources = Array.prototype.slice.call(video.querySelectorAll('source'));
            var kept = 0;
            sources.forEach(function (source) {
                var type = source.getAttribute('type') || '';
                if (!type) {
                    kept++;
                    return;
                }
                if (canPlayMime(type)) {
                    // Si seul le MIME de base est accepté, retirer un codecs= incomplet
                    // pour que le navigateur ne saute plus la source au parsing.
                    var base = type.split(';')[0].trim();
                    var probe = document.createElement('video');
                    var full = '';
                    try { full = probe.canPlayType(type); } catch (e) {}
                    if ((!full || full === '') && base && base !== type) {
                        source.setAttribute('type', base);
                    }
                    kept++;
                } else {
                    source.remove();
                }
            });
            return kept > 0;
        }

        function bindVideoEvents() {
            videoSlides.forEach(function (slide) {
                var v = slide.querySelector('[data-hero-video]');
                if (!v) return;
                v.addEventListener('ended', onVideoEnded);
                v.addEventListener('play', syncAvUi);
                v.addEventListener('pause', syncAvUi);
                v.addEventListener('volumechange', syncAvUi);
                v.addEventListener('timeupdate', function () {
                    if (mode !== 'videos' || v !== activeVideo()) return;
                    updateMediaClock();
                    if (imageRoot && imageRoot.classList.contains('hi-hero-images--waiting')) {
                        revealVideoFrame(v);
                    }
                });
                v.addEventListener('loadedmetadata', function () {
                    if (mode === 'videos' && v === activeVideo()) updateMediaClock();
                });
                v.addEventListener('loadeddata', function () {
                    if (mode === 'videos' && v === activeVideo()) revealVideoFrame(v);
                });
                v.addEventListener('playing', function () {
                    if (mode === 'videos' && v === activeVideo()) {
                        revealVideoFrame(v);
                        pauseAllVideosExcept(v);
                        if (withSound) setClipAudible(v, true);
                        else applyAudioToActive();
                    }
                });
                v.addEventListener('error', function () {
                    if (mode === 'videos') handleVideoError(slide);
                });
            });
        }

        function startHero() {
            if (reducedMotion || !videoSlides.length) {
                enableImageMode();
                if (btnLater && videoSlides.length) btnLater.classList.remove('hidden');
                return;
            }
            // Toujours démarrer muet (autoplay). Le son exige un geste :
            // consentement, bouton muet, curseur volume, ou lien « activer le son ».
            enableVideoMode(false);
            if (saved === 'full') {
                if (btnLater) btnLater.classList.remove('hidden');
            } else if (saved !== 'silent' && dlg && typeof dlg.showModal === 'function') {
                window.setTimeout(function () { dlg.showModal(); }, 900);
            }
        }

        if (toggleBtn) toggleBtn.addEventListener('click', togglePlayback);
        if (muteBtn) muteBtn.addEventListener('click', function () {
            if (mode !== 'videos') {
                unlockSoundFromUserGesture(desiredVolume());
                enableVideoMode(true);
                return;
            }
            var video = activeVideo();
            if (!video) {
                unlockSoundFromUserGesture(desiredVolume());
                return;
            }
            if (!withSound || video.muted || video.volume === 0) {
                unlockSoundFromUserGesture(desiredVolume());
            } else {
                withSound = false;
                try { localStorage.setItem(KEY, 'silent'); } catch (e) {}
                applyAudioToActive();
                if (btnLater) btnLater.classList.remove('hidden');
                syncAvUi();
            }
        });
        if (volInput) {
            volInput.addEventListener('input', function () {
                var v = parseFloat(volInput.value) || 0;
                if (v > 0) {
                    if (mode !== 'videos') {
                        unlockSoundFromUserGesture(v);
                        enableVideoMode(true);
                        return;
                    }
                    unlockSoundFromUserGesture(v);
                    return;
                }
                applyVolume(0, true);
            });
        }

        var yes = document.getElementById('immersive-yes');
        var no = document.getElementById('immersive-no');
        if (yes) yes.addEventListener('click', function () {
            // Unlock synchrone dans le geste — avant tout play() async.
            unlockSoundFromUserGesture(desiredVolume());
            if (mode !== 'videos') enableVideoMode(true);
            else applyAudioToActive();
            if (dlg) dlg.close();
        });
        if (no) no.addEventListener('click', function () {
            withSound = false;
            try { localStorage.setItem(KEY, 'silent'); } catch (e) {}
            if (mode !== 'videos') enableVideoMode(false);
            else {
                applyAudioToActive();
                if (btnLater) btnLater.classList.remove('hidden');
                syncAvUi();
            }
            if (dlg) dlg.close();
        });
        if (btnLater) btnLater.addEventListener('click', function () {
            unlockSoundFromUserGesture(desiredVolume());
            if (mode !== 'videos') enableVideoMode(true);
            else applyAudioToActive();
        });

        if (!reducedMotion && candidateSlides.length) {
            setImageStandby(false);
            if (imageSlides.length) {
                imageSlides[0].classList.add('opacity-100');
                imageSlides[0].classList.remove('opacity-0');
            }

            var presentSlides = candidateSlides.filter(function (slide) {
                return slide.getAttribute('data-present') === '1';
            });
            videoSlides = presentSlides.length ? presentSlides.slice() : candidateSlides.slice();
            candidateSlides.forEach(function (slide) {
                slide.hidden = videoSlides.indexOf(slide) === -1;
            });
            bindVideoEvents();
            enableImageMode();
            startHero();
        } else {
            videoSlides = [];
            bindVideoEvents();
            startHero();
        }

        try {
            var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            var onMotionChange = function (event) {
                reducedMotion = !!event.matches;
                if (event.matches) {
                    enableImageMode();
                } else if (videoSlides.length) {
                    enableVideoMode(false);
                    if (saved === 'full' && btnLater) btnLater.classList.remove('hidden');
                }
            };
            if (motionQuery.addEventListener) motionQuery.addEventListener('change', onMotionChange);
            else if (motionQuery.addListener) motionQuery.addListener(onMotionChange);
        } catch (e) {}

        setInterval(function () {
            if (mode === 'images') updateMediaClock();
        }, 250);
        updateMediaClock();
    })();

    (function newsletterForm() {
        var form = document.querySelector('[data-newsletter-form]');
        if (!form) return;

        var email = form.querySelector('#newsletter-email');
        var errorEl = document.getElementById('newsletter-email-error');
        var submit = form.querySelector('[data-newsletter-submit]');
        var label = form.querySelector('[data-newsletter-submit-label]');
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        function setInvalid(isInvalid) {
            if (!email) return;
            email.classList.toggle('is-invalid', isInvalid);
            email.setAttribute('aria-invalid', isInvalid ? 'true' : 'false');
            if (errorEl) errorEl.hidden = !isInvalid;
        }

        function setLoading(isLoading) {
            if (!submit || !label) return;
            submit.classList.toggle('is-loading', isLoading);
            submit.disabled = isLoading;
            label.textContent = isLoading
                ? (submit.getAttribute('data-label-loading') || hiI18n.newsletterLoading)
                : (submit.getAttribute('data-label-idle') || hiI18n.newsletterSubmit);
        }

        if (email) {
            email.addEventListener('input', function () {
                if (email.value.trim() !== '') setInvalid(false);
            });
        }

        form.addEventListener('submit', function (event) {
            if (submit && submit.disabled && !submit.classList.contains('is-loading')) {
                event.preventDefault();
                return;
            }
            var value = email ? email.value.trim() : '';
            if (!value || !emailPattern.test(value)) {
                event.preventDefault();
                setInvalid(true);
                if (email) email.focus();
                return;
            }
            setInvalid(false);
            setLoading(true);
        });
    })();
})();
