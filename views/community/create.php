<?php $error = \App\Core\Session::getFlash('error'); $success = \App\Core\Session::getFlash('success'); ?>
<div class="max-w-lg mx-auto px-4 py-12">
    <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mb-2">Créer une communauté</h1>
    <p class="text-sm text-slate-500 mb-8">Un nouvel espace (forum, documents, etc.) sera créé. Vous serez administrateur avec le même compte e-mail.</p>
    <?php if ($error): ?><div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" action="<?= url('communities/create') ?>" class="space-y-6">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Nom affiché</label>
            <input type="text" name="name" required maxlength="255" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="92e Régiment d'infanterie">
        </div>
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Slug (URL)</label>
            <input type="text" name="slug" required pattern="[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono" placeholder="92ri">
            <p class="text-[10px] text-slate-400 mt-1">Lettres minuscules, chiffres et tirets. Sera utilisé dans l’URL : /c/<em>slug</em></p>
        </div>
        <button type="submit" class="w-full py-3.5 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600">Créer la communauté</button>
    </form>
</div>
