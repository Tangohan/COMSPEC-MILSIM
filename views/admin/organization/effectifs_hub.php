<?php
declare(strict_types=1);

$canRolesList = $canRolesList ?? false;
$canRolesCanvas = $canRolesCanvas ?? false;
$canPresets = $canPresets ?? false;
$canGrades = $canGrades ?? false;
$canStructure = $canStructure ?? false;
$canStructureRecruitmentHub = $canStructureRecruitmentHub ?? false;
$canSeniorityAdmin = $canSeniorityAdmin ?? false;

/** @var list<array{title: string, desc: string, href: string, ok: bool}> $hubCards */
$hubCards = [
    [
        'title' => 'Structure & recrutement',
        'desc' => 'Organigramme interactif, invitations de membres et création de regroupements ou d’équipes au même endroit.',
        'href' => url('back-office/organisation/structure'),
        'ok' => $canStructureRecruitmentHub,
    ],
    [
        'title' => 'Rôles et droits de la communauté',
        'desc' => 'Consultez les rôles de gouvernance et opérationnels, et le détail des habilitations associées à chacun.',
        'href' => url('back-office/roles'),
        'ok' => $canRolesList,
    ],
    [
        'title' => 'Toile des rôles et des fonctions',
        'desc' => 'Visualisez comment les rôles de votre communauté se relient entre eux, à partir du référentiel des fonctions.',
        'href' => url('back-office/roles-functions'),
        'ok' => $canRolesCanvas,
    ],
    [
        'title' => 'Profils de permissions prédéfinis',
        'desc' => 'Appliquez en une fois un ensemble cohérent de droits (membre actif, formation, recrutement, etc.) sur un rôle existant.',
        'href' => url('back-office/roles/presets'),
        'ok' => $canPresets,
    ],
    [
        'title' => 'Référentiel des grades',
        'desc' => 'Parcourez et ajustez les grades communs (français, américains) utilisés sur les profils.',
        'href' => url('back-office/referentiels/grades'),
        'ok' => $canGrades,
    ],
    [
        'title' => 'Unités et regroupements',
        'desc' => 'Gérez les regroupements structurels affichés dans l’organigramme (compagnies, pelotons, etc.).',
        'href' => url('back-office/groups'),
        'ok' => $canStructure,
    ],
    [
        'title' => 'Équipes',
        'desc' => 'Créez et gérez les équipes transverses (missions, spécialités) indépendamment de l’organigramme principal.',
        'href' => url('back-office/teams'),
        'ok' => $canStructure,
    ],
    [
        'title' => 'Rôles métier (fiches personnel)',
        'desc' => 'Définissez les intitulés de fonction portés sur les dossiers (ex. radio, médic, logistique), distincts des rôles d’administration.',
        'href' => url('back-office/personnel-job-roles'),
        'ok' => $canStructure,
    ],
    [
        'title' => 'Attributions des rôles métier',
        'desc' => 'Associez ces fonctions aux membres et contrôlez qui peut exercer quelles missions sur le terrain ou en support.',
        'href' => url('back-office/personnel-job-roles/assignments'),
        'ok' => $canStructure,
    ],
    [
        'title' => 'Ancienneté affichée aux membres',
        'desc' => 'Activez les indicateurs (ancienneté dans la communauté, périodes cumulées, etc.) et lancez l’installation des réglages standards si la fiche reste vide.',
        'href' => url('back-office/organisation/anciennete'),
        'ok' => $canSeniorityAdmin,
    ],
];
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">
        <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 mb-2">Back-office communauté</p>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Organisation des effectifs</h1>
            <p class="mt-3 text-sm sm:text-base text-slate-600 max-w-3xl leading-relaxed">
                Centralisez la configuration utile aux fiches personnel, à la structure et aux habilitations au sein de votre communauté.
                Les réglages réservés à l’ensemble de la plateforme restent dans l’administration système.
            </p>
            <p class="mt-4">
                <a href="<?= url('back-office') ?>" class="text-sm font-semibold text-slate-700 underline hover:text-slate-900">Retour au centre de pilotage</a>
            </p>
        </header>

        <section aria-labelledby="eff-cards">
            <h2 id="eff-cards" class="sr-only">Raccourcis</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach ($hubCards as $c):
                    if (!$c['ok']) {
                        continue;
                    }
                    ?>
                <a href="<?= htmlspecialchars($c['href']) ?>" class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
                    <h3 class="text-base font-bold text-slate-900 group-hover:text-blue-800"><?= htmlspecialchars($c['title']) ?></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($c['desc']) ?></p>
                    <p class="mt-3 text-xs font-semibold text-blue-700">Ouvrir →</p>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
