<?php
$user = $user ?? null;
$userProfile = $userProfile ?? null;
$completenessAccount = $completenessAccount ?? ($completeness ?? ['score' => 0, 'missing' => [], 'sections_critiques' => []]);
$completenessPersonnel = $completenessPersonnel ?? null;
$isServiceAccount = $isServiceAccount ?? false;
$roles = $roles ?? [];
$grades = $grades ?? [];
if (!$user) {
    echo '<p>Utilisateur introuvable.</p>';
    return;
}
$uid = (int) $user['id'];
$personnelEditUrl = url('personnel/' . $uid . '/edit');
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Fiche utilisateur</h1>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 bg-blue-700 text-white text-sm font-semibold rounded hover:bg-blue-800">Fiche personnelle (personnage)</a>
            <a href="<?= url('back-office/users/' . $uid . '/edit') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Compte administratif</a>
            <?php if (($user['status'] ?? '') !== 'inactive' && !$isServiceAccount): ?>
            <form method="post" action="<?= url('back-office/users/' . $uid . '/deactivate') ?>" class="inline" onsubmit="return confirm('Désactiver cet utilisateur ?');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="px-4 py-2 bg-rose-100 text-rose-800 text-sm font-semibold rounded hover:bg-rose-200">Désactiver</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php $flashOk = \App\Core\Session::getFlash('success'); $flashErr = \App\Core\Session::getFlash('error'); ?>
    <?php if ($flashOk): ?><div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm"><?= htmlspecialchars((string) $flashOk) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm"><?= htmlspecialchars((string) $flashErr) ?></div><?php endif; ?>

    <?php if ($isServiceAccount): ?>
    <div class="mb-6 p-4 rounded-lg bg-slate-100 border border-slate-200 text-sm text-slate-700">
        <strong>Compte technique</strong> — réservé à la modération automatique et aux traitements système. Il n’a pas de fiche « personnage » jouable.
    </div>
    <?php endif; ?>

    <?php if (!$isServiceAccount && ($completenessAccount['score'] ?? 100) < 100 || ($completenessPersonnel !== null && ($completenessPersonnel['score'] ?? 100) < 100)): ?>
    <div class="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50/80">
        <p class="font-semibold text-amber-900 mb-2">Profil incomplet — rappel par courriel</p>
        <p class="text-sm text-amber-900/90 mb-3">Un message est envoyé à l’adresse du compte avec un lien direct vers la fiche personnelle (identité opérationnelle, affectation, clearance…).</p>
        <form method="post" action="<?= url('back-office/users/' . $uid . '/notify-profile') ?>" class="inline">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="px-4 py-2 bg-amber-700 text-white text-sm font-semibold rounded hover:bg-amber-800">Envoyer un rappel par e-mail</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="grid gap-6 md:grid-cols-2 mb-8">
        <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-3">Compte administratif</h2>
            <p class="text-xs text-slate-500 mb-4">Authentification, rôle communauté, identité civile liée au compte.</p>
            <?php if (($completenessAccount['score'] ?? 100) < 100): ?>
            <p class="text-sm font-semibold text-amber-800 mb-2">Complétude : <?= (int) $completenessAccount['score'] ?>%</p>
            <?php if (!empty($completenessAccount['missing'])): ?>
            <ul class="text-sm text-amber-900 list-disc list-inside mb-4">
                <?php foreach ($completenessAccount['missing'] as $m): ?>
                <li><?= htmlspecialchars($m['label'] ?? '') ?><?= (($m['level'] ?? '') === 'blocking') ? ' <span class="text-rose-600">(bloquant)</span>' : '' ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php else: ?>
            <p class="text-sm text-emerald-700 font-medium mb-4">Compte : informations minimales présentes.</p>
            <?php endif; ?>
            <dl class="space-y-2 text-sm">
                <dt class="text-slate-500">Email</dt>
                <dd><?= htmlspecialchars($user['email']) ?></dd>
                <dt class="text-slate-500">Nom d'affichage (compte)</dt>
                <dd><?= htmlspecialchars($user['display_name'] ?? '—') ?></dd>
                <dt class="text-slate-500">Indicatif (compte)</dt>
                <dd><?= htmlspecialchars($user['callsign'] ?? '—') ?></dd>
                <dt class="text-slate-500">Prénom</dt>
                <dd><?= htmlspecialchars($userProfile['first_name'] ?? '—') ?></dd>
                <dt class="text-slate-500">Nom</dt>
                <dd><?= htmlspecialchars($userProfile['last_name'] ?? '—') ?></dd>
                <dt class="text-slate-500">Rôle</dt>
                <dd><?= htmlspecialchars($user['role_name'] ?? '—') ?></dd>
                <dt class="text-slate-500">Statut</dt>
                <dd><span class="px-2 py-0.5 text-xs rounded <?= ($user['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= htmlspecialchars($user['status'] ?? '—') ?></span></dd>
            </dl>
        </div>

        <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500 mb-3">Personnage & dossier opérationnel</h2>
            <p class="text-xs text-slate-500 mb-4">Nom RP, affectation, clearance, qualifications — distinct du compte de connexion.</p>
            <?php if ($isServiceAccount): ?>
            <p class="text-sm text-slate-500">Non applicable.</p>
            <?php elseif ($completenessPersonnel !== null): ?>
                <?php if (($completenessPersonnel['score'] ?? 100) < 100): ?>
                <p class="text-sm font-semibold text-amber-800 mb-2">Complétude fiche : <?= (int) $completenessPersonnel['score'] ?>%</p>
                <?php if (!empty($completenessPersonnel['sections_critiques'])): ?>
                <p class="text-xs font-semibold text-rose-700 mb-1">Points critiques :</p>
                <ul class="text-sm text-rose-800 list-disc list-inside mb-3">
                    <?php foreach ($completenessPersonnel['sections_critiques'] as $c): ?>
                    <li><?= htmlspecialchars($c) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (!empty($completenessPersonnel['missing_labels'])): ?>
                <p class="text-xs font-semibold text-slate-600 mb-1">À compléter :</p>
                <ul class="text-sm text-slate-700 list-disc list-inside mb-4">
                    <?php foreach ($completenessPersonnel['missing_labels'] as $lbl): ?>
                    <li><?= htmlspecialchars($lbl) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-sm text-emerald-700 font-medium mb-4">Fiche : éléments principaux renseignés.</p>
                <?php endif; ?>
            <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-lg hover:bg-slate-800">Éditer la fiche personnelle</a>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-sm text-slate-500">
        <a href="<?= url('back-office/users') ?>" class="underline">Retour à la liste</a>
    </p>
</div>
