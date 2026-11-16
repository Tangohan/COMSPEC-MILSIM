<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\TocAccess;
use App\Services\AtakToc\TocReportPdf;
use App\Services\AtakToc\TocReportService;

/**
 * Panneau Rapports du poste TOC web : lecture des rapports tactiques du jeu (BDA, comptes rendus, SITREP, 9-line, autres),
 * lu / non lu par opérateur, accusé de réception, jonction à un plan d'opération, export PDF.
 *   GET  /atak/toc/api/reports?map=&period=&family=&type=&unread=&priority=&status=&unit=&q=&sort=&limit=   liste (ETag)
 *   GET  /atak/toc/api/reports/plans                        plans modifiables par l'opérateur, avec leurs phases
 *   GET  /atak/toc/api/reports/{clé}                        fiche (tr-12 : compte rendu, nl-7 : 9-line)
 *   POST /atak/toc/api/reports/{clé}/read    {read: bool}   lu / non lu
 *   POST /atak/toc/api/reports/read-all                     tout marquer lu (carte et période demandées)
 *   POST /atak/toc/api/reports/{clé}/ack                    accusé de réception
 *   POST /atak/toc/api/reports/{clé}/attach  {planId, phase}  joindre à un plan (et à une phase)
 *   GET  /atak/toc/reports/{clé}/export.pdf                 fiche PDF (check-in CAS de l'app JTAC : fiche 9 lignes remplie)
 *   GET  /atak/toc/cas-checkin.pdf                          fiche de check-in CAS vierge (« 9 LINE CLOSE AIR SUPPORT BRIEF »)
 * Droit : atak.toc.use (comme tout le poste). Communauté : celle de la session, jamais lue dans la requête. Habilitation :
 * un rapport au-dessus de celle de l'opérateur répond « introuvable ». Écritures : jeton CSRF et limite de débit.
 */
final class AtakTocReportsApiController extends AtakTocBaseController
{
    private TocReportService $reports;

    /** @var callable(): string */
    private $level;

    private ?AtakPlanRepository $planRepo;

    /**
     * @param callable(): string|null $level habilitation de lecture de la session (échelle SSE) ; par défaut SseClearanceService
     */
    public function __construct(?TocAccess $access = null, ?TocReportService $reports = null, ?callable $level = null, ?AtakPlanRepository $planRepo = null)
    {
        parent::__construct($access);
        $this->planRepo = $planRepo;
        $this->reports = $reports ?? new TocReportService();
        $this->level = $level ?? static function (): string {
            try {
                return (new \App\Services\Sse\SseClearanceService())->maxLevel();
            } catch (\Throwable) {
                return \App\Repositories\SseCaseRepository::CLASS_INTERNAL;
            }
        };
    }

    private function level(): string
    {
        return ($this->level)();
    }

    /**
     * Libellés du catalogue (types, priorités, statuts, champs, lignes) dans la langue du site ; les textes du jeu ne sont jamais traduits.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function localized(array $r): array
    {
        if (!function_exists('locale') || locale() !== 'en') {
            return $r;
        }
        $t = static fn (mixed $s): string => \App\Support\HtmlTranslator::translateText((string) $s);
        foreach (['type_label', 'priority_label', 'status_label'] as $k) {
            if (isset($r[$k])) {
                $r[$k] = $t($r[$k]);
            }
        }
        if (isset($r['result']['label']) && ($r['result']['code'] ?? 'unknown') !== 'unknown') {
            $r['result']['label'] = $t($r['result']['label']);
        }
        foreach (['facts', 'blocks', 'extra', 'lines'] as $list) {
            foreach ($r[$list] ?? [] as $i => $row) {
                if (isset($row['label'])) {
                    $r[$list][$i]['label'] = $t($row['label']);
                }
                if ($list === 'facts' && ($row['label'] ?? '') === 'Résultat') {
                    $r[$list][$i]['value'] = $t($row['value']);
                }
            }
        }

        return $r;
    }

    /** Clé d'une adresse : « tr-12 » ou « nl-7 », sinon null. */
    private function key(array $params): ?string
    {
        $parsed = TocReportService::parseKey((string) ($params['key'] ?? ''));

        return $parsed === null ? null : TocReportService::key($parsed[0], $parsed[1]);
    }

    private static function since(string $period): ?int
    {
        return match ($period) {
            '1h' => time() - 3600, '6h' => time() - 6 * 3600, '24h' => time() - 86400, '7d' => time() - 7 * 86400,
            '14d' => time() - 14 * 86400, '90d' => time() - 90 * 86400, 'all' => 0, default => null,
        };
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $map = (string) ($request->query('map') ?? '');
        $q = ['map' => in_array($map, ['0', 'all'], true) ? 0 : $this->mapId($request)];
        foreach (['family', 'type', 'priority', 'status', 'unit', 'q', 'sort'] as $k) {
            $v = $request->query($k);
            if (is_string($v) && trim($v) !== '') {
                $q[$k] = mb_substr(trim($v), 0, 80);
            }
        }
        if ((string) ($request->query('unread') ?? '') === '1') {
            $q['unread'] = 1;
        }
        $since = self::since((string) ($request->query('period') ?? ''));
        if ($since !== null) {
            $q['since'] = $since;
        }
        $limit = (int) ($request->query('limit') ?? 0);
        if ($limit > 0) {
            $q['limit'] = min(TocReportService::MAX_LIMIT, $limit);
        }

        return $q;
    }

    public function index(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $res = $this->reports->list($this->tenantId(), $this->userId(), $this->level(), $this->filters($request));
        $sig = [];
        $res['reports'] = array_map(fn (array $r): array => $this->localized($r), $res['reports']);
        foreach ($res['reports'] as $r) {
            $sig[] = $r['key'] . '@' . $r['updated'] . '#' . ($r['read'] ? 1 : 0) . ($r['acked'] ? 1 : 0);
        }

        return $this->jsonWithEtag(['ok' => true, 'now' => time()] + $res, substr(md5(implode(';', $sig) . '|' . $res['hidden'] . $res['readState']), 0, 16));
    }

    /** Plans où l'opérateur peut joindre un rapport (ceux qu'il peut modifier), avec leurs phases. */
    public function plans(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        try {
            $repo = $this->planRepo ?? new AtakPlanRepository();
            $units = $this->access->unitsOf($this->tenantId(), $this->userId());
            $out = [];
            foreach (array_slice($repo->listVisible($this->tenantId(), $this->userId(), $units, $this->access->canManage()), 0, 80) as $plan) {
                if (!$this->access->canEdit($plan, $this->tenantId(), $this->userId(), $repo->shares((int) $plan['id']))) {
                    continue;
                }
                $out[] = [
                    'id' => (int) $plan['id'], 'title' => (string) $plan['title'], 'map_id' => (int) $plan['map_id'],
                    'phases' => array_map(static fn (array $p): array => ['uid' => $p['uid'], 'name' => $p['name']], $repo->phases((int) $plan['id'])),
                ];
            }
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'error' => 'schema_not_ready'], 503);
        }

        return Response::json(['ok' => true, 'plans' => $out]);
    }

    public function show(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        $key = $this->key($params);
        $item = $key === null ? null : $this->reports->find($this->tenantId(), $this->userId(), $this->level(), $key);
        if ($item === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }

        return Response::json(['ok' => true, 'report' => $this->localized($item)]);
    }

    public function read(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'report-read', 240)) !== null) {
            return $deny;
        }
        $key = $this->key($params);
        $item = $key === null ? null : $this->reports->find($this->tenantId(), $this->userId(), $this->level(), $key);
        if ($item === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $read = !in_array($this->body()['read'] ?? true, [false, 0, '0', 'false'], true);
        if (!$this->reports->readsReady()) {
            return Response::json(['ok' => true, 'persisted' => false, 'readState' => 'degraded', 'read' => $read]);
        }
        $ok = $this->reports->markRead($this->tenantId(), $this->userId(), $this->level(), $key, $read);

        return Response::json(['ok' => $ok, 'persisted' => $ok, 'readState' => $this->reports->readState(), 'read' => $read], $ok ? 200 : 503);
    }

    public function readAll(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'report-read', 240)) !== null) {
            return $deny;
        }
        if (!$this->reports->readsReady()) {
            return Response::json(['ok' => true, 'persisted' => false, 'readState' => 'degraded', 'marked' => 0]);
        }
        $n = $this->reports->markAllRead($this->tenantId(), $this->userId(), $this->level(), $this->filters($request));

        return Response::json(['ok' => true, 'persisted' => true, 'readState' => 'ok', 'marked' => $n]);
    }

    public function ack(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'report-ack', 60)) !== null) {
            return $deny;
        }
        $key = $this->key($params);
        $item = $key === null ? null : $this->reports->acknowledge($this->tenantId(), $this->userId(), $this->level(), $key);
        if ($item === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $this->audit('atak_toc.report.ack', (int) $item['id'], ['key' => $key]);

        return Response::json(['ok' => true, 'report' => $this->localized($item)]);
    }

    public function attach(Request $request, array $params = []): Response
    {
        if (($deny = $this->guard()) !== null) {
            return $deny;
        }
        if (($deny = $this->guardWrite($request, 'report-attach', 60)) !== null) {
            return $deny;
        }
        $key = $this->key($params);
        $b = $this->body();
        $planId = (int) ($b['planId'] ?? 0);
        $phase = is_string($b['phase'] ?? null) ? (string) $b['phase'] : '';
        if ($key === null || $this->reports->find($this->tenantId(), $this->userId(), $this->level(), $key) === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $repo = $this->planRepo ?? new AtakPlanRepository();
        $plans = new PlanService($repo, static fn (): ?int => null);
        $plan = $planId > 0 ? $repo->find($this->tenantId(), $planId) : null;
        $shares = $plan !== null ? $repo->shares((int) $plan['id']) : [];
        // Même règle que l'éditeur de plans : un plan qu'on ne peut pas lire n'existe pas, un plan lisible mais verrouillé est refusé.
        if ($plan === null || !$this->access->canView($plan, $this->tenantId(), $this->userId(), $shares)) {
            return Response::json(['ok' => false, 'error' => 'plan_not_found'], 404);
        }
        if (!$this->access->canEdit($plan, $this->tenantId(), $this->userId(), $shares)) {
            return Response::json(['ok' => false, 'error' => 'read_only'], 403);
        }
        $res = $this->reports->attachToPlan(
            $this->tenantId(),
            $this->userId(),
            $this->level(),
            $key,
            $plan,
            $phase !== '' ? $phase : null,
            $plans,
            $this->access->canAdminister($plan, $this->tenantId(), $this->userId())
        );
        if (!$res['ok']) {
            return Response::json(['ok' => false, 'error' => $res['reason'] ?? 'refused'], 422);
        }
        $this->audit('atak_toc.report.attach', $planId, ['key' => $key, 'phase' => $phase]);

        return Response::json(['ok' => true, 'uid' => $res['uid'] ?? '', 'plans' => $this->reports->attachedPlans($this->tenantId(), $key)]);
    }

    /** Fiche de check-in CAS vierge, à imprimer (même grille que celle remplie depuis le téléphone). */
    public function casCheckinBlank(Request $request, array $params = []): Response
    {
        if (!$this->access->canUse()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        try {
            $bytes = (new \App\Services\AtakToc\CasCheckinSheetPdf())->render([], ['lang' => function_exists('locale') && locale() === 'en' ? 'en' : 'fr']);
        } catch (\Throwable) {
            $bytes = null;
        }
        if ($bytes === null) {
            return Response::json(['ok' => false, 'error' => 'pdf_unavailable'], 503);
        }

        return (new Response())->setStatusCode(200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="fiche-cas-check-in.pdf"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($bytes);
    }

    public function pdf(Request $request, array $params = []): Response
    {
        if (!$this->access->canUse()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $key = $this->key($params);
        $item = $key === null ? null : $this->reports->find($this->tenantId(), $this->userId(), $this->level(), $key);
        if ($item === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        try {
            $bytes = (new TocReportPdf())->render($item, [
                'lang' => function_exists('locale') && locale() === 'en' ? 'en' : 'fr',
                'operator' => $this->actorLabel(),
            ]);
        } catch (\Throwable) {
            $bytes = null;
        }
        if ($bytes === null) {
            return Response::json(['ok' => false, 'error' => 'pdf_unavailable'], 503);
        }
        $this->audit('atak_toc.report.pdf', (int) $item['id'], ['key' => $key]);
        $name = is_array($item['cas_ckin'] ?? null) ? 'cas-check-in-' . $key . '.pdf'
            : 'rapport-' . strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $item['type'])) . '-' . $key . '.pdf';

        return (new Response())->setStatusCode(200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($bytes);
    }
}
