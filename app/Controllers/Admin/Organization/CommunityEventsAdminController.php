<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
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
        private CommunityEventAttendanceService $attendance
    ) {}

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
        $vue = trim((string) $request->query('vue', 'a_venir'));
        if (!in_array($vue, ['a_venir', 'passes', 'annules'], true)) {
            $vue = 'a_venir';
        }
        $rows = match ($vue) {
            'passes' => $this->events->pastForTenant($tenantId, 100),
            'annules' => $this->events->cancelledForTenant($tenantId, 100),
            default => $this->events->upcomingForTenant($tenantId, 100),
        };
        $insights = $this->buildAttendanceInsights($tenantId);
        $quota = $this->featureGate->quotaStatusForFeature($tenantId, 'events');

        return Response::view('layout.main', [
            'title' => 'Gérer les événements',
            'content' => 'admin.organization.events',
            'events' => $rows,
            'eventsVue' => $vue,
            'eventsQuota' => $quota,
            'canCreateEvent' => $this->featureGate->allows($tenantId, 'events'),
            'eventsAttendanceKpis' => $insights['kpis'],
            'eventsAbsenceReasons' => $insights['absenceReasons'],
            'eventsRecommendedSlots' => $insights['recommendedSlots'],
            'eventsRegularityScores' => $insights['regularityScores'],
            'eventsNewMemberParticipationDelta' => $insights['newMemberParticipationDelta'],
        ]);
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
        $listVue = trim((string) $request->input('return_vue', 'a_venir'));
        if (!in_array($listVue, ['a_venir', 'passes', 'annules'], true)) {
            $listVue = 'a_venir';
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
        $this->events->updateDetails($eventId, $tenantId, $details);
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

        return Response::view('layout.main', [
            'title' => 'Participants — ' . (string) ($event['title'] ?? ''),
            'content' => 'admin.organization.event_show',
            'event' => $event,
            'eventRsvps' => $rsvps,
            'eventMemberLookup' => $memberLookup,
            'eventMemberLookupQuery' => $lookupQ,
            'eventRsvpUserIds' => $rsvpUserIds,
            'eventStaffActionsEnabled' => empty($event['cancelled_at']),
        ]);
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
        if (!in_array($vue, ['a_venir', 'passes', 'annules'], true)) {
            $vue = 'a_venir';
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
