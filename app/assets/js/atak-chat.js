/* COMSPEC ATAK - Tchat */
window.ATAKChat = (function () {
  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : (window.location.protocol + '//' + window.location.hostname + ':3001');
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchMessages() {
    var url = getApiBase() + '/api/chat?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      var list = (Array.isArray(data) ? data : []).filter(function (m) {
        var body = String((m && m.body) || '').toUpperCase();
        return body.indexOf('REGLAGES AFFICHAGE') !== 0 && body.indexOf('AFFICHAGE|ADVERSAIRE=') !== 0;
      });
      var el = document.getElementById('atak-chat-messages');
      if (el) el.innerHTML = list.map(formatMsg).join('');
      el.scrollTop = el.scrollHeight;
    }).catch(function () {});
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
    var author = 'User';
    if (window.ATAKSocket && window.ATAKSocket.isConnected()) {
      window.ATAKSocket.emit('Chat', { author: author, body: body });
    } else {
      fetch(getApiBase() + '/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mapId: getMapId(), author: author, body: body })
      }).then(function () { fetchMessages(); });
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
