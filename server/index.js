const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const http = require('http');
const { Server } = require('socket.io');
const multer = require('multer');
const { db, defaultMapId, defaultLayerId } = require('./db');

const app = express();
const server = http.createServer(app);

const PORT = process.env.PORT || 3001;
const UPLOAD_DIR = path.join(__dirname, 'uploads', 'intel');
if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, UPLOAD_DIR),
  filename: (req, file, cb) => cb(null, Date.now() + '-' + (file.originalname || 'photo.jpg'))
});
const upload = multer({ storage });

function atakArmaAuth(req, res, next) {
  const secret = process.env.ATAK_INTEL_SECRET || process.env.X_COMSPEC_KEY;
  if (!secret || secret === '') return next();
  const token = req.headers['x-comspec-key'] || req.headers['x-atak-token'] ||
    (req.headers.authorization && req.headers.authorization.startsWith('Bearer ') && req.headers.authorization.slice(7));
  if (token === secret) return next();
  res.status(401).json({ error: 'Unauthorized', message: 'Clé Arma manquante ou invalide (X-COMSPEC-KEY / ATAK_INTEL_SECRET).' });
}

app.use(cors({ origin: true }));
app.use(express.json());
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

const io = new Server(server, {
  cors: { origin: true },
  path: '/socket.io'
});

const mapId = defaultMapId;
const layerId = defaultLayerId;

// --- REST: Markers ---
app.get('/api/markers', (req, res) => {
  const map = req.query.mapId || mapId;
  const since = req.query.since;
  let rows;
  if (since) {
    rows = db.prepare('SELECT * FROM markers WHERE map_id = ? AND (updated_at >= ? OR created_at >= ?)').all(map, since, since);
  } else {
    rows = db.prepare('SELECT * FROM markers WHERE map_id = ?').all(map);
  }
  res.json(rows.map(r => ({ id: r.id, layerId: r.layer_id, markerData: r.marker_data, updated_at: r.updated_at })));
});

app.get('/api/atak/markers', (req, res) => {
  const map = req.query.mapId || mapId;
  const since = req.query.since;
  let rows;
  if (since) {
    rows = db.prepare('SELECT * FROM markers WHERE map_id = ? AND (updated_at >= ? OR created_at >= ?)').all(map, since, since);
  } else {
    rows = db.prepare('SELECT * FROM markers WHERE map_id = ?').all(map);
  }
  res.json(rows.map(r => ({ id: r.id, layerId: r.layer_id, markerData: r.marker_data, updated_at: r.updated_at })));
});

app.post('/api/markers', (req, res) => {
  const map = req.body.mapId || mapId;
  const layer = req.body.layerId ?? layerId;
  const markerData = typeof req.body.markerData === 'string' ? req.body.markerData : JSON.stringify(req.body.markerData || {});
  const stmt = db.prepare('INSERT INTO markers (map_id, layer_id, marker_data) VALUES (?, ?, ?)');
  const run = stmt.run(map, layer, markerData);
  const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(run.lastInsertRowid);
  const out = { id: row.id, layerId: row.layer_id, markerData: row.marker_data };
  io.to(`map-${map}`).emit('AddOrUpdateMarker', { ...out, mapId: { tacMapID: map } }, false);
  res.status(201).json(out);
});

app.delete('/api/markers/:id', (req, res) => {
  const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(req.params.id);
  if (!row) return res.status(404).json({ error: 'Not found' });
  db.prepare('DELETE FROM markers WHERE id = ?').run(req.params.id);
  io.to(`map-${row.map_id}`).emit('RemoveMarker', { id: row.id, layerId: row.layer_id, mapId: { tacMapID: row.map_id } });
  res.status(204).end();
});

app.post('/api/atak/marker', (req, res) => {
  const map = req.body.mapId || mapId;
  const layer = req.body.layerId ?? layerId;
  const arma_name = req.body.arma_name || req.body.armaName || null;
  const markerData = typeof req.body.markerData === 'string' ? req.body.markerData : JSON.stringify(req.body.markerData || {});
  if (!arma_name) return res.status(400).json({ error: 'arma_name required' });
  const existing = db.prepare('SELECT id, layer_id, marker_data FROM markers WHERE map_id = ? AND arma_name = ?').get(map, arma_name);
  if (existing) {
    db.prepare('UPDATE markers SET layer_id = ?, marker_data = ?, updated_at = datetime("now") WHERE id = ?').run(layer, markerData, existing.id);
    const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(existing.id);
    const out = { id: row.id, layerId: row.layer_id, markerData: row.marker_data };
    io.to(`map-${map}`).emit('AddOrUpdateMarker', { ...out, mapId: { tacMapID: map }, data: JSON.parse(row.marker_data) }, false);
    return res.status(200).json(out);
  }
  db.prepare('INSERT INTO markers (map_id, layer_id, marker_data, arma_name) VALUES (?, ?, ?, ?)').run(map, layer, markerData, arma_name);
  const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
  const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(id);
  const out = { id: row.id, layerId: row.layer_id, markerData: row.marker_data };
  io.to(`map-${map}`).emit('AddOrUpdateMarker', { ...out, mapId: { tacMapID: map }, data: JSON.parse(row.marker_data) }, false);
  res.status(201).json(out);
});

// --- REST: Units (profiles) ---
app.get('/api/units', (req, res) => {
  const map = req.query.mapId || mapId;
  const rows = db.prepare('SELECT * FROM units WHERE map_id = ? ORDER BY call_sign').all(map);
  res.json(rows);
});

// --- REST: ATAK health check (for setup/verification tool) ---
app.get('/api/atak/ping', (req, res) => {
  res.json({ ok: true, service: 'atak' });
});

// --- REST: whoami (IP client) for affichage dans le terminal Arma ---
app.get('/api/atak/whoami', (req, res) => {
  const forwarded = req.headers['x-forwarded-for'];
  const ip = forwarded ? (typeof forwarded === 'string' ? forwarded.split(',')[0] : forwarded[0]).trim() : (req.ip || req.socket?.remoteAddress || '');
  res.json({ ip: ip || '—' });
});

// --- REST: ATAK stats (sockets, last Arma activity) for État de santé ---
let lastArmaActivityTs = null;
app.get('/api/atak/stats', (req, res) => {
  const socketsCount = io.sockets.sockets ? io.sockets.sockets.size : 0;
  res.json({
    sockets: socketsCount,
    lastArmaActivity: lastArmaActivityTs,
    lastArmaActivityAgo: lastArmaActivityTs == null ? null : Math.round((Date.now() - lastArmaActivityTs) / 1000)
  });
});

app.post('/api/atak/designator', (req, res) => {
  const map = req.body.mapId || mapId;
  const call_sign = req.body.call_sign || req.body.callsign || 'Unknown';
  const pos_x = req.body.pos_x ?? req.body.pos?.[0] ?? 0;
  const pos_y = req.body.pos_y ?? req.body.pos?.[1] ?? 0;
  const existing = db.prepare('SELECT id FROM designator_targets WHERE map_id = ? AND call_sign = ?').get(map, call_sign);
  if (existing) {
    db.prepare('UPDATE designator_targets SET pos_x = ?, pos_y = ?, updated_at = datetime("now") WHERE id = ?').run(pos_x, pos_y, existing.id);
  } else {
    db.prepare('INSERT INTO designator_targets (map_id, call_sign, pos_x, pos_y) VALUES (?, ?, ?, ?)').run(map, call_sign, pos_x, pos_y);
  }
  const row = db.prepare('SELECT * FROM designator_targets WHERE map_id = ? AND call_sign = ?').get(map, call_sign);
  io.to(`map-${map}`).emit('DesignatorUpdate', row);
  res.status(200).json(row);
});

app.get('/api/atak/designator', (req, res) => {
  const map = req.query.mapId || mapId;
  const rows = db.prepare('SELECT * FROM designator_targets WHERE map_id = ?').all(map);
  res.json(rows);
});

app.post('/api/atak/sigint', (req, res) => {
  const map = req.body.mapId || mapId;
  const call_sign = req.body.call_sign || req.body.callsign || 'Unknown';
  const pos_x = req.body.pos_x ?? req.body.pos?.[0] ?? 0;
  const pos_y = req.body.pos_y ?? req.body.pos?.[1] ?? 0;
  const bearing = req.body.bearing != null ? req.body.bearing : null;
  db.prepare('INSERT INTO sigint_reports (map_id, call_sign, pos_x, pos_y, bearing) VALUES (?, ?, ?, ?, ?)').run(map, call_sign, pos_x, pos_y, bearing);
  const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
  const row = db.prepare('SELECT * FROM sigint_reports WHERE id = ?').get(id);
  io.to(`map-${map}`).emit('SigintReport', row);
  res.status(201).json(row);
});

app.get('/api/atak/sigint/zones', (req, res) => {
  const map = req.query.mapId || mapId;
  const limit = Math.min(parseInt(req.query.limit, 10) || 50, 200);
  const rows = db.prepare('SELECT * FROM sigint_reports WHERE map_id = ? ORDER BY created_at DESC LIMIT ?').all(map, limit);
  const zones = [];
  if (rows.length >= 2) {
    const cx = rows.reduce((s, r) => s + r.pos_x, 0) / rows.length;
    const cy = rows.reduce((s, r) => s + r.pos_y, 0) / rows.length;
    const radius = Math.max(100, rows.reduce((s, r) => s + Math.hypot(r.pos_x - cx, r.pos_y - cy), 0) / rows.length * 1.5);
    zones.push({ pos_x: cx, pos_y: cy, radius, reports: rows.length });
  }
  res.json(zones);
});

// --- REST: ATAK mod position update (upsert by call_sign) — BFT: role, health, fuel, ammo in extra ---
app.post('/api/atak/position', atakArmaAuth, (req, res) => {
  lastArmaActivityTs = Date.now();
  const map = req.body.mapId || req.body.map_id || mapId;
  const call_sign = req.body.call_sign || req.body.callsign || 'Unknown';
  const pos_x = req.body.pos_x ?? req.body.pos?.[0] ?? 0;
  const pos_y = req.body.pos_y ?? req.body.pos?.[1] ?? 0;
  const grid_ref = `${Math.round(pos_x)} ${Math.round(pos_y)}`;
  const heading = req.body.heading != null ? req.body.heading : null;
  const role = req.body.role ?? '';
  const extraObj = req.body.extra && typeof req.body.extra === 'object'
    ? req.body.extra
    : { role: req.body.role || '', health: req.body.health || 'ok', fuel: req.body.fuel || '', ammo: req.body.ammo || 'n/a' };
  if (req.body.role != null) extraObj.role = req.body.role;
  if (req.body.health != null) extraObj.health = req.body.health;
  if (req.body.fuel != null) extraObj.fuel = req.body.fuel;
  if (req.body.ammo != null) extraObj.ammo = req.body.ammo;
  const extraJson = JSON.stringify(extraObj);
  const existing = db.prepare('SELECT id FROM units WHERE map_id = ? AND call_sign = ?').get(map, call_sign);
  if (existing) {
    db.prepare('UPDATE units SET grid_ref = ?, heading = ?, role = ?, extra = ?, updated_at = datetime("now") WHERE id = ?').run(grid_ref, heading, role, extraJson, existing.id);
  } else {
    db.prepare('INSERT INTO units (map_id, call_sign, role, status, grid_ref, heading, extra) VALUES (?, ?, ?, ?, ?, ?, ?)').run(map, call_sign, role, 'linked', grid_ref, heading, extraJson);
  }
  const units = db.prepare('SELECT * FROM units WHERE map_id = ?').all(map);
  io.to(`map-${map}`).emit('ProfilesUpdate', { units });
  res.status(200).json({ ok: true });
});

app.post('/api/units', (req, res) => {
  const map = req.body.mapId || mapId;
  const { call_sign, role, status, grid_ref, heading, extra } = req.body;
  const stmt = db.prepare(
    'INSERT INTO units (map_id, call_sign, role, status, grid_ref, heading, extra) VALUES (?, ?, ?, ?, ?, ?, ?)'
  );
  stmt.run(map, call_sign || 'Unknown', role || '', status || 'linked', grid_ref || '', heading || null, extra ? JSON.stringify(extra) : null);
  const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
  const row = db.prepare('SELECT * FROM units WHERE id = ?').get(id);
  io.to(`map-${map}`).emit('ProfilesUpdate', { units: db.prepare('SELECT * FROM units WHERE map_id = ?').all(map) });
  res.status(201).json(row);
});

app.patch('/api/units/:id', (req, res) => {
  const row = db.prepare('SELECT * FROM units WHERE id = ?').get(req.params.id);
  if (!row) return res.status(404).json({ error: 'Not found' });
  const { call_sign, role, status, grid_ref, heading, extra } = req.body;
  db.prepare(
    'UPDATE units SET call_sign=?, role=?, status=?, grid_ref=?, heading=?, extra=?, updated_at=datetime("now") WHERE id=?'
  ).run(call_sign ?? row.call_sign, role ?? row.role, status ?? row.status, grid_ref ?? row.grid_ref, heading ?? row.heading, extra != null ? JSON.stringify(extra) : row.extra, row.id);
  io.to(`map-${row.map_id}`).emit('ProfilesUpdate', { units: db.prepare('SELECT * FROM units WHERE map_id = ?').all(row.map_id) });
  res.json(db.prepare('SELECT * FROM units WHERE id = ?').get(row.id));
});

// --- REST: Chat ---
app.get('/api/chat', (req, res) => {
  const map = req.query.mapId || mapId;
  const limit = Math.min(parseInt(req.query.limit, 10) || 100, 500);
  const rows = db.prepare('SELECT * FROM chat_messages WHERE map_id = ? ORDER BY created_at DESC LIMIT ?').all(map, limit);
  res.json(rows.reverse());
});

app.post('/api/chat', (req, res) => {
  const map = req.body.mapId || mapId;
  const { author, body } = req.body;
  db.prepare('INSERT INTO chat_messages (map_id, author, body) VALUES (?, ?, ?)').run(map, author || 'Anonymous', body || '');
  const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
  const row = db.prepare('SELECT * FROM chat_messages WHERE id = ?').get(id);
  io.to(`map-${map}`).emit('Chat', row);
  res.status(201).json(row);
});

// --- REST: Pings ---
app.get('/api/pings', (req, res) => {
  const map = req.query.mapId || mapId;
  const limit = parseInt(req.query.limit, 10) || 50;
  const rows = db.prepare('SELECT * FROM pings WHERE map_id = ? ORDER BY created_at DESC LIMIT ?').all(map, limit);
  res.json(rows);
});

app.post('/api/pings', (req, res) => {
  const map = req.body.mapId || mapId;
  const { author, pos_x, pos_y, pos, message } = req.body;
  const x = pos_x ?? pos?.[0] ?? 0;
  const y = pos_y ?? pos?.[1] ?? 0;
  db.prepare('INSERT INTO pings (map_id, author, pos_x, pos_y, message) VALUES (?, ?, ?, ?, ?)').run(map, author || 'Anonymous', x, y, message || '');
  const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
  const row = db.prepare('SELECT * FROM pings WHERE id = ?').get(id);
  io.to(`map-${map}`).emit('Ping', row);
  res.status(201).json(row);
});

// --- REST: ATAK Intel (Ping / Chat / Photo from mod SendIntel) ---
app.post('/api/atak/intel', atakArmaAuth, (req, res) => {
  const map = req.body.mapId || mapId;
  const { type, body, data, author: reqAuthor } = req.body;
  const author = reqAuthor || 'Arma';
  if (type === 'PING') {
    const parts = (data || '').split(',').map(s => parseFloat(s.trim()));
    const x = parts[0] ?? 0;
    const y = parts[1] ?? 0;
    db.prepare('INSERT INTO pings (map_id, author, pos_x, pos_y, message) VALUES (?, ?, ?, ?, ?)').run(map, author, x, y, body || '');
    const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
    const row = db.prepare('SELECT * FROM pings WHERE id = ?').get(id);
    io.to(`map-${map}`).emit('Ping', row);
    return res.status(201).json(row);
  }
  if (type === 'CHAT') {
    db.prepare('INSERT INTO chat_messages (map_id, author, body) VALUES (?, ?, ?)').run(map, author, body || '');
    const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
    const row = db.prepare('SELECT * FROM chat_messages WHERE id = ?').get(id);
    io.to(`map-${map}`).emit('Chat', row);
    return res.status(201).json(row);
  }
  if (type === 'PHOTO') {
    const base64Data = (data || '').replace(/^data:image\/\w+;base64,/, '');
    if (!base64Data) return res.status(400).json({ error: 'PHOTO requires body.data (base64 image).' });
    const filename = 'ctab_' + Date.now() + '.jpg';
    const filepath = path.join(UPLOAD_DIR, filename);
    try {
      fs.writeFileSync(filepath, base64Data, 'base64');
    } catch (e) {
      return res.status(500).json({ error: 'Failed to write image.', message: e.message });
    }
    const dbPath = path.join('intel', filename);
    db.prepare('INSERT INTO intel_photos (map_id, filename, path, author) VALUES (?, ?, ?, ?)').run(map, filename, dbPath, author);
    const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
    const row = db.prepare('SELECT * FROM intel_photos WHERE id = ?').get(id);
    row.url = '/uploads/intel/' + filename;
    io.to('map-' + map).emit('IntelPhoto', row);
    return res.status(201).json(row);
  }
  res.status(200).json({ ok: true });
});

// --- REST: Nine-Line CAS ---
app.get('/api/nine-line', (req, res) => {
  const map = req.query.mapId || mapId;
  const rows = db.prepare('SELECT * FROM nine_line WHERE map_id = ? ORDER BY updated_at DESC').all(map);
  res.json(rows);
});

app.post('/api/nine-line', (req, res) => {
  const map = req.body.mapId || mapId;
  const author = req.body.author || 'JTAC';
  const lines = ['line1','line2','line3','line4','line5','line6','line7','line8','line9'].map(k => req.body[k] ?? '');
  db.prepare(
    `INSERT INTO nine_line (map_id, author, line1, line2, line3, line4, line5, line6, line7, line8, line9, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')`
  ).run(map, author, ...lines);
  const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
  const row = db.prepare('SELECT * FROM nine_line WHERE id = ?').get(id);
  io.to(`map-${map}`).emit('NineLineUpdate', row);
  res.status(201).json(row);
});

app.patch('/api/nine-line/:id', (req, res) => {
  const row = db.prepare('SELECT * FROM nine_line WHERE id = ?').get(req.params.id);
  if (!row) return res.status(404).json({ error: 'Not found' });
  const status = req.body.status ?? row.status;
  db.prepare('UPDATE nine_line SET status=?, updated_at=datetime("now") WHERE id=?').run(status, row.id);
  const updated = db.prepare('SELECT * FROM nine_line WHERE id = ?').get(row.id);
  io.to(`map-${row.map_id}`).emit('NineLineUpdate', updated);
  res.json(updated);
});

// --- REST: Cams (list stream ids for WebRTC) ---
app.get('/api/cams', (req, res) => {
  const rows = db.prepare('SELECT * FROM cams ORDER BY created_at').all();
  res.json(rows);
});

app.post('/api/cams', (req, res) => {
  const { stream_id, label } = req.body;
  db.prepare('INSERT OR REPLACE INTO cams (stream_id, label) VALUES (?, ?)').run(stream_id || 'stream-' + Date.now(), label || 'Cam');
  const rows = db.prepare('SELECT * FROM cams ORDER BY created_at').all();
  res.status(201).json(rows);
});

// --- REST: Intel photos ---
app.get('/api/intel/photos', (req, res) => {
  const map = req.query.mapId || mapId;
  const rows = db.prepare('SELECT * FROM intel_photos WHERE map_id = ? ORDER BY created_at DESC').all(map);
  res.json(rows.map(r => ({ ...r, url: '/uploads/intel/' + path.basename(r.path) })));
});

// POST /api/intel/photos — Contract for mod CTAB (Arma): multipart with `photo` (file), `mapId`, `author` or `callsign`, `pos_x`, `pos_y` (optional). If ATAK_INTEL_SECRET is set, require header X-ATAK-Token or Authorization: Bearer <secret>.
// Optional auth for POST /api/intel/photos (mod CTAB from Arma): set ATAK_INTEL_SECRET in env, mod sends X-ATAK-Token or Authorization: Bearer <secret>
function intelPhotoAuth(req, res, next) {
  const secret = process.env.ATAK_INTEL_SECRET;
  if (!secret || secret === '') return next();
  const token = req.headers['x-atak-token'] || (req.headers.authorization && req.headers.authorization.startsWith('Bearer ') && req.headers.authorization.slice(7));
  if (token === secret) return next();
  res.status(401).json({ error: 'Unauthorized', message: 'Token manquant ou invalide pour l\'upload CTAB.' });
}

app.post('/api/intel/photos', intelPhotoAuth, upload.single('photo'), (req, res) => {
  const map = req.body.mapId || mapId;
  const author = req.body.author || req.body.callsign || 'Unknown';
  const pos_x = req.body.pos_x != null ? parseFloat(req.body.pos_x) : null;
  const pos_y = req.body.pos_y != null ? parseFloat(req.body.pos_y) : null;
  const filename = req.file ? req.file.filename : (req.body.url || 'photo');
  const filepath = req.file ? path.join('intel', path.basename(req.file.path)) : (req.body.url || '');
  db.prepare('INSERT INTO intel_photos (map_id, filename, path, author, pos_x, pos_y) VALUES (?, ?, ?, ?, ?, ?)').run(map, filename, filepath, author, pos_x, pos_y);
  const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
  const row = db.prepare('SELECT * FROM intel_photos WHERE id = ?').get(id);
  row.url = '/uploads/' + (req.file ? 'intel/' + req.file.filename : filepath);
  io.to(`map-${map}`).emit('IntelPhoto', row);
  res.status(201).json(row);
});

// --- Socket.IO: Map sync (MapHub-compatible) ---
io.on('connection', (socket) => {
  socket.on('Hello', (payload) => {
    const mid = (payload && payload.tacMapID) || mapId;
    socket.join(`map-${mid}`);
    socket.mapId = mid;

    const layers = db.prepare('SELECT * FROM layers WHERE map_id = ? ORDER BY "order"').all(mid);
    layers.forEach(l => {
      socket.emit('AddOrUpdateLayer', {
        id: l.id,
        mapId: { tacMapID: mid },
        data: { label: l.label, phase: l.phase, order: l.order },
        isDefaultLayer: l.id === layerId
      });
    });

    const markers = db.prepare('SELECT * FROM markers WHERE map_id = ?').all(mid);
    markers.forEach(m => {
      socket.emit('AddOrUpdateMarker', {
        id: m.id,
        layerId: m.layer_id,
        mapId: { tacMapID: mid },
        data: (() => { try { return JSON.parse(m.marker_data); } catch { return {}; } })()
      }, false);
    });

    const units = db.prepare('SELECT * FROM units WHERE map_id = ?').all(mid);
    socket.emit('ProfilesUpdate', { units });
  });

  socket.on('AddMarkerToLayer', (layerIdParam, markerData) => {
    const mid = socket.mapId || mapId;
    const lid = layerIdParam ?? layerId;
    const data = typeof markerData === 'string' ? markerData : JSON.stringify(markerData || {});
    db.prepare('INSERT INTO markers (map_id, layer_id, marker_data) VALUES (?, ?, ?)').run(mid, lid, data);
    const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
    const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(id);
    const out = { id: row.id, layerId: row.layer_id, mapId: { tacMapID: mid }, data: JSON.parse(row.marker_data) };
    io.to(`map-${mid}`).emit('AddOrUpdateMarker', out, false);
  });

  socket.on('RemoveMarker', (markerId) => {
    const mid = socket.mapId || mapId;
    const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(markerId);
    if (row && row.map_id === mid) {
      db.prepare('DELETE FROM markers WHERE id = ?').run(markerId);
      io.to(`map-${mid}`).emit('RemoveMarker', { id: row.id, layerId: row.layer_id, mapId: { tacMapID: mid } });
    }
  });

  socket.on('UpdateMarkerToLayer', (markerId, layerIdParam, markerData) => {
    const mid = socket.mapId || mapId;
    const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(markerId);
    if (!row || row.map_id !== mid) return;
    const data = typeof markerData === 'string' ? markerData : JSON.stringify(markerData || {});
    db.prepare('UPDATE markers SET layer_id=?, marker_data=?, updated_at=datetime("now") WHERE id=?').run(layerIdParam ?? row.layer_id, data, markerId);
    const updated = db.prepare('SELECT * FROM markers WHERE id = ?').get(markerId);
    const out = { id: updated.id, layerId: updated.layer_id, mapId: { tacMapID: mid }, data: JSON.parse(updated.marker_data) };
    io.to(`map-${mid}`).emit('AddOrUpdateMarker', out, false);
  });

  socket.on('MoveMarker', (markerId, markerData) => {
    const mid = socket.mapId || mapId;
    const row = db.prepare('SELECT * FROM markers WHERE id = ?').get(markerId);
    if (!row || row.map_id !== mid) return;
    const data = typeof markerData === 'string' ? markerData : JSON.stringify(markerData || {});
    db.prepare('UPDATE markers SET marker_data=?, updated_at=datetime("now") WHERE id=?').run(data, markerId);
    const updated = db.prepare('SELECT * FROM markers WHERE id = ?').get(markerId);
    const out = { id: updated.id, layerId: updated.layer_id, mapId: { tacMapID: mid }, data: JSON.parse(updated.marker_data) };
    io.to(`map-${mid}`).emit('AddOrUpdateMarker', out, false);
  });

  socket.on('AddLayer', (layerData) => {
    const mid = socket.mapId || mapId;
    db.prepare('INSERT INTO layers (map_id, label, phase, "order") VALUES (?, ?, ?, ?)').run(mid, layerData?.label || 'Layer', layerData?.phase ?? null, layerData?.order ?? 0);
    const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
    const l = db.prepare('SELECT * FROM layers WHERE id = ?').get(id);
    io.to(`map-${mid}`).emit('AddOrUpdateLayer', { id: l.id, mapId: { tacMapID: mid }, data: { label: l.label, phase: l.phase, order: l.order }, isDefaultLayer: false });
  });

  socket.on('RemoveLayer', (layerIdParam) => {
    const mid = socket.mapId || mapId;
    if (layerIdParam === layerId) return;
    db.prepare('DELETE FROM markers WHERE layer_id = ?').run(layerIdParam);
    db.prepare('DELETE FROM layers WHERE id = ? AND map_id = ?').run(layerIdParam, mid);
    io.to(`map-${mid}`).emit('RemoveLayer', { id: layerIdParam, mapId: { tacMapID: mid } });
  });

  socket.on('PointMap', (pos) => {
    const mid = socket.mapId || mapId;
    socket.broadcast.to(`map-${mid}`).emit('PointMap', socket.id, pos);
  });

  socket.on('EndPointMap', () => {
    const mid = socket.mapId || mapId;
    socket.broadcast.to(`map-${mid}`).emit('EndPointMap', socket.id);
  });

  socket.on('Chat', (msg) => {
    const mid = socket.mapId || mapId;
    db.prepare('INSERT INTO chat_messages (map_id, author, body) VALUES (?, ?, ?)').run(mid, msg?.author || 'User', msg?.body || '');
    const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
    const row = db.prepare('SELECT * FROM chat_messages WHERE id = ?').get(id);
    io.to(`map-${mid}`).emit('Chat', row);
  });

  socket.on('Ping', (payload) => {
    const mid = socket.mapId || mapId;
    const x = payload?.pos_x ?? payload?.pos?.[0] ?? 0;
    const y = payload?.pos_y ?? payload?.pos?.[1] ?? 0;
    db.prepare('INSERT INTO pings (map_id, author, pos_x, pos_y, message) VALUES (?, ?, ?, ?, ?)').run(mid, payload?.author || 'User', x, y, payload?.message || '');
    const id = db.prepare('SELECT last_insert_rowid() as id').get().id;
    const row = db.prepare('SELECT * FROM pings WHERE id = ?').get(id);
    io.to(`map-${mid}`).emit('Ping', row);
  });
});

// --- WebRTC signaling (simple offer/answer for viewers) ---
io.on('connection', (socket) => {
  socket.on('webrtc-offer', (payload) => {
    socket.broadcast.emit('webrtc-offer', { from: socket.id, ...payload });
  });
  socket.on('webrtc-answer', (payload) => {
    io.to(payload.to).emit('webrtc-answer', { from: socket.id, answer: payload.answer });
  });
  socket.on('webrtc-ice', (payload) => {
    const target = payload.to ? io.sockets.sockets.get(payload.to) : null;
    if (target) target.emit('webrtc-ice', { from: socket.id, candidate: payload.candidate });
    else socket.broadcast.emit('webrtc-ice', { from: socket.id, candidate: payload.candidate });
  });
});

server.listen(PORT, () => {
  console.log('COMSPEC ATAK server on http://localhost:' + PORT);
});
