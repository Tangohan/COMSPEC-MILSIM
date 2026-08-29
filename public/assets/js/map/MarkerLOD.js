/**
 * LOD marqueurs — taille et labels selon le niveau de zoom.
 * Les symboles ne dépassent jamais 32 px.
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
      return { band: band, size: 18, showCallsign: false, showRole: false, showStatus: false, zoom: zoom };
    case 'ops':
      return { band: band, size: 20, showCallsign: true, showRole: false, showStatus: false, zoom: zoom };
    case 'tac':
      return { band: band, size: 24, showCallsign: true, showRole: true, showStatus: false, zoom: zoom };
    case 'close':
    default:
      return { band: band, size: 28, showCallsign: true, showRole: true, showStatus: true, zoom: zoom };
  }
}

/** Taille max absolue — jamais plus grand. */
export const MAX_SYMBOL_PX = 32;
export const MIN_SYMBOL_PX = 16;

export function clampSymbolSize(px) {
  return Math.max(MIN_SYMBOL_PX, Math.min(MAX_SYMBOL_PX, Math.round(px)));
}
