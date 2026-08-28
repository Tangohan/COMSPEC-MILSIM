/* Icône géolocalisation téléphone — pin + smartphone (pas le combiné OTAN). */
window.ATAKPhoneIcon = (function () {
  function svg(size) {
    size = Math.max(12, Number(size) || 22);
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size
      + '" viewBox="0 0 32 32" class="atak-phone-geoloc-svg" aria-hidden="true">'
      + '<path d="M16 2.2 C10.8 2.2 6.6 6.5 6.6 11.8 C6.6 19.2 16 30 16 30 S25.4 19.2 25.4 11.8 C25.4 6.5 21.2 2.2 16 2.2 Z" fill="#0891b2" stroke="#134e4a" stroke-width="1.15"/>'
      + '<rect x="12.05" y="6.1" width="7.9" height="12.4" rx="1.55" fill="#f0fdff" stroke="#155e75" stroke-width="0.85"/>'
      + '<rect x="13.15" y="7.35" width="5.7" height="8.35" rx="0.45" fill="#22d3ee"/>'
      + '<circle cx="16" cy="17.35" r="0.72" fill="#155e75"/>'
      + '</svg>';
  }

  function markup(size, label) {
    var inner = svg(size);
    if (label) {
      return '<div class="atak-phone-geoloc-stack" style="display:flex;flex-direction:column;align-items:center;gap:1px;">'
        + inner
        + '<span class="nato-sidc-label">' + String(label).replace(/</g, '&lt;') + '</span></div>';
    }
    return inner;
  }

  function leafletDivIcon(L, size, label) {
    if (!L || !L.divIcon) return null;
    size = Math.max(14, Number(size) || 22);
    var html = markup(size, label);
    return L.divIcon({
      className: 'atak-phone-geoloc-icon atak-compact-marker',
      html: html,
      iconSize: [size, size],
      iconAnchor: [size / 2, size],
      popupAnchor: [0, -size]
    });
  }

  function listBadgeHtml(size) {
    size = Math.max(16, Number(size) || 20);
    return '<span class="atak-phone-geoloc-badge" title="Géolocalisation téléphone" style="display:inline-flex;vertical-align:middle;margin-right:6px;">'
      + svg(size)
      + '</span>';
  }

  return {
    svg: svg,
    markup: markup,
    leafletDivIcon: leafletDivIcon,
    listBadgeHtml: listBadgeHtml
  };
})();
