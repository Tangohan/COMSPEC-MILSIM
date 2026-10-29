'use strict';
const search = document.querySelector('[data-course-search]');
if (search) search.addEventListener('input', () => {
  const query = search.value.trim().toLocaleLowerCase();
  let visible = 0;
  document.querySelectorAll('[data-course-row]').forEach(card => {
    card.hidden = !card.textContent.toLocaleLowerCase().includes(query);
    if (!card.hidden) visible++;
  });
  const empty = document.querySelector('[data-search-empty]');
  if (empty) empty.hidden = visible > 0;
});
document.querySelectorAll('[data-countdown]').forEach(clock => {
  const initial = Math.max(0, Number(clock.dataset.countdown) - Number(clock.dataset.serverNow));
  const started = performance.now();
  const update = () => {
    const remaining = Math.max(0, initial - Math.floor((performance.now() - started) / 1000));
    clock.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    if (remaining === 0) clearInterval(interval);
  };
  const interval = setInterval(update, 1000);
  update();
});
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => {
  const notes = document.querySelector('[name=notes]');
  const printed = document.querySelector('[data-print-notes]');
  if (notes && printed) printed.textContent = notes.value;
  window.print();
}));
document.querySelectorAll('[data-course-editor]').forEach(form => {
  const chapters = form.querySelector('[data-chapters]');
  const chapterTemplate = chapters.querySelector('[data-chapter]').cloneNode(true);
  const fields = form.querySelector('[data-editor-fields]');
  fields.hidden = false;
  const advanced = form.querySelector('[data-advanced]');
  const json = form.querySelector('[name=content]');
  const bilingual = (root, selector) => Object.fromEntries(['fr', 'en'].map(language => [language, root.querySelector(`${selector}[data-language="${language}"]`).value]));
  const serialize = () => {
    const content = {
      title: bilingual(form, '[data-course-field="title"]'),
      summary: bilingual(form, '[data-course-field="summary"]'),
      chapters: [...chapters.querySelectorAll('[data-chapter]')].map(chapter => {
        const content = Object.fromEntries(['title','theory','demonstration','question','feedback'].map(key => [key, bilingual(chapter, `[data-chapter-field="${key}"]`)]));
        content.options = [0, 1, 2].map(index => bilingual(chapter, `[data-option="${index}"]`));
        content.answer = Number(chapter.querySelector('[data-answer]').value);
        content.resources = [...chapter.querySelectorAll('[data-resources] > .editor-resource')].map(resource => ({
          type: resource.querySelector('[data-resource-type]').value,
          title: bilingual(resource, '[data-resource-field="title"]'),
          url: bilingual(resource, '[data-resource-field="url"]')
        }));
        return content;
      })
    };
    json.value = JSON.stringify(content, null, 2);
  };
  const renumber = () => chapters.querySelectorAll('[data-chapter-number]').forEach((number, index) => number.textContent = index + 1);
  form.querySelector('[data-add-chapter]').addEventListener('click', () => {
    if (chapters.children.length >= 20) return;
    const chapter = chapterTemplate.cloneNode(true);
    chapter.querySelectorAll('input,textarea').forEach(input => input.value = '');
    chapters.append(chapter);
    renumber();
    chapter.querySelector('textarea').focus();
  });
  form.addEventListener('click', event => {
    const remove = event.target.closest('[data-remove-chapter]');
    if (remove && chapters.children.length > 1) { remove.closest('[data-chapter]').remove(); renumber(); }
    const addResource = event.target.closest('[data-add-resource]');
    if (addResource) {
      const target = addResource.closest('[data-chapter]').querySelector('[data-resources]');
      if (target.children.length < 20) target.append(form.querySelector('[data-resource-template]').content.cloneNode(true));
    }
    const removeResource = event.target.closest('[data-remove-resource]');
    if (removeResource) removeResource.closest('.editor-resource').remove();
  });
  advanced.addEventListener('toggle', () => {
    if (advanced.open) serialize();
    fields.hidden = advanced.open;
    fields.querySelectorAll('input,textarea,select').forEach(input => input.disabled = advanced.open);
  });
  form.addEventListener('submit', () => { if (!advanced.open) serialize(); });
});
// This stores acknowledgement only. Identity, community and entitlement stay server-side.
document.querySelectorAll('[data-academy-disclaimer]').forEach(dialog => {
  const key = `athena:academy:disclaimer:${dialog.dataset.disclaimerVersion}`;
  const open = () => {
    if (dialog.open) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };
  let acknowledged = false;
  try { acknowledged = localStorage.getItem(key) === 'accepted'; } catch { /* Storage may be unavailable in private mode. */ }
  if (!acknowledged) open();
  dialog.querySelector('[data-disclaimer-form]').addEventListener('submit', event => {
    event.preventDefault();
    if (!event.currentTarget.reportValidity()) return;
    try { localStorage.setItem(key, 'accepted'); } catch { /* Keep the page usable when persistence is denied. */ }
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  });
  document.querySelectorAll('[data-open-disclaimer]').forEach(button => button.addEventListener('click', open));
});
