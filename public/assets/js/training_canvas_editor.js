/**
 * Studio LMS — éditeur canvas : aperçu temps réel, glisser-déposer des slides, JSON en mode avancé.
 */
(function () {
  'use strict';

  var TEMPLATES = [
    { id: 'title_hero', label: 'Titre & accroche' },
    { id: 'reading_article', label: 'Article de lecture' },
    { id: 'timeline', label: 'Frise chronologique' },
    { id: 'fill_blanks', label: 'Texte à trous' },
    { id: 'resources_list', label: 'Liste de ressources (liens)' },
    { id: 'split_text_image', label: 'Texte + image' },
    { id: 'image_full', label: 'Image pleine largeur' },
    { id: 'quote', label: 'Citation' },
    { id: 'file_block', label: 'Fichier / pièce jointe' },
    { id: 'text_rich', label: 'Texte libre' },
    { id: 'knowledge_check', label: 'Repères (liste à puces)' },
    { id: 'scorm_sequence', label: 'Séquence type SCORM' },
    { id: 'scenario_decision', label: 'Mise en situation — décision' },
    { id: 'dos_donts', label: 'À faire / à ne pas faire' },
    { id: 'process_steps', label: 'Procédure pas à pas' },
    { id: 'role_scope_compare', label: 'Membre / staff / droits' },
    { id: 'common_mistakes', label: 'Erreurs fréquentes' },
    { id: 'case_review', label: 'Analyse de cas' },
  ];

  var PREVIEW_DEBOUNCE_MS = 80;

  function uid(prefix) {
    return prefix + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
  }

  function ensureOpening(o) {
    if (!o || typeof o !== 'object') {
      return { eyebrow: '', title: '', lead: '', stats: [] };
    }
    if (!Array.isArray(o.stats)) {
      o.stats = [];
    }
    if (o.eyebrow == null) {
      o.eyebrow = '';
    }
    if (o.title == null) {
      o.title = '';
    }
    if (o.lead == null) {
      o.lead = '';
    }
    return o;
  }

  function ensureClosure(c) {
    if (!c || typeof c !== 'object') {
      return { title: '', seen: [], acquired: [], nextHint: '' };
    }
    if (!Array.isArray(c.seen)) {
      c.seen = [];
    }
    if (!Array.isArray(c.acquired)) {
      c.acquired = [];
    }
    if (c.title == null) {
      c.title = '';
    }
    if (c.nextHint == null) {
      c.nextHint = '';
    }
    return c;
  }

  function ensureSlideMissionFields(sl) {
    if (!sl || typeof sl !== 'object') {
      return;
    }
    if (sl.contextKicker == null) {
      sl.contextKicker = '';
    }
    var surf = String(sl.surface || 'default');
    if (surf !== 'elevated' && surf !== 'default') {
      sl.surface = 'default';
    }
    if (!Array.isArray(sl.cards)) {
      sl.cards = [];
    }
    if (!Array.isArray(sl.insights)) {
      sl.insights = [];
    }
    if (sl.metric != null && typeof sl.metric !== 'object') {
      sl.metric = null;
    }
    if (sl.metric && typeof sl.metric === 'object') {
      if (sl.metric.label == null) {
        sl.metric.label = '';
      }
      if (sl.metric.value == null) {
        sl.metric.value = '';
      }
    }
  }

  function isStructuredTemplate(tpl) {
    return (
      [
        'scenario_decision',
        'dos_donts',
        'process_steps',
        'role_scope_compare',
        'common_mistakes',
        'case_review',
      ].indexOf(String(tpl || '')) >= 0
    );
  }

  /** Initialise les champs propres aux modèles structurés (édition + import JSON). */
  function ensureStructuredSlideShape(sl) {
    if (!sl || typeof sl !== 'object') {
      return;
    }
    var t = String(sl.template || '');
    if (t === 'scenario_decision') {
      if (sl.context == null) {
        sl.context = '';
      }
      if (sl.situation == null) {
        sl.situation = '';
      }
      if (sl.explanation == null) {
        sl.explanation = '';
      }
      if (sl.correctOptionId == null) {
        sl.correctOptionId = '';
      }
      if (!Array.isArray(sl.options)) {
        sl.options = [];
      }
    } else if (t === 'dos_donts') {
      if (!Array.isArray(sl.dos)) {
        sl.dos = [];
      }
      if (!Array.isArray(sl.donts)) {
        sl.donts = [];
      }
      if (sl.synthesis == null) {
        sl.synthesis = '';
      }
    } else if (t === 'process_steps') {
      if (!Array.isArray(sl.steps)) {
        sl.steps = [];
      }
    } else if (t === 'role_scope_compare') {
      ['memberView', 'staffView', 'rightsNote', 'notAnomaly'].forEach(function (k) {
        if (sl[k] == null) {
          sl[k] = '';
        }
      });
    } else if (t === 'common_mistakes') {
      if (!Array.isArray(sl.mistakes)) {
        sl.mistakes = [];
      }
    } else if (t === 'case_review') {
      ['caseText', 'analysis', 'goodConduct', 'conclusion'].forEach(function (k) {
        if (sl[k] == null) {
          sl[k] = '';
        }
      });
    }
  }

  function structuredFieldsHtml(tpl, s) {
    tpl = String(tpl || '');
    if (tpl === 'scenario_decision') {
      var optsJson = '';
      try {
        optsJson = JSON.stringify(s.options || [], null, 2);
      } catch (e) {
        optsJson = '[]';
      }
      return (
        '<div data-struct-wrap class="mt-3 rounded-lg border border-sky-200 bg-sky-50/40 p-3 space-y-2">' +
        '<p class="text-[11px] font-bold text-sky-900">Champs du scénario</p>' +
        '<p class="text-[10px] text-slate-600">Options : tableau JSON <code class="bg-white px-1 rounded">id</code> + <code class="bg-white px-1 rounded">text</code> pour chaque proposition.</p>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Contexte (texte simple)</label>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-struct-sd-context value="' +
        escapeHtml(String(s.context || '')) +
        '" /></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Situation (HTML léger)</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-sd-situation>' +
        escapeHtml(String(s.situation || '')) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Identifiant de la bonne option (ex. a)</label>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-struct-sd-correct value="' +
        escapeHtml(String(s.correctOptionId || '')) +
        '" /></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Options (JSON)</label>' +
        '<textarea rows="6" class="w-full border rounded px-2 py-1 text-[10px] font-mono mt-0.5" data-struct-sd-options>' +
        escapeHtml(optsJson) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Explication (HTML léger)</label>' +
        '<textarea rows="4" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-sd-explain>' +
        escapeHtml(String(s.explanation || '')) +
        '</textarea></div></div>'
      );
    }
    if (tpl === 'dos_donts') {
      var dosTxt = Array.isArray(s.dos) ? s.dos.join('\n') : '';
      var dontsTxt = Array.isArray(s.donts) ? s.donts.join('\n') : '';
      return (
        '<div data-struct-wrap class="mt-3 rounded-lg border border-teal-200 bg-teal-50/30 p-3 space-y-2">' +
        '<p class="text-[11px] font-bold text-teal-900">À faire / à ne pas faire</p>' +
        '<div><label class="text-[10px] font-bold text-slate-600">À faire (une ligne par item)</label>' +
        '<textarea rows="5" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-struct-dd-dos>' +
        escapeHtml(dosTxt) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">À ne pas faire (une ligne par item)</label>' +
        '<textarea rows="5" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-struct-dd-donts>' +
        escapeHtml(dontsTxt) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Synthèse (HTML léger)</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-dd-syn>' +
        escapeHtml(String(s.synthesis || '')) +
        '</textarea></div></div>'
      );
    }
    if (tpl === 'process_steps') {
      var stepsJson = '';
      try {
        stepsJson = JSON.stringify(s.steps || [], null, 2);
      } catch (e2) {
        stepsJson = '[]';
      }
      return (
        '<div data-struct-wrap class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50/30 p-3 space-y-2">' +
        '<p class="text-[11px] font-bold text-indigo-900">Étapes (JSON)</p>' +
        '<p class="text-[10px] text-slate-600">Tableau d’objets : <code class="bg-white px-1 rounded">title</code>, <code class="bg-white px-1 rounded">action</code>, <code class="bg-white px-1 rounded">vigilance</code>.</p>' +
        '<textarea rows="10" class="w-full border rounded px-2 py-1 text-[10px] font-mono mt-0.5" data-struct-ps-steps>' +
        escapeHtml(stepsJson) +
        '</textarea></div>'
      );
    }
    if (tpl === 'role_scope_compare') {
      return (
        '<div data-struct-wrap class="mt-3 rounded-lg border border-violet-200 bg-violet-50/30 p-3 space-y-2">' +
        '<p class="text-[11px] font-bold text-violet-900">Comparaison des vues (HTML léger)</p>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Ce que voit un membre</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-rsc-mem>' +
        escapeHtml(String(s.memberView || '')) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Ce que voit le staff</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-rsc-stf>' +
        escapeHtml(String(s.staffView || '')) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Droits et rôle</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-rsc-rights>' +
        escapeHtml(String(s.rightsNote || '')) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Pas une anomalie</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-rsc-ok>' +
        escapeHtml(String(s.notAnomaly || '')) +
        '</textarea></div></div>'
      );
    }
    if (tpl === 'common_mistakes') {
      var mistJson = '';
      try {
        mistJson = JSON.stringify(s.mistakes || [], null, 2);
      } catch (e3) {
        mistJson = '[]';
      }
      return (
        '<div data-struct-wrap class="mt-3 rounded-lg border border-rose-200 bg-rose-50/30 p-3 space-y-2">' +
        '<p class="text-[11px] font-bold text-rose-900">Erreurs (JSON)</p>' +
        '<p class="text-[10px] text-slate-600">Tableau : <code class="bg-white px-1 rounded">error</code>, <code class="bg-white px-1 rounded">why</code>, <code class="bg-white px-1 rounded">consequence</code>, <code class="bg-white px-1 rounded">correction</code>.</p>' +
        '<textarea rows="12" class="w-full border rounded px-2 py-1 text-[10px] font-mono mt-0.5" data-struct-cm-mist>' +
        escapeHtml(mistJson) +
        '</textarea></div>'
      );
    }
    if (tpl === 'case_review') {
      return (
        '<div data-struct-wrap class="mt-3 rounded-lg border border-slate-300 bg-slate-50/50 p-3 space-y-2">' +
        '<p class="text-[11px] font-bold text-slate-800">Analyse de cas (HTML léger)</p>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Cas</label>' +
        '<textarea rows="4" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-cr-case>' +
        escapeHtml(String(s.caseText || '')) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Analyse</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-cr-an>' +
        escapeHtml(String(s.analysis || '')) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Bonne conduite</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-cr-good>' +
        escapeHtml(String(s.goodConduct || '')) +
        '</textarea></div>' +
        '<div><label class="text-[10px] font-bold text-slate-600">Conclusion</label>' +
        '<textarea rows="3" class="w-full border rounded px-2 py-1 text-xs font-mono mt-0.5" data-struct-cr-conc>' +
        escapeHtml(String(s.conclusion || '')) +
        '</textarea></div></div>'
      );
    }
    return '';
  }

  function defaultDeck() {
    return {
      version: 1,
      modals: [],
      opening: ensureOpening(null),
      closure: ensureClosure(null),
      slides: [
        {
          id: uid('slide'),
          template: 'title_hero',
          title: '',
          subtitle: '',
          body: '',
          imageUrl: '',
          imageCaption: '',
          fileUrl: '',
          fileLabel: '',
          resources: [],
          primaryAction: null,
          secondaryAction: null,
          contextKicker: '',
          surface: 'default',
          cards: [],
          insights: [],
          metric: null,
        },
      ],
    };
  }

  function parseDeck(raw) {
    if (!raw || !String(raw).trim()) return defaultDeck();
    try {
      var d = JSON.parse(raw);
      if (!d || !Array.isArray(d.slides) || d.slides.length === 0) return defaultDeck();
      if (!Array.isArray(d.modals)) {
        d.modals = [];
      }
      d.opening = ensureOpening(d.opening);
      d.closure = ensureClosure(d.closure);
      d.slides.forEach(function (sl) {
        if (!Array.isArray(sl.resources)) {
          sl.resources = [];
        }
        ensureSlideMissionFields(sl);
        ensureStructuredSlideShape(sl);
      });
      return d;
    } catch (e) {
      return defaultDeck();
    }
  }

  function escapeHtml(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  /** Aperçu : même esprit que training_canvas_sanitize_html côté serveur */
  function sanitizePreviewHtml(html) {
    if (!html) return '';
    var allowed = '<p><br><br/><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span><div>';
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  }

  function parseTimelinePreviewEvents(bodyRaw) {
    var t = String(bodyRaw || '').trim();
    if (!t) {
      return [];
    }
    try {
      var j = JSON.parse(t);
      if (Array.isArray(j)) {
        var out = [];
        j.forEach(function (row) {
          if (!row || typeof row !== 'object') {
            return;
          }
          var time = String(row.time || row.date || row.label || '').trim();
          var title = String(row.title || '').trim();
          var rawHtml = String(row.html || row.text || row.body || '').trim();
          if (!time && !title && !rawHtml) {
            return;
          }
          out.push({
            time: time,
            title: title,
            htmlSafe: rawHtml ? sanitizePreviewHtml(rawHtml) : '',
          });
        });
        return out;
      }
    } catch (e1) {
      /* texte libre */
    }
    return t
      .split(/\r\n|\r|\n/)
      .map(function (line) {
        line = line.trim();
        if (!line) {
          return null;
        }
        var parts = line.split('|');
        var time = (parts[0] || '').trim();
        var title = (parts[1] || '').trim();
        var rest = parts.length > 2 ? parts.slice(2).join('|').trim() : '';
        return {
          time: time,
          title: title,
          htmlSafe: rest ? sanitizePreviewHtml(rest) : '',
        };
      })
      .filter(Boolean);
  }

  function slideResourcesPreview(sl) {
    var r = [];
    var raw = sl.resources;
    if (Array.isArray(raw)) {
      raw.forEach(function (it) {
        if (!it || typeof it !== 'object') {
          return;
        }
        var u = String(it.url || '').trim();
        if (!u) {
          return;
        }
        var lab = String(it.title || '').trim() || 'Ressource';
        r.push({ title: lab, url: u });
      });
      return r;
    }
    var body = String((sl && sl.body) || '').trim();
    try {
      var j = JSON.parse(body);
      if (!Array.isArray(j)) {
        return r;
      }
      j.forEach(function (it) {
        if (!it || typeof it !== 'object') {
          return;
        }
        var u = String(it.url || it.href || '').trim();
        if (!u) {
          return;
        }
        var lab = String(it.title || it.label || '').trim() || 'Ressource';
        r.push({ title: lab, url: u });
      });
    } catch (e2) {
      /* ignore */
    }
    return r;
  }

  function denseBottomPreviewHtml(sl) {
    var out = '';
    var m = sl.metric;
    if (m && typeof m === 'object') {
      var ml = String(m.label || '').trim();
      var mv = String(m.value || '').trim();
      if (ml || mv) {
        out +=
          '<div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">' +
          '<p class="text-[8px] font-black uppercase tracking-wide text-slate-400">' +
          escapeHtml(ml || 'Indicateur') +
          '</p>' +
          '<p class="text-sm font-bold text-slate-900">' +
          escapeHtml(mv || '—') +
          '</p></div>';
      }
    }
    var cards = Array.isArray(sl.cards) ? sl.cards : [];
    if (cards.length) {
      var cells = '';
      cards.forEach(function (c) {
        if (!c || typeof c !== 'object') {
          return;
        }
        var lab = String(c.label || '').trim();
        var bod = String(c.body || '').trim();
        if (!lab && !bod) {
          return;
        }
        cells +=
          '<div class="rounded-lg border border-slate-200 bg-white p-2 text-[11px]">' +
          (lab ? '<p class="font-bold text-slate-600">' + escapeHtml(lab) + '</p>' : '') +
          (bod
            ? '<p class="text-slate-700 mt-1">' + escapeHtml(bod).replace(/\n/g, '<br/>') + '</p>'
            : '') +
          '</div>';
      });
      if (cells) {
        out += '<div class="mt-3 grid gap-2 md:grid-cols-3">' + cells + '</div>';
      }
    }
    var insights = Array.isArray(sl.insights) ? sl.insights.slice() : [];
    if (insights.length === 0 && sl.highlight && typeof sl.highlight === 'object') {
      insights = [sl.highlight];
    }
    if (insights.length) {
      var insHtml = '';
      insights.forEach(function (ins) {
        if (!ins || typeof ins !== 'object') {
          return;
        }
        var v = String(ins.variant || 'retain').toLowerCase();
        var title = String(ins.title || '').trim();
        var body = String(ins.body || '').trim();
        var defTitle = 'À retenir';
        var boxCls = 'border-emerald-200 bg-emerald-50 text-emerald-900';
        if (v === 'key' || v === 'point') {
          defTitle = 'Point clé';
          boxCls = 'border-sky-200 bg-sky-50 text-sky-950';
        } else if (v === 'vigilance' || v === 'warn') {
          defTitle = 'Vigilance';
          boxCls = 'border-amber-200 bg-amber-50 text-amber-950';
        } else if (v === 'result') {
          defTitle = 'Résultat attendu';
          boxCls = 'border-emerald-200 bg-emerald-50 text-emerald-950';
        }
        if (!title) {
          title = defTitle;
        }
        if (!title && !body) {
          return;
        }
        insHtml +=
          '<div class="rounded-lg border p-2 text-[11px] ' +
          boxCls +
          '">' +
          '<p class="font-bold mb-1">' +
          escapeHtml(title) +
          '</p>' +
          (body ? '<p>' + escapeHtml(body).replace(/\n/g, '<br/>') + '</p>' : '') +
          '</div>';
      });
      if (insHtml) {
        out += '<div class="mt-3 grid gap-2 md:grid-cols-3">' + insHtml + '</div>';
      }
    }
    return out;
  }

  function denseCardsRowsHtml(s) {
    if (!Array.isArray(s.cards)) {
      s.cards = [];
    }
    var rows = '';
    s.cards.forEach(function (c, idx) {
      var row = c && typeof c === 'object' ? c : { label: '', body: '' };
      rows +=
        '<div class="flex flex-wrap gap-2 mb-2 items-end border-t border-violet-100 pt-2" data-card-row="' +
        idx +
        '">' +
        '<div class="flex-1 min-w-[6rem]"><label class="text-[10px] text-slate-500">Titre court</label>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-card-lab value="' +
        escapeHtml(String(row.label || '')) +
        '" /></div>' +
        '<div class="w-full min-w-[12rem]"><label class="text-[10px] text-slate-500">Texte</label>' +
        '<textarea rows="2" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-card-body>' +
        escapeHtml(String(row.body || '')) +
        '</textarea></div>' +
        '<button type="button" class="text-xs text-rose-600 font-semibold underline shrink-0 h-8 px-1" data-card-rm>Retirer</button></div>';
    });
    return (
      '<div data-dense-cards-wrap class="mt-2">' +
      '<p class="text-[10px] font-bold text-slate-600 mb-1">Cartes (affichage en colonnes sur grand écran)</p>' +
      '<div data-dense-cards-rows>' +
      rows +
      '</div>' +
      '<button type="button" class="text-xs font-bold text-violet-700 underline" data-card-add>Ajouter une carte</button></div>'
    );
  }

  function denseInsightsRowsHtml(s) {
    if (!Array.isArray(s.insights)) {
      s.insights = [];
    }
    var rows = '';
    s.insights.forEach(function (ins, idx) {
      var row = ins && typeof ins === 'object' ? ins : { variant: 'retain', title: '', body: '' };
      var v = String(row.variant || 'retain');
      rows +=
        '<div class="flex flex-wrap gap-2 mb-2 items-end border-t border-violet-100 pt-2" data-ins-row="' +
        idx +
        '">' +
        '<div><label class="text-[10px] text-slate-500">Type</label>' +
        '<select class="border rounded px-2 py-1 text-xs block mt-0.5 min-w-[9rem]" data-ins-var>' +
        '<option value="retain"' +
        (v === 'retain' ? ' selected' : '') +
        '>À retenir</option>' +
        '<option value="key"' +
        (v === 'key' ? ' selected' : '') +
        '>Point clé</option>' +
        '<option value="vigilance"' +
        (v === 'vigilance' ? ' selected' : '') +
        '>Vigilance</option>' +
        '<option value="result"' +
        (v === 'result' ? ' selected' : '') +
        '>Résultat attendu</option>' +
        '</select></div>' +
        '<div class="flex-1 min-w-[8rem]"><label class="text-[10px] text-slate-500">Titre (optionnel)</label>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-ins-title value="' +
        escapeHtml(String(row.title || '')) +
        '" /></div>' +
        '<div class="w-full"><label class="text-[10px] text-slate-500">Texte</label>' +
        '<textarea rows="2" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-ins-body>' +
        escapeHtml(String(row.body || '')) +
        '</textarea></div>' +
        '<button type="button" class="text-xs text-rose-600 font-semibold underline shrink-0 h-8 px-1" data-ins-rm>Retirer</button></div>';
    });
    return (
      '<div data-dense-ins-wrap class="mt-2">' +
      '<p class="text-[10px] font-bold text-slate-600 mb-1">Encadrés d’insight</p>' +
      '<div data-dense-ins-rows>' +
      rows +
      '</div>' +
      '<button type="button" class="text-xs font-bold text-violet-700 underline" data-ins-add>Ajouter un encadré</button></div>'
    );
  }

  function renderSlidePreviewHtml(sl) {
    if (!sl) return '<p class="text-slate-400 text-sm">—</p>';
    var tpl = String(sl.template || 'title_hero');
    var slideBg =
      String(sl.surface || 'default') === 'elevated'
        ? 'bg-gradient-to-br from-slate-50 to-violet-50/40'
        : 'bg-white';
    var ck = String(sl.contextKicker || '').trim();
    var ckHtml = ck
      ? '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">' +
        escapeHtml(ck) +
        '</p>'
      : '';
    var title = escapeHtml(sl.title || '');
    var sub = escapeHtml(sl.subtitle || '');
    var bodyRaw = String(sl.body || '');
    var bodySafe = sanitizePreviewHtml(bodyRaw);
    var img = escapeHtml(sl.imageUrl || '');
    var cap = escapeHtml(sl.imageCaption || '');
    var fileUrl = escapeHtml(sl.fileUrl || '');
    var fileLabel = escapeHtml(sl.fileLabel || 'Fichier');

    function actionsHtml() {
      var out = '';
      ['primaryAction', 'secondaryAction'].forEach(function (key) {
        var act = sl[key];
        if (!act || !act.label) return;
        var cls =
          key === 'primaryAction'
            ? 'bg-violet-600 text-white'
            : 'border border-slate-300 text-slate-800 bg-white';
        out +=
          '<span class="inline-block px-4 py-2 rounded-xl text-xs font-bold ' +
          cls +
          ' pointer-events-none opacity-90">' +
          escapeHtml(act.label) +
          '</span> ';
      });
      return out ? '<div class="mt-6 flex flex-wrap gap-2">' + out + '</div>' : '';
    }

    if (tpl === 'scorm_sequence') {
      var raw = bodyRaw.trim();
      var steps = raw
        ? raw.split(/[|→\n]+/).map(function (x) {
            return x.trim();
          })
        : ['Brief', 'Slides', 'Knowledge check', 'Assessment', 'Certification'];
      steps = steps.filter(Boolean);
      if (steps.length === 0) steps = ['Brief', 'Slides', 'Assessment'];
      var strip = steps
        .map(function (st, k) {
          return (
            (k ? '<span class="text-slate-300 mx-1">→</span>' : '') +
            '<span class="text-slate-700">' +
            escapeHtml(st) +
            '</span>'
          );
        })
        .join('');
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">Déroulé type</p>' +
        '<div class="flex flex-wrap items-center gap-1 text-sm">' +
        strip +
        '</div>' +
        (title ? '<h2 class="text-xl font-black text-slate-900 mt-6">' + title + '</h2>' : '') +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'knowledge_check') {
      var lines = bodyRaw.split(/\r\n|\r|\n/).map(function (l) {
        return l.trim();
      });
      lines = lines.filter(Boolean);
      var items = lines.map(function (line) {
        return '<li class="text-sm text-slate-800 leading-relaxed">' + escapeHtml(line) + '</li>';
      });
      var listBody =
        items.length === 0
          ? '<p class="text-sm text-slate-500">Ajoutez des lignes dans le corps.</p>'
          : '<ul class="list-disc pl-5 space-y-2 max-w-3xl">' + items.join('') + '</ul>';
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">Repères</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-4">' + title + '</h2>' : '') +
        listBody +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'timeline') {
      var evs = parseTimelinePreviewEvents(bodyRaw);
      var items =
        evs.length === 0
          ? '<p class="text-sm text-slate-500">Ajoutez des étapes (liste JSON ou lignes date | titre | texte).</p>'
          : '<ol class="space-y-8">' +
            evs
              .map(function (ev, idx) {
                var isLast = idx === evs.length - 1;
                return (
                  '<li class="flex gap-4">' +
                  '<div class="flex flex-col items-center shrink-0 pt-1">' +
                  '<span class="h-3.5 w-3.5 rounded-full bg-violet-600 ring-4 ring-violet-100"></span>' +
                  (isLast
                    ? ''
                    : '<span class="w-0.5 flex-1 min-h-[1.25rem] bg-violet-200 mt-2"></span>') +
                  '</div>' +
                  '<div class="min-w-0 flex-1 pb-2">' +
                  '<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-1">' +
                  (ev.time
                    ? '<span class="text-xs font-black uppercase tracking-wider text-violet-700">' +
                      escapeHtml(ev.time) +
                      '</span>'
                    : '') +
                  (ev.title
                    ? '<span class="text-base font-black text-slate-900">' + escapeHtml(ev.title) + '</span>'
                    : '') +
                  '</div>' +
                  (ev.htmlSafe
                    ? '<div class="prose prose-slate prose-sm max-w-none text-slate-700">' +
                      escapeHtml(ev.htmlSafe) +
                      '</div>'
                    : '') +
                  '</div></li>'
                );
              })
              .join('') +
            '</ol>';
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-4">Frise chronologique</p>' +
        (title ? '<h2 class="text-xl font-black text-slate-900 mb-6">' + title + '</h2>' : '') +
        items +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'reading_article') {
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<div class="max-w-3xl mx-auto">' +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700/90 mb-3">Article de lecture</p>' +
        (title
          ? '<h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-tight mb-3">' +
            title +
            '</h2>'
          : '') +
        (sub
          ? '<p class="text-lg text-slate-600 font-medium mb-8 border-b border-slate-200 pb-6">' + sub + '</p>'
          : '') +
        (bodyRaw
          ? '<div class="lms-reading-article prose prose-slate prose-lg max-w-none text-slate-800 leading-relaxed">' +
            bodyRaw +
            '</div>'
          : '') +
        '</div>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'fill_blanks') {
      var fbPrev = bodyRaw.replace(/\[\[([^\]]{1,200})\]\]/g, function (_, w) {
        return (
          '<span class="inline-block min-w-[4rem] border-b-2 border-violet-300 bg-violet-50/80 px-1 mx-0.5 rounded-t text-sm text-violet-900">' +
          escapeHtml(String(w).trim()) +
          '</span>'
        );
      });
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-amber-700/90 mb-3">Texte à trous</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-4">' + title + '</h2>' : '') +
        (fbPrev
          ? '<div class="prose prose-slate max-w-none text-slate-800 text-base leading-relaxed">' + fbPrev + '</div>'
          : '<p class="text-sm text-slate-500">Saisissez du texte avec des segments [[réponse]].</p>') +
        '<p class="text-xs text-slate-500 mt-4">Côté apprenant, des champs vérifient les réponses avant le passage à l’étape suivante.</p>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'resources_list') {
      var res = slideResourcesPreview(sl);
      var intro = bodyRaw
        ? '<div class="prose prose-slate prose-sm max-w-none text-slate-600 mb-6">' + bodyRaw + '</div>'
        : '';
      var lis =
        res.length === 0
          ? '<p class="text-sm text-slate-500">Ajoutez des liens ci-contre (liste de ressources).</p>'
          : '<ul class="space-y-2 max-w-2xl">' +
            res
              .map(function (rr) {
                return (
                  '<li class="text-sm text-slate-800">' +
                  '<span class="font-semibold text-violet-800">' +
                  escapeHtml(rr.title) +
                  '</span>' +
                  '<span class="text-slate-500"> — lien dans l’aperçu public</span></li>'
                );
              })
              .join('') +
            '</ul>';
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-400 mb-3">Ressources</p>' +
        (title ? '<h2 class="text-xl font-black text-slate-900 mb-2">' + title + '</h2>' : '') +
        (sub ? '<p class="text-sm text-violet-700 font-semibold mb-4">' + sub + '</p>' : '') +
        intro +
        lis +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'scenario_decision') {
      var ctx = escapeHtml(String(sl.context || '').slice(0, 400));
      var optsP =
        Array.isArray(sl.options) && sl.options.length
          ? sl.options
              .map(function (o) {
                if (!o || typeof o !== 'object') {
                  return '';
                }
                return (
                  '<li class="text-xs text-slate-800 border border-slate-200 rounded px-2 py-1">' +
                  escapeHtml(String(o.id || '')) +
                  '. ' +
                  escapeHtml(String(o.text || '').slice(0, 200)) +
                  '</li>'
                );
              })
              .filter(Boolean)
              .join('')
          : '';
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-sky-700/90 mb-2">Mise en situation</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-3">' + title + '</h2>' : '') +
        (ctx ? '<p class="text-xs text-slate-600 mb-2"><strong>Contexte</strong> — ' + ctx + '</p>' : '') +
        (optsP ? '<ul class="space-y-1 mb-2">' + optsP + '</ul>' : '<p class="text-xs text-slate-500">Définissez le contexte et les options.</p>') +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }
    if (tpl === 'dos_donts') {
      var d1 = Array.isArray(sl.dos) ? sl.dos : [];
      var d2 = Array.isArray(sl.donts) ? sl.donts : [];
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-teal-800/90 mb-2">À faire / à ne pas faire</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-3">' + title + '</h2>' : '') +
        '<div class="grid grid-cols-2 gap-2 text-[11px]">' +
        '<div class="border border-emerald-200 rounded p-2 bg-emerald-50/50"><strong>À faire</strong><br>' +
        escapeHtml(d1.slice(0, 4).join(' · ') || '—') +
        '</div>' +
        '<div class="border border-rose-200 rounded p-2 bg-rose-50/50"><strong>À ne pas faire</strong><br>' +
        escapeHtml(d2.slice(0, 4).join(' · ') || '—') +
        '</div></div>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }
    if (tpl === 'process_steps') {
      var stL = Array.isArray(sl.steps) ? sl.steps.length : 0;
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-indigo-800/90 mb-2">Procédure</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-2">' + title + '</h2>' : '') +
        '<p class="text-xs text-slate-600">' +
        (stL ? stL + ' étape(s) définie(s) en JSON.' : 'Ajoutez le tableau d’étapes.') +
        '</p>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }
    if (tpl === 'role_scope_compare') {
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-violet-800/90 mb-2">Membre / staff</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-2">' + title + '</h2>' : '') +
        '<p class="text-xs text-slate-600">Quatre blocs HTML (membre, staff, droits, pas une anomalie).</p>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }
    if (tpl === 'common_mistakes') {
      var mc = Array.isArray(sl.mistakes) ? sl.mistakes.length : 0;
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-rose-800/90 mb-2">Erreurs fréquentes</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-2">' + title + '</h2>' : '') +
        '<p class="text-xs text-slate-600">' +
        (mc ? mc + ' fiche(s) en JSON.' : 'Ajoutez le tableau d’erreurs.') +
        '</p>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }
    if (tpl === 'case_review') {
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<p class="text-[10px] font-black uppercase tracking-[0.35em] text-slate-600 mb-2">Analyse de cas</p>' +
        (title ? '<h2 class="text-lg font-black text-slate-900 mb-2">' + title + '</h2>' : '') +
        '<p class="text-xs text-slate-600">Cas, analyse, bonne conduite, conclusion (HTML).</p>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'quote') {
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<blockquote class="text-lg md:text-xl font-serif italic text-slate-800 border-l-4 border-violet-500 pl-6">' +
        escapeHtml(bodySafe) +
        '</blockquote>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'image_full' && img) {
      return (
        '<div class="p-2 ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<figure class="space-y-2">' +
        '<img src="' +
        img +
        '" alt="" class="w-full rounded-lg object-cover max-h-[320px] bg-slate-100" loading="lazy" />' +
        (cap ? '<figcaption class="text-xs text-slate-500 text-center">' + cap + '</figcaption>' : '') +
        '</figure>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    if (tpl === 'split_text_image' && img) {
      return (
        '<div class="p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
        ckHtml +
        '<div class="grid md:grid-cols-2 gap-6 items-start">' +
        '<div class="prose prose-sm max-w-none text-slate-700">' +
        (title ? '<h2 class="text-xl font-black text-slate-900 mb-2">' + title + '</h2>' : '') +
        '<div class="lms-canvas-prev-body">' +
        bodyRaw +
        '</div></div>' +
        '<figure><img src="' +
        img +
        '" class="w-full rounded-xl object-cover bg-slate-100" alt="" loading="lazy" />' +
        (cap ? '<figcaption class="text-xs text-slate-500 mt-2">' + cap + '</figcaption>' : '') +
        '</figure></div>' +
        actionsHtml() +
        denseBottomPreviewHtml(sl) +
        '</div>'
      );
    }

    var block =
      '<div class="space-y-4 p-5 md:p-8 min-h-[220px] ' + slideBg + ' rounded-xl border border-slate-100 shadow-inner">' +
      ckHtml +
      (title ? '<h2 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">' + title + '</h2>' : '') +
      (sub ? '<p class="text-lg text-violet-700 font-semibold">' + sub + '</p>' : '') +
      (bodyRaw
        ? '<div class="prose prose-sm max-w-none text-slate-700">' + bodyRaw + '</div>'
        : '') +
      (img && tpl !== 'split_text_image'
        ? '<figure class="mt-4"><img src="' +
          img +
          '" class="w-full max-h-72 rounded-xl object-cover bg-slate-100" alt="" loading="lazy" />' +
          (cap ? '<figcaption class="text-xs text-slate-500 mt-2">' + cap + '</figcaption>' : '') +
          '</figure>'
        : '') +
      (fileUrl
        ? '<div class="mt-4 p-4 rounded-xl bg-slate-100 border border-slate-200 flex flex-wrap justify-between gap-3 items-center">' +
          '<span class="text-sm font-semibold text-slate-800">' +
          fileLabel +
          '</span>' +
          '<span class="text-xs font-bold text-emerald-700">↗ Aperçu lien</span></div>'
        : '') +
      actionsHtml() +
      denseBottomPreviewHtml(sl) +
      '</div>';
    return block;
  }

  function LmsCanvasEditor(container) {
    this.container = container;
    this.textarea = container.querySelector('[data-lms-canvas-json]');
    if (!this.textarea) {
      this.invalid = true;
      return;
    }
    this.uiRoot = container.querySelector('[data-lms-canvas-root]');
    if (!this.uiRoot) {
      this.uiRoot = document.createElement('div');
      this.uiRoot.setAttribute('data-lms-canvas-root', '');
      this.textarea.insertAdjacentElement('afterend', this.uiRoot);
    }
    this.deck = parseDeck(this.textarea.value);
    this.activeSlide = 0;
    this._sortableStrip = null;
    this._previewTimer = null;
    this._build();
  }

  LmsCanvasEditor.prototype.sync = function () {
    this.textarea.value = JSON.stringify(this.deck, null, 0);
  };

  LmsCanvasEditor.prototype._slide = function () {
    return this.deck.slides[this.activeSlide];
  };

  LmsCanvasEditor.prototype._renderMissionPanel = function () {
    var self = this;
    var host = this._missionEl;
    if (!host) {
      return;
    }
    this.deck.opening = ensureOpening(this.deck.opening);
    this.deck.closure = ensureClosure(this.deck.closure);
    var op = this.deck.opening;
    var cl = this.deck.closure;

    var statsRows = '';
    op.stats.forEach(function (st, idx) {
      var row = st && typeof st === 'object' ? st : { label: '', value: '' };
      statsRows +=
        '<div class="flex flex-wrap gap-2 mb-2 items-end" data-stat-row="' +
        idx +
        '">' +
        '<div class="flex-1 min-w-[6rem]"><label class="text-[10px] text-slate-500">Libellé</label>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-stat-lab value="' +
        escapeHtml(String(row.label || '')) +
        '" /></div>' +
        '<div class="flex-1 min-w-[6rem]"><label class="text-[10px] text-slate-500">Valeur affichée</label>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-stat-val value="' +
        escapeHtml(String(row.value || '')) +
        '" /></div>' +
        '<button type="button" class="text-xs text-rose-600 font-semibold underline h-8 shrink-0 px-1" data-stat-rm>Retirer</button></div>';
    });

    host.innerHTML =
      '<details class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 open:bg-white open:shadow-sm">' +
      '<summary class="cursor-pointer text-xs font-black uppercase tracking-wider text-slate-600 select-none">Bandeau d’ouverture</summary>' +
      '<p class="text-[11px] text-slate-500 mt-2 mb-3">Textes du haut de page pour les parcours visuels. Champs vides : le site propose des libellés adaptés à la leçon.</p>' +
      '<div class="grid gap-3">' +
      '<div><label class="text-[10px] font-bold text-slate-600">Sur-titre</label>' +
      '<input type="text" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-mis-op-eyebrow value="' +
      escapeHtml(String(op.eyebrow || '')) +
      '" /></div>' +
      '<div><label class="text-[10px] font-bold text-slate-600">Titre</label>' +
      '<input type="text" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-mis-op-title value="' +
      escapeHtml(String(op.title || '')) +
      '" /></div>' +
      '<div><label class="text-[10px] font-bold text-slate-600">Accroche</label>' +
      '<textarea rows="2" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-mis-op-lead>' +
      escapeHtml(String(op.lead || '')) +
      '</textarea></div>' +
      '<div><p class="text-[10px] font-bold text-slate-600 mb-1">Vignettes chiffres (optionnel)</p>' +
      '<div data-mis-op-stats-rows>' +
      statsRows +
      '</div>' +
      '<button type="button" class="mt-1 text-xs font-bold text-violet-700 underline" data-mis-op-stat-add>Ajouter une vignette</button></div>' +
      '</div></details>' +
      '<details class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 mt-3 open:bg-white open:shadow-sm">' +
      '<summary class="cursor-pointer text-xs font-black uppercase tracking-wider text-slate-600 select-none">Synthèse de fin</summary>' +
      '<p class="text-[11px] text-slate-500 mt-2 mb-3">Résumé affiché après le défilement des étapes, avant les boutons de navigation.</p>' +
      '<div class="grid gap-3">' +
      '<div><label class="text-[10px] font-bold text-slate-600">Titre du bloc</label>' +
      '<input type="text" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-mis-cl-title value="' +
      escapeHtml(String(cl.title || '')) +
      '" /></div>' +
      '<div><label class="text-[10px] font-bold text-slate-600">Ce qui a été parcouru (une ligne par point)</label>' +
      '<textarea rows="3" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-mis-cl-seen>' +
      escapeHtml((cl.seen || []).join('\n')) +
      '</textarea></div>' +
      '<div><label class="text-[10px] font-bold text-slate-600">Ce que vous retenez (une ligne par point)</label>' +
      '<textarea rows="3" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-mis-cl-acq>' +
      escapeHtml((cl.acquired || []).join('\n')) +
      '</textarea></div>' +
      '<div><label class="text-[10px] font-bold text-slate-600">Pour la suite</label>' +
      '<textarea rows="2" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-mis-cl-hint>' +
      escapeHtml(String(cl.nextHint || '')) +
      '</textarea></div>' +
      '</div></details>';

    function linesToArr(ta) {
      return String(ta.value || '')
        .split(/\r\n|\r|\n/)
        .map(function (x) {
          return x.trim();
        })
        .filter(Boolean);
    }

    host.querySelector('[data-mis-op-eyebrow]').addEventListener('input', function (e) {
      op.eyebrow = e.target.value;
      self.sync();
    });
    host.querySelector('[data-mis-op-title]').addEventListener('input', function (e) {
      op.title = e.target.value;
      self.sync();
    });
    host.querySelector('[data-mis-op-lead]').addEventListener('input', function (e) {
      op.lead = e.target.value;
      self.sync();
    });
    host.querySelector('[data-mis-cl-title]').addEventListener('input', function (e) {
      cl.title = e.target.value;
      self.sync();
    });
    host.querySelector('[data-mis-cl-seen]').addEventListener('input', function (e) {
      cl.seen = linesToArr(e.target);
      self.sync();
    });
    host.querySelector('[data-mis-cl-acq]').addEventListener('input', function (e) {
      cl.acquired = linesToArr(e.target);
      self.sync();
    });
    host.querySelector('[data-mis-cl-hint]').addEventListener('input', function (e) {
      cl.nextHint = e.target.value;
      self.sync();
    });

    function bindStatRows() {
      host.querySelectorAll('[data-stat-row]').forEach(function (rowEl) {
        var idx = parseInt(rowEl.getAttribute('data-stat-row'), 10);
        if (isNaN(idx) || !op.stats[idx]) {
          return;
        }
        var labIn = rowEl.querySelector('[data-stat-lab]');
        var valIn = rowEl.querySelector('[data-stat-val]');
        var rm = rowEl.querySelector('[data-stat-rm]');
        if (labIn) {
          labIn.addEventListener('input', function (e) {
            op.stats[idx].label = e.target.value;
            self.sync();
          });
        }
        if (valIn) {
          valIn.addEventListener('input', function (e) {
            op.stats[idx].value = e.target.value;
            self.sync();
          });
        }
        if (rm) {
          rm.addEventListener('click', function () {
            op.stats.splice(idx, 1);
            self.sync();
            self._renderMissionPanel();
          });
        }
      });
    }
    bindStatRows();

    var addStat = host.querySelector('[data-mis-op-stat-add]');
    if (addStat) {
      addStat.addEventListener('click', function () {
        op.stats.push({ label: '', value: '' });
        self.sync();
        self._renderMissionPanel();
      });
    }
  };

  LmsCanvasEditor.prototype.schedulePreview = function () {
    var self = this;
    if (this._previewTimer) clearTimeout(this._previewTimer);
    this._previewTimer = setTimeout(function () {
      self._renderPreview();
    }, PREVIEW_DEBOUNCE_MS);
  };

  LmsCanvasEditor.prototype._build = function () {
    var self = this;
    var c = this.uiRoot;
    c.innerHTML = '';
    this.container.classList.add('lms-canvas-editor');

    var top = document.createElement('div');
    top.className =
      'lms-canvas-editor__top flex flex-wrap items-center justify-between gap-3 mb-4 p-3 rounded-xl bg-gradient-to-r from-violet-50 to-white border border-violet-100';
    top.innerHTML =
      '<div class="flex items-center gap-2">' +
      '<span class="text-xs font-black uppercase tracking-wider text-violet-800">Éditeur visuel</span>' +
      '<span class="text-[10px] text-slate-500 hidden sm:inline">Aperçu mis à jour en direct</span></div>' +
      '<div class="flex flex-wrap gap-2">' +
      '<button type="button" class="lms-canvas-add-slide px-3 py-1.5 text-xs font-bold rounded-lg bg-violet-600 text-white hover:bg-violet-700 shadow-sm">+ Slide</button>' +
      '<button type="button" class="lms-canvas-dup-slide px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-300 hover:bg-slate-50">Dupliquer</button>' +
      '<button type="button" class="lms-canvas-del-slide px-3 py-1.5 text-xs font-bold rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50">Supprimer</button>' +
      '</div>';
    this.uiRoot.appendChild(top);

    var mission = document.createElement('div');
    mission.setAttribute('data-lms-mission-panel', '');
    mission.className = 'mb-4 space-y-3';
    this.uiRoot.appendChild(mission);
    this._missionEl = mission;

    var strip = document.createElement('div');
    strip.setAttribute('data-lms-slide-strip', '');
    strip.className =
      'lms-canvas-editor__strip flex gap-2 overflow-x-auto pb-2 mb-4 border-b border-slate-200 min-h-[3rem]';
    strip.setAttribute('aria-label', 'Slides — glisser pour réordonner');
    this.uiRoot.appendChild(strip);

    var mainRow = document.createElement('div');
    mainRow.className = 'grid grid-cols-1 xl:grid-cols-12 gap-5 items-start';
    this.uiRoot.appendChild(mainRow);

    var editorCol = document.createElement('div');
    editorCol.className = 'space-y-3 min-w-0 xl:col-span-5';
    editorCol.setAttribute('data-lms-canvas-fields', '');

    var previewWrap = document.createElement('div');
    previewWrap.className =
      'lms-canvas-editor-preview-wrap xl:col-span-4 xl:sticky xl:top-4 space-y-3 min-w-0 order-first xl:order-none';
    previewWrap.innerHTML =
      '<div class="flex items-center justify-between gap-2">' +
      '<span class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-700">Aperçu apprenant</span>' +
      '<span class="text-[10px] text-slate-400">Slide active</span></div>' +
      '<div class="lms-canvas-editor-preview rounded-2xl border-2 border-dashed border-emerald-200/80 bg-slate-50/90 overflow-hidden shadow-lg shadow-slate-900/5" data-lms-live-preview></div>';

    var modCol = document.createElement('div');
    modCol.className = 'rounded-xl border border-slate-200 bg-white p-4 space-y-3 xl:col-span-3';
    modCol.innerHTML =
      '<h4 class="text-xs font-black uppercase text-slate-500">Modales</h4>' +
      '<p class="text-[11px] text-slate-500">Boutons d’action « Modale » dans les slides.</p>' +
      '<div data-lms-modal-list></div>' +
      '<button type="button" class="lms-canvas-add-modal w-full px-3 py-2 text-xs font-bold rounded-lg border border-dashed border-slate-300 hover:bg-slate-50">+ Modale</button>';

    mainRow.appendChild(editorCol);
    mainRow.appendChild(previewWrap);
    mainRow.appendChild(modCol);

    this._stripEl = strip;
    this._fieldsEl = editorCol;
    this._previewEl = previewWrap.querySelector('[data-lms-live-preview]');
    this._modalListEl = modCol.querySelector('[data-lms-modal-list]');

    top.querySelector('.lms-canvas-add-slide').addEventListener('click', function () {
      self.deck.slides.push({
        id: uid('slide'),
        template: 'title_hero',
        title: '',
        subtitle: '',
        body: '',
        imageUrl: '',
        imageCaption: '',
        fileUrl: '',
        fileLabel: '',
        resources: [],
        primaryAction: null,
        secondaryAction: null,
        contextKicker: '',
        surface: 'default',
        cards: [],
        insights: [],
        metric: null,
      });
      self.activeSlide = self.deck.slides.length - 1;
      self.sync();
      self._renderAll();
    });
    top.querySelector('.lms-canvas-dup-slide').addEventListener('click', function () {
      var s = JSON.parse(JSON.stringify(self._slide()));
      s.id = uid('slide');
      self.deck.slides.splice(self.activeSlide + 1, 0, s);
      self.activeSlide++;
      self.sync();
      self._renderAll();
    });
    top.querySelector('.lms-canvas-del-slide').addEventListener('click', function () {
      if (self.deck.slides.length < 2) return;
      if (!confirm('Supprimer cette slide ?')) return;
      self.deck.slides.splice(self.activeSlide, 1);
      self.activeSlide = Math.min(self.activeSlide, self.deck.slides.length - 1);
      self.sync();
      self._renderAll();
    });

    modCol.querySelector('.lms-canvas-add-modal').addEventListener('click', function () {
      if (!self.deck.modals) self.deck.modals = [];
      self.deck.modals.push({ id: uid('modal'), title: 'Nouvelle modale', body: '<p>Contenu…</p>' });
      self.sync();
      self._renderModals();
    });

    var adv = document.createElement('details');
    adv.className = 'mt-4 border border-slate-200 rounded-xl bg-slate-50/80 open:shadow-inner';
    var sum = document.createElement('summary');
    sum.className = 'cursor-pointer px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-600 select-none';
    sum.textContent = 'Mode avancé — JSON brut (copier / coll / scripts)';
    adv.appendChild(sum);
    var taNote = document.createElement('p');
    taNote.className = 'px-4 pb-2 text-[11px] text-slate-500';
    taNote.textContent = 'Réservé aux ajustements techniques. L’éditeur visuel reste la source principale.';
    adv.appendChild(taNote);
    this.textarea.classList.remove('hidden');
    this.textarea.className =
      'w-full font-mono text-[11px] leading-relaxed border-0 border-t border-slate-200 bg-white p-4 min-h-[140px] focus:ring-0 focus:outline-none';
    adv.appendChild(this.textarea);
    this.uiRoot.appendChild(adv);

    this.textarea.addEventListener('change', function () {
      try {
        var d = JSON.parse(self.textarea.value);
        if (d && Array.isArray(d.slides) && d.slides.length > 0) {
          self.deck = d;
          if (!Array.isArray(self.deck.modals)) {
            self.deck.modals = [];
          }
          self.deck.opening = ensureOpening(self.deck.opening);
          self.deck.closure = ensureClosure(self.deck.closure);
          self.deck.slides.forEach(function (sl) {
            if (!Array.isArray(sl.resources)) {
              sl.resources = [];
            }
            ensureSlideMissionFields(sl);
          });
          self.activeSlide = Math.min(self.activeSlide, self.deck.slides.length - 1);
          self._renderMissionPanel();
          self._renderAll();
        }
      } catch (err) {
        /* JSON invalide : laisser l’utilisateur corriger */
      }
    });

    this._renderMissionPanel();
    this._renderAll();
  };

  LmsCanvasEditor.prototype._renderPreview = function () {
    if (!this._previewEl) return;
    var s = this._slide();
    this._previewEl.innerHTML = renderSlidePreviewHtml(s);
  };

  LmsCanvasEditor.prototype._renderAll = function () {
    this._renderStrip();
    this._renderFields();
    this._renderModals();
    this._renderPreview();
    this.sync();
  };

  LmsCanvasEditor.prototype._renderStrip = function () {
    var self = this;
    var strip = this._stripEl;
    if (this._sortableStrip && window.Sortable && typeof this._sortableStrip.destroy === 'function') {
      this._sortableStrip.destroy();
      this._sortableStrip = null;
    }
    strip.innerHTML = '';
    this.deck.slides.forEach(function (sl, i) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.draggable = false;
      btn.setAttribute('data-slide-index', String(i));
      btn.className =
        'lms-canvas-strip-item flex-shrink-0 min-w-[5.5rem] px-2 py-2 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 ' +
        (i === self.activeSlide ? 'border-violet-600 bg-violet-50 text-violet-900 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50');
      btn.innerHTML =
        '<span class="text-slate-400 cursor-grab select-none" title="Glisser pour réordonner" data-lms-drag-hold>⠿</span>' +
        '<span>' +
        (i + 1) +
        '</span>';
      btn.title = (sl.title || 'Slide ' + (i + 1)) + ' — glisser pour réordonner';
      btn.addEventListener('click', function (e) {
        if (e.target && e.target.getAttribute && e.target.getAttribute('data-lms-drag-hold')) return;
        self.activeSlide = i;
        self._renderStrip();
        self._renderFields();
        self._renderPreview();
      });
      strip.appendChild(btn);
    });

    if (window.Sortable && strip.children.length > 0) {
      this._sortableStrip = new window.Sortable(strip, {
        animation: 160,
        handle: '[data-lms-drag-hold]',
        draggable: '.lms-canvas-strip-item',
        ghostClass: 'opacity-50',
        onEnd: function (evt) {
          var moved = self.deck.slides.splice(evt.oldIndex, 1)[0];
          self.deck.slides.splice(evt.newIndex, 0, moved);
          self.activeSlide = evt.newIndex;
          self.sync();
          self._renderStrip();
          self._renderFields();
          self._renderPreview();
        },
      });
    }
  };

  LmsCanvasEditor.prototype._modalOptions = function () {
    var opts = '<option value="">—</option>';
    (this.deck.modals || []).forEach(function (m) {
      opts += '<option value="' + escapeHtml(m.id) + '">' + escapeHtml(m.title || m.id) + '</option>';
    });
    return opts;
  };

  LmsCanvasEditor.prototype._resourcesEditorHtml = function (s) {
    if (!Array.isArray(s.resources)) {
      s.resources = [];
    }
    var rows = '';
    s.resources.forEach(function (row, idx) {
      rows +=
        '<div class="flex flex-wrap gap-2 mb-2 items-end" data-res-row="' +
        idx +
        '">' +
        '<div class="flex-1 min-w-[8rem]"><label class="text-[10px] text-slate-500">Titre affiché</label><input type="text" class="w-full border rounded px-2 py-1 text-xs" data-res-title value="' +
        escapeHtml(row.title || '') +
        '" /></div>' +
        '<div class="flex-[2] min-w-[12rem]"><label class="text-[10px] text-slate-500">Adresse du lien</label><input type="text" class="w-full border rounded px-2 py-1 text-xs" data-res-url placeholder="https://…" value="' +
        escapeHtml(row.url || '') +
        '" /></div>' +
        '<button type="button" class="text-xs text-rose-600 font-semibold underline px-1 h-8 shrink-0" data-res-remove>Retirer</button></div>';
    });
    return (
      '<div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50/40 p-3" data-lms-resources-wrap>' +
      '<p class="text-[11px] font-bold text-slate-700 mb-2">Liens affichés sous l’introduction</p>' +
      '<div data-lms-resources-rows>' +
      rows +
      '</div>' +
      '<button type="button" class="mt-2 text-xs font-bold text-emerald-800 underline" data-lms-res-add>Ajouter un lien</button></div>'
    );
  };

  LmsCanvasEditor.prototype._bindResourcesEditor = function (s, el) {
    var wrap = el.querySelector('[data-lms-resources-wrap]');
    if (!wrap) {
      return;
    }
    var self = this;
    if (!Array.isArray(s.resources)) {
      s.resources = [];
    }
    wrap.querySelectorAll('[data-res-row]').forEach(function (rowEl) {
      var idx = parseInt(rowEl.getAttribute('data-res-row'), 10);
      if (isNaN(idx)) {
        return;
      }
      var tInp = rowEl.querySelector('[data-res-title]');
      var uInp = rowEl.querySelector('[data-res-url]');
      var rm = rowEl.querySelector('[data-res-remove]');
      if (tInp) {
        tInp.addEventListener('input', function (e) {
          if (!s.resources[idx]) {
            s.resources[idx] = { title: '', url: '' };
          }
          s.resources[idx].title = e.target.value;
          self.sync();
          self.schedulePreview();
        });
      }
      if (uInp) {
        uInp.addEventListener('input', function (e) {
          if (!s.resources[idx]) {
            s.resources[idx] = { title: '', url: '' };
          }
          s.resources[idx].url = e.target.value;
          self.sync();
          self.schedulePreview();
        });
      }
      if (rm) {
        rm.addEventListener('click', function () {
          s.resources.splice(idx, 1);
          self.sync();
          self._renderFields();
          self.schedulePreview();
        });
      }
    });
    var addBtn = wrap.querySelector('[data-lms-res-add]');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        s.resources.push({ title: '', url: '' });
        self.sync();
        self._renderFields();
        self.schedulePreview();
      });
    }
  };

  LmsCanvasEditor.prototype._renderFields = function () {
    var self = this;
    var s = this._slide();
    if (!s) return;
    ensureSlideMissionFields(s);
    if (!Array.isArray(s.resources)) {
      s.resources = [];
    }
    var tpl = String(s.template || 'title_hero');
    var bodyLabel = 'Corps (HTML léger)';
    var bodyRows = 6;
    var resourcesSection = '';
    var readingFormatHelp = '';
    if (tpl === 'timeline') {
      bodyLabel =
        'Frise — liste d’étapes au format JSON, ou une ligne par étape : date | titre | texte';
      bodyRows = 10;
    } else if (tpl === 'fill_blanks') {
      bodyLabel =
        'Texte à trous — encadrez chaque réponse attendue avec des doubles crochets, par exemple « mot » → [[mot]]';
      bodyRows = 8;
    } else if (tpl === 'reading_article') {
      bodyLabel = 'Corps de l’article (mise en forme simple)';
      bodyRows = 12;
      var sampleReading =
        '<div class="lms-reading-callout lms-reading-callout--info">\n' +
        '<p>Votre texte avec <code>mot</code> en ligne.</p>\n' +
        '</div>\n\n' +
        '<pre class="lms-reading-code"><code>' +
        '<span class="lms-reading-hl-kw">for</span> <span class="lms-reading-hl-var">x</span> <span class="lms-reading-hl-kw">in</span> <span class="lms-reading-hl-fn">range</span>(3):\n' +
        '    <span class="lms-reading-hl-fn">print</span>(<span class="lms-reading-hl-var">x</span>)' +
        '</code></pre>\n\n' +
        '<pre class="lms-reading-terminal">Sortie ligne 1\nSortie ligne 2</pre>';
      readingFormatHelp =
        '<details class="mt-2 rounded-lg border border-slate-200 bg-slate-50/90 p-3 text-[11px] text-slate-600">' +
        '<summary class="cursor-pointer font-bold text-slate-700 select-none">Mise en forme avancée (optionnel)</summary>' +
        '<p class="mt-2 mb-2">Encadré d’information, extrait sur fond sombre, zone type console. Copiez le modèle puis adaptez-le.</p>' +
        '<pre class="mt-1 p-2 rounded bg-white border border-slate-200 text-[10px] leading-relaxed overflow-x-auto whitespace-pre-wrap font-mono max-h-56 overflow-y-auto">' +
        escapeHtml(sampleReading) +
        '</pre>' +
        '</details>';
    } else if (tpl === 'resources_list') {
      bodyLabel = 'Texte d’introduction (optionnel, mise en forme simple)';
      bodyRows = 4;
      resourcesSection = self._resourcesEditorHtml(s);
    } else if (isStructuredTemplate(tpl)) {
      bodyLabel = 'Corps optionnel (HTML léger — souvent inutilisé pour ce modèle)';
      bodyRows = 3;
    }
    var structSection = structuredFieldsHtml(tpl, s);
    var el = this._fieldsEl;
    var tplOpts = TEMPLATES.map(function (t) {
      return '<option value="' + t.id + '"' + (s.template === t.id ? ' selected' : '') + '>' + escapeHtml(t.label) + '</option>';
    }).join('');

    function actionBlock(name, act, label) {
      act = act || { label: '', type: 'link', url: '', modalId: '' };
      return (
        '<div class="rounded-lg border border-slate-100 p-3 bg-slate-50/80">' +
        '<p class="text-[11px] font-bold text-slate-600 mb-2">' +
        label +
        '</p>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mb-2" placeholder="Libellé du bouton" data-act-field="' +
        name +
        '-label" value="' +
        escapeHtml(act.label || '') +
        '" />' +
        '<select class="w-full border rounded px-2 py-1 text-xs mb-2" data-act-field="' +
        name +
        '-type">' +
        '<option value="link"' +
        (act.type === 'link' ? ' selected' : '') +
        '>Lien externe</option>' +
        '<option value="modal"' +
        (act.type === 'modal' ? ' selected' : '') +
        '>Modale</option>' +
        '</select>' +
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs mb-2" placeholder="URL (si lien)" data-act-field="' +
        name +
        '-url" value="' +
        escapeHtml(act.url || '') +
        '" />' +
        '<select class="w-full border rounded px-2 py-1 text-xs" data-act-field="' +
        name +
        '-modalId">' +
        self._modalOptions() +
        '</select>' +
        '</div>'
      );
    }

    el.innerHTML =
      '<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">' +
      '<p class="text-[10px] font-black uppercase text-slate-400 mb-3">Contenu de la slide</p>' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">' +
      '<div class="sm:col-span-2"><label class="text-[11px] font-bold text-slate-600">Modèle</label>' +
      '<select class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm mt-0.5" data-field="template">' +
      tplOpts +
      '</select></div>' +
      '</div>' +
      '<div class="mt-3"><label class="text-[11px] font-bold text-slate-600">Titre</label>' +
      '<input type="text" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm mt-0.5" data-field="title" value="' +
      escapeHtml(s.title || '') +
      '" /></div>' +
      '<div class="mt-3"><label class="text-[11px] font-bold text-slate-600">Sous-titre</label>' +
      '<input type="text" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm mt-0.5" data-field="subtitle" value="' +
      escapeHtml(s.subtitle || '') +
      '" /></div>' +
      '<div class="mt-3"><label class="text-[11px] font-bold text-slate-600">' +
      escapeHtml(bodyLabel) +
      '</label>' +
      '<textarea rows="' +
      bodyRows +
      '" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm font-mono text-xs mt-0.5" data-field="body">' +
      escapeHtml(s.body || '') +
      '</textarea></div>' +
      structSection +
      resourcesSection +
      readingFormatHelp +
      '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">' +
      '<div><label class="text-[11px] font-bold text-slate-600">Image (URL)</label>' +
      '<input type="text" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm font-mono text-xs mt-0.5" data-field="imageUrl" value="' +
      escapeHtml(s.imageUrl || '') +
      '" placeholder="https:// ou /uploads/..." /></div>' +
      '<div><label class="text-[11px] font-bold text-slate-600">Légende image</label>' +
      '<input type="text" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm mt-0.5" data-field="imageCaption" value="' +
      escapeHtml(s.imageCaption || '') +
      '" /></div>' +
      '</div>' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">' +
      '<div><label class="text-[11px] font-bold text-slate-600">Fichier (URL)</label>' +
      '<input type="text" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm font-mono text-xs mt-0.5" data-field="fileUrl" value="' +
      escapeHtml(s.fileUrl || '') +
      '" /></div>' +
      '<div><label class="text-[11px] font-bold text-slate-600">Libellé fichier</label>' +
      '<input type="text" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-sm mt-0.5" data-field="fileLabel" value="' +
      escapeHtml(s.fileLabel || '') +
      '" /></div>' +
      '</div>' +
      '<div class="mt-4 rounded-lg border border-violet-100 bg-violet-50/40 p-3">' +
      '<p class="text-[11px] font-bold text-slate-700">Compléments visuels (parcours visuel)</p>' +
      '<p class="text-[10px] text-slate-500 mb-2">S’affichent sous le contenu de l’étape.</p>' +
      '<div><label class="text-[10px] font-bold text-slate-600">Repère de contexte</label>' +
      '<input type="text" class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-dense-kicker value="' +
      escapeHtml(String(s.contextKicker || '')) +
      '" placeholder="Ex. Étape 1 · Découverte" /></div>' +
      '<div class="mt-2"><label class="text-[10px] font-bold text-slate-600">Style de fond</label>' +
      '<select class="w-full border rounded px-2 py-1.5 text-xs mt-0.5" data-dense-surface>' +
      '<option value="default"' +
      (String(s.surface || 'default') !== 'elevated' ? ' selected' : '') +
      '>Standard</option>' +
      '<option value="elevated"' +
      (String(s.surface || '') === 'elevated' ? ' selected' : '') +
      '>Mise en avant douce</option>' +
      '</select></div>' +
      '<div class="grid grid-cols-2 gap-2 mt-2">' +
      '<div><label class="text-[10px] font-bold text-slate-600">Indicateur — libellé</label>' +
      '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-dense-mlab value="' +
      escapeHtml(String((s.metric && s.metric.label) || '')) +
      '" /></div>' +
      '<div><label class="text-[10px] font-bold text-slate-600">Indicateur — valeur</label>' +
      '<input type="text" class="w-full border rounded px-2 py-1 text-xs mt-0.5" data-dense-mval value="' +
      escapeHtml(String((s.metric && s.metric.value) || '')) +
      '" /></div></div>' +
      denseCardsRowsHtml(s) +
      denseInsightsRowsHtml(s) +
      '</div>' +
      '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">' +
      actionBlock('primary', s.primaryAction, 'Action principale') +
      actionBlock('secondary', s.secondaryAction, 'Action secondaire') +
      '</div></div>';

    function bindAction(name, prefix) {
      var act = s[name] || { label: '', type: 'link', url: '', modalId: '' };
      el.querySelector('[data-act-field="' + prefix + '-label"]').addEventListener('input', function (e) {
        if (!s[name]) s[name] = { label: '', type: 'link', url: '', modalId: '' };
        s[name].label = e.target.value;
        self.sync();
        self.schedulePreview();
      });
      el.querySelector('[data-act-field="' + prefix + '-type"]').addEventListener('change', function (e) {
        if (!s[name]) s[name] = { label: '', type: 'link', url: '', modalId: '' };
        s[name].type = e.target.value;
        self.sync();
        self.schedulePreview();
      });
      el.querySelector('[data-act-field="' + prefix + '-url"]').addEventListener('input', function (e) {
        if (!s[name]) s[name] = { label: '', type: 'link', url: '', modalId: '' };
        s[name].url = e.target.value;
        self.sync();
        self.schedulePreview();
      });
      el.querySelector('[data-act-field="' + prefix + '-modalId"]').addEventListener('change', function (e) {
        if (!s[name]) s[name] = { label: '', type: 'link', url: '', modalId: '' };
        s[name].modalId = e.target.value;
        self.sync();
        self.schedulePreview();
      });
    }
    bindAction('primaryAction', 'primary');
    bindAction('secondaryAction', 'secondary');

    (function bindStructured() {
      var st = String(s.template || '');
      var n;
      function parseJsonArr(ta, setter) {
        if (!ta) {
          return;
        }
        ta.addEventListener('change', function () {
          try {
            var j = JSON.parse(ta.value || '[]');
            setter(Array.isArray(j) ? j : []);
          } catch (err) {
            setter([]);
          }
          self.sync();
          self.schedulePreview();
        });
      }
      if (st === 'scenario_decision') {
        n = el.querySelector('[data-struct-sd-context]');
        if (n) {
          n.addEventListener('input', function (e) {
            s.context = e.target.value;
            self.sync();
            self.schedulePreview();
          });
        }
        n = el.querySelector('[data-struct-sd-situation]');
        if (n) {
          n.addEventListener('input', function (e) {
            s.situation = e.target.value;
            self.sync();
            self.schedulePreview();
          });
        }
        n = el.querySelector('[data-struct-sd-correct]');
        if (n) {
          n.addEventListener('input', function (e) {
            s.correctOptionId = e.target.value;
            self.sync();
            self.schedulePreview();
          });
        }
        parseJsonArr(el.querySelector('[data-struct-sd-options]'), function (arr) {
          s.options = arr;
        });
        n = el.querySelector('[data-struct-sd-explain]');
        if (n) {
          n.addEventListener('input', function (e) {
            s.explanation = e.target.value;
            self.sync();
            self.schedulePreview();
          });
        }
      } else if (st === 'dos_donts') {
        n = el.querySelector('[data-struct-dd-dos]');
        if (n) {
          n.addEventListener('input', function (e) {
            s.dos = e.target.value.split(/\r\n|\r|\n/).map(function (x) {
              return x.trim();
            }).filter(Boolean);
            self.sync();
            self.schedulePreview();
          });
        }
        n = el.querySelector('[data-struct-dd-donts]');
        if (n) {
          n.addEventListener('input', function (e) {
            s.donts = e.target.value.split(/\r\n|\r|\n/).map(function (x) {
              return x.trim();
            }).filter(Boolean);
            self.sync();
            self.schedulePreview();
          });
        }
        n = el.querySelector('[data-struct-dd-syn]');
        if (n) {
          n.addEventListener('input', function (e) {
            s.synthesis = e.target.value;
            self.sync();
            self.schedulePreview();
          });
        }
      } else if (st === 'process_steps') {
        parseJsonArr(el.querySelector('[data-struct-ps-steps]'), function (arr) {
          s.steps = arr;
        });
      } else if (st === 'role_scope_compare') {
        [
          ['[data-struct-rsc-mem]', 'memberView'],
          ['[data-struct-rsc-stf]', 'staffView'],
          ['[data-struct-rsc-rights]', 'rightsNote'],
          ['[data-struct-rsc-ok]', 'notAnomaly'],
        ].forEach(function (pair) {
          n = el.querySelector(pair[0]);
          if (n) {
            n.addEventListener('input', function (e) {
              s[pair[1]] = e.target.value;
              self.sync();
              self.schedulePreview();
            });
          }
        });
      } else if (st === 'common_mistakes') {
        parseJsonArr(el.querySelector('[data-struct-cm-mist]'), function (arr) {
          s.mistakes = arr;
        });
      } else if (st === 'case_review') {
        [
          ['[data-struct-cr-case]', 'caseText'],
          ['[data-struct-cr-an]', 'analysis'],
          ['[data-struct-cr-good]', 'goodConduct'],
          ['[data-struct-cr-conc]', 'conclusion'],
        ].forEach(function (pair) {
          n = el.querySelector(pair[0]);
          if (n) {
            n.addEventListener('input', function (e) {
              s[pair[1]] = e.target.value;
              self.sync();
              self.schedulePreview();
            });
          }
        });
      }
    })();

    el.querySelector('[data-field="template"]').addEventListener('change', function (e) {
      s.template = e.target.value;
      ensureStructuredSlideShape(s);
      self.sync();
      self._renderFields();
      self.schedulePreview();
    });
    ['title', 'subtitle', 'body', 'imageUrl', 'imageCaption', 'fileUrl', 'fileLabel'].forEach(function (field) {
      var node = el.querySelector('[data-field="' + field + '"]');
      if (!node) return;
      node.addEventListener('input', function (e) {
        s[field] = e.target.value;
        self.sync();
        self.schedulePreview();
      });
    });

    var dk = el.querySelector('[data-dense-kicker]');
    if (dk) {
      dk.addEventListener('input', function (e) {
        s.contextKicker = e.target.value;
        self.sync();
        self.schedulePreview();
      });
    }
    var ds = el.querySelector('[data-dense-surface]');
    if (ds) {
      ds.addEventListener('change', function (e) {
        s.surface = e.target.value;
        self.sync();
        self.schedulePreview();
      });
    }
    function touchMetric() {
      if (!s.metric || typeof s.metric !== 'object') {
        s.metric = { label: '', value: '' };
      }
    }
    function maybeClearMetric() {
      if (
        s.metric &&
        typeof s.metric === 'object' &&
        !String(s.metric.label || '').trim() &&
        !String(s.metric.value || '').trim()
      ) {
        s.metric = null;
      }
    }
    var mlab = el.querySelector('[data-dense-mlab]');
    var mval = el.querySelector('[data-dense-mval]');
    if (mlab) {
      mlab.addEventListener('input', function (e) {
        touchMetric();
        s.metric.label = e.target.value;
        maybeClearMetric();
        self.sync();
        self.schedulePreview();
      });
    }
    if (mval) {
      mval.addEventListener('input', function (e) {
        touchMetric();
        s.metric.value = e.target.value;
        maybeClearMetric();
        self.sync();
        self.schedulePreview();
      });
    }

    el.querySelectorAll('[data-card-row]').forEach(function (rowEl) {
      var idx = parseInt(rowEl.getAttribute('data-card-row'), 10);
      if (isNaN(idx) || !s.cards[idx]) {
        return;
      }
      var labIn = rowEl.querySelector('[data-card-lab]');
      var bodyTa = rowEl.querySelector('[data-card-body]');
      var rm = rowEl.querySelector('[data-card-rm]');
      if (labIn) {
        labIn.addEventListener('input', function (e) {
          s.cards[idx].label = e.target.value;
          self.sync();
          self.schedulePreview();
        });
      }
      if (bodyTa) {
        bodyTa.addEventListener('input', function (e) {
          s.cards[idx].body = e.target.value;
          self.sync();
          self.schedulePreview();
        });
      }
      if (rm) {
        rm.addEventListener('click', function () {
          s.cards.splice(idx, 1);
          self.sync();
          self._renderFields();
          self.schedulePreview();
        });
      }
    });
    var cardAdd = el.querySelector('[data-card-add]');
    if (cardAdd) {
      cardAdd.addEventListener('click', function () {
        s.cards.push({ label: '', body: '' });
        self.sync();
        self._renderFields();
        self.schedulePreview();
      });
    }

    el.querySelectorAll('[data-ins-row]').forEach(function (rowEl) {
      var idx = parseInt(rowEl.getAttribute('data-ins-row'), 10);
      if (isNaN(idx) || !s.insights[idx]) {
        return;
      }
      var varSel = rowEl.querySelector('[data-ins-var]');
      var titIn = rowEl.querySelector('[data-ins-title]');
      var bodyTa = rowEl.querySelector('[data-ins-body]');
      var rm = rowEl.querySelector('[data-ins-rm]');
      if (varSel) {
        varSel.addEventListener('change', function (e) {
          s.insights[idx].variant = e.target.value;
          self.sync();
          self.schedulePreview();
        });
      }
      if (titIn) {
        titIn.addEventListener('input', function (e) {
          s.insights[idx].title = e.target.value;
          self.sync();
          self.schedulePreview();
        });
      }
      if (bodyTa) {
        bodyTa.addEventListener('input', function (e) {
          s.insights[idx].body = e.target.value;
          self.sync();
          self.schedulePreview();
        });
      }
      if (rm) {
        rm.addEventListener('click', function () {
          s.insights.splice(idx, 1);
          self.sync();
          self._renderFields();
          self.schedulePreview();
        });
      }
    });
    var insAdd = el.querySelector('[data-ins-add]');
    if (insAdd) {
      insAdd.addEventListener('click', function () {
        s.insights.push({ variant: 'retain', title: '', body: '' });
        self.sync();
        self._renderFields();
        self.schedulePreview();
      });
    }

    self._bindResourcesEditor(s, el);
  };

  LmsCanvasEditor.prototype._renderModals = function () {
    var self = this;
    var host = this._modalListEl;
    host.innerHTML = '';
    if (!this.deck.modals) this.deck.modals = [];
    this.deck.modals.forEach(function (m, idx) {
      var wrap = document.createElement('div');
      wrap.className = 'border border-slate-100 rounded-lg p-3 space-y-2 mb-2';
      wrap.innerHTML =
        '<input type="text" class="w-full border rounded px-2 py-1 text-xs" data-m-title placeholder="Titre modale" />' +
        '<textarea rows="4" class="w-full border rounded px-2 py-1 text-xs font-mono" data-m-body placeholder="HTML"></textarea>' +
        '<div class="flex gap-2">' +
        '<button type="button" class="lms-modal-preview px-2 py-1 text-[11px] rounded border hover:bg-slate-50">Aperçu</button>' +
        '<button type="button" class="lms-modal-remove text-[11px] text-rose-600 underline">Retirer</button>' +
        '</div>';
      wrap.querySelector('[data-m-title]').value = m.title || '';
      wrap.querySelector('[data-m-body]').value = m.body || '';
      wrap.querySelector('[data-m-title]').addEventListener('input', function (e) {
        m.title = e.target.value;
        self.sync();
        self._renderFields();
      });
      wrap.querySelector('[data-m-body]').addEventListener('input', function (e) {
        m.body = e.target.value;
        self.sync();
      });
      wrap.querySelector('.lms-modal-remove').addEventListener('click', function () {
        self.deck.modals.splice(idx, 1);
        self.sync();
        self._renderModals();
        self._renderFields();
      });
      wrap.querySelector('.lms-modal-preview').addEventListener('click', function () {
        self._openModalPreview(m.title || 'Modale', m.body || '');
      });
      host.appendChild(wrap);
    });
  };

  LmsCanvasEditor.prototype._openModalPreview = function (title, bodyHtml) {
    var ex = document.getElementById('lms-canvas-modal-shell');
    if (ex) ex.remove();
    var shell = document.createElement('div');
    shell.id = 'lms-canvas-modal-shell';
    shell.className = 'fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-900/60';
    shell.innerHTML =
      '<div class="bg-white rounded-2xl max-w-lg w-full max-h-[80vh] overflow-y-auto shadow-2xl p-6">' +
      '<h3 class="text-lg font-black text-slate-900 mb-3"></h3>' +
      '<div class="prose prose-sm text-slate-700"></div>' +
      '<button type="button" class="mt-6 w-full py-2 rounded-lg bg-slate-900 text-white text-sm font-bold">Fermer</button></div>';
    shell.querySelector('h3').textContent = title;
    shell.querySelector('.prose').innerHTML = bodyHtml;
    shell.querySelector('button').addEventListener('click', function () {
      shell.remove();
    });
    shell.addEventListener('click', function (e) {
      if (e.target === shell) shell.remove();
    });
    document.body.appendChild(shell);
  };

  function init(container) {
    if (!container || container.__lmsCanvas) return;
    var ed = new LmsCanvasEditor(container);
    if (ed.invalid) return;
    container.__lmsCanvas = ed;
  }

  window.lmsCanvasToggleLessonEditor = function (selectEl) {
    var form = selectEl.closest('form');
    if (!form) return;
    var wrap = form.querySelector('[data-lms-canvas-wrap]');
    var plain = form.querySelector('[data-lms-plain-content]');
    var plainTa = plain ? plain.querySelector('[data-lms-lesson-body]') || plain.querySelector('textarea[name="lesson_content"]') : null;
    var canvasTa = form.querySelector('[data-lms-canvas-json]');
    var jsonHelp = form.querySelector('[data-lms-json-help]');
    var jsonHelpText = jsonHelp ? jsonHelp.querySelector('[data-lms-json-help-text]') : null;
    var plainLabel = form.querySelector('[data-lms-plain-label]');
    var extHint = form.querySelector('[data-lms-external-hint]');
    var extLabel = form.querySelector('[data-lms-external-label]');
    if (!plain || !plainTa) return;
    var v = selectEl.value;
    var jsonTypes = { quiz: 1, modals: 1, slideshow: 1 };
    var isJson = jsonTypes[v] === 1;
    var isCanvas = v === 'canvas';
    if (jsonHelp) {
      jsonHelp.classList.add('hidden');
      if (jsonHelpText) {
        jsonHelpText.textContent = '';
      }
    }
    if (plainLabel) {
      if (isJson) {
        if (v === 'quiz') plainLabel.textContent = 'Quiz — questions & réponses';
        else if (v === 'modals') plainLabel.textContent = 'Modales — fenêtres pédagogiques';
        else if (v === 'slideshow') plainLabel.textContent = 'Diaporama — images & légendes';
        else plainLabel.textContent = 'Contenu de la leçon';
      } else {
        plainLabel.textContent = 'Contenu (HTML ou texte)';
      }
    }
    plainTa.rows = isJson ? 6 : 4;
    if (extHint) {
      if (v === 'video_embed') {
        extHint.classList.remove('hidden');
        extHint.textContent = 'Collez une URL YouTube ou Vimeo (page ou embed).';
      } else if (v === 'video' || v === 'video_integrated') {
        extHint.classList.remove('hidden');
        extHint.textContent = 'URL directe du fichier vidéo (MP4, WebM…).';
      } else {
        extHint.classList.add('hidden');
        extHint.textContent = '';
      }
    }
    if (extLabel) {
      extLabel.textContent = v === 'video_embed' ? 'URL de la vidéo (YouTube / Vimeo)' : 'URL externe (optionnel)';
    }
    if (isCanvas) {
      if (!wrap || !canvasTa) return;
      wrap.classList.remove('hidden');
      plain.classList.add('hidden');
      plainTa.disabled = true;
      canvasTa.disabled = false;
      var inner = wrap.querySelector('[data-lms-canvas-editor]');
      if (inner && !inner.__lmsCanvas) init(inner);
    } else {
      if (wrap) wrap.classList.add('hidden');
      plain.classList.remove('hidden');
      plainTa.disabled = false;
      if (canvasTa) canvasTa.disabled = true;
    }
  };

  function initAll() {
    document.querySelectorAll('[data-lms-canvas-wrap]').forEach(function (wrap) {
      var form = wrap.closest('form');
      if (!form) {
        return;
      }
      var sel = form.querySelector('select[name="lesson_type"]');
      if (sel) {
        window.lmsCanvasToggleLessonEditor(sel);
      }
    });
    document.querySelectorAll('[data-lms-canvas-editor]').forEach(init);
    document.querySelectorAll('form select[name="lesson_type"]').forEach(function (sel) {
      window.lmsCanvasToggleLessonEditor(sel);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
