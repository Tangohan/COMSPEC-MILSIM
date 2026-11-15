<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Priorité calculée d'un élément de « À trier » (0 à 100) et son explication, affichée au survol : « pourquoi cet ordre ».
 *
 * Points : gravité (critique 40, haute 25, moyenne 12, information 4), ancienneté (2 points tous les 3 jours, 20 au plus),
 * sujet sous avis de recherche actif (20), classification du sujet (CUI 0, Confidentiel 3, Secret 7, Très secret 10),
 * signaux liés au même sujet (3 chacun, 10 au plus), majoration posée par une règle. Rien d'opaque : chaque ligne de l'explication
 * est un terme de la somme.
 */
final class SseTriagePriority
{
    public const SEVERITY_POINTS = ['critical' => 40, 'high' => 25, 'medium' => 12, 'info' => 4];
    public const SEVERITY_LABELS = ['critical' => 'Gravité critique', 'high' => 'Gravité haute', 'medium' => 'Gravité moyenne', 'info' => 'Gravité d’information'];
    public const WANTED_POINTS = 20;
    public const AGE_MAX = 20;
    public const LINKED_EACH = 3;
    public const LINKED_MAX = 10;
    public const CLASSIFICATION_POINTS = ['tres_restreint' => 10, 'confidentiel' => 7, 'commandement' => 3];
    public const CLASSIFICATION_LABELS = ['tres_restreint' => 'Très secret (TS)', 'confidentiel' => 'Secret (S)', 'commandement' => 'Confidentiel (C)'];

    /** Gravité d'un élément : celle que sa file déclare, sinon déduite de la priorité 0 à 2 des files d'origine. @param array<string, mixed> $item */
    public static function severityOf(array $item): string
    {
        $sev = (string) ($item['severity'] ?? '');
        if (isset(self::SEVERITY_POINTS[$sev])) {
            return $sev;
        }
        $p = (int) ($item['priority'] ?? 0);

        return $p >= 2 ? 'critical' : ($p === 1 ? 'high' : 'info');
    }

    /**
     * @param array<string, mixed> $item élément (severity ou priority, subject, at)
     * @param int $days âge en jours
     * @param int $linked autres éléments du même sujet dans la liste
     * @param int $boost majoration posée par une règle
     * @return array{score: int, why: list<array{label: string, points: int}>}
     */
    public static function score(array $item, int $days, int $linked, int $boost = 0): array
    {
        $why = [];
        $sev = self::severityOf($item);
        $why[] = ['label' => self::SEVERITY_LABELS[$sev], 'points' => self::SEVERITY_POINTS[$sev]];
        $age = min(self::AGE_MAX, intdiv($days * 2, 3));
        if ($age > 0) {
            $why[] = ['label' => 'Ancienneté : ' . $days . ' j', 'points' => $age];
        }
        $subject = is_array($item['subject'] ?? null) ? $item['subject'] : [];
        if (!empty($subject['wanted'])) {
            $why[] = ['label' => 'Sujet sous avis de recherche actif', 'points' => self::WANTED_POINTS];
        }
        $class = (string) ($subject['classification'] ?? '');
        if (isset(self::CLASSIFICATION_POINTS[$class])) {
            $why[] = ['label' => 'Classification : ' . self::CLASSIFICATION_LABELS[$class], 'points' => self::CLASSIFICATION_POINTS[$class]];
        }
        if ($linked > 0) {
            $why[] = ['label' => $linked === 1 ? '1 autre élément sur le même sujet' : $linked . ' autres éléments sur le même sujet', 'points' => min(self::LINKED_MAX, $linked * self::LINKED_EACH)];
        }
        if ($boost !== 0) {
            $why[] = ['label' => 'Majoration d’une règle Cerbère-Tri', 'points' => $boost];
        }
        $score = max(0, min(100, array_sum(array_column($why, 'points'))));

        return ['score' => $score, 'why' => $why];
    }
}
