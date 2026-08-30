/**
 * RSVP dynamique (oui / peut-être / non) — feedback immédiat, anti-spam,
 * synchronisation de tous les groupes pour un même événement.
 *
 * Attendu : [data-rsvp-group][data-event-id][data-rsvp-current]
 * Boutons : [data-rsvp-choice="yes|maybe|no"]
 * Label   : [data-rsvp-status-label]
 * Config  : window.__DASH_RSVP__ = { csrf, endpointBase }
 */
(function () {
    'use strict';

    var LABELS = {
        yes: 'Enregistré : vous participez',
        maybe: 'Enregistré : peut-être',
        no: 'Enregistré : absent(e)'
    };
    var SHORT = {
        yes: 'Vous participez',
        maybe: 'Peut-être',
        no: 'Vous ne participez pas',
        '': 'Réponse non renseignée'
    };
    var BADGE = {
        yes: { label: 'Présent', badge: 'is-ok' },
        maybe: { label: 'Peut-être', badge: 'is-watch' },
        no: { label: 'Absent', badge: 'is-rose' },
        '': { label: 'À confirmer', badge: 'is-muted' }
    };

    var pending = Object.create(null);

    function cfg() {
        return window.__DASH_RSVP__ || {};
    }

    function groupsFor(eventId) {
        return document.querySelectorAll('[data-rsvp-group][data-event-id="' + eventId + '"]');
    }

    function applyChoice(eventId, choice, opts) {
        opts = opts || {};
        var label = LABELS[choice] || '';
        var short = SHORT[choice] || SHORT[''];
        var badgeMeta = BADGE[choice] || BADGE[''];

        groupsFor(eventId).forEach(function (g) {
            g.setAttribute('data-rsvp-current', choice);
            g.classList.toggle('dash-rsvp--saved', !!choice);
            g.querySelectorAll('[data-rsvp-choice]').forEach(function (b) {
                var isActive = b.getAttribute('data-rsvp-choice') === choice;
                b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                b.classList.toggle('is-active', isActive);
            });
            var lbl = g.querySelector('[data-rsvp-status-label]');
            if (lbl) {
                lbl.textContent = opts.busy ? 'Enregistrement…' : label;
                lbl.classList.toggle('is-busy', !!opts.busy);
                lbl.classList.toggle('is-error', false);
            }
            var reasonWrap = g.querySelector('[data-rsvp-reason-wrap]');
            if (reasonWrap) {
                reasonWrap.classList.toggle('hidden', choice !== 'no');
                reasonWrap.hidden = choice !== 'no';
            }
        });

        document.querySelectorAll('[data-rsvp-meta-label][data-event-id="' + eventId + '"]').forEach(function (el) {
            el.textContent = short;
        });

        document.querySelectorAll('[data-rsvp-badge][data-event-id="' + eventId + '"]').forEach(function (el) {
            el.textContent = badgeMeta.label;
            el.className = (el.getAttribute('data-rsvp-badge-base') || 'events-sheets__badge') + ' ' + badgeMeta.badge;
        });
    }

    function setBusy(eventId, busy) {
        groupsFor(eventId).forEach(function (g) {
            g.classList.toggle('dash-rsvp--busy', busy);
            g.querySelectorAll('[data-rsvp-choice]').forEach(function (b) {
                b.disabled = busy;
            });
        });
    }

    function setError(eventId, message, revertTo) {
        groupsFor(eventId).forEach(function (g) {
            var lbl = g.querySelector('[data-rsvp-status-label]');
            if (lbl) {
                lbl.textContent = message || 'Échec de l’enregistrement';
                lbl.classList.add('is-error');
                lbl.classList.remove('is-busy');
            }
        });
        if (revertTo !== undefined) {
            applyChoice(eventId, revertTo || '');
        }
    }

    function initGroup(g) {
        var current = g.getAttribute('data-rsvp-current') || '';
        var eventId = g.getAttribute('data-event-id') || '';
        g.querySelectorAll('[data-rsvp-choice]').forEach(function (b) {
            var isActive = b.getAttribute('data-rsvp-choice') === current && current !== '';
            b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            b.classList.toggle('is-active', isActive);
        });
        var lbl = g.querySelector('[data-rsvp-status-label]');
        if (lbl && current && !lbl.textContent.trim()) {
            lbl.textContent = LABELS[current] || '';
        }
        g.classList.toggle('dash-rsvp--saved', !!current);
        var reasonWrap = g.querySelector('[data-rsvp-reason-wrap]');
        if (reasonWrap) {
            reasonWrap.classList.toggle('hidden', current !== 'no');
            reasonWrap.hidden = current !== 'no';
        }
        if (eventId) {
            document.querySelectorAll('[data-rsvp-meta-label][data-event-id="' + eventId + '"]').forEach(function (el) {
                if (!el.textContent.trim() && current) {
                    el.textContent = SHORT[current] || SHORT[''];
                }
            });
        }
    }

    function submit(eventId, choice, group) {
        var conf = cfg();
        var csrf = conf.csrf || '';
        var base = conf.endpointBase || '';
        if (!base || !csrf) {
            setError(eventId, 'Configuration RSVP manquante.');
            return;
        }

        var previous = group.getAttribute('data-rsvp-current') || '';
        if (previous === choice) {
            applyChoice(eventId, choice);
            return;
        }

        if (pending[eventId]) {
            return;
        }
        pending[eventId] = true;
        setBusy(eventId, true);
        applyChoice(eventId, choice, { busy: true });

        var fd = new FormData();
        fd.append('_csrf_token', csrf);
        fd.append('status', choice);

        var reasonSel = group.querySelector('[name="absence_reason"]');
        if (choice === 'no' && reasonSel && reasonSel.value) {
            fd.append('absence_reason', reasonSel.value);
        }

        fetch(base + eventId + '/rsvp', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) {
                return res.json().catch(function () {
                    return { ok: res.ok, status: choice };
                });
            })
            .then(function (data) {
                pending[eventId] = false;
                setBusy(eventId, false);
                if (data && data.ok) {
                    var saved = (data.status || choice);
                    applyChoice(eventId, saved);
                } else {
                    setError(
                        eventId,
                        (data && data.error) || 'Impossible d’enregistrer votre réponse.',
                        previous
                    );
                }
            })
            .catch(function () {
                pending[eventId] = false;
                setBusy(eventId, false);
                setError(eventId, 'Connexion impossible. Réessayez.', previous);
            });
    }

    function onClick(e) {
        var btn = e.target.closest('[data-rsvp-choice]');
        if (!btn || btn.disabled) {
            return;
        }
        var group = btn.closest('[data-rsvp-group]');
        if (!group) {
            return;
        }
        var eventId = parseInt(group.getAttribute('data-event-id') || '0', 10);
        var choice = btn.getAttribute('data-rsvp-choice');
        if (!eventId || !choice) {
            return;
        }
        e.preventDefault();
        submit(eventId, choice, group);
    }

    function onReasonChange(e) {
        var sel = e.target.closest('[data-rsvp-absence-reason], select[name="absence_reason"]');
        if (!sel) {
            return;
        }
        var group = sel.closest('[data-rsvp-group]');
        if (!group) {
            return;
        }
        if ((group.getAttribute('data-rsvp-current') || '') !== 'no') {
            return;
        }
        var eventId = parseInt(group.getAttribute('data-event-id') || '0', 10);
        if (!eventId) {
            return;
        }
        // Force re-send with the chosen reason even if status is already "no".
        pending[eventId] = false;
        group.setAttribute('data-rsvp-current', '');
        submit(eventId, 'no', group);
    }

    function boot() {
        document.querySelectorAll('[data-rsvp-group]').forEach(initGroup);
        document.addEventListener('click', onClick);
        document.addEventListener('change', onReasonChange);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
