// Academy — droits d'accès et public d'un cours : filtre des longues listes, choix du public, confirmations.
// Tout reste utilisable sans script : les formulaires sont envoyés et revérifiés par le serveur.
(function () {
  'use strict';
  const normalise = text => text.toLocaleLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

  document.querySelectorAll('[data-access-filter]').forEach(input => {
    const list = input.closest('fieldset')?.querySelector('[data-access-list]');
    if (!list) return;
    input.addEventListener('input', () => {
      const query = normalise(input.value.trim());
      list.querySelectorAll('label').forEach(label => {
        label.hidden = query !== '' && !normalise(label.textContent || '').includes(query);
      });
    });
  });

  document.querySelectorAll('[data-access-form]').forEach(form => {
    const picker = form.querySelector('[data-audience-picker]');
    const choices = form.querySelectorAll('[data-audience-choice]');
    if (!picker || choices.length === 0) return;
    const sync = () => {
      const restricted = form.querySelector('[data-audience-choice]:checked')?.value === 'restricted';
      picker.hidden = !restricted;
    };
    choices.forEach(choice => choice.addEventListener('change', sync));
    sync();
  });

  document.querySelectorAll('.acc [data-confirm]').forEach(button => button.addEventListener('click', event => {
    if (!window.confirm(button.dataset.confirm)) event.preventDefault();
  }));
})();
