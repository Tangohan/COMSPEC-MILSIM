<?php

declare(strict_types=1);

namespace App\Support\Doctrine;

final class DoctrineWorkflowStatus
{
    public const DRAFT = 'draft';
    public const REVIEW = 'review';
    public const APPROVAL = 'approval';
    public const PUBLISHED = 'published';
    public const SUSPENDED = 'suspended';
    public const OBSOLETE = 'obsolete';
    public const ARCHIVED = 'archived';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::REVIEW,
            self::APPROVAL,
            self::PUBLISHED,
            self::SUSPENDED,
            self::OBSOLETE,
            self::ARCHIVED,
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::DRAFT => 'Brouillon',
            self::REVIEW => 'En validation',
            self::APPROVAL => 'Validé',
            self::PUBLISHED => 'Publié',
            self::SUSPENDED => 'Suspendu',
            self::OBSOLETE => 'Abrogé',
            self::ARCHIVED => 'Archivé',
        ];
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }

    public static function isPublished(string $status): bool
    {
        return $status === self::PUBLISHED;
    }
}
