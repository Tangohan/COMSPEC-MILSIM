<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Personnel\JobRoleEnrichmentService;

/**
 * Rendu commun de l’habillage d’une fonction : icône + abrégé, étiquettes, origine (« Site » / « Créée par vous »).
 * Les valeurs saisies par la communauté (abrégé, étiquettes, description) ne sont jamais traduites (translate="no").
 */
final class JobRoleBadge
{
    private static bool $stylesSent = false;

    public static function resetStyles(): void
    {
        self::$stylesSent = false;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Icône + abrégé ; chaîne vide quand la fonction n’a ni l’un ni l’autre. La description sert d’info-bulle.
     *
     * @param array<string, mixed>|null $decoration élément de JobRoleEnrichmentService::decorations()
     */
    public static function html(?array $decoration, bool $withDescription = true): string
    {
        if ($decoration === null) {
            return '';
        }
        $icon = isset($decoration['icon']) && is_string($decoration['icon']) ? $decoration['icon'] : null;
        $abbr = trim((string) ($decoration['abbreviation'] ?? ''));
        if (!JobRoleIconLibrary::has($icon) && $abbr === '') {
            return '';
        }
        $description = $withDescription ? trim((string) ($decoration['description'] ?? '')) : '';

        return self::styles() . '<span class="job-badge" translate="no"' . ($description !== '' ? ' title="' . self::e($description) . '"' : '') . '>'
            . JobRoleIconLibrary::svg($icon, 'job-badge__icon')
            . ($abbr !== '' ? '<span class="job-badge__abbr">' . self::e($abbr) . '</span>' : '')
            . '</span>';
    }

    /** @param list<array{name: string, color?: ?string}> $tags */
    public static function tags(array $tags): string
    {
        if ($tags === []) {
            return '';
        }
        $out = '';
        foreach ($tags as $tag) {
            $color = JobRoleEnrichmentService::normalizeTagColor($tag['color'] ?? null) ?? 'slate';
            $out .= '<span class="job-tag job-tag--' . $color . '" translate="no">' . self::e((string) ($tag['name'] ?? '')) . '</span>';
        }

        return self::styles() . '<span class="job-tags">' . $out . '</span>';
    }

    /** Badge d’origine : discret pour le site, mis en valeur pour une fonction créée par la communauté. */
    public static function origin(string $origin): string
    {
        return self::styles() . match ($origin) {
            JobRoleEnrichmentService::ORIGIN_COMMUNITY => '<span class="job-origin job-origin--community">Créée par vous</span>',
            JobRoleEnrichmentService::ORIGIN_SITE => '<span class="job-origin job-origin--site">Site</span>',
            JobRoleEnrichmentService::ORIGIN_ORBAT => '<span class="job-origin job-origin--orbat">ORBAT</span>',
            default => '',
        };
    }

    /** Feuille de style partagée, envoyée une seule fois par page. */
    public static function styles(): string
    {
        if (self::$stylesSent) {
            return '';
        }
        self::$stylesSent = true;

        return '<style>'
            . '.job-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.05rem .35rem;border:1px solid color-mix(in srgb,currentColor 25%,transparent);border-radius:.35rem;font-size:.72em;font-weight:700;line-height:1.4;letter-spacing:.04em;vertical-align:middle;white-space:nowrap;margin-inline-end:.3rem}'
            . '.job-badge__icon{width:1.05em;height:1.05em;flex:none}'
            . '.job-badge__abbr{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}'
            . '.job-tags{display:inline-flex;flex-wrap:wrap;gap:.25rem;vertical-align:middle}'
            . '.job-tag{--tag:#64748b;display:inline-block;padding:0 .4rem;border-radius:999px;font-size:.7rem;font-weight:600;line-height:1.5;color:var(--tag);background:color-mix(in srgb,var(--tag) 14%,transparent);border:1px solid color-mix(in srgb,var(--tag) 35%,transparent)}'
            . '.job-tag--red{--tag:#dc2626}.job-tag--amber{--tag:#b45309}.job-tag--green{--tag:#15803d}.job-tag--teal{--tag:#0f766e}'
            . '.job-tag--blue{--tag:#2563eb}.job-tag--violet{--tag:#7c3aed}.job-tag--pink{--tag:#db2777}'
            . '.job-origin{display:inline-block;padding:0 .4rem;border-radius:.3rem;font-size:.66rem;font-weight:700;letter-spacing:.03em;line-height:1.6;vertical-align:middle;text-transform:uppercase}'
            . '.job-origin--site{color:#64748b;border:1px solid color-mix(in srgb,#64748b 35%,transparent)}'
            . '.job-origin--community{color:#fff;background:#0f766e}'
            . '.job-origin--orbat{color:#64748b;border:1px dashed color-mix(in srgb,#64748b 45%,transparent)}'
            . '.job-hidden-flag{display:inline-block;padding:0 .4rem;border-radius:.3rem;font-size:.66rem;font-weight:700;line-height:1.6;color:#b45309;background:color-mix(in srgb,#f59e0b 16%,transparent)}'
            . '</style>';
    }
}
