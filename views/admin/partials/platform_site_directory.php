<?php
declare(strict_types=1);
$gate = \App\Core\Gate::getInstance();
if (!$gate->allows('admin.system')) {
    return;
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$item = static function (string $href, string $title, string $desc) use ($h): void {
    echo '<li><a class="pa-map__link" href="' . $h($href) . '"><strong>' . $h($title) . '</strong><span>' . $h($desc) . '</span></a></li>';
};
?>
<section id="hub-annuaire" class="pa-map scroll-mt-24" aria-labelledby="hub-annuaire-heading">
    <div class="pa-map__head">
        <div>
            <p class="pa-map__kicker">Administration complète du site</p>
            <h2 id="hub-annuaire-heading" class="pa-map__title">Tous les outils du site</h2>
            <p class="pa-map__lead">Quatre familles d’outils, chacune ouvre l’écran correspondant. La vie d’une communauté précise reste dans son back-office.</p>
        </div>
    </div>

    <div class="pa-map__grid">
        <article class="pa-map__col">
            <div class="pa-map__col-head">
                <span class="pa-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></span>
                <div>
                <h3>Communautés et accès</h3>
                <p>Organisations, formules et portes d’entrée du site.</p>
                </div>
            </div>
            <ul class="pa-map__list">
                <?php
                $item(url('admin/tenants'), 'Annuaire des communautés', 'Nom, profil, formule et effectif');
                $item(url('admin/system/tenant-recovery'), 'Récupération communauté', 'Recréer une communauté orpheline depuis une sauvegarde');
                $item(url('communities/create'), 'Créer une communauté', 'Parcours de création sur le site');
                $item(url('admin/system/subscription-plans'), 'Formules d’accès', 'Paliers, quotas et modules');
                $item(url('admin/system/demo-nda'), 'Accès démo du site', 'Code, durées et adresses autorisées');
                $item(url('admin/newsletter'), 'Lettre d’information', 'Inscriptions publiques et contacts');
                $item(url('communities'), 'Annuaire public', 'Ce que voient les visiteurs');
                ?>
            </ul>
        </article>

        <article class="pa-map__col">
            <div class="pa-map__col-head">
                <span class="pa-icon pa-icon--violet" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                <div>
                <h3>Comptes et sécurité</h3>
                <p>Identités, habilitations et mesures à l’échelle du site.</p>
                </div>
            </div>
            <ul class="pa-map__list">
                <?php
                $item(url('admin/users'), 'Comptes utilisateurs', 'Activation et désactivation');
                $item(url('admin/roles'), 'Rôles système', 'Habilitations du site');
                $item(url('admin/site-roles'), 'Affectations rôles site', 'Qui tient un rôle plateforme');
                $item(url('admin/system/blocklist'), 'Liste de restriction', 'Blocages sur tout le site');
                $item(url('admin/system/member-sanctions'), 'Sanctions du site', 'Mesures, toutes communautés');
                $item(url('admin/system/noms-inappropries'), 'Noms inappropriés', 'Mots floutés, alertes de noms');
                $item(url('admin/system/advanced-fiche-edit'), 'Édition avancée de fiche', 'Modification exceptionnelle');
                ?>
            </ul>
        </article>

        <article class="pa-map__col">
            <div class="pa-map__col-head">
                <span class="pa-icon pa-icon--blue" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></span>
                <div>
                <h3>Communication et référentiels</h3>
                <p>Messages du site et catalogues partagés.</p>
                </div>
            </div>
            <ul class="pa-map__list">
                <?php
                $item(url('admin/system/alerts'), 'Alertes plateforme', 'Messages visibles partout');
                $item(url('admin/system/brief'), 'Brief membres', 'Accès au brief pour les comptes');
                $item(url('admin/system/cooperation/catalog'), 'Types de coopération', 'Formats d’échange entre unités');
                $item(url('admin/system/cooperation/announcements'), 'Annonces de coopération', 'Textes par défaut');
                $item(url('admin/system/military-referential'), 'Référentiel militaire', 'Unités et affiliations');
                $item(url('admin/system/recruitment-portal-tools'), 'Portail candidatures', 'Filtres, relances, accès exceptionnels');
                ?>
            </ul>
        </article>

        <article class="pa-map__col">
            <div class="pa-map__col-head">
                <span class="pa-icon pa-icon--amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                <div>
                <h3>Exploitation du site</h3>
                <p>Réglages, publications, journaux et travaux.</p>
                </div>
            </div>
            <ul class="pa-map__list">
                <?php
                $item(url('admin/settings'), 'Paramètres système', 'Configuration effective');
                $item(url('admin/ops-center'), 'Synthèse opérationnelle', 'Signaux transverses');
                $item(url('admin/system/cron'), 'Tâches automatiques', 'Planification et lancement');
                $item(url('admin/system/updates'), 'Mises à jour', 'Déposer et publier une version');
                $item(url('admin/system/deployment'), 'Publications et canaux', 'Environnements et communautés de test');
                $item(url('admin/system/storage'), 'Espace disque', 'Historiques volumineux');
                $item(url('admin/audit'), 'Journal d’audit', 'Traçabilité de toutes les communautés');
                $item(url('admin/maintenance'), 'Maintenance des données', 'Fenêtre de travaux');
                $item(url('admin/analytics'), 'Indicateurs transverses', 'Usage agrégé');
                $item(url('admin/system/retours-interface'), 'Retours sur l’interface', 'Notes et questionnaires des écrans');
                $item(url('admin/system/avis-plateforme'), 'Avis et traductions', 'Note globale Athena et propositions de formulation');
                ?>
            </ul>
        </article>
    </div>
</section>
