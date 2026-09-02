<?php

declare(strict_types=1);

namespace App\Services\Calendar;

/**
 * Génération RFC 5545 (.ics) indépendante de Google Calendar.
 */
final class IcalendarService
{
    /**
     * @param array{
     *   uid: string,
     *   summary: string,
     *   description?: string,
     *   location?: string,
     *   url?: string,
     *   starts_at: string|\DateTimeInterface,
     *   ends_at: string|\DateTimeInterface,
     *   dtstamp?: string|\DateTimeInterface,
     *   organizer_email?: string,
     *   organizer_name?: string,
     *   status?: string,
     *   timezone?: string
     * } $event
     */
    public function buildEventCalendar(array $event, string $calendarName = 'Athena'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Athena Comspec//Intégration//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($calendarName),
            'BEGIN:VEVENT',
            'UID:' . $this->escapeText((string) ($event['uid'] ?? '')),
            'DTSTAMP:' . $this->formatUtc($event['dtstamp'] ?? 'now'),
            'DTSTART:' . $this->formatUtc($event['starts_at'] ?? 'now'),
            'DTEND:' . $this->formatUtc($event['ends_at'] ?? 'now'),
            'SUMMARY:' . $this->escapeText((string) ($event['summary'] ?? '')),
        ];
        $desc = trim((string) ($event['description'] ?? ''));
        if ($desc !== '') {
            $lines[] = 'DESCRIPTION:' . $this->escapeText($desc);
        }
        $loc = trim((string) ($event['location'] ?? ''));
        if ($loc !== '') {
            $lines[] = 'LOCATION:' . $this->escapeText($loc);
        }
        $url = trim((string) ($event['url'] ?? ''));
        if ($url !== '') {
            $lines[] = 'URL:' . $this->escapeText($url);
        }
        $orgEmail = trim((string) ($event['organizer_email'] ?? ''));
        if ($orgEmail !== '' && filter_var($orgEmail, FILTER_VALIDATE_EMAIL)) {
            $cn = trim((string) ($event['organizer_name'] ?? ''));
            $org = 'ORGANIZER';
            if ($cn !== '') {
                $org .= ';CN=' . $this->escapeText($cn);
            }
            $org .= ':mailto:' . $orgEmail;
            $lines[] = $org;
        }
        $status = strtoupper(trim((string) ($event['status'] ?? 'CONFIRMED')));
        if ($status === 'CANCELLED') {
            $lines[] = 'STATUS:CANCELLED';
        } else {
            $lines[] = 'STATUS:CONFIRMED';
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        $folded = [];
        foreach ($lines as $line) {
            foreach ($this->foldLine($line) as $piece) {
                $folded[] = $piece;
            }
        }

        return implode("\r\n", $folded) . "\r\n";
    }

    public function formatUtc(string|\DateTimeInterface $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            $dt = \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'));
        } else {
            $raw = trim($value);
            if ($raw === '' || strtolower($raw) === 'now') {
                $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            } else {
                try {
                    $dt = new \DateTimeImmutable($raw);
                    $dt = $dt->setTimezone(new \DateTimeZone('UTC'));
                } catch (\Throwable) {
                    $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                }
            }
        }

        return $dt->format('Ymd\THis\Z');
    }

    public function escapeText(string $s): string
    {
        $s = str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $s);

        return $s;
    }

    /**
     * @return list<string>
     */
    public function foldLine(string $line): array
    {
        if (strlen($line) <= 75) {
            return [$line];
        }
        $out = [];
        $chunk = substr($line, 0, 75);
        $out[] = $chunk;
        $rest = substr($line, 75);
        while ($rest !== '') {
            $piece = substr($rest, 0, 74);
            $out[] = ' ' . $piece;
            $rest = substr($rest, 74);
        }

        return $out;
    }
}
