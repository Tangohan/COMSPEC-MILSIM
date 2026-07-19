/**
 * Carousel horizontal + lightbox de la galerie médias (landing communauté).
 */
(function () {
  function initGallery(section) {
    if (!section || section.getAttribute('data-media-layout') !== 'carousel') {
      return;
    }

    var track = section.querySelector('[data-media-track]');
    var prev = section.querySelector('[data-media-prev]');
    var next = section.querySelector('[data-media-next]');
    if (!track || !prev || !next) {
      return;
    }

    function step() {
      var item = track.querySelector('.community-landing__gallery-item');
      if (!item) {
        return Math.max(240, Math.floor(track.clientWidth * 0.8));
      }
      var styles = window.getComputedStyle(track);
      var gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
      return Math.round(item.getBoundingClientRect().width + gap);
    }

    function updateControls() {
      var max = track.scrollWidth - track.clientWidth - 2;
      prev.disabled = track.scrollLeft <= 2;
      next.disabled = track.scrollLeft >= max;
    }

    function scrollByDir(dir) {
      track.scrollBy({ left: dir * step(), behavior: 'smooth' });
    }

    prev.addEventListener('click', function () {
      scrollByDir(-1);
    });
    next.addEventListener('click', function () {
      scrollByDir(1);
    });
    track.addEventListener('scroll', updateControls, { passive: true });
    window.addEventListener('resize', updateControls);
    updateControls();
  }

  function initLightbox() {
    var triggers = Array.prototype.slice.call(
      document.querySelectorAll('[data-lightbox-trigger]')
    );
    if (!triggers.length) {
      return;
    }

    var root = document.createElement('div');
    root.className = 'community-landing__lightbox';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-hidden', 'true');
    root.setAttribute('hidden', '');
    root.innerHTML =
      '<div class="community-landing__lightbox-backdrop" data-lightbox-close tabindex="-1"></div>' +
      '<div class="community-landing__lightbox-panel">' +
      '  <button type="button" class="community-landing__lightbox-close" data-lightbox-close aria-label="Fermer">' +
      '    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
      '  </button>' +
      '  <button type="button" class="community-landing__lightbox-nav community-landing__lightbox-nav--prev" data-lightbox-prev aria-label="Média précédent">' +
      '    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
      '  </button>' +
      '  <button type="button" class="community-landing__lightbox-nav community-landing__lightbox-nav--next" data-lightbox-next aria-label="Média suivant">' +
      '    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
      '  </button>' +
      '  <div class="community-landing__lightbox-stage" data-lightbox-stage></div>' +
      '  <div class="community-landing__lightbox-meta">' +
      '    <p class="community-landing__lightbox-title" data-lightbox-title hidden></p>' +
      '    <p class="community-landing__lightbox-caption" data-lightbox-caption hidden></p>' +
      '    <p class="community-landing__lightbox-counter" data-lightbox-counter hidden></p>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(root);

    var stage = root.querySelector('[data-lightbox-stage]');
    var titleEl = root.querySelector('[data-lightbox-title]');
    var captionEl = root.querySelector('[data-lightbox-caption]');
    var counterEl = root.querySelector('[data-lightbox-counter]');
    var prevBtn = root.querySelector('[data-lightbox-prev]');
    var nextBtn = root.querySelector('[data-lightbox-next]');
    var closeBtn = root.querySelector('.community-landing__lightbox-close');
    var index = 0;
    var lastFocus = null;
    var open = false;

    function focusables() {
      return Array.prototype.slice.call(
        root.querySelectorAll(
          'button:not([disabled]), [href], input, select, textarea, video[controls], iframe, [tabindex]:not([tabindex="-1"])'
        )
      ).filter(function (el) {
        return el.offsetParent !== null || el === closeBtn;
      });
    }

    function setMeta(item) {
      var title = (item.getAttribute('data-lightbox-title') || '').trim();
      var caption = (item.getAttribute('data-lightbox-caption') || '').trim();
      titleEl.textContent = title;
      titleEl.hidden = !title;
      captionEl.textContent = caption;
      captionEl.hidden = !caption;
      if (triggers.length > 1) {
        counterEl.textContent = (index + 1) + ' / ' + triggers.length;
        counterEl.hidden = false;
      } else {
        counterEl.hidden = true;
      }
      var many = triggers.length > 1;
      prevBtn.hidden = !many;
      nextBtn.hidden = !many;
      prevBtn.disabled = !many;
      nextBtn.disabled = !many;
    }

    function render() {
      var item = triggers[index];
      if (!item || !stage) {
        return;
      }
      var kind = item.getAttribute('data-lightbox-kind') || 'image';
      var src = item.getAttribute('data-lightbox-src') || '';
      var embed = item.getAttribute('data-lightbox-embed') || '';
      var alt = item.getAttribute('data-lightbox-alt') || '';
      var title = (item.getAttribute('data-lightbox-title') || '').trim();

      stage.innerHTML = '';
      setMeta(item);

      if (kind === 'image' && src) {
        var host = document.createElement('div');
        host.className = 'community-landing__lightbox-blur-host';
        var img = document.createElement('img');
        img.src = src;
        img.alt = alt || title || 'Image de la communauté';
        host.appendChild(img);
        item.querySelectorAll('.community-landing__blur-patch').forEach(function (patch) {
          host.appendChild(patch.cloneNode(true));
        });
        stage.appendChild(host);
      } else if (kind === 'short_video' && src) {
        var video = document.createElement('video');
        video.src = src;
        video.controls = true;
        video.playsInline = true;
        video.autoplay = true;
        video.setAttribute('controlsList', 'nodownload');
        stage.appendChild(video);
      } else if (kind === 'long_video' && embed) {
        var wrap = document.createElement('div');
        wrap.className = 'community-landing__lightbox-embed';
        var iframe = document.createElement('iframe');
        iframe.src = embed;
        iframe.title = title || alt || 'Vidéo';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        wrap.appendChild(iframe);
        stage.appendChild(wrap);
      }

      root.setAttribute('aria-label', title || alt || 'Médias en grand');
    }

    function showAt(i) {
      if (!triggers.length) {
        return;
      }
      index = ((i % triggers.length) + triggers.length) % triggers.length;
      render();
    }

    function openAt(i, fromEl) {
      lastFocus = fromEl || document.activeElement;
      showAt(i);
      root.hidden = false;
      root.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('community-landing--lightbox-open');
      open = true;
      window.requestAnimationFrame(function () {
        root.classList.add('is-open');
        if (closeBtn) {
          closeBtn.focus();
        }
      });
    }

    function closeLightbox() {
      if (!open) {
        return;
      }
      open = false;
      root.classList.remove('is-open');
      var playing = stage.querySelector('video');
      if (playing) {
        try {
          playing.pause();
        } catch (e) { /* ignore */ }
      }
      stage.innerHTML = '';
      root.setAttribute('aria-hidden', 'true');
      root.hidden = true;
      document.documentElement.classList.remove('community-landing--lightbox-open');
      if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
      }
      lastFocus = null;
    }

    triggers.forEach(function (el, i) {
      el.addEventListener('click', function (ev) {
        ev.preventDefault();
        openAt(i, el);
      });
      el.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          openAt(i, el);
        }
      });
    });

    root.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-lightbox-close]')) {
        closeLightbox();
      }
    });

    prevBtn.addEventListener('click', function () {
      showAt(index - 1);
    });
    nextBtn.addEventListener('click', function () {
      showAt(index + 1);
    });

    document.addEventListener('keydown', function (ev) {
      if (!open) {
        return;
      }
      if (ev.key === 'Escape') {
        ev.preventDefault();
        closeLightbox();
        return;
      }
      if (ev.key === 'ArrowLeft' && triggers.length > 1) {
        ev.preventDefault();
        showAt(index - 1);
        return;
      }
      if (ev.key === 'ArrowRight' && triggers.length > 1) {
        ev.preventDefault();
        showAt(index + 1);
        return;
      }
      if (ev.key === 'Tab') {
        var list = focusables();
        if (!list.length) {
          ev.preventDefault();
          return;
        }
        var first = list[0];
        var last = list[list.length - 1];
        if (ev.shiftKey && document.activeElement === first) {
          ev.preventDefault();
          last.focus();
        } else if (!ev.shiftKey && document.activeElement === last) {
          ev.preventDefault();
          first.focus();
        }
      }
    });
  }

  document.querySelectorAll('.community-landing__media').forEach(initGallery);
  initLightbox();
})();
