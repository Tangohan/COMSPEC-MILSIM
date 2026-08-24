(function () {
  var board = document.getElementById('mp-org-board');
  if (!board) return;

  var csrf = board.getAttribute('data-csrf') || '';
  var base = board.getAttribute('data-move-base') || '';
  var dragId = null;

  board.addEventListener('dragstart', function (ev) {
    var slot = ev.target.closest('.mp-tree__slot');
    if (!slot) return;
    dragId = slot.getAttribute('data-slot-id');
    slot.classList.add('is-dragging');
    if (ev.dataTransfer) {
      ev.dataTransfer.setData('text/plain', dragId || '');
      ev.dataTransfer.effectAllowed = 'move';
    }
  });

  board.addEventListener('dragend', function (ev) {
    var slot = ev.target.closest('.mp-tree__slot');
    if (slot) slot.classList.remove('is-dragging');
    board.querySelectorAll('.mp-tree__slots.is-drop').forEach(function (el) {
      el.classList.remove('is-drop');
    });
    dragId = null;
  });

  board.addEventListener('dragover', function (ev) {
    var zone = ev.target.closest('.mp-tree__slots');
    if (!zone) return;
    ev.preventDefault();
    zone.classList.add('is-drop');
  });

  board.addEventListener('dragleave', function (ev) {
    var zone = ev.target.closest('.mp-tree__slots');
    if (zone) zone.classList.remove('is-drop');
  });

  board.addEventListener('drop', function (ev) {
    var zone = ev.target.closest('.mp-tree__slots');
    if (!zone) return;
    ev.preventDefault();
    zone.classList.remove('is-drop');
    var slotId = dragId || (ev.dataTransfer ? ev.dataTransfer.getData('text/plain') : '');
    var elementId = zone.getAttribute('data-drop-element');
    if (!slotId || !elementId || !base) return;
    var order = (zone.querySelectorAll('.mp-tree__slot').length + 1) * 10;
    var body = new URLSearchParams();
    body.set('_csrf_token', csrf);
    body.set('element_id', elementId);
    body.set('display_order', String(order));
    body.set('ajax', '1');
    fetch(base + '/postes/' + slotId + '/deplacer', {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: body,
      credentials: 'same-origin'
    }).then(function (res) {
      if (res.ok) window.location.reload();
    }).catch(function () {});
  });
})();
