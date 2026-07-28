/**
 * Assistant création communauté — affiliation unité réelle / fictive.
 */
(function () {
    'use strict';

    var catalog = window.__realUnitCatalog || { countries: {}, units: {} };
    var selectedIds = [];
    var currentCountry = '';

    function $(id) {
        return document.getElementById(id);
    }

    function modeRadios() {
        var form = $('community-create-form');
        if (!form) return [];
        return form.querySelectorAll('input[name="wizard_represents_real_unit"]');
    }

    function currentMode() {
        var checked = document.querySelector('input[name="wizard_represents_real_unit"]:checked');
        if (!checked) return '';
        return checked.getAttribute('data-unit-affiliation-mode') || '';
    }

    function syncPanels() {
        var mode = currentMode();
        var fictional = $('unit-affiliation-fictional');
        var real = $('unit-affiliation-real');
        var fictionalInput = $('wizard-fictional-unit-label');
        if (fictional) fictional.classList.toggle('hidden', mode !== 'fictional');
        if (real) real.classList.toggle('hidden', mode !== 'real');
        if (fictionalInput) fictionalInput.required = mode === 'fictional';
        if (mode === 'real') {
            syncCountryPicker();
        } else if (mode === 'fictional') {
            clearRealSelections();
        }
    }

    function clearRealSelections() {
        selectedIds = [];
        currentCountry = '';
        var countrySel = $('wizard-real-unit-country');
        if (countrySel) countrySel.value = '';
        var search = $('wizard-real-unit-search');
        if (search) search.value = '';
        var picker = $('unit-affiliation-real-picker');
        if (picker) picker.classList.add('hidden');
        renderUnitList('');
        updateSummary();
    }

    function syncCountryPicker() {
        var countrySel = $('wizard-real-unit-country');
        var picker = $('unit-affiliation-real-picker');
        if (!countrySel || !picker) return;
        var code = countrySel.value || '';
        if (code !== currentCountry) {
            selectedIds = [];
            currentCountry = code;
            var search = $('wizard-real-unit-search');
            if (search) search.value = '';
        }
        picker.classList.toggle('hidden', code === '');
        renderUnitList(search ? search.value : '');
        updateSummary();
    }

    function tierLabel(tier) {
        var map = {
            command: 'Commandement',
            component: 'Composante',
            unit: 'Unité',
            subunit: 'Sous-unité',
        };
        return map[tier] || '';
    }

    function renderUnitList(query) {
        var list = $('wizard-real-unit-list');
        if (!list) return;
        list.innerHTML = '';
        if (!currentCountry || !catalog.units || !catalog.units[currentCountry]) {
            return;
        }
        var units = catalog.units[currentCountry];
        var q = (query || '').toLowerCase().trim();
        var visible = 0;
        var lastTier = '';

        units.forEach(function (u) {
            var name = String(u.name || '');
            if (q && name.toLowerCase().indexOf(q) === -1) {
                return;
            }
            visible += 1;
            if (u.tier && u.tier !== lastTier) {
                lastTier = u.tier;
                var heading = document.createElement('p');
                heading.className = 'cc-unit-affiliation-tier';
                heading.textContent = tierLabel(u.tier);
                list.appendChild(heading);
            }
            var label = document.createElement('label');
            label.className = 'cc-unit-affiliation-item';
            var indent = parseInt(u.indent, 10) || 0;
            if (indent > 0) {
                label.style.paddingLeft = (0.75 + indent * 0.85) + 'rem';
            }
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'wizard_real_unit_ids[]';
            cb.value = u.id;
            cb.className = 'cc-unit-affiliation-cb';
            cb.checked = selectedIds.indexOf(u.id) !== -1;
            cb.addEventListener('change', function () {
                if (cb.checked) {
                    if (selectedIds.indexOf(u.id) === -1) {
                        selectedIds.push(u.id);
                    }
                } else {
                    selectedIds = selectedIds.filter(function (id) { return id !== u.id; });
                }
                updateSummary();
            });
            var span = document.createElement('span');
            span.textContent = name;
            label.appendChild(cb);
            label.appendChild(span);
            list.appendChild(label);
        });

        if (visible === 0) {
            var empty = document.createElement('p');
            empty.className = 'cc-unit-affiliation-empty';
            empty.textContent = q ? 'Aucune unité ne correspond à votre recherche.' : 'Aucune unité disponible pour ce pays.';
            list.appendChild(empty);
        }
    }

    function updateSummary() {
        var summary = $('wizard-real-unit-selection-summary');
        if (!summary) return;
        if (!currentCountry || selectedIds.length === 0) {
            summary.textContent = 'Aucune unité sélectionnée.';
            return;
        }
        var units = (catalog.units && catalog.units[currentCountry]) ? catalog.units[currentCountry] : [];
        var names = [];
        selectedIds.forEach(function (id) {
            units.forEach(function (u) {
                if (u.id === id) names.push(u.name);
            });
        });
        var countryLabel = (catalog.countries && catalog.countries[currentCountry]) ? catalog.countries[currentCountry] : currentCountry;
        summary.textContent = names.length + ' unité(s) sélectionnée(s) — ' + countryLabel + ' : ' + names.join(', ');
    }

    function bind() {
        modeRadios().forEach(function (r) {
            r.addEventListener('change', syncPanels);
        });
        var countrySel = $('wizard-real-unit-country');
        if (countrySel) {
            countrySel.addEventListener('change', syncCountryPicker);
        }
        var search = $('wizard-real-unit-search');
        if (search) {
            search.addEventListener('input', function () {
                renderUnitList(search.value);
            });
        }
        syncPanels();
    }

    function restoreFromDraft(draft) {
        if (!draft || typeof draft !== 'object') return;
        var modeVal = draft.wizard_represents_real_unit;
        if (modeVal === undefined || modeVal === null || modeVal === '') return;
        var isReal = modeVal === '1' || modeVal === 1 || modeVal === true || modeVal === 'true' || modeVal === 'on';
        modeRadios().forEach(function (r) {
            var m = r.getAttribute('data-unit-affiliation-mode');
            r.checked = isReal ? (m === 'real') : (m === 'fictional');
        });
        if (!isReal && draft.wizard_fictional_unit_label) {
            var inp = $('wizard-fictional-unit-label');
            if (inp) inp.value = String(draft.wizard_fictional_unit_label);
        }
        if (isReal) {
            if (draft.wizard_real_unit_country) {
                var countrySel = $('wizard-real-unit-country');
                if (countrySel) countrySel.value = String(draft.wizard_real_unit_country);
                currentCountry = countrySel.value;
            }
            var ids = draft.wizard_real_unit_ids;
            if (typeof ids === 'string' && ids !== '') {
                ids = [ids];
            }
            if (Array.isArray(ids)) {
                selectedIds = ids.map(String).filter(Boolean);
            }
        }
        syncPanels();
    }

    window.CommunityUnitAffiliation = {
        init: bind,
        restore: restoreFromDraft,
        getSelectedIds: function () { return selectedIds.slice(); },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
