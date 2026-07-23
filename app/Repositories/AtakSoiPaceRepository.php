<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Plan de fréquences PACE (Primary / Alternate / Contingency / Emergency) par théâtre.
 * Stockage fichier JSON (même modèle que le journal de liaison).
 */
final class AtakSoiPaceRepository
{
    private function path(int $tenantId, int $mapId): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/atak-soi';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/t' . $tenantId . '_m' . $mapId . '.json';
    }

    /**
     * @return array{
     *   primary: array{label:string,freq:string,net:string,notes:string},
     *   alternate: array{label:string,freq:string,net:string,notes:string},
     *   contingency: array{label:string,freq:string,net:string,notes:string},
     *   emergency: array{label:string,freq:string,net:string,notes:string},
     *   teams: list<array{name:string,primary:string,alternate:string,contingency:string,emergency:string}>,
     *   updated_at: ?string,
     *   updated_by: ?string
     * }
     */
    public function get(int $tenantId, int $mapId): array
    {
        $empty = $this->emptyPlan();
        if ($tenantId < 1 || $mapId < 1) {
            return $empty;
        }
        $path = $this->path($tenantId, $mapId);
        if (!is_file($path)) {
            return $empty;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $empty;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $empty;
        }

        return $this->normalize($decoded);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function save(int $tenantId, int $mapId, array $payload, ?string $actor = null): array
    {
        $plan = $this->normalize($payload);
        $plan['updated_at'] = gmdate('c');
        $plan['updated_by'] = $actor !== null && $actor !== '' ? mb_substr($actor, 0, 80) : null;
        $path = $this->path($tenantId, $mapId);
        $json = json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (is_string($json)) {
            @file_put_contents($path, $json, LOCK_EX);
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPlan(): array
    {
        $slot = static fn (): array => ['label' => '', 'freq' => '', 'net' => '', 'notes' => ''];

        return [
            'primary' => $slot(),
            'alternate' => $slot(),
            'contingency' => $slot(),
            'emergency' => $slot(),
            'teams' => [],
            'updated_at' => null,
            'updated_by' => null,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $base = $this->emptyPlan();
        foreach (['primary', 'alternate', 'contingency', 'emergency'] as $key) {
            $slot = $raw[$key] ?? [];
            if (!is_array($slot)) {
                $slot = [];
            }
            $base[$key] = [
                'label' => mb_substr(trim((string) ($slot['label'] ?? '')), 0, 80),
                'freq' => mb_substr(trim((string) ($slot['freq'] ?? '')), 0, 40),
                'net' => mb_substr(trim((string) ($slot['net'] ?? '')), 0, 40),
                'notes' => mb_substr(trim((string) ($slot['notes'] ?? '')), 0, 200),
            ];
        }
        $teams = [];
        if (isset($raw['teams']) && is_array($raw['teams'])) {
            foreach ($raw['teams'] as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $name = mb_substr(trim((string) ($t['name'] ?? '')), 0, 60);
                if ($name === '') {
                    continue;
                }
                $teams[] = [
                    'name' => $name,
                    'primary' => mb_substr(trim((string) ($t['primary'] ?? '')), 0, 40),
                    'alternate' => mb_substr(trim((string) ($t['alternate'] ?? '')), 0, 40),
                    'contingency' => mb_substr(trim((string) ($t['contingency'] ?? '')), 0, 40),
                    'emergency' => mb_substr(trim((string) ($t['emergency'] ?? '')), 0, 40),
                ];
                if (count($teams) >= 24) {
                    break;
                }
            }
        }
        $base['teams'] = $teams;
        $base['updated_at'] = isset($raw['updated_at']) ? (string) $raw['updated_at'] : null;
        $base['updated_by'] = isset($raw['updated_by']) ? (string) $raw['updated_by'] : null;

        return $base;
    }
}
