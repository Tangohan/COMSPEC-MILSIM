<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Messages privés cTab (P2P) archivés sur le fil ATAK web.
 * Préfixe : « MP|from|to|texte »
 */
final class MpMessageParser
{
    private const PREFIX = 'MP';

    /**
     * @return array{
     *   is_mp: bool,
     *   from: string,
     *   to: string,
     *   text: string,
     *   label: string
     * }|null
     */
    public static function parse(?string $body): ?array
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }
        $body = self::stripCommsPrefix($body);
        $upper = mb_strtoupper($body);
        if (!str_starts_with($upper, self::PREFIX . '|') && $upper !== self::PREFIX) {
            return null;
        }

        $parts = array_map('trim', explode('|', $body));
        // [0]=MP [1]=from [2]=to [3]=texte…
        $from = (string) ($parts[1] ?? '');
        $to = (string) ($parts[2] ?? '');
        $text = trim(implode('|', array_slice($parts, 3)));
        if ($text === '') {
            $text = 'Message privé';
        }

        return [
            'is_mp' => true,
            'from' => $from,
            'to' => $to,
            'text' => $text,
            'label' => 'Message privé',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function enrichChatRow(array $row): ?array
    {
        $parsed = self::parse(isset($row['body']) ? (string) $row['body'] : null);
        if ($parsed === null) {
            return null;
        }

        return array_merge($parsed, [
            'id' => (int) ($row['id'] ?? 0),
            'author' => (string) ($row['author'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'map_id' => (int) ($row['map_id'] ?? 0),
        ]);
    }

    /**
     * True si le message concerne l’indicatif (émetteur ou destinataire).
     * Liste destinataires : callsigns séparés par virgule.
     */
    public static function concernsCallSign(array $mp, string $callSign): bool
    {
        $cs = mb_strtoupper(trim($callSign));
        if ($cs === '') {
            return false;
        }
        if (mb_strtoupper(trim((string) ($mp['from'] ?? ''))) === $cs) {
            return true;
        }
        $toRaw = (string) ($mp['to'] ?? '');
        foreach (preg_split('/\s*,\s*/', $toRaw) ?: [] as $part) {
            if (mb_strtoupper(trim((string) $part)) === $cs) {
                return true;
            }
        }

        return false;
    }

    private static function stripCommsPrefix(string $body): string
    {
        if (preg_match(
            '/^\[\d{1,2}:\d{2}:\d{2}\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\[[A-Za-z0-9_]+\]\s*([\s\S]+)$/u',
            $body,
            $m
        )) {
            return trim((string) ($m[1] ?? $body));
        }

        return $body;
    }
}
