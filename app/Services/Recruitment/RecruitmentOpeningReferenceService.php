<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Construit reference_public à partir du format tenant + unité + compteur annuel.
 */
final class RecruitmentOpeningReferenceService
{
    /**
     * @param array<string, mixed> $format TenantRecruitmentSettings::referenceFormatFromSettings()
     * @param array<string, mixed> $tenant ligne tenants (name, slug, community_code…)
     * @param array<string, mixed> $unit ligne units
     */
    public function buildReference(array $format, array $tenant, array $unit, int $year, int $seq): string
    {
        $sep = (string) ($format['separator'] ?? '/');
        if ($sep === '') {
            $sep = '/';
        }
        $parts = [];
        if (!empty($format['include_organization_tag'])) {
            $tag = trim((string) ($format['organization_tag'] ?? ''));
            if ($tag === '') {
                $cc = trim((string) ($tenant['community_code'] ?? ''));
                if ($cc !== '') {
                    $tag = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $cc) ?? '');
                }
            }
            if ($tag === '') {
                $slug = (string) ($tenant['slug'] ?? 'org');
                $tag = strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $slug) ?: 'ORG', 0, 6));
            }
            if ($tag !== '') {
                $parts[] = $tag;
            }
        }
        if (!empty($format['include_unit_code'])) {
            $code = trim((string) ($unit['code'] ?? ''));
            if ($code === '') {
                $us = (string) ($unit['slug'] ?? 'u');
                $code = strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $us) ?: 'UNT', 0, 10));
            }
            $parts[] = $code;
        }
        if (!empty($format['include_rec_segment'])) {
            $rec = trim((string) ($format['rec_segment'] ?? 'REC'));
            if ($rec !== '') {
                $parts[] = strtoupper($rec);
            }
        }
        $suffix = sprintf('%03d-%d', max(1, $seq), $year);
        if ($parts === []) {
            return $suffix;
        }

        return implode($sep, $parts) . $sep . $suffix;
    }

    public function slugFromReference(string $referencePublic): string
    {
        $s = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $referencePublic));
        $s = trim($s, '-');

        return substr($s !== '' ? $s : 'avis', 0, 100);
    }
}
