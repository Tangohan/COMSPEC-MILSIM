<?php

declare(strict_types=1);

namespace App\Services\OperatorGame;

/** Compares observations without ever mutating authoritative HR data. */
final class OperatorGameReconciliationService
{
    /** @return list<array<string, string>> */
    public function reconcile(array $reference, array $observed, array $versions = []): array
    {
        $rules = [
            'steam_id' => ['CRITICAL', 'IDENTITY', fn (?string $v): string => preg_replace('/\D/', '', $v ?? '') ?? ''],
            'blood_type' => ['CRITICAL', 'MEDICAL', fn (?string $v): string => strtoupper(str_replace(' ', '', $v ?? ''))],
            'sex' => ['ERROR', 'IDENTITY', fn (?string $v): string => strtoupper(substr(trim($v ?? ''), 0, 1))],
            'display_name' => ['WARNING', 'IDENTITY', [$this, 'normalizeText']],
            'callsign' => ['WARNING', 'IDENTITY', [$this, 'normalizeCallsign']],
            'face_class' => ['INFO', 'IDENTITY', [$this, 'normalizeText']],
        ];
        $out = [];
        foreach ($rules as $field => [$severity, $category, $normalizer]) {
            $expected = $this->scalar($reference[$field] ?? null);
            $actual = $this->scalar($observed[$field] ?? null);
            if ($expected === '' || $actual === '') {
                continue; // An absent game value is unknown, never invented.
            }
            $ne = $normalizer($expected);
            $no = $normalizer($actual);
            if ($ne !== $no) {
                $out[] = compact('field', 'severity', 'category', 'expected', 'actual', 'ne', 'no');
            }
        }
        foreach ($versions as $component => $policy) {
            $actual = $this->scalar($observed['versions'][$component] ?? null);
            $expected = $this->scalar($policy['recommended'] ?? null);
            $minimum = $this->scalar($policy['minimum'] ?? null);
            if ($actual === '' || $expected === '' || version_compare($actual, $expected, '>=')) {
                continue;
            }
            $severity = $minimum !== '' && version_compare($actual, $minimum, '<') ? 'ERROR' : 'WARNING';
            $field = 'version.' . $component;
            $category = 'SOFTWARE';
            $ne = $expected;
            $no = $actual;
            $out[] = compact('field', 'severity', 'category', 'expected', 'actual', 'ne', 'no');
        }
        return $out;
    }

    public function normalizeCallsign(?string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $value ?? '') ?? '');
        return preg_replace_callback('/\d+/', static fn (array $m): string => (string) (int) $m[0], $value) ?? $value;
    }

    public function normalizeText(?string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value ?? '')) ?: trim($value ?? '');
        return strtoupper(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
