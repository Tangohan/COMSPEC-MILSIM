/*
 * SSE > Avis de recherche : visionneuse des images jointes à un avis (fiche de l'identité). Ouvre l'image en grand depuis la
 * galerie compacte, flèches et Échap au clavier. Aucun texte dans ce fichier : la légende vient de la page.
 */
(function () {
    'use strict';
    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-wc-gal-open]'));
    if (!buttons.length) { return; }
    var viewer = document.createElement('div');
    viewer.className = 'wc-viewer';
    viewer.hidden = true;
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-modal', 'true');
    var bar = document.createElement('div');
    bar.className = 'wc-viewer__bar';
    var cap = document.createElement('span');
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'wc-x';
    close.textContent = '×';
    close.setAttribute('aria-label', document.documentElement.lang === 'en' ? 'Close' : 'Fermer');
    bar.appendChild(cap);
    bar.appendChild(close);
    var stage = document.createElement('div');
    stage.className = 'wc-viewer__stage';
    var img = document.createElement('img');
    img.alt = '';
    stage.appendChild(img);
    var nav = document.createElement('div');
    nav.className = 'wc-viewer__nav';
    var prev = document.createElement('button');
    prev.type = 'button';
    prev.textContent = '‹';
    var next = document.createElement('button');
    next.type = 'button';
    next.textContent = '›';
    nav.appendChild(prev);
    nav.appendChild(next);
    viewer.appendChild(bar);
    viewer.appendChild(stage);
    viewer.appendChild(nav);
    document.body.appendChild(viewer);
    var index = 0;
    var opener = null;

    function show(i) {
        index = (i + buttons.length) % buttons.length;
        var b = buttons[index];
        img.src = b.getAttribute('data-full') || '';
        cap.textContent = b.getAttribute('data-cap') || '';
        nav.hidden = buttons.length < 2;
    }
    function open(i) {
        opener = document.activeElement;
        show(i);
        viewer.hidden = false;
        document.body.style.overflow = 'hidden';
        close.focus();
    }
    function shut() {
        viewer.hidden = true;
        img.removeAttribute('src');
        document.body.style.overflow = '';
        if (opener && opener.focus) { opener.focus(); }
    }
    buttons.forEach(function (b, i) { b.addEventListener('click', function () { open(i); }); });
    close.addEventListener('click', shut);
    prev.addEventListener('click', function () { show(index - 1); });
    next.addEventListener('click', function () { show(index + 1); });
    viewer.addEventListener('click', function (e) { if (e.target === viewer || e.target === stage) { shut(); } });
    document.addEventListener('keydown', function (e) {
        if (viewer.hidden) { return; }
        if (e.key === 'Escape') { shut(); } else if (e.key === 'ArrowLeft') { show(index - 1); } else if (e.key === 'ArrowRight') { show(index + 1); }
    });
})();
