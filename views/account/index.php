<?php
$baseUrl = url('');
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Mon compte</h1>
    <p class="text-slate-600 mb-8">Gérez vos préférences, votre adresse email, votre photo et votre mot de passe.</p>
    <nav class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="<?= url('account/preferences') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Préférences</span>
            <p class="text-sm text-slate-500 mt-1">Nom d'affichage, indicatif, fuseau horaire, langue</p>
        </a>
        <a href="<?= url('account/mail') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Adresse email</span>
            <p class="text-sm text-slate-500 mt-1">Modifier votre adresse de connexion</p>
        </a>
        <a href="<?= url('account/image') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Photo de profil</span>
            <p class="text-sm text-slate-500 mt-1">Changer votre avatar</p>
        </a>
        <a href="<?= url('account/password') ?>" class="block p-4 border border-slate-200 rounded-lg hover:bg-slate-50">
            <span class="font-semibold text-slate-900">Mot de passe</span>
            <p class="text-sm text-slate-500 mt-1">Modifier votre mot de passe</p>
        </a>
    </nav>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
