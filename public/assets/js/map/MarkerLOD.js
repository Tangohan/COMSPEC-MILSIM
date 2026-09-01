/**
 * LOD marqueurs — taille et labels selon le niveau de zoom.
 * La taille utilisateur (réglages d’apparence) prime ; le zoom ne fait qu’un léger facteur.
 */

/** @typedef {{ band: string, size: number, showCallsign: boolean, showRole: boolean, showStatus: boolean, zoom: number }} LODLevel */

/**
 * Bandes de zoom (Leaflet ou caméra distance normalisée 0..1).
 * @param {number} zoom — zoom Leaflet ou facteur équivalent
 * @returns {LODLevel}
 */
export function computeLOD(zoom) {
  const z = Number(zoom);
  if (!isFinite(z)) return lodForBand('ops', z);

  /* Leaflet zoom typique Altis : 0–6 */
  if (z <= 1.5) return lodForBand('theatre', z);
  if (z <= 3) return lodForBand('ops', z);
  if (z <= 4.5) return lodForBand('tac', z);
  return lodForBand('close', z);
}

/**
 * LOD depuis distance caméra 3D (0 = proche, 1 = lointain).
 * @param {number} normalizedDistance
 */
export function computeLODFromDistance(normalizedDistance) {
  const d = Number(normalizedDistance) || 0;
  if (d > 0.75) return lodForBand('theatre', 1);
  if (d > 0.5) return lodForBand('ops', 2);
  if (d > 0.25) return lodForBand('tac', 4);
  return lodForBand('close', 5);
}

function lodForBand(band, zoom) {
  switch (band) {
    case 'theatre':
      return { band: band, size: 18, showCallsign: true, showRole: false, showStatus: false, zoom: zoom };
    case 'ops':
      return { band: band, size: 20, showCallsign: true, showRole: false, showStatus: false, zoom: zoom };
    case 'tac':
      return { band: band, size: 24, showCallsign: true, showRole: false, showStatus: false, zoom: zoom };
    case 'close':
    default:
      return { band: band, size: 28, showCallsign: true, showRole: false, showStatus: false, zoom: zoom };
  }
}

/** Taille max / min si aucun réglage utilisateur. */
export const MAX_SYMBOL_PX = 40;
export const MIN_SYMBOL_PX = 12;

export function clampSymbolSize(px) {
  return Math.max(MIN_SYMBOL_PX, Math.min(MAX_SYMBOL_PX, Math.round(px)));
}

/**
 * Applique la taille d’icône des réglages d’apparence, avec un léger facteur de zoom.
 * @param {LODLevel} lod
 * @param {number} iconSize — curseur 12–28
 */
export function applyDisplaySize(lod, iconSize) {
  const band = lod && lod.band ? lod.band : 'ops';
  const fallback = lod && lod.size ? lod.size : 20;
  const n = Number(iconSize);
  const base = isFinite(n) ? n : fallback;
  const factor = band === 'theatre' ? 0.95 : band === 'ops' ? 1 : band === 'tac' ? 1.08 : 1.16;
  return clampSymbolSize(base * factor);
}
