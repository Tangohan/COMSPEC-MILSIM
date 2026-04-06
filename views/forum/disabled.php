<?php
declare(strict_types=1);

$baseUrl = url('');
$name = htmlspecialchars($forumConfig['name'] ?? 'Forum', ENT_QUOTES, 'UTF-8');
$closureLevel = (string) ($briefClosureLevel ?? '');
$extraMsg = trim((string) ($briefClosedMessageText ?? ''));
$canReopen = function_exists('can') && (
    can('admin.organization')
    || can('admin.access')
    || can('admin.system')
    || can('forum.categories.manage')
    || can('forum.manage_categories')
);
$forumSettingsUrl = url('back-office/ressources/forum-config');
$platformBriefUrl = url('admin/system/brief');
?>
<div class="mx-auto max-w-xl px-4 py-14 sm:py-20 text-center">
    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400"><?= $name ?></p>
    <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Brief indisponible pour les membres</h1>
    <p class="mt-4 text-sm leading-relaxed text-slate-600">
        <?php if ($closureLevel === 'platform'): ?>
        L’accès à la salle de brief est temporairement suspendu <strong class="font-semibold text-slate-800">pour toutes les communautés</strong> (maintenance ou consigne transverse). Les personnes habilitées à la modération du forum ou au back-office peuvent encore consulter le brief pour préparer la réouverture.
        <?php else: ?>
        L’accès au brief est restreint pour votre profil. Les personnes habilitées à la modération ou au back-office peuvent encore y accéder.
        <?php endif; ?>
    </p>
    <?php if ($extraMsg !== ''): ?>
    <div class="mt-6 rounded-xl border border-slate-200 bg-white px-5 py-4 text-left text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"><?= nl2br(htmlspecialchars($extraMsg, ENT_QUOTES, 'UTF-8')) ?></div>
    <?php endif; ?>
    <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/90 px-5 py-4 text-left text-sm text-slate-700">
        <p class="font-bold text-slate-900">Où agir selon votre rôle ?</p>
        <p class="mt-2 leading-relaxed">
            <strong class="font-semibold text-slate-800">Réglage pour tout le site</strong> : réservé aux super-administrateurs (fermeture ou réouverture globale du brief).
        </p>
        <p class="mt-3 leading-relaxed">
            <strong class="font-semibold text-slate-800">Réglage pour votre unité</strong> : masquer uniquement la section « unité » dans le brief tout en laissant les canaux généraux ouverts — configuration forum du back-office de la communauté.
        </p>
    </div>
    <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center flex-wrap">
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/" class="inline-flex w-full justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">Retour au portail</a>
        <?php if ($canReopen && function_exists('can') && can('admin.system')): ?>
        <a href="<?= htmlspecialchars($platformBriefUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full justify-center rounded-xl border border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-950 hover:bg-amber-100 sm:w-auto">Réglage brief (plateforme)</a>
        <?php endif; ?>
        <?php if ($canReopen): ?>
        <a href="<?= htmlspecialchars($forumSettingsUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex w-full justify-center rounded-xl border border-emerald-200 bg-white px-5 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-50 sm:w-auto">Configuration forum (unité)</a>
        <?php endif; ?>
    </div>
    <?php if (!$canReopen): ?>
    <p class="mt-8 text-xs leading-relaxed text-slate-500">
        Pour la réouverture ou les consignes, contactez votre encadrement ou une personne administratrice de l’unité.
    </p>
    <?php endif; ?>
</div>
