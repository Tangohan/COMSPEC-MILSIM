<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakRealismRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Controllers\Web\AtakFirstLinkController;
use App\Controllers\Web\PersonnelController;
use App\Controllers\Web\RhWorkspaceController;

/**
 * Pages personnelles sous /back-office/ma-situation/* (coque Athena, sans redirection portail).
 */
final class MemberSituationController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?AtakRealismRepository $realism = null,
        private ?UserRepository $userRepository = null,
        private ?AuditService $auditService = null,
        private ?QualificationAwardRepository $awards = null,
        private ?PersonnelAssignmentRepository $assignments = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->realism ??= Container::get(AtakRealismRepository::class);
        $this->userRepository ??= Container::get(UserRepository::class);
        $this->auditService ??= Container::get(AuditService::class);
        $this->awards ??= Container::get(QualificationAwardRepository::class);
        $this->assignments ??= Container::get(PersonnelAssignmentRepository::class);
    }

    public function liaisonAtak(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;

        $terminals = $this->realism->listPhysicalTerminalsForUser($tenantId, $userId);

        return Response::view('layout.main', $this->boShell([
            'title' => 'Ma liaison ATAK',
            'content' => 'admin.member_situation.liaison_atak',
            'boPageTitle' => 'Ma liaison ATAK',
            'boPageKicker' => 'OPÉRATEUR · LIAISON',
            'boPageSubtitle' => 'Terminaux associés à votre compte, certificat de liaison et actions utiles.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'terminals' => $terminals,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]));
    }

    public function appareils(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;

        return Response::view('layout.main', $this->boShell([
            'title' => 'Mes appareils ATAK',
            'content' => 'admin.member_situation.appareils',
            'boPageTitle' => 'Mes appareils ATAK',
            'boPageKicker' => 'OPÉRATEUR · LIAISON',
            'boPageSubtitle' => 'Retirez un téléphone ou une tablette qui n’est plus le vôtre.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'devices' => $this->realism->listPhysicalTerminalsForUser($tenantId, $userId),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]));
    }

    public function revokeAppareil(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;

        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/ma-situation/appareils'));
        }

        $terminalId = (int) $request->input('terminal_id', 0);
        $ok = $this->realism->revokePhysicalTerminalForUser($tenantId, $userId, $terminalId);
        if ($ok) {
            $this->auditService->log(
                AuditAction::SECURITY_EVENT,
                $tenantId,
                $userId,
                'atak_terminal',
                $terminalId,
                null,
                'terminal_revoked_by_owner'
            );
            Session::flash('success', 'Cet appareil n’est plus autorisé pour votre compte.');
        } else {
            Session::flash('error', 'Impossible de retirer cet appareil.');
        }

        return Response::redirect(url('back-office/ma-situation/appareils'));
    }

    public function premiereLiaison(Request $request, array $params = []): Response
    {
        return Container::get(AtakFirstLinkController::class)->index($request, $params);
    }

    public function maFiche(Request $request, array $params = []): Response
    {
        return Container::get(PersonnelController::class)->me($request, $params);
    }

    public function mesDemarches(Request $request, array $params = []): Response
    {
        return Container::get(RhWorkspaceController::class)->index($request, $params);
    }

    public function unite(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, , $userId] = $ctx;

        $assignments = [];
        try {
            $assignments = $this->assignments->listActiveForUserResolved($userId);
        } catch (\Throwable) {
            $assignments = [];
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Mon unité',
            'content' => 'admin.member_situation.unite',
            'boPageTitle' => 'Mon unité',
            'boPageKicker' => 'OPÉRATEUR · AFFECTATION',
            'boPageSubtitle' => 'Votre affectation actuelle dans la communauté.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'assignments' => $assignments,
        ]));
    }

    public function qualifications(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;

        $awards = [];
        try {
            $awards = $this->awards->listForUser($userId, $tenantId);
        } catch (\Throwable) {
            $awards = [];
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Mes qualifications',
            'content' => 'admin.member_situation.qualifications',
            'boPageTitle' => 'Mes qualifications',
            'boPageKicker' => 'OPÉRATEUR · FORMATIONS',
            'boPageSubtitle' => 'Qualifications et brevets enregistrés sur votre dossier.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'awards' => $awards,
        ]));
    }

    public function downloadBrevet(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;

        $awardId = (int) ($params['awardId'] ?? 0);
        $award = $this->awards->find($awardId, $tenantId);
        if ($award === null || (int) ($award['user_id'] ?? 0) !== $userId) {
            Session::flash('error', 'Ce brevet n’est pas disponible.');

            return Response::redirect(url('back-office/ma-situation/qualifications'));
        }
        if (empty($award['certificate_document_path'])) {
            Session::flash('error', 'Aucun fichier de brevet n’est encore associé à cette qualification.');

            return Response::redirect(url('back-office/ma-situation/qualifications'));
        }
        $abs = base_path('storage/uploads/' . ltrim((string) $award['certificate_document_path'], '/'));
        if (!is_file($abs)) {
            Session::flash('error', 'Fichier de brevet introuvable.');

            return Response::redirect(url('back-office/ma-situation/qualifications'));
        }
        $downloadName = 'brevet-' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($award['certificate_number'] ?? $awardId)) . '.pdf';
        $response = new Response();
        $response->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
            ->setBodyStream(static function () use ($abs): void {
                $fh = fopen($abs, 'rb');
                if ($fh !== false) {
                    fpassthru($fh);
                    fclose($fh);
                }
            });

        return $response;
    }

    /**
     * @return array{0: array<string, mixed>, 1: int, 2: int}|Response
     */
    private function requireUser(): array|Response
    {
        $user = $this->authService->user();
        if (!$user) {
            Session::flash('error', 'Connectez-vous pour continuer.');

            return Response::redirect(url('login'));
        }
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? Session::get('tenant_id') ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $fresh = $this->userRepository->findById($userId, $tenantId);
        if (is_array($fresh)) {
            $user = array_merge($user, $fresh);
        }

        return [$user, $tenantId, $userId];
    }

    /**
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    private function boShell(array $vars): array
    {
        return array_merge($vars, [
            'isBackOfficeShell' => true,
            'boPageGroup' => (string) ($vars['boPageGroup'] ?? 'Opérateur'),
            'boSkipPageHead' => false,
        ]);
    }
}
