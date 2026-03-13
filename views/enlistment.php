<?php $success = \App\Core\Session::getFlash('success'); $error = \App\Core\Session::getFlash('error'); ?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-4">Enrôlement</h1>
    <p class="text-slate-600 mb-8">Formulaire d'enrôlement.</p>
    <?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= url('enlistment') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
                <input type="text" name="last_name" class="w-full px-3 py-2 border border-slate-300 rounded" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Prénom</label>
                <input type="text" name="first_name" class="w-full px-3 py-2 border border-slate-300 rounded" required>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" name="email" class="w-full px-3 py-2 border border-slate-300 rounded" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Callsign</label>
            <input type="text" name="callsign" class="w-full px-3 py-2 border border-slate-300 rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Pays</label>
            <input type="text" name="country" class="w-full px-3 py-2 border border-slate-300 rounded">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Expérience / Disponibilités / Notes</label>
            <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded"></textarea>
        </div>
        <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Soumettre</button>
    </form>
</div>
