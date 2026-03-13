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
  const rows = db.prepare('SELECT * FROM markers WHERE map_id = ?').all(map);
  res.json(rows.map(r => ({ id: r.id, layerId: r.layer_id, markerData: r.marker_data })));
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

// --- REST: Units (profiles) ---
app.get('/api/units', (req, res) => {
  const map = req.query.mapId || mapId;
  const rows = db.prepare('SELECT * FROM units WHERE map_id = ? ORDER BY call_sign').all(map);
  res.json(rows);
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

app.post('/api/intel/photos', upload.single('photo'), (req, res) => {
  const map = req.body.mapId || mapId;
  const author = req.body.author || 'Unknown';
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
