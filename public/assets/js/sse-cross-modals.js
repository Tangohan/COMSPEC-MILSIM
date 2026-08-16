/**
 * Croisements SSE — confirmation de retrait (modale).
 */
(function () {
  var form = document.getElementById('sse-watchlist-remove-form');
  var nameEl = document.getElementById('sse-watchlist-remove-name');
  if (!form) return;

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-watchlist-id][data-sse-modal-open="sse-modal-watchlist-remove"]');
    if (!btn) return;
    var id = String(btn.getAttribute('data-watchlist-id') || '');
    var name = btn.getAttribute('data-watchlist-name') || 'cette entrée';
    if (nameEl) nameEl.textContent = name;
    var tpl = (window.SSE_CROSS && window.SSE_CROSS.removeUrlTpl) || '';
    form.action = tpl ? String(tpl).split('__ID__').join(encodeURIComponent(id)) : '#';
  });
})();
