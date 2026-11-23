(function () {
    'use strict';

    var root = document.querySelector('[data-maintenance-backgrounds]');
    var toggle = document.querySelector('[data-background-toggle]');
    if (!root || !toggle || document.body.classList.contains('no-anim')) return;

    var layers = root.querySelectorAll('.stage__photo');
    if (layers.length !== 2) return;

    var motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var active = 0;
    var candidate = 1;
    var paused = false;
    var loading = false;
    var timer = null;

    function canPlay() {
        return !paused && !document.hidden && !motion.matches;
    }

    function advance() {
        if (!canPlay() || loading) return;

        var next = 1 - active;
        var img = layers[next].querySelector('img');
        candidate = candidate % 4 + 1;
        var src = root.dataset.base + 'hero-' + candidate;
        loading = true;
        var settled = false;

        function finish(loaded) {
            if (settled) return;
            settled = true;
            img.onload = null;
            img.onerror = null;
            loading = false;
            // Garder le fond actuel si une photo manque ou si l’animation a été mise en pause.
            if (!loaded || !canPlay()) return;
            layers[next].classList.add('is-active');
            layers[active].classList.remove('is-active');
            active = next;
        }

        img.onload = function () { finish(true); };
        img.onerror = function () { finish(false); };
        img.srcset = src + '-960.webp 960w, ' + src + '-1920.webp 1920w';
        img.src = src + '-1920.webp';
        if (img.complete) finish(img.naturalWidth > 0);
    }

    function sync() {
        window.clearInterval(timer);
        timer = null;
        toggle.hidden = motion.matches;
        toggle.setAttribute('aria-pressed', String(paused));
        toggle.textContent = paused ? toggle.dataset.resumeLabel : toggle.dataset.pauseLabel;
        document.body.classList.toggle('backgrounds-paused', !canPlay());
        if (canPlay()) timer = window.setInterval(advance, 12000);
    }

    toggle.addEventListener('click', function () {
        paused = !paused;
        sync();
    });
    document.addEventListener('visibilitychange', sync);
    motion.addEventListener('change', sync);
    sync();
})();
