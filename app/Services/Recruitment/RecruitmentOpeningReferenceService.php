<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Construit reference_public (style avis / AO : organisation, unité, domaine, numéro d’exercice).
 */
final class RecruitmentOpeningReferenceService
{
    private const REF_MAX_LEN = 270;

    /**
     * @param array<string, mixed> $format TenantRecruitmentSettings::referenceFormatFromSettings()
     * @param array<string, mixed> $tenant ligne tenants (name, slug, community_code…)
     * @param array<string, mixed> $unit ligne units
     * @param array<string, mixed>|null $opening ligne offre (arm_domain…) au moment de la publication ; optionnel pour l’aperçu BO
     */
    public function buildReference(array $format, array $tenant, array $unit, int $year, int $seq, ?array $opening = null): string
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
        if (!empty($format['include_ao_segment'])) {
            $ao = trim((string) ($format['ao_segment'] ?? 'AO'));
            if ($ao === '') {
                $ao = 'AO';
            }
            $parts[] = strtoupper(substr($ao, 0, 12));
        }
        $unitNameAbbr = '';
        if (!empty($format['include_unit_name_abbr'])) {
            $unitNameAbbr = $this->unitNameAbbreviation(trim((string) ($unit['name'] ?? '')));
        }
        if (!empty($format['include_unit_code'])) {
            $code = trim((string) ($unit['code'] ?? ''));
            if ($code === '') {
                $us = (string) ($unit['slug'] ?? 'u');
                $code = strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $us) ?: 'UNT', 0, 10));
            }
            if ($code !== '') {
                $parts[] = $code;
            }
        }
        if (!empty($format['include_unit_name_abbr']) && $unitNameAbbr !== '') {
            $parts[] = $unitNameAbbr;
        }
        if (!empty($format['include_arm_domain_abbr'])) {
            $arm = isset($opening['arm_domain']) ? trim((string) $opening['arm_domain']) : '';
            $armAbbr = $this->armDomainAbbrev($arm);
            if ($armAbbr !== '') {
                $parts[] = $armAbbr;
            }
        }
        if (!empty($format['include_rec_segment'])) {
            $rec = trim((string) ($format['rec_segment'] ?? 'REC'));
            if ($rec !== '') {
                $parts[] = strtoupper($rec);
            }
        }
        $year = max(1970, min(2100, $year));
        $seq = max(1, $seq);
        $suffix = sprintf('%d-%04d', $year, $seq);
        if ($parts === []) {
            return $suffix;
        }

        $ref = implode($sep, $parts) . $sep . $suffix;

        return $this->clampTotalLength($ref, $suffix, $sep);
    }

    public function slugFromReference(string $referencePublic): string
    {
        $s = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $referencePublic));
        $s = trim($s, '-');

        return substr($s !== '' ? $s : 'avis', 0, 100);
    }

    private function armDomainAbbrev(string $key): string
    {
        if ($key === '') {
            return '';
        }
        /** @var array<string, string> $map */
        $map = [
            'infantry' => 'INF',
            'cavalry' => 'CAV',
            'artillery' => 'ART',
            'logistics' => 'LOG',
            'train' => 'TRN',
            'engineering' => 'GEN',
            'aviation' => 'AVN',
            'signals' => 'TRS',
            'other' => 'DOM',
        ];

        return $map[$key] ?? strtoupper(substr(preg_replace('/[^a-z]/i', '', $key) ?: 'DOM', 0, 4));
    }

    /**
     * Abrégé lisible du nom d’unité (plusieurs segments type « 1RE-REG-TRA »).
     */
    private function unitNameAbbreviation(string $name, int $maxLen = 28): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($ascii === false) {
            $ascii = $name;
        }
        $upper = strtoupper($ascii);
        $tokens = preg_split('/[\s\/\-–—]+/u', $upper, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['DE', 'DU', 'DES', 'LA', 'LE', 'LES', 'ET', 'D', 'L', 'EN', 'AU', 'AUX', 'ST', 'STE', 'CIE'];
        $chunks = [];
        foreach ($tokens as $t) {
            $t = preg_replace('/[^A-Z0-9]/', '', $t) ?? '';
            if ($t === '' || in_array($t, $stop, true)) {
                continue;
            }
            if (preg_match('/^\d/', $t)) {
                $chunks[] = substr($t, 0, 5);
            } elseif (strlen($t) <= 4) {
                $chunks[] = $t;
            } else {
                $chunks[] = substr($t, 0, 3);
            }
            if (count($chunks) >= 5) {
                break;
            }
        }
        if ($chunks === []) {
            return '';
        }
        $out = implode('-', $chunks);
        if (strlen($out) > $maxLen) {
            $out = substr($out, 0, $maxLen);
            $out = rtrim($out, '-');
        }

        return $out;
    }

    private function clampTotalLength(string $ref, string $suffix, string $sep = '/'): string
    {
        if (strlen($ref) <= self::REF_MAX_LEN) {
            return $ref;
        }
        $suffixPart = $sep . $suffix;
        if (!str_ends_with($ref, $suffix)) {
            return substr($ref, 0, self::REF_MAX_LEN);
        }
        $prefix = substr($ref, 0, -strlen($suffixPart));
        $budget = self::REF_MAX_LEN - strlen($suffixPart);
        if ($budget < 4) {
            return substr($suffix, 0, self::REF_MAX_LEN);
        }
        $prefix = rtrim(substr($prefix, 0, $budget), $sep);

        return $prefix . $suffixPart;
    }
}
