const fs = require('fs');
const files = [
  'public/assets/js/arma-marker-catalog.js',
  'public/assets/js/arma-marker-library-index.js',
];
// Literal mojibake sequences as they appear in the broken UTF-8 files.
const reps = [
  ['\u00C3\u00A9', 'é'], // Ã©
  ['\u00C3\u00A8', 'è'], // Ã¨
  ['\u00C3\u00AA', 'ê'], // Ãª
  ['\u00C3\u00A0', 'à'], // Ã 
  ['\u00C3\u00A7', 'ç'], // Ã§
  ['\u00C3\u00B4', 'ô'], // Ã´
  ['\u00C3\u00BB', 'û'], // Ã»
  ['\u00C3\u00AE', 'î'], // Ã®
  ['\u00C3\u00B6', 'ö'], // Ã¶
  ['\u00C3\u00BC', 'ü'], // Ã¼
  ['\u00C3\u0089', 'É'], // Ã‰
  ['\u00C3\u0080', 'À'], // Ã€
  ['\u00E2\u0080\u0099', "'"], // â€™
  ['\u00E2\u0080\u0094', '—'], // â€”
  ['\u00E2\u0080\u0093', '–'], // â€“
  ['\u00C2\u00A0', ' '], // Â 
  ['\u00C2 ', ' '],
];
for (const f of files) {
  if (!fs.existsSync(f)) {
    console.log('skip', f);
    continue;
  }
  let t = fs.readFileSync(f, 'utf8');
  const before = (t.match(/\u00C3./g) || []).length;
  for (const [a, b] of reps) t = t.split(a).join(b);
  const after = (t.match(/\u00C3./g) || []).length;
  fs.writeFileSync(f, t);
  console.log(f, before, '->', after);
}
