<?php
/** @var array $tenant */
$code = $tenant['community_code'] ?? '';
?>
<div class="max-w-xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Identité communauté</h1>
    <p class="text-slate-600 text-sm mb-2">Nom affiché, adresse publique <code class="bg-slate-100 px-1 rounded">/c/…</code> et code court pour rejoindre (<code class="bg-slate-100 px-1 rounded">/join</code>).</p>
    <p class="mb-8"><a href="<?= htmlspecialchars(url('back-office/community/presentation')) ?>" class="text-sm font-semibold text-emerald-700 underline">Fiche registre, jeu, contact &amp; visibilité catalogue</a></p>
    <?php $err = \App\Core\Session::getFlash('error'); $ok = \App\Core\Session::getFlash('success'); ?>
    <?php if ($err): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <?php if ($ok): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($ok) ?></p><?php endif; ?>
    <form method="post" action="<?= url('back-office/community') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Nom affiché</label>
            <input type="text" name="tenant_name" value="<?= htmlspecialchars((string) ($tenant['name'] ?? '')) ?>" maxlength="255" required class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Slug URL (page publique)</label>
            <input type="text" name="tenant_slug" value="<?= htmlspecialchars((string) ($tenant['slug'] ?? '')) ?>" maxlength="50" required pattern="[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?" class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono lowercase" placeholder="mon-unite">
            <p class="text-[10px] text-slate-500 mt-1">Lettres minuscules, chiffres, tirets. Si vous le modifiez, mettez à jour les liens partagés.</p>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Code rejoindre (vide = retirer)</label>
            <input type="text" name="community_code" value="<?= htmlspecialchars((string) $code) ?>" maxlength="64" class="w-full rounded border border-slate-300 px-3 py-2 text-sm uppercase font-mono" placeholder="MON-UNIT">
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-bold rounded-lg hover:bg-slate-800">Enregistrer</button>
    </form>
    <p class="mt-8"><a href="<?= url('back-office') ?>" class="text-sm text-slate-600 underline">Retour administration organisation</a></p>
</div>
