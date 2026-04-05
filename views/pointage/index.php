<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-500">Présence</p>
        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Pointage &amp; activité</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
            Accès rapides pour marquer votre passage sur le portail : tableau de bord, messages communauté, fiche et briefings.
            Les outils de présence détaillés (événements, ORBAT) sont accessibles depuis les modules ci-dessous.
        </p>
    </div>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2">
        <?php
        $tiles = [
            ['label' => 'Tableau de bord', 'path' => 'dashboard', 'desc' => 'Vue d’ensemble, modpack, raccourcis.', 'tag' => 'Vue'],
            ['label' => 'Hub opérationnel', 'path' => 'hub', 'desc' => 'Sélection des modules et accès mission.', 'tag' => 'Ops'],
            ['label' => 'Messages', 'path' => 'messages', 'desc' => 'Fil d’annonces et messages de la communauté active.', 'tag' => 'Comm'],
            ['label' => 'Ma fiche', 'path' => 'personnel/me', 'desc' => 'Profil opérateur et dossier personnel.', 'tag' => 'RH'],
            ['label' => 'Forum', 'path' => 'forum', 'desc' => 'Briefings et discussions.', 'tag' => 'Info'],
            ['label' => 'Événements', 'path' => 'evenements', 'desc' => 'RSVP et agenda communautaire.', 'tag' => 'Agenda'],
            ['label' => 'Registre communautés', 'path' => 'communities', 'desc' => 'Changer d’unité ou parcourir les organisations.', 'tag' => 'Unités'],
            ['label' => 'Recherche', 'path' => 'search', 'desc' => 'Recherche globale sur le portail.', 'tag' => 'Tout'],
        ];
        foreach ($tiles as $t):
            $href = htmlspecialchars(url($t['path']));
        ?>
        <li>
            <a href="<?= $href ?>" class="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"><?= htmlspecialchars($t['tag']) ?></span>
                <span class="mt-2 text-lg font-black text-slate-950"><?= htmlspecialchars($t['label']) ?></span>
                <span class="mt-2 text-sm text-slate-600"><?= htmlspecialchars($t['desc']) ?></span>
                <span class="mt-4 text-[11px] font-bold text-emerald-700 group-hover:underline">Ouvrir →</span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
