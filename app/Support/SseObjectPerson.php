<?php

declare(strict_types=1);

namespace App\Support;

/** Convertit une désignation d'investigation sans inventer un nom ou un prénom. */
final class SseObjectPerson
{
    public static function fields(int $tenantId, string $label, array $meta): array
    {
        return [
            'tenant_id' => $tenantId,
            'context_id' => 1,
            'alias' => $label,
            'status' => (string) ($meta['status'] ?? 'civil'),
            'sex_apparent' => (string) ($meta['sex'] ?? ''),
            'nationality' => (string) ($meta['nationality'] ?? ''),
            'affiliation' => (string) ($meta['affiliation'] ?? ''),
            'confidence_level' => (string) ($meta['confidence'] ?? ''),
            'distinguishing_marks' => (string) ($meta['description'] ?? ''),
            'statements' => implode("\n", array_filter([
                (string) ($meta['age_range'] ?? ''),
                (string) ($meta['notes'] ?? ''),
                (string) ($meta['precision'] ?? ''),
            ], static fn (string $s): bool => $s !== '')),
            'submitter_callsign' => 'Bureau SSE',
        ];
    }
}
