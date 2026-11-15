<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Authorization\PermissionImplication;
use App\Authorization\SystemReservedPermissions;
use App\Authorization\TenantPermissionCatalog;

/**
 * Métadonnées lisibles des droits éditables sur la page « Niveaux d’accès » :
 * domaine, explication en une ligne, sensibilité, dépendances, et regroupement pour l’écran.
 *
 * Le catalogue des slugs reste {@see TenantPermissionCatalog} ; ce service n’ajoute aucune habilitation.
 * Un test (PermissionCatalogCoverageTest) échoue quand un droit utilisé dans le code n’y est pas déclaré.
 */
final class AccessRightsCatalog
{
    public const SENSITIVITY_STANDARD = 'standard';
    public const SENSITIVITY_ELEVATED = 'elevated';
    public const SENSITIVITY_CRITICAL = 'critical';

    /**
     * Droits testés dans le code mais volontairement absents du catalogue des communautés.
     * Chaque entrée dit pourquoi : alias hérités sans effet propre, ou réservés à la plateforme.
     *
     * @var array<string, string>
     */
    public const NOT_EDITABLE = [
        'admin.system' => 'Réservé à la plateforme (jamais attribuable à une communauté).',
        'site.support' => 'Réservé à la plateforme (rôles du site).',
        'admin.users.manage' => 'Mot-clé de recherche du back-office, pas un droit testé.',
        'recruitment.manage' => 'Mot-clé de recherche du back-office, pas un droit testé.',
        'atak.access' => 'Alias hérité du portail SSE : doublé par atak.sse.access.',
        'atak.sse.cases' => 'Nom de vue du portail SSE, droit réel : atak.sse.access.',
        'sse.access' => 'Alias hérité du tableau JNET : doublé par atak.sse.access.',
        'renseignement.view' => 'Alias hérité du tableau JNET : doublé par atak.sse.access.',
        'documents.create' => 'Alternative historique de doctrine.create, doublée par documents.upload.',
    ];

    /**
     * Domaines affichés, dans l’ordre. Le premier préfixe de slug qui correspond l’emporte (plus long d’abord).
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function domains(): array
    {
        return [
            ['key' => 'members', 'label' => 'Membres et dossiers', 'description' => 'Fiches, grades, affectations, statuts et annuaire. Ce n’est pas l’emploi (radio, médic…).'],
            ['key' => 'recruitment', 'label' => 'Recrutement et intégration', 'description' => 'Candidatures, ouvertures de poste, invitations et suivi des nouveaux arrivants.'],
            ['key' => 'organization', 'label' => 'Organisation et ORBAT', 'description' => 'Structure des unités, visibilité des unités, emplois du référentiel.'],
            ['key' => 'admin', 'label' => 'Administration de la communauté', 'description' => 'Paramètres, niveaux d’accès, intégrations, journaux d’audit et identité visuelle.'],
            ['key' => 'moderation', 'label' => 'Modération', 'description' => 'Traiter les signalements des membres.'],
            ['key' => 'documents', 'label' => 'Documents et doctrine', 'description' => 'Consulter, publier et classer les documents, la doctrine et les consignes.'],
            ['key' => 'training', 'label' => 'Formations et qualifications', 'description' => 'Parcours, corrections, certifications et publications de formation.'],
            ['key' => 'operations', 'label' => 'Opérations', 'description' => 'Missions, ordres, SITREP, AAR, préparation, logistique et mur opérationnel.'],
            ['key' => 'atak', 'label' => 'ATAK : terminaux et liaison', 'description' => 'Terminaux terrain, certificats, configuration et cycle de mission.'],
            ['key' => 'toc', 'label' => 'Poste TOC web', 'description' => 'Poste de commandement web : plans d’opération et envoi en jeu.'],
            ['key' => 'sse', 'label' => 'Renseignement interpersonnel (SSE / JNET)', 'description' => 'Portail de renseignement : dossiers, codes d’accès temporaires, niveaux de diffusion.'],
            ['key' => 'intel', 'label' => 'Renseignement et transmissions', 'description' => 'Sessions de transmission, mini-PV et plan d’exécution.'],
            ['key' => 'cooperation', 'label' => 'Coopération', 'description' => 'Échanges, retours d’expérience et partenariats avec d’autres unités.'],
            ['key' => 'interteam', 'label' => 'Missions entre unités', 'description' => 'Coordination de missions entre unités.'],
            ['key' => 'dashboard', 'label' => 'Tableau de bord', 'description' => 'Raccourcis et tenues du tableau de bord.'],
            ['key' => 'comms', 'label' => 'Communications', 'description' => 'Canaux et outils de communication.'],
            ['key' => 'media', 'label' => 'Médias et photothèque', 'description' => 'Galerie, publications et photos de la communauté.'],
            ['key' => 'courrier', 'label' => 'Courrier', 'description' => 'Courrier interne, validations et traçabilité.'],
            ['key' => 'finances', 'label' => 'Finances', 'description' => 'Budget, cotisations et exports financiers.'],
            ['key' => 'other', 'label' => 'Autres droits', 'description' => 'Droits non classés dans une famille principale.'],
        ];
    }

    /** @return array<string, string> préfixe de slug => clé de domaine */
    private static function prefixDomains(): array
    {
        return [
            'atak.sse.' => 'sse',
            'atak.toc.' => 'toc',
            'member_integration.' => 'recruitment',
            'organization.recruitment.' => 'recruitment',
            'invitations.' => 'recruitment',
            'admin.members.' => 'members',
            'personnel.' => 'members',
            'organization.' => 'organization',
            'tenant.' => 'admin',
            'admin.' => 'admin',
            'moderation.' => 'moderation',
            'documents.' => 'documents',
            'doctrine.' => 'documents',
            'training.' => 'training',
            'operations.' => 'operations',
            'operational.' => 'operations',
            'atak.' => 'atak',
            'intel.' => 'intel',
            'cooperation.' => 'cooperation',
            'interteam.' => 'interteam',
            'dashboard.' => 'dashboard',
            'comms.' => 'comms',
            'media.' => 'media',
            'courrier.' => 'courrier',
            'finances.' => 'finances',
        ];
    }

    public static function domainFor(string $slug, string $module = ''): string
    {
        $slug = strtolower(trim($slug));
        $prefixes = self::prefixDomains();
        uksort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($prefixes as $prefix => $key) {
            if (str_starts_with($slug, $prefix)) {
                return $key;
            }
        }
        $module = strtolower(trim($module));
        foreach (self::domains() as $d) {
            if ($d['key'] === $module) {
                return $module;
            }
        }

        return 'other';
    }

    /** Droits dont l’attribution engage la sécurité de la communauté ou des données personnelles. */
    private const CRITICAL = [
        'admin.access', 'admin.organization', 'admin.roles.manage', 'admin.permissions.manage',
        'admin.settings.manage', 'tenant.configuration.manage', 'admin.integrations.manage',
        'admin.audit.view', 'admin.compliance.export', 'admin.branding.manage', 'admin.members.moderate',
        'personnel.sensitive.view', 'personnel.member_number.manage',
        'atak.sse.grant', 'atak.sse.files.admin', 'atak.sse.case.manage', 'atak.config.manage',
        'atak.command.alert', 'atak.certificates.manage', 'atak.toc.manage',
        'finances.manage', 'finances.export', 'documents.access.manage',
        'organization.manage_unit_visibility', 'personnel.manage_visibility',
    ];

    /** Explications écrites à la main pour les droits qui demandent plus qu’un verbe. */
    private const EXPLAIN = [
        'admin.access' => 'Ouvre l’administration de la communauté. Équivaut à une grande partie des droits d’administration.',
        'admin.organization' => 'Clé de la communauté : paramètres, effectifs, référentiels et la plupart des outils d’organisation.',
        'admin.backoffice.view' => 'Entrer dans le back-office en lecture, sans pouvoir modifier.',
        'admin.roles.manage' => 'Créer, modifier et supprimer les niveaux d’accès, et choisir leurs droits. Ne permet de donner que les droits qu’on détient soi-même.',
        'admin.permissions.manage' => 'Attribuer des droits à titre individuel, hors niveau d’accès.',
        'admin.audit.view' => 'Lire le journal d’audit : qui a fait quoi et quand.',
        'admin.settings.manage' => 'Changer les paramètres de la communauté (nom, vitrine, options).',
        'admin.members.moderate' => 'Suspendre ou restreindre l’activité d’un membre.',
        'admin.compliance.export' => 'Télécharger les dossiers de conformité des formations.',
        'admin.integrations.manage' => 'Gérer les liaisons externes : API, webhooks, Discord.',
        'admin.branding.manage' => 'Changer le logo, les couleurs et l’identité visuelle publique.',
        'tenant.configuration.manage' => 'Faire évoluer l’offre et les modules activés de la communauté.',
        'invitations.send' => 'Envoyer des invitations pour rejoindre la communauté.',
        'personnel.sensitive.view' => 'Voir les données sensibles d’une fiche : coordonnées, pièces d’identité, situation personnelle.',
        'personnel.member_number.manage' => 'Attribuer ou corriger les numéros de membre.',
        'personnel.directory.export' => 'Télécharger l’annuaire des membres hors du portail.',
        'personnel.profile.view' => 'Ouvrir la fiche d’un membre (sans les données sensibles).',
        'personnel.manage_visibility' => 'Voir et gérer les fiches masquées ou restreintes.',
        'organization.manage_unit_visibility' => 'Masquer ou restreindre des unités dans l’ORBAT.',
        'organization.orbat.manage' => 'Modifier la structure des unités ; donne aussi la lecture des unités masquées.',
        'organization.effectifs.hub.view' => 'Ouvrir le bureau Effectifs en lecture.',
        'organization.recruitment.manage' => 'Traiter les candidatures et décider des suites.',
        'organization.recruitment.openings.manage' => 'Ouvrir ou fermer des postes au recrutement.',
        'atak.sse.access' => 'Entrer dans le portail de renseignement interpersonnel.',
        'atak.sse.grant' => 'Délivrer des codes d’accès temporaires au renseignement. Se règle section par section dans Accès SSE.',
        'atak.sse.case.manage' => 'Créer et clôturer les dossiers de renseignement.',
        'atak.sse.files.admin' => 'Administrer les fichiers SSE : fiches, fusions, photos, journal, extractions.',
        'atak.sse.lore.manage' => 'Préparer le contenu des sessions : personnes, connu ou caché, phases.',
        'atak.sse.export' => 'Exporter les dossiers de renseignement en PDF.',
        'atak.sse.clearance.encadrement' => 'Lire les renseignements jusqu’au niveau Confidentiel (C).',
        'atak.sse.clearance.confidentiel' => 'Lire les renseignements jusqu’au niveau Secret (S).',
        'atak.sse.clearance.tres_restreint' => 'Lire les renseignements de niveau Très secret (TS), le niveau le plus élevé.',
        'atak.config.manage' => 'Régler l’environnement ATAK de toute la communauté.',
        'atak.command.alert' => 'Envoyer une alerte plein écran à tous les opérateurs en jeu.',
        'atak.certificates.manage' => 'Émettre ou révoquer les certificats des terminaux.',
        'atak.toc.use' => 'Tenir le poste TOC web et préparer des plans d’opération.',
        'atak.toc.manage' => 'Régler le poste TOC web et gérer les plans de tous les membres.',
        'atak.mission_cycle.manage' => 'Conduire le cycle de mission : briefing, exécution, après-action.',
        'finances.view' => 'Consulter budget et cotisations, sans rien modifier.',
        'finances.manage' => 'Saisir et corriger les écritures financières.',
        'finances.export' => 'Télécharger les données financières hors du portail.',
        'documents.access.manage' => 'Choisir qui peut lire chaque document.',
        'documents.delete' => 'Supprimer un document de façon définitive.',
        'training.publications.manage' => 'Publier et retirer des parcours de formation du catalogue.',
        'events.manage' => 'Gérer les présences et les événements dans les tableaux de pilotage.',
        'dashboard.pins.manage' => 'Épingler des raccourcis et tenues sur le tableau de bord de la communauté.',
        'moderation.reports.manage' => 'Traiter les signalements : classer, répondre, sanctionner.',
    ];

    /** Dépendances explicites (droit => droits qu’il suppose), en plus de la règle « modifier suppose consulter ». */
    private const REQUIRES = [
        'atak.sse.grant' => ['atak.sse.access'],
        'atak.sse.case.manage' => ['atak.sse.access'],
        'atak.sse.files.admin' => ['atak.sse.access'],
        'atak.sse.lore.manage' => ['atak.sse.access'],
        'atak.sse.export' => ['atak.sse.access'],
        'atak.sse.clearance.confidentiel' => ['atak.sse.clearance.encadrement'],
        'atak.sse.clearance.tres_restreint' => ['atak.sse.clearance.confidentiel'],
        'atak.toc.manage' => ['atak.toc.use'],
        'admin.permissions.manage' => ['admin.roles.manage'],
    ];

    /** @return array<string, array{slug: string, module: string, action: string|null, name: string}> */
    private static function definitionsBySlug(): array
    {
        $out = [];
        foreach (TenantPermissionCatalog::definitions() as $def) {
            $out[$def['slug']] = $def;
        }

        return $out;
    }

    public static function sensitivityFor(string $slug, ?string $action = null): string
    {
        if (in_array($slug, self::CRITICAL, true)) {
            return self::SENSITIVITY_CRITICAL;
        }
        $action ??= self::definitionsBySlug()[$slug]['action'] ?? null;
        if (in_array($action, ['export', 'delete', 'moderate'], true)) {
            return self::SENSITIVITY_CRITICAL;
        }
        if ($action === null || $action === 'view') {
            return self::SENSITIVITY_STANDARD;
        }

        return self::SENSITIVITY_ELEVATED;
    }

    public static function explanationFor(string $slug, ?string $action = null): string
    {
        if (isset(self::EXPLAIN[$slug])) {
            return self::EXPLAIN[$slug];
        }
        $action ??= self::definitionsBySlug()[$slug]['action'] ?? null;

        return match ($action) {
            'view' => 'Consulter, sans rien modifier.',
            'create' => 'Ajouter de nouveaux éléments.',
            'update' => 'Modifier les éléments existants.',
            'delete' => 'Supprimer définitivement des éléments.',
            'manage' => 'Créer, modifier et supprimer, y compris les réglages associés.',
            'assign' => 'Attribuer à des membres.',
            'moderate' => 'Restreindre ou sanctionner l’activité.',
            'export' => 'Télécharger les données hors du portail.',
            'archive' => 'Archiver ou restaurer des éléments.',
            'approve' => 'Valider ou refuser des demandes.',
            default => 'Droit spécifique : voir son libellé.',
        };
    }

    /**
     * Droits supposés par un droit (celui qu’on coche d’abord).
     *
     * @return list<string>
     */
    public static function requiresFor(string $slug): array
    {
        $defs = self::definitionsBySlug();
        if (isset(self::REQUIRES[$slug])) {
            return array_values(array_filter(self::REQUIRES[$slug], static fn (string $s): bool => isset($defs[$s])));
        }
        $action = $defs[$slug]['action'] ?? null;
        if ($action === null || $action === 'view') {
            return [];
        }
        $parts = explode('.', $slug);
        array_pop($parts);
        $candidates = [implode('.', $parts) . '.view', ($parts[0] ?? '') . '.view'];
        foreach ($candidates as $candidate) {
            if ($candidate !== $slug && isset($defs[$candidate])) {
                return [$candidate];
            }
        }

        return [];
    }

    /**
     * Fiche complète d’un droit pour l’écran.
     *
     * @return array{slug: string, code: string, name: string, domain: string, action: string|null, explanation: string, curated: bool, sensitivity: string, requires: list<string>}
     */
    public static function meta(string $slug, string $name = '', string $module = ''): array
    {
        $def = self::definitionsBySlug()[$slug] ?? null;
        $action = $def['action'] ?? null;

        return [
            'slug' => $slug,
            'code' => $slug,
            'name' => $name !== '' ? $name : (string) ($def['name'] ?? $slug),
            'domain' => self::domainFor($slug, $module !== '' ? $module : (string) ($def['module'] ?? '')),
            'action' => $action,
            'explanation' => self::explanationFor($slug, $action),
            'curated' => isset(self::EXPLAIN[$slug]),
            'sensitivity' => self::sensitivityFor($slug, $action),
            'requires' => self::requiresFor($slug),
        ];
    }

    /**
     * Regroupe les droits d’une communauté par domaine, pour l’éditeur.
     *
     * @param list<array<string, mixed>> $permissionRows lignes de la table permissions (id, slug, name, module)
     * @return list<array{key: string, label: string, description: string, items: list<array<string, mixed>>}>
     */
    public static function group(array $permissionRows): array
    {
        $labels = [];
        foreach (self::domains() as $d) {
            $labels[$d['key']] = $d;
        }
        $buckets = [];
        foreach ($permissionRows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            $id = (int) ($row['id'] ?? 0);
            if ($slug === '' || $id < 1 || SystemReservedPermissions::isReserved($slug)) {
                continue;
            }
            $meta = self::meta($slug, trim((string) ($row['name'] ?? '')), (string) ($row['module'] ?? ''));
            if ($meta['name'] === '') {
                continue;
            }
            $meta['id'] = $id;
            $buckets[$meta['domain']][] = $meta;
        }
        $out = [];
        foreach (array_keys($labels) as $key) {
            if (empty($buckets[$key])) {
                continue;
            }
            $items = $buckets[$key];
            usort($items, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
            $out[] = ['key' => $key, 'label' => $labels[$key]['label'], 'description' => $labels[$key]['description'], 'items' => $items];
        }

        return $out;
    }

    /**
     * Droits du catalogue absents des lignes de la communauté (non encore créés pour elle).
     *
     * @param list<array<string, mixed>> $permissionRows
     * @return list<string>
     */
    public static function missingFromTenant(array $permissionRows): array
    {
        $have = [];
        foreach ($permissionRows as $row) {
            $have[(string) ($row['slug'] ?? '')] = true;
        }
        $missing = [];
        foreach (TenantPermissionCatalog::allSlugs() as $slug) {
            if (!isset($have[$slug]) && !SystemReservedPermissions::isReserved($slug)) {
                $missing[] = $slug;
            }
        }

        return $missing;
    }

    /**
     * Espaces du portail et droits qui les ouvrent, lus dans la navigation.
     *
     * @param array<string, mixed> $navigation contenu de config/navigation.php
     * @return list<array{section: string, label: string, path: string, any_of: list<string>}>
     */
    public static function spacesFromNavigation(array $navigation): array
    {
        $out = [];
        $seen = [];
        $walk = static function (mixed $node, string $section) use (&$walk, &$out, &$seen): void {
            if (!is_array($node)) {
                return;
            }
            if (isset($node['label'], $node['path']) && (isset($node['permission']) || isset($node['any_permissions']))) {
                $slugs = isset($node['any_permissions']) ? (array) $node['any_permissions'] : [(string) $node['permission']];
                $slugs = array_values(array_unique(array_map('strval', $slugs)));
                if ($slugs !== [] && !array_filter($slugs, static fn (string $s): bool => SystemReservedPermissions::isReserved($s))) {
                    $key = $section . '|' . $node['label'];
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $out[] = ['section' => $section, 'label' => (string) $node['label'], 'path' => (string) $node['path'], 'any_of' => $slugs];
                    }
                }

                return;
            }
            $title = isset($node['title']) && is_string($node['title']) ? $node['title'] : $section;
            foreach ($node as $child) {
                $walk($child, $title);
            }
        };
        $walk($navigation['menu'] ?? $navigation, '');

        return $out;
    }

    /**
     * Espaces ouverts ou non par un ensemble de droits.
     *
     * @param list<array{section: string, label: string, path: string, any_of: list<string>}> $spaces
     * @param list<string> $granted
     * @return list<array{section: string, label: string, path: string, any_of: list<string>, open: bool}>
     */
    public static function spacesOpenedBy(array $spaces, array $granted): array
    {
        $out = [];
        foreach ($spaces as $space) {
            $open = false;
            foreach ($space['any_of'] as $slug) {
                if (PermissionImplication::isGranted($granted, $slug)) {
                    $open = true;
                    break;
                }
            }
            $out[] = $space + ['open' => $open];
        }

        return $out;
    }

    /**
     * Portée résumée d’un niveau : lecture seule, écriture ou administration.
     *
     * @param list<string> $slugs
     * @return 'none'|'read'|'write'|'admin'
     */
    public static function scopeOf(array $slugs): string
    {
        if ($slugs === []) {
            return 'none';
        }
        $write = false;
        foreach ($slugs as $slug) {
            if (in_array($slug, ['admin.organization', 'admin.access', 'admin.roles.manage'], true)) {
                return 'admin';
            }
            $action = self::definitionsBySlug()[$slug]['action'] ?? 'view';
            if ($action !== 'view' && $action !== null) {
                $write = true;
            }
        }

        return $write ? 'write' : 'read';
    }
}
