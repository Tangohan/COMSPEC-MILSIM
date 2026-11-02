<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Personnel\PilotFleetService;
use App\Services\Personnel\PilotLogbookService;
use App\Services\Personnel\PilotQualificationService;
use App\Services\Personnel\PilotSyllabusStepService;
use App\Services\Platform\FeatureGateService;
use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog;
use Throwable;

/**
 * Back-office > Référentiels > Qualifications > Cursus pilote : paliers inspirés des vrais cursus, attribués ou
 * proposés aux instructeurs d'après les heures de vol relevées en jeu. Module des formules payantes.
 */
final class PilotSyllabusController
{
    private const BASE = 'back-office/referentiels/qualifications/cursus-pilote';

    public function __construct(
        private PilotQualificationService $service,
        private FeatureGateService $features,
        private ?PilotLogbookService $logbook = null,
        private ?PilotFleetService $fleet = null,
        private ?PilotSyllabusStepService $steps = null,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $ready = $this->service->schemaReady();
        $board = $ready ? $this->service->board($tenantId) : ['rules' => [], 'members' => [], 'flown_families' => [], 'pending_total' => 0, 'eligible_total' => 0];
        $fleetReady = $this->fleet !== null && $this->fleet->schemaReady();
        $stepsReady = $this->steps !== null && $this->steps->schemaReady();
        $fleet = $this->fleet?->fleet($tenantId) ?? PilotFleet::empty();

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.pilot_syllabus',
            'backOfficePageCss' => ['referentiel-qualifications.css', 'referentiel-pilot-syllabus.css', 'espace-aerien.css'],
            'boSkipPageHead' => true,
            'title' => 'Cursus pilote',
            'boPageKicker' => 'PERSONNEL · QUALIFICATIONS',
            'boPageTitle' => 'Cursus pilote',
            'boPageSubtitle' => 'Comme dans une vraie école de pilotage : chaque palier demande des heures de vol relevées en jeu, puis la validation d’un instructeur.',
            'schemaReady' => $ready,
            'allowed' => $this->features->allowsWithCatalogDefault($tenantId, PilotQualificationService::FEATURE),
            'board' => $board,
            'tiers' => PilotSyllabusCatalog::tiers(),
            'tracks' => PilotSyllabusCatalog::tracks(),
            'knownFamilies' => [
                PilotSyllabusCatalog::TRACK_HELI => PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_HELI),
                PilotSyllabusCatalog::TRACK_PLANE => PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_PLANE),
            ],
            'actorId' => (int) Session::get('user_id'),
            'fleetReady' => $fleetReady,
            'fleetAircraft' => $this->fleet !== null ? $this->fleet->aircraft($tenantId) : [],
            'fleetGroups' => $fleet->groups(true),
            'fleetActiveGroups' => $fleet->groups(),
            'fleetCustomGroups' => array_keys(array_filter($fleet->groups(true), static fn (string $_l, string $k): bool => $fleet->isCustomGroup($k), ARRAY_FILTER_USE_BOTH)),
            'fleetArchivedGroups' => array_keys(array_diff_key($fleet->groups(true), $fleet->groups())),
            'fleetRows' => $fleet->aircraftRows(),
            'fleetClasses' => $fleet->classMap(),
            'stepsReady' => $stepsReady,
            'customSteps' => $stepsReady ? $this->steps->board($tenantId) : [],
            'freeDefinitions' => $stepsReady ? $this->steps->freeDefinitions($tenantId) : [],
            'definitionNames' => $stepsReady ? $this->steps->definitionNames($tenantId) : [],
            'scopeOptions' => $stepsReady ? $this->steps->scopeOptions($tenantId) : [],
            'declaredFlights' => $this->logbook !== null && $this->logbook->schemaReady() ? $this->logbook->declared($tenantId) : [],
            'canReviewTrack' => $this->logbook === null ? [] : [
                PilotSyllabusCatalog::TRACK_HELI => $this->logbook->canReview($tenantId, PilotSyllabusCatalog::TRACK_HELI, (int) Session::get('user_id')) || is_platform_admin(),
                PilotSyllabusCatalog::TRACK_PLANE => $this->logbook->canReview($tenantId, PilotSyllabusCatalog::TRACK_PLANE, (int) Session::get('user_id')) || is_platform_admin(),
            ],
        ]);
    }

    public function install(Request $request, array $params = []): Response
    {
        return $this->guarded($request, function (int $tenantId) use ($request): Response {
            $families = array_values(array_filter(array_map('strval', (array) $request->input('families', []))));
            $newName = trim((string) $request->input('new_name', ''));
            $newClass = trim((string) $request->input('new_class', ''));
            if ($newName !== '' || $newClass !== '') {
                if ($this->fleet === null || !$this->fleet->schemaReady()) {
                    throw new \RuntimeException('Les appareils de la communauté arrivent avec la prochaine mise à jour du site (migration à lancer).');
                }
                $families[] = $this->fleet->saveAircraft($tenantId, [
                    'name' => $newName !== '' ? $newName : $newClass,
                    'classes' => $newClass,
                    'track' => (string) $request->input('track', PilotSyllabusCatalog::TRACK_HELI),
                    'group_key' => (string) $request->input('new_group', ''),
                ], null, $this->actorId());
            }
            $labels = [];
            foreach ((array) $request->input('family_labels', []) as $key => $label) {
                $labels[(string) $key] = mb_substr(trim((string) $label), 0, 120);
            }
            $r = $this->service->install(
                $tenantId,
                (string) $request->input('track', PilotSyllabusCatalog::TRACK_HELI),
                $families,
                array_values(array_map('strval', (array) $request->input('tiers', []))),
                $labels,
                $this->actorId()
            );
            Session::flash('success', $r['created'] . ' qualification(s) créée(s)' . ($r['reused'] > 0 ? ', ' . $r['reused'] . ' déjà présente(s) reprise(s)' : '') . '. Les heures de vol sont évaluées à chaque passage automatique, ou tout de suite avec « Évaluer maintenant ».');

            return Response::redirect(url(self::BASE));
        });
    }

    public function updateRule(Request $request, array $params = []): Response
    {
        $ruleId = (int) ($params['ruleId'] ?? 0);

        return $this->guarded($request, function (int $tenantId) use ($request, $ruleId): Response {
            $this->service->updateRule(
                $tenantId,
                $ruleId,
                self::hours($request->input('min_scope_hours', '0')),
                self::hours($request->input('min_track_hours', '0')),
                (string) $request->input('award_mode', PilotSyllabusCatalog::MODE_PROPOSE),
                (string) $request->input('is_enabled', '') === '1',
                $request->input('min_cond_hours', null) !== null ? self::hours($request->input('min_cond_hours', '0')) : null
            );
            Session::flash('success', 'Palier mis à jour.');

            return Response::redirect(url(self::BASE) . '#palier-' . $ruleId);
        });
    }

    public function evaluate(Request $request, array $params = []): Response
    {
        return $this->guarded($request, function (int $tenantId) use ($request): Response {
            $r = $this->service->runAuto($tenantId);
            $c = $this->steps !== null ? $this->steps->evaluate($tenantId) : ['awarded' => 0, 'proposed' => 0, 'renewed' => 0];
            Session::flash('success', sprintf(
                'Évaluation faite : %d qualification(s) attribuée(s), %d proposée(s) aux instructeurs, %d remise(s) à jour par un vol récent.',
                $r['awarded'] + $c['awarded'],
                $r['proposed'] + $c['proposed'],
                $r['practiced'] + $c['renewed']
            ));

            return Response::redirect(url(self::back($request)));
        });
    }

    public function decide(Request $request, array $params = []): Response
    {
        $awardId = (int) ($params['awardId'] ?? 0);

        return $this->guarded($request, function (int $tenantId) use ($request, $awardId): Response {
            $approve = (string) $request->input('decision', '') === 'valider';
            $this->service->decide($tenantId, $awardId, (int) $this->actorId(), $approve, is_platform_admin());
            Session::flash('success', $approve ? 'Palier validé : la qualification est obtenue.' : 'Palier ajourné. Le membre pourra être reproposé depuis sa fiche.');

            return Response::redirect(url(self::back($request)));
        });
    }

    /** Crée ou règle un appareil (nom, filière, famille, classes Arma, image). */
    public function saveAircraft(Request $request, array $params = []): Response
    {
        return $this->guarded($request, function (int $tenantId) use ($request): Response {
            $key = $this->fleetService()->saveAircraft($tenantId, [
                'aircraft_key' => (string) $request->input('aircraft_key', ''),
                'name' => (string) $request->input('name', ''),
                'track' => (string) $request->input('track', ''),
                'group_key' => (string) $request->input('group_key', ''),
                'classes' => (string) $request->input('classes', ''),
                'archived' => (string) $request->input('archived', '') === '1',
                'remove_image' => (string) $request->input('remove_image', '') === '1',
            ], isset($_FILES['image']) && is_array($_FILES['image']) ? $_FILES['image'] : null, $this->actorId());
            Session::flash('success', 'Appareil enregistré.');

            return Response::redirect(url(self::BASE) . '#appareil-' . rawurlencode(str_replace(':', '-', $key)));
        });
    }

    /** Crée, renomme, range ou archive une famille d'appareils. */
    public function saveGroup(Request $request, array $params = []): Response
    {
        return $this->guarded($request, function (int $tenantId) use ($request): Response {
            $this->fleetService()->saveGroup($tenantId, [
                'group_key' => (string) $request->input('group_key', ''),
                'name' => (string) $request->input('name', ''),
                'sort_order' => $request->input('sort_order', null),
                'archived' => (string) $request->input('archived', '') === '1',
            ], $this->actorId());
            Session::flash('success', 'Famille enregistrée.');

            return Response::redirect(url(self::BASE) . '#flotte');
        });
    }

    /** Crée (sans identifiant) ou modifie un palier de la communauté. */
    public function saveStep(Request $request, array $params = []): Response
    {
        $stepId = isset($params['stepId']) ? (int) $params['stepId'] : null;

        return $this->guarded($request, function (int $tenantId) use ($request, $stepId): Response {
            $id = $this->stepService()->save($tenantId, $stepId, $request->all(), $this->actorId());
            Session::flash('success', $stepId === null ? 'Palier créé. Il est évalué à chaque passage automatique, ou tout de suite avec « Évaluer maintenant ».' : 'Palier enregistré.');

            return Response::redirect(url(self::BASE) . '#etape-' . $id);
        });
    }

    /** Dupliquer, archiver, réactiver, monter ou descendre un palier de la communauté. */
    public function stepAction(Request $request, array $params = []): Response
    {
        $stepId = (int) ($params['stepId'] ?? 0);

        return $this->guarded($request, function (int $tenantId) use ($request, $stepId): Response {
            $svc = $this->stepService();
            $action = (string) $request->input('action', '');
            $id = $stepId;
            switch ($action) {
                case 'dupliquer':
                    $id = $svc->duplicate($tenantId, $stepId, $this->actorId());
                    break;
                case 'archiver':
                case 'reactiver':
                    $svc->setArchived($tenantId, $stepId, $action === 'archiver', $this->actorId());
                    break;
                case 'monter':
                case 'descendre':
                    $svc->move($tenantId, $stepId, $action === 'monter' ? -1 : 1);
                    break;
                default:
                    throw new \RuntimeException('Action inconnue.');
            }
            Session::flash('success', match ($action) {
                'dupliquer' => 'Palier dupliqué : la copie délivre une nouvelle qualification, réglez-la.',
                'archiver' => 'Palier archivé : il n’est plus délivré automatiquement, les titulaires gardent leur qualification.',
                'reactiver' => 'Palier réactivé.',
                default => 'Ordre du parcours mis à jour.',
            });

            return Response::redirect(url(self::BASE) . '#etape-' . $id);
        });
    }

    /** Validation ou refus d'une proposition d'un palier de la communauté. */
    public function decideStep(Request $request, array $params = []): Response
    {
        $awardId = (int) ($params['awardId'] ?? 0);

        return $this->guarded($request, function (int $tenantId) use ($request, $awardId): Response {
            $approve = (string) $request->input('decision', '') === 'valider';
            $this->stepService()->decide($tenantId, $awardId, $approve, (int) $this->actorId(), (int) $request->input('definition_id', 0));
            Session::flash('success', $approve ? 'Palier validé : la qualification est obtenue.' : 'Palier ajourné. Le membre pourra être reproposé depuis sa fiche.');

            $back = self::back($request);

            return Response::redirect(url($back) . ($back === self::BASE ? '#paliers-communaute' : ''));
        });
    }

    private function fleetService(): PilotFleetService
    {
        return $this->fleet ?? throw new \RuntimeException('Les appareils de la communauté ne sont pas disponibles.');
    }

    private function stepService(): PilotSyllabusStepService
    {
        return $this->steps ?? throw new \RuntimeException('Les paliers de la communauté ne sont pas disponibles.');
    }

    /** Page de retour après une action lancée depuis l'Espace aérien (liste fermée), sinon le cursus. */
    private static function back(Request $request): string
    {
        $back = (string) $request->input('retour', '');

        return preg_match('#^back-office/espace-aerien(\?vue=[a-z]+)?$#', $back) === 1 ? $back : self::BASE;
    }

        /** Heures saisies « 2,5 » ou « 2.5 ». */
    private static function hours(mixed $raw): float
    {
        return max(0.0, (float) str_replace(',', '.', trim((string) $raw)));
    }

    /** @param callable(int): Response $fn */
    private function guarded(Request $request, callable $fn): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        if (!$this->features->allowsWithCatalogDefault($tenantId, PilotQualificationService::FEATURE)) {
            return Response::redirect(url('platform/upgrade') . '?from=' . PilotQualificationService::FEATURE);
        }
        try {
            return $fn($tenantId);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url(self::BASE));
        }
    }

    private function actorId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }
}
