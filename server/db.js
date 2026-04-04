const Database = require('better-sqlite3');
const path = require('path');

const dbPath = path.join(__dirname, 'data', 'atak.db');

function ensureDataDir() {
  const fs = require('fs');
  const dir = path.join(__dirname, 'data');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

ensureDataDir();

const db = new Database(dbPath);

db.exec(`
  CREATE TABLE IF NOT EXISTS maps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    world_name TEXT DEFAULT 'altis',
    created_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS layers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    label TEXT NOT NULL,
    phase INTEGER,
    "order" INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS markers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    layer_id INTEGER NOT NULL REFERENCES layers(id),
    marker_data TEXT NOT NULL,
    arma_name TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    call_sign TEXT NOT NULL,
    role TEXT,
    status TEXT DEFAULT 'linked',
    grid_ref TEXT,
    heading REAL,
    extra TEXT,
    updated_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    author TEXT NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS pings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    author TEXT NOT NULL,
    pos_x REAL NOT NULL,
    pos_y REAL NOT NULL,
    message TEXT,
    created_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS nine_line (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    author TEXT NOT NULL,
    line1 TEXT,
    line2 TEXT,
    line3 TEXT,
    line4 TEXT,
    line5 TEXT,
    line6 TEXT,
    line7 TEXT,
    line8 TEXT,
    line9 TEXT,
    status TEXT DEFAULT 'active',
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS intel_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    filename TEXT NOT NULL,
    path TEXT NOT NULL,
    author TEXT,
    pos_x REAL,
    pos_y REAL,
    created_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS cams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stream_id TEXT UNIQUE NOT NULL,
    label TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS designator_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    call_sign TEXT NOT NULL,
    pos_x REAL NOT NULL,
    pos_y REAL NOT NULL,
    updated_at TEXT DEFAULT (datetime('now')),
    UNIQUE(map_id, call_sign)
  );

  CREATE TABLE IF NOT EXISTS sigint_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL REFERENCES maps(id),
    call_sign TEXT NOT NULL,
    pos_x REAL NOT NULL,
    pos_y REAL NOT NULL,
    bearing REAL,
    created_at TEXT DEFAULT (datetime('now'))
  );

  INSERT OR IGNORE INTO maps (id, label, world_name) VALUES (1, 'Default', 'altis');
  INSERT OR IGNORE INTO layers (id, map_id, label, "order") VALUES (1, 1, 'Base layer', 0);
`);
try { db.exec('ALTER TABLE markers ADD COLUMN arma_name TEXT'); } catch (e) { /* column may exist */ }

const defaultMapId = 1;
const defaultLayerId = 1;

module.exports = { db, defaultMapId, defaultLayerId };
