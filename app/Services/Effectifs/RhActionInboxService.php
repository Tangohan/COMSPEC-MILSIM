<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\ElevationRequestRepository;
use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Services\Personnel\PersonnelProfileGapScanService;

/**
 * Boîte de travail RH : agrège corrections, élévations, échéances et dossiers incomplets.
 * Le système propose ; le responsable décide.
 */
final class RhActionInboxService
{
    public function __construct(
        private PersonnelCorrectionRequestRepository $corrections,
        private ElevationRequestRepository $elevations,
        private RhAlertAggregatorService $alerts,
        private PersonnelProfileGapScanService $gaps,
        private ?PersonnelQualificationRepository $qualifications = null,
        private ?PersonnelMobilityRequestRepository $mobility = null,
    ) {
        $this->qualifications ??= new PersonnelQualificationRepository();
        $this->mobility ??= new PersonnelMobilityRequestRepository();
    }

    /**
     * @return array{
     *   total: int,
     *   buckets: list<array{id:string,label:string,count:int,href:string,tone:string}>,
     *   actions: list<array{
     *     id: string,
     *     bucket: string,
     *     priority: int,
     *     member_id: int,
     *     member_label: string,
     *     title: string,
     *     detail: string,
     *     cta_label: string,
     *     href: string
     *   }>
     * }
     */
    public function build(int $tenantId, ?int $inactivityDays = null, ?int $absenceDays = null): array
    {
        if ($tenantId < 1) {
            return ['total' => 0, 'buckets' => [], 'actions' => []];
        }

        $actions = [];
        $correctionCount = 0;
        $elevationCount = 0;
        $deadlineCount = 0;
        $incompleteCount = 0;
        $mobilityCount = 0;

        try {
            $correctionCount = $this->corrections->countPendingForTenant($tenantId);
            foreach ($this->corrections->listOpenForTenant($tenantId, 12) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $uid = (int) ($row['target_user_id'] ?? 0);
                $label = $this->memberLabel(
                    (string) ($row['target_display_name'] ?? ''),
                    (string) ($row['target_callsign'] ?? ''),
                    $uid
                );
                $actions[] = [
                    'id' => 'corr-' . (int) ($row['id'] ?? 0),
                    'bucket' => 'corrections',
                    'priority' => 10,
                    'member_id' => $uid,
                    'member_label' => $label,
                    'title' => 'Demande de correction',
                    'detail' => $this->truncate((string) ($row['note'] ?? 'Le membre propose une mise à jour de son dossier.')),
                    'cta_label' => 'Traiter',
                    'href' => url('back-office/personnel/corrections'),
                ];
            }
        } catch (\Throwable) {
            $correctionCount = 0;
        }

        try {
            $elevationCount = $this->elevations->countOpenForTenant($tenantId);
            foreach ($this->elevations->listOpenForTenant($tenantId, 12) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $uid = (int) ($row['target_user_id'] ?? 0);
                $label = $this->memberLabel(
                    (string) ($row['target_display_name'] ?? $row['display_name'] ?? ''),
                    (string) ($row['target_callsign'] ?? $row['callsign'] ?? ''),
                    $uid
                );
                $actions[] = [
                    'id' => 'elev-' . (int) ($row['id'] ?? 0),
                    'bucket' => 'elevations',
                    'priority' => 20,
                    'member_id' => $uid,
                    'member_label' => $label,
                    'title' => 'Proposition d’élévation',
                    'detail' => $this->truncate((string) ($row['note'] ?? 'À examiner avant décision.')),
                    'cta_label' => 'Examiner',
                    'href' => effectifs_workspace_url('elevations'),
                ];
            }
        } catch (\Throwable) {
            $elevationCount = 0;
        }

        try {
            $mobilityCount = $this->mobility->countPending($tenantId);
            if ($mobilityCount > 0) {
                $actions[] = [
                    'id' => 'mob-summary',
                    'bucket' => 'mobility',
                    'priority' => 35,
                    'member_id' => 0,
                    'member_label' => 'Mobilité',
                    'title' => $mobilityCount . ' demande' . ($mobilityCount > 1 ? 's' : '') . ' de mobilité',
                    'detail' => 'Souhaits de mutation ou d’évolution encore sans décision.',
                    'cta_label' => 'Ouvrir',
                    'href' => effectifs_workspace_url('mobilite'),
                ];
            }
        } catch (\Throwable) {
            $mobilityCount = 0;
        }

        try {
            $expiring = $this->qualifications->listExpiringForTenant($tenantId, 60, 8);
            $deadlineCount = count($this->qualifications->listExpiringForTenant($tenantId, 60, 300));
            foreach ($expiring as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $uid = (int) ($row['user_id'] ?? 0);
                $label = $this->memberLabel(
                    (string) ($row['display_name'] ?? ''),
                    (string) ($row['callsign'] ?? ''),
                    $uid
                );
                $qualName = trim((string) ($row['qualification_name'] ?? $row['name'] ?? 'Qualification'));
                $expires = substr(trim((string) ($row['expires_at'] ?? '')), 0, 10);
                $actions[] = [
                    'id' => 'qual-' . (int) ($row['id'] ?? 0) . '-' . $uid,
                    'bucket' => 'deadlines',
                    'priority' => 40,
                    'member_id' => $uid,
                    'member_label' => $label,
                    'title' => $qualName . ' à renouveler',
                    'detail' => $expires !== ''
                        ? 'Échéance le ' . $this->frDate($expires) . '. Proposition : examiner le renouvellement.'
                        : 'Échéance proche. Proposition : examiner le renouvellement.',
                    'cta_label' => 'Traiter',
                    'href' => $uid > 0
                        ? effectifs_workspace_url('membres/' . $uid)
                        : effectifs_workspace_url('qualifications'),
                ];
            }
        } catch (\Throwable) {
            $deadlineCount = 0;
        }

        try {
            $gapPack = $this->gaps->listForTenant($tenantId, 10);
            $incompleteCount = (int) ($gapPack['total'] ?? 0);
            foreach (($gapPack['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $uid = (int) ($row['id'] ?? $row['user_id'] ?? 0);
                $label = $this->memberLabel(
                    (string) ($row['display_name'] ?? ''),
                    (string) ($row['callsign'] ?? ''),
                    $uid
                );
                $issueKeys = is_array($row['issue_keys'] ?? null) ? $row['issue_keys'] : [];
                $issueLabels = [];
                foreach ($issueKeys as $key) {
                    $key = (string) $key;
                    if ($key !== '' && isset(PersonnelProfileGapScanService::ISSUE_LABELS[$key])) {
                        $issueLabels[] = PersonnelProfileGapScanService::ISSUE_LABELS[$key];
                    }
                }
                $score = max(0, 100 - ((int) ($row['issue_count'] ?? count($issueKeys)) * 18));
                $actions[] = [
                    'id' => 'gap-' . $uid,
                    'bucket' => 'incomplete',
                    'priority' => 50,
                    'member_id' => $uid,
                    'member_label' => $label,
                    'title' => 'Fiche ' . $score . ' %',
                    'detail' => $issueLabels !== []
                        ? 'À compléter : ' . implode(', ', array_slice($issueLabels, 0, 4))
                        : 'Des informations essentielles manquent encore.',
                    'cta_label' => 'Compléter',
                    'href' => $uid > 0
                        ? effectifs_workspace_url('membres/' . $uid)
                        : effectifs_workspace_url(),
                ];
            }
        } catch (\Throwable) {
            $incompleteCount = 0;
        }

        // Alertes agrégées (complément sans dupliquer chaque ligne)
        try {
            $summary = $this->alerts->summarize($tenantId, $inactivityDays, $absenceDays);
            foreach ($summary['items'] as $item) {
                if (!is_array($item) || (string) ($item['tone'] ?? '') === 'ok') {
                    continue;
                }
                $id = (string) ($item['id'] ?? '');
                if (in_array($id, ['qualif_expiring', 'mobility_pending'], true)) {
                    continue; // déjà couverts
                }
                $count = (int) ($item['count'] ?? 0);
                if ($count < 1) {
                    continue;
                }
                $actions[] = [
                    'id' => 'alert-' . $id,
                    'bucket' => 'alerts',
                    'priority' => 60,
                    'member_id' => 0,
                    'member_label' => (string) ($item['perimeter'] ?? 'Suivi'),
                    'title' => (string) ($item['label'] ?? 'Alerte'),
                    'detail' => $count . ' situation' . ($count > 1 ? 's' : '') . ' à examiner.',
                    'cta_label' => 'Voir',
                    'href' => (string) ($item['href'] ?? effectifs_workspace_url('alertes')),
                ];
            }
        } catch (\Throwable) {
        }

        usort($actions, static function (array $a, array $b): int {
            $p = ((int) ($a['priority'] ?? 99)) <=> ((int) ($b['priority'] ?? 99));
            if ($p !== 0) {
                return $p;
            }

            return strcmp((string) ($a['member_label'] ?? ''), (string) ($b['member_label'] ?? ''));
        });

        $buckets = [
            [
                'id' => 'corrections',
                'label' => 'Corrections à traiter',
                'count' => $correctionCount,
                'href' => url('back-office/personnel/corrections'),
                'tone' => $correctionCount > 0 ? 'warn' : 'ok',
            ],
            [
                'id' => 'elevations',
                'label' => 'Élévations à examiner',
                'count' => $elevationCount,
                'href' => effectifs_workspace_url('elevations'),
                'tone' => $elevationCount > 0 ? 'warn' : 'ok',
            ],
            [
                'id' => 'deadlines',
                'label' => 'Échéances',
                'count' => $deadlineCount,
                'href' => effectifs_workspace_url('qualifications'),
                'tone' => $deadlineCount > 0 ? 'warn' : 'ok',
            ],
            [
                'id' => 'incomplete',
                'label' => 'Dossiers incomplets',
                'count' => $incompleteCount,
                'href' => effectifs_workspace_url('a-traiter') . '#incomplete',
                'tone' => $incompleteCount > 0 ? 'info' : 'ok',
            ],
            [
                'id' => 'mobility',
                'label' => 'Mobilité',
                'count' => $mobilityCount,
                'href' => effectifs_workspace_url('mobilite'),
                'tone' => $mobilityCount > 0 ? 'info' : 'ok',
            ],
        ];

        $total = 0;
        foreach ($buckets as $bucket) {
            if (($bucket['tone'] ?? '') !== 'ok') {
                $total += (int) ($bucket['count'] ?? 0);
            }
        }

        return [
            'total' => $total,
            'buckets' => $buckets,
            'actions' => array_slice($actions, 0, 40),
        ];
    }

    private function memberLabel(string $displayName, string $callsign, int $userId): string
    {
        $displayName = trim($displayName);
        $callsign = trim($callsign);
        if ($displayName !== '') {
            return $displayName;
        }
        if ($callsign !== '') {
            return $callsign;
        }

        return $userId > 0 ? ('Membre #' . $userId) : 'Membre';
    }

    private function truncate(string $text, int $max = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max - 1) . '…';
        }
        if (strlen($text) > $max) {
            return substr($text, 0, $max - 1) . '…';
        }

        return $text;
    }

    private function frDate(string $ymd): string
    {
        try {
            return (new \DateTimeImmutable($ymd))->format('d/m/Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }
}
