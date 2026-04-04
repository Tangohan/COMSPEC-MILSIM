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
<div class="max-w-2xl mx-auto px-4 py-10">
    <div class="flex items-start gap-4 mb-8">
        <div class="flex-shrink-0 w-12 h-12 bg-red-600 rounded flex items-center justify-center text-white text-2xl font-black">!</div>
        <div>
            <h1 class="text-2xl font-black italic uppercase tracking-tight text-white mb-2">Tu quittes <?= $forumName ?></h1>
            <p class="text-sm text-red-400/90">Ce lien mène vers un site externe non contrôlé par nous.</p>
        </div>
    </div>

    <p class="text-xs uppercase tracking-widest text-neutral-500 mb-2">Destination</p>
    <div class="rounded-xl border border-indigo-500/40 bg-neutral-900/80 p-4 mb-8">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm">🔗</div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-white uppercase tracking-wide truncate"><?= htmlspecialchars($leaveDomain, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                <p class="text-xs text-neutral-400 break-all mt-1"><?= htmlspecialchars($leaveTargetUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
            </div>
            <?php if (!empty($leaveIsHttps)): ?>
            <span class="text-xs font-semibold text-emerald-400 whitespace-nowrap">HTTPS</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-8">
        <p class="text-xs uppercase tracking-widest text-neutral-500 mb-4">Rappels importants</p>
        <ul class="space-y-4 text-sm text-neutral-400 border-l-2 border-neutral-700 pl-4">
            <li><span class="text-yellow-400 font-semibold">Ne clique pas sur les publicités</span> — elles peuvent être trompeuses ou malveillantes.</li>
            <li><span class="text-orange-300 font-semibold">N'accepte pas les cookies</span> inutiles si tu n'utilises pas ce site régulièrement.</li>
            <li><span class="text-red-300 font-semibold">Ne saisis jamais tes identifiants <?= $forumName ?></span> sur un site externe.</li>
            <li><span class="text-violet-400 font-semibold">Contenu non vérifié</span> — nous ne sommes pas responsables du contenu tiers.</li>
            <li><span class="text-sky-400 font-semibold">Méfie-toi des demandes de paiement</span> — aucun service officiel ne se paie sur des sites tiers.</li>
        </ul>
    </div>

    <div class="mb-6">
        <div class="flex justify-between text-xs text-neutral-500 mb-2">
            <span>Disponible dans <strong id="leave-countdown" class="text-white"><?= (int) $countdown ?></strong>s</span>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="text-neutral-400 hover:text-white">ou retourne au forum</a>
        </div>
        <div class="h-1.5 bg-neutral-800 rounded overflow-hidden">
            <div id="leave-progress" class="h-full bg-red-600 transition-all duration-1000" style="width:0%"></div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-neutral-800 text-white text-sm font-semibold hover:bg-neutral-700">
            ← Retour au forum
        </a>
        <button type="button" id="leave-continue" disabled class="inline-flex items-center justify-center px-4 py-3 rounded-lg bg-red-950 text-red-300 text-sm font-semibold cursor-not-allowed opacity-60">
            Continuer quand même
        </button>
    </div>

    <p class="text-center text-xs text-neutral-500 mt-8">
        Bonjour <strong class="text-neutral-300"><?= htmlspecialchars($leaveUserDisplayName, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong> — reste vigilant·e en dehors de la salle.
    </p>
    <p class="text-center text-[10px] uppercase tracking-widest text-neutral-600 mt-4"><?= $forumName ?> · Sécurité membres</p>
</div>

<script>
(function() {
    var target = <?= json_encode($leaveTargetUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var sec = <?= (int) $countdown ?>;
    var btn = document.getElementById('leave-continue');
    var el = document.getElementById('leave-countdown');
    var bar = document.getElementById('leave-progress');
    var total = Math.max(1, sec);
    function tick() {
        if (el) el.textContent = String(sec);
        if (bar) bar.style.width = ((total - sec) / total * 100) + '%';
        if (sec <= 0) {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('cursor-not-allowed', 'opacity-60');
                btn.classList.add('hover:bg-red-800', 'cursor-pointer');
            }
            return;
        }
        sec--;
        setTimeout(tick, 1000);
    }
    if (btn) {
        btn.addEventListener('click', function() {
            if (!btn.disabled) window.location.href = target;
        });
    }
    tick();
})();
</script>
