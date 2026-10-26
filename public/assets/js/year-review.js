/*
 * Rétrospective annuelle : stories plein écran (tap / flèches / glisser, pause en maintenant appuyé ou avec Espace)
 * et cartes image 1080 × 1350 à partager sur Discord, dessinées à partir du texte affiché (donc déjà traduit).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-yr]');
    if (!root) {
        return;
    }
    var stage = root.querySelector('[data-yr-stage]');
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-yr-slide]'));
    var bars = Array.prototype.slice.call(root.querySelectorAll('.yr-progress span'));
    var btnPause = root.querySelector('[data-yr-pause]');
    var btnPrev = root.querySelector('[data-yr-prev]');
    var btnNext = root.querySelector('[data-yr-next]');
    var btnShare = root.querySelector('[data-yr-share]');
    var btnCopy = root.querySelector('[data-yr-copy]');
    var btnDownload = root.querySelector('[data-yr-download]');
    var toast = root.querySelector('[data-yr-toast]');
    var closeLink = root.querySelector('[data-yr-close]');
    var year = root.getAttribute('data-year') || '';
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var DURATION = 7000;

    var i18n = {};
    Array.prototype.forEach.call(root.querySelectorAll('[data-yr-i18n] [data-k]'), function (el) {
        i18n[el.getAttribute('data-k')] = el.textContent.trim();
    });

    var index = 0;
    var elapsed = 0;
    var last = 0;
    var paused = false;
    var held = false;
    var raf = 0;

    function show(i) {
        index = Math.max(0, Math.min(slides.length - 1, i));
        slides.forEach(function (s, k) {
            s.hidden = k !== index;
            s.classList.toggle('is-in', k === index);
        });
        bars.forEach(function (b, k) {
            b.classList.toggle('is-done', k < index);
            b.querySelector('i').style.width = k < index ? '100%' : '0';
        });
        root.classList.toggle('is-last', index === slides.length - 1);
        if (btnPrev) { btnPrev.disabled = index === 0; }
        if (btnNext) { btnNext.disabled = index === slides.length - 1; }
        elapsed = 0;
        last = performance.now();
        var canShare = slides[index].hasAttribute('data-share');
        [btnShare, btnCopy, btnDownload].forEach(function (b) {
            if (b) { b.hidden = !canShare || b.getAttribute('data-unsupported') === '1'; }
        });
        say('');
    }

    function tick(now) {
        raf = requestAnimationFrame(tick);
        if (paused || held || document.hidden) {
            last = now;
            return;
        }
        elapsed += now - last;
        last = now;
        var bar = bars[index] && bars[index].querySelector('i');
        if (index === slides.length - 1) {
            if (bar) { bar.style.width = '100%'; }
            return;
        }
        if (bar) { bar.style.width = Math.min(100, elapsed * 100 / DURATION) + '%'; }
        if (elapsed >= DURATION) {
            show(index + 1);
        }
    }

    function setPaused(p) {
        paused = p;
        if (btnPause) {
            btnPause.setAttribute('aria-label', p ? (i18n.play || 'Play') : (i18n.pause || 'Pause'));
            btnPause.innerHTML = p
                ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5l12 7-12 7z"/></svg>'
                : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg>';
        }
    }

    // Tap : tiers gauche recule, le reste avance. Appui long : pause tant que le doigt reste posé.
    var downAt = 0;
    var downX = 0;
    var downY = 0;
    stage.addEventListener('pointerdown', function (e) {
        if (e.target.closest('a,button,nav,.yr-top,.yr-dock')) {
            return;
        }
        downAt = Date.now();
        downX = e.clientX;
        downY = e.clientY;
        held = true;
    });
    stage.addEventListener('pointerup', function (e) {
        if (!downAt) {
            return;
        }
        held = false;
        var dt = Date.now() - downAt;
        var dx = e.clientX - downX;
        var dy = e.clientY - downY;
        downAt = 0;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
            show(index + (dx < 0 ? 1 : -1));
            return;
        }
        if (dt > 350 || Math.abs(dy) > 30) {
            return;
        }
        var rect = stage.getBoundingClientRect();
        show(index + (e.clientX - rect.left < rect.width * 0.35 ? -1 : 1));
    });
    stage.addEventListener('pointercancel', function () { held = false; downAt = 0; });
    stage.addEventListener('pointerleave', function () { held = false; });

    if (btnPrev) { btnPrev.addEventListener('click', function () { show(index - 1); }); }
    if (btnNext) { btnNext.addEventListener('click', function () { show(index + 1); }); }
    if (btnPause) { btnPause.addEventListener('click', function () { setPaused(!paused); }); }
    Array.prototype.forEach.call(root.querySelectorAll('[data-yr-restart]'), function (b) {
        b.addEventListener('click', function () { setPaused(false); show(0); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { show(index + 1); }
        else if (e.key === 'ArrowLeft') { show(index - 1); }
        else if (e.key === ' ' && !e.target.closest('button,a')) { e.preventDefault(); setPaused(!paused); }
        else if (e.key === 'Escape' && closeLink) { window.location.href = closeLink.href; }
    });

    function say(msg) {
        if (toast) { toast.textContent = msg; }
    }

    // ------------------------------------------------------------------ carte image

    var W = 1080;
    var H = 1350;
    var logo = null;
    (function () {
        var src = root.getAttribute('data-logo');
        if (!src) { return; }
        var img = new Image();
        img.onload = function () { logo = img; };
        img.src = src;
    })();

    function text(el, sel) {
        var n = el.querySelector(sel);
        return n ? n.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function wrap(ctx, str, x, y, maxW, lineH, maxLines) {
        var words = str.split(' ');
        var line = '';
        var lines = 0;
        for (var k = 0; k < words.length; k++) {
            var test = line ? line + ' ' + words[k] : words[k];
            if (ctx.measureText(test).width > maxW && line) {
                ctx.fillText(line, x, y);
                y += lineH;
                lines++;
                line = words[k];
                if (maxLines && lines >= maxLines) { return y; }
            } else {
                line = test;
            }
        }
        if (line) { ctx.fillText(line, x, y); y += lineH; }
        return y;
    }

    // Police la plus grande (au plus « size ») où le texte tient dans maxW ; renvoie la taille retenue.
    function fit(ctx, str, maxW, size, weight, family) {
        ctx.font = weight + ' ' + size + 'px ' + family;
        while (ctx.measureText(str).width > maxW && size > 40) {
            size -= 4;
            ctx.font = weight + ' ' + size + 'px ' + family;
        }
        return size;
    }

    function draw(slide) {
        var cs = getComputedStyle(slide);
        var A = (cs.getPropertyValue('--yr-a') || '#5cf600').trim();
        var B = (cs.getPropertyValue('--yr-b') || '#0b0f0d').trim();
        var C = (cs.getPropertyValue('--yr-c') || '#18301f').trim();
        var COND = '"Barlow Condensed", "Arial Narrow", sans-serif';
        var BODY = 'Barlow, Arial, sans-serif';
        var canvas = document.createElement('canvas');
        canvas.width = W;
        canvas.height = H;
        var ctx = canvas.getContext('2d');

        ctx.fillStyle = B;
        ctx.fillRect(0, 0, W, H);
        var g = ctx.createRadialGradient(W, 0, 0, W, 0, W * 1.1);
        g.addColorStop(0, C);
        g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        var g2 = ctx.createRadialGradient(0, H, 0, 0, H, W);
        g2.addColorStop(0, hexA(A, 0.22));
        g2.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g2;
        ctx.fillRect(0, 0, W, H);
        ctx.save();
        ctx.strokeStyle = 'rgba(255,255,255,0.03)';
        ctx.lineWidth = 3;
        for (var s = -H; s < W + H; s += 26) {
            ctx.beginPath();
            ctx.moveTo(s, 0);
            ctx.lineTo(s + H * 0.58, H);
            ctx.stroke();
        }
        ctx.restore();
        ctx.beginPath();
        ctx.lineWidth = 80;
        ctx.strokeStyle = hexA(A, 0.13);
        ctx.arc(W - 40, 330, 250, 0, Math.PI * 2);
        ctx.stroke();

        var x = 80;
        var maxW = W - 160;
        // En-tête : emblème, marque, année.
        if (logo) { ctx.drawImage(logo, x, 70, 54, 54); }
        ctx.fillStyle = '#f2f5f3';
        ctx.font = '800 34px ' + COND;
        ctx.textBaseline = 'middle';
        ctx.fillText('ATHENA', x + (logo ? 70 : 0), 98);
        var aw = ctx.measureText('ATHENA').width;
        ctx.fillStyle = A;
        ctx.fillText('· COMSPEC', x + (logo ? 70 : 0) + aw + 12, 98);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#f2f5f3';
        ctx.font = '900 40px ' + COND;
        ctx.fillText(year, W - x, 98);
        ctx.textAlign = 'left';
        ctx.textBaseline = 'alphabetic';

        var y = 250;
        var kicker = text(slide, '[data-share-kicker]');
        var name = text(slide, '[data-share-name]');
        var big = text(slide, '[data-share-big]');
        var bigLabel = text(slide, '[data-share-big-label]');
        var title = text(slide, '[data-share-title]');
        var persona = text(slide, '[data-share-persona]');
        if (kicker) {
            ctx.fillStyle = A;
            ctx.font = '800 38px ' + COND;
            ctx.fillText(kicker.toUpperCase(), x, y);
            y += 62;
        }
        if (name) {
            ctx.fillStyle = '#f2f5f3';
            fit(ctx, name.toUpperCase(), maxW, 60, '800', COND);
            ctx.fillText(name.toUpperCase(), x, y);
            y += 40;
        }
        if (big) {
            var words = big.length > 10;
            var bigSize = fit(ctx, words ? big.toUpperCase() : big, maxW, words ? 120 : 230, '900', COND);
            ctx.fillStyle = '#f2f5f3';
            y += bigSize * 0.92;
            ctx.fillText(words ? big.toUpperCase() : big, x, y);
            y += 20;
            if (bigLabel) {
                ctx.fillStyle = A;
                ctx.font = '600 44px ' + BODY;
                y += 40;
                ctx.fillText(bigLabel, x, y);
            }
            y += 30;
        }
        if (title) {
            ctx.fillStyle = '#f2f5f3';
            ctx.font = '800 58px ' + COND;
            y = wrap(ctx, title.toUpperCase(), x, y + 56, maxW, 62, 3);
        }
        if (persona) {
            ctx.fillStyle = A;
            ctx.font = '700 36px ' + BODY;
            y = wrap(ctx, persona.toUpperCase(), x, y + 14, maxW, 44, 2);
        }

        // Barres mensuelles.
        var barsEl = slide.querySelector('[data-share-bars]');
        if (barsEl) {
            var cols = barsEl.querySelectorAll('span');
            var top = y + 30;
            var hBars = 150;
            var gap = 10;
            var bw = (maxW - gap * 11) / 12;
            Array.prototype.forEach.call(cols, function (c, k) {
                var pct = parseInt(c.getAttribute('data-pct') || '0', 10);
                var bh = Math.max(4, hBars * pct / 100);
                ctx.fillStyle = hexA(A, pct > 0 ? 0.95 : 0.25);
                roundRect(ctx, x + k * (bw + gap), top + hBars - bh, bw, bh, 8);
                ctx.fill();
                ctx.fillStyle = '#8fa096';
                ctx.font = '700 24px ' + BODY;
                ctx.textAlign = 'center';
                ctx.fillText(c.textContent.trim(), x + k * (bw + gap) + bw / 2, top + hBars + 34);
                ctx.textAlign = 'left';
            });
            y = top + hBars + 50;
        }

        // Chiffres (lignes libellé / valeur) ou tableaux d'honneur.
        var stats = Array.prototype.slice.call(slide.querySelectorAll('[data-share-stat]'));
        var boards = Array.prototype.slice.call(slide.querySelectorAll('[data-share-board]'));
        var bottom = H - 130;
        if (boards.length) {
            var colW = (maxW - 30) / 2;
            boards.slice(0, 4).forEach(function (b, k) {
                var bx = x + (k % 2) * (colW + 30);
                var by = y + 40 + Math.floor(k / 2) * 250;
                ctx.fillStyle = 'rgba(242,245,243,0.07)';
                roundRect(ctx, bx, by, colW, 225, 22);
                ctx.fill();
                ctx.fillStyle = A;
                ctx.font = '700 26px ' + BODY;
                wrap(ctx, text(b, 'h3').toUpperCase(), bx + 24, by + 46, colW - 48, 30, 1);
                Array.prototype.slice.call(b.querySelectorAll('li'), 0, 3).forEach(function (li, r) {
                    var ly = by + 100 + r * 44;
                    ctx.fillStyle = r === 0 ? '#ffd25a' : '#8fa096';
                    ctx.font = '800 30px ' + COND;
                    ctx.fillText(String(r + 1), bx + 24, ly);
                    ctx.fillStyle = '#f2f5f3';
                    ctx.font = '700 28px ' + BODY;
                    var nm = text(li, 'span');
                    while (ctx.measureText(nm).width > colW - 200 && nm.length > 3) { nm = nm.slice(0, -2); }
                    ctx.fillText(nm, bx + 60, ly);
                    ctx.textAlign = 'right';
                    ctx.font = '800 32px ' + COND;
                    ctx.fillText(text(li, 'em'), bx + colW - 24, ly);
                    ctx.textAlign = 'left';
                });
            });
        } else if (stats.length) {
            var grid = slide.querySelector('.yr-stats--grid') !== null;
            var rows = stats.slice(0, grid ? 6 : 5);
            if (grid) {
                var gw = (maxW - 30) / 2;
                rows.forEach(function (st, k) {
                    var gx = x + (k % 2) * (gw + 30);
                    var gy = y + 40 + Math.floor(k / 2) * 170;
                    ctx.fillStyle = 'rgba(242,245,243,0.07)';
                    roundRect(ctx, gx, gy, gw, 150, 22);
                    ctx.fill();
                    ctx.fillStyle = A;
                    fit(ctx, text(st, 'dd'), gw - 48, 72, '900', COND);
                    ctx.fillText(text(st, 'dd'), gx + 24, gy + 82);
                    ctx.fillStyle = '#cfd8d2';
                    ctx.font = '600 28px ' + BODY;
                    wrap(ctx, text(st, 'dt'), gx + 24, gy + 124, gw - 48, 30, 1);
                });
            } else {
                // Lignes de 70 px au moins : on en montre moins plutôt que de les écraser.
                rows = rows.slice(0, Math.max(1, Math.floor((bottom - y - 30) / 70)));
                var rowH = Math.min(96, (bottom - y - 30) / rows.length);
                rows.forEach(function (st, k) {
                    var ry = y + 30 + k * rowH;
                    if (ry + rowH - 12 > bottom) { return; }
                    ctx.fillStyle = 'rgba(242,245,243,0.07)';
                    roundRect(ctx, x, ry, maxW, rowH - 14, 20);
                    ctx.fill();
                    var label = text(st, 'dt') || text(st, '[data-l]');
                    var value = text(st, 'dd') || text(st, '[data-v]');
                    var mid = ry + (rowH - 14) / 2 + 12;
                    ctx.fillStyle = '#cfd8d2';
                    ctx.font = '600 32px ' + BODY;
                    ctx.fillText(label, x + 28, mid);
                    ctx.textAlign = 'right';
                    ctx.fillStyle = '#f2f5f3';
                    ctx.font = '800 44px ' + COND;
                    ctx.fillText(value, W - x - 28, mid + 2);
                    ctx.textAlign = 'left';
                });
            }
        }

        // Pied : adresse du site.
        ctx.fillStyle = 'rgba(242,245,243,0.55)';
        ctx.font = '700 28px ' + BODY;
        ctx.fillText(window.location.host, x, H - 70);
        ctx.textAlign = 'right';
        ctx.fillStyle = A;
        ctx.font = '800 30px ' + COND;
        ctx.fillText('#COMSPEC' + year, W - x, H - 70);
        ctx.textAlign = 'left';

        return canvas;
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function hexA(hex, a) {
        var m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
        if (!m) { return 'rgba(92,246,0,' + a + ')'; }
        var n = parseInt(m[1], 16);
        return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
    }

    function blob() {
        var slide = slides[index];
        var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
        return ready.then(function () {
            return new Promise(function (resolve) { draw(slide).toBlob(resolve, 'image/png'); });
        });
    }

    function fileName() {
        var key = slides[index].getAttribute('data-key') || 'carte';
        return (i18n.file || 'retrospective') + '-' + year + '-' + key + '.png';
    }

    function download(b) {
        var url = URL.createObjectURL(b);
        var a = document.createElement('a');
        a.href = url;
        a.download = fileName();
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }

    var canFileShare = !!(navigator.canShare && window.File && navigator.canShare({ files: [new File([''], 'x.png', { type: 'image/png' })] }));
    var canCopy = !!(navigator.clipboard && window.ClipboardItem);
    if (btnShare && !canFileShare) { btnShare.setAttribute('data-unsupported', '1'); }
    if (btnCopy && !canCopy) { btnCopy.setAttribute('data-unsupported', '1'); }

    if (btnShare) {
        btnShare.addEventListener('click', function () {
            setPaused(true);
            blob().then(function (b) {
                var file = new File([b], fileName(), { type: 'image/png' });
                return navigator.share({ files: [file] });
            }).catch(function () {});
        });
    }
    if (btnCopy) {
        btnCopy.addEventListener('click', function () {
            setPaused(true);
            // Safari exige la promesse dans ClipboardItem, appelée pendant le clic.
            var p = blob();
            navigator.clipboard.write([new ClipboardItem({ 'image/png': p })]).then(function () {
                say(i18n.copied || '');
            }).catch(function () {
                p.then(download);
                say(i18n.copy_fail || '');
            });
        });
    }
    if (btnDownload) {
        btnDownload.addEventListener('click', function () {
            setPaused(true);
            blob().then(function (b) {
                download(b);
                say(i18n.downloaded || '');
            });
        });
    }

    show(0);
    // Mouvement réduit : pas de défilement automatique, on avance au tap ou aux flèches.
    setPaused(reduced);
    raf = requestAnimationFrame(tick);
})();
