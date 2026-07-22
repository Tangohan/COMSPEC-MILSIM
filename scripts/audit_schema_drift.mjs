/**
 * Audit dérive schéma offline (Node) — schema.sql vs dump SQL.
 * Usage: node scripts/audit_schema_drift.mjs [dump.sql]
 * Défaut dump: u416380327_BDD_PROD.sql
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const schemaPath = path.join(root, 'migrations', 'schema.sql');
const dumpArg = process.argv[2] || 'u416380327_BDD_PROD.sql';
const dumpPath = path.isAbsolute(dumpArg) ? dumpArg : path.join(root, dumpArg);

function stripComments(sql) {
  return sql.replace(/--[^\r\n]*/g, '');
}

function splitParts(body) {
  const parts = [];
  let buf = '';
  let depth = 0;
  let inStr = false;
  let strCh = '';
  for (let i = 0; i < body.length; i++) {
    const ch = body[i];
    if (inStr) {
      buf += ch;
      if (ch === strCh && body[i - 1] !== '\\') inStr = false;
      continue;
    }
    if (ch === "'" || ch === '"' || ch === '`') {
      inStr = true;
      strCh = ch;
      buf += ch;
      continue;
    }
    if (ch === '(') {
      depth++;
      buf += ch;
      continue;
    }
    if (ch === ')') {
      depth--;
      buf += ch;
      continue;
    }
    if (ch === ',' && depth === 0) {
      parts.push(buf);
      buf = '';
      continue;
    }
    buf += ch;
  }
  if (buf.trim()) parts.push(buf);
  return parts;
}

function parseCreateTables(sql) {
  const cleaned = stripComments(sql);
  const re = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE/gis;
  const out = {};
  let m;
  while ((m = re.exec(cleaned)) !== null) {
    const table = m[1];
    const cols = {};
    for (const partRaw of splitParts(m[2])) {
      const part = partRaw.trim();
      if (!part) continue;
      if (/^(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|CONSTRAINT|FULLTEXT|SPATIAL)\b/i.test(part)) continue;
      const cm = part.match(/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/s);
      if (!cm) continue;
      cols[cm[1]] = part.replace(/\s+/g, ' ').trim();
    }
    if (Object.keys(cols).length) out[table] = cols;
  }
  return out;
}

function normalizeType(def) {
  let d = def.toLowerCase().replace(/`/g, '');
  d = d.replace(/^[a-z0-9_]+\s+/, '').trim();
  const hadJsonValid = /json_valid/.test(d) || /json_valid/.test(def.toLowerCase());
  d = d
    .replace(/\sint\(\d+\)/g, ' int')
    .replace(/\sbigint\(\d+\)/g, ' bigint')
    .replace(/\stinyint\(\d+\)/g, ' tinyint')
    .replace(/\ssmallint\(\d+\)/g, ' smallint')
    .replace(/\smediumint\(\d+\)/g, ' mediumint')
    .replace(/character set\s+\w+/g, '')
    .replace(/collate\s+\w+/g, '')
    .replace(/check\s*\([^)]*\)/g, '')
    .replace(/\s+/g, ' ')
    .trim();
  let base = null;
  if (/\blongtext\b/.test(d) && hadJsonValid) base = 'longtext_json_compat';
  else if (/\bjson\b/.test(d)) base = 'json';
  else {
    const m = d.match(
      /^(tinyint|smallint|mediumint|int|bigint|decimal\([^)]+\)|float|double|varchar\(\d+\)|char\(\d+\)|text|mediumtext|longtext|tinytext|datetime|timestamp|date|time|blob|mediumblob|longblob|enum\([^)]+\)|set\([^)]+\))/
    );
    if (m) base = m[1];
  }
  if (!base) return d;
  const nullable = !/\bnot null\b/.test(d);
  return `${base}|${nullable ? 'null' : 'notnull'}`;
}

const expected = parseCreateTables(fs.readFileSync(schemaPath, 'utf8'));
const actual = parseCreateTables(fs.readFileSync(dumpPath, 'utf8'));

console.log('=== Audit dérive schéma (offline) ===');
console.log(`Attendu : migrations/schema.sql (${Object.keys(expected).length} tables)`);
console.log(`Réel    : dump ${path.basename(dumpPath)} (${Object.keys(actual).length} tables CREATE)`);
console.log('');

const missingTables = [];
const drifts = [];

for (const [table, cols] of Object.entries(expected)) {
  if (!actual[table]) {
    missingTables.push(table);
    continue;
  }
  for (const [col, expectedDef] of Object.entries(cols)) {
    if (!actual[table][col]) {
      drifts.push({ kind: 'missing_column', table, column: col, expected: expectedDef });
      continue;
    }
    const en = normalizeType(expectedDef);
    const an = normalizeType(actual[table][col]);
    if (en !== an) {
      drifts.push({
        kind: 'type_mismatch',
        table,
        column: col,
        expected: expectedDef,
        actual: actual[table][col],
        expected_norm: en,
        actual_norm: an,
      });
    }
  }
}

if (missingTables.length) {
  console.log(`--- Tables absentes du dump (${missingTables.length}) ---`);
  for (const t of missingTables) console.log(`  [TABLE MANQUANTE] ${t}`);
  console.log('');
}

if (!drifts.length) {
  console.log('Aucune dérive de colonne détectée sur les tables présentes.');
  process.exit(0);
}

console.log(`--- Dérives colonnes (${drifts.length}) ---`);
for (const d of drifts) {
  if (d.kind === 'missing_column') {
    console.log(`  [COLONNE MANQUANTE] ${d.table}.${d.column}`);
    console.log(`      attendu : ${d.expected}`);
    console.log(`      ALTER   : ALTER TABLE \`${d.table}\` ADD COLUMN ${d.expected};`);
  } else {
    console.log(`  [TYPE DIFFÉRENT] ${d.table}.${d.column}`);
    console.log(`      attendu : ${d.expected}  (norm: ${d.expected_norm})`);
    console.log(`      réel    : ${d.actual}  (norm: ${d.actual_norm})`);
    console.log(`      ALTER   : ALTER TABLE \`${d.table}\` MODIFY COLUMN ${d.expected};`);
    console.log('      NOTE    : ne pas appliquer sans analyse (données / CHECK / moteur).');
  }
}
console.log('');
console.log('Aucun ALTER n’a été appliqué.');
process.exit(2);
