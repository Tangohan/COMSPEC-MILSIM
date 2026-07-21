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
        var el = document.getElementById('atak-chat-messages');
        if (!el) return;
        if (list.length === 0) {
          el.innerHTML = '<div class="atak-empty-state atak-empty-state--compact" id="atak-chat-empty">' +
            '<p class="atak-empty-state-title">Aucun message</p>' +
            '<p class="atak-empty-state-text">Les échanges d’équipe s’afficheront ici.</p></div>';
        } else {
          el.innerHTML = list.map(formatMsg).join('');
          el.scrollTop = el.scrollHeight;
        }
        if (window.ATAKLastChatError) window.ATAKLastChatError(null);
      })
      .catch(function (err) {
        if (window.ATAKShowError && (!err.message || err.message.indexOf('Tchat:') !== 0)) window.ATAKShowError('Impossible de charger le tchat.');
      });
  }

  function formatMsg(m) {
    var time = m.created_at ? m.created_at.replace('T', ' ').substring(0, 19) : '';
    var bodyHtml = (window.ATAKMedicalAlerts && window.ATAKMedicalAlerts.formatChatBody)
      ? window.ATAKMedicalAlerts.formatChatBody(m.body || '')
      : String(m.body || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    var medical = (window.ATAKMedicalAlerts && window.ATAKMedicalAlerts.parseMessage)
      ? window.ATAKMedicalAlerts.parseMessage(m.body || '')
      : null;
    var cls = 'atak-chat-msg' + (medical ? ' atak-chat-msg-medical' + (medical.severity === 'critical' ? ' atak-chat-msg-medical-critical' : '') : '');
    return '<div class="' + cls + '"><span class="author">' + (m.author || '') + '</span> ' + bodyHtml + ' <span class="atak-chat-time">' + time + '</span></div>';
  }

  function appendMessage(msg) {
    var el = document.getElementById('atak-chat-messages');
    if (el) {
      var empty = el.querySelector('.atak-empty-state');
      if (empty) empty.remove();
      el.insertAdjacentHTML('beforeend', formatMsg(msg));
      el.scrollTop = el.scrollHeight;
    }
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
      body: JSON.stringify({ mapId: getMapId(), author: author, body: body })
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

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('atak-chat-send');
    var input = document.getElementById('atak-chat-input');
    if (btn) btn.addEventListener('click', send);
    if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
  });

  return { appendMessage: appendMessage, fetchMessages: fetchMessages };
})();
