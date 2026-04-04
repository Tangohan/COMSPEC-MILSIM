/* COMSPEC ATAK - Tchat */
window.ATAKChat = (function () {
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

  function fetchMessages() {
    var url = getApiBase() + '/api/chat?mapId=' + getMapId();
    fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) {
          var msg = 'Tchat: ' + (r.status === 401 ? 'Non authentifié (401)' : r.status === 403 ? 'Accès refusé (403)' : 'Erreur ' + r.status);
          if (window.ATAKShowError) window.ATAKShowError(msg);
          if (window.ATAKLastChatError) window.ATAKLastChatError(msg);
          throw new Error(msg);
        }
        return r.json();
      })
      .then(function (data) {
        var list = Array.isArray(data) ? data : [];
        var el = document.getElementById('atak-chat-messages');
        if (el) el.innerHTML = list.map(formatMsg).join('');
        el.scrollTop = el.scrollHeight;
        if (window.ATAKLastChatError) window.ATAKLastChatError(null);
      })
      .catch(function (err) {
        if (window.ATAKShowError && (!err.message || err.message.indexOf('Tchat:') !== 0)) window.ATAKShowError('Impossible de charger le tchat.');
      });
  }

  function formatMsg(m) {
    var time = m.created_at ? m.created_at.replace('T', ' ').substring(0, 19) : '';
    return '<div class="atak-chat-msg"><span class="author">' + (m.author || '') + '</span> ' + (m.body || '') + ' <span style="color:var(--atak-muted);font-size:0.7rem">' + time + '</span></div>';
  }

  function appendMessage(msg) {
    var el = document.getElementById('atak-chat-messages');
    if (el) {
      el.insertAdjacentHTML('beforeend', formatMsg(msg));
      el.scrollTop = el.scrollHeight;
    }
  }

  function send() {
    var input = document.getElementById('atak-chat-input');
    var body = input && input.value && input.value.trim();
    if (!body) return;
    var author = getAuthor();
    if (window.ATAKSocket && window.ATAKSocket.isConnected()) {
      window.ATAKSocket.emit('Chat', { author: author, body: body });
    } else if (!isNodeConfigured()) {
      if (window.ATAKShowError) window.ATAKShowError('Configurez l\'URL du nœud ATAK dans Admin → Configuration ATAK.');
    } else {
      fetch(getApiBase() + '/api/chat', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mapId: getMapId(), author: author, body: body })
      }).then(function (r) {
        if (!r.ok) {
          if (window.ATAKShowError) window.ATAKShowError('Envoi tchat: ' + r.status);
          return;
        }
        fetchMessages();
      }).catch(function () {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d\'envoyer le message.');
      });
    }
    input.value = '';
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('atak-chat-send');
    var input = document.getElementById('atak-chat-input');
    if (btn) btn.addEventListener('click', send);
    if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
  });

  return { appendMessage: appendMessage, fetchMessages: fetchMessages };
})();
