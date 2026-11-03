/* Recherche commune aux nationalités des fiches membres et du registre SSE. */
(function () {
    'use strict';
    if (window.__identityCountryPicker) return;
    window.__identityCountryPicker = true;
    // Mots-clés de recherche (jamais affichés), français et anglais, des pays réels stylisés (univers réaliste-simulation).
    var aliases = {
        USA: 'US américaine américain American United States',
        GBR: 'UK GB britannique British England Angleterre',
        FRA: 'FR française français French',
        BEL: 'BE Belgique belge Belgian België',
        DEU: 'GER DE allemande allemand German Deutschland',
        ESP: 'ES Espagne espagnole espagnol Spanish España',
        COL: 'CO Colombie colombienne colombien Colombian',
        AUT: 'AT Autriche autrichienne autrichien Austrian Österreich',
        GRC: 'GR Grèce grecque grec Greek Hellas',
        POL: 'PL Pologne polonaise polonais Polish Polska',
        UKR: 'UA ukrainienne ukrainien Ukrainian',
        RUS: 'RU Russie russe Russian',
        IRQ: 'IQ Irak irakienne irakien Iraqi',
        SYR: 'SY Syrie syrienne syrien Syrian',
        AFG: 'AF afghane afghan Afghan'
    };
    function normalize(value) { return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
    function init() {
        document.querySelectorAll('select[name="nationality"], select[data-country-picker]').forEach(function (select, index) {
            if (select.dataset.countryPickerReady || !window.HTMLDialogElement) return;
            select.dataset.countryPickerReady = '1';
            var opener = document.createElement('button');
            opener.type = 'button'; opener.className = 'eff-fm-btn';
            opener.setAttribute('aria-haspopup', 'dialog');
            var dialog = document.createElement('dialog');
            dialog.className = 'eff-dialog identity-country-dialog';
            dialog.id = 'identity-country-dialog-' + index;
            dialog.style.cssText = 'width:min(92vw,640px);max-height:80vh;padding:24px;border:1px solid #64748b;border-radius:12px;background:var(--surface,#16202d);color:var(--text,#f1f5f9)';
            opener.setAttribute('aria-controls', dialog.id);
            var title = document.createElement('h2'); title.id = dialog.id + '-title'; title.textContent = 'Choisir un pays';
            dialog.setAttribute('aria-labelledby', title.id);
            var close = document.createElement('button'); close.type = 'button'; close.textContent = 'Fermer'; close.className = 'eff-fm-btn';
            var input = document.createElement('input'); input.type = 'search'; input.placeholder = 'Rechercher un pays, une nationalité ou un code…'; input.setAttribute('aria-label', 'Rechercher un pays'); input.style.cssText = 'width:100%;margin:16px 0;padding:12px';
            var list = document.createElement('div'); list.style.cssText = 'display:grid;gap:8px;max-height:45vh;overflow:auto';
            var empty = document.createElement('p'); empty.textContent = 'Aucun pays trouvé.'; empty.setAttribute('role', 'status'); empty.hidden = true;
            dialog.append(title, close, input, list, empty);
            // Un dialog placé hors du formulaire évite les soumissions accidentelles et les labels imbriqués.
            document.body.appendChild(dialog);
            function label() { opener.textContent = select.options[select.selectedIndex] ? select.options[select.selectedIndex].textContent : 'Choisir un pays'; opener.disabled = select.disabled; }
            function render() {
                list.replaceChildren();
                var query = normalize(input.value.trim());
                Array.prototype.forEach.call(select.options, function (option) {
                    if (option.disabled || !normalize(option.textContent + ' ' + option.value + ' ' + (aliases[option.value] || '')).includes(query)) return;
                    var button = document.createElement('button'); button.type = 'button'; button.className = 'eff-fm-btn'; button.style.cssText = 'text-align:left;padding:12px'; button.textContent = (option.selected ? '✓ ' : '') + option.textContent;
                    button.addEventListener('click', function () { select.value = option.value; select.dispatchEvent(new Event('change', {bubbles: true})); dialog.close(); });
                    list.appendChild(button);
                });
                empty.hidden = list.children.length > 0;
            }
            select.insertAdjacentElement('afterend', opener); select.hidden = true;
            opener.addEventListener('click', function () { input.value = ''; render(); dialog.showModal(); input.focus(); });
            close.addEventListener('click', function () { dialog.close(); });
            dialog.addEventListener('close', function () { opener.focus(); });
            input.addEventListener('input', render); select.addEventListener('change', label); label();
        });
        document.querySelectorAll('input[type="date"][name="birth_date"]').forEach(function (input) {
            var output = document.createElement('small'); output.setAttribute('aria-live', 'polite'); input.insertAdjacentElement('afterend', output);
            function refresh() {
                output.textContent = '';
                if (!/^\d{4}-\d{2}-\d{2}$/.test(input.value)) return;
                var parts = input.value.split('-').map(Number), now = new Date();
                var age = now.getFullYear() - parts[0];
                if (now.getMonth() + 1 < parts[1] || (now.getMonth() + 1 === parts[1] && now.getDate() < parts[2])) age--;
                if (age >= 0) output.textContent = age + ' ans';
            }
            input.addEventListener('input', refresh); refresh();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
