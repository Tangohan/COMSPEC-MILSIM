<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PilotQualificationRuleRepository;
use App\Services\Personnel\PilotLogbookService;
use App\Services\Personnel\PilotQualificationService;
use App\Services\Platform\FeatureGateService;
use App\Support\PilotSyllabusCatalog;
use App\Support\PlanFeatureDenial;
use Throwable;

/**
 * Carnet de vol : « Mon carnet de vol » (Ma situation), carnet d'un membre vu par un instructeur, et contreseing.
 */
final class PilotLogbookController
{
    private const MINE = 'back-office/ma-situation/carnet-de-vol';
    private const SYLLABUS = 'back-office/referentiels/qualifications/cursus-pilote';

    public function __construct(
        private PilotLogbookService $logbook,
        private PilotQualificationRuleRepository $rules,
        private FeatureGateService $features,
    ) {
    }

    public function mine(Request $request, array $params = []): Response
    {
        [$tenantId, $userId] = $this->ids();
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->features->allowsWithCatalogDefault($tenantId, PilotQualificationService::FEATURE)) {
            return PlanFeatureDenial::upgradeView(PilotQualificationService::FEATURE);
        }

        return $this->page($tenantId, $userId, true, 'Mon carnet de vol', 'OPÉRATEUR · CARNET DE VOL', 'Vos vols relevés en jeu, et ceux que vous notez vous-même. Un instructeur contresigne ce que vous déclarez.');
    }

    public function member(Request $request, array $params = []): Response
    {
        [$tenantId] = $this->ids();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $memberId = (int) ($params['userId'] ?? 0);
        $name = $this->rules->memberNames($tenantId, [$memberId])[$memberId] ?? null;
        if ($name === null) {
            Session::flash('error', 'Membre introuvable dans la communauté.');

            return Response::redirect(url(self::SYLLABUS));
        }
        $who = trim($name['display_name']) !== '' ? $name['display_name'] : $name['callsign'];

        return $this->page($tenantId, $memberId, false, 'Carnet de vol de ' . $who, 'PERSONNEL · CARNET DE VOL', 'Vols relevés en jeu et vols déclarés par le pilote. Contresignez ce qui a été vérifié en vol.');
    }

    private function page(int $tenantId, int $userId, bool $mine, string $title, string $kicker, string $subtitle): Response
    {
        $ready = $this->logbook->schemaReady();
        $entries = $ready ? $this->logbook->entries($tenantId, $userId) : [];
        $legacy = $this->rules->pilotRowsByUser($tenantId)[$userId] ?? [];
        $actorId = (int) Session::get('user_id');

        return Response::view('layout.main', [
            'content' => 'admin.member_situation.pilot_logbook',
            'backOfficePageCss' => ['referentiel-qualifications.css', 'referentiel-pilot-syllabus.css'],
            'title' => $title,
            'boPageKicker' => $kicker,
            'boPageTitle' => $title,
            'boPageSubtitle' => $subtitle,
            'schemaReady' => $ready,
            'mine' => $mine,
            'memberId' => $userId,
            'entries' => $entries,
            'summary' => PilotLogbookService::summary($entries, $legacy),
            'canReview' => [
                PilotSyllabusCatalog::TRACK_HELI => !$mine && ($this->logbook->canReview($tenantId, PilotSyllabusCatalog::TRACK_HELI, $actorId) || is_platform_admin()),
                PilotSyllabusCatalog::TRACK_PLANE => !$mine && ($this->logbook->canReview($tenantId, PilotSyllabusCatalog::TRACK_PLANE, $actorId) || is_platform_admin()),
            ],
            'knownFamilies' => PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_HELI) + PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_PLANE),
            'crewRoles' => PilotSyllabusCatalog::crewRoles(),
        ]);
    }

    public function add(Request $request, array $params = []): Response
    {
        return $this->mineGuarded($request, function (int $tenantId, int $userId) use ($request): Response {
            $this->logbook->add($tenantId, $userId, $this->form($request));
            Session::flash('success', 'Vol ajouté à votre carnet. Il comptera pour le cursus une fois contresigné par un instructeur.');

            return Response::redirect(url(self::MINE));
        });
    }

    public function update(Request $request, array $params = []): Response
    {
        $entryId = (int) ($params['entryId'] ?? 0);

        return $this->mineGuarded($request, function (int $tenantId, int $userId) use ($request, $entryId): Response {
            $this->logbook->update($tenantId, $userId, $entryId, $this->form($request));
            Session::flash('success', 'Vol mis à jour.');

            return Response::redirect(url(self::MINE) . '#vol-' . $entryId);
        });
    }

    public function delete(Request $request, array $params = []): Response
    {
        $entryId = (int) ($params['entryId'] ?? 0);

        return $this->mineGuarded($request, function (int $tenantId, int $userId) use ($entryId): Response {
            $this->logbook->delete($tenantId, $userId, $entryId);
            Session::flash('success', 'Vol retiré de votre carnet.');

            return Response::redirect(url(self::MINE));
        });
    }

    public function review(Request $request, array $params = []): Response
    {
        $entryId = (int) ($params['entryId'] ?? 0);
        $back = (string) $request->input('retour', '');
        $target = preg_match('#^back-office/referentiels/qualifications/cursus-pilote(/carnet/\d+)?$#', $back) === 1 ? $back : self::SYLLABUS;
        [$tenantId, $actorId] = $this->ids();
        if ($tenantId < 1 || $actorId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url($target));
        }
        try {
            $approve = (string) $request->input('decision', '') === 'contresigner';
            $this->logbook->review($tenantId, $entryId, $actorId, $approve, trim((string) $request->input('note', '')), is_platform_admin());
            Session::flash('success', $approve ? 'Vol contresigné : ses heures comptent pour le cursus.' : 'Vol refusé : le pilote voit votre motif et peut le corriger.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url($target));
    }

    /** @return array<string, mixed> */
    private function form(Request $request): array
    {
        $out = [];
        foreach (['flown_on', 'duration', 'aircraft', 'aircraft_other', 'vehicle_kind', 'crew_role', 'night', 'ifr', 'formation', 'landings', 'remarks', 'mission'] as $k) {
            $out[$k] = $request->input($k, '');
        }

        return $out;
    }

    /** @return array{0: int, 1: int} */
    private function ids(): array
    {
        return [(int) Session::get('tenant_id'), (int) Session::get('user_id')];
    }

    /** @param callable(int, int): Response $fn */
    private function mineGuarded(Request $request, callable $fn): Response
    {
        [$tenantId, $userId] = $this->ids();
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::MINE));
        }
        if (!$this->features->allowsWithCatalogDefault($tenantId, PilotQualificationService::FEATURE)) {
            return Response::redirect(url('platform/upgrade') . '?from=' . PilotQualificationService::FEATURE);
        }
        try {
            return $fn($tenantId, $userId);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url(self::MINE));
        }
    }
}
