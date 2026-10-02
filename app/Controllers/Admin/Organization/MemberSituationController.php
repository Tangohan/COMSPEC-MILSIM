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
use App\Controllers\Web\CommunityEventsController;
use App\Controllers\Web\PersonnelController;
use App\Controllers\Web\RhWorkspaceController;
use App\Services\Personnel\OperatorDocumentVaultService;
use App\Services\Personnel\QualificationBadgeStorageService;
use App\Services\Personnel\QualificationCertificatePdfService;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;

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
        private ?OperatorDocumentVaultService $vault = null,
        private ?QualificationCertificatePdfService $certificates = null,
        private ?QualificationTemporalStatusService $temporal = null,
        private ?QualificationBadgeStorageService $badges = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->realism ??= Container::get(AtakRealismRepository::class);
        $this->userRepository ??= Container::get(UserRepository::class);
        $this->auditService ??= Container::get(AuditService::class);
        $this->awards ??= Container::get(QualificationAwardRepository::class);
        $this->assignments ??= Container::get(PersonnelAssignmentRepository::class);
        $this->vault ??= new OperatorDocumentVaultService();
        $this->certificates ??= Container::get(QualificationCertificatePdfService::class);
        $this->temporal ??= Container::get(QualificationTemporalStatusService::class);
        $this->badges ??= Container::get(QualificationBadgeStorageService::class);
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
            'boPageKicker' => 'OPÉRATEUR · MA LIAISON ATAK',
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
            'boPageKicker' => 'OPÉRATEUR · MES APPAREILS ATAK',
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
        [$user, $tenantId, $userId] = $ctx;

        $assignments = [];
        try {
            $assignments = $this->assignments->listActiveForUserResolved($userId);
        } catch (\Throwable) {
            $assignments = [];
        }

        $unitRepo = new \App\Repositories\UnitRepository();
        $hierarchy = [];
        try {
            $hierarchy = $unitRepo->hierarchyMetaByUnitId($tenantId);
        } catch (\Throwable) {
            $hierarchy = [];
        }

        $enriched = [];
        foreach ($assignments as $row) {
            if (!is_array($row)) {
                continue;
            }
            $unitId = (int) ($row['unit_id'] ?? 0);
            $meta = $hierarchy[$unitId] ?? null;
            $row['assignment_path'] = is_array($meta) ? trim((string) ($meta['path'] ?? '')) : '';
            $row['hierarchy_depth'] = is_array($meta) ? (int) ($meta['depth'] ?? 0) : 0;
            $enriched[] = $row;
        }

        $primary = null;
        foreach ($enriched as $row) {
            if (!empty($row['is_primary'])) {
                $primary = $row;
                break;
            }
        }
        if ($primary === null && $enriched !== []) {
            $primary = $enriched[0];
        }

        $primaryUnitId = (int) ($primary['unit_id'] ?? 0);
        $unit = null;
        $commander = null;
        $teammates = [];
        $subUnits = [];
        if ($primaryUnitId > 0) {
            try {
                $unit = $unitRepo->findById($primaryUnitId, $tenantId);
            } catch (\Throwable) {
                $unit = null;
            }
            $commanderId = (int) (($unit['commander_user_id'] ?? null) ?: ($primary['commander_user_id'] ?? 0));
            if ($commanderId > 0) {
                try {
                    $commander = $this->userRepository->findById($commanderId, $tenantId);
                } catch (\Throwable) {
                    $commander = null;
                }
            }
            try {
                $byUnit = $this->assignments->listActiveMembersByUnitForTenant($tenantId);
                $teammates = is_array($byUnit[$primaryUnitId] ?? null) ? $byUnit[$primaryUnitId] : [];
            } catch (\Throwable) {
                $teammates = [];
            }
            try {
                $subUnits = $unitRepo->childrenForTenant($tenantId, $primaryUnitId);
            } catch (\Throwable) {
                $subUnits = [];
            }
        }

        $secondary = [];
        $primaryKey = null;
        if ($primary !== null) {
            $pid = (int) ($primary['id'] ?? 0);
            $primaryKey = $pid > 0
                ? 'id:' . $pid
                : 'u:' . $primaryUnitId . '|r:' . trim((string) ($primary['role_name'] ?? ''));
        }
        foreach ($enriched as $row) {
            $rid = (int) ($row['id'] ?? 0);
            $key = $rid > 0
                ? 'id:' . $rid
                : 'u:' . (int) ($row['unit_id'] ?? 0) . '|r:' . trim((string) ($row['role_name'] ?? ''));
            if ($primaryKey !== null && $key === $primaryKey) {
                continue;
            }
            $secondary[] = $row;
        }

        $communityName = trim((string) ($user['tenant_name'] ?? ''));
        if ($communityName === '') {
            try {
                $tenant = (new \App\Repositories\TenantRepository())->findById($tenantId);
                $communityName = trim((string) ($tenant['name'] ?? ''));
            } catch (\Throwable) {
                $communityName = '';
            }
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Mon unité',
            'content' => 'admin.member_situation.unite',
            'boPageTitle' => 'Mon unité',
            'boPageKicker' => 'OPÉRATEUR · MON UNITÉ',
            'boPageSubtitle' => 'Votre place dans l’organigramme, vos camarades et votre fonction.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'assignments' => $enriched,
            'primaryAssignment' => $primary,
            'secondaryAssignments' => $secondary,
            'unit' => is_array($unit) ? $unit : null,
            'commander' => is_array($commander) ? $commander : null,
            'teammates' => $teammates,
            'subUnits' => $subUnits,
            'communityName' => $communityName,
            'viewerUserId' => $userId,
        ]));
    }

    public function evenements(Request $request, array $params = []): Response
    {
        $payload = Container::get(CommunityEventsController::class)->buildIndexPayload();
        if ($payload['blocked']) {
            return Response::view('layout.main', $this->boShell(array_merge([
                'title' => 'Événements',
                'content' => 'platform.upgrade',
            ], $payload['vars'])));
        }

        $vue = (string) $request->query('vue', 'a_venir');
        if (!in_array($vue, ['a_venir', 'calendrier', 'passes'], true)) {
            $vue = 'a_venir';
        }
        $mois = (string) $request->query('mois', '');
        if (preg_match('/^\d{4}-\d{2}$/', $mois) !== 1) {
            $mois = date('Y-m');
        }

        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) ($payload['vars']['currentUserId'] ?? 0);
        $repo = Container::get(\App\Repositories\CommunityEventRepository::class);

        // Historique : sert à l’onglet « Passés » et aux indicateurs d’assiduité.
        $past = [];
        try {
            $past = $userId > 0 ? $repo->pastForTenantWithUserRsvp($tenantId, $userId, 50) : [];
        } catch (\Throwable) {
            $past = [];
        }
        $calendar = null;
        if ($vue === 'calendrier' && $userId > 0) {
            try {
                [$from, $to] = \App\Support\EventCalendarMonth::range($mois);
                $calendar = \App\Support\EventCalendarMonth::build($mois, $repo->betweenForTenantWithUserRsvp($tenantId, $userId, $from, $to));
            } catch (\Throwable) {
                $calendar = \App\Support\EventCalendarMonth::build($mois, []);
            }
        }

        return Response::view('layout.main', $this->boShell(array_merge([
            'title' => 'Événements',
            'content' => 'admin.member_situation.evenements',
            'boPageTitle' => 'Événements',
            'boPageKicker' => 'OPÉRATEUR · ÉVÉNEMENTS',
            'boPageSubtitle' => 'Vos prochains rendez-vous, le calendrier de l’unité et votre historique de participation.',
            'backOfficePageCss' => ['back-office-member-events.css'],
            'eventsInBackOffice' => true,
            'boSkipSessionFlashes' => true,
            'memberEventsVue' => $vue,
            'memberEventsPast' => $past,
            'memberEventsCalendar' => $calendar,
        ], $payload['vars'])));
    }


    public function coffre(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;

        $vault = ['items' => [], 'counts' => ['total' => 0, 'hr' => 0, 'brevet' => 0, 'training' => 0], 'sections' => ['hr' => [], 'brevet' => [], 'training' => []]];
        try {
            $vault = $this->vault->collect($tenantId, $userId);
        } catch (\Throwable) {
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Mon coffre',
            'content' => 'admin.member_situation.coffre',
            'boPageTitle' => 'Mon coffre',
            'boPageKicker' => 'OPÉRATEUR · MON COFFRE',
            'boPageSubtitle' => 'Toutes les pièces qui vous concernent : dossier RH, brevets et attestations de formation.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'vault' => $vault,
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

        $enriched = [];
        foreach ($awards as $award) {
            if (!is_array($award)) {
                continue;
            }
            $enriched[] = $this->enrichAwardForOperatorView($award, $user);
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Mes qualifications',
            'content' => 'admin.member_situation.qualifications',
            'boPageTitle' => 'Mes qualifications',
            'boPageKicker' => 'OPÉRATEUR · MES QUALIFICATIONS',
            'boPageSubtitle' => 'Qualifications et brevets enregistrés sur votre dossier.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'awards' => $enriched,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]));
    }

    public function carriere(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;
        $timeline = [];
        $gradeHistory = [];
        try {
            $timeline = Container::get(\App\Services\Personnel\CareerFileService::class)->timeline($tenantId, $userId);
            $advRepo = Container::get(\App\Repositories\AdvancementRepository::class);
            $gradeHistory = $advRepo->tablesReady() ? $advRepo->historyFor($tenantId, $userId) : [];
        } catch (\Throwable) {
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Dossier de carrière',
            'content' => 'admin.member_situation.carriere',
            'boPageTitle' => 'Dossier de carrière',
            'boPageKicker' => 'OPÉRATEUR · CARRIÈRE',
            'boPageSubtitle' => 'Timeline unique : postes, qualifications, décorations et grades.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'timeline' => $timeline,
            'gradeHistory' => $gradeHistory,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]));
    }

    public function decorations(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;
        $rows = [];
        try {
            $rows = Container::get(\App\Repositories\PersonnelAwardRepository::class)->listForPersonnel($tenantId, $userId);
        } catch (\Throwable) {
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Mes décorations',
            'content' => 'admin.member_situation.decorations',
            'boPageTitle' => 'Mes décorations',
            'boPageKicker' => 'OPÉRATEUR · DÉCORATIONS',
            'boPageSubtitle' => 'Citations et décorations enregistrées sur votre dossier — distinctes des qualifications.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'awards' => $rows,
        ]));
    }

    public function dotation(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$user, $tenantId, $userId] = $ctx;
        $rows = [];
        try {
            $rows = Container::get(\App\Repositories\PersonnelEquipmentAssignmentRepository::class)
                ->listForPersonnel($tenantId, $userId);
        } catch (\Throwable) {
        }

        return Response::view('layout.main', $this->boShell([
            'title' => 'Ma dotation',
            'content' => 'admin.member_situation.dotation',
            'boPageTitle' => 'Ma dotation',
            'boPageKicker' => 'OPÉRATEUR · DOTATION',
            'boPageSubtitle' => 'Matériel attribué à votre nom, avec numéro de série et statut.',
            'backOfficePageCss' => ['back-office-member-situation.css'],
            'user' => $user,
            'assignments' => $rows,
        ]));
    }

    public function generateBrevet(Request $request, array $params = []): Response
    {
        $ctx = $this->requireUser();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [, $tenantId, $userId] = $ctx;

        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/ma-situation/qualifications'));
        }

        $awardId = (int) ($params['awardId'] ?? 0);
        $award = $this->awards->find($awardId, $tenantId);
        if ($award === null || (int) ($award['user_id'] ?? 0) !== $userId) {
            Session::flash('error', 'Cette qualification n’est pas disponible.');

            return Response::redirect(url('back-office/ma-situation/qualifications'));
        }

        $admin = QualificationAdminStatus::normalize(
            (string) ($award['admin_status'] ?? $award['status'] ?? '')
        );
        if ($admin !== QualificationAdminStatus::OBTAINED) {
            Session::flash('error', 'Le brevet n’est disponible que pour une qualification obtenue.');

            return Response::redirect(url('back-office/ma-situation/qualifications'));
        }

        try {
            $hadCert = trim((string) ($award['certificate_document_path'] ?? '')) !== '';
            $res = $this->certificates->generate($tenantId, $awardId, $userId);
            $holder = trim((string) ($res['holder_name'] ?? ''));
            $number = (string) ($res['certificate_number'] ?? '');
            if ($hadCert) {
                Session::flash(
                    'success',
                    'Brevet mis à jour'
                    . ($holder !== '' ? ' au nom de ' . $holder : '')
                    . ($number !== '' ? ' (n° ' . $number . ')' : '')
                    . '.'
                );
            } else {
                Session::flash(
                    'success',
                    'Brevet généré'
                    . ($holder !== '' ? ' au nom de ' . $holder : '')
                    . ($number !== '' ? ' (n° ' . $number . ')' : '')
                    . '.'
                );
            }

            return Response::redirect(url('back-office/ma-situation/qualifications/' . $awardId . '/brevet'));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/ma-situation/qualifications'));
        }
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
     * @param array<string, mixed> $award
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function enrichAwardForOperatorView(array $award, array $user = []): array
    {
        $badgeRel = trim((string) ($award['level_badge_path'] ?? ''));
        if ($badgeRel === '') {
            $badgeRel = trim((string) ($award['definition_badge_path'] ?? ''));
        }
        $award['badge_url'] = $badgeRel !== ''
            ? $this->badges->publicUrl($badgeRel)
            : '';

        $category = trim((string) ($award['category_name'] ?? ''));
        $sealLetters = '';
        if ($category !== '') {
            $words = preg_split('/\s+/u', $category) ?: [];
            foreach ($words as $word) {
                $ch = mb_substr(trim((string) $word), 0, 1);
                if ($ch !== '') {
                    $sealLetters .= mb_strtoupper($ch);
                }
                if (mb_strlen($sealLetters) >= 3) {
                    break;
                }
            }
        }
        if ($sealLetters === '') {
            $name = trim((string) ($award['definition_name'] ?? $award['qualification_name'] ?? 'Q'));
            $sealLetters = mb_strtoupper(mb_substr($name, 0, 2));
        }
        $award['seal_letters'] = $sealLetters !== '' ? $sealLetters : 'Q';

        $temporal = $this->temporal->resolve($award);
        $award['temporal_code'] = $temporal['code'];
        $award['temporal_label'] = $temporal['label'];
        $award['days_remaining'] = $temporal['days_remaining'];

        $admin = QualificationAdminStatus::normalize(
            (string) ($award['admin_status'] ?? $award['status'] ?? '')
        );
        $award['admin_status_normalized'] = $admin;
        $hasCert = trim((string) ($award['certificate_document_path'] ?? '')) !== '';
        $award['can_generate_brevet'] = $admin === QualificationAdminStatus::OBTAINED && !$hasCert;
        $award['can_regenerate_brevet'] = $admin === QualificationAdminStatus::OBTAINED && $hasCert;
        $award['holder_name'] = QualificationCertificatePdfService::pickHolderName($user);
        $award['is_permanent_flag'] = !empty($award['is_permanent'])
            || trim((string) ($award['expires_at'] ?? '')) === '';

        $cat = mb_strtolower(trim((string) ($award['category_name'] ?? '')), 'UTF-8');
        $code = mb_strtolower(trim((string) ($award['definition_code'] ?? '')), 'UTF-8');
        $hay = $cat . ' ' . $code;
        if (str_contains($hay, 'atak') || str_contains($hay, 'liaison') || str_contains($hay, 'overwatch') || str_contains($hay, 'tact')) {
            $award['card_tone'] = 'tak';
        } elseif (str_contains($hay, 'recrut') || str_contains($hay, 'rh') || str_contains($hay, 'bureau') || str_contains($hay, 'candidat')) {
            $award['card_tone'] = 'rh';
        } else {
            $award['card_tone'] = 'def';
        }

        return $award;
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
