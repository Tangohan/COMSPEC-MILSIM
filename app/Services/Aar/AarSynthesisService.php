<?php

declare(strict_types=1);

namespace App\Services\Aar;

/**
 * Condense plusieurs comptes rendus en trois textes modifiables : ce qu'il faut améliorer, ce qu'on en retient, la
 * conclusion. Aucune réécriture : on reprend le texte des auteurs, classé par compte rendu, sans doublon.
 */
final class AarSynthesisService
{
    /**
     * @param list<array<string, mixed>> $reports comptes rendus présentés (AarReportRepository::present)
     * @return array{improvements: string, lessons: string, conclusion: string}
     */
    public static function condense(array $reports): array
    {
        $improvements = [];
        $lessons = [];
        $conclusions = [];
        $seen = [];
        foreach ($reports as $r) {
            $ref = trim((string) ($r['reference_code'] ?? ''));
            $label = trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) $r['operation_label'] : (string) ($r['title'] ?? '');
            $head = trim($ref . ($label !== '' ? ' · ' . $label : ''));
            foreach ((array) ($r['weaknesses'] ?? []) as $w) {
                $improvements[] = self::line($head, (string) $w, $seen);
            }
            foreach ((array) ($r['open_actions'] ?? []) as $a) {
                $improvements[] = self::line($head, (string) (is_array($a) ? ($a['label'] ?? '') : $a), $seen);
            }
            $lessons[] = self::line($head, (string) ($r['lessons_learned'] ?? ''), $seen);
            $conclusions[] = self::line($head, (string) ($r['conclusion_text'] ?? ''), $seen);
        }

        return [
            'improvements' => implode("\n", array_values(array_filter($improvements))),
            'lessons' => implode("\n", array_values(array_filter($lessons))),
            'conclusion' => implode("\n", array_values(array_filter($conclusions))),
        ];
    }

    /** @param array<string, true> $seen */
    private static function line(string $head, string $text, array &$seen): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return '';
        }
        $key = mb_strtolower($text, 'UTF-8');
        if (isset($seen[$key])) {
            return '';
        }
        $seen[$key] = true;

        return ($head !== '' ? '[' . $head . '] ' : '') . $text;
    }
}
