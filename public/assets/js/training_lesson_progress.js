/**
 * Validation automatique de leçon LMS (toutes les étapes parcourues, quiz réussi, média terminé, etc.).
 */
(function () {
  'use strict';

  function cfg() {
    return window.__LMS_LESSON_PROGRESS__;
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
      if (sent && typeof IntersectionObserver !== 'undefined') {
        var io = new IntersectionObserver(
          function (entries) {
            entries.forEach(function (e) {
              if (e.isIntersecting) {
                io.disconnect();
                window.LmsLessonProgress.signalComplete();
              }
            });
          },
          { root: null, threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
        );
        io.observe(sent);
      }
      return;
    }

    if (lt === 'video' || lt === 'video_integrated') {
      var v = document.getElementById('lms-lesson-video');
      if (v) {
        v.addEventListener('ended', function () {
          window.LmsLessonProgress.signalComplete();
        });
      }
      return;
    }

    if (lt === 'audio') {
      var a = document.getElementById('lms-lesson-audio');
      if (a) {
        a.addEventListener('ended', function () {
          window.LmsLessonProgress.signalComplete();
        });
      }
    }
  });
})();
