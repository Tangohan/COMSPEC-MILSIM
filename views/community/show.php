<?php
/** @var array $tenant */
/** @var array $memberships */
/** @var array $communityConfig */
$slug = $tenant['slug'] ?? '';
$name = $tenant['name'] ?? '';
$communityConfig = $communityConfig ?? [];
$isLocked = !empty($communityConfig['community_locked']);
$registrationMode = ($communityConfig['registration_mode'] ?? 'milsim') === 'simple' ? 'Simple' : 'MilSim complet';
$welcomeText = trim((string) ($communityConfig['welcome_text'] ?? ''));
$communityCode = trim((string) ($tenant['community_code'] ?? ''));
?>
<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mb-2"><?= htmlspecialchars($name) ?></h1>
    <p class="text-sm text-slate-500 mb-2">Slug : <code class="bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($slug) ?></code></p>
    <?php if ($communityCode !== ''): ?>
        <p class="text-sm text-slate-600 mb-8">Code à partager : <code class="bg-emerald-50 text-emerald-900 px-2 py-0.5 rounded font-mono font-bold"><?= htmlspecialchars($communityCode) ?></code>
            · <a href="<?= url('join') ?>?code=<?= rawurlencode($communityCode) ?>" class="text-emerald-700 underline text-xs font-semibold">Page rejoindre</a></p>
    <?php else: ?>
        <p class="text-sm text-slate-500 mb-8">Code communauté : <span class="text-slate-400">non défini (admin organisation)</span></p>
    <?php endif; ?>

    <?php if ($welcomeText !== ''): ?>
        <p class="text-sm text-slate-600 mb-4"><?= nl2br(htmlspecialchars($welcomeText)) ?></p>
    <?php endif; ?>
    <p class="text-xs text-slate-500 mb-8">Recrutement : <strong><?= htmlspecialchars($registrationMode) ?></strong> · Statut : <strong><?= $isLocked ? 'Verrouillé' : 'Ouvert' ?></strong></p>

    <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('c/' . $slug . '/forum')) ?>" class="inline-flex items-center px-4 py-2.5 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-emerald-700">Accéder au forum</a>
        <?php if (!$isLocked): ?>
            <a href="<?= htmlspecialchars(url('c/' . $slug . '/enlistment')) ?>" class="inline-flex items-center px-4 py-2.5 border border-slate-300 text-xs font-bold uppercase rounded-xl hover:bg-slate-50">Inscription</a>
        <?php endif; ?>
        <?php if (!\App\Core\Session::get('user_id')): ?>
            <a href="<?= url('login') ?>" class="inline-flex items-center px-4 py-2.5 border border-slate-300 text-xs font-bold uppercase rounded-xl hover:bg-slate-50">Connexion</a>
        <?php endif; ?>
        <a href="<?= url('') ?>" class="inline-flex items-center px-4 py-2.5 text-xs font-bold uppercase text-slate-500">Accueil</a>
    </div>

    <?php if (!empty($memberships)): ?>
        <p class="mt-8 text-xs text-slate-600">Vos adhésions avec le même e-mail : utilisez le tableau de bord pour changer de communauté active.</p>
    <?php endif; ?>
</div>
