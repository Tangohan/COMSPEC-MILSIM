<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * En-tête papier : communauté, unité, groupe — sans les exemples figés du formulaire.
 */
final class CourrierLetterhead
{
    private const PLACEHOLDERS = [
        'ministère de la défense',
        'ministere de la defense',
        '92e ri — cerbere',
        '92e ri - cerbere',
        '92e ri — cerbère',
        'rh / s1',
        'rh/s1',
        '(à définir)',
        '(a definir)',
        '—',
        '-',
    ];

    private const GROUP_TYPES = ['group', 'team', 'section', 'groupe', 'equipe', 'équipe'];

    /**
     * @param list<array{name?: mixed, type?: mixed}> $leafToRoot
     * @return array{tenant_name: string, unit_name: string, group_name: string}
     */
    public static function fromAssignmentChain(
        array $leafToRoot,
        string $tenantName,
        string $affiliation = '',
        string $jobRole = ''
    ): array {
        $tenantName = trim($tenantName);
        $affiliation = trim($affiliation);
        $jobRole = trim($jobRole);

        $unitName = '';
        $groupName = '';
        foreach ($leafToRoot as $node) {
            if (!is_array($node)) {
                continue;
            }
            $name = trim((string) ($node['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = self::fold((string) ($node['type'] ?? ''));
            if (in_array($type, self::GROUP_TYPES, true)) {
                if ($groupName === '') {
                    $groupName = $name;
                }
                continue;
            }
            if ($unitName === '') {
                $unitName = $name;
            }
        }

        if ($unitName === '' && $leafToRoot !== []) {
            $root = $leafToRoot[array_key_last($leafToRoot)];
            if (is_array($root)) {
                $rootName = trim((string) ($root['name'] ?? ''));
                if ($rootName !== '' && $rootName !== $groupName) {
                    $unitName = $rootName;
                }
            }
        }

        if ($groupName === '') {
            $groupName = $jobRole;
        }

        if ($unitName === '' || ($tenantName !== '' && self::fold($unitName) === self::fold($tenantName))) {
            if ($affiliation !== '') {
                $unitName = $affiliation;
            }
        }

        return [
            'tenant_name' => $tenantName,
            'unit_name' => $unitName,
            'group_name' => $groupName,
        ];
    }

    public static function usable(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $folded = self::fold($value);
        foreach (self::PLACEHOLDERS as $dummy) {
            if ($folded === $dummy) {
                return '';
            }
        }

        return $value;
    }

    /**
     * @param array{header_line1?: string, header_unit?: string, header_section?: string} $stored
     * @param array{header_line1?: string, header_unit?: string, header_section?: string} $fromOrg
     * @return array{header_line1: string, header_unit: string, header_section: string}
     */
    public static function overlay(array $stored, array $fromOrg): array
    {
        $keys = ['header_line1', 'header_unit', 'header_section'];
        $out = ['header_line1' => '', 'header_unit' => '', 'header_section' => ''];
        foreach ($keys as $key) {
            $kept = self::usable((string) ($stored[$key] ?? ''));
            $org = trim((string) ($fromOrg[$key] ?? ''));
            $out[$key] = $kept !== '' ? $kept : $org;
        }

        return $out;
    }

    /**
     * @param array{tenant_name?: string, unit_name?: string, group_name?: string} $org
     * @return array{header_line1: string, header_unit: string, header_section: string}
     */
    public static function fieldsFromOrg(array $org): array
    {
        return [
            'header_line1' => trim((string) ($org['tenant_name'] ?? '')),
            'header_unit' => trim((string) ($org['unit_name'] ?? '')),
            'header_section' => trim((string) ($org['group_name'] ?? '')),
        ];
    }

    private static function fold(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = str_replace(['’', '`'], "'", $s);
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        return strtr($s, $map);
    }
}
