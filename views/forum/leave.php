<?php
/** @var string $leaveTargetUrl */
/** @var string $leaveDomain */
/** @var bool $leaveIsHttps */
/** @var string $leaveUserDisplayName */
/** @var int $leaveCountdown */
/** @var array $forumConfig */
$forumConfig = $forumConfig ?? [];
$baseUrl = url('');
$countdown = max(0, (int) ($leaveCountdown ?? 5));

$subtitle = trim((string) ($forumConfig['subtitle'] ?? 'Athena'));
$subtitleParts = array_map('trim', explode('·', $subtitle));
$lastSeg = $subtitleParts !== [] ? $subtitleParts[count($subtitleParts) - 1] : $subtitle;
$brandHeadline = $lastSeg !== ''
    ? (function_exists('mb_strtoupper') ? mb_strtoupper($lastSeg) : strtoupper($lastSeg))
    : 'PORTAIL';

$forumName = htmlspecialchars(trim((string) ($forumConfig['name'] ?? 'Forum')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$subtitleSafe = htmlspecialchars($subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$brandHeadlineSafe = htmlspecialchars($brandHeadline, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$tips = [
    [
        'icon' => '🚫',
        'title' => 'Évite les publicités sur ce site',
        'body' => 'Elles peuvent être trompeuses, mener à des logiciels indésirables ou vers des arnaques.',
    ],
    [
        'icon' => '🍪',
        'title' => 'Méfie-toi des demandes de cookies',
        'body' => 'Si tu ne consultes pas ce site souvent, inutile d’accepter tout — ça sert souvent à te suivre à la trace.',
    ],
    [
        'icon' => '🔐',
        'title' => 'Tes identifiants du portail',
        'body' => 'Ne les saisis jamais sur un site externe. Nous ne te demanderons jamais ton mot de passe en dehors du portail officiel.',
    ],
    [
        'icon' => '⚡',
        'title' => 'Contenu non vérifié',
        'body' => 'Le portail et ta communauté ne contrôlent pas ce que tu vas voir sur cette adresse.',
    ],
    [
        'icon' => '💳',
        'title' => 'Paiements et « offres »',
        'body' => 'Aucun service officiel lié au portail ne se règle sur des sites tiers. En cas de doute, reviens ici et demande à l’équipe.',
    ],
];
?>
<div class="flex min-h-screen flex-col bg-white">
    <div class="border-b border-red-200 bg-red-50 px-5 py-6 md:px-7 md:py-6">
        <div class="mx-auto flex max-w-3xl items-center gap-4 md:gap-5">
            <div class="relative shrink-0">
                <div class="absolute inset-0 rounded-2xl bg-red-400 opacity-25 blur-xl" aria-hidden="true"></div>
                <div class="leave-warn-pulse relative flex h-12 w-12 items-center justify-center rounded-2xl border-2 border-red-400 bg-white shadow-sm" aria-hidden="true">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-black uppercase italic leading-none tracking-tighter text-slate-900">Tu quittes <?= $brandHeadlineSafe ?></h1>
                <p class="mt-1.5 text-[11px] font-bold leading-relaxed text-red-700/90">Ce lien ouvre un site externe, hors du portail — reste prudent·e.</p>
            </div>
        </div>
    </div>

    <div class="border-b border-slate-200 bg-slate-50 px-5 py-2.5 md:px-7">
        <div class="mx-auto flex max-w-3xl items-start gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] leading-snug text-emerald-900">
            <span class="mt-0.5 shrink-0 font-black tracking-tight text-emerald-800" aria-hidden="true"><?= $brandHeadlineSafe ?></span>
            <p class="text-emerald-900/90"><span class="font-semibold text-slate-900">Rappel portail.</span> Tu es connecté·e à l’espace sécurisé <?= $subtitleSafe ?> (<?= $forumName ?>). Ce message s’affiche pour te laisser le choix avant d’ouvrir une page tiers.</p>
        </div>
    </div>

    <div class="flex flex-1 flex-col items-center px-4 py-10 sm:py-12">
        <div class="w-full max-w-lg">
            <p class="mb-6 text-center text-sm leading-relaxed text-slate-600">
                Bonjour <strong class="font-semibold text-slate-900"><?= htmlspecialchars($leaveUserDisplayName, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
                <span class="text-slate-500"> — lis bien les points ci-dessous avant de continuer.</span>
            </p>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/60 sm:p-8">
                <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Destination</p>
                    <p class="break-words text-sm font-bold text-slate-900"><?= htmlspecialchars($leaveDomain, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                    <p class="mt-2 break-all font-mono text-[11px] leading-snug text-slate-600"><?= htmlspecialchars($leaveTargetUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                    <?php if (!empty($leaveIsHttps)): ?>
                    <p class="mt-2 text-[11px] font-medium text-emerald-700">La connexion vers ce site est chiffrée (recommandé).</p>
                    <?php endif; ?>
                </div>

                <p class="mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Avant d’ouvrir le lien</p>
                <ul class="mb-8 space-y-4">
                    <?php foreach ($tips as $tip): ?>
                    <li class="flex gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3.5">
                        <span class="shrink-0 text-lg leading-none" aria-hidden="true"><?= htmlspecialchars($tip['icon'], ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($tip['title'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($tip['body'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <label class="mb-6 flex cursor-pointer select-none items-start gap-3 group">
                    <input type="checkbox" id="leave-accept" class="mt-1 h-4 w-4 rounded border-slate-300 bg-white text-red-600 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-white">
                    <span class="text-sm leading-snug text-slate-700 group-hover:text-slate-900">
                        J’ai pris connaissance des risques et je choisis d’ouvrir ce lien dans mon navigateur.
                    </span>
                </label>

                <div class="mb-6">
                    <div class="mb-2 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                        <span>Bouton actif dans <strong id="leave-countdown" class="tabular-nums text-slate-800"><?= (int) $countdown ?></strong> s</span>
                        <span class="flex flex-wrap gap-x-3 gap-y-1">
                            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/dashboard" class="font-medium text-emerald-700 hover:text-emerald-800">Tableau de bord</a>
                            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="font-medium text-emerald-700 hover:text-emerald-800">Forum</a>
                        </span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                        <div id="leave-progress" class="h-full rounded-full bg-gradient-to-r from-amber-500 to-red-600 transition-all duration-1000 ease-linear" style="width:0%"></div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 transition-colors hover:bg-slate-100">
                        Annuler — rester sur le portail
                    </a>
                    <button type="button" id="leave-continue" disabled class="inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-slate-200 px-4 py-3 text-sm font-semibold text-slate-500 transition-colors">
                        Ouvrir le site externe
                    </button>
                </div>
            </div>

            <p class="mt-6 text-center text-[10px] uppercase tracking-widest text-slate-400"><?= $subtitleSafe ?> · sécurité des liens</p>
        </div>
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
            btn.classList.remove('bg-slate-200', 'text-slate-500', 'cursor-not-allowed');
            btn.classList.add('bg-red-600', 'hover:bg-red-700', 'text-white', 'cursor-pointer', 'shadow-lg', 'shadow-red-600/25');
        } else {
            btn.classList.add('bg-slate-200', 'text-slate-500', 'cursor-not-allowed');
            btn.classList.remove('bg-red-600', 'hover:bg-red-700', 'text-white', 'cursor-pointer', 'shadow-lg', 'shadow-red-600/25');
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
