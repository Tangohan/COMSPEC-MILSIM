<?php
/** @var string $sessionId */
/** @var bool $timedOut */
/** @var string $retryUrl */
$sessionId = $sessionId ?? '';
$timedOut = !empty($timedOut);
$retryUrl = $retryUrl ?? (url('communities/create/complete') . '?session_id=' . rawurlencode((string) $sessionId));
?>
<div class="max-w-lg mx-auto px-4 py-16 text-center">
    <p class="text-[10px] font-black uppercase tracking-widest text-amber-500 mb-2">Paiement</p>
    <?php if ($timedOut): ?>
    <h1 class="text-xl font-black text-slate-900 mb-3">Validation plus longue que prévu</h1>
    <p class="text-sm text-slate-600 mb-6">Votre paiement a bien été reçu, mais la création de la communauté prend plus de temps que d’habitude. Réessayez dans un instant ou revenez à l’assistant de création.</p>
  <div class="flex flex-col gap-3 items-center">
        <a href="<?= htmlspecialchars($retryUrl) ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Réessayer maintenant</a>
        <a href="<?= htmlspecialchars(url('communities/create')) ?>" class="text-sm text-slate-600 underline font-semibold">Retour à l’assistant de création</a>
    </div>
    <?php else: ?>
    <h1 class="text-xl font-black text-slate-900 mb-3">Validation du paiement</h1>
    <p class="text-sm text-slate-600 mb-6">Nous confirmons votre abonnement. Cela prend en général quelques secondes.</p>
    <div class="inline-block h-8 w-8 border-2 border-slate-200 border-t-emerald-600 rounded-full animate-spin mb-6" aria-hidden="true"></div>
    <p class="text-xs text-slate-500 mb-4">Cette page se recharge automatiquement.</p>
    <a href="<?= htmlspecialchars($retryUrl) ?>" class="text-sm text-emerald-700 underline font-semibold">Réessayer maintenant</a>
    <?php endif; ?>
</div>
<?php if (!$timedOut): ?>
<script>
setTimeout(function () { window.location.href = <?= json_encode($retryUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>; }, 2800);
</script>
<?php endif; ?>
