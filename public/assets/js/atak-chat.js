/* COMSPEC ATAK - Tchat / journal radio */
window.ATAKChat = (function () {
  var sessionStartMs = Date.now();
  var lastMessagesFp = '';
  var cachedMessages = [];
  var LS_PREFIX = 'atak_chat_cleared_before_v1_';

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
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return 'User';
  }

  function clearStorageKey() {
    return LS_PREFIX + String(getMapId());
  }

  /** Id max masqué localement (vue seule — n’efface pas le serveur ni le journal Liaison). */
  function getClearedBeforeId() {
    try {
      var v = parseInt(localStorage.getItem(clearStorageKey()) || '0', 10);
      return isNaN(v) || v < 0 ? 0 : v;
    } catch (e) {
      return 0;
    }
  }

  function setClearedBeforeId(id) {
    try {
      localStorage.setItem(clearStorageKey(), String(id > 0 ? id : 0));
    } catch (e) { /* ignore */ }
  }

  function messageId(m) {
    if (!m || m.id == null || m.id === '') return 0;
    var n = parseInt(m.id, 10);
    return isNaN(n) ? 0 : n;
  }

  function filterVisible(list) {
    var before = getClearedBeforeId();
    if (before < 1) return list;
    return list.filter(function (m) {
      var id = messageId(m);
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

  function formatMsg(m) {
    var absTime = formatAbsoluteTime(m.created_at);
    var bodyRaw = m.body || '';
    var author = escapeHtml(m.author || '');

    var orderHtml = (window.ATAKOrders && window.ATAKOrders.formatChatBody)
      ? window.ATAKOrders.formatChatBody(bodyRaw)
      : null;
    var medical = (!orderHtml && window.ATAKMedicalAlerts && window.ATAKMedicalAlerts.parseMessage)
      ? window.ATAKMedicalAlerts.parseMessage(bodyRaw)
      : null;
    var medicalHtml = (!orderHtml && medical && window.ATAKMedicalAlerts && window.ATAKMedicalAlerts.formatChatBody)
      ? window.ATAKMedicalAlerts.formatChatBody(bodyRaw)
      : null;

    var cls = 'atak-chat-msg'
      + (orderHtml ? ' atak-chat-msg-order' : '')
      + (medical ? ' atak-chat-msg-medical' + (medical.severity === 'critical' ? ' atak-chat-msg-medical-critical' : '') : '');

    var parsed = (!orderHtml && !medicalHtml) ? parseCommsBody(bodyRaw) : null;
    var line1Tags = '';
    var line2 = '';

    if (orderHtml) {
      line1Tags = tagHtml('ORDER');
      line2 = '<div class="atak-chat-msg-line atak-chat-msg-body">' + wrapBodyHtml(orderHtml) + '</div>';
    } else if (medicalHtml) {
      line1Tags = tagHtml('MED');
      line2 = '<div class="atak-chat-msg-line atak-chat-msg-body">' + wrapBodyHtml(medicalHtml) + '</div>';
    } else if (parsed) {
      line1Tags = tagHtml(parsed.relative) + tagHtml(parsed.channel);
      line2 =
        '<div class="atak-chat-msg-line atak-chat-msg-body">' +
          tagHtml(parsed.priority) +
          tagHtml(parsed.kind) +
          ' ' +
          wrapBodyHtml(escapeHtml(parsed.text)) +
        '</div>';
    } else {
      line2 =
        '<div class="atak-chat-msg-line atak-chat-msg-body">' +
          wrapBodyHtml(escapeHtml(bodyRaw)) +
        '</div>';
    }

    return (
      '<div class="' + cls + '">' +
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

  function fetchMessages() {
    var url = getApiBase() + '/api/chat?mapId=' + getMapId();
    fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) {
          var msg = r.status === 401
            ? 'Session expirée — reconnectez-vous pour accéder au tchat.'
            : r.status === 403
              ? 'Vous n’avez pas l’autorisation d’accéder au tchat.'
              : 'Impossible de charger le tchat pour le moment.';
          if (window.ATAKShowError) window.ATAKShowError(msg);
          if (window.ATAKLastChatError) window.ATAKLastChatError(msg);
          throw new Error('Tchat:');
        }
        return r.json();
      })
      .then(function (data) {
        var list = Array.isArray(data) ? data : [];
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
        if (window.ATAKLastChatError) window.ATAKLastChatError(null);
      })
      .catch(function (err) {
        if (window.ATAKShowError && (!err.message || err.message.indexOf('Tchat:') !== 0)) window.ATAKShowError('Impossible de charger le tchat.');
      });
  }

  function appendMessage(msg) {
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
  }

  /** Formate un envoi web comme le journal radio du mod. */
  function formatOutgoingBody(text) {
    return '[' + formatSessionStamp() + '][SQUAD][ROUTINE][FREE] ' + text;
  }

  function send() {
    var input = document.getElementById('atak-chat-input');
    var body = input && input.value && input.value.trim();
    if (!body) return;
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
        return;
      }
      fetchMessages();
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer le message.');
    });
    input.value = '';
  }

  /** Masque l’historique affiché (local) — ne touche pas Liaison ni le stockage serveur. */
  function clearDisplay() {
    var el = document.getElementById('atak-chat-messages');
    var applyClear = function (list) {
      var max = getClearedBeforeId();
      (Array.isArray(list) ? list : []).forEach(function (m) {
        var id = messageId(m);
        if (id > max) max = id;
      });
      if (max < 1) max = Date.now();
      setClearedBeforeId(max);
      lastMessagesFp = '';
      renderList(Array.isArray(list) ? list : []);
    };

    if (!isNodeConfigured()) {
      setClearedBeforeId(Date.now());
      lastMessagesFp = '';
      if (el) el.innerHTML = emptyStateHtml();
      return;
    }

    fetch(getApiBase() + '/api/chat?mapId=' + getMapId(), { credentials: 'include' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (data) { applyClear(Array.isArray(data) ? data : []); })
      .catch(function () {
        setClearedBeforeId(Date.now());
        lastMessagesFp = '';
        if (el) el.innerHTML = emptyStateHtml();
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('atak-chat-send');
    var input = document.getElementById('atak-chat-input');
    var clearBtn = document.getElementById('atak-chat-clear');
    if (btn) btn.addEventListener('click', send);
    if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
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
    getCachedMessages: function () { return cachedMessages.slice(); }
  };
})();
