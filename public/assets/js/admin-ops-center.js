(() => {
  const root = document.getElementById('ops-actions-list');
  const roleFilter = document.getElementById('ops-role-filter');
  const statusFilter = document.getElementById('ops-status-filter');
  const emptyState = document.getElementById('ops-actions-empty');

  if (!root || !roleFilter || !statusFilter) {
    return;
  }

  const applyFilters = () => {
    const role = roleFilter.value;
    const status = statusFilter.value;

    let visibleCount = 0;
    root.querySelectorAll('[data-role][data-status]').forEach((card) => {
      const roleOk = role === 'all' || card.getAttribute('data-role') === role;
      const statusOk = status === 'all' || card.getAttribute('data-status') === status;
      const isVisible = roleOk && statusOk;
      card.classList.toggle('hidden', !isVisible);
      if (isVisible) {
        visibleCount += 1;
      }
    });

    if (emptyState) {
      emptyState.classList.toggle('hidden', visibleCount > 0);
    }
  };

  roleFilter.addEventListener('change', applyFilters);
  statusFilter.addEventListener('change', applyFilters);

  document.querySelectorAll('.ops-template-copy').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = btn.getAttribute('data-template') || '';
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        const original = btn.textContent;
        btn.textContent = 'Copié';
        setTimeout(() => {
          btn.textContent = original;
        }, 1200);
      } catch (err) {
        console.warn('clipboard_unavailable', err);
      }
    });
  });

  applyFilters();
})();
