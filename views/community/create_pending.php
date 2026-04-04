<?php
/** @var string $sessionId */
$sessionId = $sessionId ?? '';
$retryUrl = url('communities/create/complete') . '?session_id=' . rawurlencode((string) $sessionId);
?>
<div class="max-w-lg mx-auto px-4 py-16 text-center">
    <p class="text-[10px] font-black uppercase tracking-widest text-amber-500 mb-2">Paiement</p>
    <h1 class="text-xl font-black text-slate-900 mb-3">Validation du paiement</h1>
    <p class="text-sm text-slate-600 mb-6">Stripe confirme votre abonnement. Cela prend en général quelques secondes.</p>
    <div class="inline-block h-8 w-8 border-2 border-slate-200 border-t-emerald-600 rounded-full animate-spin mb-6" aria-hidden="true"></div>
    <p class="text-xs text-slate-500 mb-4">Cette page se recharge automatiquement.</p>
    <a href="<?= htmlspecialchars($retryUrl) ?>" class="text-sm text-emerald-700 underline font-semibold">Réessayer maintenant</a>
</div>
<script>
setTimeout(function () { window.location.href = <?= json_encode($retryUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>; }, 2800);
</script>
