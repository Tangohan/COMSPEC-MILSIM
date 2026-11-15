/* Accueil du bureau SSE : entrée décalée des bandes quand elles arrivent à l'écran. Sans ce script, ou avec « mouvement réduit »,
   les bandes sont simplement affichées. */
(function () {
    'use strict';
    var root = document.querySelector('[data-ab-bands]');
    if (!root || !('IntersectionObserver' in window)) {
        return;
    }
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    var bands = Array.prototype.slice.call(root.querySelectorAll('[data-ab-band]'));
    var rank = 0;
    var show = function (band) {
        // Décalage entre les bandes qui apparaissent ensemble, pas depuis le haut de la page.
        band.style.setProperty('--ab-d', String(rank++));
        band.classList.add('is-inview');
    };
    var observer = new IntersectionObserver(function (entries) {
        rank = 0;
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                show(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    var limit = window.innerHeight || document.documentElement.clientHeight;
    root.classList.add('is-armed');
    bands.forEach(function (band) {
        // Déjà à l'écran : montrée tout de suite ; plus bas : à son arrivée. Une bande ne reste jamais cachée.
        if (band.getBoundingClientRect().top < limit) {
            show(band);
        } else {
            observer.observe(band);
        }
    });
    window.setTimeout(function () {
        if (!root.querySelector('.is-inview')) {
            root.classList.remove('is-armed');
        }
    }, 1200);
}());
