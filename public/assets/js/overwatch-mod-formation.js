/**
 * Progression locale du parcours formation Overwatch (localStorage).
 */
(function () {
    var STORAGE_KEY = 'ow-formation-v1';
    var MODULES = ['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7'];

    var moduleSteps = {
        m1: ['m1-s1', 'm1-s2', 'm1-s3', 'm1-s4', 'm1-s5', 'm1-s6', 'm1-s7'],
        m2: ['m2-s1', 'm2-s2', 'm2-s3', 'm2-s4', 'm2-s5', 'm2-s6'],
        m3: ['m3-s1', 'm3-s2', 'm3-s3', 'm3-s4', 'm3-s5', 'm3-s6'],
        m4: ['m4-s1', 'm4-s2', 'm4-s3', 'm4-s4', 'm4-s5', 'm4-s6'],
        m5: ['m5-s1', 'm5-s2', 'm5-s3', 'm5-s4', 'm5-s5'],
        m6: ['m6-s1', 'm6-s2', 'm6-s3', 'm6-s4', 'm6-s5', 'm6-s6', 'm6-s7'],
        m7: ['m7-s1', 'm7-s2', 'm7-s3'],
    };

    function loadState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return {};
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function saveState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) { /* quota / private mode */ }
    }

    function allStepIds() {
        var ids = [];
        MODULES.forEach(function (m) {
            (moduleSteps[m] || []).forEach(function (s) { ids.push(s); });
        });
        return ids;
    }

    function countDone(state) {
        return allStepIds().filter(function (id) { return !!state[id]; }).length;
    }

    function isModuleComplete(state, moduleId) {
        var steps = moduleSteps[moduleId] || [];
        if (!steps.length) return false;
        return steps.every(function (s) { return !!state[s]; });
    }

    function updateUI(state) {
        var total = allStepIds().length;
        var done = countDone(state);
        var pct = total ? Math.round((done / total) * 100) : 0;

        var fill = document.getElementById('ow-formation-progress-fill');
        var label = document.getElementById('ow-formation-progress-label');
        var bar = document.querySelector('.ow-formation__progress-bar');
        if (fill) fill.style.width = pct + '%';
        if (bar) bar.setAttribute('aria-valuenow', String(pct));
        if (label) {
            label.textContent = pct + ' % — ' + done + ' / ' + total + ' étapes validées';
        }

        MODULES.forEach(function (mid) {
            var badge = document.querySelector('[data-module-badge="' + mid + '"]');
            var complete = isModuleComplete(state, mid);
            if (badge) badge.hidden = !complete;
            var article = document.querySelector('[data-module="' + mid + '"]');
            if (article) article.classList.toggle('ow-formation__module--done', complete);
        });

        var completeMsg = document.getElementById('ow-formation-complete-msg');
        if (completeMsg) {
            var allDone = MODULES.every(function (m) { return isModuleComplete(state, m); });
            completeMsg.hidden = !allDone;
        }

        document.querySelectorAll('[data-formation-check]').forEach(function (input) {
            var id = input.getAttribute('data-formation-check');
            input.checked = !!state[id];
            var step = input.closest('.ow-formation__step');
            if (step) step.classList.toggle('ow-formation__step--done', !!state[id]);
        });
    }

    function bind() {
        var state = loadState();
        updateUI(state);

        document.querySelectorAll('[data-formation-check]').forEach(function (input) {
            input.addEventListener('change', function () {
                var id = input.getAttribute('data-formation-check');
                if (!id) return;
                if (input.checked) state[id] = true;
                else delete state[id];
                saveState(state);
                updateUI(state);
            });
        });

        var resetBtn = document.getElementById('ow-formation-reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (!window.confirm('Effacer toute votre progression sur cet appareil ?')) return;
                state = {};
                saveState(state);
                updateUI(state);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
