<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;

/**
 * Manuel rédigé dans Athena (page de garde + avant-propos / signatures + corps).
 */
final class DocumentManuscript
{
    public const ORIGIN_UPLOAD = 'upload';
    public const ORIGIN_AUTHORED = 'authored';

    public const MAX_CODES = 8;
    public const MAX_SIGNATURES = 8;
    public const MAX_TEXT = 8000;
    public const MAX_BODY = 80000;

    /**
     * @return self::ORIGIN_*
     */
    public static function originFromRequest(mixed $raw): string
    {
        return trim((string) $raw) === self::ORIGIN_AUTHORED
            ? self::ORIGIN_AUTHORED
            : self::ORIGIN_UPLOAD;
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function isAuthored(array $document): bool
    {
        if (($document['origin'] ?? '') === self::ORIGIN_AUTHORED) {
            return true;
        }
        $json = $document['authored_json'] ?? null;

        return is_string($json) && trim($json) !== '';
    }

    /**
     * @param array<string, mixed> $document
     * @return array{
     *   publication_codes: list<string>,
     *   issue_date: string,
     *   issuing_authority: string,
     *   distribution_restriction: string,
     *   destruction_notice: string,
     *   foreword: string,
     *   signatures: list<array{name: string, rank: string, command: string}>,
     *   body: string
     * }
     */
    public static function forView(array $document, string $issuingFallback = ''): array
    {
        $defaults = self::defaults((string) ($document['title'] ?? ''), $issuingFallback);
        $decoded = self::decode(isset($document['authored_json']) ? (string) $document['authored_json'] : null);

        return self::normalize(array_replace($defaults, $decoded));
    }

    /**
     * @return array{
     *   publication_codes: list<string>,
     *   issue_date: string,
     *   issuing_authority: string,
     *   distribution_restriction: string,
     *   destruction_notice: string,
     *   foreword: string,
     *   signatures: list<array{name: string, rank: string, command: string}>,
     *   body: string
     * }
     */
    public static function fromRequest(Request $request, string $title, string $issuingFallback = ''): array
    {
        $codesRaw = (string) $request->input('manuscript_codes');
        $codes = preg_split('/\r\n|\r|\n/', $codesRaw) ?: [];
        $names = $request->input('manuscript_sig_name');
        $ranks = $request->input('manuscript_sig_rank');
        $commands = $request->input('manuscript_sig_command');
        $names = is_array($names) ? $names : [];
        $ranks = is_array($ranks) ? $ranks : [];
        $commands = is_array($commands) ? $commands : [];
        $signatures = [];
        $count = max(count($names), count($ranks), count($commands));
        for ($i = 0; $i < $count; $i++) {
            $signatures[] = [
                'name' => (string) ($names[$i] ?? ''),
                'rank' => (string) ($ranks[$i] ?? ''),
                'command' => (string) ($commands[$i] ?? ''),
            ];
        }

        $bodyRaw = (string) $request->input('manuscript_body');

        return self::normalize([
            'publication_codes' => $codes,
            'issue_date' => (string) $request->input('manuscript_issue_date'),
            'issuing_authority' => (string) $request->input('manuscript_issuing_authority') ?: $issuingFallback,
            'distribution_restriction' => (string) $request->input('manuscript_distribution'),
            'destruction_notice' => (string) $request->input('manuscript_destruction'),
            'foreword' => (string) $request->input('manuscript_foreword'),
            'signatures' => $signatures,
            'body' => $bodyRaw,
            'title' => $title,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        $json = json_encode(self::normalize($payload), JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return '{}';
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{
     *   publication_codes: list<string>,
     *   issue_date: string,
     *   issuing_authority: string,
     *   distribution_restriction: string,
     *   destruction_notice: string,
     *   foreword: string,
     *   signatures: list<array{name: string, rank: string, command: string}>,
     *   body: string
     * }
     */
    public static function defaults(string $title = '', string $issuingFallback = ''): array
    {
        $months = [
            1 => 'JANUARY', 2 => 'FEBRUARY', 3 => 'MARCH', 4 => 'APRIL',
            5 => 'MAY', 6 => 'JUNE', 7 => 'JULY', 8 => 'AUGUST',
            9 => 'SEPTEMBER', 10 => 'OCTOBER', 11 => 'NOVEMBER', 12 => 'DECEMBER',
        ];
        $month = $months[(int) date('n')] ?? 'JANUARY';

        return [
            'publication_codes' => [],
            'issue_date' => $month . ' ' . date('Y'),
            'issuing_authority' => $issuingFallback !== '' ? $issuingFallback : 'Headquarters',
            'distribution_restriction' => 'Distribution authorized to community members and designated personnel only. Other requests for this document must be referred to the issuing command.',
            'destruction_notice' => 'Destroy by any method that will prevent disclosure of contents or reconstruction of the document.',
            'foreword' => 'This publication has been prepared under the direction of the commands listed below.',
            'signatures' => [
                ['name' => '', 'rank' => '', 'command' => ''],
                ['name' => '', 'rank' => '', 'command' => ''],
                ['name' => '', 'rank' => '', 'command' => ''],
                ['name' => '', 'rank' => '', 'command' => ''],
            ],
            'body' => '',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   publication_codes: list<string>,
     *   issue_date: string,
     *   issuing_authority: string,
     *   distribution_restriction: string,
     *   foreword: string,
     *   destruction_notice: string,
     *   signatures: list<array{name: string, rank: string, command: string}>,
     *   body: string
     * }
     */
    public static function normalize(array $raw): array
    {
        $codes = [];
        $codeSrc = $raw['publication_codes'] ?? [];
        if (is_string($codeSrc)) {
            $codeSrc = preg_split('/\r\n|\r|\n/', $codeSrc) ?: [];
        }
        if (is_array($codeSrc)) {
            foreach ($codeSrc as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $codes[] = self::clip($line, 120);
                if (count($codes) >= self::MAX_CODES) {
                    break;
                }
            }
        }

        $signatures = [];
        $sigSrc = $raw['signatures'] ?? [];
        if (is_array($sigSrc)) {
            foreach ($sigSrc as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $signatures[] = [
                    'name' => self::clip((string) ($row['name'] ?? ''), 120),
                    'rank' => self::clip((string) ($row['rank'] ?? ''), 160),
                    'command' => self::clip((string) ($row['command'] ?? ''), 200),
                ];
                if (count($signatures) >= self::MAX_SIGNATURES) {
                    break;
                }
            }
        }
        while (count($signatures) < 4) {
            $signatures[] = ['name' => '', 'rank' => '', 'command' => ''];
        }

        $body = (string) ($raw['body'] ?? '');
        if (!str_contains($body, '<')) {
            $body = self::plainTextToHtml($body);
        }

        return [
            'publication_codes' => $codes,
            'issue_date' => self::clip((string) ($raw['issue_date'] ?? ''), 80),
            'issuing_authority' => self::clip((string) ($raw['issuing_authority'] ?? ''), 200),
            'distribution_restriction' => self::clip((string) ($raw['distribution_restriction'] ?? ''), self::MAX_TEXT),
            'destruction_notice' => self::clip((string) ($raw['destruction_notice'] ?? ''), self::MAX_TEXT),
            'foreword' => self::clip((string) ($raw['foreword'] ?? ''), self::MAX_TEXT),
            'signatures' => $signatures,
            'body' => self::sanitizeHtml($body),
        ];
    }

    public static function codesAsText(array $manuscript): string
    {
        $codes = $manuscript['publication_codes'] ?? [];
        if (!is_array($codes)) {
            return '';
        }

        return implode("\n", array_map('strval', $codes));
    }

    public static function bodyAsPlainText(array $manuscript): string
    {
        $html = (string) ($manuscript['body'] ?? '');
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    public static function filledSignatures(array $manuscript): array
    {
        $out = [];
        foreach ($manuscript['signatures'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('/\bon\w+\s*=\s*(["\']).*?\1/iu', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote>');
        if (strlen($html) > self::MAX_BODY) {
            $html = substr($html, 0, self::MAX_BODY);
        }

        return $html;
    }

    public static function plainTextToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split("/\n{2,}/", trim($text)) ?: [];
        if ($parts === [] || ($parts === [''] && trim($text) === '')) {
            return '';
        }
        $html = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $html .= '<p>' . nl2br(htmlspecialchars($part, ENT_QUOTES, 'UTF-8'), false) . '</p>';
        }

        return $html;
    }

    private static function clip(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }
}
