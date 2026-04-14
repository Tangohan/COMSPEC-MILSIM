<?php
declare(strict_types=1);

$boardSchemaReady = $boardSchemaReady ?? true;
$hubReturnRubrique = url('back-office/tableau-operationnel/fiche/nouvelle');

$typeCards = [
    ['slug' => 'permanence', 'title' => 'Permanence', 'desc' => 'Veille, astreinte ou poste fixe sur une période donnée.', 'tone' => 'border-sky-200 bg-sky-50/60 text-sky-950'],
    ['slug' => 'info', 'title' => 'Information pratique', 'desc' => 'Consignes, horaires, contacts ou repères utiles à toute la communauté.', 'tone' => 'border-slate-200 bg-slate-50 text-slate-900'],
    ['slug' => 'manifestation', 'title' => 'Manifestation / dispositif', 'desc' => 'Événement public, rassemblement ou dispositif particulier à suivre.', 'tone' => 'border-violet-200 bg-violet-50/70 text-violet-950'],
    ['slug' => 'mission', 'title' => 'Mission', 'desc' => 'Opération ou ligne d’effort avec objectifs, moyens et responsabilités.', 'tone' => 'border-emerald-200 bg-emerald-50/60 text-emerald-950'],
    ['slug' => 'task', 'title' => 'Tâche interne', 'desc' => 'Suivi d’actions internes, coordination ou préparation.', 'tone' => 'border-amber-200 bg-amber-50/70 text-amber-950'],
    ['slug' => 'formation', 'title' => 'Activité de formation', 'desc' => 'Période pédagogique, stage ou module à afficher sur le mur.', 'tone' => 'border-cyan-200 bg-cyan-50/70 text-cyan-950'],
    ['slug' => 'flash_info', 'title' => 'Flash information', 'desc' => 'Message court et urgent, visible en priorité sur le mur.', 'tone' => 'border-orange-200 bg-orange-50/80 text-orange-950'],
];
?>
<div class="mx-auto max-w-5xl space-y-6 pb-10 px-4">
    <?php if (!$boardSchemaReady): ?>
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-b from-amber-50/40 to-white px-6 py-10 shadow-sm sm:px-8" role="status">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-900">Activation en attente</p>
            <h2 class="mt-2 text-lg font-black tracking-tight text-slate-900 sm:text-xl">La saisie n’est pas disponible pour le moment</h2>
            <p class="mt-4 text-sm leading-relaxed text-slate-600">
                Le tableau opérationnel n’est pas encore activé sur cet environnement. Merci d’en informer la personne ou l’équipe qui administre l’hébergement du site : une étape d’installation prévue avec la version déployée doit encore être réalisée. Ensuite, actualisez cette page.
            </p>
        </div>
    <?php else: ?>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Tableau opérationnel</p>
            <h1 class="mt-1 text-xl font-black text-slate-900">Nouvelle entrée — choisir le type</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">Chaque type ouvre un écran dédié : les textes d’aide et les blocs proposés correspondent à l’activité choisie. Vous pourrez ensuite préciser <strong class="font-semibold text-slate-800">qui</strong> voit la fiche sur le mur (communauté entière, unité, emplois métier, ou brouillon personnel).</p>
        </div>
        <a href="<?= url('back-office/tableau-operationnel') ?>" class="text-sm font-semibold text-emerald-800 underline decoration-emerald-200 underline-offset-2">Retour au mur</a>
    </div>

    <section class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wider text-emerald-950">Ajouter une rubrique de classement</h2>
        <p class="text-xs leading-relaxed text-slate-700">Enregistrez une rubrique ici si besoin, puis ouvrez le type de fiche souhé : la rubrique apparaîtra dans la liste après actualisation de la page de saisie.</p>
        <form method="post" action="<?= url('back-office/tableau-operationnel/rubrique') ?>" class="grid gap-3 md:grid-cols-2">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="redirect_after" value="<?= htmlspecialchars($hubReturnRubrique, ENT_QUOTES, 'UTF-8') ?>">
            <label class="md:col-span-2 block text-xs font-semibold text-slate-700">Nom de la rubrique
                <input type="text" name="category_name" maxlength="120" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2 text-sm" autocomplete="off" placeholder="Ex. : Permanences, Logistique…">
            </label>
            <label class="block text-xs font-semibold text-slate-700">Couleur d’étiquette
                <input type="color" name="category_color" value="#334155" class="mt-1 h-10 w-full max-w-[12rem] cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Enregistrer la rubrique</button>
            </div>
        </form>
    </section>

    <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($typeCards as $card):
            $slug = (string) ($card['slug'] ?? '');
            $href = url('back-office/tableau-operationnel/fiche/nouvelle/' . rawurlencode($slug));
            ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-col rounded-2xl border-2 p-5 shadow-sm transition hover:shadow-md <?= htmlspecialchars((string) ($card['tone'] ?? 'border-slate-200 bg-white'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-slate-700">Fiche dédiée</span>
                <span class="mt-2 text-lg font-black tracking-tight"><?= htmlspecialchars((string) ($card['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="mt-2 flex-1 text-sm leading-relaxed text-slate-700"><?= htmlspecialchars((string) ($card['desc'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="mt-4 text-sm font-bold text-emerald-800 underline decoration-emerald-200 underline-offset-2">Ouvrir ce formulaire →</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
