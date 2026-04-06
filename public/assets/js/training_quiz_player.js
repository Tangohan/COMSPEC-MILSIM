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
        opts.body = JSON.stringify(
          Object.assign({}, opts.json, { _csrf_token: csrf })
        );
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
        if (data.status && data.status !== 'in_progress') {
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
          '<p class="text-rose-600">' + (e.message || 'Erreur de chargement') + '</p>';
      });
  }

  function showResult(root, attempt) {
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
    var form = el('<form class="space-y-8" id="lms-quiz-form"></form>');
    var title = quiz.title ? String(quiz.title) : 'Questionnaire';
    form.appendChild(
      el(
        '<div class="mb-6 pb-6 border-b border-slate-100">' +
          '<p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-600 mb-2">À compléter</p>' +
          '<h2 class="text-xl sm:text-2xl font-black uppercase tracking-tight text-slate-900 leading-tight">' +
          escapeHtml(title) +
          '</h2>' +
          '<p class="text-sm text-slate-500 mt-2">Répondez à chaque question puis validez une seule fois.</p></div>'
      )
    );

    questions.forEach(function (q, qi) {
      var qid = String(q.id);
      var type = q.question_type || 'single_choice';
      var block = el(
        '<div class="lms-panel rounded-2xl p-6 md:p-7 border border-slate-100 shadow-sm" data-qid="' + qid + '"></div>'
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
            '" rows="4" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm" placeholder="Votre réponse"></textarea>'
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
        '<button type="submit" class="px-8 py-3.5 bg-emerald-600 text-white text-sm font-black uppercase rounded-xl hover:bg-emerald-700 shadow-md shadow-emerald-600/15">Envoyer mes réponses</button>' +
        '<a href="' +
        cancelHref +
        '" class="px-8 py-3.5 border border-slate-200 text-slate-700 text-sm font-bold uppercase rounded-xl hover:bg-slate-50">Retour sans valider</a>' +
        '</div>'
    );
    form.appendChild(actions);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var responses = {};
      questions.forEach(function (q) {
        var qid = parseInt(q.id, 10);
        var type = q.question_type || 'single_choice';
        if (type === 'single_choice' || type === 'true_false') {
          var sel = form.querySelector('input[name="q_' + q.id + '"]:checked');
          if (sel) responses[qid] = parseInt(sel.value, 10);
        } else if (type === 'multiple_choice') {
          var boxes = form.querySelectorAll('input[name="q_' + q.id + '[]"]:checked');
          var ids = [];
          boxes.forEach(function (b) {
            ids.push(parseInt(b.value, 10));
          });
          responses[qid] = { answer_ids: ids };
        } else {
          var tx = form.querySelector('textarea[name="q_' + q.id + '"]');
          if (tx && tx.value) responses[qid] = { text: tx.value };
        }
      });

      var btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;

      fetch(base + '/api/training/quiz/submit', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          _csrf_token: csrf,
          attempt_id: parseInt(attemptId, 10),
          responses: responses,
        }),
      })
        .then(function (r) {
          return r.json().then(function (j) {
            if (!r.ok) throw new Error(j.error || 'Erreur');
            return j;
          });
        })
        .then(function (out) {
          showResult(root, out.attempt || out);
        })
        .catch(function (err) {
          alert(err.message || 'Erreur');
          if (btn) btn.disabled = false;
        });
    });

    root.appendChild(form);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
