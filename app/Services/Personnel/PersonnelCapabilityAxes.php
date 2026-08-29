<?php

declare(strict_types=1);

namespace App\Services\Personnel;

/**
 * Instantané des quatre axes RH — volontairement séparés.
 *
 * Un membre peut être OF-3, ACTING Team Leader, Medic VALID mais NON_CURRENT,
 * donc readiness élevée mais non déployable.
 */
final class PersonnelCapabilityAxes
{
    public const AXIS_GRADE = 'grade_level';

    public const AXIS_FUNCTION = 'function_billet';

    public const AXIS_QUALIFICATION = 'qualification';

    public const AXIS_CAPABILITY = 'operational_capability';

    /**
     * @param array{
     *   grade?: array<string, mixed>|null,
     *   function?: array<string, mixed>|null,
     *   qualifications?: list<array<string, mixed>>,
     *   capability?: array<string, mixed>|null
     * } $parts
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly ?array $grade,
        public readonly ?array $function,
        /** @var list<array<string, mixed>> */
        public readonly array $qualifications,
        public readonly ?array $capability,
    ) {}

    /**
     * @return array{
     *   tenant_id: int,
     *   user_id: int,
     *   axes: array{
     *     grade_level: ?array<string, mixed>,
     *     function_billet: ?array<string, mixed>,
     *     qualification: list<array<string, mixed>>,
     *     operational_capability: ?array<string, mixed>
     *   },
     *   invariants: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'axes' => [
                self::AXIS_GRADE => $this->grade,
                self::AXIS_FUNCTION => $this->function,
                self::AXIS_QUALIFICATION => $this->qualifications,
                self::AXIS_CAPABILITY => $this->capability,
            ],
            'invariants' => $this->invariants(),
        ];
    }

    /**
     * Règles métier explicites pour éviter la fusion des axes.
     *
     * @return list<string>
     */
    public function invariants(): array
    {
        return [
            'GRADE_IS_NOT_FUNCTION',
            'FUNCTION_IS_NOT_QUALIFICATION',
            'QUALIFICATION_VALID_IS_NOT_CURRENCY',
            'CURRENCY_IS_NOT_DEPLOYABILITY',
            'TEMPORARY_ASSIGNMENT_DOES_NOT_CHANGE_GRADE',
        ];
    }

    public function isDeployable(): bool
    {
        return !empty($this->capability['deployable']);
    }

    public function readinessPercent(): int
    {
        return max(0, min(100, (int) ($this->capability['readiness_percent'] ?? 0)));
    }

    /**
     * @return list<string>
     */
    public function blockingCodes(): array
    {
        $codes = $this->capability['blocking_codes'] ?? [];
        if (!is_array($codes)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $codes)));
    }
}
