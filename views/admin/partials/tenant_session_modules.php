<?php
declare(strict_types=1);

/**
 * Raccourcis vers les modules métier du tenant de session.
 */
$gate = \App\Core\Gate::getInstance();
$sys = $gate->allows('admin.system');
$org = $gate->allows('admin.organization') || $gate->allows('admin.access');
$support = $gate->allows('site.support');
$training = \App\Support\TrainingLmsStaffAccess::allows($gate);
$docs = $gate->allows('documents.upload') || $gate->allows('admin.access');
$docsView = $gate->allows('documents.view');

$links = [];
if ($sys || $org || $support) {
    $links[] = ['href' => url('back-office'), 'label' => 'Tableau de bord communauté', 'desc' => 'Membres, unités, recrutement, configuration — périmètre organisationnel'];
}
if ($sys || $org || $support) {
    $links[] = ['href' => url('back-office/ressources/modpacks'), 'label' => 'Modpacks', 'desc' => 'Packs mods de la communauté sélectionnée'];
    $links[] = ['href' => url('back-office/ressources/atak-config'), 'label' => 'Configuration ATAK / Tacmap', 'desc' => 'Carte et paramètres tactiques pour cette communauté'];
    $links[] = ['href' => url('back-office/ressources/atak-mod-blocks'), 'label' => 'Restrictions d’accès au mod', 'desc' => 'Bloquer un Steam ou une adresse réseau côté Overwatch'];
    $links[] = ['href' => url('back-office/ressources/atak-mod'), 'label' => 'Mod ATAK (Overwatch)', 'desc' => 'Fichiers mod côté communauté'];
    $links[] = ['href' => cooperation_mission_index_url(), 'label' => 'Coopérations inter-unités', 'desc' => 'Propositions, validation mutuelle, espace commun sur le brief et coordination opérationnelle'];
}
if ($training) {
    $links[] = ['href' => training_lms_admin_url(), 'label' => 'Formations (LMS)', 'desc' => 'Catalogue, inscriptions et suivi pour cette communauté'];
}
if ($sys || $docs || ($support && $docsView)) {
    $links[] = ['href' => url('documents/gestion'), 'label' => 'Gestion documentaire', 'desc' => 'Bibliothèque documentaire de la communauté courante'];
}

if ($links === []) {
    return;
}
?>
<section class="pa-card" aria-labelledby="tenant-mod-heading">
    <div class="pa-card__head">
        <span class="pa-icon pa-icon--slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        <div>
            <p class="pa-card__kicker">Communauté de votre session</p>
            <h2 id="tenant-mod-heading" class="pa-card__title">Raccourcis vers le back-office</h2>
            <p class="pa-card__help">Ces outils ne s’appliquent qu’à la communauté sélectionnée, pas au site entier.</p>
        </div>
    </div>
    <ul class="pa-tiles">
        <?php foreach ($links as $row): ?>
            <li>
                <a class="pa-tiles__a" href="<?= htmlspecialchars($row['href'], ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (($row['desc'] ?? '') !== ''): ?>
                        <span><?= htmlspecialchars((string) $row['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
