<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\CommunityEventRepository;
use App\Services\Calendar\CommunityCalendarFeedTokenService;
use App\Services\Platform\FeatureGateService;

final class CommunityCalendarFeedController
{
    public function __construct(
        private CommunityEventRepository $events,
        private FeatureGateService $featureGate,
    ) {}

    public function ics(Request $request, array $params = []): Response
    {
        $token = trim((string) ($params['token'] ?? ''));
        if ($token === '') {
            return (new Response())->setStatusCode(404)->setBody('Introuvable.');
        }
        $svc = CommunityCalendarFeedTokenService::fromEnv();
        $parsed = $svc->parse($token);
        if ($parsed === null) {
            return (new Response())->setStatusCode(403)->setBody('Lien non valide ou expiré.');
        }
        $tenantId = $parsed['tenant_id'];
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return (new Response())->setStatusCode(403)->setBody('Fonction indisponible.');
        }
        $rows = $this->events->upcomingForTenant($tenantId, 80);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Athena Comspec//Événements communauté//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Opérations — espace communautaire',
        ];
        foreach ($rows as $ev) {
            $id = (int) ($ev['id'] ?? 0);
            $title = $this->escapeIcsText((string) ($ev['title'] ?? 'Événement'));
            $desc = $this->escapeIcsText(trim((string) ($ev['description'] ?? '')));
            $loc = $this->escapeIcsText(trim((string) ($ev['location'] ?? '')));
            $startTs = strtotime((string) ($ev['starts_at'] ?? ''));
            if ($startTs === false) {
                continue;
            }
            $endRaw = trim((string) ($ev['ends_at'] ?? ''));
            $endTs = $endRaw !== '' ? strtotime($endRaw) : false;
            if ($endTs === false || $endTs <= $startTs) {
                $endTs = $startTs + 3600;
            }
            $start = gmdate('Ymd\THis\Z', $startTs);
            $end = gmdate('Ymd\THis\Z', $endTs);
            $uid = 'comspec-event-' . $tenantId . '-' . $id . '@athena';
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $start;
            $lines[] = 'DTEND:' . $end;
            $lines[] = 'SUMMARY:' . $title;
            if ($desc !== '') {
                $lines[] = 'DESCRIPTION:' . $desc;
            }
            if ($loc !== '') {
                $lines[] = 'LOCATION:' . $loc;
            }
            $lines[] = 'URL;VALUE=URI:' . $this->escapeIcsText(url('evenements'));
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        $body = implode("\r\n", $lines) . "\r\n";
        $resp = new Response();
        $resp->header('Content-Type', 'text/calendar; charset=utf-8');
        $resp->header('Content-Disposition', 'inline; filename="evenements.ics"');
        $resp->setBody($body);

        return $resp;
    }

    private function escapeIcsText(string $s): string
    {
        $s = str_replace(["\r\n", "\r", "\n"], '\\n', $s);
        $s = str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $s);

        return $s;
    }

}
