<?php
/** @var list<array<string,mixed>> $presets */
$presets = $presets ?? [];
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Profils de candidature</h1>
            <p class="text-slate-600 mt-1 text-sm">Enregistrez des préréglages (motivation, disponibilité…) réutilisables sur les formulaires d’enrôlement.</p>
        </div>
        <a href="<?= url('account/recruitment-presets/create') ?>" class="inline-flex items-center justify-center px-4 py-2.5 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600">Nouveau profil</a>
    </div>

    <?php if (!empty($success)): ?>
        <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (empty($presets)): ?>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-8 text-center text-slate-600">
            <p>Aucun profil enregistré.</p>
            <p class="text-sm text-slate-500 mt-2">Créez un profil pour préremplir rapidement une candidature.</p>
        </div>
    <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($presets as $p): ?>
                <?php
                $pay = $p['payload'] ?? [];
                $rp = is_array($pay) && is_array($pay['rp'] ?? null) ? $pay['rp'] : [];
                $char = trim((string) ($rp['character_name'] ?? ''));
                ?>
                <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                    <div>
                        <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($p['label'] ?? '')) ?></p>
                        <?php if ($char !== ''): ?>
                            <p class="text-sm text-slate-600 mt-0.5">RP : <?= htmlspecialchars($char) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-slate-500 mt-0.5">Modifié <?= !empty($p['updated_at']) ? htmlspecialchars((string) $p['updated_at']) : '—' ?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="<?= url('account/recruitment-presets/' . (int) ($p['id'] ?? 0) . '/edit') ?>" class="text-sm font-semibold text-slate-700 hover:text-slate-900 underline">Modifier</a>
                        <form method="post" action="<?= url('account/recruitment-presets/' . (int) ($p['id'] ?? 0) . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer ce profil ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="text-sm text-rose-600 hover:text-rose-800 font-semibold">Supprimer</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('account') ?>" class="underline">Retour aux paramètres</a></p>
</div>
