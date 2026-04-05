<?php
$user = $user ?? null;
$userProfile = $userProfile ?? null;
$completeness = $completeness ?? ['score' => 0, 'missing' => [], 'sections_critiques' => []];
$roles = $roles ?? [];
$grades = $grades ?? [];
if (!$user) {
    echo '<p>Utilisateur introuvable.</p>';
    return;
}
$uid = (int) $user['id'];
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Fiche utilisateur</h1>
        <div class="flex gap-2">
            <a href="<?= url('back-office/users/' . $uid . '/edit') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Modifier</a>
            <?php if (($user['status'] ?? '') !== 'inactive'): ?>
            <form method="post" action="<?= url('back-office/users/' . $uid . '/deactivate') ?>" class="inline" onsubmit="return confirm('Désactiver cet utilisateur ?');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="px-4 py-2 bg-rose-100 text-rose-800 text-sm font-semibold rounded hover:bg-rose-200">Désactiver</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($completeness['score'] < 100): ?>
    <div class="mb-6 p-4 rounded-lg <?= !empty($completeness['sections_critiques']) ? 'bg-rose-50 border border-rose-200' : 'bg-amber-50 border border-amber-200' ?>">
        <p class="font-semibold <?= !empty($completeness['sections_critiques']) ? 'text-rose-800' : 'text-amber-800' ?>">
            Profil à compléter : <?= (int) $completeness['score'] ?>%
        </p>
        <?php if (!empty($completeness['missing'])): ?>
        <ul class="mt-2 text-sm <?= !empty($completeness['sections_critiques']) ? 'text-rose-700' : 'text-amber-700' ?>">
            <?php foreach ($completeness['missing'] as $m): ?>
            <li>
                <?= htmlspecialchars($m['label']) ?>
                <?php if ($m['level'] === 'blocking'): ?><span class="text-rose-600 font-medium">(bloquant)</span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="p-4 bg-slate-50 rounded-lg">
            <h2 class="text-sm font-black text-slate-600 uppercase tracking-wider mb-3">Identité</h2>
            <dl class="space-y-2 text-sm">
                <dt class="text-slate-500">Email</dt>
                <dd><?= htmlspecialchars($user['email']) ?></dd>
                <dt class="text-slate-500">Nom d'affichage</dt>
                <dd><?= htmlspecialchars($user['display_name'] ?? '—') ?></dd>
                <dt class="text-slate-500">Indicatif</dt>
                <dd><?= htmlspecialchars($user['callsign'] ?? '—') ?></dd>
                <dt class="text-slate-500">Prénom</dt>
                <dd><?= htmlspecialchars($userProfile['first_name'] ?? '—') ?></dd>
                <dt class="text-slate-500">Nom</dt>
                <dd><?= htmlspecialchars($userProfile['last_name'] ?? '—') ?></dd>
            </dl>
        </div>
        <div class="p-4 bg-slate-50 rounded-lg">
            <h2 class="text-sm font-black text-slate-600 uppercase tracking-wider mb-3">Rôle & statut</h2>
            <dl class="space-y-2 text-sm">
                <dt class="text-slate-500">Rôle</dt>
                <dd><?= htmlspecialchars($user['role_name'] ?? '—') ?></dd>
                <dt class="text-slate-500">Statut</dt>
                <dd><span class="px-2 py-0.5 text-xs rounded <?= ($user['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= htmlspecialchars($user['status'] ?? '—') ?></span></dd>
            </dl>
        </div>
    </div>

    <p class="mt-8 text-sm text-slate-500">
        <a href="<?= url('back-office/users') ?>" class="underline">Retour à la liste</a>
    </p>
</div>
