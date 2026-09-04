<?php
declare(strict_types=1);
namespace App\Services\Tactical;

final class AthenaEventValidator
{
    public const MAX_BATCH = 250;
    public const MAX_EVENT_BYTES = 262144;
    private const SCHEMA = 'athena.event.v1';

    /** @return list<string> */
    public static function errors(mixed $event): array
    {
        if (!is_array($event)) return ['event_must_be_object'];
        $errors = [];
        if (($event['schema'] ?? '') !== self::SCHEMA) $errors[] = 'unsupported_schema';
        $id = $event['event_id'] ?? null;
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $id)) $errors[] = 'invalid_event_id';
        $type = $event['type'] ?? null;
        if (!is_string($type) || !preg_match('/^[a-z][a-z0-9_.-]{2,127}$/', $type)) $errors[] = 'invalid_type';
        $stamp = $event['timestamp'] ?? null;
        if (!is_string($stamp) || strtotime($stamp) === false || !str_contains($stamp, 'T')) $errors[] = 'invalid_timestamp';
        $source = $event['source'] ?? null;
        if (!is_array($source) || trim((string)($source['terminal_id'] ?? '')) === '' || trim((string)($source['source_type'] ?? '')) === '') $errors[] = 'invalid_source';
        if (isset($event['context']) && !is_array($event['context'])) $errors[] = 'invalid_context';
        if (!array_key_exists('payload', $event) || !is_array($event['payload'])) $errors[] = 'invalid_payload';
        $json = json_encode($event);
        if ($json === false || strlen($json) > self::MAX_EVENT_BYTES) $errors[] = 'event_too_large';
        return $errors;
    }

    public static function isState(string $type): bool
    {
        foreach (['position.', 'bft.', 'weather.', 'terminal.heartbeat', 'entity.state', 'radio.state', 'drone.state'] as $prefix) {
            if ($type === $prefix || str_starts_with($type, $prefix)) return true;
        }
        return false;
    }
}
