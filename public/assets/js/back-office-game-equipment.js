/**
 * Back-office — Équipement des joueurs et Kits par rôle
 * (views/admin/organization/game_equipment.php, game_role_kits.php, game_role_kit_form.php).
 *
 * - Classements : « Voir le top 10 ».
 * - Mods : filtre texte, masquer les DLC.
 * - Kits : confirmation de suppression ; sélecteur d’objets avec recherche dans le catalogue observé,
 *   noms affichés, mods requis calculés en direct, lignes de contenu ajoutées / retirées.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-ge]');
  if (!root) {
    return;
  }

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '');
  }

  // ── Classements ───────────────────────────────────────────────────
  root.querySelectorAll('[data-ge-more]').forEach(function (btn) {
    var label = btn.textContent;
    btn.addEventListener('click', function () {
      var card = btn.closest('[data-ge-rank]');
      if (!card) {
        return;
      }
      var open = card.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.textContent = open ? 'Voir le top 5' : label;
    });
  });

  // ── Table des mods ────────────────────────────────────────────────
  var hideDlc = root.querySelector('[data-ge-hide-dlc]');
  var filterInput = root.querySelector('[data-ge-filter]');
  function applyModFilter() {
    var table = filterInput ? document.querySelector(filterInput.getAttribute('data-ge-filter')) : document.getElementById('ge-mods-table');
    if (!table) {
      return;
    }
    var q = filterInput ? norm(filterInput.value.trim()) : '';
    var noDlc = hideDlc ? hideDlc.checked : false;
    table.querySelectorAll('[data-ge-row]').forEach(function (row) {
      var okText = q === '' || norm(row.textContent).indexOf(q) !== -1;
      var okDlc = !noDlc || row.getAttribute('data-ge-dlc') !== '1';
      row.hidden = !(okText && okDlc);
    });
  }
  if (filterInput) {
    filterInput.addEventListener('input', applyModFilter);
  }
  if (hideDlc) {
    hideDlc.addEventListener('change', applyModFilter);
  }

  // ── Confirmation ──────────────────────────────────────────────────
  root.querySelectorAll('form[data-ge-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-ge-confirm') || 'Confirmer ?')) {
        e.preventDefault();
      }
    });
  });

  // ── Formulaire de kit ─────────────────────────────────────────────
  var form = root.querySelector('[data-ge-kit-form]');
  if (!form) {
    return;
  }
  var catalogue = [];
  try {
    var raw = root.querySelector('[data-ge-catalogue]');
    catalogue = raw ? JSON.parse(raw.textContent || '[]') : [];
  } catch (err) {
    catalogue = [];
  }
  var byClass = {};
  var modsSet = {};
  catalogue.forEach(function (it) {
    it.s = norm(it.n + ' ' + it.c + ' ' + it.m);
    byClass[it.c.toLowerCase()] = it;
    if (it.m) {
      modsSet[it.m] = true;
    }
  });

  var picker = root.querySelector('[data-ge-picker]');
  var pickerQ = picker ? picker.querySelector('[data-ge-picker-q]') : null;
  var pickerKind = picker ? picker.querySelector('[data-ge-picker-kind]') : null;
  var pickerMod = picker ? picker.querySelector('[data-ge-picker-mod]') : null;
  var pickerList = picker ? picker.querySelector('[data-ge-picker-list]') : null;
  var pickerInfo = picker ? picker.querySelector('[data-ge-picker-info]') : null;
  var currentInput = null;
  var canPick = !!(picker && typeof picker.showModal === 'function');

  if (pickerMod) {
    Object.keys(modsSet)
      .sort(function (a, b) { return a.localeCompare(b); })
      .forEach(function (m) {
        var o = document.createElement('option');
        o.value = m;
        o.textContent = m;
        o.setAttribute('translate', 'no');
        pickerMod.appendChild(o);
      });
  }

  function slotName(slot) {
    var input = slot.querySelector('[data-ge-class]');
    var out = slot.querySelector('[data-ge-name]');
    if (!input || !out) {
      return;
    }
    var v = input.value.trim();
    var it = v ? byClass[v.toLowerCase()] : null;
    out.textContent = it ? it.n + (it.m ? ' · ' + it.m : '') : (v ? 'Jamais vu en jeu' : '');
    out.classList.toggle('is-unknown', !!v && !it);
  }

  function refreshSummary() {
    var seen = {};
    var mods = {};
    var unknown = false;
    form.querySelectorAll('[data-ge-class]').forEach(function (input) {
      var v = input.value.trim();
      if (!v) {
        return;
      }
      seen[v.toLowerCase()] = true;
      var it = byClass[v.toLowerCase()];
      if (!it) {
        unknown = true;
        return;
      }
      if (it.m && it.m.toLowerCase() !== 'arma 3') {
        mods[it.m] = true;
      }
    });
    var count = form.querySelector('[data-ge-count]');
    if (count) {
      count.textContent = String(Object.keys(seen).length);
    }
    var box = form.querySelector('[data-ge-mods]');
    if (box) {
      box.innerHTML = '';
      var list = Object.keys(mods).sort(function (a, b) { return a.localeCompare(b); });
      if (list.length === 0) {
        var soft = document.createElement('span');
        soft.className = 'bo-ge-chip bo-ge-chip--soft';
        soft.textContent = 'Arma 3 seul';
        box.appendChild(soft);
      }
      list.forEach(function (m) {
        var chip = document.createElement('span');
        chip.className = 'bo-ge-chip';
        chip.setAttribute('translate', 'no');
        chip.textContent = m;
        box.appendChild(chip);
      });
    }
    var warn = form.querySelector('[data-ge-unknown]');
    if (warn) {
      warn.hidden = !unknown;
    }
  }

  function bindSlot(slot) {
    var input = slot.querySelector('[data-ge-class]');
    var btn = slot.querySelector('[data-ge-pick]');
    if (btn && canPick) {
      btn.hidden = false;
      btn.addEventListener('click', function () {
        openPicker(input, slot.getAttribute('data-ge-kind') || '');
      });
    }
    if (input) {
      input.addEventListener('input', function () {
        slotName(slot);
        refreshSummary();
      });
    }
    slotName(slot);
  }

  function renderPicker() {
    if (!pickerList) {
      return;
    }
    var q = norm(pickerQ ? pickerQ.value.trim() : '');
    var terms = q ? q.split(/\s+/) : [];
    var kind = pickerKind ? pickerKind.value : '';
    var mod = pickerMod ? pickerMod.value : '';
    var hits = [];
    for (var i = 0; i < catalogue.length; i++) {
      var it = catalogue[i];
      if (kind && it.k !== kind) {
        continue;
      }
      if (mod && it.m !== mod) {
        continue;
      }
      var ok = true;
      for (var t = 0; t < terms.length; t++) {
        if (it.s.indexOf(terms[t]) === -1) {
          ok = false;
          break;
        }
      }
      if (ok) {
        hits.push(it);
      }
    }
    pickerList.innerHTML = '';
    var max = 200;
    hits.slice(0, max).forEach(function (it) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bo-ge-picker__item';
      b.setAttribute('role', 'option');
      b.setAttribute('translate', 'no');
      var name = document.createElement('span');
      name.className = 'bo-ge-picker__name';
      name.textContent = it.n;
      var meta = document.createElement('span');
      meta.className = 'bo-ge-picker__meta';
      meta.textContent = it.c + (it.m ? ' · ' + it.m : '');
      var players = document.createElement('span');
      players.className = 'bo-ge-picker__players';
      players.textContent = String(it.p);
      players.title = 'Joueurs l’ayant porté';
      b.appendChild(name);
      b.appendChild(meta);
      b.appendChild(players);
      b.addEventListener('click', function () {
        choose(it.c);
      });
      li.appendChild(b);
      pickerList.appendChild(li);
    });
    if (pickerInfo) {
      pickerInfo.textContent = hits.length === 0
        ? 'Aucun objet ne correspond. Changez de famille ou de mod, ou saisissez la classe à la main.'
        : (hits.length > max ? max + ' premiers sur ' + hits.length + ' objets, les plus portés d’abord. Affinez la recherche.' : hits.length + ' objet(s), les plus portés d’abord.');
    }
  }

  function openPicker(input, kind) {
    if (!picker) {
      return;
    }
    currentInput = input;
    if (pickerKind) {
      pickerKind.value = kind && pickerKind.querySelector('option[value="' + kind + '"]') ? kind : '';
    }
    if (pickerMod) {
      pickerMod.value = '';
    }
    if (pickerQ) {
      pickerQ.value = '';
    }
    renderPicker();
    picker.showModal();
    if (pickerQ) {
      pickerQ.focus();
    }
  }

  function choose(cls) {
    if (currentInput) {
      currentInput.value = cls;
      currentInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (picker && picker.open) {
      picker.close();
    }
    if (currentInput) {
      currentInput.focus();
    }
  }

  if (picker) {
    [pickerQ, pickerKind, pickerMod].forEach(function (el) {
      if (el) {
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', renderPicker);
      }
    });
    picker.querySelectorAll('[data-ge-picker-close]').forEach(function (b) {
      b.addEventListener('click', function () {
        picker.close();
      });
    });
    var clear = picker.querySelector('[data-ge-picker-clear]');
    if (clear) {
      clear.addEventListener('click', function () {
        choose('');
      });
    }
    picker.addEventListener('click', function (e) {
      if (e.target === picker) {
        picker.close();
      }
    });
  }

  form.querySelectorAll('[data-ge-slot]').forEach(bindSlot);

  // Lignes de contenu
  function bindRemove(row) {
    var rm = row.querySelector('[data-ge-cargo-remove]');
    if (!rm) {
      return;
    }
    rm.hidden = false;
    rm.addEventListener('click', function () {
      var list = row.parentNode;
      if (list && list.querySelectorAll('[data-ge-cargo-row]').length > 1) {
        row.remove();
      } else {
        row.querySelectorAll('input').forEach(function (i) {
          i.value = i.type === 'number' ? '1' : '';
        });
        var slot = row.querySelector('[data-ge-slot]');
        if (slot) {
          slotName(slot);
        }
      }
      refreshSummary();
    });
  }

  form.querySelectorAll('[data-ge-cargo]').forEach(function (box) {
    var container = box.getAttribute('data-ge-cargo');
    var list = box.querySelector('[data-ge-cargo-list]');
    var add = box.querySelector('[data-ge-cargo-add]');
    box.querySelectorAll('[data-ge-cargo-row]').forEach(bindRemove);
    if (!add || !list) {
      return;
    }
    add.hidden = false;
    add.addEventListener('click', function () {
      var tpl = list.querySelector('[data-ge-cargo-row]');
      if (!tpl) {
        return;
      }
      var next = parseInt(box.getAttribute('data-ge-next') || '0', 10) || 0;
      box.setAttribute('data-ge-next', String(next + 1));
      var row = tpl.cloneNode(true);
      row.querySelectorAll('input').forEach(function (i) {
        i.name = i.name.replace(/^cargo\[[^\]]+\]\[\d+\]/, 'cargo[' + container + '][' + next + ']');
        i.value = i.type === 'number' ? '1' : '';
      });
      row.querySelectorAll('[data-ge-pick]').forEach(function (b) {
        var clone = b.cloneNode(true);
        b.parentNode.replaceChild(clone, b);
      });
      row.querySelectorAll('[data-ge-cargo-remove]').forEach(function (b) {
        var clone = b.cloneNode(true);
        b.parentNode.replaceChild(clone, b);
      });
      list.appendChild(row);
      var slot = row.querySelector('[data-ge-slot]');
      if (slot) {
        var input = slot.querySelector('[data-ge-class]');
        if (input) {
          var fresh = input.cloneNode(true);
          input.parentNode.replaceChild(fresh, input);
        }
        bindSlot(slot);
      }
      bindRemove(row);
      var first = row.querySelector('[data-ge-pick]');
      if (first && canPick) {
        first.click();
      }
    });
  });

  refreshSummary();
})();
