/**
 * Menu contextuel (clic droit) sur les catégories racine du forum — création de sous-catégorie.
 */
(function () {
  const cfg = document.getElementById('forum-context-menu-config');
  if (!cfg || cfg.dataset.enabled !== '1') {
    return;
  }

  const apiUrl = cfg.dataset.apiUrl || '';
  const csrf = cfg.dataset.csrf || '';
  const fullAdmin = cfg.dataset.fullAdmin === '1';
  const adminUrl = cfg.dataset.adminUrl || '';
  const contextTenantId = cfg.dataset.contextTenantId || '';
  const deleteMenu = cfg.dataset.deleteMenu === '1';

  const modal = document.getElementById('forum-subcategory-modal');
  const form = document.getElementById('forum-subcategory-form');
  const errEl = document.getElementById('forum-subcat-error');
  const parentLabel = document.getElementById('forum-subcat-modal-parent');
  const btnCancel = document.getElementById('forum-subcat-cancel');
  const btnSubmit = document.getElementById('forum-subcat-submit');

  if (!modal || !form || !apiUrl || !csrf) {
    return;
  }

  let menuEl = null;
  /** @type {{ id: string, name: string, scope: string } | null} */
  let pendingParent = null;

  function closeMenu() {
    if (menuEl) {
      menuEl.remove();
      menuEl = null;
    }
  }

  function openMenu(clientX, clientY, card) {
    closeMenu();
    pendingParent = {
      id: String(card.dataset.categoryId || ''),
      name: card.dataset.categoryName || '',
      scope: card.dataset.categoryScope || 'general',
    };
    if (!pendingParent.id) {
      return;
    }

    menuEl = document.createElement('div');
    menuEl.className = 'forum-ctx-menu';
    menuEl.setAttribute('role', 'menu');

    const mkBtn = (label, fn) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'forum-ctx-menu__item';
      b.textContent = label;
      b.addEventListener('click', (e) => {
        e.stopPropagation();
        fn();
        closeMenu();
      });
      return b;
    };

    menuEl.appendChild(
      mkBtn('Nouvelle sous-catégorie…', () => {
        openModal();
      })
    );

    if (deleteMenu) {
      menuEl.appendChild(
        mkBtn('Supprimer cette catégorie…', () => {
          const id = pendingParent.id;
          const name = pendingParent.name || '';
          void runDeleteCategory(id, name);
        })
      );
    }

    if (fullAdmin && adminUrl) {
      menuEl.appendChild(
        mkBtn('Configuration forum', () => {
          window.location.href = adminUrl;
        })
      );
    }

    document.body.appendChild(menuEl);

    const pad = 8;
    const mw = menuEl.offsetWidth;
    const mh = menuEl.offsetHeight;
    let x = clientX;
    let y = clientY;
    if (x + mw + pad > window.innerWidth) {
      x = Math.max(pad, window.innerWidth - mw - pad);
    }
    if (y + mh + pad > window.innerHeight) {
      y = Math.max(pad, window.innerHeight - mh - pad);
    }
    menuEl.style.left = x + 'px';
    menuEl.style.top = y + 'px';
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    errEl.classList.add('hidden');
    errEl.textContent = '';
    form.reset();
    pendingParent = null;
  }

  function openModal() {
    if (!pendingParent || !pendingParent.id) {
      return;
    }
    parentLabel.textContent = 'Sous-catégorie de « ' + pendingParent.name + ' »';
    errEl.classList.add('hidden');
    errEl.textContent = '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    const nameInput = document.getElementById('forum-subcat-name');
    if (nameInput) {
      nameInput.focus();
    }
  }

  document.addEventListener(
    'contextmenu',
    function (e) {
      const card = e.target && e.target.closest ? e.target.closest('[data-forum-category-root="1"]') : null;
      if (!card) {
        return;
      }
      e.preventDefault();
      openMenu(e.clientX, e.clientY, card);
    },
    true
  );

  document.addEventListener('click', function (e) {
    if (menuEl && !menuEl.contains(e.target)) {
      closeMenu();
    }
    if (modal && !modal.classList.contains('hidden') && e.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeMenu();
      if (modal && !modal.classList.contains('hidden')) {
        closeModal();
      }
    }
  });

  btnCancel.addEventListener('click', closeModal);

  async function runDeleteCategory(categoryId, categoryName) {
    if (!categoryId) {
      return;
    }
    const label = categoryName ? '« ' + categoryName + ' »' : 'cette catégorie';
    if (
      !confirm(
        'Supprimer ' +
          label +
          ' ? Cette action est définitive. La catégorie doit être vide (aucun sujet, aucune sous-catégorie).'
      )
    ) {
      return;
    }
    const body = new URLSearchParams();
    body.set('_csrf_token', csrf);
    body.set('action', 'delete');
    body.set('id', String(categoryId));
    if (contextTenantId) {
      body.set('context_tenant_id', contextTenantId);
    }
    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          Accept: 'application/json',
        },
        body: body.toString(),
        credentials: 'same-origin',
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        window.alert(data.message || 'La suppression n’a pas pu être effectuée.');
        return;
      }
      window.location.reload();
    } catch (_err) {
      window.alert('Erreur réseau. Réessayez.');
    }
  }

  modal.querySelector('.forum-modal-panel').addEventListener('click', function (e) {
    e.stopPropagation();
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!pendingParent || !pendingParent.id) {
      errEl.textContent = 'Sélection invalide. Rechargez la page.';
      errEl.classList.remove('hidden');
      return;
    }

    const fd = new FormData(form);
    const name = String(fd.get('name') || '').trim();
    const slug = String(fd.get('slug') || '').trim();
    const description = String(fd.get('description') || '').trim();

    if (!name) {
      errEl.textContent = 'Le nom est obligatoire.';
      errEl.classList.remove('hidden');
      return;
    }

    const body = new URLSearchParams();
    body.set('_csrf_token', csrf);
    body.set('action', 'create');
    body.set('parent_id', pendingParent.id);
    body.set('name', name);
    if (slug) {
      body.set('slug', slug);
    }
    if (description) {
      body.set('description', description);
    }
    body.set('scope', pendingParent.scope);
    if (contextTenantId) {
      body.set('context_tenant_id', contextTenantId);
    }

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Création…';

    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          Accept: 'application/json',
        },
        body: body.toString(),
        credentials: 'same-origin',
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        errEl.textContent = data.message || 'Échec de la création (' + res.status + ').';
        errEl.classList.remove('hidden');
        return;
      }
      window.location.reload();
    } catch (err) {
      errEl.textContent = 'Erreur réseau. Réessayez.';
      errEl.classList.remove('hidden');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Créer';
    }
  });
})();
