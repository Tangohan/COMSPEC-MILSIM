<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AthenaInspectorMetricsRepository;

/**
 * GET /api/athena/inspector/metrics?view=…&hours=…&scope=tenant|platform&q=…&format=json|csv
 *
 * Vues : overview, pipeline, players, mods, mod_players, terminals, traffic, errors, communities.
 * La portée « platform » (toutes communautés) est réservée aux administrateurs plateforme.
 */
final class AthenaInspectorMetricsController
{
    private const VIEWS = ['overview', 'pipeline', 'players', 'mods', 'mod_players', 'terminals', 'traffic', 'errors', 'communities'];
    private const PERIODS = [1, 6, 24, 72, 168, 720];

    public function __construct(private ?AthenaInspectorMetricsRepository $repo = null)
    {
        $this->repo ??= new AthenaInspectorMetricsRepository();
    }

    public static function canSeePlatform(): bool
    {
        try {
            $gate = Gate::getInstance();

            return $gate->isPlatformAdmin() || $gate->allows('admin.system');
        } catch (\Throwable) {
            return false;
        }
    }

    public function metrics(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $view = $request->queryString('view', 'overview');
        if (!in_array($view, self::VIEWS, true)) {
            return Response::json(['ok' => false, 'error' => 'unknown_view', 'views' => self::VIEWS], 400);
        }
        $platform = $request->query('scope', 'tenant') === 'platform';
        if ($platform && !self::canSeePlatform()) {
            return Response::json(['ok' => false, 'error' => 'forbidden_scope'], 403);
        }
        if ($view === 'communities' && !$platform) {
            return Response::json(['ok' => false, 'error' => 'platform_scope_required'], 400);
        }
        if (!$platform && $tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'tenant_context_required'], 403);
        }
        $scopeId = $platform ? null : $tenantId;
        $hours = (int) $request->query('hours', 24);
        if (!in_array($hours, self::PERIODS, true)) {
            $hours = 24;
        }
        $q = mb_substr(trim($request->queryString('q', '')), 0, 80);

        $data = match ($view) {
            'overview' => $this->repo->overview($scopeId, $hours),
            'pipeline' => ['players' => $this->repo->pipeline($scopeId, $hours, $q)],
            'players' => ['players' => $this->repo->players($scopeId, $hours, $q)],
            'mods' => $this->repo->mods($scopeId, $hours, $q),
            'mod_players' => ['players' => $this->repo->modPlayers($scopeId, mb_substr($request->queryString('mod', ''), 0, 160))],
            'terminals' => $this->repo->terminals($scopeId, $q),
            'traffic' => $this->repo->traffic($scopeId, $hours),
            'errors' => $this->repo->errors($scopeId, $hours),
            'communities' => ['communities' => $this->repo->communities($hours)],
        };

        if ($request->query('format', 'json') === 'csv') {
            return $this->csv($view, $data);
        }

        return Response::json([
            'ok' => true,
            'view' => $view,
            'scope' => $platform ? 'platform' : 'tenant',
            'hours' => $hours,
            'db_offset' => $this->repo->dbOffset(),
            'generated_at' => gmdate(DATE_ATOM),
            'unavailable' => $this->repo->missingSources(),
            'data' => $data,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function csv(string $view, array $data): Response
    {
        $rows = match ($view) {
            'pipeline', 'players', 'mod_players' => $data['players'] ?? [],
            'mods' => $data['mods'] ?? [],
            'terminals' => $data['terminals'] ?? [],
            'communities' => $data['communities'] ?? [],
            'errors' => $data['tickets'] ?? [],
            'traffic' => $data['top_players'] ?? [],
            default => [],
        };
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        $header = null;
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            if (isset($r['stages']) && is_array($r['stages'])) {
                foreach ($r['stages'] as $k => $st) {
                    $r['etape_' . $k] = ($st['status'] ?? '') . ' ' . ($st['at'] ?? '') . ' ' . ($st['detail'] ?? '');
                }
                unset($r['stages']);
            }
            $r = array_map(static fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v, $r);
            if ($header === null) {
                $header = array_keys($r);
                fputcsv($fh, $header, ';');
            }
            fputcsv($fh, array_map(static fn ($k) => $r[$k] ?? '', $header), ';');
        }
        rewind($fh);
        $body = (string) stream_get_contents($fh);
        fclose($fh);

        return (new Response())
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="athena-' . $view . '-' . date('Ymd-Hi') . '.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody($body);
    }
}
