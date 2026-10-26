/**
 * Mon compte › Double vérification : copier / télécharger les codes de secours affichés une seule fois.
 */
(function () {
  'use strict';

  var box = document.querySelector('[data-recovery-box]');
  if (!box) return;
  var codes = Array.prototype.map.call(box.querySelectorAll('[data-recovery-codes] code'), function (el) {
    return (el.textContent || '').trim();
  }).filter(Boolean);
  if (!codes.length) return;

  var copyBtn = box.querySelector('[data-recovery-copy]');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var text = codes.join('\n');
      var done = function () {
        var label = copyBtn.textContent;
        copyBtn.textContent = copyBtn.getAttribute('data-done') || label;
        window.setTimeout(function () { copyBtn.textContent = label; }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () {});
      }
    });
  }

  var printBtn = box.querySelector('[data-recovery-print]');
  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }

  var dlBtn = box.querySelector('[data-recovery-download]');
  if (dlBtn) {
    dlBtn.addEventListener('click', function () {
      var header = dlBtn.getAttribute('data-header') || '';
      var body = header + '\n\n' + codes.join('\n') + '\n\nChaque code ne sert qu’une fois.\n';
      var blob = new Blob([body], { type: 'text/plain;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = dlBtn.getAttribute('data-filename') || 'codes-de-secours.txt';
      document.body.appendChild(a);
      a.click();
      window.setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
    });
  }
})();
