/**
 * Compositeur forum : aperçu markdown, brouillon local, pièces jointes (progression, réordonnancement),
 * glisser-déposer, collage, compteurs, conseils rédactionnels, mentions (@).
 * Expose : window.ForumComposer.markdownToHtml, .init
 */
(function (global) {
  'use strict';

  function escapeHtml(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function slugifyAnchor(text) {
    return String(text)
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '') || 'section';
  }

  /** Aligné sur app/Support/forum_helpers.php :: forum_markdown_to_html */
  function markdownToHtml(text) {
    if (!text || !String(text).trim()) {
      return '<p class="text-slate-500 italic">Le rendu s’affichera ici au fur et à mesure.</p>';
    }
    var raw = String(text);
    var s = escapeHtml(raw);

    var codePh = [];
    var ci = 0;
    s = s.replace(/```(\w*)\s*([\s\S]*?)```/g, function () {
      var lang = arguments[1];
      var code = arguments[2];
      var key = '@@CODE' + ci++ + '@@';
      codePh.push(
        '<pre class="my-2 p-3 bg-slate-100 border border-slate-200 rounded text-sm overflow-x-auto text-slate-900"><code>' +
          code +
          '</code></pre>'
      );
      return key;
    });

    var fencePh = [];
    var fi = 0;
    s = s.replace(/:::(spoiler|info|warning)\s*\n([\s\S]*?)\n:::/g, function () {
      var kind = arguments[1];
      var inner = arguments[2];
      var key = '@@FENCE' + fi++ + '@@';
      if (kind === 'spoiler') {
        fencePh.push(
          '<details class="my-2 rounded-lg border border-slate-200 bg-slate-50 p-2"><summary class="cursor-pointer text-sm font-semibold text-slate-700">Contenu masqué</summary><div class="mt-2 text-sm text-slate-800">' +
            inner.replace(/\n/g, '<br>') +
            '</div></details>'
        );
      } else if (kind === 'info') {
        fencePh.push(
          '<div class="my-2 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950">' +
            inner.replace(/\n/g, '<br>') +
            '</div>'
        );
      } else {
        fencePh.push(
          '<div class="my-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">' +
            inner.replace(/\n/g, '<br>') +
            '</div>'
        );
      }
      return key;
    });

    s = processTables(s);

    var out = [];
    var inBlockquote = false;
    var inUl = false;
    var inOl = false;
    var lines = s.split('\n');
    for (var li = 0; li < lines.length; li++) {
      var line = lines[li];
      var trimmed = line.replace(/^\s+/, '');
      if (/^@@CODE\d+@@$/.test(trimmed) || /^@@FENCE\d+@@$/.test(trimmed)) {
        if (inBlockquote) {
          out.push('</blockquote>');
          inBlockquote = false;
        }
        if (inUl) {
          out.push('</ul>');
          inUl = false;
        }
        if (inOl) {
          out.push('</ol>');
          inOl = false;
        }
        out.push(trimmed);
        continue;
      }
      var hm = trimmed.match(/^(\#{2,3})\s+(.+)$/);
      if (hm) {
        if (inBlockquote) {
          out.push('</blockquote>');
          inBlockquote = false;
        }
        if (inUl) {
          out.push('</ul>');
          inUl = false;
        }
        if (inOl) {
          out.push('</ol>');
          inOl = false;
        }
        var level = hm[1].length;
        var ht = hm[2].replace(/\s+\{#([a-zA-Z0-9_-]+)\}\s*$/, '');
        var idm = hm[2].match(/\{#([a-zA-Z0-9_-]+)\}\s*$/);
        var aid = idm ? idm[1] : slugifyAnchor(ht);
        var tag = level === 2 ? 'h2' : 'h3';
        out.push(
          '<' +
            tag +
            ' id="' +
            escapeHtml(aid) +
            '" class="font-bold text-slate-900 mt-3 mb-1">' +
            ht +
            '</' +
            tag +
            '>'
        );
        continue;
      }
      if (/^(?:\*{3}|-{3}|_{3})\s*$/.test(trimmed)) {
        if (inBlockquote) {
          out.push('</blockquote>');
          inBlockquote = false;
        }
        if (inUl) {
          out.push('</ul>');
          inUl = false;
        }
        if (inOl) {
          out.push('</ol>');
          inOl = false;
        }
        out.push('<hr class="my-3 border-slate-200">');
        continue;
      }
      if (/^&gt;\s?(.*)$/.test(trimmed)) {
        var qm = trimmed.match(/^&gt;\s?(.*)$/);
        if (inUl) {
          out.push('</ul>');
          inUl = false;
        }
        if (inOl) {
          out.push('</ol>');
          inOl = false;
        }
        if (!inBlockquote) {
          out.push('<blockquote class="border-l-2 border-emerald-400 pl-4 my-1.5 text-slate-600">');
          inBlockquote = true;
        }
        out.push(qm[1] + '<br>');
        continue;
      }
      if (/^[-*]\s+(.+)$/.test(trimmed)) {
        var um = trimmed.match(/^[-*]\s+(.+)$/);
        if (inBlockquote) {
          out.push('</blockquote>');
          inBlockquote = false;
        }
        if (inOl) {
          out.push('</ol>');
          inOl = false;
        }
        if (!inUl) {
          out.push('<ul class="list-disc list-inside space-y-0.5 my-2 text-slate-700 pl-2">');
          inUl = true;
        }
        out.push('<li>' + um[1] + '</li>');
        continue;
      }
      if (/^\d+\.\s+(.+)$/.test(trimmed)) {
        var om = trimmed.match(/^\d+\.\s+(.+)$/);
        if (inBlockquote) {
          out.push('</blockquote>');
          inBlockquote = false;
        }
        if (inUl) {
          out.push('</ul>');
          inUl = false;
        }
        if (!inOl) {
          out.push('<ol class="list-decimal list-inside space-y-0.5 my-2 text-slate-700 pl-2">');
          inOl = true;
        }
        out.push('<li>' + om[1] + '</li>');
        continue;
      }
      if (inBlockquote) {
        out.push('</blockquote>');
        inBlockquote = false;
      }
      if (inUl) {
        out.push('</ul>');
        inUl = false;
      }
      if (inOl) {
        out.push('</ol>');
        inOl = false;
      }
      out.push(line + '\n');
    }
    if (inBlockquote) out.push('</blockquote>');
    if (inUl) out.push('</ul>');
    if (inOl) out.push('</ol>');
    s = out.join('');

    for (var cj = 0; cj < codePh.length; cj++) {
      s = s.replace('@@CODE' + cj + '@@', codePh[cj]);
    }
    for (var fj = 0; fj < fencePh.length; fj++) {
      s = s.replace('@@FENCE' + fj + '@@', fencePh[fj]);
    }

    s = s.replace(/`([^`\n]+)`/g, '<code class="px-1 py-0.5 bg-slate-100 border border-slate-200 rounded text-xs text-slate-900">$1</code>');
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    s = s.replace(/_([^_]+)_/g, '<em>$1</em>');
    s = s.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (_, label, href) {
      var safe = escapeHtml(href);
      return (
        '<a href="' +
        safe +
        '" rel="noopener noreferrer" class="text-emerald-700 hover:text-emerald-600 underline">' +
        label +
        '</a>'
      );
    });
    s = s.replace(/\bhttps?:\/\/[^\s<>"'\[\]]+/gi, function (raw) {
      var trail = '';
      var u = raw;
      var pm = raw.match(/([.,;:!?]+)$/);
      if (pm) {
        trail = pm[1];
        u = raw.slice(0, -trail.length);
      }
      return (
        '<a href="' +
        u +
        '" target="_blank" rel="noopener noreferrer" class="text-orange-600 hover:text-orange-500 underline break-all">' +
        u +
        '</a>' +
        trail
      );
    });

    s = highlightMentionsInHtml(s);
    return s.replace(/\n/g, '<br>');
  }

  function processTables(s) {
    var rows = s.split('\n');
    var result = [];
    var i = 0;
    while (i < rows.length) {
      var row = rows[i];
      if (/^\|.*\|\s*$/.test(row.trim())) {
        var block = [];
        while (i < rows.length && /^\|.*\|\s*$/.test(rows[i].trim())) {
          block.push(rows[i]);
          i++;
        }
        if (block.length >= 2) {
          var sep = block[1].replace(/\s/g, '');
          if (/^\|?[-:|]+\|?$/.test(sep)) {
            var header = parseTableRow(block[0]);
            var bodyRows = [];
            for (var b = 2; b < block.length; b++) bodyRows.push(parseTableRow(block[b]));
            if (header.length) {
              var html =
                '<div class="my-2 overflow-x-auto"><table class="min-w-full text-sm border border-slate-200 rounded-lg overflow-hidden"><thead><tr>';
              for (var h = 0; h < header.length; h++) {
                html +=
                  '<th class="border border-slate-200 bg-slate-50 px-2 py-1 text-left font-semibold text-slate-800">' +
                  header[h] +
                  '</th>';
              }
              html += '</tr></thead><tbody>';
              for (var r = 0; r < bodyRows.length; r++) {
                html += '<tr>';
                var br = bodyRows[r];
                for (var c = 0; c < Math.max(br.length, header.length); c++) {
                  html +=
                    '<td class="border border-slate-200 px-2 py-1 text-slate-700">' +
                    (br[c] != null ? br[c] : '') +
                    '</td>';
                }
                html += '</tr>';
              }
              html += '</tbody></table></div>';
              result.push(html);
              continue;
            }
          }
        }
        for (var j = 0; j < block.length; j++) result.push(block[j]);
      } else {
        result.push(row);
        i++;
      }
    }
    return result.join('\n');
  }

  function parseTableRow(line) {
    var t = line.trim();
    if (t.startsWith('|')) t = t.slice(1);
    if (t.endsWith('|')) t = t.slice(0, -1);
    return t.split('|').map(function (c) {
      return c.trim();
    });
  }

  function highlightMentionsInHtml(html) {
    return html.replace(/@([^\s<&]+)/g, '<span class="forum-mention text-emerald-800 font-semibold">@$1</span>');
  }

  function countWords(t) {
    var m = String(t || '').trim().match(/[\p{L}\p{N}_-]+/gu);
    return m ? m.length : 0;
  }

  function readingMinutes(words) {
    return Math.max(1, Math.round(words / 200) || 1);
  }

  function lengthLabel(words) {
    if (words < 40) return 'court';
    if (words < 200) return 'moyen';
    return 'détaillé';
  }

  var EMOJI_RE = /[\u{1F300}-\u{1F9FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/gu;

  function stripEmojis(s) {
    return String(s || '').replace(EMOJI_RE, '').replace(/\s+/g, '').trim();
  }

  function isOnlyEmojiOrWhitespace(s) {
    var t = String(s || '').trim();
    if (!t) return true;
    var noEmoji = stripEmojis(t);
    return noEmoji.length === 0;
  }

  function hasAttachmentCue(s) {
    return /\b(voici|cf\.?\s*pi[eè]ce|pi[eè]ce\s+jointe|voir\s+joint|comme\s+vu)\b/i.test(s);
  }

  function harshWords(s) {
    var hits = [];
    var list = ['con\b', 'connard', 'merde', 'putain', 'fdp', 'encul'];
    list.forEach(function (w) {
      try {
        if (new RegExp(w, 'i').test(s)) hits.push('Ton pouvant paraître agressif');
      } catch (e) {}
    });
    return hits;
  }

  function validateMessage(text, attachmentCount, maxLen) {
    var t = String(text || '').trim();
    var issues = [];
    if (t.length < 1 && attachmentCount < 1) issues.push({ type: 'error', msg: 'Saisissez un message ou joignez un fichier.' });
    if (t.length > maxLen) issues.push({ type: 'error', msg: 'Message trop long.' });
    if (attachmentCount > 0 && t.length < 1) issues.push({ type: 'warn', msg: 'Ajoutez une courte phrase pour expliquer les pièces jointes.' });
    if (attachmentCount < 1 && hasAttachmentCue(t)) issues.push({ type: 'warn', msg: 'Vous évoquez une pièce jointe : joignez un fichier ou précisez le contexte.' });
    if (t.length > 0 && t.length < 25 && attachmentCount < 1) issues.push({ type: 'info', msg: 'Message très court : un peu de contexte aide les lecteurs.' });
    var onlyLink = /^\s*https?:\/\/\S+\s*$/i.test(t);
    if (onlyLink) issues.push({ type: 'warn', msg: 'Ajoutez une phrase autour du lien pour expliquer son intérêt.' });
    if (isOnlyEmojiOrWhitespace(t) && t.length > 0) issues.push({ type: 'warn', msg: 'Le message ne contient que des émoticônes : précisez votre demande en mots.' });
    var hw = harshWords(t);
    for (var i = 0; i < hw.length; i++) issues.push({ type: 'info', msg: hw[i] });
    var lines = t.split('\n').filter(function (l) {
      return l.trim().length > 0;
    });
    if (lines.length >= 3 && /^[-*]\s/.test(lines[0]) && /^[-*]\s/.test(lines[1])) {
      issues.push({ type: 'info', msg: 'Structure claire (liste) : bonne lisibilité.' });
    }
    return issues;
  }

  function wrapSelection(ta, before, after) {
    after = after == null ? before : after;
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var val = ta.value;
    var sel = val.slice(start, end);
    ta.value = val.slice(0, start) + before + sel + after + val.slice(end);
    var pos = start + before.length + sel.length + after.length;
    ta.selectionStart = ta.selectionEnd = pos;
    ta.focus();
  }

  function insertAtCursor(ta, text) {
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var val = ta.value;
    ta.value = val.slice(0, start) + text + val.slice(end);
    ta.selectionStart = ta.selectionEnd = start + text.length;
    ta.focus();
  }

  function structureText(ta) {
    var t = ta.value.trim();
    if (!t) return;
    var lines = t.split('\n').map(function (l) {
      return l.trim();
    });
    ta.value = '## Contexte\n\n' + lines.join('\n\n') + '\n\n## Suite\n\n';
  }

  function toBulletPoints(ta) {
    var lines = ta.value.split('\n');
    ta.value = lines
      .map(function (l) {
        var x = l.trim();
        if (!x) return '';
        if (/^[-*]\s/.test(x)) return x;
        return '- ' + x;
      })
      .join('\n');
  }

  function addTitle(ta) {
    insertAtCursor(ta, '## Titre\n\n');
  }

  function formatCleanup(ta) {
    ta.value = ta.value
      .split('\n')
      .map(function (l) {
        return l.replace(/\s+$/g, '');
      })
      .join('\n')
      .replace(/\n{3,}/g, '\n\n');
  }

  function init(opts) {
    opts = opts || {};
    var ta = typeof opts.textarea === 'string' ? document.querySelector(opts.textarea) : opts.textarea;
    if (!ta) return null;

    var previewEl =
      typeof opts.previewEl === 'string' ? document.querySelector(opts.previewEl) : opts.previewEl;
    var tabWrite = typeof opts.tabWrite === 'string' ? document.querySelector(opts.tabWrite) : opts.tabWrite;
    var tabPreview = typeof opts.tabPreview === 'string' ? document.querySelector(opts.tabPreview) : opts.tabPreview;
    var previewWrap =
      typeof opts.previewWrap === 'string' ? document.querySelector(opts.previewWrap) : opts.previewWrap;
    var writeWrap =
      typeof opts.writeWrap === 'string' ? document.querySelector(opts.writeWrap) : opts.writeWrap;

    var maxLen = opts.maxLen || 10000;
    var uploadUrl = opts.uploadUrl || '';
    var csrf = opts.csrf || '';
    var baseUrl = opts.baseUrl || '';
    var toast = typeof opts.toast === 'function' ? opts.toast : function () {};
    var extraFormData = opts.extraFormData || {};
    var maxFiles = opts.maxFiles || 5;

    var charCountEl =
      typeof opts.charCountEl === 'string' ? document.querySelector(opts.charCountEl) : opts.charCountEl;
    var smartMetaEl =
      typeof opts.smartMetaEl === 'string' ? document.querySelector(opts.smartMetaEl) : opts.smartMetaEl;
    var draftBadgeEl =
      typeof opts.draftBadgeEl === 'string' ? document.querySelector(opts.draftBadgeEl) : opts.draftBadgeEl;
    var qualityEl =
      typeof opts.qualityEl === 'string' ? document.querySelector(opts.qualityEl) : opts.qualityEl;

    var fileInput =
      typeof opts.fileInput === 'string' ? document.querySelector(opts.fileInput) : opts.fileInput;
    var listEl =
      typeof opts.attachmentListEl === 'string'
        ? document.querySelector(opts.attachmentListEl)
        : opts.attachmentListEl;
    var hiddenInput =
      typeof opts.hiddenAttachmentInput === 'string'
        ? document.querySelector(opts.hiddenAttachmentInput)
        : opts.hiddenAttachmentInput;
    var dropZone =
      typeof opts.dropZone === 'string' ? document.querySelector(opts.dropZone) : opts.dropZone;

    var draftKey = opts.draftKey || null;
    var draftInterval = opts.draftIntervalMs || 7500;

    var attachments = [];

    function syncHidden() {
      var ids = attachments
        .filter(function (a) {
          return a.id;
        })
        .map(function (a) {
          return a.id;
        });
      if (hiddenInput) hiddenInput.value = JSON.stringify(ids);
      if (typeof opts.onAttachmentIdsChange === 'function') opts.onAttachmentIdsChange(ids);
    }

    function renderAttachmentList() {
      if (!listEl) return;
      listEl.innerHTML = '';
      attachments.forEach(function (att, idx) {
        var row = document.createElement('div');
        row.className =
          'forum-att-row flex items-center gap-2 p-2 border border-slate-200 rounded-lg bg-white text-[10px]';
        row.setAttribute('draggable', 'true');
        row.dataset.index = String(idx);
        row.addEventListener('dragstart', function (e) {
          e.dataTransfer.setData('text/plain', String(idx));
          row.classList.add('opacity-50');
        });
        row.addEventListener('dragend', function () {
          row.classList.remove('opacity-50');
        });
        row.addEventListener('dragover', function (e) {
          e.preventDefault();
        });
        row.addEventListener('drop', function (e) {
          e.preventDefault();
          var from = parseInt(e.dataTransfer.getData('text/plain'), 10);
          if (isNaN(from) || from === idx) return;
          var tmp = attachments[from];
          attachments[from] = attachments[idx];
          attachments[idx] = tmp;
          renderAttachmentList();
          syncHidden();
          updatePreview();
        });

        var thumb = document.createElement('div');
        thumb.className = 'w-12 h-12 shrink-0 rounded border border-slate-200 overflow-hidden bg-slate-50 flex items-center justify-center';
        if (att.previewUrl) {
          thumb.innerHTML = '<img src="' + att.previewUrl + '" alt="" class="w-full h-full object-cover">';
        } else if (att.isPdf) {
          thumb.innerHTML = '<span class="font-black text-rose-700">PDF</span>';
        } else {
          thumb.innerHTML = '<span class="text-slate-400">—</span>';
        }
        row.appendChild(thumb);

        var meta = document.createElement('div');
        meta.className = 'flex-1 min-w-0';
        var nameEl = document.createElement('div');
        nameEl.className = 'font-bold text-slate-800 truncate';
        nameEl.textContent = att.name || 'Fichier';
        meta.appendChild(nameEl);
        var sizeEl = document.createElement('div');
        sizeEl.className = 'text-slate-500';
        sizeEl.textContent = att.sizeLabel || '';
        meta.appendChild(sizeEl);
        if (att.progress != null && att.progress < 100) {
          var bar = document.createElement('div');
          bar.className = 'h-1.5 mt-1 bg-slate-100 rounded overflow-hidden';
          var fill = document.createElement('div');
          fill.className = 'h-full bg-emerald-500 transition-all';
          fill.style.width = att.progress + '%';
          bar.appendChild(fill);
          meta.appendChild(bar);
        }
        row.appendChild(meta);

        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'text-rose-600 font-bold px-2';
        rm.textContent = '×';
        rm.addEventListener('click', function () {
          if (att.previewUrl) URL.revokeObjectURL(att.previewUrl);
          attachments.splice(idx, 1);
          renderAttachmentList();
          syncHidden();
          updatePreview();
          refreshQuality();
        });
        row.appendChild(rm);

        listEl.appendChild(row);
      });
    }

    function formatSize(n) {
      if (n < 1024) return n + ' o';
      if (n < 1048576) return (n / 1024).toFixed(1) + ' Ko';
      return (n / 1048576).toFixed(1) + ' Mo';
    }

    function uploadOne(file, attIndex) {
      var fd = new FormData();
      fd.append('_csrf_token', csrf);
      Object.keys(extraFormData).forEach(function (k) {
        if (extraFormData[k] != null && extraFormData[k] !== '') fd.append(k, String(extraFormData[k]));
      });
      fd.append('files[]', file);

      var xhr = new XMLHttpRequest();
      xhr.open('POST', uploadUrl);
      xhr.upload.onprogress = function (e) {
        if (!e.lengthComputable) return;
        var p = Math.round((e.loaded / e.total) * 100);
        if (attachments[attIndex]) attachments[attIndex].progress = p;
        renderAttachmentList();
      };
      xhr.onload = function () {
        try {
          var d = JSON.parse(xhr.responseText || '{}');
          if (d.success && d.files && d.files[0]) {
            attachments[attIndex].id = d.files[0].id;
            attachments[attIndex].progress = 100;
            if (d.files[0].url) attachments[attIndex].serverUrl = d.files[0].url;
            if (d.warnings && d.warnings.length) toast(d.warnings.join(' '));
          } else {
            attachments.splice(attIndex, 1);
            toast(d.error || 'Envoi du fichier impossible');
          }
        } catch (err) {
          attachments.splice(attIndex, 1);
          toast('Envoi du fichier impossible');
        }
        renderAttachmentList();
        syncHidden();
        updatePreview();
      };
      xhr.onerror = function () {
        attachments.splice(attIndex, 1);
        renderAttachmentList();
        syncHidden();
        toast('Erreur réseau lors de l’envoi');
      };
      xhr.send(fd);
    }

    function addFiles(fileList) {
      var files = Array.prototype.slice.call(fileList || []);
      files.forEach(function (file) {
        if (attachments.length >= maxFiles) {
          toast('Maximum ' + maxFiles + ' fichiers');
          return;
        }
        var isImg = file.type && file.type.indexOf('image/') === 0;
        var isPdf = file.type === 'application/pdf';
        if (!isImg && !isPdf) {
          toast('Seules les images et les PDF sont acceptés');
          return;
        }
        var previewUrl = isImg ? URL.createObjectURL(file) : null;
        var att = {
          id: null,
          name: file.name,
          sizeLabel: formatSize(file.size),
          progress: 0,
          previewUrl: previewUrl,
          isPdf: isPdf,
          file: file,
        };
        attachments.push(att);
        var idx = attachments.length - 1;
        renderAttachmentList();
        if (uploadUrl) uploadOne(file, idx);
      });
    }

    function updateCounters() {
      var v = ta.value;
      var len = v.length;
      var words = countWords(v);
      if (charCountEl) {
        charCountEl.textContent = len + ' / ' + maxLen + ' · ' + words + ' mot' + (words > 1 ? 's' : '');
      }
      if (smartMetaEl) {
        smartMetaEl.textContent =
          'Lecture ~ ' +
          readingMinutes(words) +
          ' min · ' +
          lengthLabel(words);
      }
    }

    function refreshQuality() {
      if (!qualityEl) return;
      var attCount = attachments.filter(function (a) {
        return a.id;
      }).length;
      var issues = validateMessage(ta.value, attCount, maxLen);
      qualityEl.innerHTML = '';
      issues.forEach(function (iss) {
        var span = document.createElement('span');
        span.className =
          'inline-flex items-center rounded px-2 py-0.5 text-[9px] font-semibold ' +
          (iss.type === 'error'
            ? 'bg-rose-100 text-rose-900'
            : iss.type === 'warn'
              ? 'bg-amber-100 text-amber-900'
              : 'bg-slate-100 text-slate-700');
        span.textContent = iss.msg;
        qualityEl.appendChild(span);
      });
    }

    function updatePreview() {
      if (!previewEl) return;
      var html = markdownToHtml(ta.value);
      var attHtml = '';
      attachments.forEach(function (a) {
        if (a.previewUrl) {
          attHtml +=
            '<div class="inline-block mr-2 mb-2 max-w-[120px]"><img src="' +
            a.previewUrl +
            '" class="rounded border border-slate-200 max-h-24 object-cover" alt=""></div>';
        } else if (a.isPdf) {
          attHtml +=
            '<span class="inline-flex items-center gap-1 mr-2 mb-2 px-2 py-1 bg-rose-50 border border-rose-200 rounded text-[10px] font-bold text-rose-800">PDF · ' +
            escapeHtml(a.name) +
            '</span>';
        }
      });
      if (attHtml) {
        html += '<div class="mt-3 pt-3 border-t border-slate-200"><p class="text-[9px] font-bold text-slate-500 mb-1">Pièces jointes (aperçu)</p>' + attHtml + '</div>';
      }
      previewEl.innerHTML = html;
    }

    function setMode(mode) {
      if (mode === 'preview') {
        if (writeWrap) writeWrap.classList.add('hidden');
        if (previewWrap) previewWrap.classList.remove('hidden');
        if (tabWrite) tabWrite.classList.remove('border-emerald-600', 'text-emerald-800', 'bg-white');
        if (tabWrite) tabWrite.classList.add('border-transparent', 'text-slate-500');
        if (tabPreview) tabPreview.classList.add('border-emerald-600', 'text-emerald-800', 'bg-white');
        if (tabPreview) tabPreview.classList.remove('border-transparent', 'text-slate-500');
        updatePreview();
      } else {
        if (writeWrap) writeWrap.classList.remove('hidden');
        if (previewWrap) previewWrap.classList.add('hidden');
        if (tabPreview) tabPreview.classList.remove('border-emerald-600', 'text-emerald-800', 'bg-white');
        if (tabPreview) tabPreview.classList.add('border-transparent', 'text-slate-500');
        if (tabWrite) tabWrite.classList.add('border-emerald-600', 'text-emerald-800', 'bg-white');
        if (tabWrite) tabWrite.classList.remove('border-transparent', 'text-slate-500');
      }
    }

    if (tabWrite)
      tabWrite.addEventListener('click', function () {
        setMode('write');
      });
    if (tabPreview)
      tabPreview.addEventListener('click', function () {
        setMode('preview');
      });

    var draftTimer = null;
    function saveDraft() {
      if (!draftKey) return;
      try {
        localStorage.setItem(draftKey, ta.value);
        if (draftBadgeEl) {
          draftBadgeEl.classList.remove('hidden');
          draftBadgeEl.textContent = 'Brouillon enregistré';
        }
      } catch (e) {}
    }

    ta.addEventListener('input', function () {
      updateCounters();
      refreshQuality();
      if (previewEl && (!previewWrap || !previewWrap.classList.contains('hidden'))) updatePreview();
      clearTimeout(draftTimer);
      draftTimer = setTimeout(saveDraft, draftInterval);
    });

    ta.addEventListener('paste', function (e) {
      var items = e.clipboardData && e.clipboardData.items;
      if (!items) return;
      for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image/') === 0) {
          e.preventDefault();
          var f = items[i].getAsFile();
          if (f) addFiles([f]);
          return;
        }
      }
      var plain = e.clipboardData.getData('text/plain');
      var html = e.clipboardData.getData('text/html');
      if (html && plain) {
        var urlMatch = plain.match(/^\s*(https?:\/\/\S+)\s*$/);
        if (urlMatch) {
          e.preventDefault();
          var u = urlMatch[1];
          var host = '';
          try {
            host = new URL(u).hostname;
          } catch (err) {}
          insertAtCursor(ta, '[' + (host || 'Lien') + '](' + u + ')');
          updateCounters();
          refreshQuality();
        }
      }
    });

    if (dropZone) {
      ;['dragenter', 'dragover'].forEach(function (ev) {
        dropZone.addEventListener(ev, function (e) {
          e.preventDefault();
          dropZone.classList.add('ring-2', 'ring-emerald-400', 'bg-emerald-50/50');
        });
      });
      ;['dragleave', 'drop'].forEach(function (ev) {
        dropZone.addEventListener(ev, function (e) {
          if (ev === 'drop') e.preventDefault();
          dropZone.classList.remove('ring-2', 'ring-emerald-400', 'bg-emerald-50/50');
        });
      });
      dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        var dt = e.dataTransfer;
        if (dt && dt.files && dt.files.length) addFiles(dt.files);
      });
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files.length) addFiles(fileInput.files);
        fileInput.value = '';
      });
    }

    if (draftKey) {
      try {
        var saved = localStorage.getItem(draftKey);
        if (saved && saved.length > 0 && !ta.value.trim()) {
          if (global.confirm('Un brouillon a été trouvé. Voulez-vous le restaurer ?')) {
            ta.value = saved;
          } else {
            localStorage.removeItem(draftKey);
          }
        }
      } catch (e) {}
    }

    var tbRoot = opts.toolbarRoot
      ? typeof opts.toolbarRoot === 'string'
        ? document.querySelector(opts.toolbarRoot)
        : opts.toolbarRoot
      : null;
    if (tbRoot) {
      tbRoot.querySelectorAll('[data-fc-wrap]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var b = btn.getAttribute('data-fc-wrap') || '';
          var a = btn.getAttribute('data-fc-end');
          if (a == null) a = b;
          b = b.replace(/\\n/g, '\n');
          a = String(a).replace(/\\n/g, '\n');
          wrapSelection(ta, b, a);
          ta.dispatchEvent(new Event('input'));
        });
      });
      var linkBtn = tbRoot.querySelector('[data-fc-link]');
      if (linkBtn) {
        linkBtn.addEventListener('click', function () {
          var url = global.prompt('Adresse du lien :', 'https://');
          if (url === null) return;
          var label = global.prompt(
            'Texte affiché :',
            ta.value.slice(ta.selectionStart, ta.selectionEnd) || ''
          );
          if (label === null) return;
          insertAtCursor(ta, '[' + (label || 'lien') + '](' + url + ')');
          ta.dispatchEvent(new Event('input'));
        });
      }
      tbRoot.querySelectorAll('[data-fc-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var act = btn.getAttribute('data-fc-action');
          if (act === 'structure') structureText(ta);
          else if (act === 'bullets') toBulletPoints(ta);
          else if (act === 'title') addTitle(ta);
          else if (act === 'format') formatCleanup(ta);
          ta.dispatchEvent(new Event('input'));
        });
      });
    }

    var mentionUrl = opts.mentionSearchUrl || '';
    var mentionBox = null;
    var mentionStart = -1;

    function closeMention() {
      if (mentionBox) {
        mentionBox.remove();
        mentionBox = null;
      }
      mentionStart = -1;
    }

    function openMentionDropdown(query) {
      if (!mentionUrl) return;
      var url = mentionUrl + encodeURIComponent(query);
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) {
          return r.json();
        })
        .then(function (d) {
          if (!d.success) return;
          closeMention();
          mentionBox = document.createElement('div');
          mentionBox.className =
            'forum-mention-dd absolute z-50 mt-1 max-h-48 w-64 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg text-sm';
          var users = d.users || [];
          var groups = d.groups || [];
          groups.forEach(function (g) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full text-left px-3 py-2 hover:bg-slate-50 text-slate-800';
            b.textContent = g.label;
            b.addEventListener('click', function () {
              replaceMention(g.insert || g.label);
            });
            mentionBox.appendChild(b);
          });
          users.forEach(function (u) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full text-left px-3 py-2 hover:bg-slate-50 text-slate-800';
            var dn = u.display_name || u.callsign || '#' + u.id;
            b.textContent = dn + (u.callsign ? ' · ' + u.callsign : '');
            b.addEventListener('click', function () {
              replaceMention(dn, u.id);
            });
            mentionBox.appendChild(b);
          });
          if (!groups.length && !users.length) {
            mentionBox.innerHTML =
              '<div class="px-3 py-2 text-slate-500 text-xs">Aucun résultat</div>';
          }
          ta.parentElement.style.position = ta.parentElement.style.position || 'relative';
          ta.parentElement.appendChild(mentionBox);
        })
        .catch(function () {});
    }

    function replaceMention(label, userId) {
      var end = ta.selectionStart;
      var before = ta.value.slice(0, mentionStart);
      var after = ta.value.slice(end);
      var insert = userId ? '@' + label + ' ' : '@' + label + ' ';
      ta.value = before + insert + after;
      var pos = before.length + insert.length;
      ta.selectionStart = ta.selectionEnd = pos;
      closeMention();
      ta.focus();
      ta.dispatchEvent(new Event('input'));
    }

    ta.addEventListener('keyup', function () {
      if (!mentionUrl) return;
      var pos = ta.selectionStart;
      var v = ta.value.slice(0, pos);
      var at = v.lastIndexOf('@');
      if (at < 0) {
        closeMention();
        return;
      }
      var prev = at > 0 ? v.charAt(at - 1) : ' ';
      if (prev && !/\s/.test(prev) && prev !== '(') {
        closeMention();
        return;
      }
      var q = v.slice(at + 1);
      if (/\s/.test(q)) {
        closeMention();
        return;
      }
      mentionStart = at;
      openMentionDropdown(q);
    });

    document.addEventListener('click', function (e) {
      if (mentionBox && !mentionBox.contains(e.target) && e.target !== ta) closeMention();
    });

    updateCounters();
    refreshQuality();
    syncHidden();

    return {
      getAttachmentIds: function () {
        return attachments
          .filter(function (a) {
            return a.id;
          })
          .map(function (a) {
            return a.id;
          });
      },
      clearDraft: function () {
        if (draftKey)
          try {
            localStorage.removeItem(draftKey);
          } catch (e) {}
        if (draftBadgeEl) draftBadgeEl.classList.add('hidden');
      },
      validateForSubmit: function () {
        var attCount = attachments.filter(function (a) {
          return a.id;
        }).length;
        return validateMessage(ta.value, attCount, maxLen);
      },
      textarea: ta,
      setMode: setMode,
      updatePreview: updatePreview,
    };
  }

  global.ForumComposer = {
    markdownToHtml: markdownToHtml,
    init: init,
    wrapSelection: wrapSelection,
    insertAtCursor: insertAtCursor,
  };
})(typeof window !== 'undefined' ? window : this);
