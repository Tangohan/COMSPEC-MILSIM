/**
 * Fenêtre « autorisations liées aux emplois » (page attributions rôles métier).
 */
(function () {
  'use strict';

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  var dlg = document.getElementById('pjr-member-perms-dialog');
  var titleEl = document.getElementById('pjr-member-perms-title');
  var bodyEl = document.getElementById('pjr-member-perms-body');
  var baseUrl = typeof window.__PJR_MEMBER_PERM_URL === 'string' ? window.__PJR_MEMBER_PERM_URL : '';

  if (!dlg || !titleEl || !bodyEl || !baseUrl) {
    return;
  }

  function openDialog() {
    if (typeof dlg.showModal === 'function') {
      dlg.showModal();
    } else {
      dlg.setAttribute('open', '');
    }
  }

  function closeDialog() {
    if (typeof dlg.close === 'function') {
      dlg.close();
    } else {
      dlg.removeAttribute('open');
    }
  }

  function renderLoading(name) {
    titleEl.textContent = name || 'Membre';
    bodyEl.innerHTML =
      '<p class="flex items-center gap-2 text-sm text-slate-600"><span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-emerald-600"></span> Chargement…</p>';
    openDialog();
  }

  function renderError(msg) {
    bodyEl.innerHTML = '<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">' + esc(msg || 'Impossible de charger l’aperçu.') + '</p>';
  }

  function renderOk(data) {
    titleEl.textContent = 'Autorisations liées aux emplois — ' + (data.member && data.member.display_name ? data.member.display_name : 'Membre');

    var html = '';
    if (data.disclaimer) {
      html +=
        '<p class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-700">' +
        esc(data.disclaimer) +
        '</p>';
    }

    var roles = data.assigned_roles || [];
    if (roles.length === 0) {
      html += '<p class="text-sm text-slate-600">Aucun emploi n’est attribué à cette personne pour l’instant.</p>';
      bodyEl.innerHTML = html;
      return;
    }

    html += '<h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Emplois attribués</h3><ul class="mb-5 space-y-2">';
    roles.forEach(function (r) {
      var pc = typeof r.permission_count === 'number' ? r.permission_count : parseInt(r.permission_count, 10) || 0;
      var pri = r.primary ? ' <span class="text-emerald-700">(principal)</span>' : '';
      var pcLabel =
        pc > 0
          ? pc + (pc > 1 ? ' autorisations liées dans le référentiel' : ' autorisation liée dans le référentiel')
          : 'Aucune autorisation liée dans le référentiel';
      html +=
        '<li class="rounded-lg border border-slate-100 bg-white px-3 py-2 text-sm shadow-sm">' +
        '<span class="font-semibold text-slate-900">' +
        esc(r.name || 'Emploi') +
        '</span>' +
        pri +
        '<span class="mt-0.5 block text-xs text-slate-600">' +
        esc(pcLabel) +
        '</span></li>';
    });
    html += '</ul>';

    var perms = data.permissions || [];
    if (perms.length === 0) {
      html +=
        '<p class="text-sm text-slate-600">Aucune autorisation n’est associée à ces emplois dans le référentiel. Vous pouvez en ajouter depuis la fiche de chaque emploi (référentiel).</p>';
      bodyEl.innerHTML = html;
      return;
    }

    html +=
      '<h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Liste fusionnée (' +
      (data.distinct_count != null ? esc(String(data.distinct_count)) : String(perms.length)) +
      ' distinctes)</h3>';

    var byMod = {};
    perms.forEach(function (p) {
      var k = p.module_label || 'Autre';
      if (!byMod[k]) {
        byMod[k] = [];
      }
      byMod[k].push(p.name || '');
    });

    Object.keys(byMod)
      .sort(function (a, b) {
        return a.localeCompare(b, 'fr');
      })
      .forEach(function (k) {
        html += '<section class="mb-4 last:mb-0"><h4 class="text-xs font-semibold text-slate-700">' + esc(k) + '</h4>';
        html += '<ul class="mt-1.5 list-disc space-y-1 pl-5 text-sm text-slate-800">';
        byMod[k].forEach(function (n) {
          if (n) {
            html += '<li>' + esc(n) + '</li>';
          }
        });
        html += '</ul></section>';
      });

    bodyEl.innerHTML = html;
  }

  document.querySelectorAll('.pjr-open-member-perms').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var uid = btn.getAttribute('data-user-id');
      if (!uid) {
        return;
      }
      var name = btn.getAttribute('data-member-name') || '';
      renderLoading(name);
      var url = baseUrl + (baseUrl.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(uid);
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) {
          return r.text().then(function (t) {
            try {
              return { ok: r.ok, json: JSON.parse(t) };
            } catch (e) {
              return { ok: false, json: { ok: false, message: 'Réponse invalide du serveur.' } };
            }
          });
        })
        .then(function (pack) {
          if (!pack.json || !pack.json.ok) {
            renderError((pack.json && pack.json.message) || 'Réponse inattendue du serveur.');
            return;
          }
          renderOk(pack.json);
        })
        .catch(function () {
          renderError('Impossible de joindre le serveur.');
        });
    });
  });

  dlg.querySelectorAll('.pjr-member-perms-close').forEach(function (b) {
    b.addEventListener('click', function () {
      closeDialog();
    });
  });
})();
