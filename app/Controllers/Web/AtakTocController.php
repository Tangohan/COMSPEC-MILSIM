<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanCatalog;
use App\Services\AtakToc\PlanInterop;
use App\Services\AtakToc\PlanPdfExporter;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\TocAccess;

/**
 * Pages du poste TOC web (section ATAK) :
 *   GET  /atak/toc                         console plein écran (carte commune, chronos, main courante, EXCHECK…)
 *   GET  /atak/toc/plans                   liste des plans d'opération
 *   GET  /atak/toc/plans/{id}              éditeur de plan (lecture seule sur téléphone ou sans droit d'édition)
 *   GET  /atak/toc/plans/lien/{jeton}      plan en lecture seule pour un membre de la communauté
 *   GET  /atak/toc/plans/{id}/export.json  fichier d'échange
 *   GET  /atak/toc/plans/{id}/export.geojson  plan en GeoJSON (mètres Arma, réimportable sans perte)
 *   GET  /atak/toc/plans/{id}/export.kml   plan en KML 2.2 (mètres Arma, réimportable sans perte)
 *   POST /atak/toc/plans/{id}/export.pdf   fiche de briefing (image de la carte jointe par l'éditeur)
 */
final class AtakTocController
{
    private TocAccess $access;

    private PlanService $plans;

    public function __construct(?TocAccess $access = null, ?PlanService $plans = null)
    {
        $this->access = $access ?? new TocAccess();
        $this->plans = $plans ?? new PlanService(new AtakPlanRepository());
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    private function userId(): int
    {
        return (int) Session::get('user_id');
    }

    private function denied(): Response
    {
        return Response::view('atak.toc.denied', ['title' => 'Poste TOC']);
    }

    /**
     * Terrains proposés : cartes du site (atak_maps) avec leur fond (tuiles du jeu, Photo / Plan COMSPEC si publiés).
     *
     * @return array{maps: list<array<string, mixed>>, default: int}
     */
    public static function terrains(int $tenantId): array
    {
        $out = [];
        $default = 1;
        try {
            $repo = new \App\Repositories\AtakMapRepository();
            foreach ($repo->getAll() as $m) {
                $cfg = \App\Support\AtakOverwatchMapConfig::fromMap($m);
                $out[] = [
                    'id' => (int) $m['id'],
                    'slug' => (string) ($m['slug'] ?? ''),
                    'label' => (string) ($m['label'] ?? $m['slug'] ?? ''),
                    'world' => strtolower((string) ($m['world_name'] ?? $m['slug'] ?? '')),
                    'config' => $cfg,
                ];
            }
            $def = $tenantId > 0 ? $repo->getDefaultForTenant($tenantId) : null;
            $default = (int) ($def['id'] ?? ($out[0]['id'] ?? 1));
        } catch (\Throwable) {
            // Sans base : aucune carte, la page le dit.
        }

        return ['maps' => $out, 'default' => $default];
    }

    /** @return array<string, mixed> */
    private function pageData(string $title): array
    {
        $tenantId = $this->tenantId();
        $terrains = self::terrains($tenantId);
        $label = '';
        try {
            $u = (new \App\Repositories\UserRepository())->findById($this->userId(), $tenantId);
            $label = trim((string) (($u['callsign'] ?? '') ?: ($u['display_name'] ?? '') ?: ($u['username'] ?? '')));
        } catch (\Throwable) {
        }

        return [
            'title' => $title,
            'tocTerrains' => $terrains['maps'],
            'tocDefaultMap' => $terrains['default'],
            'tocOperator' => $label !== '' ? $label : 'Opérateur',
            'tocCanManage' => $this->access->canManage(),
            'tocCsrf' => Csrf::token(),
            'tocCatalog' => PlanCatalog::forClient(),
        ];
    }

    public function console(Request $request, array $params = []): Response
    {
        if (!$this->access->canUse()) {
            return $this->denied();
        }

        return Response::view('atak.toc.console', $this->pageData('Poste TOC'));
    }

    public function plans(Request $request, array $params = []): Response
    {
        if (!$this->access->canUse()) {
            return $this->denied();
        }

        return Response::view('atak.toc.plans', $this->pageData('Plans d’opération'));
    }

    /** Guide écrit de l'éditeur de plans (docs/TOC-PLANS-TUTORIEL.md), lié depuis l'aide de l'éditeur. */
    public function planHelp(Request $request, array $params = []): Response
    {
        if (!$this->access->canUse()) {
            return $this->denied();
        }
        $file = base_path('docs/TOC-PLANS-TUTORIEL.md');
        $md = is_file($file) ? (string) file_get_contents($file) : '';
        $html = $md === ''
            ? '<p>Le guide n’est pas disponible pour le moment.</p>'
            : (function_exists('markdown_to_html') ? markdown_to_html($md) : '<pre>' . htmlspecialchars($md, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>');

        return Response::view('atak.toc.plan_help', ['title' => 'Éditeur de plans : guide', 'tocHelpHtml' => $html]);
    }

    public function editor(Request $request, array $params = []): Response
    {
        if (!$this->access->canUse()) {
            return $this->denied();
        }
        $plan = $this->plans->repo()->find($this->tenantId(), (int) ($params['id'] ?? 0));
        if ($plan === null || !$this->access->canView($plan, $this->tenantId(), $this->userId(), $this->plans->repo()->shares((int) $plan['id']))) {
            return Response::view('atak.toc.denied', ['title' => 'Plan introuvable', 'tocMissing' => true]);
        }

        return Response::view('atak.toc.plan_editor', $this->pageData($plan['title']) + ['tocPlanId' => (int) $plan['id'], 'tocReadOnlyLink' => false]);
    }

    /** Lien de lecture : tout membre connecté de la communauté du plan, sans droit d'édition. */
    public function sharedLink(Request $request, array $params = []): Response
    {
        $plan = $this->plans->repo()->findByToken($this->tenantId(), (string) ($params['token'] ?? ''));
        if ($plan === null) {
            return Response::view('atak.toc.denied', ['title' => 'Plan introuvable', 'tocMissing' => true]);
        }

        return Response::view('atak.toc.plan_editor', $this->pageData($plan['title']) + [
            'tocPlanId' => (int) $plan['id'],
            'tocReadOnlyLink' => true,
            'tocSharedDoc' => $this->plans->document($plan),
        ]);
    }

    /** @return array{0: array<string, mixed>|null, 1: Response|null} */
    private function readable(array $params): array
    {
        if (!$this->access->canUse()) {
            return [null, Response::json(['ok' => false, 'error' => 'forbidden'], 403)];
        }
        $plan = $this->plans->repo()->find($this->tenantId(), (int) ($params['id'] ?? 0));
        if ($plan === null || !$this->access->canView($plan, $this->tenantId(), $this->userId(), $this->plans->repo()->shares((int) $plan['id']))) {
            return [null, Response::json(['ok' => false, 'error' => 'not_found'], 404)];
        }

        return [$plan, null];
    }

    private static function fileName(string $title, string $ext): string
    {
        $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', \App\Services\AtakToc\PlanGameMapper::ascii($title, 60)) ?? 'plan');

        return 'plan-' . trim($s, '-') . '.' . $ext;
    }

    public function exportJson(Request $request, array $params = []): Response
    {
        [$plan, $err] = $this->readable($params);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        $doc = $this->plans->document($plan);
        $r = new Response();

        return $r->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . self::fileName($plan['title'], 'json') . '"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}');
    }

    /** Plan en GeoJSON : éléments montrés, coordonnées en mètres Arma (PlanInterop::toGeoJson). */
    public function exportGeoJson(Request $request, array $params = []): Response
    {
        [$plan, $err] = $this->readable($params);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        $r = new Response();

        return $r->setStatusCode(200)
            ->header('Content-Type', 'application/geo+json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . self::fileName($plan['title'], 'geojson') . '"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(json_encode(PlanInterop::toGeoJson($this->plans->document($plan)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    /** Plan en KML 2.2 : éléments montrés, coordonnées en mètres Arma (PlanInterop::toKml). */
    public function exportKml(Request $request, array $params = []): Response
    {
        [$plan, $err] = $this->readable($params);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        $r = new Response();

        return $r->setStatusCode(200)
            ->header('Content-Type', 'application/vnd.google-earth.kml+xml; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . self::fileName($plan['title'], 'kml') . '"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody(PlanInterop::toKml($this->plans->document($plan)));
    }

    public function exportPdf(Request $request, array $params = []): Response
    {
        [$plan, $err] = $this->readable($params);
        if ($plan === null) {
            return $err ?? Response::json(['ok' => false], 404);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'csrf'], 419);
        }
        $image = null;
        $raw = $request->inputString('image');
        if ($raw !== '') {
            $image = PlanPdfExporter::decodeImage($raw);
        }
        $doc = $this->plans->document($plan) + ['plan_id' => (int) $plan['id']];
        try {
            $label = '';
            try {
                $u = (new \App\Repositories\UserRepository())->findById($this->userId(), $this->tenantId());
                $label = (string) (($u['callsign'] ?? '') ?: ($u['display_name'] ?? ''));
            } catch (\Throwable) {
            }
            $pdf = (new PlanPdfExporter())->render($doc, $this->tenantId(), $label, $image);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'error' => 'pdf_unavailable'], 503);
        }
        $r = new Response();

        return $r->setStatusCode(200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . self::fileName($plan['title'], 'pdf') . '"')
            ->header('Cache-Control', 'private, no-store')
            ->setBody($pdf);
    }
}
