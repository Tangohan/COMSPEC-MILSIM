<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\SeniorityRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;

/**
 * Ancienneté antérieure à l’arrivée sur la plateforme :
 * — date de création de l’entité (communauté) avant le site ;
 * — ancienneté individuelle d’un membre avant son entrée sur la plateforme.
 *
 * Ces indicateurs ne sont jamais auto-remplis à l’acceptation : saisie RH uniquement.
 */
final class SeniorityPrePlatformService
{
    public const PERSON_MARKER = 'system:pre_platform_manual';

    public const ORG_MARKER = 'system:org_pre_platform';

    public const CODE_PERSON = 'tenure_pre_platform';

    public const CODE_ORG = 'tenure_org_pre_platform';

    public function __construct(
        private SeniorityRepository $seniorityRepository,
        private SeniorityTenantDefaultsService $tenantDefaultsService,
        private UserRepository $userRepository,
    ) {}

    public function getPersonStartDate(int $tenantId, int $userId): ?string
    {
        return $this->readMarkedStart($tenantId, $userId, self::CODE_PERSON, self::PERSON_MARKER);
    }

    /**
     * Date de création d’entité déjà propagée (plus ancienne période connue pour l’indicateur org).
     */
    public function getOrgFoundingDate(int $tenantId): ?string
    {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1) {
            return null;
        }
        $this->tenantDefaultsService->ensureStandardPack($tenantId);
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, self::CODE_ORG);
        if ($defId === null) {
            return null;
        }

        return $this->seniorityRepository->earliestStartForDefinitionTenant($tenantId, $defId);
    }

    /**
     * Enregistre ou efface l’ancienneté individuelle antérieure à la plateforme.
     *
     * @return 'inserted'|'updated'|'cleared'|'unchanged'|'skipped_schema'|'skipped_no_definition'|'invalid_date'|'insert_failed'
     */
    public function upsertPersonStartDate(int $tenantId, int $userId, ?string $startDateRaw): string
    {
        return $this->upsertMarkedPeriod(
            $tenantId,
            $userId,
            self::CODE_PERSON,
            self::PERSON_MARKER,
            $startDateRaw,
            ['source' => 'personnel_edit']
        );
    }

    /**
     * Propage la date de création de l’entité à tous les membres actifs.
     *
     * @return array{
     *   members: int,
     *   inserted: int,
     *   updated: int,
     *   unchanged: int,
     *   cleared: int,
     *   insert_failed: int,
     *   skipped_schema: int,
     *   skipped_no_definition: int,
     *   invalid_date: int
     * }
     */
    public function syncOrgFoundingForAllActiveMembers(int $tenantId, ?string $startDateRaw): array
    {
        $stats = [
            'members' => 0,
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'cleared' => 0,
            'insert_failed' => 0,
            'skipped_schema' => 0,
            'skipped_no_definition' => 0,
            'invalid_date' => 0,
        ];
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1) {
            $stats['skipped_schema'] = 1;

            return $stats;
        }
        $normalized = $startDateRaw === null || trim($startDateRaw) === ''
            ? null
            : $this->normalizeDateString($startDateRaw);
        if ($startDateRaw !== null && trim($startDateRaw) !== '' && $normalized === null) {
            $stats['invalid_date'] = 1;

            return $stats;
        }

        $this->tenantDefaultsService->ensureStandardPack($tenantId);
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, self::CODE_ORG);
        if ($defId === null) {
            $stats['skipped_no_definition'] = 1;

            return $stats;
        }

        foreach ($this->userRepository->listActiveUserIdsForTenant($tenantId) as $uid) {
            ++$stats['members'];
            $code = $this->upsertMarkedPeriod(
                $tenantId,
                $uid,
                self::CODE_ORG,
                self::ORG_MARKER,
                $normalized,
                ['source' => 'org_founding_sync'],
                true
            );
            match ($code) {
                'inserted' => ++$stats['inserted'],
                'updated' => ++$stats['updated'],
                'unchanged' => ++$stats['unchanged'],
                'cleared' => ++$stats['cleared'],
                'insert_failed' => ++$stats['insert_failed'],
                'skipped_no_definition' => ++$stats['skipped_no_definition'],
                'skipped_schema' => ++$stats['skipped_schema'],
                'invalid_date' => ++$stats['invalid_date'],
                default => null,
            };
        }

        return $stats;
    }

    private function readMarkedStart(int $tenantId, int $userId, string $code, string $marker): ?string
    {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, $code);
        if ($defId === null) {
            return null;
        }
        $periodId = $this->seniorityRepository->findPeriodIdByRelatedType($tenantId, $userId, $defId, $marker);
        if ($periodId === null) {
            $periods = $this->seniorityRepository->listPeriodsForUserAndDefinition($userId, $defId);
            if ($periods === []) {
                return null;
            }
            $start = trim((string) ($periods[0]['start_date'] ?? ''));

            return $start !== '' ? $start : null;
        }

        return $this->seniorityRepository->findPeriodStartDateById($periodId, $tenantId, $userId);
    }

    /**
     * @param array<string, mixed> $meta
     * @return 'inserted'|'updated'|'cleared'|'unchanged'|'skipped_schema'|'skipped_no_definition'|'invalid_date'|'insert_failed'
     */
    private function upsertMarkedPeriod(
        int $tenantId,
        int $userId,
        string $code,
        string $marker,
        ?string $startDateRaw,
        array $meta,
        bool $dateAlreadyNormalized = false
    ): string {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            return 'skipped_schema';
        }
        $this->tenantDefaultsService->ensureStandardPack($tenantId);
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, $code);
        if ($defId === null) {
            return 'skipped_no_definition';
        }

        $normalized = null;
        if ($startDateRaw !== null && trim((string) $startDateRaw) !== '') {
            $normalized = $dateAlreadyNormalized
                ? trim((string) $startDateRaw)
                : $this->normalizeDateString((string) $startDateRaw);
            if ($normalized === null) {
                return 'invalid_date';
            }
        }

        $existingId = $this->seniorityRepository->findPeriodIdByRelatedType($tenantId, $userId, $defId, $marker);

        if ($normalized === null) {
            if ($existingId === null) {
                return 'unchanged';
            }

            return $this->seniorityRepository->deletePeriodById($existingId, $tenantId, $userId)
                ? 'cleared'
                : 'unchanged';
        }

        if ($existingId !== null) {
            $current = $this->seniorityRepository->findPeriodStartDateById($existingId, $tenantId, $userId);
            if ($current === $normalized) {
                return 'unchanged';
            }

            return $this->seniorityRepository->updatePeriodStartDate($existingId, $tenantId, $userId, $normalized)
                ? 'updated'
                : 'unchanged';
        }

        /* Ne pas écraser une saisie hors marqueur (autre source). */
        $periods = $this->seniorityRepository->listPeriodsForUserAndDefinition($userId, $defId);
        if ($periods !== []) {
            $firstStart = trim((string) ($periods[0]['start_date'] ?? ''));
            if ($firstStart === $normalized) {
                return 'unchanged';
            }
            /* Remplacer la première période existante si elle n’a pas notre marqueur : on crée quand même
               une ligne marquée pour garder la traçabilité ; le moteur cumule / prend from_start. */
        }

        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $newId = $this->seniorityRepository->insertPeriod(
            $tenantId,
            $userId,
            $defId,
            $normalized,
            $marker,
            null,
            'active',
            is_string($metaJson) ? $metaJson : null,
        );

        return $newId !== null ? 'inserted' : 'insert_failed';
    }

    private function normalizeDateString(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);
        if ($t === '' || str_starts_with($t, '0000-00-00')) {
            return null;
        }
        try {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $t, $m)) {
                $d = new DateTimeImmutable($m[1]);

                return $d->format('Y-m-d');
            }
            if (preg_match('/^(\d{4})$/', $t, $m)) {
                $d = new DateTimeImmutable($m[1] . '-01-01');

                return $d->format('Y-m-d');
            }
            $d = new DateTimeImmutable($t);

            return $d->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
