/* COMSPEC ATAK — Profil de session (rôle + spécialités), localStorage */
window.ATAKSessionProfile = (function () {
  'use strict';

  var STORAGE_PREFIX = 'atak_session_profile_v1_';
  var ROLES = {
    commander: {
      id: 'commander',
      label: 'Commandant d’unité',
      hint: 'Pilote les ordres et la manœuvre d’ensemble.'
    },
    deputy: {
      id: 'deputy',
      label: 'Commandant adjoint',
      hint: 'Appuie le commandant ; mêmes outils de conduite.'
    },
    operator: {
      id: 'operator',
      label: 'Exécutant',
      hint: 'Suit les ordres reçus et remonte la situation.'
    }
  };
  var SPECIALTIES = {
    medic: {
      id: 'medic',
      label: 'Médecin',
      hint: 'Assistances, triage et choix médicaux.'
    },
    radio: {
      id: 'radio',
      label: 'Transmetteur',
      hint: 'Radio proximité et transmissions renforcées.'
    },
    jtac: {
      id: 'jtac',
      label: 'JTAC',
      hint: 'Appui aérien et 9-line.'
    }
  };

  var state = null;
  var readyCallbacks = [];
  var readyFired = false;

  function userKey() {
    var u = window.ATAK_USER || {};
    var tid = u.tenantId != null ? String(u.tenantId) : '0';
    var uid = u.id != null ? String(u.id) : 'anon';
    return STORAGE_PREFIX + tid + '_' + uid;
  }

  function isLoggedIn() {
    return !!(window.ATAK_CAPS && window.ATAK_CAPS.loggedIn && window.ATAK_USER && window.ATAK_USER.id);
  }

  function normalize(raw) {
    var role = (raw && raw.role) || 'operator';
    if (!ROLES[role]) role = 'operator';
    var specs = [];
    if (raw && Array.isArray(raw.specialties)) {
      raw.specialties.forEach(function (s) {
        if (SPECIALTIES[s] && specs.indexOf(s) === -1) specs.push(s);
      });
    }
    return {
      role: role,
      specialties: specs,
      updatedAt: (raw && raw.updatedAt) || new Date().toISOString()
    };
  }

  function loadStored() {
    try {
      var raw = localStorage.getItem(userKey());
      if (!raw) return null;
      return normalize(JSON.parse(raw));
    } catch (e) {
      return null;
    }
  }

  function save(profile) {
    state = normalize(profile);
    state.updatedAt = new Date().toISOString();
    try {
      localStorage.setItem(userKey(), JSON.stringify(state));
    } catch (e) {}
    window.ATAK_SESSION_PROFILE = state;
    applyGating();
    document.dispatchEvent(new CustomEvent('atak:session-profile', { detail: state }));
    return state;
  }

  function clearStored() {
    try {
      localStorage.removeItem(userKey());
    } catch (e) {}
    state = null;
    window.ATAK_SESSION_PROFILE = null;
  }

  /** Suggestions depuis le compte Athena (permissions, libellés). */
  function detectSuggestions() {
    var caps = window.ATAK_CAPS || {};
    var hints = window.ATAK_PROFILE_HINTS || {};
    var role = hints.suggestedRole || 'operator';
    if (!ROLES[role]) role = 'operator';
    var specs = Array.isArray(hints.suggestedSpecialties)
      ? hints.suggestedSpecialties.filter(function (s) { return !!SPECIALTIES[s]; })
      : [];
    if (caps.canTriageMedical && specs.indexOf('medic') === -1) {
      specs.push('medic');
    }
    return { role: role, specialties: specs };
  }

  function hasSpecialty(id) {
    return !!(state && state.specialties && state.specialties.indexOf(id) !== -1);
  }

  function isCommandRole() {
    return !!(state && (state.role === 'commander' || state.role === 'deputy'));
  }

  function canAccessTab(tab) {
    if (!state) return true;
    switch (tab) {
      case 'medical':
        return hasSpecialty('medic');
      case 'jtac':
        return hasSpecialty('jtac');
      case 'radio':
        return hasSpecialty('radio');
      case 'orders':
        // Tout le monde peut recevoir ; l’émission est gérée à part.
        return true;
      case 'cams':
      case 'markers':
      case 'chat':
      case 'pings':
      case 'liaison':
        return true;
      default:
        return true;
    }
  }

  function canIssueOrders() {
    if (!isLoggedIn()) return false;
    if (!state) return !!window.ATAK_CAN_ISSUE_ORDERS;
    return isCommandRole();
  }

  function canTriageMedicalUi() {
    if (!isLoggedIn()) return false;
    var serverOk = !!(window.ATAK_CAPS && window.ATAK_CAPS.canTriageMedical);
    if (!state) return serverOk;
    return serverOk && hasSpecialty('medic');
  }

  function applyGating() {
    var tabs = document.querySelectorAll('.atak-tab[data-tab]');
    var firstVisible = null;
    tabs.forEach(function (btn) {
      var tab = btn.getAttribute('data-tab');
      var ok = !state || canAccessTab(tab);
      btn.hidden = !ok;
      btn.setAttribute('aria-hidden', ok ? 'false' : 'true');
      if (ok && !firstVisible) firstVisible = btn;
      if (!ok && btn.classList.contains('active')) {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
        var panel = document.getElementById('tab-' + tab);
        if (panel) panel.classList.remove('active');
      }
    });

    var active = document.querySelector('.atak-tab.active:not([hidden])');
    if (!active && firstVisible) {
      firstVisible.click();
    }

    document.querySelectorAll('[data-atak-needs-specialty]').forEach(function (el) {
      var need = el.getAttribute('data-atak-needs-specialty');
      el.hidden = !(state && hasSpecialty(need));
    });
    document.querySelectorAll('[data-atak-needs-command]').forEach(function (el) {
      el.hidden = !isCommandRole();
    });

    var orderForm = document.getElementById('atak-orders-issue');
    if (orderForm) {
      orderForm.hidden = !canIssueOrders();
    }

    var badge = document.getElementById('atak-session-profile-badge');
    var chip = document.getElementById('atak-session-profile-change');
    var accountSummary = document.getElementById('atak-session-profile-account-summary');
    if (state) {
      var bits = [ROLES[state.role] ? ROLES[state.role].label : state.role];
      state.specialties.forEach(function (s) {
        if (SPECIALTIES[s]) bits.push(SPECIALTIES[s].label);
      });
      var label = bits.join(' · ');
      if (badge) {
        badge.textContent = label;
        badge.hidden = false;
      }
      if (chip) chip.hidden = false;
      if (accountSummary) accountSummary.textContent = label;
    } else {
      if (badge) badge.hidden = true;
      if (chip) chip.hidden = true;
      if (accountSummary) accountSummary.textContent = 'Non défini';
    }

    window.ATAK_CAPS = window.ATAK_CAPS || {};
    window.ATAK_CAPS.sessionRole = state ? state.role : null;
    window.ATAK_CAPS.sessionSpecialties = state ? state.specialties.slice() : [];
    window.ATAK_CAPS.canIssueOrdersSession = canIssueOrders();
    window.ATAK_CAPS.canTriageMedicalSession = canTriageMedicalUi();
    if (isLoggedIn() && state) {
      window.ATAK_CAN_ISSUE_ORDERS = canIssueOrders();
    }
  }

  function fillForm(suggested) {
    var roleInputs = document.querySelectorAll('input[name="atak-session-role"]');
    roleInputs.forEach(function (inp) {
      inp.checked = inp.value === suggested.role;
    });
    Object.keys(SPECIALTIES).forEach(function (sid) {
      var inp = document.getElementById('atak-spec-' + sid);
      if (inp) inp.checked = suggested.specialties.indexOf(sid) !== -1;
    });
    var hintEl = document.getElementById('atak-session-profile-suggest');
    if (hintEl) {
      var parts = [];
      if (suggested.role && ROLES[suggested.role]) {
        parts.push('Rôle proposé : ' + ROLES[suggested.role].label);
      }
      if (suggested.specialties.length) {
        parts.push(
          'Spécialités proposées : ' +
            suggested.specialties.map(function (s) {
              return SPECIALTIES[s] ? SPECIALTIES[s].label : s;
            }).join(', ')
        );
      }
      hintEl.textContent = parts.length
        ? parts.join(' — ') + '. Vous pouvez tout modifier avant de continuer.'
        : 'Choisissez votre rôle pour cette session. Les spécialités débloquent des outils (médecin, radio, JTAC).';
    }
  }

  function readForm() {
    var role = 'operator';
    var roleInp = document.querySelector('input[name="atak-session-role"]:checked');
    if (roleInp) role = roleInp.value;
    var specialties = [];
    Object.keys(SPECIALTIES).forEach(function (sid) {
      var inp = document.getElementById('atak-spec-' + sid);
      if (inp && inp.checked) specialties.push(sid);
    });
    return { role: role, specialties: specialties };
  }

  function showOverlay(force) {
    var overlay = document.getElementById('atak-session-profile-overlay');
    if (!overlay) return;
    var suggested = state || detectSuggestions();
    fillForm(suggested);
    overlay.hidden = false;
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('atak-session-profile-locked');
    var title = document.getElementById('atak-session-profile-title');
    if (title) {
      title.textContent = force ? 'Modifier le profil de session' : 'Profil de session ATAK';
    }
    // Au-dessus du loader Halo si encore visible
    var halo = document.getElementById('halo-loader');
    if (halo && !force) {
      halo.classList.add('is-done');
      halo.setAttribute('aria-busy', 'false');
    }
  }

  function hideOverlay() {
    var overlay = document.getElementById('atak-session-profile-overlay');
    if (!overlay) return;
    overlay.hidden = true;
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('atak-session-profile-locked');
  }

  function fireReady() {
    if (readyFired) return;
    readyFired = true;
    readyCallbacks.forEach(function (cb) {
      try { cb(state); } catch (e) {}
    });
    readyCallbacks = [];
  }

  function onReady(cb) {
    if (typeof cb !== 'function') return;
    if (readyFired) {
      cb(state);
      return;
    }
    readyCallbacks.push(cb);
  }

  function confirmFromForm() {
    save(readForm());
    hideOverlay();
    fireReady();
  }

  function bindUi() {
    var form = document.getElementById('atak-session-profile-form');
    if (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        confirmFromForm();
      });
    }
    var changeBtn = document.getElementById('atak-session-profile-change');
    if (changeBtn) {
      changeBtn.addEventListener('click', function () {
        showOverlay(true);
      });
    }
    var changeBtnAccount = document.getElementById('atak-session-profile-change-account');
    if (changeBtnAccount) {
      changeBtnAccount.addEventListener('click', function () {
        showOverlay(true);
      });
    }
    var resetBtn = document.getElementById('atak-session-profile-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        clearStored();
        fillForm(detectSuggestions());
      });
    }
  }

  function init() {
    bindUi();
    if (!isLoggedIn()) {
      state = null;
      window.ATAK_SESSION_PROFILE = null;
      applyGating();
      fireReady();
      return;
    }
    var stored = loadStored();
    if (stored) {
      state = stored;
      window.ATAK_SESSION_PROFILE = state;
      applyGating();
      hideOverlay();
      fireReady();
      return;
    }
    // Première visite connectée : demander le profil avant de continuer
    showOverlay(false);
  }

  return {
    init: init,
    onReady: onReady,
    get: function () { return state; },
    save: save,
    showEditor: function () { showOverlay(true); },
    applyGating: applyGating,
    canAccessTab: canAccessTab,
    canIssueOrders: canIssueOrders,
    canTriageMedicalUi: canTriageMedicalUi,
    hasSpecialty: hasSpecialty,
    isCommandRole: isCommandRole,
    ROLES: ROLES,
    SPECIALTIES: SPECIALTIES
  };
})();
