<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Authorization\DashboardPinsAccess;
use App\Core\Gate;
use App\Repositories\CommunityEventRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\UserRepository;
use App\Support\EffectifsLmsAccess;

/**
 * Recherche du back-office : pages d’administration + contenu de la communauté.
 *
 * @phpstan-type SearchHit array{title: string, subtitle?: string, excerpt?: string, href: string}
 */
final class BackOfficeSearchService
{
    public const MIN_QUERY_LEN = 2;

    public const MAX_QUERY_LEN = 200;

    private const PER_SCOPE = 10;

    public function __construct(
        private UserRepository $userRepository,
        private DocumentRepository $documentRepository,
        private ?CommunityEventRepository $communityEventRepository = null,
    ) {}

    /**
     * @return array{
     *   query: string,
     *   minLength: int,
     *   pages: list<SearchHit>,
     *   personnel: list<SearchHit>,
     *   documents: list<SearchHit>,
     *   events: list<SearchHit>
     * }
     */
    public function search(int $tenantId, string $raw, Gate $gate): array
    {
        if (mb_strlen($raw) > self::MAX_QUERY_LEN) {
            $raw = mb_substr($raw, 0, self::MAX_QUERY_LEN);
        }
        $pages = $this->filterPages($raw, $gate);
        if (mb_strlen(trim($raw)) < self::MIN_QUERY_LEN) {
            return [
                'query' => $raw,
                'minLength' => self::MIN_QUERY_LEN,
                'pages' => $pages,
                'personnel' => [],
                'documents' => [],
                'events' => [],
            ];
        }

        return [
            'query' => $raw,
            'minLength' => self::MIN_QUERY_LEN,
            'pages' => $pages,
            'personnel' => $this->searchPersonnel($tenantId, $raw, $gate),
            'documents' => $this->searchDocuments($tenantId, $raw, $gate),
            'events' => $this->searchEvents($tenantId, $raw),
        ];
    }

    /**
     * @return list<SearchHit>
     */
    private function filterPages(string $raw, Gate $gate): array
    {
        $needle = $this->normalize($raw);
        $out = [];
        foreach ($this->pageCatalog($gate) as $page) {
            $hay = $this->normalize($page['title'] . ' ' . ($page['subtitle'] ?? '') . ' ' . ($page['keywords'] ?? ''));
            if ($needle === '' || $this->hayContains($hay, $needle)) {
                $out[] = [
                    'title' => $page['title'],
                    'subtitle' => $page['subtitle'],
                    'href' => $page['href'],
                ];
            }
            if (count($out) >= ($needle === '' ? 8 : 40)) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{title: string, subtitle: string, href: string, keywords: string}>
     */
    private function pageCatalog(Gate $gate): array
    {
        $org = $gate->allows('admin.organization') || $gate->allows('admin.access');
        $eff = EffectifsLmsAccess::allows($gate);
        $all = [
            ['title' => 'Tableau de bord', 'subtitle' => 'Aperçu de la communauté', 'href' => url('back-office'), 'keywords' => 'accueil synthèse aperçu', 'ok' => true],
            ['title' => 'Mur opérationnel', 'subtitle' => 'Tableau des actions en cours', 'href' => url('back-office/tableau-operationnel'), 'keywords' => 'mur opérationnel actions', 'ok' => $org],
            ['title' => 'Effectifs', 'subtitle' => 'Tableur des membres', 'href' => url('back-office/ressources/effectifs'), 'keywords' => 'effectifs roster tableur personnel rh membres ancienneté', 'ok' => $eff],
            ['title' => 'Ancienneté', 'subtitle' => 'Dates de fondation et d’arrivée', 'href' => url('back-office/organisation/anciennete'), 'keywords' => 'ancienneté anciennete fondation tenure organisation', 'ok' => $org],
            ['title' => 'Annuaire des membres', 'subtitle' => 'Comptes de la communauté', 'href' => url('back-office/users'), 'keywords' => 'membres comptes utilisateurs annuaire', 'ok' => $org || $gate->allows('admin.users.manage')],
            ['title' => 'Candidatures', 'subtitle' => 'Dossiers de recrutement', 'href' => url('back-office/recruitments'), 'keywords' => 'recrutement candidatures dossiers', 'ok' => $org || $gate->allows('recruitment.manage')],
            ['title' => 'Sanctions et absences', 'subtitle' => 'Modération interne', 'href' => url('back-office/moderation'), 'keywords' => 'sanctions absences discipline', 'ok' => $org],
            ['title' => 'Bureau de suivi', 'subtitle' => 'Suivi d’immersion des membres', 'href' => url('back-office/roleplay-followup'), 'keywords' => 'suivi immersion roleplay', 'ok' => $org],
            ['title' => 'Échéances de suivi', 'subtitle' => 'Dates à ne pas manquer', 'href' => url('back-office/roleplay-followup/echeances'), 'keywords' => 'échéances suivi', 'ok' => $org],
            ['title' => 'Réglages d’immersion', 'subtitle' => 'Options du suivi', 'href' => url('back-office/roleplay/immersion'), 'keywords' => 'immersion réglages', 'ok' => $org],
            ['title' => 'Structure et effectifs', 'subtitle' => 'Organigramme de l’unité', 'href' => url('back-office/organisation-effectifs'), 'keywords' => 'structure organigramme orbat unités', 'ok' => $org || $eff],
            ['title' => 'Catalogue de l’organisation', 'subtitle' => 'Modèles, administration de la structure et journal des applications', 'href' => url('back-office/organisation/catalogue'), 'keywords' => 'catalogue modèles organisation kits infanterie gaming journal historique', 'ok' => $org],
            ['title' => 'Journal du catalogue', 'subtitle' => 'Historique complet des modèles appliqués à cette communauté', 'href' => url('back-office/organisation/catalogue/historique'), 'keywords' => 'journal historique catalogue applications modèles', 'ok' => $org],
            ['title' => 'Doctrine des fonctions', 'subtitle' => 'Emplois et rôles métier', 'href' => url('back-office/roles-functions'), 'keywords' => 'doctrine fonctions emplois', 'ok' => $org],
            ['title' => 'Kits d’accès', 'subtitle' => 'Lecture, modification, recrutement, paramètres — attribuables', 'href' => url('back-office/personnel-job-roles/kits'), 'keywords' => 'kits accès permissions lecture modification recrutement paramètres tenant attributions', 'ok' => $org],
            ['title' => 'Attributions métier', 'subtitle' => 'Qui tient quel emploi', 'href' => url('back-office/personnel-job-roles/assignments'), 'keywords' => 'attributions emplois métier', 'ok' => $org],
            ['title' => 'Corrections de dossiers', 'subtitle' => 'Écarts à régulariser', 'href' => url('back-office/personnel/corrections'), 'keywords' => 'corrections dossiers profils', 'ok' => $org],
            ['title' => 'Identité de la communauté', 'subtitle' => 'Nom, options, présentation', 'href' => url('back-office/community'), 'keywords' => 'identité communauté paramètres', 'ok' => $org],
            ['title' => 'Paramètres d’inscription', 'subtitle' => 'Accueil des nouveaux', 'href' => url('back-office/community/inscription'), 'keywords' => 'inscription recrutement public', 'ok' => $org],
            ['title' => 'Page d’accueil publique', 'subtitle' => 'Vitrine de la communauté', 'href' => url('back-office/community/presentation'), 'keywords' => 'présentation page publique vitrine', 'ok' => $org],
            ['title' => 'Médias', 'subtitle' => 'Galerie de la communauté', 'href' => url('back-office/media'), 'keywords' => 'médias photos galerie', 'ok' => $org],
            ['title' => 'Tenues du tableau de bord', 'subtitle' => 'Vitrine de tenues, comme le catalogue', 'href' => url('back-office/dashboard-tenues'), 'keywords' => 'tenues vitrine tableau de bord png personnage équipement catalogue', 'ok' => DashboardPinsAccess::canManage($gate)],
            ['title' => 'Annonces et alertes', 'subtitle' => 'Messages affichés aux membres', 'href' => url('back-office/alerts'), 'keywords' => 'annonces alertes bandeau', 'ok' => $org],
            ['title' => 'Intégration des nouveaux membres', 'subtitle' => 'Parcours d’arrivée', 'href' => url('back-office/integration-membres'), 'keywords' => 'intégration arrivée parcours onboarding membres', 'ok' => $org],
            ['title' => 'Indicateurs d’usage', 'subtitle' => 'Activité de la communauté', 'href' => url('back-office/analytics'), 'keywords' => 'indicateurs usage statistiques', 'ok' => $org],
            ['title' => 'Configuration initiale', 'subtitle' => 'Premiers réglages', 'href' => url('back-office/configuration-initiale'), 'keywords' => 'configuration initiale wizard', 'ok' => $org],
            ['title' => 'Opérations', 'subtitle' => 'Espaces de mission, plan et vue terrain', 'href' => url('operations'), 'keywords' => 'opérations mission plan ordres vue terrain', 'ok' => true],
            ['title' => 'Opérations (calendrier)', 'subtitle' => 'Manœuvres et événements', 'href' => url('back-office/events'), 'keywords' => 'opérations manœuvres événements calendrier agenda', 'ok' => true],
            ['title' => 'Portail missions', 'subtitle' => 'Missions de la communauté', 'href' => url('back-office/missions'), 'keywords' => 'missions portail', 'ok' => $org],
            ['title' => 'Planification', 'subtitle' => 'Préparation des opérations', 'href' => url('back-office/planification'), 'keywords' => 'planification ordre mission', 'ok' => $org],
            ['title' => 'Historique des inscriptions', 'subtitle' => 'Présences passées', 'href' => url('back-office/events/insights'), 'keywords' => 'historique rsvp présences', 'ok' => $org],
            ['title' => 'Comptes rendus', 'subtitle' => 'Après-action', 'href' => url('back-office/atak/comptes-rendus'), 'keywords' => 'comptes rendus debriefing après-action', 'ok' => $org],
            ['title' => 'Poste de situation', 'subtitle' => 'Carte et suivi en session', 'href' => url('back-office/atak'), 'keywords' => 'atak poste situation carte', 'ok' => $org],
            ['title' => 'Parc de terminaux', 'subtitle' => 'Appareils liés', 'href' => url('back-office/atak/realisme'), 'keywords' => 'terminaux appareils atak', 'ok' => $org],
            ['title' => 'Sessions et connexions', 'subtitle' => 'Opérateurs en ligne', 'href' => url('back-office/atak/operateurs'), 'keywords' => 'sessions connexions opérateurs', 'ok' => $org],
            ['title' => 'Certificats ATAK', 'subtitle' => 'Accès tablette', 'href' => url('back-office/atak/certificats'), 'keywords' => 'certificats atak', 'ok' => $org],
            ['title' => 'Fiche opérateur', 'subtitle' => 'Identité en session', 'href' => url('back-office/atak/fiche-operateur'), 'keywords' => 'fiche opérateur', 'ok' => $org],
            ['title' => 'Documents', 'subtitle' => 'Bibliothèque de la communauté', 'href' => url('documents/gestion'), 'keywords' => 'documents doctrine consignes', 'ok' => !$gate->deny('documents.view')],
            ['title' => 'Forum', 'subtitle' => 'Modération des discussions', 'href' => url('back-office/forum-moderation'), 'keywords' => 'forum modération signalements', 'ok' => $org || $gate->allows('forum.moderate')],
            ['title' => 'Paramètres de la communauté', 'subtitle' => 'Réglages généraux', 'href' => url('back-office/organisation/parametres'), 'keywords' => 'paramètres réglages communauté', 'ok' => $org || $gate->allows('admin.settings.manage')],
            ['title' => 'Configuration système', 'subtitle' => 'Réglages avancés', 'href' => url('back-office/configuration'), 'keywords' => 'configuration système', 'ok' => $org],
            ['title' => 'Mise à niveau', 'subtitle' => 'Nouveautés à configurer', 'href' => url('back-office/mise-a-niveau'), 'keywords' => 'mise à niveau configuration nouveautés', 'ok' => $gate->allows('tenant.configuration.manage') || $org],
            ['title' => 'Matrice des rôles', 'subtitle' => 'Qui peut faire quoi', 'href' => url('back-office/roles-permissions'), 'keywords' => 'rôles droits permissions matrice', 'ok' => $org],
            ['title' => 'Table des rôles', 'subtitle' => 'Catalogue des rôles', 'href' => url('back-office/roles'), 'keywords' => 'rôles table catalogue', 'ok' => $org || $eff],
            ['title' => 'Profils de permissions', 'subtitle' => 'Jeux de droits prêts à l’emploi', 'href' => url('back-office/roles/presets'), 'keywords' => 'profils permissions presets', 'ok' => $org],
            ['title' => 'Journal d’audit', 'subtitle' => 'Historique des actions', 'href' => url('back-office/audit'), 'keywords' => 'audit journal historique', 'ok' => $org],
            ['title' => 'Intégrations', 'subtitle' => 'Services liés', 'href' => url('back-office/integrations'), 'keywords' => 'intégrations discord steam', 'ok' => $org],
            ['title' => 'Rôles Effectifs', 'subtitle' => 'Rôles du tableur', 'href' => url('back-office/ressources/effectifs/roles'), 'keywords' => 'rôles effectifs', 'ok' => $eff],
            ['title' => 'Fonctions', 'subtitle' => 'Emplois du personnel', 'href' => url('back-office/ressources/effectifs/fonctions'), 'keywords' => 'fonctions emplois métiers', 'ok' => $eff],
            ['title' => 'Affectations', 'subtitle' => 'Unités de rattachement', 'href' => url('back-office/ressources/effectifs/affectations'), 'keywords' => 'affectations unités', 'ok' => $eff],
        ];
        $out = [];
        foreach ($all as $row) {
            if (empty($row['ok'])) {
                continue;
            }
            unset($row['ok']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<SearchHit>
     */
    private function searchPersonnel(int $tenantId, string $raw, Gate $gate): array
    {
        if ($tenantId < 1) {
            return [];
        }
        if (!$gate->allows('personnel.profile.view') && !$gate->allows('admin.organization') && !EffectifsLmsAccess::allows($gate)) {
            return [];
        }
        $users = $this->userRepository->listForTenant($tenantId, $raw, null, null, self::PER_SCOPE, 0, true);
        $out = [];
        foreach ($users as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $callsign = trim((string) ($u['callsign'] ?? ''));
            $display = trim((string) ($u['display_name'] ?? ''));
            $sub = $callsign !== '' ? $callsign : $display;
            $out[] = [
                'title' => (string) ($u['display_name'] ?? 'Membre'),
                'subtitle' => $sub,
                'href' => url('back-office/ressources/effectifs/membres/' . $id),
            ];
        }

        return $out;
    }

    /**
     * @return list<SearchHit>
     */
    private function searchDocuments(int $tenantId, string $raw, Gate $gate): array
    {
        if ($tenantId < 1 || $gate->deny('documents.view')) {
            return [];
        }
        $docs = $this->documentRepository->listForTenant(
            $tenantId,
            null,
            null,
            $raw,
            null,
            null,
            null,
            null,
            'updated_desc'
        );
        $docs = array_slice($docs, 0, self::PER_SCOPE);
        $out = [];
        foreach ($docs as $d) {
            $id = (int) ($d['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = [
                'title' => (string) ($d['title'] ?? 'Document'),
                'subtitle' => trim((string) ($d['category_name'] ?? '')),
                'href' => url('documents/gestion/' . $id),
            ];
        }

        return $out;
    }

    /**
     * @return list<SearchHit>
     */
    private function searchEvents(int $tenantId, string $raw): array
    {
        if ($this->communityEventRepository === null || $tenantId < 1) {
            return [];
        }
        $needle = $this->normalize($raw);
        $rows = $this->communityEventRepository->upcomingForTenant($tenantId, 80);
        $out = [];
        foreach ($rows as $row) {
            $title = (string) ($row['title'] ?? '');
            $loc = (string) ($row['location'] ?? '');
            if ($title === '' || !$this->hayContains($this->normalize($title . ' ' . $loc), $needle)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $out[] = [
                'title' => $title,
                'subtitle' => $loc !== '' ? $loc : (string) ($row['starts_at'] ?? ''),
                'href' => $id > 0 ? url('back-office/events/' . $id) : url('back-office/events'),
            ];
            if (count($out) >= self::PER_SCOPE) {
                break;
            }
        }

        return $out;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        if (class_exists(\Normalizer::class)) {
            $nfd = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($nfd)) {
                $s = preg_replace('/\p{Mn}+/u', '', $nfd) ?? $s;
            }
        }

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private function hayContains(string $hay, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        if (str_contains($hay, $needle)) {
            return true;
        }
        $tokens = preg_split('/\s+/', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return false;
        }
        foreach ($tokens as $tok) {
            if (!str_contains($hay, $tok)) {
                return false;
            }
        }

        return true;
    }
}
