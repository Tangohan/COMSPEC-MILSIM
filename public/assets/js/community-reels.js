/**
 * Fil vertical Reels — lecture auto, son, « J’aime ».
 */
(function () {
  function parseCount(value) {
    var n = parseInt(value, 10);
    return isNaN(n) || n < 0 ? 0 : n;
  }

  function formatCount(n) {
    return n > 0 ? String(n) : '';
  }

  function applyLikeState(item, liked, count) {
    if (!item) {
      return;
    }
    item.setAttribute('data-liked', liked ? '1' : '0');
    item.setAttribute('data-like-count', String(count));
    item.querySelectorAll('[data-media-like]').forEach(function (btn) {
      btn.classList.toggle('is-liked', !!liked);
      btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
      btn.setAttribute('aria-label', liked ? 'Retirer mon j’aime' : 'J’aime ce média');
      var label = btn.querySelector('[data-like-count-label]');
      if (label) {
        label.textContent = formatCount(count);
      }
    });
  }

  function showLikeNotice(root, message, loginUrl) {
    if (!root) {
      return;
    }
    var existing = root.querySelector('[data-media-like-notice]');
    if (existing) {
      existing.remove();
    }
    var notice = document.createElement('p');
    notice.className = 'community-reels__like-notice';
    notice.setAttribute('data-media-like-notice', '');
    notice.setAttribute('role', 'status');
    if (loginUrl) {
      notice.innerHTML =
        '<span>' +
        String(message || 'Connectez-vous pour aimer ce média.') +
        '</span> <a href="' +
        String(loginUrl).replace(/"/g, '&quot;') +
        '">Se connecter</a>';
    } else {
      notice.textContent = message || 'Action impossible pour le moment.';
    }
    root.appendChild(notice);
    window.setTimeout(function () {
      if (notice.parentNode) {
        notice.remove();
      }
    }, 8000);
  }

  function toggleLike(item, root) {
    if (!item || !root) {
      return;
    }
    var url = item.getAttribute('data-like-url') || '';
    if (!url) {
      return;
    }
    var csrf = root.getAttribute('data-media-likes-csrf') || '';
    var canAuth = root.getAttribute('data-media-likes-auth') === '1';
    var loginUrl = root.getAttribute('data-media-likes-login') || '';
    if (!canAuth) {
      showLikeNotice(root, 'Connectez-vous pour aimer ce média.', loginUrl);
      return;
    }

    var liked = item.getAttribute('data-liked') === '1';
    var count = parseCount(item.getAttribute('data-like-count'));
    var nextLiked = !liked;
    var nextCount = Math.max(0, count + (nextLiked ? 1 : -1));
    applyLikeState(item, nextLiked, nextCount);

    var body = new URLSearchParams();
    body.set('csrf_token', csrf);
    body.set('like', nextLiked ? '1' : '0');

    fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: body.toString(),
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, status: res.status, data: data || {} };
        });
      })
      .then(function (result) {
        var data = result.data;
        if (result.status === 401 || data.needs_login) {
          applyLikeState(item, liked, count);
          showLikeNotice(root, data.message || 'Connectez-vous pour aimer ce média.', data.login_url || loginUrl);
          return;
        }
        if (!result.ok || !data.success) {
          applyLikeState(item, liked, count);
          showLikeNotice(root, data.message || 'Impossible d’enregistrer votre réaction.');
          return;
        }
        applyLikeState(
          item,
          !!data.liked,
          typeof data.likes_count === 'number' ? data.likes_count : nextCount
        );
      })
      .catch(function () {
        applyLikeState(item, liked, count);
        showLikeNotice(root, 'Connexion interrompue. Réessayez dans un instant.');
      });
  }

  function pauseAll(root, exceptSlide) {
    root.querySelectorAll('[data-reels-video]').forEach(function (video) {
      var slide = video.closest('[data-reels-slide]');
      if (exceptSlide && slide === exceptSlide) {
        return;
      }
      try {
        video.pause();
      } catch (e) {}
    });
  }

  function playSlide(slide, root) {
    if (!slide) {
      return;
    }
    pauseAll(root, slide);
    var video = slide.querySelector('[data-reels-video]');
    if (!video) {
      return;
    }
    video.muted = true;
    var muteBtn = slide.querySelector('[data-reels-mute]');
    if (muteBtn) {
      muteBtn.setAttribute('aria-pressed', 'true');
      muteBtn.setAttribute('aria-label', 'Activer le son');
    }
    var playPromise = video.play();
    if (playPromise && typeof playPromise.catch === 'function') {
      playPromise.catch(function () {});
    }
  }

  function activateNearest(scroller, root) {
    var slides = Array.prototype.slice.call(scroller.querySelectorAll('[data-reels-slide]'));
    if (!slides.length) {
      return;
    }
    var mid = scroller.scrollTop + scroller.clientHeight / 2;
    var best = slides[0];
    var bestDist = Infinity;
    slides.forEach(function (slide) {
      var center = slide.offsetTop + slide.offsetHeight / 2;
      var dist = Math.abs(center - mid);
      if (dist < bestDist) {
        bestDist = dist;
        best = slide;
      }
    });
    if (best !== root._activeSlide) {
      root._activeSlide = best;
      playSlide(best, root);
    }
  }

  function mountEmbed(wrap) {
    if (!wrap || wrap.classList.contains('is-playing')) {
      return;
    }
    var src = wrap.getAttribute('data-embed-src') || '';
    if (!src) {
      return;
    }
    var iframe = document.createElement('iframe');
    iframe.src = src + (src.indexOf('?') >= 0 ? '&' : '?') + 'autoplay=1';
    iframe.title = 'Vidéo';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    wrap.appendChild(iframe);
    wrap.classList.add('is-playing');
  }

  function init(root) {
    var scroller = root.querySelector('[data-reels-scroller]');
    var hint = root.querySelector('[data-reels-hint]');
    if (!scroller) {
      return;
    }

    root._activeSlide = null;
    activateNearest(scroller, root);

    var scrollTimer = null;
    scroller.addEventListener(
      'scroll',
      function () {
        if (hint && !hint.classList.contains('is-hidden')) {
          hint.classList.add('is-hidden');
        }
        if (scrollTimer) {
          window.clearTimeout(scrollTimer);
        }
        scrollTimer = window.setTimeout(function () {
          activateNearest(scroller, root);
        }, 80);
      },
      { passive: true }
    );

    root.addEventListener('click', function (event) {
      var muteBtn = event.target.closest('[data-reels-mute]');
      if (muteBtn) {
        event.preventDefault();
        var slide = muteBtn.closest('[data-reels-slide]');
        var video = slide ? slide.querySelector('[data-reels-video]') : null;
        if (!video) {
          return;
        }
        var wantUnmute = muteBtn.getAttribute('aria-pressed') === 'true';
        video.muted = !wantUnmute;
        muteBtn.setAttribute('aria-pressed', video.muted ? 'true' : 'false');
        muteBtn.setAttribute('aria-label', video.muted ? 'Activer le son' : 'Couper le son');
        if (!video.paused) {
          return;
        }
        var p = video.play();
        if (p && typeof p.catch === 'function') {
          p.catch(function () {});
        }
        return;
      }

      var likeBtn = event.target.closest('[data-media-like]');
      if (likeBtn) {
        event.preventDefault();
        event.stopPropagation();
        var likeSlide = likeBtn.closest('[data-reels-slide]');
        toggleLike(likeSlide, root);
        return;
      }

      var embedWrap = event.target.closest('[data-reels-embed-wrap]');
      if (embedWrap) {
        mountEmbed(embedWrap);
      }
    });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        pauseAll(root, null);
      } else {
        activateNearest(scroller, root);
      }
    });
  }

  function boot() {
    document.querySelectorAll('[data-reels-root]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
