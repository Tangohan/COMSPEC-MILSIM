<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Journal d’activité ATAK léger (fichier JSON par théâtre), pour l’affichage
 * « Activité de liaison » sur la Tacmap. Pas d’historique long terme.
 */
final class AtakActivityLogService
{
    private const MAX_EVENTS = 120;
    private const POSITION_THROTTLE_SEC = 15;
    private const INIT_THROTTLE_SEC = 90;
    private const SESSION_TTL_SEC = 7200;

    /** Types internes → libellés métier (FR). */
    public const TYPE_CLIENT_INIT = 'client_init';
    public const TYPE_CALLSIGN_CHANGE = 'callsign_change';
    public const TYPE_POSITION = 'position';
    public const TYPE_CHAT = 'chat';
    public const TYPE_PING = 'ping';
    public const TYPE_MARKER = 'marker';
    public const TYPE_DESIGNATOR = 'designator';
    public const TYPE_SIGINT = 'sigint';
    public const TYPE_FLIGHT = 'flight';
    public const TYPE_NINE_LINE = 'nine_line';
    public const TYPE_LASER = 'laser';
    public const TYPE_INTEL = 'intel';

    /**
     * Enregistre une entrée générique (libellé déjà lisible).
     *
     * @param array<string, mixed> $meta
     */
    public function record(
        int $tenantId,
        int $mapId,
        string $type,
        string $label,
        ?string $actor = null,
        array $meta = []
    ): void {
        if ($tenantId < 1 || $mapId < 1 || $label === '') {
            return;
        }
        $this->mutate($tenantId, $mapId, function (array &$data) use ($type, $label, $actor, $meta): void {
            $this->appendEvent($data, $type, $label, $actor, $meta);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    private function appendEvent(array &$data, string $type, string $label, ?string $actor = null, array $meta = []): void
    {
        $id = (int) ($data['next_id'] ?? 1);
        $data['next_id'] = $id + 1;
        $event = [
            'id' => $id,
            'type' => $type,
            'label' => $label,
            'actor' => $actor !== null && $actor !== '' ? $actor : null,
            'at' => date('c'),
        ];
        if ($meta !== []) {
            $event['meta'] = $meta;
        }
        $events = $data['events'] ?? [];
        if (!is_array($events)) {
            $events = [];
        }
        $events[] = $event;
        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }
        $data['events'] = $events;
    }

    /**
     * Première prise de contact / vérification de liaison (throttlée par client).
     */
    public function recordClientInit(int $tenantId, int $mapId, string $clientKey, ?string $callSign = null): void
    {
        $clientKey = $this->normalizeKey($clientKey);
        if ($clientKey === '') {
            return;
        }
        $now = time();
        $this->mutate($tenantId, $mapId, function (array &$data) use ($clientKey, $callSign, $now): void {
            $sessions = $this->pruneSessions($data['sessions'] ?? [], $now);
            $prev = $sessions[$clientKey] ?? null;
            $lastInit = is_array($prev) ? (int) ($prev['last_init_at'] ?? 0) : 0;
            if ($lastInit > 0 && ($now - $lastInit) < self::INIT_THROTTLE_SEC) {
                $data['sessions'] = $sessions;
                return;
            }
            $call = $callSign !== null ? trim($callSign) : '';
            $sessions[$clientKey] = [
                'call_sign' => $call !== '' ? $call : (is_array($prev) ? (string) ($prev['call_sign'] ?? '') : ''),
                'last_init_at' => $now,
                'last_seen_at' => $now,
                'last_position_at' => is_array($prev) ? (int) ($prev['last_position_at'] ?? 0) : 0,
            ];
            $data['sessions'] = $sessions;
            $label = $call !== ''
                ? 'Connexion établie — ' . $call
                : 'Client ATAK initialisé';
            $this->appendEvent($data, self::TYPE_CLIENT_INIT, $label, $call !== '' ? $call : null);
        });
    }

    /**
     * Position reçue : détecte init / changement d’indicatif / envoi (throttlé).
     */
    public function recordFromPosition(int $tenantId, int $mapId, string $clientKey, string $callSign, bool $isNewUnit): void
    {
        $clientKey = $this->normalizeKey($clientKey);
        $callSign = trim($callSign);
        if ($callSign === '') {
            $callSign = 'Inconnu';
        }
        $now = time();
        $this->mutate($tenantId, $mapId, function (array &$data) use ($clientKey, $callSign, $isNewUnit, $now): void {
            $sessions = $this->pruneSessions($data['sessions'] ?? [], $now);
            $prev = ($clientKey !== '' && isset($sessions[$clientKey]) && is_array($sessions[$clientKey]))
                ? $sessions[$clientKey]
                : null;
            $prevCall = is_array($prev) ? trim((string) ($prev['call_sign'] ?? '')) : '';
            $lastInit = is_array($prev) ? (int) ($prev['last_init_at'] ?? 0) : 0;
            $lastPos = is_array($prev) ? (int) ($prev['last_position_at'] ?? 0) : 0;

            if ($prevCall !== '' && strcasecmp($prevCall, $callSign) !== 0) {
                $this->appendEvent($data, self::TYPE_CALLSIGN_CHANGE, 'Indicatif mis à jour — ' . $prevCall . ' → ' . $callSign, $callSign, [
                    'from' => $prevCall,
                    'to' => $callSign,
                ]);
                $lastInit = $now;
            } elseif ($prev === null || $lastInit === 0 || ($isNewUnit && ($now - $lastInit) >= self::INIT_THROTTLE_SEC)) {
                $this->appendEvent($data, self::TYPE_CLIENT_INIT, 'Connexion établie — ' . $callSign, $callSign);
                $lastInit = $now;
            }

            if ($lastPos === 0 || ($now - $lastPos) >= self::POSITION_THROTTLE_SEC) {
                $this->appendEvent($data, self::TYPE_POSITION, 'Position envoyée — ' . $callSign, $callSign);
                $lastPos = $now;
            }

            if ($clientKey !== '') {
                $sessions[$clientKey] = [
                    'call_sign' => $callSign,
                    'last_init_at' => $lastInit > 0 ? $lastInit : $now,
                    'last_seen_at' => $now,
                    'last_position_at' => $lastPos,
                ];
            }
            $data['sessions'] = $sessions;
        });
    }

    /**
     * @return list<array{id: int, type: string, label: string, actor: ?string, at: string, meta?: array}>
     */
    public function listRecent(int $tenantId, int $mapId, int $limit = 50, ?int $afterId = null): array
    {
        $limit = max(1, min(100, $limit));
        $path = $this->path($tenantId, $mapId);
        $data = $this->readFile($path);
        $events = $data['events'] ?? [];
        if (!is_array($events)) {
            return [];
        }
        if ($afterId !== null && $afterId > 0) {
            $events = array_values(array_filter(
                $events,
                static fn ($e) => is_array($e) && (int) ($e['id'] ?? 0) > $afterId
            ));
        }
        $events = array_slice($events, -$limit);

        $out = [];
        foreach ($events as $e) {
            if (!is_array($e)) {
                continue;
            }
            $item = [
                'id' => (int) ($e['id'] ?? 0),
                'type' => (string) ($e['type'] ?? ''),
                'label' => (string) ($e['label'] ?? ''),
                'actor' => isset($e['actor']) && is_string($e['actor']) && $e['actor'] !== '' ? $e['actor'] : null,
                'at' => (string) ($e['at'] ?? ''),
            ];
            if (isset($e['meta']) && is_array($e['meta']) && $e['meta'] !== []) {
                // Ne pas exposer de clés techniques brutes côté UI : uniquement from/to d’indicatif.
                $meta = [];
                if (isset($e['meta']['from'], $e['meta']['to'])) {
                    $meta['from'] = (string) $e['meta']['from'];
                    $meta['to'] = (string) $e['meta']['to'];
                }
                if ($meta !== []) {
                    $item['meta'] = $meta;
                }
            }
            $out[] = $item;
        }

        return array_reverse($out);
    }

    public function clientKeyFromRequest(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ip = is_string($forwarded) ? trim(explode(',', $forwarded)[0]) : trim((string) $forwarded[0]);
        }
        $ip = trim((string) $ip);
        if ($ip === '') {
            return '';
        }

        return hash('sha256', 'atak-client:' . $ip);
    }

    /**
     * @param callable(array<string, mixed>): void $fn
     */
    private function mutate(int $tenantId, int $mapId, callable $fn): void
    {
        $path = $this->path($tenantId, $mapId);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                $data = ['next_id' => 1, 'events' => [], 'sessions' => []];
            }
            $fn($data);
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return;
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);
        } catch (\Throwable) {
            // Journal best-effort : ne jamais faire échouer l’API ATAK.
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** @return array<string, mixed> */
    private function readFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function path(int $tenantId, int $mapId): string
    {
        return base_path('storage/cache/atak-activity/t' . $tenantId . '_m' . $mapId . '.json');
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);

        return strlen($key) > 8 ? $key : '';
    }

    /**
     * @param mixed $sessions
     * @return array<string, array<string, mixed>>
     */
    private function pruneSessions(mixed $sessions, int $now): array
    {
        if (!is_array($sessions)) {
            return [];
        }
        $out = [];
        foreach ($sessions as $key => $row) {
            if (!is_string($key) || !is_array($row)) {
                continue;
            }
            $seen = (int) ($row['last_seen_at'] ?? 0);
            if ($seen > 0 && ($now - $seen) > self::SESSION_TTL_SEC) {
                continue;
            }
            $out[$key] = $row;
        }

        return $out;
    }
}
