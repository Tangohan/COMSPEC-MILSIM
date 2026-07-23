<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Messages de groupe ATAK Enhanced (Iceman) remontés via le canal messagerie Athena.
 * Préfixe : « GROUPE|groupId|callsign|grid|texte »
 */
final class GroupMessageParser
{
    private const PREFIX = 'GROUPE';

    /**
     * @return array{
     *   is_group: bool,
     *   group_id: string,
     *   call_sign: string,
     *   grid: string,
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
        // [0]=GROUPE [1]=groupId [2]=callsign [3]=grid [4]=texte…
        $groupId = (string) ($parts[1] ?? '');
        $callSign = (string) ($parts[2] ?? '');
        $grid = (string) ($parts[3] ?? '');
        $text = trim(implode('|', array_slice($parts, 4)));
        if ($text === '') {
            $text = 'Message de groupe';
        }

        return [
            'is_group' => true,
            'group_id' => $groupId,
            'call_sign' => $callSign,
            'grid' => $grid,
            'text' => $text,
            'label' => 'Message de groupe',
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
