<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Colonnes d'identité du tableur des effectifs : indicatif, matricule, prénom et nom, chacun dans sa colonne
 * (avant, tout était empilé dans la colonne « Membre »).
 *
 * Le prénom et le nom viennent de l'identité du membre (MemberName::withIdentity) ; un membre masqué ne montre que son
 * libellé d'anonymat, dans la colonne Nom. Le matricule affiché est celui de la communauté, à défaut le matricule Athena.
 */
final class RosterMemberColumns
{
    /** Colonnes du tableur que l'on peut masquer (menu « Colonnes ») ; l'indicatif et « Fiche » restent toujours affichés. */
    public const IDENTITY = ['matricule', 'prenom', 'nom'];

    /**
     * @param array<string, mixed> $nameRow ligne passée par MemberName::withIdentity
     * @return array{callsign: string, extra_callsigns: list<string>, matricule: string, matricule_title: string, first: string, last: string, masked: bool, fallback: string}
     */
    public static function split(array $nameRow, string $orgNumber = '', string $athenaId = '', array $extraCallsigns = []): array
    {
        $masked = MemberName::isMasked($nameRow);
        $callsign = $masked ? '' : trim((string) ($nameRow['callsign'] ?? ''));
        $first = $masked ? '' : trim((string) preg_replace('/\s+/u', ' ', (string) ($nameRow['first_name'] ?? '')));
        $lastRaw = $masked ? '' : trim((string) preg_replace('/\s+/u', ' ', (string) ($nameRow['last_name'] ?? '')));
        $last = $lastRaw !== '' ? MemberName::full(['last_name' => $lastRaw]) : '';
        if ($masked) {
            $last = MemberName::maskedLabel($nameRow);
        }
        $orgNumber = trim($orgNumber);
        $athenaId = trim($athenaId);
        $titleParts = array_values(array_filter([
            $orgNumber !== '' ? 'Matricule communauté ' . $orgNumber : '',
            $athenaId !== '' ? 'Matricule Athena ' . $athenaId : '',
        ]));
        // Sans indicatif, la première colonne garde un repère : pseudo du compte, à défaut « Membre ».
        $fallback = '';
        if ($callsign === '') {
            $display = trim((string) ($nameRow['display_name'] ?? ''));
            $fallback = $masked ? MemberName::maskedLabel($nameRow) : ($display !== '' && !MemberName::looksLikeEmail($display) ? $display : MemberName::FALLBACK);
        }

        return [
            'callsign' => $callsign,
            'extra_callsigns' => $masked ? [] : array_values(array_filter(array_map(static fn ($c): string => trim((string) $c), $extraCallsigns), static fn (string $c): bool => $c !== '')),
            'matricule' => $orgNumber !== '' ? $orgNumber : $athenaId,
            'matricule_title' => implode(' · ', $titleParts),
            'first' => $first,
            'last' => $last,
            'masked' => $masked,
            'fallback' => $fallback,
        ];
    }
}
