<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Support\CommunityLanguages;

/**
 * Règles pures de la fiche communauté (administration du site) : listes blanches et masquage.
 * Aucune lecture de base ici, pour rester testable sans serveur.
 */
final class TenantEditRules
{
    /** @var list<string> onglets de la fiche, dans l'ordre d'affichage */
    public const TABS = ['resume', 'edition', 'membres', 'diagnostic'];

    public const MEMBER_PER_PAGE = 20;

    /** Onglet demandé, liste blanche (inconnu = résumé). */
    public static function tab(mixed $raw): string
    {
        $v = is_string($raw) ? $raw : '';

        return in_array($v, self::TABS, true) ? $v : 'resume';
    }

    /** Langue de la communauté : code reconnu ou null. */
    public static function locale(mixed $raw): ?string
    {
        return CommunityLanguages::normalize($raw);
    }

    /** Fuseau horaire IANA connu de PHP ou null. */
    public static function timezone(mixed $raw): ?string
    {
        $v = trim((string) $raw);
        if ($v === '' || strlen($v) > 64) {
            return null;
        }

        return in_array($v, \DateTimeZone::listIdentifiers(), true) ? $v : null;
    }

    /** Identifiant utilisateur positif ou null. */
    public static function positiveId(mixed $raw): ?int
    {
        $s = is_string($raw) || is_int($raw) ? (string) $raw : '';

        return preg_match('/^[1-9][0-9]{0,9}$/', $s) === 1 ? (int) $s : null;
    }

    /**
     * Retire d'un texte libre (message d'erreur, résumé) ce qui ne doit pas sortir : adresses e-mail, URL avec
     * identifiants, jetons et longues suites hexadécimales ou base64, valeurs de clés sensibles.
     */
    public static function redact(string $text, int $max = 160): string
    {
        $text = preg_replace('~[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}~', '[e-mail]', $text) ?? '';
        $text = preg_replace('~https?://[^\s/@]+:[^\s/@]+@~i', 'https://[masqué]@', $text) ?? '';
        $text = preg_replace('~(?i)(password|passwd|secret|token|api[_-]?key|authorization|bearer)\s*[=:]\s*\S+~', '$1=[masqué]', $text) ?? '';
        $text = preg_replace('~\b[A-Za-z0-9_\-]{32,}\b~', '[masqué]', $text) ?? '';
        $text = trim(preg_replace('~\s+~', ' ', $text) ?? '');

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }

    /**
     * Rapport de diagnostic en texte brut, à copier dans un ticket. Reçoit des valeurs déjà prêtes à être lues :
     * jamais de secret, d'e-mail ni de jeton (tout passe par redact()).
     *
     * @param array{title: string, generated: string, sections: list<array{title: string, lines: list<string>}>} $report
     */
    public static function reportText(array $report): string
    {
        $out = [self::redact($report['title'], 200), 'Généré le ' . self::redact($report['generated'], 40), ''];
        foreach ($report['sections'] as $section) {
            $out[] = '== ' . self::redact($section['title'], 80) . ' ==';
            foreach ($section['lines'] as $line) {
                $out[] = '- ' . self::redact($line, 240);
            }
            $out[] = '';
        }

        return rtrim(implode("\n", $out)) . "\n";
    }
}
