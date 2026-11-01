// Lecteur de diapositives de la salle de cours : PDF importé depuis Google Slides, rendu page par page avec pdf.js
// (déjà livré dans assets/vendor). Navigation, barre de progression, vignettes et plein écran.
const decks = document.querySelectorAll('[data-academy-deck]');

const fit = (page, box) => {
  const base = page.getViewport({ scale: 1 });
  return Math.min(box.clientWidth / base.width, box.clientHeight / base.height) * (window.devicePixelRatio || 1);
};

async function render(page, canvas, scale) {
  const viewport = page.getViewport({ scale });
  canvas.width = Math.floor(viewport.width);
  canvas.height = Math.floor(viewport.height);
  await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
}

async function mount(deck, lib) {
  const stage = deck.querySelector('.sv');
  const canvas = deck.querySelector('[data-deck-canvas]');
  const status = deck.querySelector('[data-deck-status]');
  const count = deck.querySelector('[data-deck-count]');
  const bar = deck.querySelector('[data-deck-bar]');
  const thumbs = deck.querySelector('[data-deck-thumbs]');
  const pdf = await lib.getDocument(deck.dataset.src).promise;
  const total = pdf.numPages;
  let current = 1;
  let seen = 1;
  let busy = Promise.resolve();
  status.hidden = true;
  canvas.style.width = '100%';
  canvas.style.height = '100%';
  canvas.style.objectFit = 'contain';
  const marks = Array.from({ length: total }, (_, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-label', String(index + 1));
    button.addEventListener('click', () => show(index + 1));
    bar.append(button);
    return button;
  });
  const minis = Array.from({ length: total }, (_, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'th';
    button.setAttribute('aria-label', String(index + 1));
    button.innerHTML = `<span>${String(index + 1).padStart(2, '0')}</span>`;
    button.addEventListener('click', () => show(index + 1));
    thumbs.append(button);
    return button;
  });
  // Vignettes rendues à l'approche, pour ne pas charger toute la présentation d'un coup.
  const observer = new IntersectionObserver(entries => entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    observer.unobserve(entry.target);
    const index = minis.indexOf(entry.target);
    pdf.getPage(index + 1).then(page => {
      const mini = document.createElement('canvas');
      entry.target.prepend(mini);
      return render(page, mini, fit(page, entry.target));
    }).catch(() => {});
  }), { root: thumbs, rootMargin: '200px' });
  minis.forEach(mini => observer.observe(mini));
  const show = number => {
    current = Math.max(1, Math.min(total, number));
    seen = Math.max(seen, current);
    count.textContent = `${current} ${deck.dataset.labelOf || '/'} ${total}`;
    marks.forEach((mark, index) => { mark.className = index + 1 === current ? 'on' : index < seen ? 'seen' : ''; });
    minis.forEach((mini, index) => mini.classList.toggle('on', index + 1 === current));
    const mini = minis[current - 1];
    thumbs.scrollTo({ left: mini.offsetLeft - thumbs.clientWidth / 2 + mini.clientWidth / 2, behavior: 'smooth' });
    deck.querySelectorAll('[data-deck-prev]').forEach(button => { button.disabled = current === 1; });
    deck.querySelectorAll('[data-deck-next]').forEach(button => { button.disabled = current === total; });
    busy = busy.then(() => pdf.getPage(current)).then(page => render(page, canvas, fit(page, stage))).catch(() => {});
  };
  deck.querySelectorAll('[data-deck-prev]').forEach(button => button.addEventListener('click', () => show(current - 1)));
  deck.querySelectorAll('[data-deck-next]').forEach(button => button.addEventListener('click', () => show(current + 1)));
  deck.querySelector('[data-deck-full]')?.addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen(); else deck.requestFullscreen?.();
  });
  deck.tabIndex = 0;
  deck.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight' || event.key === 'PageDown') { event.preventDefault(); show(current + 1); }
    if (event.key === 'ArrowLeft' || event.key === 'PageUp') { event.preventDefault(); show(current - 1); }
  });
  document.addEventListener('fullscreenchange', () => show(current));
  show(1);
}

if (decks.length) {
  const first = decks[0];
  import(first.dataset.lib).then(lib => {
    lib.GlobalWorkerOptions.workerSrc = first.dataset.worker;
    decks.forEach(deck => mount(deck, lib).catch(() => {
      // Le lien « Ouvrir les diapositives » reste affiché : le PDF s'ouvre dans le lecteur du navigateur.
      const status = deck.querySelector('[data-deck-status]');
      if (status) status.hidden = false;
    }));
  }).catch(() => {});
}
