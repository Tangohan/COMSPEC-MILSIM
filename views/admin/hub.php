<?php
$canSystem = $canSystem ?? false;
$canOrganization = $canOrganization ?? false;
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Choisir un espace d’administration</h1>
    <p class="text-slate-600 mb-8 max-w-2xl">Deux périmètres distincts : le <strong class="font-semibold text-slate-800">site entier</strong> (opérateurs plateforme) et le <strong class="font-semibold text-slate-800">back-office de votre communauté</strong> (membres, unités, modules métier scopés au tenant).</p>

    <div class="grid md:grid-cols-2 gap-6">
        <?php if ($canSystem): ?>
        <a href="<?= url('admin') ?>" class="block p-6 rounded-xl border-2 border-amber-200 bg-amber-50/50 hover:bg-amber-50 hover:border-amber-400 transition-all shadow-sm hover:shadow-md group">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-lg bg-amber-500/20 flex items-center justify-center text-amber-700 group-hover:bg-amber-500/30 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </span>
                <h2 class="text-lg font-black text-slate-900">Plateforme (/admin)</h2>
            </div>
            <p class="text-sm text-slate-600">Tenants, rôles site, paramètres applicatifs transverses, maintenance BDD, audit global. Réservé aux super-administrateurs.</p>
        </a>
        <?php endif; ?>

        <?php if ($canOrganization): ?>
        <a href="<?= url('back-office') ?>" class="block p-6 rounded-xl border-2 border-emerald-200 bg-emerald-50/50 hover:bg-emerald-50 hover:border-emerald-400 transition-all shadow-sm hover:shadow-md group">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-700 group-hover:bg-emerald-500/30 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </span>
                <h2 class="text-lg font-black text-slate-900">Communauté (/back-office)</h2>
            </div>
            <p class="text-sm text-slate-600">Membres, invitations, unités, rôles communautaires, recrutement, événements. Les modules forum / LMS / modpacks s’y rattachent (session tenant).</p>
        </a>
        <?php endif; ?>
    </div>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('dashboard') ?>" class="underline">Retour au dashboard</a></p>
</div>
