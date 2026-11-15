<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Atak\MarkerCycle\AtakMarkerArchiveService;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use App\Services\Atak\MarkerCycle\PdoMarkerCycleStore;

/**
 * Cycle de 24 h des marqueurs et missions archivées, pour le poste TOC web, ATAK web (Overwatch) et TACMAP.
 *   GET  /api/atak/cycle/status                   cycle en cours, temps restant, réglages, droits du membre
 *   GET  /api/atak/cycle/archives                 missions archivées (carte, dates, recherche)
 *   GET  /api/atak/cycle/archives/{id}            une mission archivée en lecture seule (éléments, positions, tracés)
 *   GET  /api/atak/cycle/archives/{id}/export.json  export complet
 *   POST /api/atak/cycle/archives/{id}/restore    copie les éléments vers le cycle en cours (poste TOC ou gestion)
 *   POST /api/atak/cycle/archives/{id}/delete     purge manuelle (gestion, phrase SUPPRIMER)
 *   POST /api/atak/cycle/settings                 heure de bascule, durée, activation, rétention (gestion)
 *   POST /api/atak/cycle/pin                      épingler ou désépingler un marqueur ou un tracé (poste TOC ou gestion)
 *
 * La communauté vient uniquement de la session du membre (jamais de la requête) ; toute écriture exige le jeton CSRF.
 * Droits : lecture = membre connecté de la communauté (comme la carte) ; épingler et restaurer = droit du poste TOC
 * (atak.toc.use) ou gestion ; réglages et purge = atak.toc.manage, atak.mission_cycle.manage ou gestion de la communauté.
 */
final class AtakMarkerCycleApiController
{
    public const CONFIRM_PHRASE = 'SUPPRIMER';

    /** Corps JSON imposé par les tests (sinon php://input). */
    public static ?string $testBody = null;

    private AtakMarkerArchiveService $archives;

    /** @var callable(int, int): array{operate: bool, manage: bool} */
    private $rights;

    /** @var callable(string, int, int, array<string, mixed>): void */
    private $audit;

    /** @var array<string, mixed>|null */
    private ?array $bodyCache = null;

    /**
     * @param callable(int, int): array{operate: bool, manage: bool}|null $rights droits du membre dans la communauté
     * @param callable(string, int, int, array<string, mixed>): void|null $audit action, communauté, membre, détails
     */
    public function __construct(?AtakMarkerArchiveService $archives = null, ?callable $rights = null, ?callable $audit = null)
    {
        $this->archives = $archives ?? new AtakMarkerArchiveService(new PdoMarkerCycleStore());
        $this->rights = $rights ?? static function (int $tenantId, int $userId): array {
            try {
                $admin = new \App\Services\Atak\AtakGameAdminService();
                $role = $admin->role($tenantId, $userId) !== null;
                $manage = $role || $admin->can($tenantId, $userId, ['atak.toc.manage', 'atak.mission_cycle.manage']);
                $operate = $manage || $admin->can($tenantId, $userId, ['atak.toc.use']);

                return ['operate' => $operate, 'manage' => $manage];
            } catch (\Throwable) {
                return ['operate' => false, 'manage' => false];
            }
        };
        $this->audit = $audit ?? static function (string $action, int $tenantId, int $userId, array $details): void {
            try {
                (new \App\Services\Audit\AuditService())->logChange($action, $tenantId, $userId ?: null, 'atak_marker_cycle', (int) ($details['archive_id'] ?? 0) ?: null, [], $details);
            } catch (\Throwable $e) {
                error_log('[atak-marker-cycle audit] ' . $action . ' : ' . $e->getMessage());
            }
        };
    }

    private function tenantId(): int
    {
        return (int) (Session::get('tenant_id') ?? 0);
    }

    private function userId(): int
    {
        return (int) (Session::get('user_id') ?? 0);
    }

    /** @return array{operate: bool, manage: bool} */
    private function rights(): array
    {
        return ($this->rights)($this->tenantId(), $this->userId());
    }

    private function deny(Request $request, bool $write, string $need = 'member'): ?Response
    {
        Session::start();
        if ($this->tenantId() < 1 || $this->userId() < 1) {
            return Response::json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }
        if ($write && !Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }
        if ($need !== 'member') {
            $r = $this->rights();
            if (!$r[$need]) {
                return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }
        $raw = self::$testBody ?? \App\Support\HttpJsonBody::rawJson();
        $dec = $raw !== '' ? json_decode($raw, true, 32) : null;
        if (!is_array($dec)) {
            $dec = is_array($_POST) ? $_POST : [];
        }

        return $this->bodyCache = $dec;
    }

    public function status(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, false)) !== null) {
            return $d;
        }
        $tenantId = $this->tenantId();
        $status = $this->archives->cycles()->status($tenantId);
        $r = $this->rights();

        return Response::json(['ok' => true, 'cycle' => $status, 'canOperate' => $r['operate'], 'canManage' => $r['manage']])
            ->header('Cache-Control', 'private, no-store');
    }

    public function archives(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, false)) !== null) {
            return $d;
        }
        $tenantId = $this->tenantId();
        $tz = new \DateTimeZone($this->archives->cycles()->settings($tenantId)['timezone']);
        $filter = [];
        $map = (int) ($request->query('mapId') ?? 0);
        if ($map > 0) {
            $filter['map'] = $map;
        }
        foreach (['from' => '00:00:00', 'to' => '23:59:59'] as $k => $time) {
            $v = trim((string) ($request->query($k) ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
                $d0 = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $v . ' ' . $time, $tz);
                if ($d0 !== false) {
                    $filter[$k] = gmdate('Y-m-d H:i:s', $d0->getTimestamp());
                }
            }
        }
        $q = trim((string) ($request->query('q') ?? ''));
        if ($q !== '') {
            $filter['q'] = mb_substr($q, 0, 60);
        }
        $rows = $this->archives->listArchives($tenantId, $filter, 60, max(0, (int) ($request->query('offset') ?? 0)));

        return Response::json(['ok' => true, 'archives' => array_map(fn (array $a): array => $this->presentArchive($a, $tz), $rows)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, false)) !== null) {
            return $d;
        }
        $tenantId = $this->tenantId();
        $view = $this->archives->view($tenantId, (int) ($params['id'] ?? 0));
        if ($view === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $tz = new \DateTimeZone($this->archives->cycles()->settings($tenantId)['timezone']);

        return Response::json([
            'ok' => true,
            'readOnly' => true,
            'archive' => $this->presentArchive($view['archive'], $tz),
            'items' => array_map([self::class, 'presentItem'], $view['items']),
            'truncated' => $view['truncated'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, false)) !== null) {
            return $d;
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->archives->export($this->tenantId(), $id);
        if ($doc === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $this->audit('atak_marker_cycle.export', ['archive_id' => $id]);

        return (new Response())->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="mission-archive-' . $id . '.json"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');
    }

    public function restore(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, true, 'operate')) !== null) {
            return $d;
        }
        $id = (int) ($params['id'] ?? 0);
        $res = $this->archives->restore($this->tenantId(), $id);
        if (!$res['ok']) {
            return Response::json(['ok' => false, 'error' => $res['error'] ?? 'not_found'], 404);
        }
        $this->audit('atak_marker_cycle.restore', ['archive_id' => $id, 'restored' => $res['restored'], 'skipped' => $res['skipped']]);

        return Response::json(['ok' => true, 'restored' => $res['restored'], 'skipped' => $res['skipped']]);
    }

    public function delete(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, true, 'manage')) !== null) {
            return $d;
        }
        if (strcasecmp(trim((string) ($this->body()['confirm'] ?? '')), self::CONFIRM_PHRASE) !== 0) {
            return Response::json(['ok' => false, 'error' => 'confirm_required'], 422);
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$this->archives->deleteArchive($this->tenantId(), $id)) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $this->audit('atak_marker_cycle.delete', ['archive_id' => $id]);

        return Response::json(['ok' => true]);
    }

    public function settings(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, true, 'manage')) !== null) {
            return $d;
        }
        $b = $this->body();
        $clean = $this->archives->cycles()->saveSettings($this->tenantId(), [
            'enabled' => !empty($b['enabled']),
            'switch_time' => (string) ($b['switch_time'] ?? ''),
            'duration_days' => (int) ($b['duration_days'] ?? 1),
            'timezone' => (string) ($b['timezone'] ?? ''),
            'retention_days' => (int) ($b['retention_days'] ?? AtakMarkerCycleService::DEFAULT_RETENTION_DAYS),
        ], $this->userId());
        $this->audit('atak_marker_cycle.settings', $clean);

        return Response::json(['ok' => true, 'settings' => $clean, 'cycle' => $this->archives->cycles()->status($this->tenantId())]);
    }

    public function pin(Request $request, array $params = []): Response
    {
        if (($d = $this->deny($request, true, 'operate')) !== null) {
            return $d;
        }
        $b = $this->body();
        $kind = (string) ($b['kind'] ?? '');
        $id = (int) ($b['id'] ?? 0);
        $pinned = !empty($b['pinned']);
        if (!$this->archives->setPinned($this->tenantId(), $kind, $id, $pinned)) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $this->audit('atak_marker_cycle.pin', ['kind' => $kind, 'id' => $id, 'pinned' => $pinned]);

        return Response::json(['ok' => true, 'pinned' => $pinned]);
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, array $details): void
    {
        ($this->audit)($action, $this->tenantId(), $this->userId(), $details);
    }

    /**
     * @param array<string, mixed> $a
     * @return array<string, mixed>
     */
    private function presentArchive(array $a, \DateTimeZone $tz): array
    {
        $fmt = static function (string $utc) use ($tz): string {
            $t = strtotime($utc . ' UTC');

            return $t === false ? '' : (new \DateTimeImmutable('@' . $t))->setTimezone($tz)->format('d/m H:i');
        };

        return [
            'id' => (int) $a['id'],
            'mapId' => (int) $a['map_id'],
            'key' => (string) $a['cycle_key'],
            'label' => (string) $a['label'],
            'startLocal' => $fmt((string) $a['starts_at']),
            'endLocal' => $fmt((string) $a['ends_at']),
            'startsAt' => gmdate('Y-m-d\TH:i:s\Z', (int) strtotime((string) $a['starts_at'] . ' UTC')),
            'endsAt' => gmdate('Y-m-d\TH:i:s\Z', (int) strtotime((string) $a['ends_at'] . ' UTC')),
            'markers' => (int) $a['markers_count'],
            'shapes' => (int) $a['shapes_count'],
            'legacy' => (string) ($a['source'] ?? '') === 'legacy',
            'system' => ($a['created_by'] ?? null) === null,
        ];
    }

    /**
     * @param array{kind: string, id: int, at: string, payload: array<string, mixed>} $item
     * @return array<string, mixed>
     */
    public static function presentItem(array $item): array
    {
        $p = $item['payload'];
        $out = [
            'kind' => $item['kind'],
            'id' => $item['id'],
            'at' => gmdate('Y-m-d\TH:i:s\Z', (int) strtotime($item['at'] . ' UTC')),
        ];
        if ($item['kind'] === 'marker') {
            $raw = $p['marker_data'] ?? '';
            $d = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
            $d = is_array($d) ? $d : [];
            $pos = $d['pos'] ?? null;
            if (!(is_array($pos) && isset($pos[0], $pos[1]) && is_numeric($pos[0]) && is_numeric($pos[1]))) {
                $x = $d['pos_x'] ?? $d['x'] ?? null;
                $y = $d['pos_y'] ?? $d['y'] ?? null;
                $pos = is_numeric($x) && is_numeric($y) ? [$x, $y] : null;
            }
            $out += [
                'label' => mb_substr((string) ($d['text'] ?? $d['label'] ?? ''), 0, 120),
                'type' => (string) ($d['type'] ?? ''),
                'color' => (string) ($d['color'] ?? ''),
                'author' => mb_substr((string) ($d['author'] ?? ''), 0, 60),
                'pos' => $pos === null ? null : [(float) $pos[0], (float) $pos[1]],
                'pinned' => !empty($d['web_permanent']),
            ];
        } else {
            $meta = is_array($p['meta'] ?? null) ? $p['meta'] : [];
            $out += [
                'label' => mb_substr((string) ($p['label'] ?? ''), 0, 120),
                'type' => (string) ($p['type'] ?? ''),
                'color' => (string) ($p['color'] ?? ''),
                'author' => mb_substr((string) ($p['created_by'] ?? ''), 0, 60),
                'geometry' => $p['geometry'] ?? null,
                'pinned' => !empty($meta['web_permanent']),
            ];
        }

        return $out;
    }
}
