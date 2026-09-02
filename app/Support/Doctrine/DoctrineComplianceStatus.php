<?php

declare(strict_types=1);

namespace App\Support\Doctrine;

/**
 * Statut de conformité membre ↔ doctrine (calcul centralisé).
 */
final class DoctrineComplianceStatus
{
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';
    public const UNREAD = 'UNREAD';
    public const READ = 'READ';
    public const ACK_REQUIRED = 'ACK_REQUIRED';
    public const ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const ACK_OUTDATED = 'ACK_OUTDATED';
    public const OVERDUE = 'OVERDUE';

    /** @return array<string, string> */
    public static function memberBadgeLabels(): array
    {
        return [
            self::NOT_APPLICABLE => 'Non concerné',
            self::UNREAD => 'À lire',
            self::READ => 'Consulté',
            self::ACK_REQUIRED => 'À signer',
            self::ACKNOWLEDGED => 'Pris en compte',
            self::ACK_OUTDATED => 'Nouvelle version',
            self::OVERDUE => 'En retard',
        ];
    }

    public static function label(string $status): string
    {
        return self::memberBadgeLabels()[$status] ?? $status;
    }
}
