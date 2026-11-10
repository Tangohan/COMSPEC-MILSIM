<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Support\AtakReleaseInfo;
use App\Support\GameClientVersionGate;

/**
 * État du client qui a envoyé un rapport du mod (ticket Discord) : versions comparées aux dernières publiées,
 * et preuve du module officiel vue par Athena à la réception.
 *
 * D'où viennent les versions du rapport (POST /api/atak/mod-report, fn_reportDiag du PBO comspec_overwatch_connect,
 * embarqué dans l'ATAK natif comme dans Overwatch) :
 *  - mod_version : versionStr de CfgPatches comspec_overwatch_connect (fn_getModVersion), donc la liaison
 *    « Overwatch connect », pas l'ATAK natif ;
 *  - extension_version : version de la DLL COMSPEC Link (GetExtensionVersion) ;
 *  - ATAK natif et SSE : pas envoyés aujourd'hui ; lus dans context_json (atak_version, sse_version) s'ils y sont.
 * Dernières versions : AtakReleaseInfo (fichiers du dépôt déployé).
 *
 * Intégrité (colonne client_attest, posée à la réception par OfficialClientAttestation) :
 *  valid : preuve du module officiel bonne ; absent : aucune preuve ; invalid : preuve fausse ou périmée ;
 *  off : attestation pas configurée sur le serveur ; null : rapport reçu avant ce contrôle.
 * La preuve couvre la DLL, pas les PBO, et un client modifié qui a extrait le secret peut la reproduire.
 */
final class AtakModReportClientState
{
    public const UP_TO_DATE = 'up_to_date';
    public const OUTDATED = 'outdated';
    public const NEWER = 'newer';
    public const MISSING = 'missing';
    public const UNREADABLE = 'unreadable';
    public const NO_REFERENCE = 'no_reference';

    public const INTEGRITY_OK = 'ok';
    public const INTEGRITY_KO = 'ko';
    public const INTEGRITY_UNKNOWN = 'unknown';

    /** Composants affichés : clé => [libellé, toujours affiché]. SSE est facultatif : affiché seulement s'il est transmis. */
    private const COMPONENTS = [
        'atak' => ['ATAK', true],
        'link' => ['Link', true],
        'connect' => ['Overwatch connect', true],
        'sse' => ['SSE', false],
    ];

    /** @var array{atak:?string, link:?string, connect:?string, sse:?string}|null */
    private static ?array $latestCache = null;

    /**
     * Dernières versions publiées (fichiers du dépôt).
     *
     * @return array{atak:?string, link:?string, connect:?string, sse:?string}
     */
    public static function latest(): array
    {
        if (self::$latestCache !== null) {
            return self::$latestCache;
        }
        try {
            $info = AtakReleaseInfo::latest();

            return self::$latestCache = [
                'atak' => $info['atak_native'],
                'link' => $info['link'] ?? $info['link_expected'],
                'connect' => AtakReleaseInfo::connectVersion(),
                'sse' => AtakReleaseInfo::sseVersion(),
            ];
        } catch (\Throwable) {
            return ['atak' => null, 'link' => null, 'connect' => null, 'sse' => null];
        }
    }

    /**
     * Versions transmises par le rapport, par composant (null : non transmise).
     *
     * @param array<string, mixed> $report
     * @return array{atak:?string, link:?string, connect:?string, sse:?string}
     */
    public static function reported(array $report): array
    {
        $context = [];
        $raw = $report['context_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $context = is_array($decoded) ? $decoded : [];
        }
        $pick = static function (mixed ...$values): ?string {
            foreach ($values as $v) {
                $v = is_scalar($v) ? trim((string) $v) : '';
                if ($v !== '' && $v !== '?' && $v !== '-') {
                    return mb_substr($v, 0, 32);
                }
            }

            return null;
        };

        return [
            'atak' => $pick($report['atak_version'] ?? null, $context['atak_version'] ?? null, $context['atak'] ?? null),
            'link' => $pick($report['extension_version'] ?? null),
            'connect' => $pick($report['mod_version'] ?? null),
            'sse' => $pick($report['sse_version'] ?? null, $context['sse_version'] ?? null, $context['sse'] ?? null),
        ];
    }

    /** État d'une version par rapport à la dernière publiée. */
    public static function compare(?string $version, ?string $latest): string
    {
        if ($version === null || trim($version) === '') {
            return self::MISSING;
        }
        $v = GameClientVersionGate::normalizeVersion($version);
        if ($v === null) {
            return self::UNREADABLE;
        }
        $l = $latest !== null ? GameClientVersionGate::normalizeVersion($latest) : null;
        if ($l === null) {
            return self::NO_REFERENCE;
        }
        $cmp = GameClientVersionGate::compareVersions($v, $l);

        return $cmp < 0 ? self::OUTDATED : ($cmp > 0 ? self::NEWER : self::UP_TO_DATE);
    }

    /**
     * @param array<string, mixed> $report
     * @param array{atak:?string, link:?string, connect:?string, sse:?string}|null $latest null : AtakReleaseInfo
     * @return list<array{key:string, label:string, version:?string, latest:?string, state:string}>
     */
    public static function components(array $report, ?array $latest = null): array
    {
        $latest ??= self::latest();
        $reported = self::reported($report);
        $out = [];
        foreach (self::COMPONENTS as $key => [$label, $always]) {
            $version = $reported[$key];
            if ($version === null && !$always) {
                continue;
            }
            $ref = $latest[$key] ?? null;
            $out[] = ['key' => $key, 'label' => $label, 'version' => $version, 'latest' => $ref, 'state' => self::compare($version, $ref)];
        }

        return $out;
    }

    /**
     * Champ « Versions » : une ligne par composant, FR · EN.
     *
     * @param array<string, mixed> $report
     * @param array{atak:?string, link:?string, connect:?string, sse:?string}|null $latest
     */
    public static function versionsField(array $report, ?array $latest = null): string
    {
        $lines = [];
        foreach (self::components($report, $latest) as $c) {
            $lines[] = self::componentLine($c);
        }

        return implode("\n", $lines);
    }

    /** @param array{label:string, version:?string, latest:?string, state:string} $c */
    public static function componentLine(array $c): string
    {
        $name = $c['label'] . ($c['version'] !== null ? ' ' . self::safe($c['version']) : '');
        $latest = $c['latest'] !== null ? self::safe($c['latest']) : '';

        return $name . ' ' . match ($c['state']) {
            self::UP_TO_DATE => '✅ à jour · up to date',
            self::OUTDATED => '⚠️ pas à jour (' . $latest . ' disponible) · outdated (' . $latest . ' available)',
            self::NEWER => '🧪 plus récente que la publication (' . $latest . ') · newer than release',
            self::UNREADABLE => '❔ version illisible · unreadable version',
            self::NO_REFERENCE => '❔ dernière version inconnue · latest unknown',
            default => '— version non transmise · not reported',
        };
    }

    /**
     * @param array<string, mixed> $report
     * @param array{atak:?string, link:?string, connect:?string, sse:?string}|null $latest
     */
    public static function isOutdated(array $report, ?array $latest = null): bool
    {
        foreach (self::components($report, $latest) as $c) {
            if ($c['state'] === self::OUTDATED) {
                return true;
            }
        }

        return false;
    }

    public static function integrityState(mixed $attest): string
    {
        return match (is_string($attest) ? strtolower(trim($attest)) : '') {
            'valid' => self::INTEGRITY_OK,
            'absent', 'invalid' => self::INTEGRITY_KO,
            default => self::INTEGRITY_UNKNOWN,
        };
    }

    /** Champ « Intégrité » : ce qu'Athena a vu à la réception du rapport (FR · EN). */
    public static function integrityField(mixed $attest): string
    {
        return match (is_string($attest) ? strtolower(trim($attest)) : '') {
            'valid' => '✅ client vérifié : DLL officielle · verified official client',
            'invalid' => '❌ client non vérifié : preuve fausse ou périmée, DLL ou PBO non officiels ? · unverified client: bad or stale proof',
            'absent' => '❌ client non vérifié : aucune preuve, DLL non officielle ou recompilée ? · unverified client: no proof',
            'off' => '➖ non vérifié : attestation pas activée sur le serveur · not checked: attestation off',
            default => '➖ non vérifié · not checked',
        };
    }

    /**
     * Pastilles du titre : version périmée, client non vérifié. Vide si rien à signaler.
     *
     * @param array<string, mixed> $report
     * @param array{atak:?string, link:?string, connect:?string, sse:?string}|null $latest
     */
    public static function titleBadges(array $report, ?array $latest = null): string
    {
        $badges = [];
        if (self::integrityState($report['client_attest'] ?? null) === self::INTEGRITY_KO) {
            $badges[] = '❌ client non vérifié';
        }
        if (self::isOutdated($report, $latest)) {
            $badges[] = '⚠️ version périmée';
        }

        return implode(' · ', $badges);
    }

    /** Valeur envoyée par le jeu : jamais de mise en forme ni de mention Discord. */
    private static function safe(string $v): string
    {
        return preg_replace('/[^0-9A-Za-z.+_-]/', '', $v) ?? '';
    }
}
