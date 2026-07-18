<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Repositories\CommunityEventRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingService;

/**
 * Synthèse « mission du jour » pour le tableau de bord membre (OP, formations, modpack, consignes courtes).
 */
final class MemberMissionBriefingService
{
    public function __construct(
        private CommunityEventRepository $events,
        private FeatureGateService $featureGate,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingService $trainingService,
    ) {}

    /**
     * @param list<array{id: int, kind: string, label: string, href: ?string, notice_text: ?string}> $dashboardPins
     * @return array{
     *   next_op: ?array{title: string, starts_at: string, list_href: string, rsvp_label: ?string, summary?: string},
     *   upcoming_ops: list<array{title: string, starts_at: string, list_href: string, rsvp_label: ?string, summary: string}>,
     *   trainings: list<array{title: string, href: string, subtitle: string, urgent: bool, progress_pct: int}>,
     *   modpack: ?array{title: string, detail_href: string, has_pack: bool},
     *   consigne_excerpt: ?string,
     *   pins_anchor_href: string
     * }
     */
    public function buildForViewer(
        int $tenantId,
        int $userId,
        ?array $modpack,
        array $dashboardPins,
        bool $trainingFeatureEnabled,
    ): array {
        $nextOp = null;
        $upcomingOps = [];
        if ($this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            $rows = $this->events->upcomingForTenantWithUserRsvp($tenantId, $userId, 5);
            foreach ($rows as $ev) {
                $title = trim((string) ($ev['title'] ?? ''));
                if ($title === '') {
                    $title = 'Opération à venir';
                }
                $starts = (string) ($ev['starts_at'] ?? '');
                $rsvp = isset($ev['rsvp_status']) ? (string) $ev['rsvp_status'] : '';
                $item = [
                    'title' => $title,
                    'starts_at' => $starts,
                    'list_href' => url('evenements'),
                    'rsvp_label' => self::rsvpLabel($rsvp),
                    'summary' => trim(preg_replace('/\s+/', ' ', strip_tags((string) ($ev['description'] ?? ''))) ?? ''),
                ];
                if (mb_strlen($item['summary']) > 120) {
                    $item['summary'] = mb_substr($item['summary'], 0, 117) . '…';
                }
                $upcomingOps[] = $item;
            }
            if ($upcomingOps !== []) {
                $nextOp = $upcomingOps[0];
            }
        }

        $trainings = [];
        if ($trainingFeatureEnabled) {
            $enrollments = $this->enrollmentRepository->listByUserId($userId, $tenantId);
            $open = array_values(array_filter(
                $enrollments,
                static fn (array $e): bool => in_array((string) ($e['status'] ?? ''), ['assigned', 'in_progress', 'pending_approval'], true)
            ));
            usort($open, static function (array $a, array $b): int {
                $ma = !empty($a['is_mandatory']) ? 1 : 0;
                $mb = !empty($b['is_mandatory']) ? 1 : 0;
                if ($ma !== $mb) {
                    return $mb <=> $ma;
                }
                $ea = (string) ($a['expires_at'] ?? '');
                $eb = (string) ($b['expires_at'] ?? '');
                if ($ea !== '' && $eb !== '') {
                    return strcmp($ea, $eb);
                }
                if ($ea !== '') {
                    return -1;
                }
                if ($eb !== '') {
                    return 1;
                }

                return strcmp((string) ($b['assigned_at'] ?? ''), (string) ($a['assigned_at'] ?? ''));
            });
            foreach (array_slice($open, 0, 3) as $e) {
                $eid = (int) ($e['id'] ?? 0);
                $slug = trim((string) ($e['course_slug'] ?? ''));
                $title = trim((string) ($e['course_title'] ?? ''));
                if ($title === '') {
                    $title = 'Formation';
                }
                $href = $slug !== '' ? url('formations/' . rawurlencode($slug)) : url('formations/mes-formations');
                $pct = $eid > 0 ? (int) round($this->trainingService->getGlobalProgress($eid)) : 0;
                $mandatory = !empty($e['is_mandatory']);
                $exp = (string) ($e['expires_at'] ?? '');
                $urgent = $mandatory || self::expiresWithinDays($exp, 14);
                $parts = [];
                if ($mandatory) {
                    $parts[] = 'Obligatoire';
                }
                $parts[] = 'Avancement : ' . $pct . ' %';
                if ($exp !== '') {
                    $ts = strtotime($exp);
                    if ($ts !== false) {
                        $parts[] = 'Échéance : ' . date('d/m/Y', $ts);
                    }
                }
                $expiresLabel = null;
                if ($exp !== '') {
                    $expTs = strtotime($exp);
                    if ($expTs !== false) {
                        $expiresLabel = date('d/m/Y', $expTs);
                    }
                }
                $trainings[] = [
                    'title' => $title,
                    'href' => $href,
                    'subtitle' => implode(' · ', $parts),
                    'urgent' => $urgent,
                    'mandatory' => $mandatory,
                    'progress_pct' => max(0, min(100, $pct)),
                    'expires_at' => $exp !== '' ? $exp : null,
                    'expires_label' => $expiresLabel,
                ];
            }
        }

        $modpackBlock = null;
        if ($modpack && !empty($modpack['id'])) {
            $slug = trim((string) ($modpack['slug'] ?? ''));
            $detailHref = $slug !== '' ? url('modpacks/' . rawurlencode($slug)) : url('modpacks');
            $mtitle = trim((string) ($modpack['name'] ?? $modpack['title'] ?? ''));
            if ($mtitle === '') {
                $mtitle = 'Modpack';
            }
            $modpackBlock = [
                'title' => $mtitle,
                'detail_href' => $detailHref,
                'has_pack' => true,
            ];
        } else {
            $modpackBlock = [
                'title' => 'Modpack communautaire',
                'detail_href' => url('modpacks'),
                'has_pack' => false,
            ];
        }

        $consigneExcerpt = null;
        foreach ($dashboardPins as $pin) {
            if (($pin['kind'] ?? '') === 'notice' && !empty($pin['notice_text'])) {
                $raw = trim(preg_replace('/\s+/', ' ', (string) $pin['notice_text']) ?? '');
                if ($raw !== '') {
                    $consigneExcerpt = mb_strlen($raw) > 160 ? mb_substr($raw, 0, 157) . '…' : $raw;
                }
                break;
            }
        }

        return [
            'next_op' => $nextOp,
            'upcoming_ops' => $upcomingOps,
            'trainings' => $trainings,
            'modpack' => $modpackBlock,
            'consigne_excerpt' => $consigneExcerpt,
            'pins_anchor_href' => url('dashboard') . '#dashboard-community-pins',
        ];
    }

    private static function rsvpLabel(string $status): ?string
    {
        return match ($status) {
            'yes' => 'Vous participez',
            'no' => 'Vous ne participez pas',
            'maybe' => 'Peut-être',
            '' => 'Réponse non renseignée',
            default => null,
        };
    }

    private static function expiresWithinDays(string $expiresAtIso, int $days): bool
    {
        if ($expiresAtIso === '') {
            return false;
        }
        $t = strtotime($expiresAtIso);
        if ($t === false) {
            return false;
        }

        return $t <= strtotime('+' . $days . ' days');
    }
}
