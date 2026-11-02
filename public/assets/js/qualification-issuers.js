/**
 * Référentiels > Qualifications > Émetteurs.
 * - Fiche d'un émetteur : ouverture du formulaire replié visé par un lien (#modifier).
 * - Liste : tableau triable et filtrable (recherche, pays, type, origine, statut), pagination, état dans l'adresse.
 * - Assistants pas à pas en fenêtre (« Importer les émetteurs réels », « Créer un émetteur ») : recherche dans chaque
 *   étape, récapitulatif, envoi par fetch (Accept: application/json) puis rechargement des zones data-qis-region.
 * - Archiver / réactiver depuis une ligne, sans recharger la page.
 * Les textes posés ici sont traduits par i18n_dom.js (dictionnaire lang/en/site_auto.php).
 */
(function () {
  'use strict';

  var DEFAULT_PER_PAGE = 25;

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  function all(root, sel) {
    return Array.prototype.slice.call(root.querySelectorAll(sel));
  }

  function node(tag, text, cls) {
    var el = document.createElement(tag);
    if (cls) el.className = cls;
    if (text !== undefined && text !== null) el.textContent = text;
    return el;
  }

  function initials(s) {
    var words = String(s || '').trim().split(/[\s\-_.]+/).filter(Boolean);
    if (!words.length) return '?';
    return (words.length === 1 ? words[0].slice(0, 2) : words[0][0] + words[1][0]).toUpperCase();
  }

  // ——— Formulaires repliés (fiche de l'émetteur) ———
  function openFold(id) {
    var el = id ? document.getElementById(id) : null;
    if (!el || el.tagName !== 'DETAILS') return false;
    el.open = true;
    var first = el.querySelector('input:not([type=hidden]), select, textarea');
    if (first) first.focus({ preventScroll: true });
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return true;
  }

  var root = document.querySelector('[data-qis]');

  // ——— Fenêtres des assistants ———
  function openDialog(id) {
    var d = id ? document.getElementById(id) : null;
    if (!d || d.tagName !== 'DIALOG') return null;
    var form = d.querySelector('[data-qis-wiz]');
    var wz = form ? wizard(form) : null;
    if (!d.open) {
      if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
      if (wz) wz.show(1);
    }
    if (history.replaceState) history.replaceState(null, '', location.pathname + location.search + '#' + id);
    return wz;
  }

  function closeDialog(d) {
    if (!d) return;
    if (typeof d.close === 'function' && d.open) d.close(); else d.removeAttribute('open');
  }

  document.addEventListener('click', function (ev) {
    var opener = ev.target.closest('[data-qis-open]');
    if (opener) {
      ev.preventDefault();
      openDialog(opener.getAttribute('data-qis-open'));
      return;
    }
    var closer = ev.target.closest('[data-qis-close]');
    if (closer) {
      closeDialog(closer.closest('dialog'));
      return;
    }
    var a = ev.target.closest('a[href^="#"]');
    if (!a) return;
    var id = a.getAttribute('href').slice(1);
    var el = id ? document.getElementById(id) : null;
    if (!el) return;
    if (el.tagName === 'DIALOG') {
      ev.preventDefault();
      openDialog(id);
    } else if (el.tagName === 'DETAILS') {
      ev.preventDefault();
      openFold(id);
      if (history.replaceState) history.replaceState(null, '', '#' + id);
    }
  });

  // Fermeture d'une fenêtre : l'adresse perd son ancre.
  document.addEventListener('close', function (ev) {
    if (ev.target.tagName === 'DIALOG' && location.hash === '#' + ev.target.id && history.replaceState) {
      history.replaceState(null, '', location.pathname + location.search);
    }
  }, true);

  // ——— Messages ———
  function message(kind, text, link) {
    var box = root ? root.querySelector('[data-qis-msg]') : null;
    if (!box || !text) return;
    var p = node('p', null, 'effk-note ' + (kind === 'ok' ? 'is-info' : 'is-warn'));
    p.setAttribute('role', kind === 'ok' ? 'status' : 'alert');
    p.appendChild(node('span', text));
    if (link) {
      var a = node('a', link.label);
      a.href = link.href;
      a.className = 'qis-msg__link';
      p.appendChild(a);
    }
    box.appendChild(p);
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearMessages() {
    var box = root ? root.querySelector('[data-qis-msg]') : null;
    if (box) box.innerHTML = '';
  }

  function busy(btn, on) {
    if (!btn) return;
    btn.disabled = on;
    btn.classList.toggle('is-busy', on);
    if (on) btn.setAttribute('aria-busy', 'true'); else btn.removeAttribute('aria-busy');
  }

  function postJson(form) {
    return fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      return res.json().catch(function () {
        return { ok: false, message: 'Réponse inattendue du serveur. Rechargez la page et réessayez.' };
      }).then(function (data) {
        data = data || {};
        if (!res.ok && data.ok !== false) data.ok = false;
        return data;
      });
    }, function () {
      return { ok: false, message: 'Connexion impossible. Vérifiez votre réseau et réessayez.' };
    });
  }

  // ——— Rechargement des zones après une action ———
  function refresh() {
    if (!root) return Promise.resolve();
    var region = root.querySelector('[data-qis-region="list"]');
    var skel = region ? region.querySelector('[data-qis-skel]') : null;
    if (region) region.classList.add('is-loading');
    if (skel) skel.hidden = false;
    if (region) region.setAttribute('aria-busy', 'true');
    return fetch(root.getAttribute('data-qis-url') || location.pathname, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        all(doc, '[data-qis-region]').forEach(function (fresh) {
          var name = fresh.getAttribute('data-qis-region');
          var cur = root.querySelector('[data-qis-region="' + name + '"]');
          if (cur && !(cur.tagName === 'DIALOG' && cur.open)) {
            cur.replaceWith(document.importNode(fresh, true));
          }
        });
        list.init();
      })
      .catch(function () {
        if (region) region.classList.remove('is-loading');
        if (skel) skel.hidden = true;
        message('err', 'La liste n’a pas pu être actualisée : rechargez la page.');
      });
  }

  // ——— Liste des émetteurs ———
  var list = {
    state: {},
    rows: [],
    init: function () {
      if (!root) return;
      var region = root.querySelector('[data-qis-region="list"]');
      if (region) {
        region.classList.remove('is-loading');
        region.removeAttribute('aria-busy');
      }
      this.region = region;
      this.tbody = region ? region.querySelector('[data-qis-rows]') : null;
      this.rows = this.tbody ? all(this.tbody, '[data-qis-row]') : [];
      if (!this.tbody) return;
      var params = new URLSearchParams(location.search);
      var st = { q: '', pays: '', type: '', origine: '', statut: 'active', tri: 'name', sens: 'asc', page: 1, parpage: DEFAULT_PER_PAGE };
      ['q', 'pays', 'type', 'origine', 'tri', 'sens'].forEach(function (k) {
        if (params.has(k)) st[k] = params.get(k);
      });
      if (params.has('statut')) st.statut = params.get('statut');
      st.page = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);
      var pp = parseInt(params.get('parpage') || '', 10);
      if ([25, 50, 100].indexOf(pp) !== -1) st.parpage = pp;
      this.state = st;
      all(region, '[data-qis-f]').forEach(function (el) {
        var k = el.getAttribute('data-qis-f');
        var v = String(st[k] === undefined ? '' : st[k]);
        if (el.tagName === 'SELECT' && !all(el, 'option').some(function (o) { return o.value === v; })) return;
        el.value = v;
      });
      this.apply(false);
    },
    sortValue: function (row, key) {
      if (key === 'quals' || key === 'awards') return parseInt(row.getAttribute('data-' + key) || '0', 10);
      if (key === 'country') return row.getAttribute('data-country-label') || '￿';
      if (key === 'kind' || key === 'origin') return (row.querySelector(key === 'kind' ? 'td:nth-child(3)' : 'td:nth-child(4)') || row).textContent.trim().toLowerCase();
      return row.getAttribute('data-name') || '';
    },
    apply: function (write) {
      var st = this.state;
      var self = this;
      var term = norm(st.q.trim());
      var matched = this.rows.filter(function (r) {
        var c = r.getAttribute('data-country');
        return (!term || norm(r.getAttribute('data-search')).indexOf(term) !== -1)
          && (!st.pays || (st.pays === '-' ? c === '' : c === st.pays))
          && (!st.type || r.getAttribute('data-kind') === st.type)
          && (!st.origine || r.getAttribute('data-origin') === st.origine)
          && (!st.statut || r.getAttribute('data-status') === st.statut);
      });
      var dir = st.sens === 'desc' ? -1 : 1;
      matched.sort(function (a, b) {
        var va = self.sortValue(a, st.tri);
        var vb = self.sortValue(b, st.tri);
        var cmp = typeof va === 'number' ? va - vb : String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' });
        if (cmp === 0) cmp = (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
        return cmp * dir;
      });
      var pages = Math.max(1, Math.ceil(matched.length / st.parpage));
      if (st.page > pages) st.page = pages;
      var start = (st.page - 1) * st.parpage;
      var visible = {};
      matched.slice(start, start + st.parpage).forEach(function (r) { visible[r.getAttribute('data-id')] = true; });
      this.rows.forEach(function (r) { r.hidden = true; });
      matched.forEach(function (r) {
        self.tbody.appendChild(r);
        r.hidden = !visible[r.getAttribute('data-id')];
      });
      var region = this.region;
      var count = region.querySelector('[data-qis-count]');
      if (count) count.textContent = String(matched.length);
      var none = region.querySelector('[data-qis-none]');
      if (none) none.hidden = matched.length > 0;
      var table = region.querySelector('.qis-tablewrap');
      if (table) table.hidden = matched.length === 0;
      var pager = region.querySelector('[data-qis-pager]');
      if (pager) {
        pager.hidden = matched.length <= 25;
        pager.querySelector('[data-qis-page-n]').textContent = String(st.page);
        pager.querySelector('[data-qis-page-total]').textContent = String(pages);
        pager.querySelector('[data-qis-page="prev"]').disabled = st.page <= 1;
        pager.querySelector('[data-qis-page="next"]').disabled = st.page >= pages;
      }
      var filtered = !!(st.q || st.pays || st.type || st.origine || st.statut !== 'active');
      all(region, '[data-qis-reset]').forEach(function (b) { if (b.closest('.qis-list__bar')) b.hidden = !filtered; });
      all(region, 'select[data-qis-f]').forEach(function (sel) {
        var def = sel.getAttribute('data-default') || '';
        sel.classList.toggle('is-set', sel.value !== def);
      });
      all(region, '[data-qis-sort]').forEach(function (b) {
        var th = b.closest('th');
        if (b.getAttribute('data-qis-sort') === st.tri) th.setAttribute('aria-sort', st.sens === 'desc' ? 'descending' : 'ascending');
        else th.removeAttribute('aria-sort');
      });
      if (write && history.replaceState) {
        var p = new URLSearchParams(location.search);
        var defs = { q: '', pays: '', type: '', origine: '', statut: 'active', tri: 'name', sens: 'asc', page: 1, parpage: DEFAULT_PER_PAGE };
        Object.keys(defs).forEach(function (k) {
          if (String(st[k]) === String(defs[k])) p.delete(k); else p.set(k, String(st[k]));
        });
        var qs = p.toString();
        history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
      }
    }
  };

  if (root) {
    var typing = null;
    root.addEventListener('input', function (ev) {
      var f = ev.target.closest('[data-qis-region="list"] [data-qis-f="q"]');
      if (!f) return;
      clearTimeout(typing);
      typing = setTimeout(function () {
        list.state.q = f.value;
        list.state.page = 1;
        list.apply(true);
      }, 120);
    });
    root.addEventListener('change', function (ev) {
      var f = ev.target.closest('[data-qis-region="list"] select[data-qis-f]');
      if (!f) return;
      var k = f.getAttribute('data-qis-f');
      list.state[k] = k === 'parpage' ? parseInt(f.value, 10) || DEFAULT_PER_PAGE : f.value;
      list.state.page = 1;
      list.apply(true);
    });
    root.addEventListener('click', function (ev) {
      var sort = ev.target.closest('[data-qis-sort]');
      if (sort) {
        var key = sort.getAttribute('data-qis-sort');
        if (list.state.tri === key) list.state.sens = list.state.sens === 'asc' ? 'desc' : 'asc';
        else { list.state.tri = key; list.state.sens = sort.hasAttribute('data-qis-desc') ? 'desc' : 'asc'; }
        list.apply(true);
        return;
      }
      var pg = ev.target.closest('[data-qis-page]');
      if (pg) {
        list.state.page += pg.getAttribute('data-qis-page') === 'next' ? 1 : -1;
        list.apply(true);
        var region = root.querySelector('[data-qis-region="list"]');
        if (region) region.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      if (ev.target.closest('[data-qis-reset]')) {
        list.state.q = ''; list.state.pays = ''; list.state.type = ''; list.state.origine = ''; list.state.statut = 'active'; list.state.page = 1;
        all(root, '[data-qis-region="list"] [data-qis-f]').forEach(function (el) {
          var k = el.getAttribute('data-qis-f');
          if (k !== 'parpage') el.value = k === 'statut' ? 'active' : '';
        });
        list.apply(true);
      }
    });

    // Archiver / réactiver depuis une ligne.
    root.addEventListener('submit', function (ev) {
      var form = ev.target.closest('form[data-qis-ajax]');
      if (!form || !window.fetch) return;
      ev.preventDefault();
      var q = form.getAttribute('data-confirm');
      if (q && !window.confirm(q)) return;
      var btn = form.querySelector('button[type=submit]');
      busy(btn, true);
      postJson(form).then(function (data) {
        busy(btn, false);
        clearMessages();
        message(data.ok ? 'ok' : 'err', data.message || (data.ok ? '' : 'Action impossible. Réessayez.'));
        if (data.ok) refresh();
      });
    });
  }

  // ——— Assistants pas à pas ———
  function wizard(form) {
    if (form._qisWizard) return form._qisWizard;
    var steps = all(form, '[data-qis-step]');
    var items = all(form, '.ds-stepper__item');
    items.forEach(function (li) { all(li, '.sr-only').forEach(function (s) { s.remove(); }); });
    var prev = form.querySelector('[data-qis-prev]');
    var next = form.querySelector('[data-qis-next]');
    var submit = form.querySelector('[data-qis-submit]');
    var where = form.querySelector('[data-qis-where]');
    var errorBox = form.querySelector('[data-qis-wiz-error]');
    var kind = form.getAttribute('data-qis-wiz');
    var spec = kind === 'real' ? realSpec(form) : newSpec(form);
    var wz = {
      form: form,
      cur: 1,
      total: steps.length,
      spec: spec,
      show: function (n) {
        this.cur = Math.max(1, Math.min(this.total, n));
        var cur = this.cur;
        steps.forEach(function (s) { s.hidden = Number(s.getAttribute('data-qis-step')) !== cur; });
        items.forEach(function (li, i) {
          var k = i + 1;
          li.classList.toggle('ds-stepper__item--active', k === cur);
          li.classList.toggle('ds-stepper__item--done', k < cur);
          if (k === cur) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
          var num = li.querySelector('.ds-stepper__num');
          if (num) num.textContent = k < cur ? '✓' : String(k);
        });
        if (prev) prev.disabled = cur === 1;
        if (next) next.hidden = cur === this.total;
        if (submit) submit.hidden = cur !== this.total;
        if (where) where.textContent = 'Étape ' + cur + ' sur ' + this.total;
        if (errorBox) errorBox.hidden = true;
        if (spec.onShow) spec.onShow(cur);
        var body = form.querySelector('.qis-wiz__b');
        if (body) body.scrollTop = 0;
        var focus = steps[cur - 1] ? steps[cur - 1].querySelector('input[type=search], input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea') : null;
        if (focus && cur !== this.total) focus.focus({ preventScroll: true });
      },
      valid: function (n) { return spec.valid ? spec.valid(n) : true; }
    };
    if (prev) prev.addEventListener('click', function () { wz.show(wz.cur - 1); });
    if (next) next.addEventListener('click', function () { if (wz.valid(wz.cur)) wz.show(wz.cur + 1); });
    items.forEach(function (li, i) {
      li.style.cursor = 'pointer';
      li.addEventListener('click', function () {
        for (var k = 1; k <= i; k++) {
          if (!wz.valid(k)) { wz.show(k); return; }
        }
        wz.show(i + 1);
      });
    });
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (wz.cur < wz.total) {
        if (wz.valid(wz.cur)) wz.show(wz.cur + 1);
        return;
      }
      for (var k = 1; k < wz.total; k++) {
        if (!wz.valid(k)) { wz.show(k); return; }
      }
      if (!window.fetch) { form.submit(); return; }
      busy(submit, true);
      if (prev) prev.disabled = true;
      if (errorBox) errorBox.hidden = true;
      postJson(form).then(function (data) {
        busy(submit, false);
        if (prev) prev.disabled = false;
        if (!data.ok) {
          if (errorBox) {
            errorBox.textContent = data.message || 'Action impossible. Réessayez.';
            errorBox.hidden = false;
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
          return;
        }
        closeDialog(form.closest('dialog'));
        clearMessages();
        message('ok', data.message, data.url ? { href: data.url, label: 'Ouvrir la fiche' } : null);
        if (data.warning) message('err', data.warning);
        refresh();
      });
    });
    form.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && ev.target.matches('input[type=search]')) ev.preventDefault();
    });
    form._qisWizard = wz;
    return wz;
  }

  function showErr(form, key, on) {
    var el = form.querySelector('[data-qis-err="' + key + '"]');
    if (el) el.hidden = !on;
    return !on;
  }

  // Importer les émetteurs réels : 1 pays, 2 émetteurs, 3 récapitulatif et plafond.
  function realSpec(form) {
    var remainingAttr = form.getAttribute('data-remaining');
    var remaining = remainingAttr === '' || remainingAttr === null ? null : parseInt(remainingAttr, 10);
    var used = parseInt(form.getAttribute('data-used') || '0', 10);
    var limit = parseInt(form.getAttribute('data-limit') || '0', 10);
    var cards = all(form, '[data-qis-country-card]');
    var groups = all(form, '[data-qis-group]');
    var q1 = form.querySelector('[data-qis-q1]');
    var q2 = form.querySelector('[data-qis-q2]');
    var type2 = form.querySelector('[data-qis-type2]');
    var hidePresent = form.querySelector('[data-qis-hide-present]');
    var all2 = form.querySelector('[data-qis-all2]');

    function countries() {
      return all(form, '[data-qis-country]:checked').map(function (c) { return c.value; });
    }
    function selected() {
      return all(form, 'input[name="keys[]"]:checked:not(:disabled)');
    }
    function filterCountries() {
      var term = norm(q1 ? q1.value.trim() : '');
      var shown = 0;
      cards.forEach(function (card) {
        var code = card.querySelector('[data-qis-country]').value;
        var hit = card.querySelector('[data-qis-hit]');
        var n = 0;
        if (term) {
          var g = form.querySelector('[data-qis-group="' + code + '"]');
          n = g ? all(g, '[data-qis-ri]').filter(function (li) { return norm(li.getAttribute('data-search')).indexOf(term) !== -1; }).length : 0;
        }
        var ok = !term || n > 0 || norm(card.getAttribute('data-search')).indexOf(term) !== -1;
        card.hidden = !ok;
        if (ok) shown++;
        if (hit) {
          hit.hidden = !term || n === 0;
          hit.textContent = n + ' correspondance(s)';
        }
      });
      var none = form.querySelector('[data-qis-q1-none]');
      if (none) none.hidden = shown > 0;
    }
    function filterIssuers() {
      var chosen = countries();
      var term = norm(q2 ? q2.value.trim() : '');
      var t = type2 ? type2.value : '';
      var hide = hidePresent && hidePresent.checked;
      var shownAll = 0;
      var visibleBoxes = [];
      groups.forEach(function (g) {
        var inCountry = chosen.indexOf(g.getAttribute('data-qis-group')) !== -1;
        var shown = 0;
        all(g, '[data-qis-ri]').forEach(function (li) {
          var ok = inCountry
            && (!term || norm(li.getAttribute('data-search')).indexOf(term) !== -1)
            && (!t || li.getAttribute('data-type') === t)
            && !(hide && li.hasAttribute('data-present'));
          li.hidden = !ok;
          if (ok) {
            shown++;
            var box = li.querySelector('input[name="keys[]"]');
            if (box && !box.disabled) visibleBoxes.push(box);
          }
        });
        g.hidden = !inCountry || shown === 0;
        shownAll += shown;
      });
      var none = form.querySelector('[data-qis-q2-none]');
      if (none) none.hidden = shownAll > 0;
      var shownEl = form.querySelector('[data-qis-shown2]');
      if (shownEl) shownEl.textContent = String(visibleBoxes.length);
      if (all2) {
        all2.disabled = visibleBoxes.length === 0;
        var n = visibleBoxes.filter(function (b) { return b.checked; }).length;
        all2.checked = visibleBoxes.length > 0 && n === visibleBoxes.length;
        all2.indeterminate = n > 0 && n < visibleBoxes.length;
      }
      return visibleBoxes;
    }
    function updateCount() {
      var n = selected().length;
      var el = form.querySelector('[data-qis-nsel]');
      if (el) el.textContent = String(n);
      showErr(form, 'quota', remaining !== null && n > remaining);
      return n;
    }
    function summary() {
      var box = form.querySelector('[data-qis-summary1]');
      if (!box) return;
      box.innerHTML = '';
      var sel = selected();
      if (!sel.length) {
        box.appendChild(node('p', 'Aucun émetteur coché.', 'qis-wiz__err'));
      }
      groups.forEach(function (g) {
        var boxes = all(g, 'input[name="keys[]"]:checked:not(:disabled)');
        if (!boxes.length) return;
        var sec = node('div', null, 'qis-sum__group');
        var h = g.querySelector('h3');
        var title = node('h4');
        title.innerHTML = h ? h.innerHTML : '';
        title.appendChild(node('span', String(boxes.length), 'effk-tag'));
        sec.appendChild(title);
        var ul = node('ul', null, 'qis-chips');
        boxes.forEach(function (b) {
          var li = node('li', b.getAttribute('data-label'));
          li.setAttribute('translate', 'no');
          ul.appendChild(li);
        });
        sec.appendChild(ul);
        box.appendChild(sec);
      });
      var after = form.querySelector('[data-qis-meter-after]');
      if (after) after.textContent = String(used + sel.length);
      var add = form.querySelector('[data-qis-meter-add]');
      if (add && limit > 0) add.style.width = Math.max(0, Math.min(100 - Math.round(100 * used / limit), Math.round(100 * sel.length / limit))) + '%';
      var meter = form.querySelector('[data-qis-meter]');
      if (meter) meter.classList.toggle('is-over', remaining !== null && sel.length > remaining);
    }

    form.addEventListener('input', function (ev) {
      if (ev.target === q1) filterCountries();
      if (ev.target === q2) filterIssuers();
    });
    form.addEventListener('change', function (ev) {
      var t = ev.target;
      if (t.matches('[data-qis-country]')) {
        if (!t.checked) {
          var g = form.querySelector('[data-qis-group="' + t.value + '"]');
          if (g) all(g, 'input[name="keys[]"]:not(:disabled)').forEach(function (b) { b.checked = false; });
        }
        showErr(form, '1', false);
        updateCount();
      } else if (t === type2 || t === hidePresent) {
        filterIssuers();
      } else if (t === all2) {
        var want = all2.checked;
        var boxes = filterIssuers();
        var room = remaining === null ? Infinity : remaining - selected().filter(function (b) { return boxes.indexOf(b) === -1; }).length;
        boxes.forEach(function (b, i) { b.checked = want && i < room; });
        filterIssuers();
        updateCount();
        if (want && boxes.length > room) {
          var cap = form.querySelector('[data-qis-err="cap"]');
          if (cap) cap.hidden = false;
        }
        showErr(form, '2', false);
      } else if (t.name === 'keys[]') {
        showErr(form, 'cap', false);
        filterIssuers();
        updateCount();
        showErr(form, '2', false);
      }
    });

    filterCountries();
    return {
      onShow: function (n) {
        if (n === 2) {
          if (q2 && !q2.value && q1 && q1.value) q2.value = q1.value;
          filterIssuers();
          updateCount();
        }
        if (n === 3) summary();
      },
      valid: function (n) {
        if (n === 1) return showErr(form, '1', countries().length === 0);
        if (n === 2) {
          var c = updateCount();
          if (!showErr(form, '2', c === 0)) return false;
          return !(remaining !== null && c > remaining);
        }
        return true;
      },
      // Depuis « Créer un émetteur » : un émetteur du catalogue proposé, coché d'office.
      preselect: function (key) {
        var box = form.querySelector('input[name="keys[]"][value="' + key + '"]');
        if (!box) return false;
        var g = box.closest('[data-qis-group]');
        var c = g ? form.querySelector('[data-qis-country][value="' + g.getAttribute('data-qis-group') + '"]') : null;
        if (c) c.checked = true;
        if (!box.disabled) box.checked = true;
        updateCount();
        return true;
      }
    };
  }

  // Créer un émetteur : 1 identité, 2 détails, 3 récapitulatif.
  function newSpec(form) {
    var name = form.querySelector('[data-qis-name]');
    var short = form.querySelector('input[name="short_name"]');
    var web = form.querySelector('input[name="website"]');
    var logo = form.querySelector('input[name="logo"]');
    var qdef = form.querySelector('[data-qis-qdef]');
    var logoUrl = '';
    var timer = null;

    function suggest() {
      var box = form.querySelector('[data-qis-suggest]');
      if (!box || !name) return;
      var term = norm(name.value.trim());
      var sigle = norm(short ? short.value.trim() : '');
      var dup = form.querySelector('[data-qis-dup]');
      var hits = form.querySelector('[data-qis-real-hits]');
      var hitList = form.querySelector('[data-qis-real-hit-list]');
      var found = null;
      if (root && (term || sigle)) {
        all(root, '[data-qis-row]').some(function (r) {
          if ((term && norm(r.getAttribute('data-name')) === term) || (sigle && norm(r.getAttribute('data-short')) === sigle)) { found = r; return true; }
          return false;
        });
      }
      if (dup) {
        dup.innerHTML = '';
        dup.hidden = !found;
        if (found) {
          dup.appendChild(node('span', 'Un émetteur de votre référentiel porte déjà ce nom ou ce sigle.'));
          var link = found.querySelector('.qis-who__id a');
          if (link) {
            var a = node('a', 'Ouvrir sa fiche');
            a.href = link.href;
            dup.appendChild(document.createTextNode(' '));
            dup.appendChild(a);
          }
        }
      }
      var matches = [];
      if (term.length >= 3) {
        all(document, '[data-qis-wiz="real"] [data-qis-ri]:not([data-present])').some(function (li) {
          if (norm(li.getAttribute('data-search')).indexOf(term) !== -1) matches.push(li);
          return matches.length >= 5;
        });
      }
      if (hitList) {
        hitList.innerHTML = '';
        matches.forEach(function (li) {
          var item = node('li');
          var label = li.querySelector('.qis-real__id b');
          var full = li.querySelector('[data-qis-ri-name]');
          var txt = node('span', null, 'qis-suggest__name');
          txt.setAttribute('translate', 'no');
          txt.appendChild(node('b', label ? label.textContent : ''));
          txt.appendChild(document.createTextNode(' ' + (full ? full.textContent : '')));
          item.appendChild(txt);
          var btn = node('button', 'Importer celui-ci', 'effk-btn is-sm');
          btn.type = 'button';
          btn.setAttribute('data-qis-import-key', li.getAttribute('data-key'));
          item.appendChild(btn);
          hitList.appendChild(item);
        });
      }
      if (hits) hits.hidden = matches.length === 0;
      box.hidden = !found && matches.length === 0;
    }

    function paintLogo(el) {
      if (!el) return;
      var src = name && name.value.trim() ? (short && short.value.trim() ? short.value : name.value) : '';
      el.innerHTML = '';
      if (logoUrl) {
        var img = node('img');
        img.src = logoUrl;
        img.alt = '';
        el.appendChild(img);
      } else {
        el.appendChild(node('span', initials(src)));
      }
    }

    function filterDefs() {
      var term = norm(qdef ? qdef.value.trim() : '');
      var shown = 0;
      all(form, '[data-qis-def]').forEach(function (d) {
        var ok = !term || norm(d.getAttribute('data-search')).indexOf(term) !== -1;
        d.hidden = !ok;
        if (ok) shown++;
      });
      var none = form.querySelector('[data-qis-qdef-none]');
      if (none) none.hidden = shown > 0;
    }

    function countDefs() {
      var el = form.querySelector('[data-qis-ndef]');
      var n = all(form, 'input[name="definition_ids[]"]:checked').length;
      if (el) el.textContent = String(n);
      return n;
    }

    function summary() {
      var box = form.querySelector('[data-qis-summary2]');
      if (!box) return;
      box.innerHTML = '';
      var dl = node('dl', null, 'qis-sum');
      var seen = {};
      all(form, '[data-sum]').forEach(function (el) {
        var label = el.getAttribute('data-label') || '';
        var value = '';
        if (el.type === 'radio') {
          if (!el.checked) return;
          value = label || el.value;
          label = 'Type';
        } else if (el.type === 'file') {
          value = el.files && el.files[0] ? el.files[0].name : '';
        } else if (el.tagName === 'SELECT') {
          value = el.value ? (el.options[el.selectedIndex].textContent || '').trim() : '';
        } else {
          value = el.value.trim();
        }
        if (!value || seen[label]) return;
        seen[label] = true;
        var row = node('div');
        row.appendChild(node('dt', label));
        var dd = node('dd', value);
        if (el.name !== 'issuer_kind' && el.name !== 'country' && el.name !== 'unit_id') dd.setAttribute('translate', 'no');
        row.appendChild(dd);
        dl.appendChild(row);
      });
      var defs = all(form, 'input[name="definition_ids[]"]:checked');
      if (defs.length) {
        var row = node('div');
        row.appendChild(node('dt', 'Qualifications délivrées'));
        var dd = node('dd');
        var ul = node('ul', null, 'qis-chips');
        defs.forEach(function (d) {
          var li = node('li', d.getAttribute('data-label'));
          li.setAttribute('translate', 'no');
          ul.appendChild(li);
        });
        dd.appendChild(ul);
        row.appendChild(dd);
        dl.appendChild(row);
      }
      box.appendChild(dl);
      paintLogo(form.querySelector('[data-qis-recap-logo]'));
    }

    form.addEventListener('input', function (ev) {
      if (ev.target === name || ev.target === short) {
        showErr(form, '1', false);
        clearTimeout(timer);
        timer = setTimeout(suggest, 150);
        paintLogo(form.querySelector('[data-qis-logo-preview]'));
      }
      if (ev.target === qdef) filterDefs();
      if (ev.target === web) showErr(form, 'web', false);
    });
    form.addEventListener('change', function (ev) {
      if (ev.target === logo) {
        if (logoUrl) URL.revokeObjectURL(logoUrl);
        logoUrl = '';
        var f = logo.files && logo.files[0];
        var ok = !f || (f.size <= 2 * 1024 * 1024 && /^image\/(png|webp|svg\+xml)$/.test(f.type));
        showErr(form, 'logo', !ok);
        if (f && ok && window.URL) logoUrl = URL.createObjectURL(f);
        paintLogo(form.querySelector('[data-qis-logo-preview]'));
      }
      if (ev.target.name === 'definition_ids[]') countDefs();
    });
    form.addEventListener('click', function (ev) {
      var imp = ev.target.closest('[data-qis-import-key]');
      if (!imp) return;
      var key = imp.getAttribute('data-qis-import-key');
      closeDialog(form.closest('dialog'));
      var wz = openDialog('emetteurs-reels');
      if (wz && wz.spec.preselect && wz.spec.preselect(key)) wz.show(3);
    });

    return {
      onShow: function (n) {
        if (n === 2) { filterDefs(); countDefs(); paintLogo(form.querySelector('[data-qis-logo-preview]')); }
        if (n === 3) summary();
      },
      valid: function (n) {
        if (n === 1) {
          var ok = showErr(form, '1', !name || name.value.trim() === '');
          if (!ok && name) name.focus();
          return ok;
        }
        if (n === 2) {
          var v = web ? web.value.trim() : '';
          var webOk = showErr(form, 'web', v !== '' && !/^https?:\/\/[a-z0-9.-]+\.[a-z]{2,}(:\d{2,5})?(\/\S*)?$/i.test(v));
          var logoErr = form.querySelector('[data-qis-err="logo"]');
          return webOk && !(logoErr && !logoErr.hidden);
        }
        return true;
      }
    };
  }

  // ——— Démarrage ———
  list.init();
  if (location.hash) {
    var target = location.hash.slice(1);
    if (!openDialog(target)) openFold(target);
  }
})();
