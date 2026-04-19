document.addEventListener('input', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) {
    return;
  }
  if (target.dataset.lowercase === 'email') {
    target.value = target.value.toLowerCase();
  }
});
