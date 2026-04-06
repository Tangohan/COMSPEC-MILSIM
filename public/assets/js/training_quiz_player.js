/**
 * UI quiz LMS — charge la tentative, affiche les questions, soumet les réponses.
 */
(function () {
  'use strict';

  function el(html) {
    var t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstChild;
  }

  function escapeHtml(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  /** Préfère started_at_rfc3339 (UTC explicite) pour éviter tout décalage navigateur / serveur. */
  function parseStartedAtMs(startedAt, startedAtRfc3339Utc) {
    if (startedAtRfc3339Utc) {
      var u = String(startedAtRfc3339Utc).trim();
      if (u) {
        var msUtc = Date.parse(u);
        if (!isNaN(msUtc)) return msUtc;
      }
    }
    if (!startedAt) return NaN;
    var s = String(startedAt).trim();
    if (!s) return NaN;
    var iso = s.indexOf('T') === -1 ? s.replace(' ', 'T') : s;
    var ms = Date.parse(iso);
    if (!isNaN(ms)) return ms;
    return Date.parse(s);
  }

  function formatRemaining(sec) {
    if (sec < 0) sec = 0;
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function clearQuizTimer(state) {
    if (state && state.timerId) {
      clearInterval(state.timerId);
      state.timerId = null;
    }
  }

  function showGlobalAlert(root, type, title, lines) {
    var box = root.querySelector('#lms-quiz-global-alert');
    if (!box) return;
    lines = lines || [];
    var isErr = type === 'error';
    box.className =
      'mb-6 rounded-2xl border px-4 py-3 text-sm ' +
      (isErr
        ? 'border-rose-200 bg-rose-50 text-rose-900'
        : 'border-amber-200 bg-amber-50 text-amber-950');
    box.innerHTML =
      '<p class="font-bold mb-1">' +
      escapeHtml(title) +
      '</p>' +
      (lines.length
        ? '<ul class="list-disc pl-5 space-y-1 mt-2">' +
          lines.map(function (l) {
            return '<li>' + escapeHtml(l) + '</li>';
          }).join('') +
          '</ul>'
        : '');
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideGlobalAlert(root) {
    var box = root.querySelector('#lms-quiz-global-alert');
    if (!box) return;
    box.classList.add('hidden');
    box.innerHTML = '';
  }

  function boot() {
    var root = document.getElementById('lms-quiz-app');
    if (!root) return;
    var attemptId = root.getAttribute('data-attempt-id');
    var base = (root.getAttribute('data-base') || '').replace(/\/$/, '');
    var csrf = root.getAttribute('data-csrf') || '';

    function api(path, opts) {
      opts = opts || {};
      var headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
      if (opts.json) {
        headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(Object.assign({}, opts.json, { _csrf_token: csrf }));
      } else if (opts.form) {
        opts.body = opts.form;
      }
      if (!opts.credentials) opts.credentials = 'same-origin';
      opts.headers = headers;
      return fetch(base + path, opts).then(function (r) {
        return r.json().then(function (j) {
          if (!r.ok) throw new Error(j.error || r.statusText);
          return j;
        });
      });
    }

    root.innerHTML =
      '<div class="lms-panel rounded-2xl p-8 md:p-10 text-center">' +
      '<p class="text-slate-600 text-sm font-medium">Chargement du questionnaire…</p></div>';

    api('/api/training/quiz/attempts/' + encodeURIComponent(attemptId))
      .then(function (data) {
        var st = data.status || '';
        if (st === 'expired') {
          showAttemptClosed(root, 'Le temps imparti est écoulé pour cette tentative. Ouvrez à nouveau le questionnaire depuis la formation pour recommencer, si des tentatives restent disponibles.');
          return;
        }
        if (st && st !== 'in_progress') {
          showResult(root, data);
          return;
        }
        var questions = data.questions || [];
        if (!questions.length) {
          root.innerHTML =
            '<div class="lms-panel rounded-2xl p-8 border border-rose-100 bg-rose-50/50"><p class="text-rose-800 text-sm font-medium">Ce questionnaire ne contient pas encore de questions exploitables. Revenez plus tard ou contactez l’équipe pédagogique.</p></div>';
          return;
        }
        renderForm(root, data, questions, base, csrf);
      })
      .catch(function (e) {
        root.innerHTML =
          '<div class="lms-panel rounded-2xl p-8 border border-rose-100 bg-rose-50/50"><p class="text-rose-800 text-sm font-medium">' +
          escapeHtml(e.message || 'Impossible de charger le questionnaire.') +
          '</p></div>';
      });
  }

  function showAttemptClosed(root, message) {
    var back =
      root.getAttribute('data-course-url') ||
      root.getAttribute('data-formations-url') ||
      '/formations';
    root.innerHTML = '';
    var box = el(
      '<div class="lms-panel rounded-[2rem] p-8 md:p-10 max-w-lg mx-auto text-center border border-amber-100 bg-amber-50/40">' +
        '<p class="text-[10px] font-black uppercase tracking-[0.3em] text-amber-700 mb-2">Session non valide</p>' +
        '<p class="text-slate-800 text-sm leading-relaxed mb-6">' +
        escapeHtml(message) +
        '</p>' +
        '<a href="' +
        back +
        '" class="inline-flex px-8 py-3 bg-emerald-600 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700">Retour à la formation</a>' +
        '</div>'
    );
    root.appendChild(box);
  }

  function showResult(root, attempt) {
    var st = attempt.status || '';
    if (st === 'expired') {
      showAttemptClosed(
        root,
        'Le temps imparti est écoulé pour cette tentative. Reprenez depuis la fiche formation si vous le pouvez.'
      );
      return;
    }
    var passed = attempt.passed == 1 || attempt.passed === true;
    var score = attempt.score != null ? attempt.score : '—';
    var back =
      root.getAttribute('data-course-url') ||
      root.getAttribute('data-formations-url') ||
      '/formations';
    root.innerHTML = '';
    var box = el(
      '<div class="lms-panel rounded-[2rem] p-8 md:p-10 max-w-lg mx-auto text-center relative overflow-hidden">' +
        '<div class="absolute top-0 left-0 right-0 h-1 ' +
        (passed ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-rose-500 to-amber-400') +
        ' rounded-t-[2rem]"></div>' +
        '<p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 mb-2">Résultat</p>' +
        '<h2 class="text-2xl font-black text-slate-900 mb-2">Votre score</h2>' +
        '<p class="text-4xl font-black text-slate-900 mb-3">' +
        score +
        ' %</p>' +
        '<p class="text-sm font-bold ' +
        (passed ? 'text-emerald-700' : 'text-rose-700') +
        '">' +
        (passed ? 'Parcours validé pour cette évaluation' : 'Seuil non atteint — vous pourrez réessayer selon les règles du parcours') +
        '</p>' +
        '<a href="' +
        back +
        '" class="inline-flex mt-8 px-8 py-3 bg-emerald-600 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700 shadow-lg shadow-emerald-600/20">Continuer</a>' +
        '</div>'
    );
    root.appendChild(box);
  }

  function renderForm(root, attempt, questions, base, csrf) {
    root.innerHTML = '';
    var quiz = attempt.quiz || {};
    var limitMin = attempt.time_limit_minutes != null ? parseInt(String(attempt.time_limit_minutes), 10) : 0;
    if (isNaN(limitMin) || limitMin < 1) limitMin = 0;
    var startedMs = parseStartedAtMs(attempt.started_at, attempt.started_at_rfc3339);
    var deadlineMs = limitMin > 0 && !isNaN(startedMs) ? startedMs + limitMin * 60 * 1000 : null;

    var timerState = { timerId: null };

    var form = el('<form class="space-y-8" id="lms-quiz-form" novalidate></form>');
    form.appendChild(
      el(
        '<div id="lms-quiz-global-alert" class="hidden rounded-2xl border px-4 py-3 text-sm" role="alert"></div>'
      )
    );

    var title = quiz.title ? String(quiz.title) : 'Questionnaire';
    var headerInner =
      '<div class="mb-6 pb-6 border-b border-slate-100">' +
      '<p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-600 mb-2">À compléter</p>' +
      '<h2 class="text-xl sm:text-2xl font-black uppercase tracking-tight text-slate-900 leading-tight">' +
      escapeHtml(title) +
      '</h2>' +
      '<p class="text-sm text-slate-500 mt-2">Répondez à chaque question puis validez une seule fois.</p>';

    if (deadlineMs) {
      headerInner +=
        '<div id="lms-quiz-timer-bar" class="mt-5 flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm">' +
        '<div class="flex items-center gap-2">' +
        '<span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Temps restant</span>' +
        '<span id="lms-quiz-remaining" class="font-mono font-black text-lg text-slate-900 tabular-nums">--:--</span>' +
        '</div>' +
        '<div class="h-4 w-px bg-slate-200 hidden sm:block" aria-hidden="true"></div>' +
        '<p class="text-xs text-slate-600"><span class="font-semibold text-slate-800">' +
        limitMin +
        ' min</span> à compter du début de session · fin prévue vers <span id="lms-quiz-deadline-clock" class="font-semibold text-slate-800">—</span></p>' +
        '</div>';
    } else {
      headerInner +=
        '<p class="mt-4 text-xs text-slate-500">Aucune limite de durée n’est imposée pour cette session : prenez le temps de relire chaque énoncé.</p>';
    }

    headerInner += '</div>';
    form.appendChild(el(headerInner));

    if (deadlineMs) {
      var dlEl = form.querySelector('#lms-quiz-deadline-clock');
      if (dlEl) {
        try {
          dlEl.textContent = new Date(deadlineMs).toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
          });
        } catch (e) {
          dlEl.textContent = '—';
        }
      }
    }

    questions.forEach(function (q, qi) {
      var qid = String(q.id);
      var type = q.question_type || 'single_choice';
      var block = el(
        '<div class="lms-panel rounded-2xl p-6 md:p-7 border border-slate-100 shadow-sm transition-colors" data-qid="' +
          qid +
          '" data-q-index="' +
          (qi + 1) +
          '"></div>'
      );
      block.appendChild(
        el(
          '<div class="flex items-center gap-2 mb-3"><span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-black">' +
            (qi + 1) +
            '</span><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Question</p></div>'
        )
      );
      var qt = document.createElement('div');
      qt.className = 'text-slate-900 mb-5 text-base font-semibold leading-relaxed whitespace-pre-wrap';
      qt.textContent = q.question_text || '';
      block.appendChild(qt);

      var answers = q.answers || [];
      if (type === 'single_choice' || type === 'true_false') {
        answers.forEach(function (a) {
          var id = 'q' + qid + 'a' + a.id;
          var row = el(
            '<label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200/90 hover:border-emerald-200 hover:bg-emerald-50/40 cursor-pointer transition-colors">' +
              '<input type="radio" name="q_' +
              qid +
              '" value="' +
              a.id +
              '" class="mt-1">' +
              '<span class="text-sm text-slate-700">' +
              escapeHtml(a.answer_text || '') +
              '</span></label>'
          );
          block.appendChild(row);
        });
      } else if (type === 'multiple_choice') {
        answers.forEach(function (a) {
          var row = el(
            '<label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200/90 hover:border-emerald-200 hover:bg-emerald-50/40 cursor-pointer transition-colors">' +
              '<input type="checkbox" name="q_' +
              qid +
              '[]" value="' +
              a.id +
              '" class="mt-1">' +
              '<span class="text-sm text-slate-700">' +
              escapeHtml(a.answer_text || '') +
              '</span></label>'
          );
          block.appendChild(row);
        });
      } else {
        var ta = el(
          '<textarea name="q_' +
            qid +
            '" rows="4" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm" placeholder="Saisissez votre réponse"></textarea>'
        );
        block.appendChild(ta);
      }
      form.appendChild(block);
    });

    var cancelHref =
      root.getAttribute('data-course-url') ||
      root.getAttribute('data-formations-url') ||
      '/formations';
    var actions = el(
      '<div class="flex flex-wrap gap-4 pt-4">' +
        '<button type="submit" id="lms-quiz-submit-btn" class="px-8 py-3.5 bg-emerald-600 text-white text-sm font-black uppercase rounded-xl hover:bg-emerald-700 shadow-md shadow-emerald-600/15">Envoyer mes réponses</button>' +
        '<a href="' +
        cancelHref +
        '" class="px-8 py-3.5 border border-slate-200 text-slate-700 text-sm font-bold uppercase rounded-xl hover:bg-slate-50">Retour sans valider</a>' +
        '</div>'
    );
    form.appendChild(actions);

    function setExpiredUi() {
      clearQuizTimer(timerState);
      var btn = form.querySelector('#lms-quiz-submit-btn');
      if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
      }
      showGlobalAlert(
        root,
        'error',
        'Temps écoulé',
        ['Vous ne pouvez plus envoyer vos réponses pour cette session. Reprenez le questionnaire depuis la formation si des tentatives restent disponibles.']
      );
    }

    function tickTimer() {
      if (!deadlineMs) return;
      var remEl = form.querySelector('#lms-quiz-remaining');
      var bar = form.querySelector('#lms-quiz-timer-bar');
      var now = Date.now();
      var secLeft = Math.floor((deadlineMs - now) / 1000);
      if (remEl) remEl.textContent = formatRemaining(secLeft);
      if (bar) {
        if (secLeft <= 120) bar.classList.add('border-amber-300', 'bg-amber-50/60');
        if (secLeft <= 30) bar.classList.add('border-rose-200', 'bg-rose-50/50');
      }
      if (secLeft <= 0) {
        setExpiredUi();
      }
    }

    if (deadlineMs) {
      tickTimer();
      timerState.timerId = setInterval(tickTimer, 1000);
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      hideGlobalAlert(root);
      questions.forEach(function (q) {
        var b = form.querySelector('[data-qid="' + q.id + '"]');
        if (b) {
          b.classList.remove('border-rose-400', 'ring-2', 'ring-rose-100');
          b.classList.add('border-slate-100');
        }
      });

      if (deadlineMs && Date.now() > deadlineMs) {
        setExpiredUi();
        return;
      }

      var responses = {};
      var missing = [];
      questions.forEach(function (q) {
        var qid = parseInt(q.id, 10);
        var type = q.question_type || 'single_choice';
        var idx = q.__displayIndex || 1;
        if (type === 'single_choice' || type === 'true_false') {
          var sel = form.querySelector('input[name="q_' + q.id + '"]:checked');
          if (sel) responses[qid] = parseInt(sel.value, 10);
          else missing.push('Question ' + idx + ' : aucune réponse sélectionnée.');
        } else if (type === 'multiple_choice') {
          var boxes = form.querySelectorAll('input[name="q_' + q.id + '[]"]:checked');
          var ids = [];
          boxes.forEach(function (b) {
            ids.push(parseInt(b.value, 10));
          });
          if (ids.length === 0) {
            missing.push('Question ' + idx + ' : cochez au moins une proposition ou décochez pour choisir autrement.');
          } else {
            responses[qid] = { answer_ids: ids };
          }
        } else {
          var tx = form.querySelector('textarea[name="q_' + q.id + '"]');
          var raw = tx ? String(tx.value || '').trim() : '';
          if (raw === '') {
            missing.push('Question ' + idx + ' : la réponse écrite est vide.');
          } else {
            responses[qid] = { text: raw };
          }
        }
      });

      if (missing.length) {
        showGlobalAlert(root, 'error', 'Formulaire incomplet', missing);
        var m0 = missing[0].match(/^Question (\d+)/);
        if (m0) {
          var block0 = form.querySelector('[data-q-index="' + m0[1] + '"]');
          if (block0) {
            block0.classList.remove('border-slate-100');
            block0.classList.add('border-rose-400', 'ring-2', 'ring-rose-100');
            block0.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }
        return;
      }

      var btn = form.querySelector('#lms-quiz-submit-btn');
      if (btn) btn.disabled = true;

      fetch(base + '/api/training/quiz/submit', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          _csrf_token: csrf,
          attempt_id: parseInt(attempt.id, 10),
          responses: responses,
        }),
      })
        .then(function (r) {
          return r.json().then(function (j) {
            if (!r.ok) throw new Error(j.error || 'Envoi impossible');
            return j;
          });
        })
        .then(function (out) {
          clearQuizTimer(timerState);
          showResult(root, out.attempt || out);
        })
        .catch(function (err) {
          showGlobalAlert(root, 'error', 'Envoi refusé', [err.message || 'Une erreur est survenue. Vous pouvez réessayer.']);
          if (btn) btn.disabled = false;
        });
    });

    questions.forEach(function (q, i) {
      q.__displayIndex = i + 1;
    });

    root.appendChild(form);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
