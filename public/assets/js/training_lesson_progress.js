/**
 * Validation automatique de leçon LMS : critères plus stricts pour éviter une validation
 * sans parcours réel (scroll bref, slides enchaînées trop vite, saut en fin de vidéo, etc.).
 */
(function () {
  'use strict';

  function cfg() {
    return window.__LMS_LESSON_PROGRESS__;
  }

  function strictNum(key, def) {
    var c = cfg();
    var v = c && c.strict && c.strict[key];
    return typeof v === 'number' && !isNaN(v) && v > 0 ? v : def;
  }

  function updateProgressBars(percent) {
    if (typeof percent !== 'number' || Number.isNaN(percent)) {
      return;
    }
    var p = Math.min(100, Math.max(0, percent));
    document.querySelectorAll('[data-lms-header-progress]').forEach(function (el) {
      el.style.width = p + '%';
    });
    document.querySelectorAll('[data-lms-header-pct]').forEach(function (el) {
      el.textContent = String(Math.round(p * 10) / 10);
    });
  }

  function markUiSuccess(percent) {
    var hint = document.getElementById('lms-progress-status');
    if (hint) {
      hint.textContent = 'Leçon validée — vous pouvez poursuivre le parcours.';
      hint.classList.remove('text-slate-600', 'text-rose-600');
      hint.classList.add('text-emerald-700', 'font-semibold');
    }
    var btn = document.getElementById('lms-btn-complete');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Leçon validée';
      btn.classList.add('opacity-60', 'cursor-default');
      btn.classList.remove('hover:bg-emerald-700');
    }
    updateProgressBars(percent);
    if (typeof window.lmsTrainingToastShow === 'function') {
      window.lmsTrainingToastShow('Leçon validée — vous pouvez poursuivre le parcours.', 'success');
    }
  }

  var posted = false;

  function warnMediaSkip() {
    if (typeof window.lmsTrainingToastShow === 'function') {
      window.lmsTrainingToastShow(
        'La leçon ne peut pas être validée : le média doit être lu sur la majeure partie de sa durée (évitez de sauter directement à la fin).',
        'warning'
      );
    }
  }

  /** Part du temps réellement lue (TimeRanges HTML5), entre 0 et 1. */
  function mediaPlayedRatio(el) {
    if (!el || !el.duration || !isFinite(el.duration) || el.duration <= 0) {
      return 0;
    }
    var played = el.played;
    if (!played || typeof played.length !== 'number' || played.length < 1) {
      return 0;
    }
    var t = 0;
    for (var i = 0; i < played.length; i++) {
      t += played.end(i) - played.start(i);
    }
    return t / el.duration;
  }

  window.LmsLessonProgress = {
    signalComplete: function () {
      var c = cfg();
      if (!c || !c.auto || c.alreadyCompleted || posted) {
        return;
      }
      posted = true;
      var token = window.__LMS_CSRF__ || '';
      fetch(c.apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': token,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          enrollment_id: c.enrollmentId,
          lesson_id: c.lessonId,
          status: 'completed',
          _csrf_token: token,
        }),
      })
        .then(function (r) {
          var ct = (r.headers.get('content-type') || '').toLowerCase();
          if (ct.indexOf('application/json') === -1) {
            return r.text().then(function () {
              throw new Error('Réponse inattendue du serveur. Rechargez la page.');
            });
          }
          return r.json().then(function (j) {
            if (!r.ok) {
              throw new Error((j && j.error) || 'Erreur');
            }
            return j;
          });
        })
        .then(function (data) {
          markUiSuccess(typeof data.percent === 'number' ? data.percent : null);
        })
        .catch(function (err) {
          posted = false;
          var msg =
            err && err.message
              ? err.message
              : 'La validation automatique a échoué. Rechargez la page ou réessayez dans un instant.';
          if (typeof window.lmsTrainingToastShow === 'function') {
            window.lmsTrainingToastShow(msg, 'error');
          }
          var hint = document.getElementById('lms-progress-status');
          if (hint) {
            hint.textContent = msg;
            hint.classList.remove('text-slate-600', 'text-emerald-700');
            hint.classList.add('text-rose-600', 'font-semibold');
          }
        });
    },
  };

  document.addEventListener('DOMContentLoaded', function () {
    var c = cfg();
    if (!c || !c.auto || c.alreadyCompleted) {
      return;
    }
    var lt = c.lessonType || '';

    if (lt === 'richtext') {
      var sent = document.getElementById('lms-richtext-sentinel');
      var root = document.querySelector('[data-lms-richtext-root="1"]');
      if (!sent || typeof IntersectionObserver === 'undefined') {
        return;
      }
      var SENT_MS = strictNum('richtextSentinelMs', 2800);
      var SCROLL_R = strictNum('richtextScrollRatio', 0.86);
      var sustainTimer = null;
      var sentinelOk = false;

      function docScrollRatio() {
        var de = document.documentElement;
        var body = document.body;
        var sh = Math.max(de.scrollHeight, body.scrollHeight);
        var vh = window.innerHeight || de.clientHeight;
        var maxScroll = Math.max(1, sh - vh);
        return Math.min(1, (window.scrollY || window.pageYOffset || 0) / maxScroll);
      }

      function scrollEnough() {
        if (docScrollRatio() >= SCROLL_R) {
          return true;
        }
        if (root && root.getBoundingClientRect) {
          var r = root.getBoundingClientRect();
          var vh = window.innerHeight || 0;
          if (vh > 0 && r.bottom <= vh * 0.92) {
            return true;
          }
        }
        return false;
      }

      function tryRichtextComplete() {
        if (!sentinelOk || !scrollEnough()) {
          return;
        }
        window.LmsLessonProgress.signalComplete();
      }

      function clearSustain() {
        if (sustainTimer) {
          clearTimeout(sustainTimer);
          sustainTimer = null;
        }
      }

      var io = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (e) {
            if (e.isIntersecting && e.intersectionRatio >= 0.32) {
              if (!sustainTimer) {
                sustainTimer = setTimeout(function () {
                  sentinelOk = true;
                  tryRichtextComplete();
                }, SENT_MS);
              }
            } else {
              clearSustain();
              sentinelOk = false;
            }
          });
        },
        { root: null, threshold: [0, 0.32, 0.55], rootMargin: '0px 0px -10% 0px' }
      );
      io.observe(sent);
      window.addEventListener('scroll', tryRichtextComplete, { passive: true });
      return;
    }

    if (lt === 'video' || lt === 'video_integrated') {
      var v = document.getElementById('lms-lesson-video');
      if (v) {
        var minR = strictNum('mediaPlayedMinRatio', 0.88);
        v.addEventListener('ended', function () {
          if (mediaPlayedRatio(v) >= minR) {
            window.LmsLessonProgress.signalComplete();
          } else {
            warnMediaSkip();
          }
        });
      }
      return;
    }

    if (lt === 'audio') {
      var a = document.getElementById('lms-lesson-audio');
      if (a) {
        var minRa = strictNum('mediaPlayedMinRatio', 0.88);
        a.addEventListener('ended', function () {
          if (mediaPlayedRatio(a) >= minRa) {
            window.LmsLessonProgress.signalComplete();
          } else {
            warnMediaSkip();
          }
        });
      }
    }
  });
})();
