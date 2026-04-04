<?php
/** @var list<array<string, mixed>> $invitations */
/** @var list<array<string, mixed>> $rolesCommunity */
/** @var bool $canAdd */
/** @var string $inviteFilterStatus */
$inviteFilterStatus = $inviteFilterStatus ?? '';
$rolesCommunity = $rolesCommunity ?? [];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Invitations</h1>
        <a href="<?= url('admin/organization') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <?php if (!$canAdd): ?>
        <p class="text-amber-700 text-sm mb-4">Limite de membres atteinte pour ce plan — passez à une offre supérieure pour inviter davantage.</p>
    <?php endif; ?>
    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>

    <?php if ($canAdd && empty($rolesCommunity)): ?>
        <p class="text-amber-800 text-sm mb-6 border border-amber-200 rounded-lg p-4">Aucun rôle de gouvernance communauté disponible. Exécutez les migrations ou vérifiez la configuration des rôles.</p>
    <?php endif; ?>

    <?php if ($canAdd && !empty($rolesCommunity)): ?>
    <form method="post" action="<?= url('admin/organization/invitations') ?>" class="mb-10 border border-slate-200 rounded-lg p-4 space-y-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div class="flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-slate-500 mb-1">Email</label>
                <input type="email" name="email" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Rôle de communauté (obligatoire)</label>
            <p class="text-xs text-slate-500 mb-2">Gouvernance de l’espace (ex. administrateur organisation). Les rôles site/plateforme ne sont pas proposés ici.</p>
            <select name="role_id" required class="border border-slate-300 rounded px-3 py-2 text-sm max-w-md">
                <?php foreach ($rolesCommunity as $r): ?>
                    <option value="<?= (int) ($r['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($r['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="rounded-md bg-slate-50 border border-slate-100 p-3">
            <p class="text-xs font-semibold text-slate-700 mb-1">Rôles opérationnels (intra-communauté)</p>
            <p class="text-xs text-slate-600">L’affectation multi-rôles métier (ex. officier, membre) sera gérée dans une prochaine étape. Pour l’instant, utilisez le profil utilisateur après acceptation.</p>
        </div>
        <p class="text-xs text-slate-500">Un rôle métier comme « Officier » n’accorde pas l’administration plateforme — il s’agit de fonctions internes à la communauté.</p>
        <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded">Envoyer</button>
    </form>
    <?php endif; ?>

    <form method="get" action="<?= url('admin/organization/invitations') ?>" class="flex flex-wrap items-end gap-2 mb-4">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Statut</label>
            <select name="status" class="border border-slate-300 rounded px-3 py-2 text-sm" onchange="this.form.submit()">
                <option value="">Tous</option>
                <option value="pending" <?= $inviteFilterStatus === 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="accepted" <?= $inviteFilterStatus === 'accepted' ? 'selected' : '' ?>>Acceptée</option>
                <option value="revoked" <?= $inviteFilterStatus === 'revoked' ? 'selected' : '' ?>>Révoquée</option>
                <option value="expired" <?= $inviteFilterStatus === 'expired' ? 'selected' : '' ?>>Expirée</option>
            </select>
        </div>
        <a href="<?= url('admin/organization/invitations') ?>" class="text-sm text-slate-600 hover:underline pb-2">Réinitialiser</a>
    </form>

    <ul class="divide-y divide-slate-200 border border-slate-200 rounded-lg">
        <?php foreach ($invitations as $i): ?>
            <li class="px-4 py-3 flex justify-between items-center text-sm">
                <div>
                    <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($i['email'] ?? '')) ?></span>
                    <span class="text-slate-500 ml-2"><?= htmlspecialchars((string) ($i['status'] ?? '')) ?></span>
                </div>
                <?php if (($i['status'] ?? '') === 'pending'): ?>
                <form method="post" action="<?= url('admin/organization/invitations/revoke') ?>" onsubmit="return confirm('Révoquer ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) ($i['id'] ?? 0) ?>">
                    <button type="submit" class="text-red-600 text-xs underline">Révoquer</button>
                </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (empty($invitations)): ?>
            <li class="px-4 py-6 text-slate-500 text-sm">Aucune invitation.</li>
        <?php endif; ?>
    </ul>
</div>
