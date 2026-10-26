<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Personnel\PilotLogbookService;
use App\Services\Personnel\PilotQualificationService;
use App\Services\Platform\FeatureGateService;
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

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.pilot_syllabus',
            'backOfficePageCss' => ['referentiel-qualifications.css', 'referentiel-pilot-syllabus.css'],
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
        return $this->guarded($request, function (int $tenantId): Response {
            $r = $this->service->runAuto($tenantId);
            Session::flash('success', sprintf(
                'Évaluation faite : %d qualification(s) attribuée(s), %d proposée(s) aux instructeurs, %d remise(s) à jour par un vol récent.',
                $r['awarded'],
                $r['proposed'],
                $r['practiced']
            ));

            return Response::redirect(url(self::BASE));
        });
    }

    public function decide(Request $request, array $params = []): Response
    {
        $awardId = (int) ($params['awardId'] ?? 0);

        return $this->guarded($request, function (int $tenantId) use ($request, $awardId): Response {
            $approve = (string) $request->input('decision', '') === 'valider';
            $this->service->decide($tenantId, $awardId, (int) $this->actorId(), $approve, is_platform_admin());
            Session::flash('success', $approve ? 'Palier validé : la qualification est obtenue.' : 'Palier ajourné. Le membre pourra être reproposé depuis sa fiche.');

            return Response::redirect(url(self::BASE));
        });
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
