<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;
use App\Repositories\UserRepository;
use App\Support\RoleplayBilanPolicy;

/**
 * Vitrine roleplay du membre (self-service) : personnage, statut de suivi/tutorat,
 * échéance de bilan, timeline d'événements. Regroupe des données déjà stockées
 * (personnel_profiles, personnel_roleplay_timeline_events) sans nouvelle table.
 */
final class RoleplayPageController
{
    public function __construct(
        private UserRepository $userRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelRoleplayTimelineRepository $timelineRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            return Response::redirect(url('dashboard'));
        }
        $profile = $this->personnelProfileRepository->getByUserId($userId) ?? [];

        $tutorLabel = null;
        $tutorId = (int) ($profile['rp_tutor_user_id'] ?? 0);
        if ($tutorId > 0) {
            $tutor = $this->userRepository->findById($tutorId, $tenantId);
            if ($tutor) {
                $tutorLabel = trim((string) ($tutor['display_name'] ?? '')) ?: trim((string) ($tutor['callsign'] ?? ''));
            }
        }

        $joinedAt = trim((string) ($user['created_at'] ?? '')) ?: null;
        $lastReviewAt = trim((string) ($profile['rp_last_review_at'] ?? '')) ?: null;
        $nextDueAt = RoleplayBilanPolicy::nextReviewDueAt($joinedAt, $lastReviewAt);

        $timeline = $this->timelineRepository->tableExists()
            ? $this->timelineRepository->listForUser($tenantId, $userId, 30)
            : [];

        return Response::view('layout.main', [
            'title' => 'Mon personnage — Roleplay',
            'content' => 'roleplay.index',
            'rpUser' => $user,
            'rpProfile' => $profile,
            'rpTutorLabel' => $tutorLabel,
            'rpNextDueAt' => $nextDueAt?->format('Y-m-d'),
            'rpLastReviewAt' => $lastReviewAt,
            'rpOverdue' => RoleplayBilanPolicy::isOverdue($joinedAt, $lastReviewAt),
            'rpTimeline' => $timeline,
        ]);
    }
}
