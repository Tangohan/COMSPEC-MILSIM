/**
 * Clustering tactique — regroupe les unités proches en badge [ N ].
 */
import { renderClusterHtml } from './TacticalSymbol.js';

export class MarkerClusterManager {
  /**
   * @param {{ cellSize?: number, minCluster?: number }} opts
   */
  constructor(opts) {
    opts = opts || {};
    this.cellSize = opts.cellSize || 80;
    this.minCluster = opts.minCluster || 3;
  }

  /**
   * Regroupe les entités par cellule grille écran.
   * @param {Array<object>} entities — { id, screenX, screenY, type, affiliation, ... }
   * @param {number} zoom
   * @returns {{ singles: object[], clusters: object[] }}
   */
  cluster(entities, zoom) {
    if (!entities || !entities.length) return { singles: [], clusters: [] };
    /* Uniquement à très petite échelle : un groupe au sol ne doit pas clignoter. */
    if (Number(zoom) >= 2) return { singles: entities.slice(), clusters: [] };

    const cell = this.cellSize * (Number(zoom) <= 2 ? 1.6 : 1);
    const buckets = new Map();

    entities.forEach(function (e) {
      const cx = Math.floor(e.screenX / cell);
      const cy = Math.floor(e.screenY / cell);
      const key = cx + ':' + cy;
      if (!buckets.has(key)) buckets.set(key, []);
      buckets.get(key).push(e);
    });

    const singles = [];
    const clusters = [];

    buckets.forEach(function (members, key) {
      if (members.length < this.minCluster) {
        singles.push.apply(singles, members);
        return;
      }
      let sx = 0;
      let sy = 0;
      const breakdown = { infantry: 0, vehicle: 0, command: 0, other: 0 };
      members.forEach(function (m) {
        sx += m.screenX;
        sy += m.screenY;
        const t = String(m.type || m.unitType || '').toUpperCase();
        if (t === 'INFANTRY') breakdown.infantry += 1;
        else if (t === 'VEHICLE') breakdown.vehicle += 1;
        else if (t === 'COMMAND') breakdown.command += 1;
        else breakdown.other += 1;
      });
      clusters.push({
        id: 'cluster-' + key,
        isCluster: true,
        count: members.length,
        members: members,
        screenX: sx / members.length,
        screenY: sy / members.length,
        breakdown: breakdown,
        html: renderClusterHtml(members.length, breakdown),
      });
    }, this);

    return { singles: singles, clusters: clusters };
  }
}
