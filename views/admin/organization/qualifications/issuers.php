<?php
$issuers = $issuers ?? [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <a href="<?= url('back-office/referentiels/qualifications') ?>" class="text-sm text-slate-500 hover:text-slate-800">← Référentiel</a>
    <h1 class="text-2xl font-black text-slate-900 mt-2 mb-6">Organismes émetteurs</h1>
    <?php if ($flashSuccess): ?><p class="mb-4 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded"><?= htmlspecialchars($flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

    <ul class="mb-6 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
        <?php foreach ($issuers as $i): ?>
            <li class="px-4 py-3 text-sm flex justify-between gap-3">
                <span>
                    <strong><?= htmlspecialchars((string) $i['name']) ?></strong>
                    <?php if (!empty($i['short_name'])): ?><span class="text-slate-500">(<?= htmlspecialchars((string) $i['short_name']) ?>)</span><?php endif; ?>
                    <span class="ml-2 text-xs uppercase tracking-wider text-slate-400"><?= htmlspecialchars((string) $i['issuer_kind']) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
        <?php if ($issuers === []): ?>
            <li class="px-4 py-6 text-sm text-slate-500">Aucun organisme pour l’instant.</li>
        <?php endif; ?>
    </ul>

    <form method="post" action="<?= url('back-office/referentiels/qualifications/emetteurs') ?>" class="space-y-3 rounded-lg border border-slate-200 bg-white p-6">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Nom</label>
            <input name="name" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>
        <div class="grid md:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nom court</label>
                <input name="short_name" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nature</label>
                <select name="issuer_kind" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <option value="school">École</option>
                    <option value="unit" selected>Unité</option>
                    <option value="external">Organisme externe</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Rattaché à</label>
            <select name="parent_issuer_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($issuers as $i): ?>
                    <option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded">Ajouter</button>
    </form>
</div>
