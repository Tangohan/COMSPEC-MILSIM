/* COMSPEC ATAK - Tchat / journal radio (+ mentions @) */
window.ATAKChat = (function () {
  var sessionStartMs = Date.now();
  var lastMessagesFp = '';
  var cachedMessages = [];
  var LS_PREFIX = 'atak_chat_cleared_before_v1_';
  var LS_MENTION_SEEN = 'atak_chat_mention_seen_v1_';
  var mentionState = {
    open: false,
    query: '',
    start: -1,
    items: [],
    active: 0
  };

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function isNodeConfigured() {
    var b = getApiBase();
    return b && b.trim() !== '';
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function getAuthor() {
    var ph = window.ATAK_PHONE_SESSION;
    if (ph && ph.label) return String(ph.label);
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return 'Opérateur';
  }

  function getMyCallsigns() {
    var out = [];
    var u = window.ATAK_USER || {};
    [u.callsign, u.armaCallsign, u.displayName].forEach(function (v) {
      var s = String(v || '').trim();
      if (s) out.push(s.toUpperCase());
    });
    return out;
  }

  function clearStorageKey() {
    return LS_PREFIX + String(getMapId());
  }

  function mentionSeenKey() {
    return LS_MENTION_SEEN + String(getMapId());
  }

  function getMentionSeenId() {
    try {
      var v = parseInt(localStorage.getItem(mentionSeenKey()) || '0', 10);
      return isNaN(v) || v < 0 ? 0 : v;
    } catch (e) {
      return 0;
    }
  }

  function setMentionSeenId(id) {
    try {
      localStorage.setItem(mentionSeenKey(), String(id > 0 ? id : 0));
    } catch (e) { /* ignore */ }
  }

  /** Id max masqué localement (vue seule — n’efface pas le serveur ni le journal Liaison). */
  function getClearedBeforeId() {
    try {
      var v = parseInt(localStorage.getItem(clearStorageKey()) || '0', 10);
      if (isNaN(v) || v < 0) return 0;
      // Ancien bug : timestamp Date.now() stocké → masquait tous les ids BDD.
      if (v > 1000000000) {
        try { localStorage.removeItem(clearStorageKey()); } catch (e2) { /* ignore */ }
        return 0;
      }
      return v;
    } catch (e) {
      return 0;
    }
  }

  function setClearedBeforeId(id) {
    try {
      var n = id > 0 ? id : 0;
      if (n > 1000000000) n = 0;
      localStorage.setItem(clearStorageKey(), String(n));
    } catch (e) { /* ignore */ }
  }

  function messageId(m) {
    if (!m || m.id == null || m.id === '') return 0;
    var n = parseInt(m.id, 10);
    return isNaN(n) ? 0 : n;
  }

  /** Sync technique (affichage camps) — ne doit pas apparaître dans le journal radio. */
  function isHiddenSystemMessage(m) {
    if (!m) return false;
    var body = String(m.body || '').trim();
    var author = String(m.author || '').trim().toUpperCase();
    var upper = body.toUpperCase();
    if (upper.indexOf('REGLAGES AFFICHAGE') === 0) return true;
    if (upper.indexOf('AFFICHAGE|ADVERSAIRE=') === 0) return true;
    if (author === 'REGLAGES' && upper.indexOf('AFFICHAGE|') === 0) return true;
    return false;
  }

  function filterVisible(list) {
    var before = getClearedBeforeId();
    return (list || []).filter(function (m) {
      if (isHiddenSystemMessage(m)) return false;
      var id = messageId(m);
      if (before < 1) return true;
      return id < 1 || id > before;
    });
  }

  function emptyStateHtml() {
    return '<div class="atak-empty-state atak-empty-state--compact" id="atak-chat-empty">' +
      '<p class="atak-empty-state-title">Aucun message</p>' +
      '<p class="atak-empty-state-text">Les échanges radio de l’équipe s’afficheront ici.</p></div>';
  }

  function renderList(list) {
    var el = document.getElementById('atak-chat-messages');
    if (!el) return;
    var visible = filterVisible(list);
    if (visible.length === 0) {
      el.innerHTML = emptyStateHtml();
    } else {
      el.innerHTML = visible.map(formatMsg).join('');
      el.scrollTop = el.scrollHeight;
    }
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function pad2(n) {
    return (n < 10 ? '0' : '') + n;
  }

  /** Horodatage relatif de session (aligné esprit serverTime du mod). */
  function formatSessionStamp() {
    var elapsed = Math.max(0, Math.floor((Date.now() - sessionStartMs) / 1000));
    var h = Math.floor(elapsed / 3600);
    var m = Math.floor((elapsed % 3600) / 60);
    var s = elapsed % 60;
    return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
  }

  function formatAbsoluteTime(createdAt) {
    if (!createdAt) return '';
    return String(createdAt).replace('T', ' ').substring(0, 19);
  }

  /**
   * Corps radio jeu : [HH:MM:SS][CHANNEL][PRIORITY][KIND] texte
   * @see fn_formatCommsMessage.sqf
   */
  function parseCommsBody(body) {
    var raw = String(body || '').trim();
    var m = raw.match(/^\[(\d{1,2}:\d{2}:\d{2})\]\[([A-Za-z0-9_]+)\]\[([A-Za-z0-9_]+)\]\[([A-Za-z0-9_]+)\]\s*([\s\S]*)$/);
    if (!m) return null;
    return {
      relative: m[1],
      channel: String(m[2] || '').toUpperCase(),
      priority: String(m[3] || '').toUpperCase(),
      kind: String(m[4] || '').toUpperCase(),
      text: m[5] || ''
    };
  }

  function tagHtml(value) {
    return '<span class="atak-chat-tag">[' + escapeHtml(value) + ']</span>';
  }

  function wrapBodyHtml(html) {
    return '<span class="atak-chat-text">' + html + '</span>';
  }

  /** Surligne les @indicatif dans un texte déjà échappé HTML. */
  function highlightMentionsHtml(escapedText) {
    return String(escapedText || '').replace(
      /(^|[\s\[\(\{,;:])@([A-Za-z0-9][A-Za-z0-9._\-]{0,31})\b/g,
      function (_m, pre, token) {
        var mine = getMyCallsigns().indexOf(String(token).toUpperCase()) >= 0;
        var cls = 'atak-chat-mention' + (mine ? ' atak-chat-mention--me' : '');
        return pre + '<span class="' + cls + '">@' + token + '</span>';
      }
    );
  }

  function bodyWithMentions(rawText) {
    return wrapBodyHtml(highlightMentionsHtml(escapeHtml(rawText)));
  }

  function extractMentionTokens(body) {
    var text = String(body || '');
    var parsed = parseCommsBody(text);
    if (parsed) text = parsed.text;
    var re = /(^|[\s\[\(\{,;:])@([A-Za-z0-9][A-Za-z0-9._\-]{0,31})\b/g;
    var out = [];
    var seen = {};
    var m;
    while ((m = re.exec(text)) !== null) {
      var t = String(m[2] || '').trim();
      var key = t.toUpperCase();
      if (!t || seen[key]) continue;
      seen[key] = true;
      out.push(t);
    }
    return out;
  }

  function messageMentionsMe(m) {
    if (!m) return false;
    var mine = getMyCallsigns();
    if (!mine.length) return false;
    var author = String(m.author || '').toUpperCase();
    if (mine.indexOf(author) >= 0) return false;
    if (Array.isArray(m.mentions)) {
      for (var i = 0; i < m.mentions.length; i++) {
        var cs = String((m.mentions[i] && (m.mentions[i].call_sign || m.mentions[i].token)) || '').toUpperCase();
        if (cs && mine.indexOf(cs) >= 0) return true;
      }
    }
    var tokens = extractMentionTokens(m.body || '');
    for (var j = 0; j < tokens.length; j++) {
      if (mine.indexOf(String(tokens[j]).toUpperCase()) >= 0) return true;
    }
    return false;
  }

  var mentionToastKeys = {};
  var mentionToastLastPrune = 0;

  function consumeMentionToast(key) {
    var k = String(key || '');
    if (!k) return true;
    var now = Date.now();
    if (now - mentionToastLastPrune > 60000) {
      mentionToastKeys = {};
      mentionToastLastPrune = now;
    }
    if (mentionToastKeys[k] && (now - mentionToastKeys[k]) < 8000) {
      return false;
    }
    mentionToastKeys[k] = now;
    return true;
  }

  function notifyMention(m) {
    if (!m || !messageMentionsMe(m)) return;
    var author = String(m.author || 'Un opérateur');
    var dedupe = 'mention:' + author.toUpperCase();
    if (!consumeMentionToast(dedupe)) return;
    var msg = author + ' vous a mentionné dans le journal radio.';
    var parsed = parseCommsBody(m.body || '');
    var prio = parsed && (parsed.priority === 'URGENT' || parsed.priority === 'CONTACT');
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification(msg, prio ? { priority: true } : undefined);
    }
  }

  function notifyPriorityComms(list) {
    var arr = Array.isArray(list) ? list : [];
    var seen = getMentionSeenId();
    var newestPrio = null;
    for (var i = 0; i < arr.length; i++) {
      var m = arr[i];
      var id = messageId(m);
      if (id > 0 && id <= seen) continue;
      var parsed = parseCommsBody((m && m.body) || '');
      if (!parsed) continue;
      if (parsed.priority !== 'URGENT' && parsed.priority !== 'CONTACT') continue;
      if (!newestPrio || id > messageId(newestPrio)) newestPrio = m;
    }
    if (!newestPrio) return;
    if (window.ATAKSounds && typeof window.ATAKSounds.playPriority === 'function') {
      window.ATAKSounds.playPriority();
    }
  }

  function scanMentionsForMe(list, opts) {
    opts = opts || {};
    var seen = getMentionSeenId();
    var maxId = seen;
    var newest = null;
    (Array.isArray(list) ? list : []).forEach(function (m) {
      var id = messageId(m);
      if (id > maxId) maxId = id;
      if (id > 0 && id <= seen) return;
      if (!messageMentionsMe(m)) return;
      if (!newest || id > messageId(newest)) newest = m;
    });
    if (opts.notify && newest) {
      notifyMention(newest);
    }
    if (maxId > seen) {
      setMentionSeenId(maxId);
    }
  }

  function formatMsg(m) {
    var absTime = formatAbsoluteTime(m.created_at);
    var bodyRaw = m.body || '';
    var author = escapeHtml(m.author || '');
    var mentionedMe = messageMentionsMe(m);

    var orderHtml = (window.ATAKOrders && window.ATAKOrders.formatChatBody)
      ? window.ATAKOrders.formatChatBody(bodyRaw)
      : null;
    var medical = (!orderHtml && window.ATAKMedicalAlerts && window.ATAKMedicalAlerts.parseMessage)
      ? window.ATAKMedicalAlerts.parseMessage(bodyRaw)
      : null;
    var medicalHtml = (!orderHtml && medical && window.ATAKMedicalAlerts && window.ATAKMedicalAlerts.formatChatBody)
      ? window.ATAKMedicalAlerts.formatChatBody(bodyRaw)
      : null;
    var tactical = (!orderHtml && !medical && window.TacmapTacticalAlerts && window.TacmapTacticalAlerts.parseChatBody)
      ? window.TacmapTacticalAlerts.parseChatBody(bodyRaw)
      : null;
    var tacticalHtml = (!orderHtml && !medicalHtml && tactical && window.TacmapTacticalAlerts.formatChatBody)
      ? window.TacmapTacticalAlerts.formatChatBody(bodyRaw)
      : null;
    var groupMsg = (!orderHtml && !medical && !tactical && window.TacmapTacticalAlerts && window.TacmapTacticalAlerts.parseGroupBody)
      ? window.TacmapTacticalAlerts.parseGroupBody(bodyRaw)
      : null;
    var mine = getMyCallsigns();
    var groupOutgoing = !!(groupMsg && (
      (author && mine.indexOf(String(m.author || '').toUpperCase()) >= 0) ||
      (groupMsg.call_sign && mine.indexOf(String(groupMsg.call_sign).toUpperCase()) >= 0)
    ));
    var groupHtml = (!orderHtml && !medicalHtml && !tacticalHtml && groupMsg && window.TacmapTacticalAlerts.formatGroupChatBody)
      ? window.TacmapTacticalAlerts.formatGroupChatBody(bodyRaw, { outgoing: groupOutgoing })
      : null;
    var mpMsg = (!orderHtml && !medical && !tactical && !groupMsg && window.TacmapTacticalAlerts && window.TacmapTacticalAlerts.parseMpBody)
      ? window.TacmapTacticalAlerts.parseMpBody(bodyRaw)
      : null;
    var mpOutgoing = !!(mpMsg && (
      (mpMsg.from && mine.indexOf(String(mpMsg.from).toUpperCase()) >= 0) ||
      (author && mine.indexOf(String(m.author || '').toUpperCase()) >= 0)
    ));
    var mpHtml = (!orderHtml && !medicalHtml && !tacticalHtml && !groupHtml && mpMsg && window.TacmapTacticalAlerts.formatMpChatBody)
      ? window.TacmapTacticalAlerts.formatMpChatBody(bodyRaw, { outgoing: mpOutgoing })
      : null;

    var cls = 'atak-chat-msg'
      + (orderHtml ? ' atak-chat-msg-order' : '')
      + (medical ? ' atak-chat-msg-medical' + (medical.severity === 'critical' ? ' atak-chat-msg-medical-critical' : '') : '')
      + (tactical ? ' atak-chat-msg-tactical' + (tactical.severity === 'critical' ? ' atak-chat-msg-tactical-critical' : '') : '')
      + (groupMsg ? ' atak-chat-msg-group' + (groupOutgoing ? ' atak-chat-msg-group--out' : ' atak-chat-msg-group--in') : '')
      + (mpMsg ? ' atak-chat-msg-mp' + (mpOutgoing ? ' atak-chat-msg-mp--out' : ' atak-chat-msg-mp--in') : '')
      + (mentionedMe ? ' atak-chat-msg-mention' : '');

    var parsed = (!orderHtml && !medicalHtml && !tacticalHtml && !groupHtml && !mpHtml) ? parseCommsBody(bodyRaw) : null;
    var line1Tags = '';
    var line2 = '';

    if (orderHtml) {
      line1Tags = tagHtml('ORDER');
      line2 = '<div class="atak-chat-msg-line atak-chat-msg-body">' + wrapBodyHtml(orderHtml) + '</div>';
    } else if (medicalHtml) {
      line1Tags = tagHtml('MED');
      line2 = '<div class="atak-chat-msg-line atak-chat-msg-body">' + wrapBodyHtml(medicalHtml) + '</div>';
    } else if (tacticalHtml) {
      line1Tags = tagHtml('CTAB');
      line2 = '<div class="atak-chat-msg-line atak-chat-msg-body">' + wrapBodyHtml(tacticalHtml) +
        ' <button type="button" class="atak-chat-talert-open" data-talert-chat-open="1">Ouvrir</button></div>';
    } else if (groupHtml) {
      line1Tags = tagHtml('GROUPE');
      line2 = '<div class="atak-chat-msg-line atak-chat-msg-body">' + wrapBodyHtml(groupHtml) + '</div>';
    } else if (mpHtml) {
      line1Tags = tagHtml('PRIVÉ');
      line2 = '<div class="atak-chat-msg-line atak-chat-msg-body">' + wrapBodyHtml(mpHtml) + '</div>';
    } else if (parsed) {
      line1Tags = tagHtml(parsed.relative) + tagHtml(parsed.channel);
      line2 =
        '<div class="atak-chat-msg-line atak-chat-msg-body">' +
          tagHtml(parsed.priority) +
          tagHtml(parsed.kind) +
          ' ' +
          bodyWithMentions(parsed.text) +
        '</div>';
    } else {
      line2 =
        '<div class="atak-chat-msg-line atak-chat-msg-body">' +
          bodyWithMentions(bodyRaw) +
        '</div>';
    }

    var msgIdAttr = (m && m.id != null && m.id !== '')
      ? (' data-chat-id="' + escapeHtml(String(m.id)) + '"')
      : '';

    return (
      '<div class="' + cls + '"' + msgIdAttr +
        (tactical ? ' data-talert-body="' + escapeHtml(bodyRaw) + '"' : '') + '>' +
        '<div class="atak-chat-msg-line atak-chat-msg-meta">' +
          '<span class="atak-chat-author">' + author + '</span>' +
          (line1Tags ? ' ' + line1Tags : '') +
        '</div>' +
        line2 +
        (absTime
          ? '<div class="atak-chat-msg-line atak-chat-msg-abs">' + escapeHtml(absTime) + '</div>'
          : '') +
      '</div>'
    );
  }

  function focusMessage(chatId, opts) {
    var id = String(chatId || '').trim();
    var callSign = opts && opts.callSign ? String(opts.callSign).trim() : '';
    var list = document.getElementById('atak-chat-list')
      || document.getElementById('atak-chat-messages')
      || document.querySelector('.atak-chat-list, #atak-panel-chat .atak-chat-msgs');
    var el = null;
    if (id) {
      el = document.querySelector('.atak-chat-msg[data-chat-id="' + id.replace(/"/g, '') + '"]');
    }
    if (!el && callSign && list) {
      var authors = list.querySelectorAll('.atak-chat-msg .atak-chat-author');
      for (var i = authors.length - 1; i >= 0; i--) {
        if (String(authors[i].textContent || '').trim().toUpperCase() === callSign.toUpperCase()) {
          el = authors[i].closest('.atak-chat-msg');
          break;
        }
      }
    }
    if (!el) return false;
    document.querySelectorAll('.atak-chat-msg--focus').forEach(function (n) {
      n.classList.remove('atak-chat-msg--focus');
    });
    el.classList.add('atak-chat-msg--focus');
    if (typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    setTimeout(function () {
      try { el.classList.remove('atak-chat-msg--focus'); } catch (e) { /* ignore */ }
    }, 4200);
    return true;
  }

  function fetchMessages() {
    var url = getApiBase() + '/api/chat?mapId=' + getMapId();
    fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) {
          var msg = r.status === 401
            ? 'Session expirée — reconnectez-vous pour accéder au tchat.'
            : r.status === 403
              ? 'Vous n’avez pas l’autorisation d’accéder au tchat.'
              : r.status === 503
                ? 'Liaison radio dégradée — nouvel essai…'
                : 'Impossible de charger le tchat pour le moment.';
          if (window.ATAKShowError) window.ATAKShowError(msg);
          if (window.ATAKLastChatError) window.ATAKLastChatError(msg);
          // Ne pas écraser un cache déjà peuplé (perte de paquet roleplay / 503).
          if (cachedMessages.length) {
            renderList(cachedMessages);
          }
          throw new Error('Tchat:');
        }
        return r.json();
      })
      .then(function (data) {
        var list = Array.isArray(data) ? data : [];
        var prevFp = lastMessagesFp;
        cachedMessages = list;
        var visible = filterVisible(list);
        var fp = visible.map(function (m) {
          return (m && m.id != null ? m.id : '') + ':' + (m && (m.body || m.message) ? String(m.body || m.message).length : 0);
        }).join('|') + '#' + visible.length + '@' + getClearedBeforeId();
        if (fp === lastMessagesFp) {
          // Resync Assistances même si le tchat n’a pas changé (API médicale parfois vide).
          if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.ingestFromChatMessages === 'function') {
            window.ATAKMedicalAlerts.ingestFromChatMessages(list);
          }
          if (window.ATAKLastChatError) window.ATAKLastChatError(null);
          return;
        }
        lastMessagesFp = fp;
        renderList(list);
        if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.ingestFromChatMessages === 'function') {
          window.ATAKMedicalAlerts.ingestFromChatMessages(list);
        }
        // Premier chargement : mémoriser sans toast. Ensuite : priorités puis mentions.
        if (prevFp !== '') notifyPriorityComms(list);
        scanMentionsForMe(list, { notify: prevFp !== '' });
        if (window.ATAKLastChatError) window.ATAKLastChatError(null);
      })
      .catch(function (err) {
        if (window.ATAKShowError && (!err.message || err.message.indexOf('Tchat:') !== 0)) window.ATAKShowError('Impossible de charger le tchat.');
      });
  }

  function applyMentionPings(pings) {
    if (!Array.isArray(pings) || !pings.length) return;
    pings.forEach(function (p) {
      if (window.ATAKPings && typeof window.ATAKPings.appendPing === 'function') {
        window.ATAKPings.appendPing(p);
      }
    });
    if (window.ATAKPings && typeof window.ATAKPings.fetchPings === 'function') {
      window.ATAKPings.fetchPings();
    }
  }

  function appendMessage(msg) {
    if (isHiddenSystemMessage(msg)) return;
    if (messageId(msg) > 0 && messageId(msg) <= getClearedBeforeId()) {
      return;
    }
    if (msg) {
      cachedMessages = cachedMessages.concat([msg]);
    }
    var el = document.getElementById('atak-chat-messages');
    if (el) {
      var empty = el.querySelector('.atak-empty-state');
      if (empty) empty.remove();
      el.insertAdjacentHTML('beforeend', formatMsg(msg));
      el.scrollTop = el.scrollHeight;
      lastMessagesFp = '';
    }
    if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.notifyFromChatMessage === 'function') {
      window.ATAKMedicalAlerts.notifyFromChatMessage(msg);
    }
    var id = messageId(msg);
    if (id > 0 && id > getMentionSeenId()) {
      if (messageMentionsMe(msg)) {
        notifyMention(msg);
      }
      setMentionSeenId(id);
    }
    if (msg && Array.isArray(msg.mention_pings)) {
      applyMentionPings(msg.mention_pings);
    }
  }

  /** Formate un envoi web comme un message de groupe (symétrique jeu→web). */
  function formatOutgoingBody(text) {
    var author = String(getAuthor() || 'TOC').trim() || 'TOC';
    var groupId = author;
    try {
      if (window.ATAK_CHAT_GROUP_ID) {
        groupId = String(window.ATAK_CHAT_GROUP_ID).trim() || author;
      }
    } catch (e) { /* ignore */ }
    var grid = '------';
    try {
      if (window.ATAKMap && typeof window.ATAKMap.getCursorGrid === 'function') {
        var g = String(window.ATAKMap.getCursorGrid() || '').trim();
        if (g) grid = g;
      }
    } catch (e2) { /* ignore */ }
    // GROUPE|groupId|indicatif|grille|texte — même contrat que le bridge Iceman.
    return 'GROUPE|' + groupId + '|' + author + '|' + grid + '|' + text;
  }

  /* ——— Autocomplete @ ——— */

  function getMentionCandidates() {
    var map = {};
    var units = (window.ATAKUnits && typeof window.ATAKUnits.getUnits === 'function')
      ? window.ATAKUnits.getUnits()
      : [];
    units.forEach(function (u) {
      var cs = String((u && u.call_sign) || '').trim();
      if (!cs) return;
      var status = String((u && u.status) || '');
      var statusFr = status === 'linked' ? 'En liaison'
        : (status === 'delayed' ? 'Signal différé'
          : (status === 'offline' ? 'Hors liaison' : 'Effectif'));
      var role = String((u && u.role) || '').trim();
      map[cs.toUpperCase()] = {
        callsign: cs,
        label: cs,
        subtitle: role ? (role + ' · ' + statusFr) : statusFr,
        kind: 'unit'
      };
    });
    var dir = window.ATAK_CALLSIGN_TO_USER || {};
    Object.keys(dir).forEach(function (k) {
      var p = dir[k] || {};
      var cs = String(p.callsign || k || '').trim();
      if (!cs) return;
      var key = cs.toUpperCase();
      var dn = String(p.displayName || '').trim();
      if (!map[key]) {
        map[key] = {
          callsign: cs,
          label: cs,
          subtitle: dn || 'Opérateur',
          kind: 'operator'
        };
      } else if (dn) {
        map[key].subtitle = dn + ' · ' + map[key].subtitle;
      }
    });
    return Object.keys(map).map(function (k) { return map[k]; }).sort(function (a, b) {
      return String(a.callsign).localeCompare(String(b.callsign), 'fr', { sensitivity: 'base' });
    });
  }

  function findMentionContext(value, caret) {
    var before = String(value || '').slice(0, caret == null ? value.length : caret);
    var m = before.match(/(^|[\s\[\(\{,;:])@([A-Za-z0-9._\-]*)$/);
    if (!m) return null;
    return {
      start: before.length - m[2].length - 1,
      query: m[2] || ''
    };
  }

  function hideMentions() {
    mentionState.open = false;
    mentionState.items = [];
    mentionState.active = 0;
    mentionState.start = -1;
    mentionState.query = '';
    var list = document.getElementById('atak-chat-mentions');
    if (list) {
      list.hidden = true;
      list.innerHTML = '';
    }
    var input = document.getElementById('atak-chat-input');
    if (input) input.setAttribute('aria-expanded', 'false');
  }

  function renderMentions() {
    var list = document.getElementById('atak-chat-mentions');
    if (!list) return;
    if (!mentionState.open || !mentionState.items.length) {
      hideMentions();
      return;
    }
    list.hidden = false;
    list.innerHTML = mentionState.items.map(function (item, idx) {
      var active = idx === mentionState.active ? ' is-active' : '';
      var kindFr = item.kind === 'operator' ? 'Opérateur' : 'ATAK';
      return '<li class="atak-chat-mention-item' + active + '" role="option" data-idx="' + idx + '"' +
        (idx === mentionState.active ? ' aria-selected="true"' : ' aria-selected="false"') + '>' +
        '<span class="atak-chat-mention-cs">@' + escapeHtml(item.callsign) + '</span>' +
        '<span class="atak-chat-mention-meta">' + escapeHtml(item.subtitle || kindFr) + '</span>' +
        '</li>';
    }).join('');
    var input = document.getElementById('atak-chat-input');
    if (input) input.setAttribute('aria-expanded', 'true');
    list.querySelectorAll('.atak-chat-mention-item').forEach(function (li) {
      li.addEventListener('mousedown', function (e) {
        e.preventDefault();
        var idx = parseInt(li.getAttribute('data-idx') || '0', 10);
        applyMention(idx);
      });
    });
  }

  function updateMentionsFromInput() {
    var input = document.getElementById('atak-chat-input');
    if (!input) return;
    var ctx = findMentionContext(input.value, input.selectionStart);
    if (!ctx) {
      hideMentions();
      return;
    }
    var q = String(ctx.query || '').toUpperCase();
    var items = getMentionCandidates().filter(function (c) {
      if (!q) return true;
      var cs = String(c.callsign || '').toUpperCase();
      var sub = String(c.subtitle || '').toUpperCase();
      return cs.indexOf(q) === 0 || cs.indexOf(q) >= 0 || sub.indexOf(q) >= 0;
    }).slice(0, 8);
    if (!items.length) {
      hideMentions();
      return;
    }
    mentionState.open = true;
    mentionState.start = ctx.start;
    mentionState.query = ctx.query;
    mentionState.items = items;
    if (mentionState.active >= items.length) mentionState.active = 0;
    renderMentions();
  }

  function applyMention(idx) {
    var input = document.getElementById('atak-chat-input');
    if (!input || !mentionState.open) return;
    var item = mentionState.items[idx];
    if (!item) return;
    var val = String(input.value || '');
    var caret = input.selectionStart == null ? val.length : input.selectionStart;
    var start = mentionState.start;
    if (start < 0) start = caret;
    var before = val.slice(0, start);
    var after = val.slice(caret);
    var insert = '@' + item.callsign + ' ';
    input.value = before + insert + after;
    var pos = before.length + insert.length;
    input.focus();
    try {
      input.setSelectionRange(pos, pos);
    } catch (e) { /* ignore */ }
    hideMentions();
  }

  function send() {
    var input = document.getElementById('atak-chat-input');
    var body = input && input.value && input.value.trim();
    if (!body) return;
    hideMentions();
    var author = getAuthor();
    if (!isNodeConfigured()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible pour envoyer un message.');
      return;
    }
    fetch(getApiBase() + '/api/chat', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mapId: getMapId(), author: author, body: formatOutgoingBody(body) })
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer le message.');
        return null;
      }
      return r.json().catch(function () { return null; });
    }).then(function (row) {
      if (row && row.id) {
        appendMessage(row);
        var pinged = Array.isArray(row.mentions)
          ? row.mentions.filter(function (m) { return m && m.pinged; }).length
          : 0;
        if (pinged > 0 && window.ATAKShowNotification) {
          window.ATAKShowNotification(
            pinged === 1
              ? 'Message envoyé — ping carte vers l’unité mentionnée.'
              : 'Message envoyé — pings carte vers les unités mentionnées.',
            { silent: true }
          );
        }
      } else {
        fetchMessages();
      }
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer le message.');
    });
    input.value = '';
  }

  /** Masque l’historique affiché (local) — ne touche pas Liaison ni le stockage serveur. */
  function clearDisplay() {
    var el = document.getElementById('atak-chat-messages');
    var applyClear = function (list) {
      var max = 0;
      (Array.isArray(list) ? list : []).forEach(function (m) {
        var id = messageId(m);
        if (id > max) max = id;
      });
      // Sans message connu : ne pas inventer un seuil (ex. Date.now) qui masquerait tout.
      if (max < 1) {
        lastMessagesFp = '';
        if (el) el.innerHTML = emptyStateHtml();
        return;
      }
      setClearedBeforeId(max);
      lastMessagesFp = '';
      renderList(Array.isArray(list) ? list : []);
    };

    if (!isNodeConfigured()) {
      lastMessagesFp = '';
      if (el) el.innerHTML = emptyStateHtml();
      return;
    }

    var source = cachedMessages.length ? cachedMessages : null;
    if (source) {
      applyClear(source);
      return;
    }

    fetch(getApiBase() + '/api/chat?mapId=' + getMapId(), { credentials: 'include' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (data) { applyClear(Array.isArray(data) ? data : []); })
      .catch(function () {
        lastMessagesFp = '';
        if (el) el.innerHTML = emptyStateHtml();
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('atak-chat-send');
    var input = document.getElementById('atak-chat-input');
    var clearBtn = document.getElementById('atak-chat-clear');
    var chatEl = document.getElementById('atak-chat-messages');
    if (chatEl && !chatEl._talertOpenBound) {
      chatEl._talertOpenBound = true;
      chatEl.addEventListener('click', function (ev) {
        var openBtn = ev.target && ev.target.closest ? ev.target.closest('[data-talert-chat-open]') : null;
        if (!openBtn) return;
        ev.preventDefault();
        var wrap = openBtn.closest('[data-talert-body]');
        var raw = wrap ? wrap.getAttribute('data-talert-body') : '';
        if (!raw || !window.TacmapTacticalAlerts || typeof window.TacmapTacticalAlerts.parseChatBody !== 'function') return;
        var parsed = window.TacmapTacticalAlerts.parseChatBody(raw);
        if (!parsed) return;
        window.TacmapTacticalAlerts.openDetail(parsed, function (x, y) {
          try {
            window.dispatchEvent(new CustomEvent('atak:locate', { detail: { x: x, y: y } }));
          } catch (e) { /* ignore */ }
        });
      });
    }
    if (btn) btn.addEventListener('click', send);
    if (input) {
      input.addEventListener('input', updateMentionsFromInput);
      input.addEventListener('click', updateMentionsFromInput);
      input.addEventListener('blur', function () {
        setTimeout(hideMentions, 120);
      });
      input.addEventListener('keydown', function (e) {
        if (mentionState.open && mentionState.items.length) {
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionState.active = (mentionState.active + 1) % mentionState.items.length;
            renderMentions();
            return;
          }
          if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionState.active = (mentionState.active - 1 + mentionState.items.length) % mentionState.items.length;
            renderMentions();
            return;
          }
          if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            applyMention(mentionState.active);
            return;
          }
          if (e.key === 'Escape') {
            e.preventDefault();
            hideMentions();
            return;
          }
        }
        if (e.key === 'Enter') send();
      });
    }
    if (clearBtn && !clearBtn._atakBound) {
      clearBtn._atakBound = true;
      clearBtn.addEventListener('click', function () {
        if (!window.confirm('Vider l’affichage du tchat ? Les messages restent disponibles côté serveur ; le journal Liaison n’est pas modifié.')) {
          return;
        }
        clearDisplay();
      });
    }
  });

  return {
    appendMessage: appendMessage,
    fetchMessages: fetchMessages,
    clearDisplay: clearDisplay,
    parseCommsBody: parseCommsBody,
    formatMsg: formatMsg,
    extractMentionTokens: extractMentionTokens,
    consumeMentionToast: consumeMentionToast,
    focusMessage: focusMessage,
    getCachedMessages: function () { return cachedMessages.slice(); }
  };
})();
