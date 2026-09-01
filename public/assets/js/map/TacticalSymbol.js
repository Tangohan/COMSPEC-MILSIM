/**
 * Symbologie tactique Athena — inspirée MIL-STD-2525 / APP-6, simplifiée.
 * Silhouettes géométriques compactes (18–32 px), sans pictogrammes web.
 */
import { clamp } from '../terrain3d/utils.js';

/** @typedef {'FRIENDLY'|'HOSTILE'|'UNKNOWN'|'NEUTRAL'} Affiliation */
/** @typedef {'INFANTRY'|'VEHICLE'|'AIR'|'UAV'|'COMMAND'|'MEDICAL'|'OBSERVATION'|'STATIC_POSITION'} UnitType */
/** @typedef {'ONLINE'|'DEGRADED'|'STALE'|'LOST'|'KIA'} LinkStatus */

export const AFFILIATION_COLORS = {
  FRIENDLY: { fill: '#1a3a52', stroke: '#5eb8e8', glyph: '#9fd4f7', label: '#dce5eb' },
  HOSTILE: { fill: '#3a1a1a', stroke: '#e86a6a', glyph: '#ffb0b0', label: '#f0d0d0' },
  UNKNOWN: { fill: '#3a3518', stroke: '#e8c84d', glyph: '#ffe680', label: '#ebe5c8' },
  NEUTRAL: { fill: '#1a3a28', stroke: '#6ecf98', glyph: '#a8e8c0', label: '#d0ebe0' },
};

export function affiliationKey(raw) {
  const a = String(raw || 'FRIENDLY').toUpperCase();
  if (a === 'HOSTILE' || a === 'ENEMY' || a === 'H' || a === 'RED' || a === 'EAST' || a === 'OPFOR') return 'HOSTILE';
  if (a === 'NEUTRAL' || a === 'N' || a === 'CIV' || a === 'CIVILIAN') return 'NEUTRAL';
  if (a === 'UNKNOWN' || a === 'U' || a === 'GUER' || a === 'INDEP' || a === 'RESISTANCE' || a === 'SUSPECT') return 'UNKNOWN';
  return 'FRIENDLY';
}

export const UNIT_TYPES = [
  'INFANTRY', 'VEHICLE', 'AIR', 'UAV', 'COMMAND', 'MEDICAL', 'OBSERVATION', 'STATIC_POSITION',
];

/** Cadre selon affiliation (viewBox 0 0 32 32). */
export function framePath(affiliation) {
  switch (String(affiliation || 'FRIENDLY').toUpperCase()) {
    case 'HOSTILE':
      return 'M16 2 L30 16 L16 30 L2 16 Z';
    case 'NEUTRAL':
      return 'M6 6 H26 V26 H6 Z';
    case 'UNKNOWN':
      return 'M16 3 C21 3 29 11 29 16 C29 21 21 29 16 29 C11 29 3 21 3 16 C3 11 11 3 16 3 Z';
    case 'FRIENDLY':
    default:
      return 'M4 7 H28 V25 H4 Z';
  }
}

/** Glyphe interne minimal par type d'unité. */
export function innerGlyph(type) {
  const t = String(type || 'INFANTRY').toUpperCase();
  switch (t) {
    case 'VEHICLE':
      return '<ellipse cx="16" cy="17" rx="6" ry="3.5" fill="none" stroke="currentColor" stroke-width="1.4"/>'
        + '<circle cx="11" cy="17" r="1.2" fill="currentColor"/><circle cx="21" cy="17" r="1.2" fill="currentColor"/>';
    case 'AIR':
      return '<path d="M8 16 H24 M16 9 V23 M11 12 L16 9 L21 12" fill="none" stroke="currentColor" stroke-width="1.5"/>';
    case 'UAV':
      return '<path d="M11 19 L16 11 L21 19 Z" fill="none" stroke="currentColor" stroke-width="1.4"/>'
        + '<circle cx="16" cy="21" r="1.3" fill="currentColor"/>';
    case 'COMMAND':
      return '<rect x="12" y="10" width="8" height="9" fill="none" stroke="currentColor" stroke-width="1.4"/>'
        + '<line x1="12" y1="14" x2="20" y2="14" stroke="currentColor" stroke-width="1.2"/>';
    case 'MEDICAL':
      return '<line x1="16" y1="10" x2="16" y2="22" stroke="currentColor" stroke-width="1.8"/>'
        + '<line x1="10" y1="16" x2="22" y2="16" stroke="currentColor" stroke-width="1.8"/>';
    case 'OBSERVATION':
      return '<circle cx="16" cy="16" r="5" fill="none" stroke="currentColor" stroke-width="1.4"/>'
        + '<circle cx="16" cy="16" r="1.6" fill="currentColor"/>';
    case 'STATIC_POSITION':
      return '<circle cx="16" cy="16" r="2.5" fill="currentColor"/>'
        + '<line x1="16" y1="8" x2="16" y2="12" stroke="currentColor" stroke-width="1.3"/>'
        + '<line x1="16" y1="20" x2="16" y2="24" stroke="currentColor" stroke-width="1.3"/>'
        + '<line x1="8" y1="16" x2="12" y2="16" stroke="currentColor" stroke-width="1.3"/>'
        + '<line x1="20" y1="16" x2="24" y2="16" stroke="currentColor" stroke-width="1.3"/>';
    case 'INFANTRY':
    default:
      return '<line x1="10" y1="10" x2="22" y2="22" stroke="currentColor" stroke-width="1.7"/>'
        + '<line x1="22" y1="10" x2="10" y2="22" stroke="currentColor" stroke-width="1.7"/>';
  }
}

/** Modificateurs visuels de statut liaison. */
export function statusStyle(status) {
  const s = String(status || 'ONLINE').toUpperCase();
  switch (s) {
    case 'DEGRADED': return { opacity: 0.72, dash: null, ghost: false, strike: false };
    case 'STALE': return { opacity: 0.85, dash: '3 2', ghost: false, strike: false };
    case 'LOST': return { opacity: 0.38, dash: '2 3', ghost: true, strike: false };
    case 'KIA': return { opacity: 0.55, dash: null, ghost: false, strike: true };
    case 'ONLINE':
    default: return { opacity: 1, dash: null, ghost: false, strike: false };
  }
}

/**
 * Indicateur de cap — chevron indépendant du symbole.
 * @param {number} headingDeg — cap en degrés (0 = nord)
 * @param {number} speed — vitesse (longueur chevron)
 */
export function headingIndicator(headingDeg, speed) {
  const h = Number(headingDeg) || 0;
  const len = clamp(6 + (Number(speed) || 0) * 0.08, 6, 14);
  return '<g transform="rotate(' + h + ' 16 28)" class="tac-sym-heading">'
    + '<path d="M16 ' + (32 - len) + ' L13 30 L16 32 L19 30 Z" fill="currentColor" opacity="0.85"/>'
    + '</g>';
}

/**
 * Génère le SVG complet d'un symbole tactique.
 * @param {object} entity
 * @param {number} sizePx — taille cible en pixels
 */
export function renderSymbolSvg(entity, sizePx, opts) {
  entity = entity || {};
  opts = opts || {};
  const aff = affiliationKey(entity.affiliation);
  const colors = AFFILIATION_COLORS[aff] || AFFILIATION_COLORS.FRIENDLY;
  const st = statusStyle(entity.status || entity.linkStatus || 'ONLINE');
  const size = clamp(sizePx || 20, 12, 40);
  const frame = framePath(aff);
  const glyph = innerGlyph(entity.type || entity.unitType || 'INFANTRY');
  const heading = opts.showHeading !== false && entity.heading != null
    ? headingIndicator(entity.heading, entity.speed)
    : '';

  let strike = '';
  if (st.strike) {
    strike = '<line x1="6" y1="6" x2="26" y2="26" stroke="' + colors.stroke + '" stroke-width="1.5" opacity="0.7"/>';
  }

  const dashAttr = st.dash ? ' stroke-dasharray="' + st.dash + '"' : '';

  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="' + size + '" height="' + size + '"'
    + ' class="tac-sym' + (st.ghost ? ' tac-sym--ghost' : '') + '"'
    + ' style="opacity:' + st.opacity + '" aria-hidden="true">'
    + '<path d="' + frame + '" fill="' + colors.fill + '" stroke="' + colors.stroke + '"'
    + ' stroke-width="1.6"' + dashAttr + '/>'
    + '<g color="' + colors.glyph + '">' + glyph + '</g>'
    + heading + strike
    + '</svg>';
}

/**
 * HTML complet marqueur 2D (symbole + labels LOD).
 * @param {object} entity
 * @param {import('./MarkerLOD.js').LODLevel} lod
 */
export function renderMarkerHtml(entity, lod) {
  entity = entity || {};
  lod = lod || { size: 20, showCallsign: true, showRole: false, showStatus: false };
  const style = String(lod.styleMode || entity.styleMode || 'nato');
  const showHeading = lod.showHeading !== false;
  const ft = lod.showFtFrame && entity.ftColor ? String(entity.ftColor) : '';
  let inner = '';
  if (style === 'intel_dot' || style === 'dot' || style === 'team_dot') {
    inner = renderDotInner(entity, lod, style);
  } else if (lod.preferAvatar && entity.avatarUrl) {
    inner = renderAvatarInner(entity, lod);
  } else {
    inner = '<div class="tac-marker__symbol">' + renderSymbolSvg(entity, lod.size, { showHeading: showHeading }) + '</div>';
  }
  if (ft) {
    inner = '<div class="tac-marker__ft" style="--ft-color:' + escapeAttr(ft) + '">' + inner + '</div>';
  }
  return '<div class="tac-marker" data-id="' + escapeAttr(entity.id) + '">' + inner + callsignLabel(entity, lod) + '</div>';
}

function callsignLabel(entity, lod) {
  if (!lod.showCallsign || !entity.callsign) return '';
  return '<div class="tac-marker__callsign mono">' + escapeHtml(entity.callsign) + '</div>';
}

function renderDotInner(entity, lod, style) {
  const d = Math.max(10, Math.round(lod.size || 16));
  let color = '#22c55e';
  if (style === 'intel_dot') color = '#94a3b8';
  if (style === 'team_dot' && entity.ftColor) color = entity.ftColor;
  else if (style !== 'intel_dot') {
    const aff = affiliationKey(entity.affiliation);
    color = (AFFILIATION_COLORS[aff] || AFFILIATION_COLORS.FRIENDLY).stroke;
  }
  const cls = style === 'intel_dot' ? 'tac-marker__dot tac-marker__dot--intel' : 'tac-marker__dot';
  return '<span class="' + cls + '" style="width:' + d + 'px;height:' + d + 'px;background:' + escapeAttr(color) + ';"></span>';
}

function renderAvatarInner(entity, lod) {
  const d = Math.max(12, Math.round(lod.size || 20));
  const src = String(entity.avatarUrl || '').replace(/"/g, '&quot;');
  return '<img class="tac-marker__avatar" src="' + src + '" alt="" width="' + d + '" height="' + d + '" style="width:' + d + 'px;height:' + d + 'px;"/>';
}

/** Lignes d’infobulle (rôle, état, grille) — pas collées sous le symbole. */
export function markerHoverLines(entity) {
  entity = entity || {};
  const lines = [];
  if (entity.role) lines.push(entity.role);
  if (entity.status) lines.push(statusLabelFr(entity.status));
  if (entity.grid) lines.push(entity.grid);
  return lines;
}

function statusLabelFr(status) {
  const s = String(status || '').toUpperCase();
  if (s === 'ONLINE') return 'En liaison';
  if (s === 'DEGRADED') return 'Liaison dégradée';
  if (s === 'STALE') return 'Position en retard';
  if (s === 'LOST') return 'Hors liaison';
  if (s === 'KIA') return 'Hors combat';
  return status;
}

/** Marqueur 3D avec ligne d'ancrage et point au sol. */
export function renderMarker3DHtml(entity, lod) {
  entity = entity || {};
  lod = lod || { size: 20, showCallsign: true, showRole: false, showStatus: false };
  const sym = renderSymbolSvg(entity, lod.size);
  let html = '<div class="tac-marker-3d" data-id="' + escapeAttr(entity.id) + '">';
  html += '<div class="tac-marker-3d__stack">';

  if (lod.showCallsign && entity.callsign) {
    html += '<div class="tac-marker-3d__label mono">' + escapeHtml(entity.callsign) + '</div>';
  }
  html += '<div class="tac-marker-3d__symbol">' + sym + '</div>';
  html += '<div class="tac-marker-3d__stem"></div>';
  html += '<div class="tac-marker-3d__ground"></div>';
  html += '</div></div>';
  return html;
}

/** Cluster badge tactique. */
export function renderClusterHtml(count, breakdown) {
  breakdown = breakdown || {};
  let detail = '';
  if (breakdown.infantry) detail += breakdown.infantry + ' INF ';
  if (breakdown.vehicle) detail += breakdown.vehicle + ' VEH ';
  if (breakdown.command) detail += breakdown.command + ' CMD ';
  return '<div class="tac-cluster">'
    + '<span class="tac-cluster__count mono">' + count + '</span>'
    + (detail ? '<span class="tac-cluster__detail">' + escapeHtml(detail.trim()) + '</span>' : '')
    + '</div>';
}

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function escapeAttr(s) {
  return escapeHtml(s).replace(/"/g, '&quot;');
}

export { escapeHtml, escapeAttr };
