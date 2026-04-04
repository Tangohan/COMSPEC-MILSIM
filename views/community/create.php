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
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Plan communauté</label>
            <select name="plan_slug" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm">
                <option value="free">Free</option>
                <option value="premium">Premium</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Mode d’inscription</label>
            <select name="registration_mode" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm">
                <option value="milsim">Formulaire MilSim complet</option>
                <option value="simple">Mode simple</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Message d’accueil (optionnel)</label>
            <textarea name="welcome_text" rows="3" maxlength="500" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Texte visible sur la page de communauté."></textarea>
        </div>
        <label class="flex items-start gap-3 text-sm text-slate-700">
            <input type="checkbox" name="community_locked" value="1" class="mt-0.5">
            <span><strong>Verrouiller la communauté</strong><br><span class="text-xs text-slate-500">Le recrutement public est fermé.</span></span>
        </label>
        <label class="flex items-start gap-3 text-sm text-slate-700">
            <input type="checkbox" name="require_ai_ack" value="1" checked class="mt-0.5">
            <span><strong>Exiger la confirmation “sans IA”</strong><br><span class="text-xs text-slate-500">Applicable aux inscriptions.</span></span>
        </label>
        <button type="submit" class="w-full py-3.5 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600">Créer la communauté</button>
    </form>
</div>
