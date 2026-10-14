<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

/**
 * Gravité d'un rapport du mod COMSPEC ATAK (page /admin/atak-mod-reports).
 *
 * Le mod remonte beaucoup d'erreurs automatiques, dont la plupart sont passagères (réseau, délai, serveur occupé).
 * La gravité est calculée à la réception d'après la source, le niveau envoyé par le mod et le texte du message,
 * puis peut être corrigée à la main sur la page. Elle décide aussi des e-mails :
 *  - critique : e-mail à l'exploitation, même pour une détection automatique ;
 *  - faible : jamais d'e-mail au joueur (ni automatique ni groupé), seulement la notification Athena si on répond.
 */
final class AtakModReportGravity
{
    public const CRITICAL = 'critical';
    public const HIGH = 'high';
    public const NORMAL = 'normal';
    public const LOW = 'low';

    /** Du plus grave au moins grave. */
    public const LEVELS = [self::CRITICAL, self::HIGH, self::NORMAL, self::LOW];

    /** @var array<string, string> */
    public const LABELS = [
        self::CRITICAL => 'Critique',
        self::HIGH => 'Importante',
        self::NORMAL => 'Moyenne',
        self::LOW => 'Faible',
    ];

    /** Ce que veut dire chaque niveau (aide sur la page). */
    public const HINTS = [
        self::CRITICAL => 'Le mod ou COMSPEC Link ne fonctionne plus (plantage, extension absente). E-mail à l’exploitation.',
        self::HIGH => 'Problème signalé par un joueur depuis le jeu. E-mail à l’exploitation.',
        self::NORMAL => 'Une fonction a échoué (envoi refusé, accès refusé par Athena…). Pas d’e-mail à l’exploitation.',
        self::LOW => 'Incident passager (réseau, délai, serveur occupé) ou simple alerte. Aucun e-mail.',
    ];

    /** Mots qui disent que le mod ou la liaison ne marche plus du tout. */
    private const CRITICAL_NEEDLES = [
        'crash', 'plantage', 'planté', 'fatal', 'out of memory', 'mémoire insuffisante', 'stack overflow',
        'access violation', 'callextension', 'extension introuvable', 'extension absente', 'extension non chargée',
        'dll introuvable', 'dll absente', 'dll manquante', 'failed to load', 'link absent', 'link introuvable',
        'corrompu', 'corrupt',
    ];

    /** Incidents passagers : le mod réessaie tout seul. */
    private const TRANSIENT_NEEDLES = [
        'timeout', 'timed out', 'time out', 'délai dépassé', 'délai d’attente', "délai d'attente", 'hors ligne', 'offline',
        'temporairement', 'too many requests', 'rate limit', 'rate_limited', 'http 429', 'http 502', 'http 503', 'http 504',
        ' 429', ' 502', ' 503', ' 504', 'bad gateway', 'service unavailable', 'connection reset',
        'connexion perdue', 'connexion interrompue', 'connection refused', 'network', 'réseau indisponible',
        'nouvel essai', 'retry', 'name resolution', 'could not resolve',
    ];

    public static function normalize(?string $raw): ?string
    {
        $s = strtolower(trim((string) $raw));

        return match ($s) {
            'critical', 'critique', 'crit', 'fatal', 'p1' => self::CRITICAL,
            'high', 'haute', 'importante', 'important', 'major', 'p2' => self::HIGH,
            'normal', 'moyenne', 'medium', 'moderate', 'p3' => self::NORMAL,
            'low', 'faible', 'minor', 'mineure', 'p4' => self::LOW,
            default => null,
        };
    }

    public static function label(?string $gravity): string
    {
        return self::LABELS[self::normalize($gravity) ?? self::NORMAL];
    }

    /** 0 = le plus grave. */
    public static function rank(?string $gravity): int
    {
        $i = array_search(self::normalize($gravity) ?? self::NORMAL, self::LEVELS, true);

        return $i === false ? 2 : (int) $i;
    }

    /** Signalement écrit par le joueur (Échap > Signaler un problème) plutôt que détection automatique. */
    public static function isPlayerReport(?string $source): bool
    {
        return str_starts_with(strtolower(trim((string) $source)), 'player');
    }

    /**
     * @param string $severity niveau envoyé par le mod (error, warn, info, bug)
     * @param string|null $requested gravité proposée par le mod lui-même (champ « gravity »), prioritaire si valable
     */
    public static function classify(string $source, string $severity, string $message, string $detail = '', ?string $requested = null): string
    {
        $forced = self::normalize($requested);
        if ($forced !== null) {
            return $forced;
        }
        $text = mb_strtolower($message . ' ' . $detail);
        $critical = self::contains($text, self::CRITICAL_NEEDLES);

        if (self::isPlayerReport($source)) {
            return $critical ? self::CRITICAL : self::HIGH;
        }
        if ($critical) {
            return self::CRITICAL;
        }
        // Refus attendu : la formule de la communauté n'inclut pas la carte (le joueur voit le message en jeu).
        if (\App\Support\AtakPlanAccess::isDenialReport($message, $detail)) {
            return self::LOW;
        }
        $sev = strtolower(trim($severity));
        if (in_array($sev, ['warn', 'warning', 'info'], true)) {
            return self::LOW;
        }
        // Accès refusé par Athena : le joueur est prévenu par e-mail avec la marche à suivre.
        if (AtakAuthFailureNotifier::isAuthFailure($source, $message, $detail)) {
            return self::NORMAL;
        }
        // Le message seul décide du caractère passager (le détail contient souvent des journaux bavards).
        if (self::contains(' ' . mb_strtolower($message), self::TRANSIENT_NEEDLES)) {
            return self::LOW;
        }

        return self::NORMAL;
    }

    /** E-mail à la boîte de l'exploitation pour un nouveau rapport. */
    public static function mailsOps(string $source, ?string $gravity): bool
    {
        if (self::isPlayerReport($source)) {
            return true;
        }

        return self::normalize($gravity) === self::CRITICAL;
    }

    /** E-mail au joueur (automatique ou réponse groupée) : jamais pour un rapport de gravité faible. */
    public static function mailsPlayer(?string $gravity): bool
    {
        return (self::normalize($gravity) ?? self::NORMAL) !== self::LOW;
    }

    /** @param list<string> $needles */
    private static function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
