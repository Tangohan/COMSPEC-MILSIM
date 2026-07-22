<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Extrait et résout les mentions @Indicatif dans les messages radio ATAK.
 */
final class ChatMentionParser
{
    /** Indicatifs type N-10, HAWK-1, VIPER_2 (max 32 car. hors @). */
    private const TOKEN_PATTERN = '/(^|[\s\[\(\{,;:])@([A-Za-z0-9][A-Za-z0-9._\-]{0,31})\b/u';

    /**
     * Tokens bruts (sans @), ordre d’apparition, sans doublon (casse conservée du 1er).
     *
     * @return list<string>
     */
    public static function extractTokens(?string $body): array
    {
        $body = trim((string) $body);
        if ($body === '') {
            return [];
        }
        $body = self::stripCommsPrefix($body);
        if (!preg_match_all(self::TOKEN_PATTERN, $body, $m) || empty($m[2])) {
            return [];
        }
        $seen = [];
        $out = [];
        foreach ($m[2] as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            $key = mb_strtoupper($token);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $token;
        }

        return $out;
    }

    /**
     * Résout les mentions contre les unités carte (indicatif).
     *
     * @param list<array<string, mixed>> $units
     * @return list<array{
     *   token: string,
     *   call_sign: string,
     *   matched: bool,
     *   status: string,
     *   pos_x: ?float,
     *   pos_y: ?float
     * }>
     */
    public static function resolve(?string $body, array $units): array
    {
        $tokens = self::extractTokens($body);
        if ($tokens === []) {
            return [];
        }

        $byCall = [];
        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            $cs = trim((string) ($unit['call_sign'] ?? ''));
            if ($cs === '') {
                continue;
            }
            $byCall[mb_strtoupper($cs)] = $unit;
        }

        $out = [];
        foreach ($tokens as $token) {
            $key = mb_strtoupper($token);
            $unit = $byCall[$key] ?? null;
            if (!is_array($unit)) {
                $out[] = [
                    'token' => $token,
                    'call_sign' => $token,
                    'matched' => false,
                    'status' => '',
                    'pos_x' => null,
                    'pos_y' => null,
                ];
                continue;
            }
            $posX = isset($unit['pos_x']) && $unit['pos_x'] !== null && $unit['pos_x'] !== ''
                ? (float) $unit['pos_x']
                : null;
            $posY = isset($unit['pos_y']) && $unit['pos_y'] !== null && $unit['pos_y'] !== ''
                ? (float) $unit['pos_y']
                : null;
            if ($posX !== null && $posY !== null && !self::coordsLookValid($posX, $posY)) {
                $posX = null;
                $posY = null;
            }
            $out[] = [
                'token' => $token,
                'call_sign' => trim((string) ($unit['call_sign'] ?? $token)),
                'matched' => true,
                'status' => (string) ($unit['status'] ?? ''),
                'pos_x' => $posX,
                'pos_y' => $posY,
            ];
        }

        return $out;
    }

    /**
     * Indique si le texte mentionne l’indicatif donné (insensible à la casse).
     */
    public static function mentionsCallsign(?string $body, string $callsign): bool
    {
        $callsign = trim($callsign);
        if ($callsign === '') {
            return false;
        }
        $want = mb_strtoupper($callsign);
        foreach (self::extractTokens($body) as $token) {
            if (mb_strtoupper($token) === $want) {
                return true;
            }
        }

        return false;
    }

    private static function stripCommsPrefix(string $body): string
    {
        if (preg_match('/^\[\d{1,2}:\d{2}:\d{2}\]\[[^\]]+\]\[[^\]]+\]\[[^\]]+\]\s*/u', $body, $m)) {
            return trim(substr($body, strlen($m[0])));
        }

        return $body;
    }

    private static function coordsLookValid(float $x, float $y): bool
    {
        if (!is_finite($x) || !is_finite($y)) {
            return false;
        }
        // 0/0 = position absente fréquente côté synchro.
        if (abs($x) < 0.0001 && abs($y) < 0.0001) {
            return false;
        }

        return true;
    }
}
