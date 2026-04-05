/**
 * Navigation portail : drawer mobile, accordéons, piège de focus, Escape.
 * Desktop xl+ : méga menus CSS (hover / focus-within) + bascule au clic (tactile / ouverture explicite).
 */
(function () {
    var XL = 1280;

    function isXl() {
        return window.matchMedia('(min-width: ' + XL + 'px)').matches;
    }

    var root = document.querySelector('[data-portal-nav]');
    if (!root) return;

    var toggle = root.querySelector('[data-mobile-nav-toggle]');
    var drawer = document.querySelector('[data-mobile-nav-drawer]');
    var overlay = document.querySelector('[data-mobile-nav-overlay]');
    var closeBtn = drawer ? drawer.querySelector('[data-mobile-nav-close]') : null;

    var lastFocusBeforeDrawer = null;

    function focusableElements(container) {
        if (!container) return [];
        var sel = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        return Array.prototype.slice.call(container.querySelectorAll(sel)).filter(function (el) {
            var accPanel = el.closest('[data-mobile-accordion-panel]');
            if (accPanel && accPanel.classList.contains('hidden')) return false;
            var st = window.getComputedStyle(el);
            return st.visibility !== 'hidden' && st.display !== 'none';
        });
    }

    function setDrawerOpen(open) {
        if (!drawer || !overlay) return;
        var html = document.documentElement;
        if (open) {
            lastFocusBeforeDrawer = document.activeElement;
            drawer.hidden = false;
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');
            overlay.setAttribute('aria-hidden', 'false');
            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
            drawer.setAttribute('aria-hidden', 'false');
            html.classList.add('portal-nav-open');
            document.body.style.overflow = 'hidden';
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
            window.setTimeout(function () {
                var focusables = focusableElements(drawer);
                var toFocus = closeBtn || focusables[0] || drawer;
                if (toFocus && toFocus.focus) {
                    toFocus.focus();
                }
            }, 0);
        } else {
            drawer.classList.add('translate-x-full');
            drawer.classList.remove('translate-x-0');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.setAttribute('aria-hidden', 'true');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            html.classList.remove('portal-nav-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            window.setTimeout(function () {
                if (!drawer.classList.contains('translate-x-0')) {
                    drawer.hidden = true;
                }
            }, 220);
            if (lastFocusBeforeDrawer && typeof lastFocusBeforeDrawer.focus === 'function') {
                lastFocusBeforeDrawer.focus();
            }
            lastFocusBeforeDrawer = null;
        }
    }

    if (toggle && drawer) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            setDrawerOpen(!open);
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    }

    if (drawer) {
        drawer.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !toggle || toggle.getAttribute('aria-expanded') !== 'true') return;
            var focusables = focusableElements(drawer);
            if (focusables.length === 0) return;
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
    }

    function closeAllMegas() {
        root.querySelectorAll('li[data-nav-item].nav-mega-is-open').forEach(function (li) {
            li.classList.remove('nav-mega-is-open');
            var btn = li.querySelector('[data-nav-trigger]');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    root.querySelectorAll('li[data-nav-item][data-nav-type="mega"]').forEach(function (item) {
        var btn = item.querySelector('[data-nav-trigger]');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            if (!isXl()) return;
            e.stopPropagation();
            var wasOpen = item.classList.contains('nav-mega-is-open');
            root.querySelectorAll('li[data-nav-item].nav-mega-is-open').forEach(function (other) {
                if (other !== item) {
                    other.classList.remove('nav-mega-is-open');
                    var ob = other.querySelector('[data-nav-trigger]');
                    if (ob) ob.setAttribute('aria-expanded', 'false');
                }
            });
            if (wasOpen) {
                item.classList.remove('nav-mega-is-open');
                btn.setAttribute('aria-expanded', 'false');
            } else {
                item.classList.add('nav-mega-is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', function (e) {
        if (!isXl()) return;
        if (!e.target) return;
        var insideMega = e.target.closest('li[data-nav-item][data-nav-type="mega"]');
        if (insideMega) return;
        closeAllMegas();
    });

    function closeAlertsPanel() {
        var wrap = document.querySelector('[data-portal-alerts-wrap]');
        if (!wrap) return;
        var trig = wrap.querySelector('[data-portal-alerts-trigger]');
        var panel = wrap.querySelector('[data-portal-alerts-panel]');
        if (!panel) return;
        panel.hidden = true;
        if (trig) {
            trig.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleAlertsPanel() {
        var wrap = document.querySelector('[data-portal-alerts-wrap]');
        if (!wrap) return;
        var trig = wrap.querySelector('[data-portal-alerts-trigger]');
        var panel = wrap.querySelector('[data-portal-alerts-panel]');
        if (!trig || !panel) return;
        var open = trig.getAttribute('aria-expanded') === 'true';
        if (open) {
            panel.hidden = true;
            trig.setAttribute('aria-expanded', 'false');
        } else {
            panel.hidden = false;
            trig.setAttribute('aria-expanded', 'true');
            window.setTimeout(function () {
                var first = panel.querySelector('a, button');
                if (first && first.focus) first.focus();
            }, 0);
        }
    }

    var alertsWrap = document.querySelector('[data-portal-alerts-wrap]');
    if (alertsWrap) {
        var alertsTrig = alertsWrap.querySelector('[data-portal-alerts-trigger]');
        var alertsPanel = alertsWrap.querySelector('[data-portal-alerts-panel]');
        if (alertsTrig && alertsPanel) {
            alertsTrig.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleAlertsPanel();
            });
            document.addEventListener('click', function (e) {
                if (!e.target) return;
                if (alertsWrap.contains(e.target)) return;
                closeAlertsPanel();
            });
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        setDrawerOpen(false);
        closeAllMegas();
        closeAlertsPanel();
    });

    window.addEventListener('resize', function () {
        if (isXl() && drawer && overlay) {
            setDrawerOpen(false);
        }
        if (!isXl()) {
            closeAllMegas();
        }
    });

    root.querySelectorAll('[data-mobile-accordion]').forEach(function (block) {
        var trig = block.querySelector('[data-mobile-accordion-trigger]');
        var panel = block.querySelector('[data-mobile-accordion-panel]');
        var icon = block.querySelector('[data-mobile-accordion-icon]');
        if (!trig || !panel) return;
        trig.addEventListener('click', function () {
            var open = trig.getAttribute('aria-expanded') === 'true';
            trig.setAttribute('aria-expanded', open ? 'false' : 'true');
            panel.classList.toggle('hidden', open);
            if (icon) {
                icon.classList.toggle('rotate-180', !open);
            }
        });
    });

    if (drawer) {
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
    }
    if (overlay) {
        overlay.setAttribute('aria-hidden', 'true');
    }
})();

/** Copie code communauté (registre /communities) — pas d’Alpine dans les attributs HTML. */
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-registry-copy]');
        if (!btn) {
            return;
        }
        var code = btn.getAttribute('data-registry-copy');
        if (!code || !navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            return;
        }
        e.preventDefault();
        navigator.clipboard.writeText(code).then(function () {
            var lab = btn.querySelector('[data-registry-copy-label]');
            var prev = lab ? lab.textContent : '';
            if (lab) {
                lab.textContent = 'Copié';
            }
            window.setTimeout(function () {
                if (lab) {
                    lab.textContent = prev || 'Copier';
                }
            }, 2000);
        });
    });
})();
