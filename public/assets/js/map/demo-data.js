/**
 * Données de démo — unités, traces, journal.
 */
export function createDemoUnits(count) {
  count = count || 24;
  const units = [];
  const types = ['INFANTRY', 'VEHICLE', 'AIR', 'UAV', 'COMMAND', 'MEDICAL'];
  const affs = ['FRIENDLY', 'FRIENDLY', 'FRIENDLY', 'HOSTILE', 'UNKNOWN', 'NEUTRAL'];
  const statuses = ['ONLINE', 'ONLINE', 'ONLINE', 'DEGRADED', 'STALE'];

  for (let i = 0; i < count; i += 1) {
    const aff = affs[i % affs.length];
    const prefix = aff === 'HOSTILE' ? 'X' : aff === 'UNKNOWN' ? 'U' : 'N';
    units.push({
      id: 'u-' + i,
      callsign: prefix + '-' + String(i + 1).padStart(2, '0'),
      role: i % 5 === 0 ? 'Équipe Alpha' : i % 3 === 0 ? 'Section' : '',
      affiliation: aff,
      type: types[i % types.length],
      status: statuses[i % statuses.length],
      heading: (i * 37) % 360,
      speed: 10 + (i % 8) * 5,
      altitude: 80 + (i % 12) * 15,
      x: 400 + (i % 8) * 70 + Math.sin(i) * 30,
      y: 400 + Math.floor(i / 8) * 65 + Math.cos(i) * 25,
      grid: '48T XP ' + (51200 + i * 110),
    });
  }
  return units;
}

export function createDemoTracks(units) {
  return (units || []).slice(0, 8).map(function (u, idx) {
    const points = [];
    for (let j = 0; j < 12; j += 1) {
      points.push({
        x: u.x - j * 8 + Math.sin(j + idx) * 4,
        y: u.y - j * 6 + Math.cos(j + idx) * 3,
        t: Date.now() - j * 60000,
        extrapolated: j < 2,
      });
    }
    return {
      id: 'track-' + u.id,
      color: u.affiliation === 'HOSTILE' ? '#dc5d5d' : '#3ecfb4',
      points: points,
    };
  });
}

export function createDemoLog() {
  return [
    { time: '22:38:01', text: 'Contact hostile détecté X-01', level: 'warn' },
    { time: '22:37:44', text: 'N-10 ALPHA — position actualisée', level: 'info' },
    { time: '22:36:12', text: 'Relief théâtre — couverture 78 %', level: 'info' },
    { time: '22:35:00', text: 'U-01 INCONNU — piste SSE ouverte', level: 'info' },
  ];
}
