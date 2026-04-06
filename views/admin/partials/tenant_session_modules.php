<?php
declare(strict_types=1);

/**
 * Raccourcis vers les modules métier du tenant de session.
 * Les URLs canoniques côté navigation sont préfixées /back-office/ressources/ ; les cibles techniques restent sous /admin/… ou /documents/…
 */
$gate = \App\Core\Gate::getInstance();
$sys = $gate->allows('admin.system');
$org = $gate->allows('admin.organization') || $gate->allows('admin.access');
$training = $gate->allows('training.manage') || $gate->allows('training.assign') || $gate->allows('admin.access');
$docs = $gate->allows('documents.upload') || $gate->allows('admin.access');

$links = [];
if ($sys || $org) {
    $links[] = ['href' => url('back-office'), 'label' => 'Tableau de bord communauté', 'desc' => 'Membres, unités, recrutement, configuration — périmètre organisationnel'];
}
if ($sys || $org) {
    $links[] = ['href' => url('back-office/ressources/modpacks'), 'label' => 'Modpacks', 'desc' => 'Packs mods de la communauté sélectionnée'];
    $links[] = ['href' => url('back-office/ressources/atak-config'), 'label' => 'Configuration ATAK / Tacmap', 'desc' => 'Carte et paramètres tactiques pour ce tenant'];
    $links[] = ['href' => url('back-office/ressources/atak-mod'), 'label' => 'Mod ATAK (Overwatch)', 'desc' => 'Fichiers mod côté communauté'];
    $links[] = ['href' => url('back-office/ressources/forum-config'), 'label' => 'Configuration forum', 'desc' => 'Canaux, modération technique, filtres'];
    $links[] = ['href' => url('back-office/ressources/interteam-missions'), 'label' => 'Missions inter-unités', 'desc' => 'Coopération entre communautés et partage ciblé du brief'];
}
if ($sys || $training) {
    $links[] = ['href' => url('back-office/ressources/training'), 'label' => 'Formations (LMS)', 'desc' => 'Catalogue, inscriptions et suivi pour ce tenant'];
}
if ($sys || $docs) {
    $links[] = ['href' => url('documents/gestion'), 'label' => 'Gestion documentaire', 'desc' => 'Bibliothèque documentaire de la communauté courante'];
}

if ($links === []) {
    return;
}
?>
<section class="rounded-2xl border border-emerald-200/90 bg-gradient-to-b from-emerald-50/50 to-white p-5 sm:p-6 shadow-sm" aria-labelledby="tenant-mod-heading">
    <h2 id="tenant-mod-heading" class="text-lg font-bold text-slate-900 mb-1">Raccourcis · modules de la communauté active</h2>
    <p class="text-sm text-slate-600 mb-4 max-w-3xl">
        Ces outils s’appliquent à la <strong class="font-semibold text-slate-800">communauté de votre session</strong>, pas à la plateforme entière.
        Ce n’est <strong class="font-semibold text-slate-800">pas</strong> l’administration globale du site (rôles système, tenants, audit global → section « Modules plateforme » ci-dessus).
        Préfixe d’URL recommandé : <code class="text-xs bg-white/80 px-1.5 py-0.5 rounded border border-emerald-200/80">/back-office/ressources/…</code>
    </p>
    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <?php foreach ($links as $row): ?>
            <li>
                <a href="<?= htmlspecialchars($row['href'], ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-col rounded-xl border border-emerald-100 bg-white/90 px-4 py-3 shadow-sm hover:border-emerald-300 hover:shadow-md transition-all">
                    <span class="font-semibold text-emerald-950 group-hover:underline"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (($row['desc'] ?? '') !== ''): ?>
                        <span class="text-xs text-slate-500 mt-1"><?= htmlspecialchars((string) $row['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
