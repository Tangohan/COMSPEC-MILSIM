(function () {
  'use strict';

  function normalize(value) {
    return value.normalize ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() : value.toLowerCase();
  }

  document.querySelectorAll('[data-msg-compose]').forEach(function (button) {
    button.addEventListener('click', function () {
      var compose = document.getElementById('msg-compose');
      if (!compose) return;
      compose.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.setTimeout(function () {
        var input = compose.querySelector('input[name="subject"]');
        if (input) input.focus();
      }, 450);
    });
  });

  var search = document.querySelector('[data-msg-search]');
  if (search) {
    search.addEventListener('input', function () {
      var query = normalize(search.value.trim());
      var visible = 0;
      document.querySelectorAll('[data-msg-thread]').forEach(function (thread) {
        var match = normalize(thread.getAttribute('data-search') || '').indexOf(query) !== -1;
        thread.hidden = !match;
        if (match) visible += 1;
      });
      var empty = document.querySelector('[data-msg-no-results]');
      if (empty) empty.hidden = visible !== 0;
    });
  }

  document.querySelectorAll('[data-msg-body]').forEach(function (body) {
    var form = body.closest('[data-msg-form]');
    var counter = form ? form.querySelector('[data-msg-count]') : null;
    function updateCount() { if (counter) counter.textContent = String(body.value.length); }
    body.addEventListener('input', updateCount);
    updateCount();
  });

  var stream = document.querySelector('[data-msg-stream]');
  if (stream) stream.scrollTop = stream.scrollHeight;
}());
