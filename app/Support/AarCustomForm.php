<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modèles de debriefing : champs personnalisés (question, liste, cases, texte libre).
 */
final class AarCustomForm
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SELECT = 'select';
    public const TYPE_CHECKBOX = 'checkbox';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_SELECT,
        self::TYPE_CHECKBOX,
    ];

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_TEXT => 'Question courte',
            self::TYPE_TEXTAREA => 'Zone de texte',
            self::TYPE_SELECT => 'Liste déroulante',
            self::TYPE_CHECKBOX => 'Cases à cocher',
            default => 'Question',
        };
    }

    /**
     * @param mixed $raw
     * @return list<array{id:string,type:string,label:string,help:string,required:bool,options:list<string>}>
     */
    public static function normalizeFields(mixed $raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($raw as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = self::clip((string) ($row['label'] ?? $row['question'] ?? ''), 240);
            if ($label === '') {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? self::TYPE_TEXT)));
            if (!in_array($type, self::TYPES, true)) {
                $type = self::TYPE_TEXT;
            }
            $id = self::sanitizeId((string) ($row['id'] ?? ''), $index, $seen);
            $seen[$id] = true;
            $options = [];
            if (in_array($type, [self::TYPE_SELECT, self::TYPE_CHECKBOX], true)) {
                $options = self::normalizeOptions($row['options'] ?? []);
            }
            $out[] = [
                'id' => $id,
                'type' => $type,
                'label' => $label,
                'help' => self::clip((string) ($row['help'] ?? $row['hint'] ?? ''), 400),
                'required' => !empty($row['required']),
                'options' => $options,
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return array{fields: list<array<string, mixed>>, answers: array<string, mixed>}
     */
    public static function collectAnswers(array $fields, mixed $rawAnswers): array
    {
        $bag = is_array($rawAnswers) ? $rawAnswers : [];
        $answers = [];
        foreach ($fields as $field) {
            $id = (string) ($field['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $type = (string) ($field['type'] ?? self::TYPE_TEXT);
            $raw = $bag[$id] ?? null;
            if ($type === self::TYPE_CHECKBOX) {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                if ($options === []) {
                    $answers[$id] = ($raw === null || $raw === '') ? null : self::isChecked($raw);
                    continue;
                }
                $selected = [];
                if (is_array($raw)) {
                    foreach ($raw as $item) {
                        $item = trim((string) $item);
                        if ($item !== '' && in_array($item, $options, true)) {
                            $selected[] = $item;
                        }
                    }
                } elseif (is_string($raw) && in_array($raw, $options, true)) {
                    $selected[] = $raw;
                }
                $answers[$id] = array_values(array_unique($selected));
                continue;
            }
            if ($type === self::TYPE_SELECT) {
                $value = is_scalar($raw) ? trim((string) $raw) : '';
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                $answers[$id] = ($value !== '' && in_array($value, $options, true)) ? $value : '';
                continue;
            }
            $text = is_scalar($raw) ? trim((string) $raw) : '';
            $max = $type === self::TYPE_TEXTAREA ? 8000 : 500;
            $answers[$id] = self::clip($text, $max);
        }

        return [
            'fields' => $fields,
            'answers' => $answers,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $answers
     * @return list<string>
     */
    public static function missingRequired(array $fields, array $answers): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (empty($field['required'])) {
                continue;
            }
            $id = (string) ($field['id'] ?? '');
            $type = (string) ($field['type'] ?? self::TYPE_TEXT);
            $raw = $answers[$id] ?? null;
            $ok = true;
            if ($type === self::TYPE_CHECKBOX) {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                if ($options === []) {
                    $ok = $raw !== null && $raw !== '';
                } else {
                    $ok = is_array($raw) && $raw !== [];
                }
            } else {
                $ok = is_scalar($raw) && trim((string) $raw) !== '';
            }
            if (!$ok) {
                $missing[] = (string) ($field['label'] ?? 'Question');
            }
        }

        return $missing;
    }

    /**
     * @param mixed $payload
     * @return array{fields: list<array<string, mixed>>, answers: array<string, mixed>}
     */
    public static function unwrap(mixed $payload): array
    {
        $data = $payload;
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($data)) {
            return ['fields' => [], 'answers' => []];
        }
        $fields = self::normalizeFields($data['fields'] ?? []);
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

        return ['fields' => $fields, 'answers' => $answers];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $answers
     * @return list<array{id:string,type:string,label:string,help:string,required:bool,display:string,empty:bool}>
     */
    public static function presentAnswers(array $fields, array $answers): array
    {
        $rows = [];
        foreach ($fields as $field) {
            $id = (string) ($field['id'] ?? '');
            $type = (string) ($field['type'] ?? self::TYPE_TEXT);
            $raw = $answers[$id] ?? null;
            $display = '';
            if ($type === self::TYPE_CHECKBOX) {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                if ($options === []) {
                    if ($raw === null || $raw === '') {
                        $display = '';
                    } else {
                        $display = self::isChecked($raw) ? 'Oui' : 'Non';
                    }
                } elseif (is_array($raw) && $raw !== []) {
                    $display = implode(', ', array_map(static fn (mixed $v): string => (string) $v, $raw));
                }
            } elseif (is_scalar($raw)) {
                $display = trim((string) $raw);
            }
            $rows[] = [
                'id' => $id,
                'type' => $type,
                'label' => (string) ($field['label'] ?? ''),
                'help' => (string) ($field['help'] ?? ''),
                'required' => !empty($field['required']),
                'display' => $display,
                'empty' => $display === '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function normalizeOptions(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n|,/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = $item['label'] ?? $item['value'] ?? '';
            }
            $item = self::clip((string) $item, 160);
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
            if (count($out) >= 30) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string, true> $seen
     */
    private static function sanitizeId(string $id, int|string $index, array $seen): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]+/', '_', $id) ?? '';
        $id = trim($id, '_-');
        if ($id === '' || isset($seen[$id])) {
            $id = 'q' . ((int) $index + 1);
            $n = 2;
            while (isset($seen[$id])) {
                $id = 'q' . ((int) $index + 1) . '_' . $n;
                $n++;
            }
        }

        return substr($id, 0, 40);
    }

    private static function isChecked(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_array($raw)) {
            return $raw !== [];
        }
        $v = strtolower(trim((string) $raw));

        return in_array($v, ['1', 'true', 'oui', 'on', 'yes'], true);
    }

    private static function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }
}
