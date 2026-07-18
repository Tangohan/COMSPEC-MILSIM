/**
 * Progression de leçon LMS : le parcours débloque le bouton « Terminer »,
 * la validation côté serveur n’est enregistrée qu’après confirmation explicite
 * (sauf actions déjà volontaires : Terminer du parcours visuel, envoi d’évaluation).
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

  function feedbackSkipKey() {
    var c = cfg();
    var modal = document.getElementById('lms-feedback-modal');
    var enr = (c && c.enrollmentId) || (modal && modal.getAttribute('data-lms-feedback-enrollment')) || '0';
    var les = (c && c.lessonId) || (modal && modal.getAttribute('data-lms-feedback-lesson')) || '0';
    return 'lms_feedback_skip_' + enr + '_' + les;
  }

  function isFeedbackSkipped() {
    try {
      return localStorage.getItem(feedbackSkipKey()) === '1';
    } catch (e) {
      return false;
    }
  }

  function persistFeedbackSkip() {
    try {
      localStorage.setItem(feedbackSkipKey(), '1');
    } catch (e) {}
  }

  function openFeedbackModal() {
    var modal = document.getElementById('lms-feedback-modal');
    if (!modal) {
      return;
    }
    if (modal.getAttribute('data-lms-feedback-done') === '1' || isFeedbackSkipped()) {
      return;
    }
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('lms-modal-open');
  }

  function closeFeedbackModal() {
    var modal = document.getElementById('lms-feedback-modal');
    if (!modal) {
      return;
    }
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('lms-modal-open');
  }

  function markFeedbackDone(message) {
    var formWrap = document.getElementById('lms-feedback-form-wrap');
    var done = document.getElementById('lms-feedback-done');
    var modal = document.getElementById('lms-feedback-modal');
    if (formWrap) {
      formWrap.classList.add('hidden');
      formWrap.innerHTML = '';
    }
    if (done) {
      done.classList.remove('hidden');
      var title = done.querySelector('.lms-fb-done__title') || done.querySelector('p');
      if (title) {
        title.textContent = message || 'Merci : votre retour a bien été enregistré.';
      }
      var meta = done.querySelector('.lms-fb-done__meta');
      if (meta && message && message.indexOf('plus tard') !== -1) {
        meta.classList.add('hidden');
      }
    }
    if (modal) {
      modal.setAttribute('data-lms-feedback-done', '1');
    }
    var c = cfg();
    if (c) {
      c.hasFeedback = true;
    }
  }

  function skipFeedback() {
    persistFeedbackSkip();
    markFeedbackDone('Étape passée — vous pourrez donner votre avis plus tard si besoin.');
    closeFeedbackModal();
    if (typeof window.lmsTrainingToastShow === 'function') {
      window.lmsTrainingToastShow('Avis passé pour cette leçon.', 'info');
    }
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
      btn.classList.add('lms-btn--disabled', 'opacity-60', 'cursor-default');
      btn.classList.remove('lms-btn--primary', 'hover:bg-emerald-700');
      btn.removeAttribute('data-lms-await-parcours');
    }
    updateProgressBars(percent);
    if (typeof window.lmsTrainingToastShow === 'function') {
      window.lmsTrainingToastShow('Leçon validée — vous pouvez poursuivre le parcours.', 'success');
    }
    var c = cfg();
    if (c) {
      c.alreadyCompleted = true;
    }
    if ((!c || !c.hasFeedback) && !isFeedbackSkipped()) {
      openFeedbackModal();
    }
  }

  function renderEventRecommendation(reco) {
    if (!reco || typeof reco !== 'object') {
      return;
    }
    var root = document.getElementById('lms-event-recommendation');
    if (!root) {
      return;
    }
    var label = reco.label || 'Entraînement recommandé';
    var startsAt = reco.starts_at || '';
    var location = reco.location ? ' · Lieu : ' + reco.location : '';
    var courseUrl = (cfg() && cfg().courseUrl) || '#';
    root.classList.remove('hidden');
    root.innerHTML =
      '<p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Événement d’entraînement recommandé</p>' +
      '<h3 class="mt-1 text-sm font-bold text-slate-900"></h3>' +
      '<p class="mt-1 text-xs text-slate-600"></p>' +
      '<a class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:underline" href="' +
      courseUrl +
      '">Voir le parcours et ses créneaux →</a>';
    var title = root.querySelector('h3');
    var details = root.querySelector('p.mt-1');
    if (title) {
      title.textContent = String(label);
    }
    if (details) {
      details.textContent = 'Début : ' + String(startsAt) + String(location);
    }
    if (typeof window.lmsTrainingToastShow === 'function') {
      window.lmsTrainingToastShow('Module clé validé : un événement d’entraînement recommandé vous est proposé.', 'info');
    }
  }

  var posted = false;
  var readyArmed = false;

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

  function armReady() {
    var c = cfg();
    if (!c || c.alreadyCompleted || posted || readyArmed) {
      return;
    }
    readyArmed = true;
    var btn = document.getElementById('lms-btn-complete');
    if (btn && btn.getAttribute('data-lms-await-parcours') === '1') {
      btn.disabled = false;
      btn.classList.remove('lms-btn--disabled', 'opacity-60');
      btn.classList.add('lms-btn--primary');
      btn.textContent = 'Terminer la leçon';
    }
    var hint = document.getElementById('lms-progress-status');
    if (hint) {
      hint.textContent =
        'Contenu parcouru. Cliquez sur « Terminer la leçon » pour enregistrer votre validation auprès du système.';
      hint.classList.remove('text-rose-600');
      hint.classList.add('text-slate-600');
    }
    if (typeof window.lmsTrainingToastShow === 'function') {
      window.lmsTrainingToastShow('Parcours complété — validez avec « Terminer la leçon ».', 'info');
    }
  }

  function confirmComplete() {
    var c = cfg();
    if (!c || !c.apiUrl || c.alreadyCompleted || posted) {
      return;
    }
    posted = true;
    var token = window.__LMS_CSRF__ || '';
    var btn = document.getElementById('lms-btn-complete');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Enregistrement…';
    }
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
        if (data && data.event_recommendation) {
          renderEventRecommendation(data.event_recommendation);
        }
      })
      .catch(function (err) {
        posted = false;
        var msg =
          err && err.message
            ? err.message
            : 'L’enregistrement a échoué. Rechargez la page ou réessayez dans un instant.';
        if (typeof window.lmsTrainingToastShow === 'function') {
          window.lmsTrainingToastShow(msg, 'error');
        }
        var hint = document.getElementById('lms-progress-status');
        if (hint) {
          hint.textContent = msg;
          hint.classList.remove('text-slate-600', 'text-emerald-700');
          hint.classList.add('text-rose-600', 'font-semibold');
        }
        if (btn && btn.getAttribute('data-lms-await-parcours') === '1') {
          btn.disabled = !readyArmed;
          btn.textContent = 'Terminer la leçon';
          if (readyArmed) {
            btn.classList.remove('lms-btn--disabled');
            btn.classList.add('lms-btn--primary');
          }
        } else if (btn && btn.type === 'submit') {
          btn.disabled = false;
          btn.textContent = 'Terminer la leçon';
        }
      });
  }

  window.LmsLessonProgress = {
    /** Débloque le bouton « Terminer » après parcours du contenu. */
    armReady: armReady,
    /** Enregistre la validation auprès du système (action explicite). */
    confirmComplete: confirmComplete,
    /**
     * Compat : sans argument = débloquer ; avec true = valider tout de suite
     * (ex. clic « Terminer » du parcours visuel, évaluation réussie).
     */
    signalComplete: function (immediate) {
      if (immediate) {
        confirmComplete();
      } else {
        armReady();
      }
    },
  };

  document.addEventListener('DOMContentLoaded', function () {
    var cBoot = cfg();
    if (cBoot && !cBoot.hasFeedback && isFeedbackSkipped()) {
      cBoot.hasFeedback = true;
      var modalBoot = document.getElementById('lms-feedback-modal');
      if (modalBoot) {
        modalBoot.setAttribute('data-lms-feedback-done', '1');
      }
    }

    var feedbackModal = document.getElementById('lms-feedback-modal');
    if (feedbackModal) {
      feedbackModal.querySelectorAll('[data-lms-feedback-close]').forEach(function (el) {
        el.addEventListener('click', closeFeedbackModal);
      });
      feedbackModal.querySelectorAll('[data-lms-feedback-skip]').forEach(function (el) {
        el.addEventListener('click', function (e) {
          e.preventDefault();
          skipFeedback();
        });
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !feedbackModal.classList.contains('hidden')) {
          closeFeedbackModal();
        }
      });
    }

    var feedbackForm = document.querySelector('form[data-lesson-feedback]');
    if (feedbackForm) {
      feedbackForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var feedbackStatus = document.getElementById('lms-feedback-status');
        var c0 = cfg();
        var api = c0 && c0.feedbackApiUrl ? c0.feedbackApiUrl : '';
        if (!api) {
          if (feedbackStatus) {
            feedbackStatus.textContent = 'Enregistrement indisponible pour le moment.';
            feedbackStatus.className = 'lms-fb-status text-rose-600';
          }
          return;
        }
        var fd = new FormData(feedbackForm);
        fd.set('_csrf_token', window.__LMS_CSRF__ || '');
        if (feedbackStatus) {
          feedbackStatus.textContent = 'Enregistrement…';
          feedbackStatus.className = 'lms-fb-status text-slate-600';
        }
        fetch(api, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-CSRF-Token': window.__LMS_CSRF__ || '',
          },
          credentials: 'same-origin',
          body: fd,
        })
          .then(function (r) {
            return r.json().then(function (j) {
              if (!r.ok) {
                throw new Error((j && j.error) || 'Erreur');
              }
              return j;
            });
          })
          .then(function (payload) {
            markFeedbackDone((payload && payload.message) || 'Merci : votre retour a bien été enregistré.');
            closeFeedbackModal();
            if (typeof window.lmsTrainingToastShow === 'function') {
              window.lmsTrainingToastShow('Avis enregistré.', 'success');
            }
          })
          .catch(function (err) {
            if (feedbackStatus) {
              feedbackStatus.textContent = (err && err.message) || 'Impossible d’enregistrer votre avis.';
              feedbackStatus.className = 'lms-fb-status text-rose-600';
            }
            if (typeof window.lmsTrainingToastShow === 'function') {
              window.lmsTrainingToastShow('Impossible d’enregistrer votre avis.', 'error');
            }
          });
      });
    }

    var progressForm = document.querySelector('form[data-progress-lesson]');
    if (progressForm) {
      progressForm.addEventListener('submit', function (event) {
        event.preventDefault();
        confirmComplete();
      });
    }

    var awaitBtn = document.getElementById('lms-btn-complete');
    if (awaitBtn && awaitBtn.getAttribute('data-lms-await-parcours') === '1') {
      awaitBtn.addEventListener('click', function () {
        if (awaitBtn.disabled) {
          return;
        }
        confirmComplete();
      });
    }

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

      function tryRichtextReady() {
        if (!sentinelOk || !scrollEnough()) {
          return;
        }
        window.LmsLessonProgress.armReady();
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
                  tryRichtextReady();
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
      window.addEventListener('scroll', tryRichtextReady, { passive: true });
      return;
    }

    if (lt === 'video' || lt === 'video_integrated') {
      var v = document.getElementById('lms-lesson-video');
      if (v) {
        var minR = strictNum('mediaPlayedMinRatio', 0.88);
        v.addEventListener('ended', function () {
          if (mediaPlayedRatio(v) >= minR) {
            window.LmsLessonProgress.armReady();
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
            window.LmsLessonProgress.armReady();
          } else {
            warnMediaSkip();
          }
        });
      }
    }
  });
})();
