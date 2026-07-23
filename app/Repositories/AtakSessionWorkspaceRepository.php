<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Espace de travail ATAK temporaire (bloc-notes + tableurs), scopé tenant + carte.
 * Stockage fichier JSON — non permanent (contrairement aux tableurs admin).
 *
 * Feuilles :
 * - soi : lignes type Organization Net List / SOI US (réseau, indicatif, suffixe, fréquences, rôle)
 * - eta : suivi ETA forces alliées
 * - allied_ids : ID ATAK / militaires d’unités alliées hors communauté (manuel)
 */
final class AtakSessionWorkspaceRepository
{
    private const MAX_NOTEPAD = 20000;
    private const MAX_ROWS = 80;
    private const MAX_CELL = 120;

    private function path(int $tenantId, int $mapId): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/atak-session';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/t' . $tenantId . '_m' . $mapId . '.json';
    }

    /**
     * @return array{
     *   notepad: string,
     *   soi: list<array<string, string>>,
     *   eta: list<array<string, string>>,
     *   allied_ids: list<array<string, string>>,
     *   updated_at: ?string,
     *   updated_by: ?string
     * }
     */
    public function get(int $tenantId, int $mapId): array
    {
        $empty = $this->emptyWorkspace();
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
     * Fusion partielle : seules les clés présentes dans $payload sont remplacées.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function save(int $tenantId, int $mapId, array $payload, ?string $actor = null): array
    {
        $current = $this->get($tenantId, $mapId);
        if (array_key_exists('notepad', $payload)) {
            $current['notepad'] = $this->clipNote((string) $payload['notepad']);
        }
        if (array_key_exists('soi', $payload)) {
            $current['soi'] = $this->normalizeSoiRows($payload['soi']);
        }
        if (array_key_exists('eta', $payload)) {
            $current['eta'] = $this->normalizeEtaRows($payload['eta']);
        }
        if (array_key_exists('allied_ids', $payload)) {
            $current['allied_ids'] = $this->normalizeAlliedRows($payload['allied_ids']);
        }
        $current['updated_at'] = gmdate('c');
        $current['updated_by'] = $actor !== null && $actor !== '' ? mb_substr($actor, 0, 80) : null;

        $path = $this->path($tenantId, $mapId);
        $json = json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (is_string($json)) {
            @file_put_contents($path, $json, LOCK_EX);
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyWorkspace(): array
    {
        return [
            'notepad' => '',
            'soi' => [],
            'eta' => [],
            'allied_ids' => [],
            'updated_at' => null,
            'updated_by' => null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        return [
            'notepad' => $this->clipNote((string) ($data['notepad'] ?? '')),
            'soi' => $this->normalizeSoiRows($data['soi'] ?? []),
            'eta' => $this->normalizeEtaRows($data['eta'] ?? []),
            'allied_ids' => $this->normalizeAlliedRows($data['allied_ids'] ?? []),
            'updated_at' => isset($data['updated_at']) && is_string($data['updated_at']) ? $data['updated_at'] : null,
            'updated_by' => isset($data['updated_by']) && is_string($data['updated_by'])
                ? mb_substr($data['updated_by'], 0, 80)
                : null,
        ];
    }

    private function clipNote(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (mb_strlen($text) > self::MAX_NOTEPAD) {
            return mb_substr($text, 0, self::MAX_NOTEPAD);
        }

        return $text;
    }

    private function cell(mixed $v): string
    {
        $s = trim((string) ($v ?? ''));
        if (mb_strlen($s) > self::MAX_CELL) {
            return mb_substr($s, 0, self::MAX_CELL);
        }

        return $s;
    }

    /**
     * Lignes SOI (Organization Net List simplifiée — doctrine US).
     *
     * @param mixed $rows
     * @return list<array{net:string,callsign:string,suffix:string,frequency:string,alt_frequency:string,role:string,remarks:string}>
     */
    private function normalizeSoiRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $net = $this->cell($row['net'] ?? $row['channel'] ?? '');
            $cs = $this->cell($row['callsign'] ?? $row['call_sign'] ?? '');
            $freq = $this->cell($row['frequency'] ?? $row['freq'] ?? '');
            $role = $this->cell($row['role'] ?? $row['team'] ?? '');
            $remarks = $this->cell($row['remarks'] ?? $row['notes'] ?? '');
            $suffix = $this->cell($row['suffix'] ?? '');
            $alt = $this->cell($row['alt_frequency'] ?? $row['alt_freq'] ?? '');
            if ($net === '' && $cs === '' && $freq === '' && $role === '' && $remarks === '' && $suffix === '' && $alt === '') {
                continue;
            }
            $out[] = [
                'net' => $net,
                'callsign' => $cs,
                'suffix' => $suffix,
                'frequency' => $freq,
                'alt_frequency' => $alt,
                'role' => $role,
                'remarks' => $remarks,
            ];
            if (count($out) >= self::MAX_ROWS) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array{callsign:string,eta:string,status:string,remarks:string}>
     */
    private function normalizeEtaRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $allowedStatus = ['en_route', 'on_time', 'delayed', 'arrived', 'cancelled', ''];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cs = $this->cell($row['callsign'] ?? $row['call_sign'] ?? '');
            $eta = $this->cell($row['eta'] ?? '');
            $status = strtolower($this->cell($row['status'] ?? ''));
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'en_route';
            }
            $remarks = $this->cell($row['remarks'] ?? $row['notes'] ?? '');
            if ($cs === '' && $eta === '' && $remarks === '') {
                continue;
            }
            $out[] = [
                'callsign' => $cs,
                'eta' => $eta,
                'status' => $status !== '' ? $status : 'en_route',
                'remarks' => $remarks,
            ];
            if (count($out) >= self::MAX_ROWS) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array{callsign:string,military_id:string,affiliation:string,remarks:string}>
     */
    private function normalizeAlliedRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cs = $this->cell($row['callsign'] ?? $row['unit'] ?? $row['name'] ?? '');
            $mid = $this->cell($row['military_id'] ?? $row['atak_id'] ?? $row['id'] ?? '');
            $aff = $this->cell($row['affiliation'] ?? $row['faction'] ?? '');
            $remarks = $this->cell($row['remarks'] ?? $row['notes'] ?? '');
            if ($cs === '' && $mid === '' && $aff === '' && $remarks === '') {
                continue;
            }
            $out[] = [
                'callsign' => $cs,
                'military_id' => $mid,
                'affiliation' => $aff,
                'remarks' => $remarks,
            ];
            if (count($out) >= self::MAX_ROWS) {
                break;
            }
        }

        return $out;
    }
}
