<?php
/** @var list<array<string, mixed>> $integration_keys */
/** @var string|null $new_integration_key_plain */
$integration_keys = $integration_keys ?? [];
$new_integration_key_plain = $new_integration_key_plain ?? null;
?>
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900">Intégrations</h1>
    <p class="mt-2 text-sm text-slate-600">Clés d’accès pour outils externes (liste des opérations à venir, etc.). Gardez-les confidentielles.</p>

    <?php if ($new_integration_key_plain): ?>
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-sm font-bold text-amber-900">Copiez cette clé maintenant : elle ne sera plus affichée en clair.</p>
        <code class="mt-2 block break-all rounded bg-white p-3 text-xs text-slate-800"><?= htmlspecialchars($new_integration_key_plain, ENT_QUOTES, 'UTF-8') ?></code>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(url('back-office/integrations/api-keys'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="integ_name" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Nom du service</label>
            <input type="text" id="integ_name" name="name" maxlength="120" placeholder="Ex. Site vitrine, outil logistique…" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800">Créer une clé</button>
    </form>

    <section class="mt-10">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Clés existantes</h2>
        <?php if ($integration_keys === []): ?>
            <p class="mt-3 text-sm text-slate-500">Aucune clé pour l’instant.</p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                <?php foreach ($integration_keys as $k): ?>
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($k['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500">Préfixe <?= htmlspecialchars((string) ($k['key_prefix'] ?? ''), ENT_QUOTES, 'UTF-8') ?>… · quota <?= (int) ($k['quota_per_day'] ?? 0) ?> / jour</p>
                        <?php if (!empty($k['revoked_at'])): ?>
                        <span class="text-xs font-bold text-rose-600">Révoquée</span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($k['revoked_at'])): ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/integrations/api-keys/' . (int) ($k['id'] ?? 0) . '/revoke'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Révoquer cette clé ? Les outils qui l’utilisent cesseront de fonctionner.');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-xs font-bold uppercase text-rose-600 hover:text-rose-800">Révoquer</button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <p class="mt-8 text-xs text-slate-500">Documentation technique : <code class="rounded bg-slate-100 px-1">docs/openapi-integrations.yaml</code></p>
</div>
