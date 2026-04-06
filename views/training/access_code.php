<?php
declare(strict_types=1);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<div class="max-w-lg mx-auto px-4 py-10">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight mb-2">Trouver une formation par code</h1>
    <p class="text-sm text-slate-600 mb-6 leading-relaxed">
        Saisissez le code court communiqué par votre formateur ou votre staff. Il ouvre la fiche correspondante dans <strong>cette</strong> communauté lorsque la formation y est publiée.
    </p>
    <?php if ($flashOk): ?>
    <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-sm font-medium"><?= htmlspecialchars((string) $flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
    <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-950 text-sm font-medium"><?= htmlspecialchars((string) $flashErr) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= url('formations/code-acces') ?>" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="access_code" class="block text-xs font-bold text-slate-600 mb-1">Code</label>
            <input type="text" id="access_code" name="access_code" autocomplete="off" maxlength="32" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono tracking-widest uppercase" placeholder="Ex. AB12CD34EF" required>
        </div>
        <button type="submit" class="w-full px-6 py-3 bg-emerald-600 text-white text-xs font-black uppercase tracking-wider rounded-xl hover:bg-emerald-700">Ouvrir la formation</button>
    </form>
    <p class="mt-6 text-sm text-slate-500">
        <a href="<?= url('formations') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-200 hover:text-emerald-950">← Retour au catalogue</a>
    </p>
</div>
