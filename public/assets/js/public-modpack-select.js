(() => {
    // Galerie : la vignette choisie remplace l'image principale et sa légende.
    const gallery = document.querySelector('[data-gallery]');
    if (gallery) {
        const image = gallery.querySelector('[data-gallery-image]');
        const caption = gallery.querySelector('[data-gallery-caption]');
        const thumbs = gallery.querySelectorAll('.mp-thumb');
        thumbs.forEach((thumb) => thumb.addEventListener('click', () => {
            image.src = thumb.dataset.src;
            caption.textContent = thumb.dataset.caption;
            thumbs.forEach((other) => {
                other.classList.toggle('is-on', other === thumb);
                other.setAttribute('aria-pressed', other === thumb ? 'true' : 'false');
            });
        }));
    }

    // Téléchargement groupé : cases à cocher, tout / aucun, compteur et poids de la sélection.
    const form = document.querySelector('[data-select]');
    if (!form) return;
    const boxes = [...form.querySelectorAll('input[name="mods[]"]')];
    const status = form.querySelector('[data-select-status]');
    const submit = form.querySelector('[data-select-submit]');
    const all = form.querySelector('[data-select-all]');
    const none = form.querySelector('[data-select-none]');
    if (boxes.length === 0) {
        if (submit) submit.disabled = true;
        return;
    }
    const hint = status.textContent;
    const size = (bytes) => {
        const unit = bytes >= 1073741824 ? [1073741824, form.dataset.gib, 2] : [1048576, form.dataset.mib, 1];
        return (bytes / unit[0]).toFixed(unit[2]).replace('.', form.dataset.decimal) + ' ' + unit[1];
    };
    const refresh = () => {
        const checked = boxes.filter((box) => box.checked);
        const bytes = checked.reduce((sum, box) => sum + Number(box.dataset.bytes || 0), 0);
        status.textContent = checked.length === 0
            ? hint
            : form.dataset.countLabel.replace('{count}', String(checked.length)).replace('{size}', size(bytes));
        if (!submit.dataset.locked) submit.disabled = checked.length === 0;
        boxes.forEach((box) => box.closest('.mp-item').classList.toggle('is-on', box.checked));
    };
    if (submit.disabled) submit.dataset.locked = '1';
    if (all) all.hidden = false;
    if (none) none.hidden = false;
    if (all) all.addEventListener('click', () => { boxes.forEach((box) => { box.checked = true; }); refresh(); });
    if (none) none.addEventListener('click', () => { boxes.forEach((box) => { box.checked = false; }); refresh(); });
    boxes.forEach((box) => box.addEventListener('change', refresh));
    refresh();
})();
