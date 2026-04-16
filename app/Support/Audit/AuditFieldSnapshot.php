<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * Snapshots JSON pour audit_logs.old_value / new_value (champs filtrés, secrets exclus, troncature).
 */
final class AuditFieldSnapshot
{
    public const DEFAULT_MAX_BYTES = 65000;

    /** @var list<string> */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'hash', 'api_key', 'authorization', 'private_key',
    ];

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public static function pick(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $row)) {
                continue;
            }
            $out[$k] = self::normalizeScalar($row[$k]);
        }

        return self::stripSensitive($out);
    }

    /**
     * Ne conserve que les clés dont la valeur a changé (comparaison sérialisée).
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string> $keys
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diffOnly(array $before, array $after, array $keys): array
    {
        $o = self::pick($before, $keys);
        $n = self::pick($after, $keys);
        $changedOld = [];
        $changedNew = [];
        $allKeys = array_unique(array_merge(array_keys($o), array_keys($n)));
        foreach ($allKeys as $k) {
            $ov = $o[$k] ?? null;
            $nv = $n[$k] ?? null;
            if (self::serializeValue($ov) !== self::serializeValue($nv)) {
                $changedOld[$k] = $ov;
                $changedNew[$k] = $nv;
            }
        }

        return [$changedOld, $changedNew];
    }

    /**
     * @param array<string, mixed>|null $oldSubset
     * @param array<string, mixed>|null $newSubset
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function encodePair(?array $oldSubset, ?array $newSubset, int $maxBytes = self::DEFAULT_MAX_BYTES): array
    {
        $oldSubset = self::stripSensitive($oldSubset ?? []);
        $newSubset = self::stripSensitive($newSubset ?? []);
        if ($oldSubset === [] && $newSubset === []) {
            return [null, null];
        }
        $os = self::jsonTruncate($oldSubset, $maxBytes);
        $ns = self::jsonTruncate($newSubset, $maxBytes);

        return [$os, $ns];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function stripSensitive(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (self::isSensitiveKey((string) $k)) {
                continue;
            }
            if (is_array($v)) {
                $nested = self::stripSensitive($v);
                if ($nested !== []) {
                    $out[$k] = $nested;
                }
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEY_FRAGMENTS as $frag) {
            if (str_contains($lower, $frag)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeScalar(mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v) || is_int($v) || is_float($v)) {
            return $v;
        }
        if (is_string($v)) {
            return $v;
        }
        if (is_array($v)) {
            return self::stripSensitive($v);
        }

        return null;
    }

    private static function serializeValue(mixed $v): string
    {
        $enc = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $enc;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function jsonTruncate(array $payload, int $maxBytes): ?string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{"erreur":"encodage"}';
        }
        if (strlen($json) <= $maxBytes) {
            return $json;
        }
        $marker = '…(tronqué)';
        $cut = max(0, $maxBytes - strlen($marker));

        return substr($json, 0, $cut) . $marker;
    }
}
