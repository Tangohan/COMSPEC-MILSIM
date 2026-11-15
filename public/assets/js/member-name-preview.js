/*
 * Aperçu en direct du nom d'un membre dans les formulaires d'identité (même règle que App\Support\MemberIdentity) :
 * prénom + nom, puis indicatif, puis nom du compte, puis le libellé de repli. Aucune dépendance ; tout est écrit
 * avec textContent (jamais de HTML).
 */
(function () {
  'use strict';

  var PARTICLES = ['de', 'du', 'des', 'd', 'la', 'le', 'les', 'l', 'van', 'von', 'der', 'den', 'ter', 'ten', 'zu', 'zum', 'zur',
    'di', 'da', 'dos', 'das', 'do', 'del', 'della', 'dello', 'degli', 'dei', 'y', 'e', 'af', 'av', 'st', 'ben', 'bin', 'ibn', 'el', 'al', 'lo'];

  function clean(v) {
    var s = String(v == null ? '' : v).replace(/\s+/g, ' ').trim();
    return (s === '—' || s === '-') ? '' : s;
  }

  function stripAccents(s) {
    return s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : s;
  }

  function isParticle(w) {
    return PARTICLES.indexOf(stripAccents(w.replace(/[’']+$/, '')).toLowerCase()) !== -1;
  }

  function upperSurname(last) {
    var words = last.split(' ');
    var seenMain = false;
    return words.map(function (w, i) {
      if (!seenMain && i < words.length - 1 && isParticle(w)) { return w.toLowerCase(); }
      seenMain = true;
      var m = /^([dDlL][’'])(.+)$/.exec(w);
      return m ? m[1].toLowerCase() + m[2].toUpperCase() : w.toUpperCase();
    }).join(' ');
  }

  function surnameInitials(last) {
    var words = last.split(' ');
    var seenMain = false;
    var out = [];
    words.forEach(function (w, i) {
      if (!seenMain && i < words.length - 1 && isParticle(w)) { return; }
      seenMain = true;
      var pieces = [];
      w.split(/-+/).forEach(function (p) {
        p = p.replace(/^[^\p{L}]+/u, '').replace(/^[dDlL][’']/, '');
        if (p) { pieces.push(p.charAt(0).toUpperCase() + '.'); }
      });
      if (pieces.length) { out.push(pieces.join('-')); }
    });
    return out.join(' ');
  }

  function civil(first, last, format, callsign, trigram) {
    function full() { return [first, last ? upperSurname(last) : ''].filter(Boolean).join(' '); }
    function short() {
      if (!first) { return upperSurname(last); }
      var ini = surnameInitials(last);
      return ini ? first + ' ' + ini : first;
    }
    if (format === 'full') { return full(); }
    if (format === 'trigram' && trigram) { return trigram; }
    if (format === 'callsign' && callsign) { return callsign; }
    return short();
  }

  function render(box) {
    var q = function (sel) { return sel ? document.querySelector(sel) : null; };
    var val = function (sel, fallback) { var el = q(sel); return clean(el ? el.value : fallback); };
    var first = val(box.getAttribute('data-first'), '');
    var last = val(box.getAttribute('data-last'), '');
    var callsign = box.getAttribute('data-callsign-input')
      ? val(box.getAttribute('data-callsign-input'), '')
      : clean(box.getAttribute('data-callsign'));
    var account = clean(box.getAttribute('data-account'));
    var trigram = clean(box.getAttribute('data-trigram')).toUpperCase();
    var fallback = box.getAttribute('data-fallback') || '';
    var hasCivil = first !== '' || last !== '';
    var plain = callsign || account || fallback;
    var values = {
      list: hasCivil ? civil(first, last, box.getAttribute('data-list-format') || 'short', callsign, trigram) : plain,
      card: hasCivil ? civil(first, last, box.getAttribute('data-card-format') || 'full', callsign, trigram) : plain,
      c2: callsign || (hasCivil ? civil(first, last, 'short', '', '') : (account || fallback))
    };
    Array.prototype.forEach.call(box.querySelectorAll('[data-mnp]'), function (el) {
      el.textContent = values[el.getAttribute('data-mnp')] || fallback;
    });
  }

  function init(box) {
    ['data-first', 'data-last', 'data-callsign-input'].forEach(function (attr) {
      var el = box.getAttribute(attr) ? document.querySelector(box.getAttribute(attr)) : null;
      if (el) { el.addEventListener('input', function () { render(box); }); }
    });
    render(box);
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-member-name-preview]'), init);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
