<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AarReportRepository;
use App\Repositories\CommunityEventRepository;
use App\Repositories\CommunityEventSlotAssignmentRepository;
use App\Repositories\CommunityEventSlotRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Attendance\CommunityEventAttendanceService;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;
use App\Support\CommunityEventDetails;

final class CommunityEventsAdminController
{
    public function __construct(
        private CommunityEventRepository $events,
        private UserRepository $users,
        private AuthService $authService,
        private FeatureGateService $featureGate,
        private CommunityEventAttendanceService $attendance,
        private CommunityEventSlotRepository $slots,
        private CommunityEventSlotAssignmentRepository $slotAssignments,
        private UnitRepository $units,
        private TrainingCourseRepository $courses,
        private AarReportRepository $aarReports,
    ) {}

    /**
     * Prérequis de qualification saisi sur un poste.
     * Retourne un tableau vide si le déploiement n'a pas encore la migration : l'écriture
     * se fait alors sans ces champs, sans erreur.
     *
     * @return array{required_training_course_id?: ?int, qualification_enforcement?: string}
     */
    private function slotQualificationInput(Request $request, int $tenantId): array
    {
        if (!$this->slots->qualificationColumnsReady()) {
            return [];
        }
        $courseId = (int) $request->input('required_training_course_id', 0);
        if ($courseId > 0) {
            // Ne jamais référencer une formation d'une autre communauté.
            $course = $this->courses->findByIdForViewer($courseId, $tenantId);
            if (!$course) {
                $courseId = 0;
            }
        }
        $mode = (string) $request->input('qualification_enforcement', 'advisory');
        if (!in_array($mode, ['advisory', 'strict'], true)) {
            $mode = 'advisory';
        }

        return [
            'required_training_course_id' => $courseId > 0 ? $courseId : null,
            'qualification_enforcement' => $mode,
        ];
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::view('layout.main', [
                'title' => 'Événements',
                'content' => 'platform.upgrade',
                'feature' => 'events',
                'planName' => 'pro',
            ]);
        }
        $user = $this->authService->user();
        $this->featureGate->maybeRecordQuotaSoftBlock(
            $tenantId,
            $user ? (int) $user['id'] : null,
            'events'
        );
        $vue = trim((string) $request->query('vue', 'calendrier'));
        if (!in_array($vue, ['calendrier', 'a_venir', 'passes', 'annules'], true)) {
            $vue = 'calendrier';
        }
        $mois = trim((string) $request->query('mois', ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = date('Y-m');
        }
        $registryFilters = [
            'vue' => $vue,
            'mois' => $mois,
            'annee' => (int) $request->query('annee', 0),
            'type' => trim((string) $request->query('type', '')),
            'statut' => trim((string) $request->query('statut', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
        if ($request->query('export') === 'csv') {
            return $this->exportRegistry($tenantId, $registryFilters);
        }
        $rowLimit = $vue === 'calendrier' ? 400 : 150;
        $rows = $this->events->registryForTenant($tenantId, $registryFilters, $rowLimit);
        $insights = $this->buildAttendanceInsights($tenantId);
        $quota = $this->featureGate->quotaStatusForFeature($tenantId, 'events');
        $aarCrIndex = $this->aarReports->operationStatusIndexForTenant($tenantId);
        $calendarMonth = $vue === 'calendrier' ? $this->buildCalendarMonth($mois, $rows) : null;

        return Response::view('layout.main', [
            'title' => 'Agenda',
            'content' => 'admin.organization.events',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageTitle' => 'Agenda',
            'boPageKicker' => 'OPÉRATIONS · AGENDA',
            'boPageSubtitle' => $vue === 'calendrier'
                ? 'Calendrier des opérations et créneaux — cliquez un jour ou un événement pour ouvrir la fiche.'
                : 'Registre des opérations passées et à venir : effectifs engagés, durée et état des comptes rendus.',
            'boPageAction' => 'Planifier une opération',
            'boPageActionUrl' => url('back-office/events') . '?vue=calendrier#nouveau',
            'boPageQuick' => [
                ['label' => 'Calendrier', 'href' => url('back-office/events') . '?vue=calendrier'],
                ['label' => 'À venir', 'href' => url('back-office/events') . '?vue=a_venir'],
                ['label' => 'Passées', 'href' => url('back-office/events') . '?vue=passes'],
                ['label' => 'Annulées', 'href' => url('back-office/events') . '?vue=annules'],
            ],
            'backOfficePageCss' => ['back-office-events.css'],
            'events' => $rows,
            'eventsVue' => $vue,
            'eventsRegistryFilters' => $registryFilters,
            'eventsCalendarMonth' => $calendarMonth,
            'eventsAarIndex' => $aarCrIndex,
            'eventsQuota' => $quota,
            'canCreateEvent' => $this->featureGate->allows($tenantId, 'events'),
            'eventsAttendanceKpis' => $insights['kpis'],
            'eventsAbsenceReasons' => $insights['absenceReasons'],
            'eventsRecommendedSlots' => $insights['recommendedSlots'],
            'eventsRegularityScores' => $insights['regularityScores'],
            'eventsNewMemberParticipationDelta' => $insights['newMemberParticipationDelta'],
        ]);
    }

    /**
     * Grille mensuelle (lundi → dimanche) pour la vue calendrier BO.
     *
     * @param list<array<string, mixed>> $events
     * @return array{
     *   mois: string,
     *   label: string,
     *   prev: string,
     *   next: string,
     *   today: string,
     *   weeks: list<list<array{ymd: string, in_month: bool, is_today: bool, day: int, events: list<array<string, mixed>>}>>
     * }
     */
    private function buildCalendarMonth(string $mois, array $events): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = date('Y-m');
        }
        $firstTs = strtotime($mois . '-01 12:00:00');
        if ($firstTs === false) {
            $firstTs = strtotime(date('Y-m-01') . ' 12:00:00') ?: time();
            $mois = date('Y-m', $firstTs);
        }
        $monthsFr = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
            7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $monthNum = (int) date('n', $firstTs);
        $label = ($monthsFr[$monthNum] ?? date('F', $firstTs)) . ' ' . date('Y', $firstTs);

        $prevTs = strtotime($mois . '-01 -1 month');
        $nextTs = strtotime($mois . '-01 +1 month');
        $prev = $prevTs !== false ? date('Y-m', $prevTs) : $mois;
        $next = $nextTs !== false ? date('Y-m', $nextTs) : $mois;
        $today = date('Y-m-d');

        $byDay = [];
        foreach ($events as $ev) {
            $startsRaw = isset($ev['starts_at']) ? (string) $ev['starts_at'] : '';
            $ts = $startsRaw !== '' ? strtotime($startsRaw) : false;
            if ($ts === false) {
                continue;
            }
            $ymd = date('Y-m-d', $ts);
            if (!isset($byDay[$ymd])) {
                $byDay[$ymd] = [];
            }
            $byDay[$ymd][] = $ev;
        }

        /* Lundi = début de grille (N = 1..7 lundi..dimanche en PHP avec format 'N'). */
        $startDow = (int) date('N', $firstTs);
        $gridStartTs = strtotime('-' . ($startDow - 1) . ' days', $firstTs);
        if ($gridStartTs === false) {
            $gridStartTs = $firstTs;
        }
        $daysInMonth = (int) date('t', $firstTs);
        $lastTs = strtotime($mois . '-' . str_pad((string) $daysInMonth, 2, '0', STR_PAD_LEFT) . ' 12:00:00');
        if ($lastTs === false) {
            $lastTs = $firstTs;
        }
        $endDow = (int) date('N', $lastTs);
        $gridEndTs = strtotime('+' . (7 - $endDow) . ' days', $lastTs);
        if ($gridEndTs === false) {
            $gridEndTs = $lastTs;
        }

        $weeks = [];
        $cursor = $gridStartTs;
        while ($cursor <= $gridEndTs) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $ymd = date('Y-m-d', $cursor);
                $inMonth = date('Y-m', $cursor) === $mois;
                $week[] = [
                    'ymd' => $ymd,
                    'in_month' => $inMonth,
                    'is_today' => $ymd === $today,
                    'day' => (int) date('j', $cursor),
                    'events' => $byDay[$ymd] ?? [],
                ];
                $nextDay = strtotime('+1 day', $cursor);
                $cursor = $nextDay !== false ? $nextDay : ($cursor + 86400);
            }
            $weeks[] = $week;
        }

        return [
            'mois' => $mois,
            'label' => $label,
            'prev' => $prev,
            'next' => $next,
            'today' => $today,
            'weeks' => $weeks,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function exportRegistry(int $tenantId, array $filters): Response
    {
        $rows = $this->events->registryForTenant($tenantId, $filters, 500);
        $aarIndex = $this->aarReports->operationStatusIndexForTenant($tenantId);
        $sep = ';';
        $lines = [];
        $lines[] = implode($sep, [
            'Référence', 'Opération', 'Date', 'Type', 'Zone', 'Commandant', 'Engagés', 'Durée',
            'Objectifs', 'Pertes', 'Météo', 'Compte rendu', 'Statut',
        ]);
        foreach ($rows as $ev) {
            $mapped = $this->mapRegistryRow($ev, $aarIndex);
            $lines[] = implode($sep, array_map(
                static fn (string $cell): string => '"' . str_replace('"', '""', $cell) . '"',
                $mapped
            ));
        }
        $body = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $vue = (string) ($filters['vue'] ?? 'a_venir');
        $filename = 'registre-operations-' . $vue . '-' . date('Y-m-d') . '.csv';

        return (new Response())
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($body);
    }

    /**
     * @param array<string, mixed> $ev
     * @param array<string, array{id:int, status:string, status_label:string}> $aarIndex
     * @return list<string>
     */
    private function mapRegistryRow(array $ev, array $aarIndex): array
    {
        $eid = (int) ($ev['id'] ?? 0);
        $startsRaw = isset($ev['starts_at']) ? (string) $ev['starts_at'] : '';
        $startsTs = $startsRaw !== '' ? strtotime($startsRaw) : false;
        $refYear = $startsTs !== false ? (int) date('Y', $startsTs) : (int) date('Y');
        $ref = 'OP-' . $refYear . '-' . str_pad((string) $eid, 3, '0', STR_PAD_LEFT);
        $title = trim((string) ($ev['title'] ?? ''));
        $et = (string) ($ev['event_type'] ?? 'evenement');
        $typeLabel = match ($et) {
            'operation' => 'Opération',
            'formation' => 'Formation',
            'autre' => 'Autre',
            default => 'Événement',
        };
        $date = $startsTs !== false ? date('d/m/Y', $startsTs) : '—';
        $zone = trim((string) ($ev['location'] ?? ''));
        $commander = trim((string) ($ev['commander_callsign'] ?? ''));
        if ($commander === '') {
            $commander = trim((string) ($ev['commander_name'] ?? ''));
        }
        $engaged = (int) ($ev['engaged_count'] ?? 0);
        $duration = $this->formatRegistryDuration(
            isset($ev['starts_at']) ? (string) $ev['starts_at'] : null,
            isset($ev['ends_at']) ? (string) $ev['ends_at'] : null
        );
        $objectives = $this->formatRegistryObjectives($ev);
        $weather = $this->formatRegistryWeather($ev);
        $cr = $this->formatRegistryCr($ev, $aarIndex);
        $status = $this->formatRegistryStatus($ev);

        return [
            $ref,
            $title !== '' ? $title : '—',
            $date,
            $typeLabel,
            $zone !== '' ? $zone : '—',
            $commander !== '' ? $commander : '—',
            $engaged > 0 ? (string) $engaged : '—',
            $duration,
            $objectives,
            '—',
            $weather,
            $cr,
            $status,
        ];
    }

    /**
     * @param array<string, mixed> $ev
     */
    private function formatRegistryDuration(?string $starts, ?string $ends): string
    {
        if ($starts === null || trim($starts) === '' || $ends === null || trim($ends) === '') {
            return '—';
        }
        $s = strtotime($starts);
        $e = strtotime($ends);
        if ($s === false || $e === false || $e <= $s) {
            return '—';
        }
        $mins = (int) round(($e - $s) / 60);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        if ($h > 0 && $m > 0) {
            return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        }
        if ($h > 0) {
            return $h . 'h';
        }

        return $m . ' min';
    }

    /**
     * @param array<string, mixed> $ev
     */
    private function formatRegistryObjectives(array $ev): string
    {
        $phases = CommunityEventDetails::decodeSchedule($ev['schedule_json'] ?? null);
        $phaseCount = 0;
        foreach ($phases as $phase) {
            if (($phase['type'] ?? '') === 'phase') {
                $phaseCount++;
            }
        }
        if ($phaseCount > 0) {
            return (string) $phaseCount;
        }
        $slots = (int) ($ev['slot_count'] ?? 0);
        if ($slots > 0) {
            return (string) $slots;
        }

        return '—';
    }

    /**
     * @param array<string, mixed> $ev
     */
    private function formatRegistryWeather(array $ev): string
    {
        $text = trim((string) ($ev['conditions_special'] ?? ''));
        if ($text === '') {
            $text = trim((string) ($ev['conditions_general'] ?? ''));
        }
        if ($text === '') {
            return '—';
        }
        $line = trim((string) (preg_split('/\R/u', $text)[0] ?? $text));
        if ($line === '') {
            return '—';
        }
        if (mb_strlen($line) > 40) {
            return mb_substr($line, 0, 37) . '…';
        }

        return $line;
    }

    /**
     * @param array<string, mixed> $ev
     * @param array<string, array{id:int, status:string, status_label:string}> $aarIndex
     */
    private function formatRegistryCr(array $ev, array $aarIndex): string
    {
        $registryStatus = (string) ($ev['registry_status'] ?? '');
        if (in_array($registryStatus, ['planifie', 'en_cours'], true)) {
            return '—';
        }
        $titleKey = AarReportRepository::normalizeOperationKey((string) ($ev['title'] ?? ''));
        $aar = $titleKey !== '' ? ($aarIndex[$titleKey] ?? null) : null;
        if ($aar === null) {
            $tagKey = AarReportRepository::normalizeOperationKey((string) ($ev['campaign_tag'] ?? ''));
            $aar = $tagKey !== '' ? ($aarIndex[$tagKey] ?? null) : null;
        }
        if ($aar === null) {
            return $registryStatus === 'annule' ? '—' : 'Manquant';
        }

        return (string) ($aar['status_label'] ?? 'En attente');
    }

    /**
     * @param array<string, mixed> $ev
     */
    private function formatRegistryStatus(array $ev): string
    {
        return match ((string) ($ev['registry_status'] ?? '')) {
            'annule' => 'Annulé',
            'en_cours' => 'En cours',
            'clos' => 'Clos',
            default => 'Planifié',
        };
    }

    public function insights(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::view('layout.main', [
                'title' => 'Insights présence',
                'content' => 'platform.upgrade',
                'feature' => 'events',
                'planName' => 'pro',
            ]);
        }

        $insights = $this->buildAttendanceInsights($tenantId);

        return Response::view('layout.main', [
            'title' => 'Insights présence',
            'content' => 'admin.organization.events_insights',
            'eventsAttendanceKpis' => $insights['kpis'],
            'eventsAbsenceReasons' => $insights['absenceReasons'],
            'eventsRecommendedSlots' => $insights['recommendedSlots'],
            'eventsRegularityScores' => $insights['regularityScores'],
            'eventsNewMemberParticipationDelta' => $insights['newMemberParticipationDelta'],
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $listVue = trim((string) $request->input('return_vue', 'calendrier'));
        if (!in_array($listVue, ['calendrier', 'a_venir', 'passes', 'annules'], true)) {
            $listVue = 'calendrier';
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectEventsIndex($listVue);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return $this->redirectEventsIndex($listVue);
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'events')) {
            $this->featureGate->recordQuotaLimitReached($tenantId, (int) $user['id'], 'events');
            Session::flash('error', "Quota mensuel de créations d'événements atteint. Passez à un plan supérieur pour en ajouter davantage.");

            return $this->redirectEventsIndex($listVue);
        }
        $title = trim((string) $request->input('title'));
        $startsRaw = trim((string) $request->input('starts_at'));
        if ($title === '' || $startsRaw === '') {
            Session::flash('error', 'Titre et date de début requis.');

            return $this->redirectEventsIndex($listVue);
        }
        $starts = $this->parseEventDatetimeInput($startsRaw);
        if ($starts === null) {
            Session::flash('error', 'La date et l’heure de début ne sont pas valides. Vérifiez le créneau choisi.');

            return $this->redirectEventsIndex($listVue);
        }
        $endsRaw = trim((string) $request->input('ends_at'));
        $ends = $endsRaw === '' ? null : $this->parseEventDatetimeInput($endsRaw);
        if ($endsRaw !== '' && $ends === null) {
            Session::flash('error', 'La date et l’heure de fin ne sont pas valides.');

            return $this->redirectEventsIndex($listVue);
        }
        $eventType = trim((string) $request->input('event_type', 'evenement'));
        if (!in_array($eventType, ['operation', 'evenement', 'formation', 'autre'], true)) {
            $eventType = 'evenement';
        }
        $eventId = $this->events->create(
            $tenantId,
            (int) $user['id'],
            $title,
            trim((string) $request->input('description')) ?: null,
            trim((string) $request->input('location')) ?: null,
            $starts,
            $ends,
            trim((string) $request->input('campaign_tag')) ?: null,
            $eventType
        );
        $details = CommunityEventDetails::fromRequestInput(static fn (string $k, mixed $d = null) => $request->input($k, $d));
        $cover = $this->storeCoverImage($request, $tenantId);
        if (($cover['error'] ?? null) !== null) {
            Session::flash('error', (string) $cover['error']);
        }
        if (($cover['path'] ?? null) !== null) {
            $details['cover_image_path'] = $cover['path'];
        }
        $this->events->updateDetails($eventId, $tenantId, array_merge($details, [
            'show_on_public_page' => (string) $request->input('show_on_public_page', '0') === '1' ? 1 : 0,
        ]));
        $this->featureGate->recordQuotaUse($tenantId, 'events', (int) $user['id']);
        Session::flash('success', 'Événement créé.');

        return $this->redirectEventsIndex($listVue);
    }

    public function updateDetails(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        $event = $id > 0 ? $this->events->findByIdForTenant($id, $tenantId) : null;
        if (!$event || !empty($event['cancelled_at'])) {
            Session::flash('error', 'Créneau introuvable ou déjà annulé.');

            return $this->redirectToEvent($params, $id > 0 ? $id : null);
        }

        $details = CommunityEventDetails::fromRequestInput(static fn (string $k, mixed $d = null) => $request->input($k, $d));
        $desc = trim((string) $request->input('description', ''));
        $details['description'] = $desc !== '' ? $desc : null;
        $loc = trim((string) $request->input('location', ''));
        $details['location'] = $loc !== '' ? $loc : null;

        if ((string) $request->input('remove_cover') === '1') {
            $this->deleteCoverFile(isset($event['cover_image_path']) ? (string) $event['cover_image_path'] : null);
            $details['cover_image_path'] = null;
        } else {
            $cover = $this->storeCoverImage($request, $tenantId);
            if (($cover['error'] ?? null) !== null) {
                Session::flash('error', (string) $cover['error']);

                return $this->redirectToEvent($params, $id);
            }
            if (($cover['path'] ?? null) !== null) {
                $this->deleteCoverFile(isset($event['cover_image_path']) ? (string) $event['cover_image_path'] : null);
                $details['cover_image_path'] = $cover['path'];
            }
        }

        $this->events->updateDetails($id, $tenantId, $details);
        $this->events->updateDetails($id, $tenantId, [
            'show_on_public_page' => (string) $request->input('show_on_public_page', '0') === '1' ? 1 : 0,
        ]);
        Session::flash('success', 'Détails du créneau enregistrés.');

        return $this->redirectToEvent($params, $id);
    }

    /**
     * @return array{path:?string,error:?string}
     */
    private function storeCoverImage(Request $request, int $tenantId): array
    {
        $file = $_FILES['cover_image'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Le téléversement de l’image a échoué. Réessayez.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['path' => null, 'error' => 'Fichier image invalide.'];
        }
        if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
            return ['path' => null, 'error' => 'Image trop volumineuse (maximum 8 Mo).'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['path' => null, 'error' => 'Formats acceptés : JPG, PNG ou WebP.'];
        }
        $dirRel = 'uploads/community-events/' . $tenantId;
        $dirAbs = base_path('public/' . $dirRel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return ['path' => null, 'error' => 'Stockage des images indisponible pour le moment.'];
        }
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $name = 'cover-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destAbs = $dirAbs . '/' . $name;
        $destRel = $dirRel . '/' . $name;
        if (!@move_uploaded_file($tmp, $destAbs) && !@copy($tmp, $destAbs)) {
            return ['path' => null, 'error' => 'Impossible d’enregistrer l’image.'];
        }

        return ['path' => $destRel, 'error' => null];
    }

    private function deleteCoverFile(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        $norm = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($norm, 'uploads/community-events/')) {
            return;
        }
        $abs = base_path('public/' . ltrim($norm, '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::view('layout.main', [
                'title' => 'Événements',
                'content' => 'platform.upgrade',
                'feature' => 'events',
                'planName' => 'pro',
            ]);
        }
        $id = (int) ($params['id'] ?? 0);
        $event = $id > 0 ? $this->events->findByIdForTenant($id, $tenantId) : null;
        if (!$event) {
            Session::flash('error', 'Événement introuvable.');

            return Response::redirect(url('back-office/events'));
        }
        $rsvps = $this->events->listRsvpsWithUsersForEvent($id);
        $lookupQ = trim((string) $request->query('q', ''));
        $memberLookup = [];
        if (strlen($lookupQ) >= 2) {
            $memberLookup = $this->users->searchForPortal($tenantId, $lookupQ, 18);
        }
        $rsvpUserIds = [];
        foreach ($rsvps as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $rsvpUserIds[$uid] = true;
            }
        }
        $slots = $this->slots->listForEventWithCounts($id);
        $slotAssignmentsBySlot = $this->slotAssignments->listForEventGroupedBySlot($id);

        // Prérequis de qualification : seules les formations certifiantes produisent une
        // qualification exploitable (cf. TrainingCertificateService), donc seules elles
        // peuvent être exigées sur un poste.
        $qualificationCourses = [];
        $slotQualificationsEnabled = $this->slots->qualificationColumnsReady();
        if ($slotQualificationsEnabled) {
            foreach ($this->courses->listForTenant($tenantId, 'published') as $course) {
                if ((int) ($course['is_certifying'] ?? 0) === 1) {
                    $qualificationCourses[] = [
                        'id' => (int) $course['id'],
                        'title' => (string) ($course['title'] ?? ''),
                    ];
                }
            }
        }

        $eventTitle = trim((string) ($event['title'] ?? ''));
        $typeLabel = match ((string) ($event['event_type'] ?? 'evenement')) {
            'operation' => 'Opération',
            'formation' => 'Formation',
            'autre' => 'Autre',
            default => 'Événement',
        };
        $startsRaw = isset($event['starts_at']) ? (string) $event['starts_at'] : '';
        $startsTs = $startsRaw !== '' ? strtotime($startsRaw) : false;
        $subtitleParts = [];
        if ($startsTs !== false) {
            $subtitleParts[] = date('j/n/Y · H:i', $startsTs);
            if (!empty($event['ends_at'])) {
                $endsTs = strtotime((string) $event['ends_at']);
                if ($endsTs !== false) {
                    $subtitleParts[0] .= ' → ' . date('H:i', $endsTs);
                }
            }
        }
        $location = trim((string) ($event['location'] ?? ''));
        if ($location !== '') {
            $subtitleParts[] = $location;
        }
        $subtitleParts[] = $typeLabel;
        if (!empty($event['cancelled_at'])) {
            $subtitleParts[] = 'Annulé';
        }

        return Response::view('layout.main', [
            'title' => $eventTitle !== '' ? $eventTitle : 'Fiche créneau',
            'content' => 'admin.organization.event_show',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Opérations',
            'boPageKicker' => 'OPÉRATIONS · CRÉNEAU',
            'boPageTitle' => $eventTitle !== '' ? $eventTitle : 'Fiche créneau',
            'boPageSubtitle' => implode(' · ', $subtitleParts),
            'boPageQuick' => [
                ['label' => 'Réponses nominatives', 'href' => url('back-office/events/' . $id . '/reponses-nominatives')],
                ['label' => 'Feuille de présence', 'href' => url('back-office/events/' . $id . '/export-presences')],
                ['label' => 'Registre', 'href' => url('back-office/events')],
            ],
            'backOfficePageCss' => ['back-office-events.css'],
            'event' => $event,
            'eventRsvps' => $rsvps,
            'eventMemberLookup' => $memberLookup,
            'eventMemberLookupQuery' => $lookupQ,
            'eventRsvpUserIds' => $rsvpUserIds,
            'eventStaffActionsEnabled' => empty($event['cancelled_at']),
            'eventSlots' => $slots,
            'eventSlotAssignmentsBySlot' => $slotAssignmentsBySlot,
            'eventUnits' => $this->units->allForTenant($tenantId),
            'eventSlotQualificationsEnabled' => $slotQualificationsEnabled,
            'eventQualificationCourses' => $qualificationCourses,
        ]);
    }

    public function storeSlot(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        $event = $id > 0 ? $this->events->findByIdForTenant($id, $tenantId) : null;
        if (!$event || !empty($event['cancelled_at'])) {
            Session::flash('error', 'Créneau introuvable ou déjà annulé.');

            return $this->redirectToEvent($params, $id > 0 ? $id : null);
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Le nom du poste est requis.');

            return $this->redirectToEvent($params, $id);
        }
        $capacity = max(1, min(200, (int) $request->input('capacity', 1)));
        $unitId = (int) $request->input('unit_id', 0);
        $this->slots->create($tenantId, $id, array_merge([
            'label' => mb_substr($label, 0, 160),
            'unit_id' => $unitId > 0 ? $unitId : null,
            'capacity' => $capacity,
            'loadout_notes' => trim((string) $request->input('loadout_notes', '')) ?: null,
        ], $this->slotQualificationInput($request, $tenantId)));
        Session::flash('success', 'Poste ajouté.');

        return $this->redirectToEvent($params, $id);
    }

    public function updateSlot(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        $slotId = (int) ($params['slotId'] ?? 0);
        $event = $id > 0 ? $this->events->findByIdForTenant($id, $tenantId) : null;
        if (!$event) {
            Session::flash('error', 'Créneau introuvable.');

            return $this->redirectToEvent($params, $id > 0 ? $id : null);
        }
        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Le nom du poste est requis.');

            return $this->redirectToEvent($params, $id);
        }
        $capacity = max(1, min(200, (int) $request->input('capacity', 1)));
        $unitId = (int) $request->input('unit_id', 0);
        $updated = $this->slots->update($slotId, $id, array_merge([
            'label' => mb_substr($label, 0, 160),
            'unit_id' => $unitId > 0 ? $unitId : null,
            'capacity' => $capacity,
            'loadout_notes' => trim((string) $request->input('loadout_notes', '')) ?: null,
        ], $this->slotQualificationInput($request, $tenantId)));
        Session::flash($updated ? 'success' : 'error', $updated ? 'Poste modifié.' : 'Poste introuvable.');

        return $this->redirectToEvent($params, $id);
    }

    public function deleteSlot(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        $slotId = (int) ($params['slotId'] ?? 0);
        $event = $id > 0 ? $this->events->findByIdForTenant($id, $tenantId) : null;
        if (!$event) {
            Session::flash('error', 'Créneau introuvable.');

            return $this->redirectToEvent($params, $id > 0 ? $id : null);
        }
        $deleted = $this->slots->delete($slotId, $id);
        if ($deleted) {
            $this->slotAssignments->deleteAllForSlot($slotId);
        }
        Session::flash($deleted ? 'success' : 'error', $deleted ? 'Poste supprimé.' : 'Poste introuvable.');

        return $this->redirectToEvent($params, $id);
    }

    public function updateParticipantRsvp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Créneau introuvable.');

            return Response::redirect(url('back-office/events'));
        }
        $targetUserId = (int) $request->input('user_id');
        $action = trim((string) $request->input('participation', ''));
        if (!in_array($action, ['yes', 'no', 'maybe', 'remove'], true)) {
            Session::flash('error', 'Choix de participation invalide.');

            return $this->redirectToEvent($params, $id);
        }
        $result = $this->attendance->adminSetParticipantRsvpWithReason(
            $id,
            $tenantId,
            $targetUserId,
            $action,
            trim((string) $request->input('absence_reason', '')) ?: null,
            trim((string) $request->input('absence_note', '')) ?: null
        );
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Modification impossible.');

            return $this->redirectToEvent($params, $id);
        }
        Session::flash('success', $action === 'remove' ? 'Inscription retirée.' : 'Participation mise à jour.');

        return $this->redirectToEvent($params, $id);
    }

    public function addParticipant(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Créneau introuvable.');

            return Response::redirect(url('back-office/events'));
        }
        $targetUserId = (int) $request->input('user_id');
        $action = trim((string) $request->input('participation', 'yes'));
        if (!in_array($action, ['yes', 'no', 'maybe'], true)) {
            $action = 'yes';
        }
        $result = $this->attendance->adminSetParticipantRsvpWithReason(
            $id,
            $tenantId,
            $targetUserId,
            $action,
            trim((string) $request->input('absence_reason', '')) ?: null,
            trim((string) $request->input('absence_note', '')) ?: null
        );
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Ajout impossible.');

            return $this->redirectToEvent($params, $id);
        }
        Session::flash('success', 'Membre ajouté à la feuille de présence.');

        return $this->redirectToEvent($params, $id);
    }

    public function forceCheckIn(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Créneau introuvable.');

            return Response::redirect(url('back-office/events'));
        }
        $targetUserId = (int) $request->input('user_id');
        $result = $this->attendance->adminForceCheckIn($id, $tenantId, $targetUserId);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Pointage impossible.');

            return $this->redirectToEvent($params, $id);
        }
        Session::flash('success', 'Présence enregistrée pour ce membre.');

        return $this->redirectToEvent($params, $id);
    }

    public function clearCheckIn(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectToEvent($params);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Créneau introuvable.');

            return Response::redirect(url('back-office/events'));
        }
        $targetUserId = (int) $request->input('user_id');
        $result = $this->attendance->adminClearCheckIn($id, $tenantId, $targetUserId);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Action impossible.');

            return $this->redirectToEvent($params, $id);
        }
        Session::flash('success', 'Pointage effacé. Le membre pourra à nouveau être pointé si les règles du portail le permettent.');

        return $this->redirectToEvent($params, $id);
    }

    public function exportPresences(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $id = (int) ($params['id'] ?? 0);
        $event = $id > 0 ? $this->events->findByIdForTenant($id, $tenantId) : null;
        if (!$event) {
            Session::flash('error', 'Événement introuvable.');

            return Response::redirect(url('back-office/events'));
        }
        $rows = $this->events->listRsvpsWithUsersForEvent($id);
        $sep = ';';
        $lines = [];
        $lines[] = implode($sep, ['Nom affiché', 'Indicatif', 'Adresse e-mail', 'Participation', 'Motif absence', 'Note absence', 'Pointage', 'Inscription', 'Rappel envoyé']);
        $lab = static function (string $s): string {
            return match ($s) {
                'yes' => 'Présent',
                'maybe' => 'Peut-être',
                'no' => 'Absent',
                default => $s,
            };
        };
        foreach ($rows as $r) {
            $rem = !empty($r['reminder_sent_at']) ? 'Oui' : 'Non';
            $lines[] = implode($sep, [
                '"' . str_replace('"', '""', (string) ($r['display_name'] ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r['callsign'] ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r['email'] ?? '')) . '"',
                '"' . str_replace('"', '""', $lab((string) ($r['status'] ?? ''))) . '"',
                '"' . str_replace('"', '""', (string) ($r['absence_reason'] ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r['absence_note'] ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r['checked_in_at'] ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($r['rsvp_created_at'] ?? '')) . '"',
                '"' . $rem . '"',
            ]);
        }
        $body = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/u', '-', (string) ($event['title'] ?? 'creneau'));
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'creneau';
        }
        $filename = 'feuille-presences-' . $slug . '-' . $id . '.csv';

        return (new Response())
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($body);
    }

    public function cancel(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/events'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Événement invalide.');

            return Response::redirect(url('back-office/events'));
        }
        $reason = trim((string) $request->input('cancel_reason', ''));
        $result = $this->attendance->cancelEventByOrg($id, $tenantId, $reason !== '' ? $reason : null);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Annulation impossible.');

            return Response::redirect(url('back-office/events/' . (string) $id));
        }
        Session::flash('success', 'Événement annulé. Notifications envoyées : ' . (int) ($result['notified'] ?? 0) . '.');

        return Response::redirect(url('back-office/events'));
    }

    private function redirectEventsIndex(string $vue): Response
    {
        if (!in_array($vue, ['calendrier', 'a_venir', 'passes', 'annules'], true)) {
            $vue = 'calendrier';
        }

        return Response::redirect(url('back-office/events') . '?vue=' . rawurlencode($vue));
    }

    /**
     * Accepte la valeur issue d’un champ datetime-local du navigateur (séparateur « T ») ou un libellé classique « Y-m-d H:i:s ».
     */
    private function parseEventDatetimeInput(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        $s = str_replace('T', ' ', $s);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s) === 1) {
            return $s . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $s) === 1) {
            return $s;
        }
        try {
            $d = new \DateTimeImmutable($s);

            return $d->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, string> $params */
    private function redirectToEvent(array $params, ?int $eventId = null): Response
    {
        $id = $eventId ?? (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url('back-office/events'));
        }

        return Response::redirect(url('back-office/events/' . (string) $id));
    }

    /**
     * @return array{
     *   kpis: array{confirmed_yes:int,effective_yes:int,no_show_yes:int},
     *   absenceReasons: list<array<string,mixed>>,
     *   recommendedSlots: list<array<string,mixed>>,
     *   regularityScores: list<array<string,mixed>>,
     *   newMemberParticipationDelta: float
     * }
     */
    private function buildAttendanceInsights(int $tenantId): array
    {
        return [
            'kpis' => $this->events->attendanceKpisForTenant($tenantId, 90),
            'absenceReasons' => $this->events->absenceReasonBreakdownForTenant($tenantId, 90, 5),
            'recommendedSlots' => $this->events->recommendedSlotsForTenant($tenantId, 120, 3),
            'regularityScores' => $this->events->regularityScoresForTenant($tenantId, 60, 8),
            'newMemberParticipationDelta' => $this->events->newMembersParticipationDeltaForTenant($tenantId, 120),
        ];
    }
}
