<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\ElevationRequestRepository;
use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\OrgAdequacyService;
use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Services\Personnel\PersonnelDuplicateDetectionService;
use App\Services\Personnel\PersonnelProfileGapScanService;
use App\Support\EffectifsWorkspaceShellExtras;

/**
 * « À traiter » : toutes les alertes RH du bureau effectifs, rangées par urgence.
 *
 * - now   : une décision ou une action est attendue maintenant ;
 * - watch : à surveiller, rien ne presse mais la situation peut se dégrader ;
 * - info  : pour information, aucune décision n'est demandée.
 *
 * Le système propose ; le responsable décide. Chaque carte porte un compteur, un lien direct vers l'action et,
 * si utile, le détail des personnes concernées (affiché dans une fenêtre). Les textes fixes sont dans CARDS ;
 * les nombres et les dates sont composés par la vue pour que la traduction reste possible.
 * Une absence est déclarative : elle ne produit jamais de dossier incomplet (voir PersonnelProfileGapScanService).
 */
final class RhActionInboxService
{
    /** Nombre maximal de lignes de détail par carte ; le reste renvoie vers la page de l'action. */
    public const ITEM_LIMIT = 8;

    /** Qualification « à traiter maintenant » : échue, ou échéance sous ce nombre de jours. */
    public const QUALIF_URGENT_DAYS = 14;

    public const QUALIF_WINDOW_DAYS = 60;

    /**
     * @var array<string, array{label: string, slug: string, lead: string}>
     */
    public const SECTIONS = [
        'now' => [
            'label' => 'À traiter maintenant',
            'slug' => 'maintenant',
            'lead' => 'Une décision ou une action vous est demandée.',
        ],
        'watch' => [
            'label' => 'À surveiller',
            'slug' => 'surveiller',
            'lead' => 'Rien ne presse, mais ces situations peuvent se dégrader.',
        ],
        'info' => [
            'label' => 'Informations',
            'slug' => 'infos',
            'lead' => 'Pour votre information : aucune décision n’est demandée.',
        ],
    ];

    /**
     * Cartes connues : section, titre, phrase d'explication, libellé du bouton, ton.
     * Un bouton vide : la carte n'a pas de page d'action propre, son détail s'ouvre dans une fenêtre.
     *
     * @var array<string, array{section: string, title: string, text: string, cta: string, tone: string}>
     */
    public const CARDS = [
        'corrections' => [
            'section' => 'now',
            'title' => 'Corrections de fiche à valider',
            'text' => 'Des membres demandent de modifier leur fiche. Rien ne change tant que vous n’avez pas décidé.',
            'cta' => 'Valider ou refuser',
            'tone' => 'warn',
        ],
        'elevations' => [
            'section' => 'now',
            'title' => 'Propositions d’élévation',
            'text' => 'Changements de grade, de rôle ou de fonction à examiner avant décision.',
            'cta' => 'Examiner',
            'tone' => 'warn',
        ],
        'mobility' => [
            'section' => 'now',
            'title' => 'Demandes de mobilité',
            'text' => 'Souhaits de changement d’unité, de poste ou d’évolution qui attendent une réponse.',
            'cta' => 'Répondre',
            'tone' => 'warn',
        ],
        'qualif_urgent' => [
            'section' => 'now',
            'title' => 'Qualifications échues ou presque',
            'text' => 'Qualifications déjà échues ou qui expirent dans moins de quinze jours.',
            'cta' => 'Ouvrir les qualifications',
            'tone' => 'danger',
        ],
        'vacant_critical' => [
            'section' => 'now',
            'title' => 'Postes essentiels sans titulaire',
            'text' => 'Postes marqués comme essentiels qui n’ont pas assez de titulaires.',
            'cta' => 'Préparer une relève',
            'tone' => 'danger',
        ],
        'phase_gates' => [
            'section' => 'now',
            'title' => 'Passages d’étape à valider',
            'text' => 'Des membres remplissent les conditions pour passer à l’étape suivante : il faut votre accord.',
            'cta' => 'Ouvrir le suivi',
            'tone' => 'warn',
        ],
        'phase_errors' => [
            'section' => 'now',
            'title' => 'Passages d’étape en erreur',
            'text' => 'Un passage d’étape automatique n’a pas pu se faire. Il faut le reprendre à la main.',
            'cta' => 'Corriger',
            'tone' => 'danger',
        ],
        'roleplay_due' => [
            'section' => 'now',
            'title' => 'Suivis roleplay en retard',
            'text' => 'Entretiens ou suivis prévus dont la date est dépassée.',
            'cta' => 'Ouvrir le suivi',
            'tone' => 'warn',
        ],
        'qualif_soon' => [
            'section' => 'watch',
            'title' => 'Qualifications qui expirent bientôt',
            'text' => 'Échéance dans les deux prochains mois. Le renouvellement passe par une formation.',
            'cta' => 'Ouvrir les qualifications',
            'tone' => 'warn',
        ],
        'absence_long' => [
            'section' => 'watch',
            'title' => 'Absences prolongées',
            'text' => 'Absences déclarées depuis longtemps. Vérifiez si un retour ou une décision est attendu.',
            'cta' => '',
            'tone' => 'warn',
        ],
        'inactive' => [
            'section' => 'watch',
            'title' => 'Membres sans connexion',
            'text' => 'Comptes actifs sans connexion depuis longtemps. Ce n’est pas un départ : c’est le moment de prendre des nouvelles.',
            'cta' => '',
            'tone' => 'warn',
        ],
        'incomplete' => [
            'section' => 'watch',
            'title' => 'Dossiers incomplets',
            'text' => 'Il manque une fonction, un grade, un niveau d’accès ou une image d’opérateur.',
            'cta' => '',
            'tone' => 'info',
        ],
        'missing_command' => [
            'section' => 'watch',
            'title' => 'Unités sans rattachement au commandement',
            'text' => 'La chaîne de ces unités ne remonte pas jusqu’au commandement prévu.',
            'cta' => 'Corriger les rattachements',
            'tone' => 'warn',
        ],
        'no_leader' => [
            'section' => 'watch',
            'title' => 'Unités sans chef désigné',
            'text' => 'Aucun chef n’est désigné pour ces unités.',
            'cta' => 'Ouvrir la chaîne',
            'tone' => 'warn',
        ],
        'adequacy' => [
            'section' => 'watch',
            'title' => 'Écarts avec l’organisation attendue',
            'text' => 'Des personnes ou des postes ne correspondent pas au niveau attendu pour leur échelon.',
            'cta' => 'Voir les écarts',
            'tone' => 'warn',
        ],
        'duplicates' => [
            'section' => 'watch',
            'title' => 'Fiches possiblement en double',
            'text' => 'Des fiches partagent un matricule, un indicatif ou un nom.',
            'cta' => 'Comparer les fiches',
            'tone' => 'warn',
        ],
        'no_unit' => [
            'section' => 'watch',
            'title' => 'Membres sans affectation',
            'text' => 'Ces membres ne sont rattachés à aucune unité.',
            'cta' => 'Voir ces membres',
            'tone' => 'info',
        ],
        'no_role' => [
            'section' => 'watch',
            'title' => 'Membres sans niveau d’accès',
            'text' => 'Ces membres n’ont aucun niveau d’accès dans la communauté.',
            'cta' => 'Voir ces membres',
            'tone' => 'info',
        ],
        'career' => [
            'section' => 'info',
            'title' => 'Écarts de parcours',
            'text' => 'Différences entre grade, emploi et qualifications. Indicatif : cela ne bloque jamais une affectation ni une promotion.',
            'cta' => '',
            'tone' => 'info',
        ],
        'vacant' => [
            'section' => 'info',
            'title' => 'Postes à pourvoir',
            'text' => 'Postes de l’organigramme qui ont moins de titulaires que prévu.',
            'cta' => 'Préparer une relève',
            'tone' => 'info',
        ],
        'integration' => [
            'section' => 'info',
            'title' => 'Intégrations en cours',
            'text' => 'Nouveaux membres qui n’ont pas encore terminé leur parcours d’accueil.',
            'cta' => 'Suivre les intégrations',
            'tone' => 'info',
        ],
        'pending_accounts' => [
            'section' => 'info',
            'title' => 'Comptes en attente de vérification',
            'text' => 'Comptes créés dont l’adresse n’a pas encore été confirmée par le membre.',
            'cta' => 'Voir ces comptes',
            'tone' => 'info',
        ],
    ];

    private PersonnelProfileGapScanService $gaps;

    public function __construct(
        private PersonnelCorrectionRequestRepository $corrections,
        private ElevationRequestRepository $elevations,
        private RhAlertAggregatorService $alerts,
        ?PersonnelProfileGapScanService $gaps = null,
        private ?PersonnelQualificationRepository $qualifications = null,
        private ?PersonnelMobilityRequestRepository $mobility = null,
        private ?UnitRepository $units = null,
        private ?UserRepository $users = null,
    ) {
        $this->gaps = $gaps ?? new PersonnelProfileGapScanService();
        $this->qualifications ??= new PersonnelQualificationRepository();
        $this->mobility ??= new PersonnelMobilityRequestRepository();
        $this->units ??= new UnitRepository();
        $this->users ??= new UserRepository();
    }

    /**
     * @return array{
     *   total: int,
     *   attention: int,
     *   sections: array<string, array{id: string, slug: string, label: string, lead: string, count: int, cards: list<array<string, mixed>>, clear: list<string>}>
     * }
     */
    public function build(int $tenantId, ?int $inactivityDays = null, ?int $absenceDays = null): array
    {
        if ($tenantId < 1) {
            return self::assemble([]);
        }
        $inactivityDays = max(14, min(180, $inactivityDays ?? RhAlertAggregatorService::INACTIVITY_DAYS));
        $absenceDays = max(7, min(90, $absenceDays ?? RhAlertAggregatorService::PROLONGED_ABSENCE_DAYS));
        $facts = [];
        $limit = self::ITEM_LIMIT;

        $this->collect($facts, 'corrections', function () use ($tenantId, $limit): array {
            $items = [];
            $labels = PersonnelCorrectionRequestService::fieldLabels(true);
            foreach ($this->corrections->listOpenForTenant($tenantId, $limit) as $row) {
                $fields = [];
                foreach (array_keys(is_array($row['proposed'] ?? null) ? $row['proposed'] : []) as $key) {
                    $fields[] = $labels[(string) $key] ?? 'Information du dossier';
                }
                $items[] = [
                    'label' => self::memberLabel((string) ($row['target_display_name'] ?? ''), '', (int) ($row['target_user_id'] ?? 0)),
                    'since' => (string) ($row['created_at'] ?? ''),
                    'by' => trim((string) ($row['requester_display_name'] ?? '')),
                    'fields' => array_slice($fields, 0, 4),
                    'href' => url('back-office/personnel/corrections'),
                ];
            }

            return [
                'count' => $this->corrections->countPendingForTenant($tenantId),
                'items' => $items,
                'href' => url('back-office/personnel/corrections'),
                'gate' => 'corrections',
            ];
        });

        $this->collect($facts, 'elevations', function () use ($tenantId, $limit): array {
            $items = [];
            foreach (array_slice($this->elevations->listOpenForTenant($tenantId, $limit), 0, $limit) as $row) {
                $uid = (int) ($row['target_user_id'] ?? 0);
                $items[] = [
                    'label' => self::memberLabel((string) ($row['target_display_name'] ?? ''), '', $uid),
                    'since' => (string) ($row['created_at'] ?? ''),
                    'by' => trim((string) ($row['requester_display_name'] ?? '')),
                    'href' => effectifs_workspace_url('elevations'),
                ];
            }

            return ['count' => $this->elevations->countOpenForTenant($tenantId), 'items' => $items, 'href' => effectifs_workspace_url('elevations')];
        });

        $this->collect($facts, 'mobility', function () use ($tenantId, $limit): array {
            $count = $this->mobility->countPending($tenantId);
            $items = [];
            if ($count > 0) {
                foreach (array_slice($this->mobility->listPendingForTenant($tenantId, $limit), 0, $limit) as $row) {
                    $uid = (int) ($row['user_id'] ?? 0);
                    $items[] = [
                        'label' => self::memberLabel((string) ($row['user_display_name'] ?? ''), '', $uid),
                        'since' => (string) ($row['created_at'] ?? ''),
                        'kind' => (string) (PersonnelMobilityRequestRepository::TYPE_LABELS[(string) ($row['request_type'] ?? '')] ?? ''),
                        'unit' => trim((string) ($row['target_unit_name'] ?? '')),
                        'href' => effectifs_workspace_url('mobilite'),
                    ];
                }
            }

            return ['count' => $count, 'items' => $items, 'href' => effectifs_workspace_url('mobilite')];
        });

        $qualifRows = [];
        try {
            $qualifRows = $this->qualifications->listExpiringForTenant($tenantId, self::QUALIF_WINDOW_DAYS, 300);
        } catch (\Throwable) {
            $qualifRows = [];
        }
        $urgent = [];
        $soon = [];
        $today = strtotime(date('Y-m-d')) ?: time();
        foreach ($qualifRows as $row) {
            $expires = substr(trim((string) ($row['expires_at'] ?? '')), 0, 10);
            $ts = $expires !== '' ? strtotime($expires) : false;
            if ($ts === false) {
                continue;
            }
            $daysLeft = (int) round(($ts - $today) / 86400);
            $uid = (int) ($row['user_id'] ?? 0);
            $item = [
                'label' => self::memberLabel((string) ($row['display_name'] ?? ''), (string) ($row['callsign'] ?? ''), $uid),
                'what' => trim((string) ($row['qualification_name'] ?? $row['name'] ?? '')),
                'days_left' => $daysLeft,
                'expires_on' => $expires,
                'href' => $uid > 0 ? effectifs_workspace_url('membres/' . $uid) : effectifs_workspace_url('qualifications'),
            ];
            if ($daysLeft <= self::QUALIF_URGENT_DAYS) {
                $urgent[] = $item;
            } else {
                $soon[] = $item;
            }
        }
        $facts['qualif_urgent'] = ['count' => count($urgent), 'items' => array_slice($urgent, 0, $limit), 'href' => effectifs_workspace_url('qualifications')];
        $facts['qualif_soon'] = ['count' => count($soon), 'items' => array_slice($soon, 0, $limit), 'href' => effectifs_workspace_url('qualifications')];

        $vacant = [];
        try {
            $vacant = $this->alerts->listVacantBillets($tenantId, 100);
        } catch (\Throwable) {
            $vacant = [];
        }
        $critical = [];
        $other = [];
        foreach ($vacant as $b) {
            $item = [
                'label' => (string) ($b['title'] ?? ''),
                'unit' => trim((string) ($b['unit_name'] ?? '')),
                'held' => (int) ($b['held'] ?? 0),
                'authorized' => (int) ($b['authorized'] ?? 0),
                'href' => url('back-office/organisation/structure') . ((int) ($b['unit_id'] ?? 0) > 0 ? '?unite=' . (int) $b['unit_id'] : ''),
            ];
            if (!empty($b['critical'])) {
                $critical[] = $item;
            } else {
                $other[] = $item;
            }
        }
        $facts['vacant_critical'] = ['count' => count($critical), 'items' => array_slice($critical, 0, $limit), 'href' => effectifs_workspace_url('vivier')];
        $facts['vacant'] = ['count' => count($other), 'items' => array_slice($other, 0, $limit), 'href' => effectifs_workspace_url('vivier')];

        $extras = ['phaseGateCount' => 0, 'phaseAutoErrorCount' => 0, 'roleplayDueCount' => 0, 'integrationOpenCount' => 0];
        try {
            $extras = EffectifsWorkspaceShellExtras::counts($tenantId);
        } catch (\Throwable) {
        }
        $facts['phase_gates'] = ['count' => (int) $extras['phaseGateCount'], 'href' => effectifs_workspace_url('roleplay')];
        $facts['phase_errors'] = ['count' => (int) $extras['phaseAutoErrorCount'], 'href' => effectifs_workspace_url('roleplay')];
        $facts['roleplay_due'] = ['count' => (int) $extras['roleplayDueCount'], 'href' => effectifs_workspace_url('roleplay')];
        $facts['integration'] = ['count' => (int) $extras['integrationOpenCount'], 'href' => url('back-office/integration-membres')];

        $this->collect($facts, 'absence_long', function () use ($tenantId, $absenceDays, $limit): array {
            $count = $this->alerts->countProlongedAbsences($tenantId, $absenceDays);
            $items = [];
            if ($count > 0) {
                foreach ($this->alerts->listProlongedAbsences($tenantId, $limit, $absenceDays) as $row) {
                    $uid = (int) ($row['user_id'] ?? 0);
                    $items[] = [
                        'label' => self::memberLabel((string) ($row['user_display_name'] ?? ''), '', $uid),
                        'since' => (string) ($row['starts_on'] ?? ''),
                        'days_open' => (int) ($row['days_open'] ?? 0),
                        'ends_on' => trim((string) ($row['ends_on'] ?? '')),
                        'href' => effectifs_workspace_url('membres/' . $uid) . '#absences',
                    ];
                }
            }

            return ['count' => $count, 'items' => $items, 'days' => $absenceDays];
        });

        $this->collect($facts, 'inactive', function () use ($tenantId, $inactivityDays, $limit): array {
            $count = $this->alerts->countInactiveMembers($tenantId, $inactivityDays);
            $items = [];
            if ($count > 0) {
                foreach ($this->alerts->listInactiveMembers($tenantId, $inactivityDays, $limit) as $row) {
                    $uid = (int) ($row['id'] ?? 0);
                    $items[] = [
                        'label' => self::memberLabel((string) ($row['display_name'] ?? ''), (string) ($row['callsign'] ?? ''), $uid),
                        'last_login' => trim((string) ($row['last_login_at'] ?? '')),
                        'href' => effectifs_workspace_url('membres/' . $uid),
                    ];
                }
            }

            return ['count' => $count, 'items' => $items, 'days' => $inactivityDays];
        });

        $this->collect($facts, 'incomplete', function () use ($tenantId, $limit): array {
            $pack = $this->gaps->listForTenant($tenantId, 10);
            $items = [];
            foreach (array_slice(is_array($pack['rows'] ?? null) ? $pack['rows'] : [], 0, $limit) as $row) {
                $uid = (int) ($row['id'] ?? 0);
                $issueKeys = is_array($row['issue_keys'] ?? null) ? $row['issue_keys'] : [];
                $items[] = [
                    'label' => self::memberLabel((string) ($row['display_name'] ?? ''), (string) ($row['callsign'] ?? ''), $uid),
                    'issues' => $uid > 0 ? self::issueLinks($uid, $issueKeys) : [],
                    'href' => effectifs_workspace_url('membres/' . $uid),
                ];
            }

            return ['count' => (int) ($pack['total'] ?? 0), 'items' => $items];
        });

        $this->collect($facts, 'missing_command', function () use ($tenantId, $limit): array {
            $rows = $this->alerts->listMissingCommand($tenantId);
            $href = effectifs_workspace_url('affectations') . '?vue=unites';
            $items = [];
            foreach (array_slice($rows, 0, $limit) as $row) {
                $items[] = ['label' => (string) ($row['name'] ?? ''), 'expected' => (string) ($row['expected'] ?? ''), 'href' => $href];
            }

            return ['count' => count($rows), 'items' => $items, 'href' => $href];
        });

        $this->collect($facts, 'no_leader', fn (): array => [
            'count' => $this->units->countWithoutCommander($tenantId),
            'href' => effectifs_workspace_url('chaine'),
        ]);

        $this->collect($facts, 'adequacy', function () use ($tenantId): array {
            $result = (new OrgAdequacyService())->evaluate($tenantId);
            if (empty($result['enabled'])) {
                return ['count' => 0];
            }
            $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];

            return ['count' => (int) ($summary['warn'] ?? 0), 'href' => effectifs_workspace_url('chaine') . '?ecarts=1#eff-adequacy'];
        });

        $this->collect($facts, 'duplicates', function () use ($tenantId): array {
            $scan = (new PersonnelDuplicateDetectionService())->scan($tenantId);

            return ['count' => (int) ($scan['group_count'] ?? 0), 'href' => effectifs_workspace_url('doublons')];
        });

        $this->collect($facts, 'no_unit', fn (): array => [
            'count' => $this->users->countListForTenant($tenantId, null, null, null, true, true, null),
            'href' => effectifs_workspace_url() . '?sans_affectation=1',
        ]);
        $this->collect($facts, 'no_role', fn (): array => [
            'count' => $this->users->countListForTenant($tenantId, null, null, null, true, null, true),
            'href' => effectifs_workspace_url() . '?sans_role=1',
        ]);
        $this->collect($facts, 'pending_accounts', fn (): array => [
            'count' => $this->users->countListForTenant($tenantId, null, 'pending_verification', null, true),
            'href' => effectifs_workspace_url() . '?status=pending_verification',
        ]);

        $this->collect($facts, 'career', function () use ($tenantId, $limit): array {
            $rows = $this->alerts->listCareerDiscrepancies($tenantId);
            $items = [];
            foreach (array_slice($rows, 0, $limit) as $gap) {
                $items[] = [
                    'label' => (string) ($gap['member_label'] ?? ''),
                    'source' => (string) ($gap['source'] ?? ''),
                    'detail' => (string) ($gap['detail'] ?? ''),
                    'href' => effectifs_workspace_url('membres/' . (int) ($gap['user_id'] ?? 0)),
                ];
            }

            return ['count' => count($rows), 'items' => $items];
        });

        return self::assemble($facts);
    }

    /**
     * Range les faits recueillis par section. Pure : sans base de données, donc testable.
     *
     * @param array<string, array{count?: int, items?: list<array<string, mixed>>, href?: string, gate?: string, days?: int}> $facts
     * @return array{
     *   total: int,
     *   attention: int,
     *   sections: array<string, array{id: string, slug: string, label: string, lead: string, count: int, cards: list<array<string, mixed>>, clear: list<string>}>
     * }
     */
    public static function assemble(array $facts): array
    {
        $sections = [];
        foreach (self::SECTIONS as $id => $meta) {
            $sections[$id] = ['id' => $id, 'slug' => $meta['slug'], 'label' => $meta['label'], 'lead' => $meta['lead'], 'count' => 0, 'cards' => [], 'clear' => []];
        }
        foreach (self::CARDS as $id => $card) {
            $fact = $facts[$id] ?? [];
            $count = max(0, (int) ($fact['count'] ?? 0));
            $section = $card['section'];
            if ($count < 1) {
                $sections[$section]['clear'][] = $card['title'];
                continue;
            }
            $items = is_array($fact['items'] ?? null) ? array_values($fact['items']) : [];
            $sections[$section]['cards'][] = [
                'id' => $id,
                'section' => $section,
                'title' => $card['title'],
                'text' => $card['text'],
                'cta' => $card['cta'],
                'tone' => $card['tone'],
                'count' => $count,
                'href' => (string) ($fact['href'] ?? ''),
                'gate' => (string) ($fact['gate'] ?? ''),
                'days' => (int) ($fact['days'] ?? 0),
                'items' => $items,
                'more' => max(0, $count - count($items)),
            ];
            $sections[$section]['count'] += $count;
        }

        return [
            // Pastille du menu : seulement ce qui demande une action maintenant.
            'total' => $sections['now']['count'],
            'attention' => $sections['now']['count'] + $sections['watch']['count'],
            'sections' => $sections,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $facts
     * @param callable(): array<string, mixed> $read
     */
    private function collect(array &$facts, string $id, callable $read): void
    {
        try {
            $facts[$id] = $read();
        } catch (\Throwable $e) {
            error_log('À traiter : « ' . $id . ' » indisponible : ' . $e->getMessage());
            $facts[$id] = ['count' => 0];
        }
    }

    /**
     * Raccourcis vers la partie de la fiche qui corrige chaque manque.
     *
     * @param list<mixed> $issueKeys
     * @return list<array{key: string, label: string, href: string}>
     */
    private static function issueLinks(int $userId, array $issueKeys): array
    {
        $anchors = [
            'function' => 'eff-dlg-jobs',
            'rank' => 'grade',
            'role' => 'acces',
            'operator_image' => 'eff-dlg-portrait',
        ];
        $base = effectifs_workspace_url('membres/' . $userId);
        $out = [];
        foreach ($issueKeys as $key) {
            $key = (string) $key;
            if (!isset(PersonnelProfileGapScanService::ISSUE_LABELS[$key])) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => PersonnelProfileGapScanService::ISSUE_LABELS[$key],
                'href' => $base . '#' . ($anchors[$key] ?? 'dossier'),
            ];
        }

        return $out;
    }

    private static function memberLabel(string $displayName, string $callsign, int $userId): string
    {
        $displayName = trim($displayName);
        $callsign = trim($callsign);
        if ($displayName !== '') {
            return $displayName;
        }
        if ($callsign !== '') {
            return $callsign;
        }

        return $userId > 0 ? ('Membre n° ' . $userId) : 'Membre';
    }
}
