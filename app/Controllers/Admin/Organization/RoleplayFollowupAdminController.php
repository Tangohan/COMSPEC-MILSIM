<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;

final class RoleplayFollowupAdminController
{
    public function __construct(
        private UserRepository $userRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelRoleplayTimelineRepository $timelineRepository,
        private TenantRepository $tenantRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $cfg = is_array($community['roleplay_followup'] ?? null) ? $community['roleplay_followup'] : [];

        $rows = [];
        $enabled = !empty($cfg['enabled']);
        $today = date('Y-m-d');
        $countEligible = 0;
        $countTracked = 0;
        foreach ($this->userRepository->allForTenant($tenantId) as $u) {
            if ((string) ($u['status'] ?? '') !== 'active') {
                continue;
            }
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $p = $this->personnelProfileRepository->getByUserId($uid) ?? [];
            $hasTracking = trim((string) ($p['rp_followup_stage'] ?? '')) !== ''
                || (int) ($p['rp_tutor_user_id'] ?? 0) > 0
                || trim((string) ($p['rp_recruitment_stream'] ?? '')) !== ''
                || trim((string) ($p['rp_operational_function'] ?? '')) !== ''
                || trim((string) ($p['rp_recruitment_origin'] ?? '')) !== '';
            if ($hasTracking) {
                $countTracked++;
            }
            $snapshot = [];
            if (!empty($p['rp_eligibility_snapshot_json']) && is_string($p['rp_eligibility_snapshot_json'])) {
                $decoded = json_decode((string) $p['rp_eligibility_snapshot_json'], true);
                $snapshot = is_array($decoded) ? $decoded : [];
            }
            if (!empty($snapshot['eligible'])) {
                $countEligible++;
            }
            $tutorLabel = null;
            $tid = (int) ($p['rp_tutor_user_id'] ?? 0);
            if ($tid > 0) {
                $tu = $this->userRepository->findById($tid, $tenantId);
                if ($tu) {
                    $tutorLabel = trim((string) ($tu['display_name'] ?? '')) ?: trim((string) ($tu['callsign'] ?? ''));
                }
            }
            $timeline = $this->timelineRepository->listForUser($tenantId, $uid, 1);
            $nextDue = null;
            foreach (['rp_next_interview_date', 'rp_medical_due_date', 'rp_service_rotation_date'] as $k) {
                $raw = trim((string) ($p[$k] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if ($nextDue === null || $raw < $nextDue) {
                    $nextDue = $raw;
                }
            }
            $rpProgress = $p['rp_followup_progress'] ?? null;
            $rows[] = [
                'user_id' => $uid,
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'stage' => trim((string) ($p['rp_followup_stage'] ?? '')),
                'status' => trim((string) ($p['rp_followup_status'] ?? '')),
                'progress' => $rpProgress !== null ? (int) $rpProgress : null,
                'track' => trim((string) ($p['rp_recruitment_stream'] ?? '')),
                'function' => trim((string) ($p['rp_operational_function'] ?? '')),
                'origin' => trim((string) ($p['rp_recruitment_origin'] ?? '')),
                'tutor_label' => $tutorLabel,
                'next_due' => $nextDue,
                'next_due_is_overdue' => $nextDue !== null && $nextDue < $today,
                'eligible' => !empty($snapshot['eligible']),
                'latest_timeline' => $timeline[0] ?? null,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $ad = (string) ($a['next_due'] ?? '9999-12-31');
            $bd = (string) ($b['next_due'] ?? '9999-12-31');
            if ($ad !== $bd) {
                return strcmp($ad, $bd);
            }

            return strcmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        });

        return Response::view('layout.main', [
            'content' => 'admin.organization.roleplay_followup',
            'title' => 'Back-office roleplay — suivi',
            'rpFeatureEnabled' => $enabled,
            'rpConfig' => $cfg,
            'rpRows' => $rows,
            'rpTrackedCount' => $countTracked,
            'rpEligibleCount' => $countEligible,
            'rpTotalActiveMembers' => count($rows),
            'rpTimelineTableReady' => $this->timelineRepository->tableExists(),
        ]);
    }
}
