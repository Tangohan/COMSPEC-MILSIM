/**
 * Rack de rubans interactif (hover / sélection).
 * Motifs illustratifs — pas une reproduction officielle.
 */
(function () {
  'use strict';

  function closestRack(el) {
    return el && el.closest ? el.closest('[data-dk-rack]') : null;
  }

  function slotsOf(rack) {
    return rack ? Array.prototype.slice.call(rack.querySelectorAll('.dk-slot')) : [];
  }

  function selectSlot(slot) {
    if (!slot) return;
    var rack = closestRack(slot);
    if (!rack) return;
    slotsOf(rack).forEach(function (s) {
      s.classList.remove('is-selected');
      s.setAttribute('aria-pressed', 'false');
    });
    slot.classList.add('is-selected');
    slot.setAttribute('aria-pressed', 'true');
    updateDetail(rack, slot);
  }

  function fillGlyph(disc, glyph, small) {
    if (!disc) return;
    var svg = '';
    if (window.DK_GLYPHS && window.DK_GLYPHS[glyph]) {
      svg = window.DK_GLYPHS[glyph];
    }
    disc.innerHTML = svg;
    disc.classList.toggle('dk-m-disc--small', !!small);
  }

  function updateDetail(rack, slot) {
    var detail = rack.querySelector('[data-dk-detail]');
    if (!detail) return;
    var nameEl = detail.querySelector('[data-dk-detail-name]');
    var famEl = detail.querySelector('[data-dk-detail-fam]');
    var drop = detail.querySelector('[data-dk-detail-drop]');
    var disc = detail.querySelector('[data-dk-detail-disc]');
    var name = slot.getAttribute('data-dk-name') || '';
    var detailLine = slot.getAttribute('data-dk-detail') || '';
    var type = slot.getAttribute('data-dk-type') || 'ribbon';
    var glyph = slot.getAttribute('data-dk-glyph') || '';
    var discClass = slot.getAttribute('data-dk-disc') || 'dk-disc-svc';
    var dropClass = slot.getAttribute('data-dk-drop') || '';
    var pattern = slot.getAttribute('data-dk-pattern') || '';
    var image = slot.getAttribute('data-dk-image') || '';
    var customBg = slot.getAttribute('data-dk-custom-bg') || '';
    if (nameEl) nameEl.textContent = name;
    if (famEl) famEl.textContent = detailLine;
    if (drop) {
      if (image) {
        drop.className = 'dk-m-ribbon dk-rb-image';
        drop.style.backgroundImage = 'url(\'' + image.replace(/'/g, '\\\'') + '\')';
        drop.style.background = '';
      } else if (customBg) {
        drop.className = 'dk-m-ribbon';
        drop.style.backgroundImage = '';
        drop.style.background = customBg;
      } else {
        drop.className = 'dk-m-ribbon' + (dropClass ? ' ' + dropClass : pattern ? ' ' + pattern : '');
        drop.style.backgroundImage = '';
        drop.style.background = '';
      }
    }
    if (disc) {
      disc.className = 'dk-m-disc ' + discClass;
      if (type === 'medal' && glyph) {
        fillGlyph(disc, glyph, false);
      } else {
        disc.innerHTML = '';
      }
    }
  }

  function onActivate(event) {
    var slot = event.target.closest ? event.target.closest('.dk-slot') : null;
    if (!slot) return;
    if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
    if (event.type === 'keydown') event.preventDefault();
    selectSlot(slot);
  }

  document.addEventListener('click', onActivate);
  document.addEventListener('keydown', onActivate);

  function initRacks() {
    document.querySelectorAll('[data-dk-rack]').forEach(function (rack) {
      var selected = rack.querySelector('.dk-slot.is-selected') || rack.querySelector('.dk-slot');
      if (selected) selectSlot(selected);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRacks);
  } else {
    initRacks();
  }
})();
