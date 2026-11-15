/*
 * Fiche identité › Photos : visionneuse plein écran (flèches, Échap, zoom, comparaison avec le portrait, métadonnées) et
 * repli des vignettes dont l'image ne charge pas. Les textes viennent tous du HTML de la page (donc traduits avec elle) :
 * ce script n'écrit aucune phrase. Sans lui, chaque vignette reste un lien direct vers l'image.
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-idg]');
    if (!root) {
        return;
    }

    // Vignette dont l'image est introuvable : texte de repli au lieu de l'icône d'image cassée.
    function markBroken(img) {
        var thumb = img.closest ? img.closest('.idg-thumb') : null;
        if (thumb) { thumb.classList.add('is-broken'); }
    }
    root.addEventListener('error', function (e) {
        if (e.target && e.target.tagName === 'IMG') { markBroken(e.target); }
    }, true);
    Array.prototype.forEach.call(root.querySelectorAll('.idg-thumb img'), function (img) {
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) { markBroken(img); }
    });

    var viewer = root.querySelector('[data-idg-viewer]');
    var items = Array.prototype.slice.call(root.querySelectorAll('[data-idg-item]'));
    var editLink = root.querySelector('[data-idg-edit]');
    if (editLink) {
        editLink.addEventListener('click', function (e) {
            var d = document.getElementById('images');
            if (d && d.tagName === 'DETAILS') {
                e.preventDefault();
                d.open = true;
                d.scrollIntoView({ block: 'start' });
            }
        });
    }
    if (!viewer || !items.length) {
        return;
    }

    var $ = function (sel) { return viewer.querySelector(sel); };
    var img = $('[data-idv-img]');
    var cmpFig = $('[data-idv-cmp]');
    var cmpImg = $('[data-idv-cmp-img]');
    var cmpLabel = $('[data-idv-cmp-label]');
    var stage = $('[data-idv-stage]');
    var broken = $('[data-idv-broken]');
    var title = $('[data-idv-title]');
    var meta = $('[data-idv-meta]');
    var count = $('[data-idv-count]');
    var zoomLabel = $('[data-idv-zoom]');
    var portraitForm = $('[data-idv-portrait]');
    var portraitId = $('[data-idv-portrait-id]');
    var download = $('[data-idv-download]');
    var library = $('[data-idv-library]');
    var compareBtn = $('[data-idv="compare"]');
    // Dans la visionneuse, l'image affichée se charge tout de suite (le chargement différé vaut pour les vignettes).
    img.loading = 'eager';
    cmpImg.loading = 'eager';
    var index = -1;
    var zoom = 1;
    var panX = 0;
    var panY = 0;
    var comparing = false;
    var opener = null;
    var drag = null;

    function applyZoom() {
        [img, cmpImg].forEach(function (el) {
            el.style.setProperty('--z', String(zoom));
            el.style.setProperty('--x', panX + 'px');
            el.style.setProperty('--y', panY + 'px');
        });
        stage.classList.toggle('is-zoomed', zoom > 1);
        zoomLabel.textContent = Math.round(zoom * 100) + ' %';
    }

    function setZoom(z) {
        zoom = Math.max(1, Math.min(6, z));
        if (zoom === 1) { panX = 0; panY = 0; }
        applyZoom();
    }

    function holderIndex() {
        for (var i = 0; i < items.length; i++) {
            if (items[i].hasAttribute('data-holder')) { return i; }
        }
        return -1;
    }

    function compareIndex() {
        var h = holderIndex();
        if (h >= 0 && h !== index) { return h; }
        return items.length > 1 ? (index + 1) % items.length : -1;
    }

    function showCompare() {
        var ci = comparing ? compareIndex() : -1;
        cmpFig.hidden = ci < 0;
        cmpLabel.hidden = true;
        if (ci >= 0) {
            cmpImg.src = items[ci].getAttribute('data-src') || items[ci].getAttribute('href');
            var m = items[ci].parentNode.querySelector('.idg-meta');
            cmpFig.querySelector('figcaption').textContent = m ? m.getAttribute('data-title') || '' : '';
        }
        compareBtn.setAttribute('aria-pressed', comparing && ci >= 0 ? 'true' : 'false');
    }

    function show(i, push) {
        index = (i + items.length) % items.length;
        var a = items[index];
        var src = a.getAttribute('data-src') || a.getAttribute('href');
        broken.hidden = true;
        img.hidden = false;
        img.src = src;
        img.alt = '';
        zoom = 1;
        panX = 0;
        panY = 0;
        applyZoom();
        var m = a.parentNode.querySelector('.idg-meta');
        title.textContent = m ? m.getAttribute('data-title') || '' : '';
        meta.innerHTML = '';
        if (m) {
            var dl = m.querySelector('dl');
            if (dl && dl.children.length) { meta.appendChild(dl.cloneNode(true)); }
        }
        count.textContent = (index + 1) + ' / ' + items.length;
        var isHolder = a.hasAttribute('data-holder');
        if (portraitForm) {
            portraitForm.hidden = isHolder || !a.getAttribute('data-id') || a.getAttribute('data-id') === '0';
            portraitId.value = a.getAttribute('data-id') || '';
        }
        if (download) {
            var d = a.getAttribute('data-download');
            download.hidden = !d;
            if (d) { download.setAttribute('href', d); }
        }
        if (library) {
            var l = a.getAttribute('data-library');
            library.hidden = !l;
            if (l) { library.setAttribute('href', l); }
        }
        showCompare();
        if (push && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#photo-' + a.getAttribute('data-uid'));
        }
    }

    function open(i, from) {
        opener = from || document.activeElement;
        viewer.hidden = false;
        document.body.classList.add('idv-open');
        show(i, true);
        var close = $('[data-idv="close"]');
        if (close) { close.focus(); }
    }

    function close() {
        viewer.hidden = true;
        document.body.classList.remove('idv-open');
        if (window.history && window.history.replaceState && /^#photo-/.test(window.location.hash)) {
            window.history.replaceState(null, '', '#photos');
        }
        if (opener && opener.focus) { opener.focus(); }
    }

    img.addEventListener('error', function () { img.hidden = true; broken.hidden = false; });
    img.addEventListener('load', function () { img.hidden = false; broken.hidden = true; });

    items.forEach(function (a, i) {
        a.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) { return; }
            e.preventDefault();
            open(i, a);
        });
    });

    viewer.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-idv]') : null;
        if (!btn) { return; }
        var act = btn.getAttribute('data-idv');
        if (act === 'close') { close(); }
        else if (act === 'prev') { show(index - 1, true); }
        else if (act === 'next') { show(index + 1, true); }
        else if (act === 'zoom-in') { setZoom(zoom + 0.5); }
        else if (act === 'zoom-out') { setZoom(zoom - 0.5); }
        else if (act === 'zoom-reset') { setZoom(1); }
        else if (act === 'compare') { comparing = !comparing; showCompare(); }
    });

    // Zoom : clic sur l'image (1 puis 2), molette, glisser pour déplacer.
    stage.addEventListener('wheel', function (e) {
        e.preventDefault();
        setZoom(zoom + (e.deltaY < 0 ? 0.25 : -0.25));
    }, { passive: false });
    stage.addEventListener('pointerdown', function (e) {
        drag = { x: e.clientX, y: e.clientY, px: panX, py: panY, moved: false };
        stage.setPointerCapture(e.pointerId);
    });
    stage.addEventListener('pointermove', function (e) {
        if (!drag) { return; }
        var dx = e.clientX - drag.x;
        var dy = e.clientY - drag.y;
        if (Math.abs(dx) + Math.abs(dy) > 4) { drag.moved = true; }
        if (zoom > 1 && drag.moved) {
            panX = drag.px + dx;
            panY = drag.py + dy;
            applyZoom();
        }
    });
    stage.addEventListener('pointerup', function () {
        if (drag && !drag.moved) { setZoom(zoom > 1 ? 1 : 2); }
        drag = null;
    });

    document.addEventListener('keydown', function (e) {
        if (viewer.hidden) { return; }
        var k = e.key;
        if (k === 'Escape') { e.preventDefault(); close(); }
        else if (k === 'ArrowLeft') { e.preventDefault(); show(index - 1, true); }
        else if (k === 'ArrowRight') { e.preventDefault(); show(index + 1, true); }
        else if (k === '+' || k === '=') { setZoom(zoom + 0.5); }
        else if (k === '-') { setZoom(zoom - 0.5); }
        else if (k === '0') { setZoom(1); }
        else if (k === 'Tab') {
            var f = Array.prototype.filter.call(viewer.querySelectorAll('button, a[href], input, [tabindex]'), function (el) {
                return !el.hidden && !el.closest('[hidden]') && el.getAttribute('tabindex') !== '-1';
            });
            if (!f.length) { return; }
            var first = f[0];
            var last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    // Adresse directe d'une photo : #photo-p12 ouvre la visionneuse dessus.
    function fromHash() {
        var m = /^#photo-(.+)$/.exec(window.location.hash || '');
        if (!m) { return; }
        for (var i = 0; i < items.length; i++) {
            if (items[i].getAttribute('data-uid') === m[1]) { open(i, items[i]); return; }
        }
    }
    window.addEventListener('hashchange', fromHash);
    window.addEventListener('load', fromHash);
})();
