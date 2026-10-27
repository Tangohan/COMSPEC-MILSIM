/* Back-office — Diffusion des rapports : éditeur de règle, ordre, simulateur. */
(() => {
  'use strict';
  const root = document.getElementById('bo-rt');
  if (!root) return;
  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let data = { rules: [] };
  try { data = JSON.parse($('#bo-rt-data').textContent || '{}'); } catch (e) { /* rien */ }

  // ---------------------------------------------------------------- éditeur
  const drawer = $('#bo-rt-editor');
  // Panneau en position fixe : un ancêtre animé (transform) le cantonnait à la zone de contenu.
  // Rattaché à la coque (.ath-shell, qui porte les jetons de thème), il recouvre bien toute la fenêtre.
  (root.closest('.ath-shell') || document.body).appendChild(drawer);
  const form = drawer.querySelector('form');
  const pickerKeys = ['roles', 'units', 'users', 'escalate_roles'];

  function setChecks(name, values) {
    const set = new Set((values || []).map(String));
    $$(`input[name="${name}[]"]`, form).forEach(i => { i.checked = set.has(i.value); });
  }
  function legacy(key, values) {
    // Valeurs enregistrées qui ne sont pas dans l'annuaire (anciennes saisies libres).
    const picker = $(`[data-picker="${key}"]`, form);
    const known = new Set($$(`input[name="${key}[]"]`, form).map(i => i.value));
    const extra = (values || []).map(String).filter(v => !known.has(v));
    const box = $('[data-legacy]', picker);
    const hidden = $(`input[name="${key}_extra"]`, form);
    if (hidden) hidden.value = extra.join('\n');
    box.hidden = extra.length === 0;
    box.innerHTML = extra.length ? `<p class="bo-rt-hint">Valeurs saisies à la main, introuvables dans l’annuaire (personne ne les reçoit) :</p>` + extra.map(v => `<span class="ath-tag ath-tag--warn">${esc(v)} <button type="button" data-drop="${esc(v)}" aria-label="Retirer ${esc(v)}">×</button></span>`).join(' ') : '';
  }
  function counts() {
    pickerKeys.forEach(k => {
      const p = $(`[data-picker="${k}"]`, form); if (!p) return;
      const n = $$('input[type=checkbox]:checked', p).length;
      const c = $('[data-count]', p); if (c) c.textContent = n ? `· ${n} choisi(s)` : '';
    });
  }
  function open(rule, title) {
    form.reset();
    $$('.bo-rt-filter', form).forEach(f => { f.value = ''; filter(f); });
    form.elements.namedItem('id').value = rule.id || '';
    form.rule_name.value = rule.rule_name || '';
    form.priority_order.value = rule.priority_order || (data.rules.length + 1) * 10;
    form.escalate_after_minutes.value = rule.escalate_after_minutes || 0;
    form.keywords.value = (rule.keywords || []).join(', ');
    form.is_active.checked = rule.is_active !== false;
    setChecks('report_types', rule.report_types);
    setChecks('priorities', rule.priorities);
    ['roles', 'units', 'users', 'escalate_roles'].forEach(k => setChecks(k, rule[k]));
    ['roles', 'units'].forEach(k => legacy(k, rule[k]));
    drawer.querySelector('#bo-rt-ed-title').textContent = title;
    counts();
    drawer.hidden = false;
    form.rule_name.focus();
  }
  const onClick = e => {
    const ed = e.target.closest('[data-edit]');
    if (ed) {
      const id = ed.dataset.edit;
      if (id === 'new') open({}, 'Nouvelle règle');
      else { const r = data.rules.find(x => String(x.id) === id); if (r) open(r, 'Modifier la règle'); }
      return;
    }
    const pre = e.target.closest('[data-preset]');
    if (pre) { try { open(JSON.parse(pre.dataset.preset), 'Nouvelle règle'); } catch (err) { open({}, 'Nouvelle règle'); } return; }
    if (e.target.closest('[data-close]') || e.target === drawer) { drawer.hidden = true; return; }
    const drop = e.target.closest('[data-drop]');
    if (drop) {
      const picker = drop.closest('[data-picker]'); const key = picker.dataset.picker;
      const hidden = $(`input[name="${key}_extra"]`, form);
      const vals = hidden.value.split('\n').filter(v => v && v !== drop.dataset.drop);
      legacy(key, [...$$(`input[name="${key}[]"]:checked`, form).map(i => i.value), ...vals]);
      return;
    }
    const mv = e.target.closest('[data-move]');
    if (mv) {
      const li = mv.closest('li'), list = li.parentElement;
      const dir = Number(mv.dataset.move);
      const sib = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
      if (!sib) return;
      dir < 0 ? list.insertBefore(li, sib) : list.insertBefore(sib, li);
      $$('li', list).forEach((x, i, all) => {
        $('.bo-rt-rule__n', x).textContent = i + 1;
        $('[data-move="-1"]', x).disabled = i === 0;
        $('[data-move="1"]', x).disabled = i === all.length - 1;
      });
      $('#bo-rt-order-bar').hidden = false;
    }
  };
  root.addEventListener('click', onClick);
  drawer.addEventListener('click', onClick);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !drawer.hidden) drawer.hidden = true; });
  form.addEventListener('change', counts);
  function filter(input) {
    const q = input.value.trim().toLowerCase();
    $$('label[data-text]', input.closest('[data-picker]')).forEach(l => { l.hidden = q !== '' && !l.dataset.text.includes(q); });
  }
  $$('.bo-rt-filter', form).forEach(f => f.addEventListener('input', () => filter(f)));
  form.addEventListener('submit', e => {
    const dest = ['roles', 'units', 'users'].some(k => $$(`input[name="${k}[]"]:checked`, form).length > 0 || ($(`input[name="${k}_extra"]`, form)?.value || '').trim() !== '');
    if (!dest) { e.preventDefault(); alert('Choisissez au moins un destinataire : sans destinataire, la règle ne diffuse rien.'); return; }
    if (Number(form.escalate_after_minutes.value) > 0 && !$$('input[name="escalate_roles[]"]:checked', form).length) {
      e.preventDefault(); alert('Escalade : choisissez au moins une fonction à prévenir, ou mettez le délai à 0.');
    }
  });

  // ---------------------------------------------------------------- simulateur
  const sim = $('#bo-rt-sim');
  if (sim) {
    const fake = $('.bo-rt-sim__fake', sim);
    sim.report_id.addEventListener('change', () => { fake.hidden = sim.report_id.value !== ''; });
    sim.addEventListener('submit', async e => {
      e.preventDefault();
      const out = $('#bo-rt-sim-out');
      out.innerHTML = '<p class="bo-rt-emptyline">Évaluation…</p>';
      const body = new URLSearchParams(new FormData(sim));
      body.set('_csrf_token', root.dataset.csrf);
      try {
        const r = await fetch(root.dataset.base + '/simuler', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const j = await r.json();
        if (!j.ok) throw new Error(j.message || 'Échec');
        const applied = j.results.filter(x => x.applies);
        out.innerHTML = `
          <div class="ath-panel bo-rt-sim__verdict ${applied.length ? 'is-ok' : 'is-none'}">
            <p class="bo-rt-flow__label">Résultat</p>
            <p class="bo-rt-sim__big">${applied.length ? `${applied.length} règle(s) déclenchée(s) · ${j.recipients.length} destinataire(s)` : 'Aucune règle ne se déclenche : le rapport arriverait sans destinataire.'}</p>
            ${j.recipients.length ? `<p>${j.recipients.map(d => `<span class="ath-tag ath-tag--${d.known ? 'ok' : 'warn'}" title="${esc(d.rule)}">${esc(d.label)}${d.known ? '' : ' ⚠'}</span>`).join(' ')}</p>` : ''}
          </div>
          <ol class="bo-rt-sim__list">${j.results.map(x => `
            <li class="${x.applies ? 'is-hit' : ''}">
              <header><b>${esc(x.name)}</b> <span class="ath-tag ath-tag--${x.applies ? 'ok' : (x.active ? 'neut' : 'neut')}">${x.applies ? 'Se déclenche' : (x.active ? 'Ne se déclenche pas' : 'Désactivée')}</span></header>
              <ul>${x.reasons.map(r => `<li class="${r.ok ? 'ok' : 'ko'}">${r.ok ? '✓' : '✕'} ${esc(r.text)}</li>`).join('')}</ul>
            </li>`).join('') || '<li>Aucune règle.</li>'}</ol>`;
      } catch (err) { out.innerHTML = `<p class="bo-rt-emptyline">${esc(err.message)}</p>`; }
    });
  }
})();
