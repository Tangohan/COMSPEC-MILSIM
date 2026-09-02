<?php
declare(strict_types=1);
$gate = \App\Core\Gate::getInstance();
if (!$gate->allows('admin.system')) {
    return;
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$item = static function (string $href, string $title, string $desc) use ($h): void {
    echo '<li><a href="' . $h($href) . '"><strong>' . $h($title) . '</strong><span>' . $h($desc) . '</span></a></li>';
};
?>
<section id="hub-annuaire" class="pa-map scroll-mt-24" aria-labelledby="hub-annuaire-heading">
    <div class="pa-map__head">
        <div>
            <p class="pa-map__kicker">Administration complète du site</p>
            <h2 id="hub-annuaire-heading" class="pa-map__title">Quatre postes, tout le site</h2>
            <p class="pa-map__lead">Chaque ligne ouvre l’écran correspondant. La vie d’une communauté précise reste dans son back-office.</p>
        </div>
        <a class="pa-btn pa-btn--ink" href="<?= $h(url('admin/tenants')) ?>">Annuaire des communautés</a>
    </div>

    <div class="pa-map__grid">
        <article class="pa-map__col">
            <div class="pa-map__col-head">
                <h3>Communautés et accès</h3>
                <p>Organisations, formules et portes d’entrée du site.</p>
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
                <h3>Comptes et sécurité</h3>
                <p>Identités, habilitations et mesures à l’échelle du site.</p>
            </div>
            <ul class="pa-map__list">
                <?php
                $item(url('admin/users'), 'Comptes utilisateurs', 'Activation et désactivation');
                $item(url('admin/roles'), 'Rôles système', 'Habilitations du site');
                $item(url('admin/site-roles'), 'Affectations rôles site', 'Qui tient un rôle plateforme');
                $item(url('admin/system/blocklist'), 'Liste de restriction', 'Blocages sur tout le site');
                $item(url('admin/system/member-sanctions'), 'Sanctions du site', 'Mesures, toutes communautés');
                $item(url('admin/system/advanced-fiche-edit'), 'Édition avancée de fiche', 'Modification exceptionnelle');
                ?>
            </ul>
        </article>

        <article class="pa-map__col">
            <div class="pa-map__col-head">
                <h3>Communication et référentiels</h3>
                <p>Messages du site et catalogues partagés.</p>
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
                <h3>Exploitation du site</h3>
                <p>Réglages, publications, journaux et travaux.</p>
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
                ?>
            </ul>
        </article>
    </div>
</section>
