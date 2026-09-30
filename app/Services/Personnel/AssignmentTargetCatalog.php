<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AdvancementRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Services\Advancement\AdvancementWorkflowService;
use DateTimeImmutable;
use Throwable;

/**
 * Cibles sélectionnables dans une demande : postes, AAV, offres.
 */
final class AssignmentTargetCatalog
{
    public const KIND_POSTE = 'poste';
    public const KIND_AAV = 'aav';
    public const KIND_OFFRE = 'offre';

    /** @var array<string, string> */
    public const KIND_LABELS = [
        self::KIND_POSTE => 'Poste',
        self::KIND_AAV => 'AAV',
        self::KIND_OFFRE => 'Offre',
    ];

    public function __construct(
        private ?OrbatBilletRepository $billets = null,
        private ?PersonnelJobRoleRepository $jobRoles = null,
        private ?AdvancementRepository $advancement = null,
        private ?RecruitmentOpeningRepository $openings = null,
    ) {
        $this->billets ??= new OrbatBilletRepository();
        $this->jobRoles ??= new PersonnelJobRoleRepository();
        $this->advancement ??= new AdvancementRepository();
        $this->openings ??= new RecruitmentOpeningRepository();
    }

    /**
     * @return array{
     *   postes: list<array{value:string,label:string}>,
     *   aav: list<array{value:string,label:string}>,
     *   offres: list<array{value:string,label:string}>
     * }
     */
    public function grouped(int $tenantId): array
    {
        return [
            'postes' => $this->postes($tenantId),
            'aav' => $this->aav($tenantId),
            'offres' => $this->offres($tenantId),
        ];
    }

    /**
     * @return array{
     *   kind:string,
     *   source:string,
     *   id:int,
     *   unit_id:?int,
     *   job_role_id:?int,
     *   billet_id:?int,
     *   opening_id:?int,
     *   campaign_id:?int,
     *   label:string
     * }|null
     */
    public function resolve(int $tenantId, string $ref): ?array
    {
        $parsed = self::parseRef($ref);
        if ($parsed === null) {
            return null;
        }
        $kind = $parsed['kind'];
        $source = $parsed['source'];
        $id = $parsed['id'];
        $groups = $this->grouped($tenantId);
        $bucket = $groups[$kind === self::KIND_POSTE ? 'postes' : ($kind === self::KIND_AAV ? 'aav' : 'offres')] ?? [];
        $label = '';
        foreach ($bucket as $row) {
            if ((string) ($row['value'] ?? '') === $ref) {
                $label = (string) ($row['label'] ?? '');
                break;
            }
        }
        if ($label === '') {
            return null;
        }

        $out = [
            'kind' => $kind,
            'source' => $source,
            'id' => $id,
            'unit_id' => null,
            'job_role_id' => null,
            'billet_id' => null,
            'opening_id' => null,
            'campaign_id' => null,
            'label' => $label,
        ];
        if ($source === 'billet') {
            $out['billet_id'] = $id;
            $billet = $this->findBillet($tenantId, $id);
            if ($billet !== null) {
                $unitId = (int) ($billet['unit_id'] ?? 0);
                $out['unit_id'] = $unitId > 0 ? $unitId : null;
                $jobId = (int) ($billet['job_role_id'] ?? 0);
                $out['job_role_id'] = $jobId > 0 ? $jobId : null;
            }
        } elseif ($source === 'job') {
            $out['job_role_id'] = $id;
        } elseif ($source === 'campaign') {
            $out['campaign_id'] = $id;
        } elseif ($source === 'opening') {
            $out['opening_id'] = $id;
            $opening = $this->findOpening($tenantId, $id);
            if ($opening !== null) {
                $unitId = (int) ($opening['unit_id'] ?? 0);
                $out['unit_id'] = $unitId > 0 ? $unitId : null;
                $jobId = (int) ($opening['personnel_job_role_id'] ?? 0);
                $out['job_role_id'] = $jobId > 0 ? $jobId : null;
            }
        }

        return $out;
    }

    /**
     * @return array{kind:string,source:string,id:int}|null
     */
    public static function parseRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '' || !preg_match('/^(poste|aav|offre):(billet|job|campaign|opening):(\d+)$/', $ref, $m)) {
            return null;
        }
        $kind = (string) $m[1];
        $source = (string) $m[2];
        $id = (int) $m[3];
        if ($id < 1) {
            return null;
        }
        $allowed = [
            self::KIND_POSTE => ['billet', 'job'],
            self::KIND_AAV => ['campaign', 'billet'],
            self::KIND_OFFRE => ['opening'],
        ];
        if (!in_array($source, $allowed[$kind] ?? [], true)) {
            return null;
        }

        return ['kind' => $kind, 'source' => $source, 'id' => $id];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function postes(int $tenantId): array
    {
        $out = [];
        try {
            foreach ($this->billets->listActiveForTenant($tenantId, 400) as $row) {
                $title = trim((string) ($row['title'] ?? $row['code'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $unit = trim((string) ($row['unit_name'] ?? ''));
                $out[] = [
                    'value' => 'poste:billet:' . (int) ($row['id'] ?? 0),
                    'label' => $unit !== '' ? $title . ' · ' . $unit : $title,
                ];
            }
        } catch (Throwable) {
        }
        try {
            foreach ($this->jobRoles->listRoleOptionsForSelect($tenantId) as $row) {
                $label = trim((string) ($row['label'] ?? $row['name'] ?? ''));
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1 || $label === '') {
                    continue;
                }
                $out[] = [
                    'value' => 'poste:job:' . $id,
                    'label' => $label,
                ];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function aav(int $tenantId): array
    {
        $out = [];
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        try {
            if ($this->advancement->tablesReady()) {
                foreach ($this->advancement->listCampaigns($tenantId) as $row) {
                    if ((string) ($row['status'] ?? '') !== AdvancementWorkflowService::STATUS_OPEN) {
                        continue;
                    }
                    $opens = substr((string) ($row['opens_at'] ?? ''), 0, 10);
                    $closes = substr((string) ($row['closes_at'] ?? ''), 0, 10);
                    if ($opens !== '' && $today < $opens) {
                        continue;
                    }
                    if ($closes !== '' && $today > $closes) {
                        continue;
                    }
                    $grade = trim((string) ($row['grade_label'] ?? $row['grade_short_label'] ?? 'grade'));
                    $year = (int) ($row['year'] ?? 0);
                    $out[] = [
                        'value' => 'aav:campaign:' . (int) ($row['id'] ?? 0),
                        'label' => 'AAV · Avancement ' . $grade . ($year > 0 ? ' · ' . $year : ''),
                    ];
                }
            }
        } catch (Throwable) {
        }
        try {
            foreach ($this->vacantBillets($tenantId) as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $unit = trim((string) ($row['unit_name'] ?? ''));
                $vacant = (int) ($row['vacant'] ?? 0);
                $out[] = [
                    'value' => 'aav:billet:' . (int) ($row['id'] ?? 0),
                    'label' => 'Poste vacant · ' . $title . ($unit !== '' ? ' · ' . $unit : '') . ($vacant > 0 ? ' (' . $vacant . ')' : ''),
                ];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function offres(int $tenantId): array
    {
        $out = [];
        try {
            if (!$this->openings->tablesExist()) {
                return $out;
            }
            foreach ($this->openings->listPublishedForTenant($tenantId) as $row) {
                $title = trim((string) ($row['title'] ?? $row['reference_public'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $unit = trim((string) ($row['unit_name'] ?? ''));
                $out[] = [
                    'value' => 'offre:opening:' . (int) ($row['id'] ?? 0),
                    'label' => $unit !== '' ? $title . ' · ' . $unit : $title,
                ];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function vacantBillets(int $tenantId): array
    {
        $rows = [];
        try {
            $filledByBillet = $this->billets->filledSeatCountsForTenant($tenantId);
            foreach ($this->billets->listActiveForTenant($tenantId, 400) as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $authorized = max(1, (int) ($row['authorized_slots'] ?? 1));
                $filled = (int) ($filledByBillet[$id] ?? 0);
                $vacant = max(0, $authorized - $filled);
                if ($vacant < 1) {
                    continue;
                }
                $row['vacant'] = $vacant;
                $rows[] = $row;
            }
        } catch (Throwable) {
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function findBillet(int $tenantId, int $id): ?array
    {
        try {
            return $this->billets->findById($tenantId, $id);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function findOpening(int $tenantId, int $id): ?array
    {
        try {
            return $this->openings->findByIdForTenant($id, $tenantId);
        } catch (Throwable) {
            return null;
        }
    }
}
