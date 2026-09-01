(function () {
  var form = document.getElementById('doc-create-form') || document.getElementById('doc-edit-form');
  if (!form) return;

  function val(name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? String(el.value || '') : '';
  }

  function setOrigin(origin) {
    var authored = origin === 'authored';
    var uploadPanel = document.getElementById('doc-origin-upload');
    var writePanel = document.getElementById('doc-origin-authored');
    if (uploadPanel) uploadPanel.classList.toggle('hidden', authored);
    if (writePanel) writePanel.classList.toggle('hidden', !authored);
    var without = document.getElementById('doc-without-file');
    if (without && authored) {
      without.checked = true;
      without.dispatchEvent(new Event('change'));
    }
  }

  form.querySelectorAll('input[name="document_origin"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      setOrigin(radio.value);
    });
  });

  var initial = form.querySelector('input[name="document_origin"]:checked');
  setOrigin(initial ? initial.value : 'upload');

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function refreshPreview() {
    var root = document.getElementById('fm-live-preview');
    if (!root) return;
    var title = val('title') || 'Titre du document';
    var codes = val('manuscript_codes')
      .split(/\r\n|\r|\n/)
      .map(function (l) { return l.trim(); })
      .filter(Boolean)
      .map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; })
      .join('');
    var date = val('manuscript_issue_date');
    var restriction = val('manuscript_distribution');
    var destruction = val('manuscript_destruction');
    var hq = val('manuscript_issuing_authority');
    var foreword = val('manuscript_foreword');
    var names = form.querySelectorAll('[name="manuscript_sig_name[]"]');
    var ranks = form.querySelectorAll('[name="manuscript_sig_rank[]"]');
    var cmds = form.querySelectorAll('[name="manuscript_sig_command[]"]');
    var sigs = '';
    for (var i = 0; i < names.length; i++) {
      var n = String(names[i].value || '').trim();
      var r = ranks[i] ? String(ranks[i].value || '').trim() : '';
      var c = cmds[i] ? String(cmds[i].value || '').trim() : '';
      if (!n && !r && !c) continue;
      sigs += '<div class="fm-sig">'
        + '<p class="fm-sig-script">' + escapeHtml(n || ' ') + '</p>'
        + '<p class="fm-sig-name">' + escapeHtml(n) + '</p>'
        + (r ? '<p class="fm-sig-rank">' + escapeHtml(r) + '</p>' : '')
        + (c ? '<p class="fm-sig-cmd">' + escapeHtml(c) + '</p>' : '')
        + '</div>';
    }
    if (!sigs) {
      sigs = '<div class="fm-sig"><p class="fm-sig-script">Signature</p><p class="fm-sig-name">Nom</p><p class="fm-sig-rank">Grade / fonction</p><p class="fm-sig-cmd">Commandement</p></div>';
    }
    var bodyRaw = val('manuscript_body').trim();
    var bodyHtml = '';
    if (bodyRaw) {
      bodyRaw.split(/\n{2,}/).forEach(function (part) {
        part = part.trim();
        if (!part) return;
        bodyHtml += '<p>' + escapeHtml(part).replace(/\n/g, '<br>') + '</p>';
      });
    }
    root.querySelector('[data-fm="codes"]').innerHTML = codes || '<div>FM …</div>';
    root.querySelector('[data-fm="title"]').textContent = title;
    root.querySelector('[data-fm="date"]').textContent = date;
    root.querySelector('[data-fm="restriction"]').textContent = restriction;
    root.querySelector('[data-fm="destruction"]').textContent = destruction;
    root.querySelector('[data-fm="hq"]').textContent = hq;
    root.querySelector('[data-fm="foreword"]').textContent = foreword;
    root.querySelector('[data-fm="sigs"]').innerHTML = sigs;
    var bodyEl = root.querySelector('[data-fm="body"]');
    bodyEl.innerHTML = bodyHtml || '<p class="fm-body-empty">Le corps du document apparaîtra ici.</p>';
  }

  ['input', 'change'].forEach(function (evt) {
    form.addEventListener(evt, function (e) {
      var t = e.target;
      if (!t || !t.name) return;
      if (t.name === 'title' || t.name.indexOf('manuscript_') === 0) {
        refreshPreview();
      }
    });
  });

  var addBtn = document.getElementById('fm-add-signature');
  var list = document.getElementById('fm-signature-list');
  if (addBtn && list) {
    addBtn.addEventListener('click', function () {
      if (list.querySelectorAll('.fm-sig-row').length >= 8) return;
      var row = document.createElement('div');
      row.className = 'fm-sig-row grid gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 sm:grid-cols-3';
      row.innerHTML = '<input type="text" name="manuscript_sig_name[]" placeholder="Nom" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />'
        + '<input type="text" name="manuscript_sig_rank[]" placeholder="Grade ou fonction" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />'
        + '<input type="text" name="manuscript_sig_command[]" placeholder="Commandement" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />';
      list.appendChild(row);
      refreshPreview();
    });
  }

  refreshPreview();
})();
