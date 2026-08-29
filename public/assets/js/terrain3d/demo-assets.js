/**
 * Génère des assets de démo procéduraux (texture carte + heightmap).
 * Utilisable sans fichiers PNG externes.
 */

/** Texture cartographique tactique (fond satellite stylisé). */
export function createDemoMapTexture(size) {
  size = size || 512;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');

  /* Base terre / végétation. */
  const grad = ctx.createLinearGradient(0, 0, size, size);
  grad.addColorStop(0, '#2a3d2e');
  grad.addColorStop(0.35, '#3d5238');
  grad.addColorStop(0.65, '#4a5f42');
  grad.addColorStop(1, '#2f4230');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, size, size);

  /* Routes et zones urbaines. */
  ctx.strokeStyle = 'rgba(200, 190, 160, 0.35)';
  ctx.lineWidth = 2;
  for (let i = 0; i < 6; i += 1) {
    ctx.beginPath();
    ctx.moveTo(Math.random() * size, Math.random() * size);
    ctx.lineTo(Math.random() * size, Math.random() * size);
    ctx.stroke();
  }

  ctx.fillStyle = 'rgba(120, 130, 140, 0.25)';
  ctx.fillRect(size * 0.62, size * 0.28, size * 0.18, size * 0.14);
  ctx.fillRect(size * 0.22, size * 0.58, size * 0.12, size * 0.1);

  /* Grille MGRS légère. */
  ctx.strokeStyle = 'rgba(180, 200, 210, 0.08)';
  ctx.lineWidth = 1;
  const step = size / 16;
  for (let x = 0; x <= size; x += step) {
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x, size);
    ctx.stroke();
  }
  for (let y = 0; y <= size; y += step) {
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(size, y);
    ctx.stroke();
  }

  /* Bruit subtil. */
  const imgData = ctx.getImageData(0, 0, size, size);
  for (let i = 0; i < imgData.data.length; i += 4) {
    const n = (Math.random() - 0.5) * 8;
    imgData.data[i] = Math.max(0, Math.min(255, imgData.data[i] + n));
    imgData.data[i + 1] = Math.max(0, Math.min(255, imgData.data[i + 1] + n));
    imgData.data[i + 2] = Math.max(0, Math.min(255, imgData.data[i + 2] + n));
  }
  ctx.putImageData(imgData, 0, 0);

  return canvas.toDataURL('image/png');
}

/** Heightmap procédurale — collines, vallées, crête centrale. */
export function createDemoHeightmap(size) {
  size = size || 256;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  const img = ctx.createImageData(size, size);

  for (let y = 0; y < size; y += 1) {
    for (let x = 0; x < size; x += 1) {
      const nx = x / size;
      const ny = y / size;
      let h = 0.35;
      h += 0.22 * Math.sin(nx * Math.PI * 3.2) * Math.cos(ny * Math.PI * 2.8);
      h += 0.15 * Math.sin((nx + ny) * Math.PI * 4);
      h += 0.28 * Math.exp(-((nx - 0.55) ** 2 + (ny - 0.42) ** 2) / 0.018);
      h += 0.18 * Math.exp(-((nx - 0.28) ** 2 + (ny - 0.68) ** 2) / 0.035);
      h -= 0.12 * Math.exp(-((nx - 0.72) ** 2 + (ny - 0.75) ** 2) / 0.02);
      h = Math.max(0, Math.min(1, h));
      const v = Math.round(h * 255);
      const i = (y * size + x) * 4;
      img.data[i] = v;
      img.data[i + 1] = v;
      img.data[i + 2] = v;
      img.data[i + 3] = 255;
    }
  }

  ctx.putImageData(img, 0, 0);
  return canvas.toDataURL('image/png');
}

/** Marqueurs de démonstration. */
export function createDemoMarkers(worldSize) {
  worldSize = worldSize || 1024;
  return [
    { id: 'alpha', x: worldSize * 0.48, y: worldSize * 0.52, label: 'ALPHA-1', type: 'unit', color: '#35d6a1' },
    { id: 'bravo', x: worldSize * 0.35, y: worldSize * 0.62, label: 'BRAVO-2', type: 'unit', color: '#35d6a1' },
    { id: 'obj', x: worldSize * 0.58, y: worldSize * 0.38, label: 'OBJ IRON', type: 'objective', color: '#68a5d8' },
    { id: 'contact', x: worldSize * 0.72, y: worldSize * 0.55, label: 'CONTACT', type: 'hostile', color: '#dc5d5d' },
  ];
}
