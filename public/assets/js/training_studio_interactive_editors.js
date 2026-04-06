/**
 * Studio LMS — éditeurs visuels pour leçons Quiz, Modales, Diaporama (JSON synchronisé en arrière-plan).
 */
(function () {
  'use strict';

  function uid(prefix) {
    return prefix + '_' + Math.random().toString(36).slice(2, 10);
  }

  function safeParse(json, fallback) {
    try {
      var o = JSON.parse(json);
      return o !== null && typeof o === 'object' ? o : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function getTemplates(form) {
    var help = form.querySelector('[data-lms-json-help]');
    if (!help) return { quiz: '{}', modals: '{}', slideshow: '{}' };
    return {
      quiz: help.getAttribute('data-template-quiz') || '{}',
      modals: help.getAttribute('data-template-modals') || '{}',
      slideshow: help.getAttribute('data-template-slideshow') || '{}',
    };
  }

  function syncTextarea(ta, obj) {
    ta.value = JSON.stringify(obj, null, 0);
  }

  /* ---------- Quiz ---------- */
  function renderQuiz(root, data, ta) {
    data.version = 1;
    if (typeof data.passingPercent !== 'number') data.passingPercent = 70;
    if (typeof data.shuffle !== 'boolean') data.shuffle = false;
    if (!Array.isArray(data.questions) || data.questions.length === 0) {
      data.questions = [
        {
          id: uid('q'),
          prompt: '',
          choices: [
            { id: 'a', label: '' },
            { id: 'b', label: '' },
          ],
          correct: ['a'],
        },
      ];
    }

    root.innerHTML = '';
    var wrap = document.createElement('div');
    wrap.className = 'lms-int-quiz space-y-4 text-sm';

    var top = document.createElement('div');
    top.className = 'flex flex-wrap gap-4 items-end';
    top.innerHTML =
      '<label class="block text-xs font-bold text-slate-700">Seuil de réussite (%)' +
      '<input type="number" min="1" max="100" class="mt-1 block w-24 border border-slate-200 rounded-lg px-2 py-1.5" data-lq-pass value="' +
      String(data.passingPercent) +
      '"></label>' +
      '<label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">' +
      '<input type="checkbox" data-lq-shuffle class="rounded border-slate-300"' +
      (data.shuffle ? ' checked' : '') +
      '> Mélanger les questions</label>';
    wrap.appendChild(top);

    var qHost = document.createElement('div');
    qHost.className = 'space-y-4';
    qHost.setAttribute('data-lq-questions', '');
    wrap.appendChild(qHost);

    function collectAndPaint() {
      data.passingPercent = Math.min(100, Math.max(1, parseInt(wrap.querySelector('[data-lq-pass]').value, 10) || 70));
      data.shuffle = wrap.querySelector('[data-lq-shuffle]').checked;
      data.questions = [];
      qHost.querySelectorAll('[data-lq-question]').forEach(function (qEl, qi) {
        var qid = qEl.getAttribute('data-qid') || uid('q');
        var prompt = qEl.querySelector('[data-lq-prompt]').value;
        var choices = [];
        var correct = [];
        qEl.querySelectorAll('[data-lq-choice]').forEach(function (cEl) {
          var cid = cEl.getAttribute('data-cid');
          var label = cEl.querySelector('[data-lq-clabel]').value;
          choices.push({ id: cid, label: label });
          if (cEl.querySelector('[data-lq-ccorrect]').checked) correct.push(cid);
        });
        data.questions.push({ id: qid, prompt: prompt, choices: choices, correct: correct });
      });
      syncTextarea(ta, data);
    }

    function paintQuestion(q, idx) {
      var qid = q.id || uid('q');
      var box = document.createElement('div');
      box.className = 'rounded-xl border border-slate-200 bg-white p-4 shadow-sm';
      box.setAttribute('data-lq-question', '');
      box.setAttribute('data-qid', qid);

      var head = document.createElement('div');
      head.className = 'flex justify-between items-center gap-2 mb-2';
      head.innerHTML =
        '<span class="text-xs font-black uppercase text-emerald-700">Question ' +
        (idx + 1) +
        '</span>' +
        '<button type="button" class="text-xs text-rose-600 font-bold hover:underline" data-lq-remove-q>Retirer</button>';
      box.appendChild(head);

      var taPrompt = document.createElement('textarea');
      taPrompt.rows = 2;
      taPrompt.className = 'w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm mb-3';
      taPrompt.placeholder = 'Intitulé de la question';
      taPrompt.setAttribute('data-lq-prompt', '');
      taPrompt.value = q.prompt || '';
      box.appendChild(taPrompt);

      var chHost = document.createElement('div');
      chHost.className = 'space-y-2';
      chHost.setAttribute('data-lq-choices', '');
      box.appendChild(chHost);

      if (!Array.isArray(q.choices) || q.choices.length === 0) {
        q.choices = [
          { id: 'a', label: '' },
          { id: 'b', label: '' },
        ];
      }
      var correctSet = {};
      (q.correct || []).forEach(function (c) {
        correctSet[c] = true;
      });

      q.choices.forEach(function (ch) {
        var cid = ch.id || uid('c');
        var row = document.createElement('div');
        row.className = 'flex flex-wrap items-center gap-2';
        row.setAttribute('data-lq-choice', '');
        row.setAttribute('data-cid', cid);
        row.innerHTML =
          '<label class="inline-flex items-center gap-2 shrink-0"><input type="checkbox" data-lq-ccorrect title="Bonne réponse"' +
          (correctSet[cid] ? ' checked' : '') +
          '></label>' +
          '<input type="text" class="flex-1 min-w-[120px] border border-slate-200 rounded px-2 py-1 text-sm" placeholder="Libellé de la réponse" data-lq-clabel>' +
          '<button type="button" class="text-xs text-slate-500 hover:text-rose-600" data-lq-remove-c>×</button>';
        row.querySelector('[data-lq-clabel]').value = ch.label || '';
        chHost.appendChild(row);
      });

      chHost.querySelectorAll('[data-lq-choice]').forEach(bindRow);

      var addC = document.createElement('button');
      addC.type = 'button';
      addC.className = 'mt-2 text-xs font-bold text-emerald-700 hover:underline';
      addC.textContent = '+ Ajouter une réponse';
      addC.addEventListener('click', function () {
        var nid = uid('c');
        var row = document.createElement('div');
        row.className = 'flex flex-wrap items-center gap-2';
        row.setAttribute('data-lq-choice', '');
        row.setAttribute('data-cid', nid);
        row.innerHTML =
          '<label class="inline-flex items-center gap-2 shrink-0"><input type="checkbox" data-lq-ccorrect title="Bonne réponse"></label>' +
          '<input type="text" class="flex-1 min-w-[120px] border border-slate-200 rounded px-2 py-1 text-sm" placeholder="Libellé" data-lq-clabel>' +
          '<button type="button" class="text-xs text-slate-500 hover:text-rose-600" data-lq-remove-c>×</button>';
        chHost.appendChild(row);
        bindRow(row);
        collectAndPaint();
      });
      box.appendChild(addC);

      function bindRow(row) {
        row.querySelector('[data-lq-remove-c]').addEventListener('click', function () {
          row.remove();
          collectAndPaint();
        });
        row.querySelectorAll('input').forEach(function (inp) {
          inp.addEventListener('input', collectAndPaint);
          inp.addEventListener('change', collectAndPaint);
        });
      }
      head.querySelector('[data-lq-remove-q]').addEventListener('click', function () {
        box.remove();
        collectAndPaint();
      });
      taPrompt.addEventListener('input', collectAndPaint);

      return box;
    }

    data.questions.forEach(function (q, i) {
      qHost.appendChild(paintQuestion(q, i));
    });

    var addQ = document.createElement('button');
    addQ.type = 'button';
    addQ.className = 'px-4 py-2 rounded-lg border border-dashed border-emerald-400 text-emerald-800 text-xs font-black uppercase tracking-wide hover:bg-emerald-50';
    addQ.textContent = '+ Ajouter une question';
    addQ.addEventListener('click', function () {
      var nq = {
        id: uid('q'),
        prompt: '',
        choices: [
          { id: 'a', label: '' },
          { id: 'b', label: '' },
        ],
        correct: ['a'],
      };
      qHost.appendChild(paintQuestion(nq, qHost.querySelectorAll('[data-lq-question]').length));
      collectAndPaint();
    });
    wrap.appendChild(addQ);

    wrap.querySelector('[data-lq-pass]').addEventListener('input', collectAndPaint);
    wrap.querySelector('[data-lq-shuffle]').addEventListener('change', collectAndPaint);

    root.appendChild(wrap);
    collectAndPaint();
  }

  /* ---------- Modals ---------- */
  function renderModals(root, data, ta) {
    data.version = 1;
    if (!Array.isArray(data.modals) || data.modals.length === 0) {
      data.modals = [{ title: '', body: '', imageUrl: '' }];
    }

    root.innerHTML = '';
    var wrap = document.createElement('div');
    wrap.className = 'space-y-4';

    var host = document.createElement('div');
    host.className = 'space-y-4';
    wrap.appendChild(host);

    function collect() {
      data.modals = [];
      host.querySelectorAll('[data-lm-modal]').forEach(function (el) {
        data.modals.push({
          title: el.querySelector('[data-lm-title]').value,
          body: el.querySelector('[data-lm-body]').value,
          imageUrl: el.querySelector('[data-lm-img]').value,
        });
      });
      syncTextarea(ta, data);
    }

    function card(m, i) {
      var el = document.createElement('div');
      el.className = 'rounded-xl border border-slate-200 bg-white p-4 space-y-2';
      el.setAttribute('data-lm-modal', '');
      el.innerHTML =
        '<div class="flex justify-between items-center"><span class="text-xs font-black uppercase text-violet-700">Fenêtre ' +
        (i + 1) +
        '</span><button type="button" class="text-xs text-rose-600 font-bold" data-lm-remove>Retirer</button></div>' +
        '<label class="block text-xs font-bold text-slate-600">Titre<input type="text" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm" data-lm-title></label>' +
        '<label class="block text-xs font-bold text-slate-600">Texte (HTML simple possible)<textarea rows="4" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm" data-lm-body></textarea></label>' +
        '<label class="block text-xs font-bold text-slate-600">Image (URL, optionnel)<input type="text" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm" data-lm-img placeholder="https://…"></label>';
      el.querySelector('[data-lm-title]').value = m.title || '';
      el.querySelector('[data-lm-body]').value = m.body || '';
      el.querySelector('[data-lm-img]').value = m.imageUrl || '';
      el.querySelectorAll('input, textarea').forEach(function (inp) {
        inp.addEventListener('input', collect);
      });
      el.querySelector('[data-lm-remove]').addEventListener('click', function () {
        el.remove();
        collect();
      });
      return el;
    }

    data.modals.forEach(function (m, i) {
      host.appendChild(card(m, i));
    });

    var add = document.createElement('button');
    add.type = 'button';
    add.className = 'px-4 py-2 rounded-lg border border-dashed border-violet-300 text-violet-900 text-xs font-black uppercase';
    add.textContent = '+ Ajouter une fenêtre';
    add.addEventListener('click', function () {
      host.appendChild(card({ title: '', body: '', imageUrl: '' }, data.modals.length));
      collect();
    });
    wrap.appendChild(add);

    root.appendChild(wrap);
    collect();
  }

  /* ---------- Slideshow ---------- */
  function renderSlideshow(root, data, ta) {
    data.version = 1;
    if (!Array.isArray(data.slides) || data.slides.length === 0) {
      data.slides = [{ imageUrl: '', title: '', caption: '' }];
    }

    root.innerHTML = '';
    var wrap = document.createElement('div');
    wrap.className = 'space-y-4';
    var host = document.createElement('div');
    host.className = 'space-y-4';
    wrap.appendChild(host);

    function collect() {
      data.slides = [];
      host.querySelectorAll('[data-ls-slide]').forEach(function (el) {
        data.slides.push({
          imageUrl: el.querySelector('[data-ls-url]').value.trim(),
          title: el.querySelector('[data-ls-title]').value,
          caption: el.querySelector('[data-ls-cap]').value,
        });
      });
      syncTextarea(ta, data);
    }

    function slide(s, i) {
      var el = document.createElement('div');
      el.className = 'rounded-xl border border-slate-200 bg-white p-4 space-y-2';
      el.setAttribute('data-ls-slide', '');
      el.innerHTML =
        '<div class="flex justify-between items-center"><span class="text-xs font-black uppercase text-sky-700">Diapositive ' +
        (i + 1) +
        '</span><button type="button" class="text-xs text-rose-600 font-bold" data-ls-remove>Retirer</button></div>' +
        '<label class="block text-xs font-bold text-slate-600">Image (URL) *<input type="text" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm" data-ls-url placeholder="https://…"></label>' +
        '<label class="block text-xs font-bold text-slate-600">Titre<input type="text" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm" data-ls-title></label>' +
        '<label class="block text-xs font-bold text-slate-600">Légende<textarea rows="2" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm" data-ls-cap></textarea></label>';
      el.querySelector('[data-ls-url]').value = s.imageUrl || '';
      el.querySelector('[data-ls-title]').value = s.title || '';
      el.querySelector('[data-ls-cap]').value = s.caption || '';
      el.querySelectorAll('input, textarea').forEach(function (inp) {
        inp.addEventListener('input', collect);
      });
      el.querySelector('[data-ls-remove]').addEventListener('click', function () {
        el.remove();
        collect();
      });
      return el;
    }

    data.slides.forEach(function (s, i) {
      host.appendChild(slide(s, i));
    });

    var add = document.createElement('button');
    add.type = 'button';
    add.className = 'px-4 py-2 rounded-lg border border-dashed border-sky-300 text-sky-900 text-xs font-black uppercase';
    add.textContent = '+ Ajouter une diapositive';
    add.addEventListener('click', function () {
      host.appendChild(slide({ imageUrl: '', title: '', caption: '' }, data.slides.length));
      collect();
    });
    wrap.appendChild(add);

    root.appendChild(wrap);
    collect();
  }

  function mountForm(form) {
    var sel = form.querySelector('select[name="lesson_type"]');
    var root = form.querySelector('[data-lms-interactive-root]');
    var ta = form.querySelector('textarea[name="lesson_content"][data-lms-lesson-body]');
    var plainLabel = form.querySelector('[data-lms-plain-label]');
    if (!sel || !root || !ta) return;

    var templates = getTemplates(form);
    var jsonTypes = { quiz: 1, modals: 1, slideshow: 1 };

    function refresh() {
      var v = sel.value;
      var isJson = jsonTypes[v] === 1;
      if (!isJson) {
        root.classList.add('hidden');
        root.innerHTML = '';
        ta.classList.remove('hidden');
        if (plainLabel) plainLabel.textContent = 'Contenu (HTML ou texte)';
        return;
      }

      ta.classList.add('hidden');
      root.classList.remove('hidden');
      if (plainLabel) {
        if (v === 'quiz') plainLabel.textContent = 'Quiz — questions & réponses';
        else if (v === 'modals') plainLabel.textContent = 'Modales — fenêtres pédagogiques';
        else plainLabel.textContent = 'Diaporama — images & légendes';
      }

      var raw = ta.value.trim();
      var tmpl = templates[v] || '{}';
      var parsed = safeParse(raw, null);
      if (parsed === null) parsed = safeParse(tmpl, {});

      if (v === 'quiz') renderQuiz(root, parsed, ta);
      else if (v === 'modals') renderModals(root, parsed, ta);
      else renderSlideshow(root, parsed, ta);
    }

    sel.addEventListener('change', refresh);
    refresh();
  }

  function init() {
    document.querySelectorAll('form').forEach(mountForm);
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        var sel = form.querySelector('select[name="lesson_type"]');
        var ta = form.querySelector('textarea[name="lesson_content"][data-lms-lesson-body]');
        if (!sel || !ta) return;
        if (sel.value === 'quiz' || sel.value === 'modals' || sel.value === 'slideshow') {
          var root = form.querySelector('[data-lms-interactive-root]');
          if (root && !root.classList.contains('hidden')) {
            var ev = new Event('input', { bubbles: true });
            root.dispatchEvent(ev);
          }
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  document.addEventListener('lms-studio-interactive-refresh', init);
})();
