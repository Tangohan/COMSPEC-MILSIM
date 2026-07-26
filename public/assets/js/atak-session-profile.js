/* COMSPEC ATAK — Hub de connexion (préambule → profil → liaison) */
window.ATAKSessionProfile = (function () {
  'use strict';

  var STORAGE_PREFIX = 'atak_session_profile_v1_';
  var WELCOME_PREFIX = 'atak_hub_welcome_v1_';
  var GUEST_SEEN_KEY = 'atak_hub_guest_v1';

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
      hint: 'Triage médical et choix de secours.'
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
  var hubMode = 'onboarding'; // onboarding | resume | edit
  var hubStep = 'welcome';
  var linkBusy = false;

  function userKey() {
    var u = window.ATAK_USER || {};
    var tid = u.tenantId != null ? String(u.tenantId) : '0';
    var uid = u.id != null ? String(u.id) : 'anon';
    return STORAGE_PREFIX + tid + '_' + uid;
  }

  function welcomeKey() {
    var u = window.ATAK_USER || {};
    var tid = u.tenantId != null ? String(u.tenantId) : '0';
    var uid = u.id != null ? String(u.id) : 'anon';
    return WELCOME_PREFIX + tid + '_' + uid;
  }

  function isLoggedIn() {
    return !!(window.ATAK_CAPS && window.ATAK_CAPS.loggedIn && window.ATAK_USER && window.ATAK_USER.id);
  }

  function welcomeSeen() {
    try {
      return sessionStorage.getItem(welcomeKey()) === '1';
    } catch (e) {
      return false;
    }
  }

  function markWelcomeSeen() {
    try {
      sessionStorage.setItem(welcomeKey(), '1');
    } catch (e) {}
  }

  function guestSeen() {
    try {
      return sessionStorage.getItem(GUEST_SEEN_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function markGuestSeen() {
    try {
      sessionStorage.setItem(GUEST_SEEN_KEY, '1');
    } catch (e) {}
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

  /** Suggestions depuis le dossier effectifs / compte (injectées côté serveur). */
  function detectSuggestions() {
    var hints = window.ATAK_PROFILE_HINTS || {};
    var role = hints.suggestedRole || 'operator';
    if (!ROLES[role]) role = 'operator';
    var specs = Array.isArray(hints.suggestedSpecialties)
      ? hints.suggestedSpecialties.filter(function (s) { return !!SPECIALTIES[s]; })
      : [];
    return {
      role: role,
      specialties: specs,
      fromPersonnel: !!hints.hasSuggestionBasis
    };
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
      // Médical toujours visible : alertes / MEDEVAC utiles à tout le TOC ;
      // le triage reste réservé à la spécialité Médecin (canTriageMedicalUi).
      case 'medical':
        return true;
      case 'jtac':
        return hasSpecialty('jtac');
      case 'radio':
        return hasSpecialty('radio');
      case 'orders':
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
    if (state && hasSpecialty('medic')) return true;
    return !!(window.ATAK_CAPS && window.ATAK_CAPS.canTriageMedical);
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

  function syncCardStates() {
    document.querySelectorAll('[data-role-card]').forEach(function (card) {
      var inp = card.querySelector('input[type="radio"]');
      card.classList.toggle('is-selected', !!(inp && inp.checked));
    });
    document.querySelectorAll('[data-spec-card]').forEach(function (card) {
      var inp = card.querySelector('input[type="checkbox"]');
      card.classList.toggle('is-selected', !!(inp && inp.checked));
    });
  }

  function fillIdentity() {
    var u = window.ATAK_USER || {};
    var nameEl = document.getElementById('atak-session-hub-name');
    var metaEl = document.getElementById('atak-session-hub-meta');
    var avatarEl = document.querySelector('#atak-session-profile-overlay .atak-session-hub__avatar');
    var name = String(u.displayName || u.display_name || '').trim();
    var callsign = String(u.callsign || u.arma_callsign || u.armaCallsign || '').trim();
    var email = String(u.email || '').trim();
    if (nameEl) nameEl.textContent = name || 'Opérateur';
    if (metaEl) {
      if (callsign) metaEl.textContent = 'Indicatif ' + callsign;
      else if (email) metaEl.textContent = email;
      else metaEl.textContent = 'Compte Athena';
    }
    if (avatarEl) {
      var seed = name || callsign || 'A';
      avatarEl.textContent = seed.charAt(0).toUpperCase();
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
    syncCardStates();
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
      if (parts.length && (suggested.fromPersonnel || suggested.role !== 'operator' || suggested.specialties.length)) {
        var basis = suggested.fromPersonnel
          ? ' d’après votre affectation et vos rôles métier'
          : '';
        hintEl.textContent =
          parts.join(' — ') + basis + '. Vous pouvez tout modifier avant de continuer.';
      } else {
        hintEl.textContent =
          'Sélectionnez votre rôle. Les spécialités débloquent des outils (médecin, radio, JTAC).';
      }
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

  function setBodyLocked(locked) {
    document.body.classList.toggle('atak-session-profile-locked', !!locked);
  }

  function dismissHalo() {
    var halo = document.getElementById('halo-loader');
    if (halo) {
      halo.classList.add('is-done');
      halo.setAttribute('aria-busy', 'false');
    }
  }

  function updateProgress(step) {
    var progress = document.getElementById('atak-session-hub-progress');
    if (!progress) return;
    var hide = hubMode === 'edit' || hubMode === 'resume';
    progress.hidden = hide;
    progress.setAttribute('aria-hidden', hide ? 'true' : 'false');
    if (hide) return;
    var order = ['welcome', 'profile', 'link'];
    var idx = order.indexOf(step);
    progress.querySelectorAll('[data-step-dot]').forEach(function (li) {
      var id = li.getAttribute('data-step-dot');
      var i = order.indexOf(id);
      li.classList.toggle('is-active', i === idx);
      li.classList.toggle('is-done', i >= 0 && i < idx);
    });
  }

  function setStep(step) {
    hubStep = step;
    var overlay = document.getElementById('atak-session-profile-overlay');
    if (!overlay) return;
    overlay.setAttribute('data-step', step);
    overlay.querySelectorAll('[data-hub-step]').forEach(function (sec) {
      var match = sec.getAttribute('data-hub-step') === step;
      sec.hidden = !match;
      sec.classList.toggle('is-active', match);
    });
    updateProgress(step);

    var profileBack = document.getElementById('atak-hub-profile-back');
    if (profileBack) {
      profileBack.hidden = hubMode !== 'onboarding';
    }

    var submit = document.getElementById('atak-session-profile-submit');
    if (submit) {
      submit.textContent = hubMode === 'edit' ? 'Enregistrer' : 'Continuer';
    }

    var focusSel =
      step === 'welcome'
        ? '#atak-hub-welcome-next'
        : step === 'profile'
          ? 'input[name="atak-session-role"]:checked'
          : '#atak-hub-enter';
    var focusEl = overlay.querySelector(focusSel);
    if (focusEl && typeof focusEl.focus === 'function') {
      try {
        focusEl.focus({ preventScroll: true });
      } catch (e) {
        focusEl.focus();
      }
    }
  }

  function configureWelcomeCopy() {
    var title = document.getElementById('atak-session-profile-title');
    var kicker = document.getElementById('atak-session-hub-kicker');
    var lead = document.getElementById('atak-session-hub-lead');
    var nextBtn = document.getElementById('atak-hub-welcome-next');
    if (hubMode === 'resume') {
      if (kicker) kicker.textContent = 'Session';
      if (title) title.textContent = 'Reprise ATAK';
      if (lead) {
        lead.textContent =
          'Votre profil de session est déjà enregistré sur cet appareil. Entrez pour reprendre la carte, ou modifiez le profil plus tard depuis la pastille Profil.';
      }
      if (nextBtn) nextBtn.textContent = 'Entrer dans la session';
    } else {
      if (kicker) kicker.textContent = 'Préambule';
      if (title) title.textContent = 'Connexion ATAK';
      if (lead) {
        lead.textContent =
          'Préparez votre entrée sur la carte tactique : rôle et spécialités pour cette session, puis liaison optionnelle avec Arma pour synchroniser le théâtre.';
      }
      if (nextBtn) nextBtn.textContent = 'Continuer';
    }
  }

  function showOverlay(mode) {
    var overlay = document.getElementById('atak-session-profile-overlay');
    if (!overlay) return;
    hubMode = mode || 'onboarding';
    var suggested = state || detectSuggestions();
    fillIdentity();
    fillForm(suggested);
    configureWelcomeCopy();
    overlay.hidden = false;
    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.toggle('atak-session-hub--edit', hubMode === 'edit');
    overlay.classList.toggle('atak-session-hub--resume', hubMode === 'resume');
    setBodyLocked(true);
    dismissHalo();

    if (hubMode === 'edit') {
      setStep('profile');
    } else if (hubMode === 'resume') {
      setStep('welcome');
    } else {
      setStep('welcome');
    }
  }

  function hideOverlay() {
    var overlay = document.getElementById('atak-session-profile-overlay');
    if (overlay) {
      overlay.hidden = true;
      overlay.setAttribute('aria-hidden', 'true');
      overlay.classList.remove('atak-session-hub--edit', 'atak-session-hub--resume');
    }
    var guest = document.getElementById('atak-session-guest-hub');
    if (guest) {
      guest.hidden = true;
      guest.setAttribute('aria-hidden', 'true');
    }
    setBodyLocked(false);
  }

  function showGuestHub() {
    var guest = document.getElementById('atak-session-guest-hub');
    if (!guest) {
      fireReady();
      return;
    }
    guest.hidden = false;
    guest.setAttribute('aria-hidden', 'false');
    setBodyLocked(true);
    dismissHalo();
    var btn = document.getElementById('atak-guest-continue');
    if (btn && typeof btn.focus === 'function') {
      try {
        btn.focus({ preventScroll: true });
      } catch (e) {
        btn.focus();
      }
    }
  }

  function fireReady() {
    if (readyFired) return;
    readyFired = true;
    readyCallbacks.forEach(function (cb) {
      try {
        cb(state);
      } catch (e) {}
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

  function enterSession() {
    markWelcomeSeen();
    hideOverlay();
    fireReady();
  }

  function confirmProfileFromForm() {
    save(readForm());
    if (hubMode === 'edit') {
      hideOverlay();
      fireReady();
      return;
    }
    setStep('link');
  }

  function gameLinkUrl() {
    var overlay = document.getElementById('atak-session-profile-overlay');
    var fromData = overlay && overlay.getAttribute('data-game-link-url');
    if (fromData) return fromData;
    return '/atak/game-link';
  }

  function generateHubGameLink() {
    var btn = document.getElementById('atak-hub-game-link-btn');
    var resultEl = document.getElementById('atak-hub-game-link-result');
    var codeEl = document.getElementById('atak-hub-game-link-code');
    var metaEl = document.getElementById('atak-hub-game-link-meta');
    var errEl = document.getElementById('atak-hub-game-link-error');
    if (linkBusy || !btn) return;
    if (errEl) {
      errEl.hidden = true;
      errEl.textContent = '';
    }
    linkBusy = true;
    btn.disabled = true;
    btn.textContent = 'Génération…';

    function unlock(label, cooldownMs) {
      var wait = Math.max(0, cooldownMs || 0);
      setTimeout(function () {
        linkBusy = false;
        btn.disabled = false;
        btn.textContent = label || 'Générer un code';
      }, wait);
    }

    fetch(gameLinkUrl(), {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) {
        return r.text().then(function (raw) {
          var j = null;
          try {
            j = raw ? JSON.parse(raw) : null;
          } catch (e) {
            j = null;
          }
          return { ok: r.ok, status: r.status, body: j };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.body || !res.body.code) {
          var msg =
            res.body && res.body.message
              ? res.body.message
              : res.status === 404
                ? 'Service de liaison introuvable. Rechargez la page après mise à jour du portail.'
                : 'Impossible de générer le code.';
          if (errEl) {
            errEl.textContent = msg;
            errEl.hidden = false;
          }
          unlock('Générer un code', res.status === 503 ? 4000 : 800);
          return;
        }
        unlock('Générer un nouveau code', 0);
        if (codeEl) codeEl.textContent = res.body.code;
        if (metaEl) {
          metaEl.textContent =
            res.body.hint ||
            'Dans Arma : touche K → Compte Athena, puis saisissez ce code.';
        }
        if (resultEl) resultEl.hidden = false;
      })
      .catch(function () {
        unlock('Générer un code', 1500);
        if (errEl) {
          errEl.textContent = 'Réseau indisponible. Réessayez dans un instant.';
          errEl.hidden = false;
        }
      });
  }

  function bindUi() {
    var form = document.getElementById('atak-session-profile-form');
    if (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        confirmProfileFromForm();
      });
      form.addEventListener('change', function () {
        syncCardStates();
      });
    }

    document.querySelectorAll('[data-role-card]').forEach(function (card) {
      card.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        ev.preventDefault();
        var inp = card.querySelector('input[type="radio"]');
        if (inp) {
          inp.checked = true;
          syncCardStates();
        }
      });
    });
    document.querySelectorAll('[data-spec-card]').forEach(function (card) {
      card.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        ev.preventDefault();
        var inp = card.querySelector('input[type="checkbox"]');
        if (inp) {
          inp.checked = !inp.checked;
          syncCardStates();
        }
      });
    });

    var welcomeNext = document.getElementById('atak-hub-welcome-next');
    if (welcomeNext) {
      welcomeNext.addEventListener('click', function () {
        if (hubMode === 'resume') {
          enterSession();
          return;
        }
        setStep('profile');
      });
    }

    var profileBack = document.getElementById('atak-hub-profile-back');
    if (profileBack) {
      profileBack.addEventListener('click', function () {
        setStep('welcome');
      });
    }

    var linkBack = document.getElementById('atak-hub-link-back');
    if (linkBack) {
      linkBack.addEventListener('click', function () {
        setStep('profile');
      });
    }

    var enterBtn = document.getElementById('atak-hub-enter');
    if (enterBtn) {
      enterBtn.addEventListener('click', function () {
        if (!state) save(readForm());
        enterSession();
      });
    }

    var hubLinkBtn = document.getElementById('atak-hub-game-link-btn');
    if (hubLinkBtn) {
      hubLinkBtn.addEventListener('click', generateHubGameLink);
    }

    var hubCopy = document.getElementById('atak-hub-game-link-copy');
    var hubCode = document.getElementById('atak-hub-game-link-code');
    if (hubCopy && hubCode) {
      hubCopy.addEventListener('click', function () {
        var t = hubCode.textContent || '';
        if (!t || t.indexOf('—') === 0) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(t).then(function () {
            hubCopy.textContent = 'Copié';
            setTimeout(function () {
              hubCopy.textContent = 'Copier';
            }, 1500);
          });
        }
      });
    }

    var changeBtn = document.getElementById('atak-session-profile-change');
    if (changeBtn) {
      changeBtn.addEventListener('click', function () {
        showOverlay('edit');
      });
    }
    var changeBtnAccount = document.getElementById('atak-session-profile-change-account');
    if (changeBtnAccount) {
      changeBtnAccount.addEventListener('click', function () {
        showOverlay('edit');
      });
    }

    var resetBtn = document.getElementById('atak-session-profile-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        clearStored();
        fillForm(detectSuggestions());
      });
    }

    var guestContinue = document.getElementById('atak-guest-continue');
    if (guestContinue) {
      guestContinue.addEventListener('click', function () {
        markGuestSeen();
        hideOverlay();
        fireReady();
      });
    }
  }

  function init() {
    bindUi();
    if (!isLoggedIn()) {
      state = null;
      window.ATAK_SESSION_PROFILE = null;
      applyGating();
      if (guestSeen()) {
        fireReady();
        return;
      }
      showGuestHub();
      return;
    }

    var stored = loadStored();
    if (stored) {
      state = stored;
      window.ATAK_SESSION_PROFILE = state;
      applyGating();
      if (welcomeSeen()) {
        hideOverlay();
        fireReady();
        return;
      }
      showOverlay('resume');
      return;
    }

    showOverlay('onboarding');
  }

  return {
    init: init,
    onReady: onReady,
    get: function () {
      return state;
    },
    save: save,
    clear: clearStored,
    showEditor: function () {
      showOverlay('edit');
    },
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
