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
 * Les pages et les guides sont classés par pertinence : titre avant sous-titre avant mots-clés,
 * avec synonymes métier (orbat ↔ organigramme, droits ↔ accès…) et tolérance d’une faute de frappe.
 *
 * @phpstan-type SearchHit array{title: string, subtitle?: string, excerpt?: string, href: string}
 */
final class BackOfficeSearchService
{
    public const MIN_QUERY_LEN = 2;

    public const MAX_QUERY_LEN = 200;

    private const PER_SCOPE = 10;

    private const GUIDES_LIMIT = 6;

    /**
     * Synonymes métier : un mot tapé trouve aussi les pages qui emploient l’autre terme.
     *
     * @var array<string, list<string>>
     */
    private const SYNONYMS = [
        'orbat' => ['organigramme', 'structure', 'unite'],
        'organigramme' => ['orbat', 'structure'],
        'unite' => ['structure', 'orbat', 'affectation'],
        'droit' => ['acces', 'permission', 'role'],
        'permission' => ['droit', 'acces', 'role'],
        'acces' => ['droit', 'permission', 'role'],
        'role' => ['acces', 'droit'],
        'recrutement' => ['candidature', 'inscription', 'enrolement'],
        'candidature' => ['recrutement', 'dossier'],
        'candidat' => ['recrutement', 'candidature'],
        'inscription' => ['recrutement', 'candidature'],
        'membre' => ['effectifs', 'personnel', 'utilisateur', 'compte'],
        'joueur' => ['membre', 'effectifs', 'personnel'],
        'personnel' => ['effectifs', 'membre'],
        'utilisateur' => ['membre', 'compte'],
        'grade' => ['avancement', 'referentiel'],
        'promotion' => ['avancement', 'grade'],
        'mission' => ['operation', 'evenement'],
        'operation' => ['mission', 'evenement'],
        'evenement' => ['operation', 'calendrier'],
        'session' => ['evenement', 'operation', 'pointage'],
        'aide' => ['guide', 'documentation'],
        'tuto' => ['guide', 'tutoriel'],
        'tutoriel' => ['guide'],
        'doc' => ['documentation', 'guide', 'document'],
        'mail' => ['e-mail', 'communications'],
        'email' => ['e-mail', 'communications'],
        'serveur' => ['serveurs', 'jeu'],
        'mod' => ['modpack'],
        'mods' => ['modpack'],
        'callsign' => ['indicatif'],
        'indicatif' => ['callsign'],
        'formation' => ['training', 'lms', 'cours'],
        'cours' => ['formation'],
        'fonction' => ['emploi', 'metier'],
        'emploi' => ['fonction', 'metier'],
        'metier' => ['fonction', 'emploi'],
        'modele' => ['catalogue', 'kit'],
        'kit' => ['catalogue', 'modele'],
        'parametre' => ['reglage', 'configuration'],
        'reglage' => ['parametre', 'configuration'],
        'vitrine' => ['page publique', 'presentation'],
    ];

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
     *   events: list<SearchHit>,
     *   guides: list<SearchHit>
     * }
     */
    public function search(int $tenantId, string $raw, Gate $gate): array
    {
        if (mb_strlen($raw) > self::MAX_QUERY_LEN) {
            $raw = mb_substr($raw, 0, self::MAX_QUERY_LEN);
        }
        $pages = $this->filterPages($raw, $gate);
        $guides = $this->filterGuides($raw);
        if (mb_strlen(trim($raw)) < self::MIN_QUERY_LEN) {
            return [
                'query' => $raw,
                'minLength' => self::MIN_QUERY_LEN,
                'pages' => $pages,
                'personnel' => [],
                'documents' => [],
                'events' => [],
                'guides' => $guides,
            ];
        }

        return [
            'query' => $raw,
            'minLength' => self::MIN_QUERY_LEN,
            'pages' => $pages,
            'personnel' => $this->searchPersonnel($tenantId, $raw, $gate),
            'documents' => $this->searchDocuments($tenantId, $raw, $gate),
            'events' => $this->searchEvents($tenantId, $raw),
            'guides' => $guides,
        ];
    }

    /**
     * @return list<SearchHit>
     */
    private function filterPages(string $raw, Gate $gate): array
    {
        return $this->rank($this->pageCatalog($gate), $raw, 8, 40);
    }

    /**
     * Guides d’aide : quelques entrées d’accueil sans saisie, puis les plus pertinents.
     *
     * @return list<SearchHit>
     */
    private function filterGuides(string $raw): array
    {
        return $this->rank(BackOfficeHelpCatalog::entries(), $raw, 3, self::GUIDES_LIMIT);
    }

    /**
     * @param list<array{title: string, subtitle: string, href: string, keywords?: string}> $items
     * @return list<SearchHit>
     */
    private function rank(array $items, string $raw, int $emptyLimit, int $limit): array
    {
        $tokens = $this->tokens($this->normalize($raw));
        $scored = [];
        foreach ($items as $i => $item) {
            $score = $tokens === [] ? 1.0 : $this->score($item, $tokens);
            if ($score <= 0.0) {
                continue;
            }
            $scored[] = ['score' => $score, 'i' => $i, 'item' => $item];
            if ($tokens === [] && count($scored) >= $emptyLimit) {
                break;
            }
        }
        usort($scored, static fn (array $a, array $b): int => [$b['score'], $a['i']] <=> [$a['score'], $b['i']]);
        $out = [];
        foreach (array_slice($scored, 0, $tokens === [] ? $emptyLimit : $limit) as $row) {
            $out[] = [
                'title' => $row['item']['title'],
                'subtitle' => $row['item']['subtitle'],
                'href' => $row['item']['href'],
            ];
        }

        return $out;
    }

    /**
     * Chaque mot tapé doit se retrouver quelque part ; le titre pèse plus que le sous-titre, lui-même plus que les mots-clés.
     *
     * @param array{title: string, subtitle: string, keywords?: string} $item
     * @param list<string> $tokens
     */
    private function score(array $item, array $tokens): float
    {
        $fields = [
            [$this->normalize($item['title']), 3.0],
            [$this->normalize($item['subtitle']), 1.5],
            [$this->normalize($item['keywords'] ?? ''), 1.0],
        ];
        $total = 0.0;
        foreach ($tokens as $token) {
            $best = 0.0;
            foreach ($fields as [$hay, $weight]) {
                $best = max($best, $this->tokenMatch($hay, $token) * $weight);
            }
            if ($best <= 0.0) {
                return 0.0;
            }
            $total += $best;
        }
        $title = $fields[0][0];
        $phrase = implode(' ', $tokens);
        if (str_starts_with($title, $phrase)) {
            $total += 2.0;
        } elseif (str_contains($title, $phrase)) {
            $total += 1.0;
        }

        return $total;
    }

    /**
     * 1 = présent, 0,8 = synonyme, 0,6 = une faute de frappe (mots de 5 lettres et plus), 0 = absent.
     */
    private function tokenMatch(string $hay, string $token): float
    {
        if ($hay === '') {
            return 0.0;
        }
        if (str_contains($hay, $token)) {
            return 1.0;
        }
        $stem = $this->stem($token);
        if ($stem !== $token && str_contains($hay, $stem)) {
            return 0.95;
        }
        foreach (self::SYNONYMS[$stem] ?? self::SYNONYMS[$token] ?? [] as $syn) {
            if (str_contains($hay, $syn)) {
                return 0.8;
            }
        }
        $len = strlen($token);
        if ($len < 5) {
            return 0.0;
        }
        $maxDistance = $len >= 9 ? 2 : 1;
        foreach (preg_split('/[^a-z0-9]+/', $hay, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (abs(strlen($word) - $len) > $maxDistance) {
                continue;
            }
            if (levenshtein($word, $token) <= $maxDistance) {
                return 0.6;
            }
        }

        return 0.0;
    }

    /**
     * Pluriel simple du français et de l’anglais : « grades » → « grade », « unités » → « unite ».
     */
    private function stem(string $token): string
    {
        if (strlen($token) > 3 && (str_ends_with($token, 's') || str_ends_with($token, 'x'))) {
            return substr($token, 0, -1);
        }

        return $token;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $needle): array
    {
        $parts = preg_split('/[^a-z0-9]+/', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['de', 'du', 'des', 'la', 'le', 'les', 'un', 'une', 'et', 'a', 'l', 'd', 'en', 'pour', 'ou'];

        return array_values(array_filter($parts, static fn (string $t): bool => !in_array($t, $stop, true)));
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
            ['title' => 'Tableau opérationnel', 'subtitle' => 'Événements, articles, ATAK et mur de consignes', 'href' => url('back-office/tableau-operationnel'), 'keywords' => 'mur opérationnel tableau événements articles atak', 'ok' => $org],
            ['title' => 'Effectifs', 'subtitle' => 'Tableur des membres', 'href' => url('back-office/ressources/effectifs'), 'keywords' => 'effectifs roster tableur personnel rh membres ancienneté', 'ok' => $eff],
            ['title' => 'Ancienneté', 'subtitle' => 'Dates de fondation et d’arrivée', 'href' => url('back-office/organisation/anciennete'), 'keywords' => 'ancienneté anciennete fondation tenure organisation', 'ok' => $org],
            ['title' => 'Annuaire des membres', 'subtitle' => 'Comptes de la communauté', 'href' => url('back-office/users'), 'keywords' => 'membres comptes utilisateurs annuaire', 'ok' => $org || $gate->allows('admin.users.manage')],
            ['title' => 'Candidatures', 'subtitle' => 'Dossiers de recrutement', 'href' => url('back-office/recruitments'), 'keywords' => 'recrutement candidatures dossiers', 'ok' => $org || $gate->allows('recruitment.manage')],
            ['title' => 'Sanctions et absences', 'subtitle' => 'Modération interne', 'href' => url('back-office/moderation'), 'keywords' => 'sanctions absences discipline', 'ok' => $org],
            ['title' => 'Bureau de suivi', 'subtitle' => 'Suivi d’immersion des membres', 'href' => url('back-office/roleplay-followup'), 'keywords' => 'suivi immersion roleplay', 'ok' => $org],
            ['title' => 'Échéances de suivi', 'subtitle' => 'Dates à ne pas manquer', 'href' => url('back-office/roleplay-followup/echeances'), 'keywords' => 'échéances suivi', 'ok' => $org],
            ['title' => 'Parcours d’immersion', 'subtitle' => 'Étapes, filières et dossier prêt', 'href' => url('back-office/roleplay/immersion'), 'keywords' => 'immersion réglages parcours arrivée tutorat filière dossier prêt cadences bilans essai', 'ok' => $org],
            ['title' => 'Parcours RH', 'subtitle' => 'Étapes du parcours et conditions de passage', 'href' => url('back-office/roleplay/regles-phases'), 'keywords' => 'parcours rh étapes conditions passage qualification formation présence tuteur', 'ok' => $org],
            ['title' => 'Sessions Arma', 'subtitle' => 'Pointage et temps retenu', 'href' => url('back-office/roleplay/sessions'), 'keywords' => 'sessions arma pointage présence heures opération entraînement', 'ok' => $org],
            ['title' => 'Structure et effectifs', 'subtitle' => 'Organigramme de l’unité', 'href' => url('back-office/organisation-effectifs'), 'keywords' => 'structure organigramme orbat unités', 'ok' => $org || $eff],
            ['title' => 'Catalogue de l’organisation', 'subtitle' => 'Modèles, administration de la structure et journal des applications', 'href' => url('back-office/organisation/catalogue'), 'keywords' => 'catalogue modèles organisation armée française sof américain infanterie gaming journal historique emplois', 'ok' => $org],
            ['title' => 'Journal du catalogue', 'subtitle' => 'Historique complet des modèles appliqués à cette communauté', 'href' => url('back-office/organisation/catalogue/historique'), 'keywords' => 'journal historique catalogue applications modèles', 'ok' => $org],
            ['title' => 'Emplois du dossier', 'subtitle' => 'Libellés métier, pas des droits', 'href' => effectifs_workspace_url('fonctions'), 'keywords' => 'emplois fonctions doctrine attributions métier catalogue', 'ok' => $org || $eff],
            ['title' => 'Corrections de dossiers', 'subtitle' => 'Demandes à confirmer, ou correction immédiate', 'href' => url('back-office/personnel/corrections'), 'keywords' => 'corrections dossiers profils demande', 'ok' => $org],
            ['title' => 'Paramètres de la communauté', 'subtitle' => 'Identité, vitrine, inscription, accueil et profil', 'href' => url('back-office/community'), 'keywords' => 'identité communauté paramètres inscription vitrine accueil', 'ok' => $org],
            ['title' => 'Inscription des membres', 'subtitle' => 'Parcours de candidature et dossier', 'href' => url('back-office/community') . '?onglet=inscription', 'keywords' => 'inscription recrutement public candidature', 'ok' => $org],
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
            ['title' => 'Forum', 'subtitle' => 'Modération des discussions', 'href' => url('back-office/forum-moderation'), 'keywords' => 'forum modération signalements', 'ok' => (function_exists('forum_public_nav_visible') && forum_public_nav_visible()) && ($org || $gate->allows('forum.moderate'))],
            ['title' => 'Paramètres de la communauté', 'subtitle' => 'Réglages généraux', 'href' => url('back-office/organisation/parametres'), 'keywords' => 'paramètres réglages communauté images accueil bienvenue connexion', 'ok' => $org || $gate->allows('admin.settings.manage')],
            ['title' => 'Images d’accueil', 'subtitle' => 'Photos de l’écran de bienvenue après connexion', 'href' => url('back-office/organisation/parametres') . '?onglet=accueil#accueil-connexion', 'keywords' => 'images accueil bienvenue connexion photos défilement écran', 'ok' => $org || $gate->allows('admin.settings.manage')],
            ['title' => 'Configuration système', 'subtitle' => 'Réglages avancés', 'href' => url('back-office/configuration'), 'keywords' => 'configuration système', 'ok' => $org],
            ['title' => 'Mise à niveau', 'subtitle' => 'Nouveautés à configurer', 'href' => url('back-office/mise-a-niveau'), 'keywords' => 'mise à niveau configuration nouveautés', 'ok' => $gate->allows('tenant.configuration.manage') || $org],
            ['title' => 'Niveaux d’accès', 'subtitle' => 'Ce qu’un membre a le droit de faire', 'href' => effectifs_workspace_url('roles'), 'keywords' => 'accès droits niveaux rôles matrice table presets discord effectifs', 'ok' => $org || $eff],
            ['title' => 'Journal d’audit', 'subtitle' => 'Historique des actions', 'href' => url('back-office/audit'), 'keywords' => 'audit journal historique', 'ok' => $org],
            ['title' => 'Intégrations', 'subtitle' => 'Services liés', 'href' => url('back-office/integrations'), 'keywords' => 'intégrations discord steam', 'ok' => $org],
            ['title' => 'Affectations', 'subtitle' => 'Unités de rattachement', 'href' => url('back-office/ressources/effectifs/affectations'), 'keywords' => 'affectations unités', 'ok' => $eff],
            ['title' => 'Chaîne de commandement', 'subtitle' => 'Qui relève de qui', 'href' => url('back-office/ressources/effectifs/chaine'), 'keywords' => 'chaîne chef commandement supérieur relève organigramme', 'ok' => $eff],
            ['title' => 'Centre d’opérations', 'subtitle' => 'Demandes à traiter, incidents et historique', 'href' => url('back-office/centre-operations'), 'keywords' => 'centre opérations file incidents ops admin', 'ok' => $org],
            ['title' => 'Qualité des données', 'subtitle' => 'Fiches incomplètes ou incohérentes', 'href' => url('back-office/organisation/qualite-donnees'), 'keywords' => 'qualité données fiches incomplètes', 'ok' => $org],
            ['title' => 'Sécurité et blocages', 'subtitle' => 'Comptes bloqués et alertes de connexion', 'href' => url('back-office/security-indicators'), 'keywords' => 'sécurité blocages connexion portail', 'ok' => $org],
            ['title' => 'Droits par membre', 'subtitle' => 'Exceptions accordées à une personne', 'href' => effectifs_workspace_url('droits'), 'keywords' => 'droits accès permissions membre exception', 'ok' => $org || $eff],
            ['title' => 'Accès au renseignement', 'subtitle' => 'Qui peut consulter le renseignement', 'href' => url('back-office/renseignement/acces'), 'keywords' => 'renseignement accès sse codes', 'ok' => $org],
            ['title' => 'Alertes RH', 'subtitle' => 'Dossiers qui demandent une action', 'href' => effectifs_workspace_url('alertes'), 'keywords' => 'alertes rh dossiers', 'ok' => $eff],
            ['title' => 'Demandes d’accès élevé', 'subtitle' => 'Membres qui demandent plus de droits', 'href' => effectifs_workspace_url('elevations'), 'keywords' => 'élévation demandes accès officier droits', 'ok' => $eff],
            ['title' => 'Documents RH', 'subtitle' => 'Pièces des dossiers du personnel', 'href' => effectifs_workspace_url('documents-rh'), 'keywords' => 'documents rh pièces dossier', 'ok' => $eff],
            ['title' => 'Mobilité', 'subtitle' => 'Mutations entre unités', 'href' => effectifs_workspace_url('mobilite'), 'keywords' => 'mobilité mutation transfert', 'ok' => $eff],
            ['title' => 'Relève et succession', 'subtitle' => 'Qui peut remplacer qui', 'href' => effectifs_workspace_url('vivier'), 'keywords' => 'vivier relève succession remplaçant', 'ok' => $eff],
            ['title' => 'Départs', 'subtitle' => 'Membres sortants', 'href' => effectifs_workspace_url('departs'), 'keywords' => 'départs sortie démission', 'ok' => $eff],
            ['title' => 'Dossiers roleplay', 'subtitle' => 'Suivi roleplay dans les dossiers RH', 'href' => effectifs_workspace_url('roleplay'), 'keywords' => 'roleplay dossiers rh', 'ok' => $eff],
            ['title' => 'Offres de recrutement', 'subtitle' => 'Postes ouverts aux candidats', 'href' => url('back-office/recruitment/offers'), 'keywords' => 'offres recrutement postes', 'ok' => $org],
            ['title' => 'Invitations', 'subtitle' => 'Inviter par e-mail', 'href' => url('back-office/invitations'), 'keywords' => 'invitations e-mail lien', 'ok' => $org],
            ['title' => 'Groupes', 'subtitle' => 'Sous-unités et cellules', 'href' => url('back-office/groups'), 'keywords' => 'groupes cellules sous-unités', 'ok' => $org],
            ['title' => 'Équipes', 'subtitle' => 'Équipes de mission', 'href' => url('back-office/teams'), 'keywords' => 'équipes binômes', 'ok' => $org],
            ['title' => 'Matricules', 'subtitle' => 'Numérotation des membres', 'href' => url('back-office/organisation/matricules'), 'keywords' => 'matricules numéros', 'ok' => $org],
            ['title' => 'Indicatifs radio', 'subtitle' => 'Séries d’indicatifs', 'href' => url('back-office/organisation/indicatifs'), 'keywords' => 'indicatifs callsign radio', 'ok' => $org],
            ['title' => 'E-mails aux membres', 'subtitle' => 'Écrire à tout ou partie de la communauté', 'href' => url('back-office/communications'), 'keywords' => 'e-mail communications envoi message', 'ok' => $org],
            ['title' => 'Doctrine et SOP', 'subtitle' => 'Procédures de l’unité', 'href' => url('back-office/doctrine'), 'keywords' => 'doctrine sop procédures', 'ok' => $org],
            ['title' => 'Traçabilité du courrier', 'subtitle' => 'Qui a lu quoi', 'href' => url('back-office/courrier/traceabilite'), 'keywords' => 'courrier traçabilité lecture', 'ok' => $org],
            ['title' => 'Référentiel des grades', 'subtitle' => 'Grades disponibles et libellés', 'href' => url('back-office/referentiels/grades'), 'keywords' => 'grades référentiel français américain insignes rang', 'ok' => $org],
            ['title' => 'Avancement et grades', 'subtitle' => 'Règles de promotion', 'href' => url('back-office/organisation/grades'), 'keywords' => 'avancement promotion grades ancienneté règles', 'ok' => $org],
            ['title' => 'Campagnes d’avancement', 'subtitle' => 'Promotions à valider', 'href' => url('back-office/rh/avancement'), 'keywords' => 'avancement campagne promotion tableau', 'ok' => $org],
            ['title' => 'Progression et carrière', 'subtitle' => 'Parcours, grades et qualifications', 'href' => url('back-office/organisation/progression'), 'keywords' => 'progression carrière parcours grades qualifications', 'ok' => $org],
            ['title' => 'Référentiel des qualifications', 'subtitle' => 'Brevets, certifications et habilitations', 'href' => url('back-office/referentiels/qualifications'), 'keywords' => 'qualifications brevets certifications habilitations compétences', 'ok' => $org],
            ['title' => 'Matrice de compétences', 'subtitle' => 'Compétences attendues par grade', 'href' => url('back-office/referentiels/competences'), 'keywords' => 'compétences matrice grades attendus', 'ok' => $org],
            ['title' => 'Décorations', 'subtitle' => 'Médailles et distinctions', 'href' => url('back-office/referentiels/decorations'), 'keywords' => 'décorations médailles distinctions récompenses', 'ok' => $org],
            ['title' => 'Dotation', 'subtitle' => 'Équipement attribué aux membres', 'href' => url('back-office/referentiels/dotation'), 'keywords' => 'dotation équipement matériel paquetage', 'ok' => $org],
            ['title' => 'Disponibilité des unités', 'subtitle' => 'Postes tenus et vacants', 'href' => url('back-office/organisation/disponibilite'), 'keywords' => 'disponibilité unités postes vacants effectif', 'ok' => $org],
            ['title' => 'Structure et recrutement', 'subtitle' => 'Organigramme et postes ouverts', 'href' => url('back-office/organisation/structure'), 'keywords' => 'structure orbat recrutement postes unités', 'ok' => $org],
            ['title' => 'Rôles et fonctions', 'subtitle' => 'Emplois du dossier et niveaux d’accès', 'href' => url('back-office/roles-functions'), 'keywords' => 'rôles fonctions emplois métiers accès', 'ok' => $org],
            ['title' => 'Codes d’invitation', 'subtitle' => 'Codes de recrutement prioritaires', 'href' => url('back-office/recruitments/codes-invitation'), 'keywords' => 'codes invitation recrutement prioritaire parrainage', 'ok' => $org || $gate->allows('organization.recruitment.manage')],
            ['title' => 'Questions du formulaire Discord', 'subtitle' => 'Recrutement via Discord', 'href' => url('back-office/recruitments/discord-questions'), 'keywords' => 'discord questions formulaire recrutement', 'ok' => $org || $gate->allows('organization.recruitment.manage')],
            ['title' => 'Messages préfaits du recrutement', 'subtitle' => 'Réponses types aux candidats', 'href' => url('back-office/recruitments/messages-prefaits'), 'keywords' => 'messages préfaits réponses types candidats recrutement', 'ok' => $org || $gate->allows('organization.recruitment.manage')],
            ['title' => 'Délais d’alerte du recrutement', 'subtitle' => 'Relances des dossiers en attente', 'href' => url('back-office/recruitments/settings'), 'keywords' => 'délais alerte relance recrutement réglages', 'ok' => $org || $gate->allows('organization.recruitment.manage')],
            ['title' => 'Analyses des candidatures', 'subtitle' => 'Volumes, délais et conversion', 'href' => url('back-office/ressources/recrutement/analyses'), 'keywords' => 'analyses statistiques candidatures recrutement conversion', 'ok' => $org || $gate->allows('organization.recruitment.manage')],
            ['title' => 'Aide après inscription', 'subtitle' => 'Reprendre la configuration d’une communauté', 'href' => url('back-office/onboarding-recovery'), 'keywords' => 'rattrapage onboarding assistant reprise configuration création', 'ok' => $org],
            ['title' => 'Nouveautés de votre organisation', 'subtitle' => 'Fonctions récentes à découvrir', 'href' => url('back-office/nouveautes-organisation'), 'keywords' => 'nouveautés fonctionnalités récentes mises à jour', 'ok' => $org],
            ['title' => 'Réglages effectifs', 'subtitle' => 'Options du bureau des effectifs', 'href' => url('back-office/ressources/effectifs/reglages'), 'keywords' => 'réglages effectifs options dossier rh', 'ok' => $org],
            ['title' => 'Fiches jumelles', 'subtitle' => 'Doublons de dossiers', 'href' => url('back-office/ressources/effectifs/doublons'), 'keywords' => 'doublons fiches jumelles fusion', 'ok' => $org],
            ['title' => 'Mini-articles', 'subtitle' => 'Actualités courtes de la communauté', 'href' => url('back-office/articles'), 'keywords' => 'articles actualités news publication', 'ok' => $org],
            ['title' => 'Modèles d’e-mail', 'subtitle' => 'Messages réutilisables', 'href' => url('back-office/communications/templates'), 'keywords' => 'modèles e-mail templates communications', 'ok' => $org],
            ['title' => 'Types de documents', 'subtitle' => 'Classement de la bibliothèque', 'href' => url('back-office/documents/types'), 'keywords' => 'types documents classement catégories', 'ok' => $org],
            ['title' => 'Modpacks', 'subtitle' => 'Listes de mods de la communauté', 'href' => url('back-office/ressources/modpacks'), 'keywords' => 'modpacks mods workshop arma', 'ok' => $org],
            ['title' => 'Serveurs de jeu', 'subtitle' => 'Adresses et accès des serveurs', 'href' => url('back-office/ressources/serveurs-de-jeu'), 'keywords' => 'serveurs jeu arma adresse ip connexion', 'ok' => $org],
            ['title' => 'Configuration du forum', 'subtitle' => 'Catégories et réglages du forum', 'href' => url('back-office/ressources/forum-config'), 'keywords' => 'forum configuration catégories sections', 'ok' => $org],
            ['title' => 'Configuration ATAK', 'subtitle' => 'Réglages de la carte tactique', 'href' => url('back-office/ressources/atak-config'), 'keywords' => 'atak tacmap configuration carte', 'ok' => $org],
            ['title' => 'Escouades en jeu', 'subtitle' => 'Groupes affichés dans ATAK', 'href' => url('back-office/atak/escouades'), 'keywords' => 'escouades atak jeu groupes', 'ok' => $org],
            ['title' => 'Diapositives de briefing', 'subtitle' => 'Présentations tactiques', 'href' => url('back-office/atak/briefing-slides'), 'keywords' => 'diapositives briefing présentation eden', 'ok' => $org],
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
