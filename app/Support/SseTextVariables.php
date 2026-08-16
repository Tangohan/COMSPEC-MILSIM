<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contexte de substitution des mentions de la bibliothèque rédactionnelle.
 *
 * Une variable sans valeur connue n'est pas remplacée par du vide : elle reste
 * visible dans le texte inséré, à charge du rédacteur de la compléter.
 */
final class SseTextVariables
{
    /**
     * @param array<string,mixed>|null $tenant
     * @param array<string,mixed>|null $case
     * @param array<string,mixed>|null $extra personne.identite, site.designation, piece.reference…
     * @return array<string,string>
     */
    public static function context(?array $tenant = null, ?array $case = null, string $author = '', ?array $extra = null): array
    {
        $ctx = [
            'date' => date('d/m/Y'),
            'heure' => date('H\hi'),
            'unite.nom' => self::unitName($tenant),
            'bureau.nom' => 'Bureau SSE',
            'redacteur.identite' => trim($author),
        ];

        if (is_array($case) && $case !== []) {
            $ctx['dossier.numero'] = (string) ($case['reference_code'] ?? '');
            $ctx['dossier.nom'] = (string) ($case['title'] ?? '');
            $ctx['dossier.classification'] = (string) ($case['classification_label'] ?? $case['classification'] ?? '');
            $ctx['dossier.statut'] = (string) ($case['status_label'] ?? $case['status'] ?? '');
            $ctx['dossier.date_ouverture'] = self::frDate($case['created_at'] ?? null);
            $ctx['dossier.date_revision'] = self::frDate($case['review_at'] ?? $case['updated_at'] ?? null);
        }

        foreach ((array) $extra as $key => $value) {
            $key = strtolower(trim((string) $key));
            if ($key !== '' && isset(SseTextLibraryCatalog::VARIABLES[$key])) {
                $ctx[$key] = trim((string) $value);
            }
        }

        return array_filter($ctx, static fn (string $v): bool => $v !== '');
    }

    private static function unitName(?array $tenant): string
    {
        if (!is_array($tenant) || $tenant === []) {
            return '';
        }
        if (function_exists('community_display_name')) {
            $name = (string) community_display_name($tenant);
            if (trim($name) !== '') {
                return $name;
            }
        }

        return trim((string) ($tenant['name'] ?? ''));
    }

    private static function frDate(mixed $raw): string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return '';
        }
        $stamp = strtotime($raw);

        return $stamp ? date('d/m/Y', $stamp) : '';
    }
}
