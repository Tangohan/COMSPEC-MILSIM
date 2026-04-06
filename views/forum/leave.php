<?php
/** @var string $leaveTargetUrl */
/** @var string $leaveDomain */
/** @var bool $leaveIsHttps */
/** @var string $leaveUserDisplayName */
/** @var int $leaveCountdown */
$forumName = htmlspecialchars($forumConfig['name'] ?? 'Forum', ENT_QUOTES | ENT_HTML5, 'UTF-8');
$baseUrl = url('');
$countdown = max(0, (int) ($leaveCountdown ?? 5));
?>
<div class="flex-1 flex flex-col items-center justify-center px-4 py-10 sm:py-14 bg-gradient-to-b from-slate-100 via-white to-slate-50">
    <div class="w-full max-w-md">
        <p class="text-center text-sm text-slate-600 mb-8 leading-relaxed">
            Bonjour <strong class="text-slate-900 font-semibold"><?= htmlspecialchars($leaveUserDisplayName, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
            <span class="text-slate-500"> — reste vigilant·e en dehors de la salle.</span>
        </p>

        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-lg shadow-slate-200/50 p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-lg font-bold" aria-hidden="true">↗</div>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 leading-tight">Site externe</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Lien hors de <?= $forumName ?></p>
                </div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 mb-6">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Destination</p>
                <p class="font-semibold text-slate-900 text-sm break-words"><?= htmlspecialchars($leaveDomain, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                <p class="text-xs text-slate-500 break-all mt-2 font-mono leading-snug"><?= htmlspecialchars($leaveTargetUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                <?php if (!empty($leaveIsHttps)): ?>
                <p class="text-[11px] text-emerald-600 font-medium mt-2">Connexion sécurisée (HTTPS)</p>
                <?php endif; ?>
            </div>

            <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                Ce contenu n’est pas contrôlé par <?= $forumName ?>. Ne saisis jamais tes identifiants sur un site tiers.
            </p>

            <label class="flex items-start gap-3 cursor-pointer group mb-6 select-none">
                <input type="checkbox" id="leave-accept" class="mt-1 h-4 w-4 rounded border-slate-300 text-red-700 focus:ring-red-600">
                <span class="text-sm text-slate-700 group-hover:text-slate-900 leading-snug">
                    J’accepte de quitter <?= $forumName ?> et d’ouvrir ce lien dans mon navigateur.
                </span>
            </label>

            <div class="mb-6">
                <div class="flex justify-between text-xs text-slate-500 mb-2">
                    <span>Bouton disponible dans <strong id="leave-countdown" class="text-slate-800 tabular-nums"><?= (int) $countdown ?></strong>s</span>
                    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="text-emerald-700 hover:text-emerald-800 font-medium">Retour forum</a>
                </div>
                <div class="h-1.5 bg-slate-200 rounded-full overflow-hidden">
                    <div id="leave-progress" class="h-full bg-gradient-to-r from-amber-500 to-red-600 transition-all duration-1000 ease-linear rounded-full" style="width:0%"></div>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="inline-flex items-center justify-center px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm font-semibold hover:bg-slate-50 transition-colors">
                    Annuler — retour au forum
                </a>
                <button type="button" id="leave-continue" disabled class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-slate-300 text-slate-500 text-sm font-semibold cursor-not-allowed transition-colors">
                    Continuer vers le site
                </button>
            </div>
        </div>

        <p class="text-center text-[10px] text-slate-400 mt-6 uppercase tracking-widest"><?= $forumName ?> · sécurité</p>
    </div>
</div>

<script>
(function() {
    var target = <?= json_encode($leaveTargetUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var sec = <?= (int) $countdown ?>;
    var btn = document.getElementById('leave-continue');
    var accept = document.getElementById('leave-accept');
    var el = document.getElementById('leave-countdown');
    var bar = document.getElementById('leave-progress');
    var total = Math.max(1, sec);
    var countdownReady = sec <= 0;

    function setButtonState() {
        if (!btn) return;
        var ok = countdownReady && accept && accept.checked;
        btn.disabled = !ok;
        if (ok) {
            btn.classList.remove('bg-slate-300', 'text-slate-500', 'cursor-not-allowed');
            btn.classList.add('bg-red-800', 'hover:bg-red-900', 'text-white', 'cursor-pointer', 'shadow-md');
        } else {
            btn.classList.add('bg-slate-300', 'text-slate-500', 'cursor-not-allowed');
            btn.classList.remove('bg-red-800', 'hover:bg-red-900', 'text-white', 'cursor-pointer', 'shadow-md');
        }
    }

    function tick() {
        if (el) el.textContent = String(sec);
        if (bar) bar.style.width = ((total - sec) / total * 100) + '%';
        if (sec <= 0) {
            countdownReady = true;
            setButtonState();
            return;
        }
        sec--;
        setTimeout(tick, 1000);
    }

    if (accept) {
        accept.addEventListener('change', setButtonState);
    }
    if (btn) {
        btn.addEventListener('click', function() {
            if (!btn.disabled) window.location.href = target;
        });
    }
    setButtonState();
    tick();
})();
</script>
