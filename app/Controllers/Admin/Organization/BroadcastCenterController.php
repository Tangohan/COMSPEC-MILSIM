<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\Broadcast\BroadcastCenterService;
use App\Services\Platform\FeatureGateService;
use App\Support\BroadcastCenter as BC;

/**
 * Centre de diffusion (/back-office/alerts) : annonces du site, consignes, ordres, tips, messages en jeu et alertes,
 * créés, suivis, modifiés et retirés au même endroit.
 *
 * Droits vérifiés ici, à chaque requête, canal par canal (allowedChannels) : bandeau du site, notes du téléphone,
 * messages en jeu et Discord pour la gestion de la communauté ; ordres et alertes de l'app Tâches aussi pour les
 * responsables (atak.command.alert, atak.mission_cycle.manage), comme l'alerte plein écran de la Tacmap.
 * Écritures : jeton CSRF, communauté de la session seulement. Réponse JSON pour back-office-broadcast.js, sinon
 * message et retour à la page : tout marche sans JavaScript.
 */
final class BroadcastCenterController
{
    private const BASE = 'back-office/alerts';

    public function __construct(
        private BroadcastCenterService $center,
        private AuthService $auth,
        private FeatureGateService $features,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $json = self::wantsJson($request);
        if ($tenantId < 1) {
            return $json ? Response::json(['ok' => false, 'message' => 'Aucune communauté active.', 'reload' => true], 403) : Response::redirect(url('dashboard'));
        }
        $filters = self::filters($request->queryString('type'), $request->queryString('canal'), $request->queryString('etat'), $request->queryString('importance'), $request->queryString('tri'));
        if ($json && $request->queryString('fragment') === '1') {
            return Response::json(['ok' => true, 'html' => $this->fragment($tenantId, $filters)]);
        }
        $prefill = null;
        $dup = $request->queryString('dupliquer');
        if ($dup !== '') {
            $item = $this->center->find($tenantId, $dup);
            if ($item !== null) {
                $prefill = self::prefillFrom($item);
            } else {
                Session::flash('error', 'Diffusion introuvable : rien à dupliquer.');
            }
        }

        return $this->page($tenantId, $filters, $prefill, null);
    }

    /** Aperçu de ce que verra chaque cible, sans rien écrire. */
    public function preview(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $this->fail($request, 403, 'Aucune communauté active.', true);
        }
        if (!Csrf::verify($request)) {
            return $this->fail($request, 419, 'Session expirée. Rechargez la page, puis réessayez.', true);
        }
        $input = self::composeInput($request);
        $preview = $this->buildPreview($tenantId, $input);
        if (!self::wantsJson($request)) {
            return $this->page($tenantId, self::filters('', '', '', '', ''), $input, $preview);
        }

        return Response::json(['ok' => true, 'html' => $this->render('admin.broadcast._preview', ['form' => ['preview' => $preview]]), 'errors' => $preview['errors']]);
    }

    public function send(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $this->fail($request, 403, 'Aucune communauté active.', true);
        }
        if (!Csrf::verify($request)) {
            return $this->fail($request, 419, 'Session expirée. Rechargez la page, puis réessayez.', true);
        }
        $input = self::composeInput($request);
        $allowed = $this->allowedChannels($tenantId);
        $checked = BC::validate($input, $allowed, time());
        $errors = $checked['errors'];
        $result = null;
        if ($errors === []) {
            try {
                $result = $this->center->send($tenantId, $checked['data'], $this->actor());
                $errors = $result['errors'];
            } catch (\Throwable $e) {
                error_log('[broadcast-center] send: ' . $e->getMessage());
                $errors = ['L’envoi a échoué : rien n’a été diffusé. Réessayez dans un instant.'];
            }
        }
        if ($errors !== []) {
            if (!self::wantsJson($request)) {
                return $this->page($tenantId, self::filters('', '', '', '', ''), $input, ['errors' => $errors, 'warnings' => $checked['warnings'], 'channels' => [], 'data' => $checked['data']], 422);
            }

            return Response::json(['ok' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 422);
        }
        $lines = array_map(static fn (array $r): string => BC::CHANNELS[$r['channel']] . ' : ' . $r['message'], $result['results']);
        $ok = $result['ok'];
        $message = ($ok ? 'Diffusé. ' : 'Diffusion incomplète : vérifiez chaque canal. ') . implode(' ', $lines);
        if (!self::wantsJson($request)) {
            Session::flash($ok ? 'success' : 'error', $message);

            return Response::redirect(url(self::BASE) . '#diffusions');
        }

        return Response::json([
            'ok' => $ok,
            'message' => $message,
            'results' => $result['results'],
            'html' => $this->safeFragment($tenantId, self::filtersFrom($request)),
        ], $ok ? 200 : 207);
    }

    public function update(Request $request, array $params = []): Response
    {
        return $this->write($request, function (int $tenantId, string $ref) use ($request): array {
            return $this->center->update($tenantId, $ref, [
                'title' => $request->inputString('title'),
                'body' => $request->inputString('body'),
                'importance' => $request->inputString('importance'),
                'starts_at' => $request->inputString('starts_at'),
                'ends_at' => $request->inputString('ends_at'),
                'kind' => $request->inputString('kind'),
                'rev' => $request->inputString('rev'),
            ], $this->allowedChannels($tenantId), $this->actor());
        });
    }

    public function withdraw(Request $request, array $params = []): Response
    {
        return $this->write($request, function (int $tenantId, string $ref) use ($request): array {
            return $this->center->withdraw($tenantId, $ref, $request->inputString('rev'), $this->allowedChannels($tenantId), $this->actor());
        });
    }

    /** @param callable(int, string): array{ok: bool, status: int, message: string, conflict?: bool} $change */
    private function write(Request $request, callable $change): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $this->fail($request, 403, 'Aucune communauté active.', true);
        }
        if (!Csrf::verify($request)) {
            return $this->fail($request, 419, 'Session expirée. Rechargez la page, puis réessayez.', true);
        }
        $ref = $request->inputString('ref');
        if (preg_match('/^(site|note|order|msgs):\d{1,9}$|^bc:[a-z0-9]{12}$/', $ref) !== 1) {
            return $this->fail($request, 422, 'Diffusion inconnue.', false);
        }
        try {
            $result = $change($tenantId, $ref);
        } catch (\Throwable $e) {
            error_log('[broadcast-center] write: ' . $e->getMessage());
            $result = ['ok' => false, 'status' => 500, 'message' => 'L’enregistrement a échoué. Réessayez dans un instant.'];
        }
        if (!self::wantsJson($request)) {
            Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

            return Response::redirect(url(self::BASE) . self::query(self::filtersFrom($request)) . '#diffusions');
        }
        $payload = ['ok' => $result['ok'], 'message' => $result['message']];
        if (!empty($result['conflict'])) {
            $payload['conflict'] = true;
        }
        // Déjà écrit (ou liste changée ailleurs) : la liste à jour accompagne la réponse.
        if ($result['ok'] || !empty($result['conflict']) || $result['status'] === 404) {
            $html = $this->safeFragment($tenantId, self::filtersFrom($request));
            if ($html !== null) {
                $payload['html'] = $html;
            }
        }

        return Response::json($payload, $result['status']);
    }

    // ── Rendu ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $filters
     * @param array<string, mixed>|null $prefill formulaire prérempli (duplication, aperçu ou erreur sans JavaScript)
     * @param array<string, mixed>|null $preview
     */
    private function page(int $tenantId, array $filters, ?array $prefill, ?array $preview, int $status = 200): Response
    {
        $response = Response::view('layout.main', [
            'content' => 'admin.broadcast.index',
            'title' => 'Centre de diffusion',
            'backOfficePageCss' => ['effectifs-kit.css', 'back-office-atak-brief.css', 'back-office-broadcast.css'],
            'boPageTitle' => 'Centre de diffusion',
            'boPageKicker' => 'COMMUNAUTÉ · DIFFUSION',
            'boPageSubtitle' => 'Annonces du site, consignes, ordres, tips, messages en jeu et alertes : écrire une fois, choisir qui reçoit et par où, puis suivre la lecture.',
            'form' => $this->viewData($tenantId, $filters) + [
                'prefill' => $prefill,
                'preview' => $preview,
                'options' => $this->center->options($tenantId),
                'allowed' => $this->allowedChannels($tenantId),
                'siteDenied' => !$this->siteFeature($tenantId),
                'showUpcoming' => $this->showUpcoming($tenantId),
            ],
        ]);

        return $status === 200 ? $response : $response->setStatusCode($status);
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    private function viewData(int $tenantId, array $filters): array
    {
        $all = $this->center->items($tenantId);
        $counts = ['active' => 0, 'scheduled' => 0, 'tracked' => 0, 'total' => count($all['items'])];
        foreach ($all['items'] as $item) {
            if (isset($counts[$item['state']])) {
                ++$counts[$item['state']];
            }
            if ($item['state'] === 'active' && (isset($item['parts']['orders']) || isset($item['parts']['game']))) {
                ++$counts['tracked'];
            }
        }

        return [
            'items' => BroadcastCenterService::filter($all['items'], $filters),
            'filters' => $filters,
            'counts' => $counts,
            'notesRev' => $all['notes_rev'],
            'allowedChannels' => $this->allowedChannels($tenantId),
        ];
    }

    /** @param array<string, string> $filters */
    private function fragment(int $tenantId, array $filters): string
    {
        return $this->render('admin.broadcast._live', ['form' => $this->viewData($tenantId, $filters)]);
    }

    /** @param array<string, string> $filters */
    private function safeFragment(int $tenantId, array $filters): ?string
    {
        try {
            return $this->fragment($tenantId, $filters);
        } catch (\Throwable $e) {
            error_log('[broadcast-center] fragment: ' . $e->getMessage());

            return null;
        }
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        $html = Response::view($view, $vars)->body();
        if (function_exists('locale') && locale() === 'en') {
            $html = \App\Support\HtmlTranslator::translate($html);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: list<string>, warnings: list<string>, channels: array<string, array<string, mixed>>, data: array<string, mixed>}
     */
    private function buildPreview(int $tenantId, array $input): array
    {
        $checked = BC::validate($input, $this->allowedChannels($tenantId), time());
        $plan = ['errors' => [], 'channels' => []];
        if ($checked['errors'] === []) {
            $plan = $this->center->plan($tenantId, $checked['data']);
        }
        foreach ($plan['channels'] as &$channel) {
            unset($channel['players']);
        }
        unset($channel);

        return ['errors' => array_merge($checked['errors'], $plan['errors']), 'warnings' => $checked['warnings'], 'channels' => $plan['channels'], 'data' => $checked['data']];
    }

    // ── Droits ────────────────────────────────────────────────────────────

    /**
     * Canaux que la personne connectée peut utiliser dans cette communauté.
     *
     * @return list<string>
     */
    private function allowedChannels(int $tenantId): array
    {
        $gate = Gate::getInstance();
        $admin = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('admin.system');
        $command = $admin || $gate->allows('atak.command.alert') || $gate->allows('atak.mission_cycle.manage');
        $out = [];
        if ($admin && $this->siteFeature($tenantId)) {
            $out[] = 'site';
        }
        if ($admin) {
            array_push($out, 'phone', 'game', 'discord');
        }
        if ($command) {
            $out[] = 'tasks';
        }

        return $out;
    }

    /** @var array<int, bool> */
    private array $siteFeatureCache = [];

    private function siteFeature(int $tenantId): bool
    {
        if (!isset($this->siteFeatureCache[$tenantId])) {
            try {
                $this->siteFeatureCache[$tenantId] = $this->features->allows($tenantId, 'alerts');
            } catch (\Throwable) {
                $this->siteFeatureCache[$tenantId] = false;
            }
        }

        return $this->siteFeatureCache[$tenantId];
    }

    private function showUpcoming(int $tenantId): bool
    {
        try {
            $settings = (new \App\Repositories\TenantAdminSettingsRepository())->getForTenant($tenantId);

            return !empty($settings['portal']['show_upcoming_alerts']);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{id: ?int, label: string} */
    private function actor(): array
    {
        $user = $this->auth->user() ?? [];
        $name = trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? ''));

        return ['id' => ((int) ($user['id'] ?? 0)) ?: null, 'label' => $name !== '' ? $name : 'Gestion de la communauté'];
    }

    // ── Saisie ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private static function composeInput(Request $request): array
    {
        $in = [];
        foreach (['type', 'title', 'body', 'importance', 'target', 'starts_at', 'ends_at', 'map_id', 'group_id', 'fire_team_id'] as $k) {
            $in[$k] = $request->inputString($k);
        }
        foreach (['channels', 'unit_ids', 'role_slugs', 'user_ids'] as $k) {
            $v = $request->input($k, []);
            $in[$k] = is_array($v) ? array_values(array_filter($v, 'is_scalar')) : [];
        }
        $in['require_ack'] = $request->inputString('require_ack', '1');

        return $in;
    }

    /** @return array<string, mixed> */
    private static function prefillFrom(array $item): array
    {
        $date = static fn (?int $t): string => $t !== null && $t > time() ? date('Y-m-d\TH:i', $t) : '';

        return [
            'type' => $item['type'],
            'title' => $item['title'],
            'body' => $item['body'],
            'importance' => $item['importance'] ?? 'info',
            'channels' => array_values(array_intersect($item['channels'], BC::TYPE_CHANNELS[$item['type']] ?? [])),
            'target' => 'all',
            'starts_at' => $date($item['starts'] ?? null),
            'ends_at' => $date($item['ends'] ?? null),
        ];
    }

    /** @return array<string, string> */
    private static function filters(string $type, string $canal, string $etat, string $importance, string $tri): array
    {
        return [
            'type' => isset(BC::TYPES[$type]) ? $type : '',
            'canal' => isset(BC::CHANNELS[$canal]) ? $canal : '',
            'etat' => isset(BC::STATES[$etat]) ? $etat : '',
            'importance' => isset(BC::IMPORTANCE[$importance]) ? $importance : '',
            'tri' => $tri === 'importance' ? 'importance' : '',
        ];
    }

    /** Filtres de la liste affichée, renvoyés par les formulaires d'action. */
    private static function filtersFrom(Request $request): array
    {
        return self::filters($request->inputString('f_type'), $request->inputString('f_canal'), $request->inputString('f_etat'), $request->inputString('f_importance'), $request->inputString('f_tri'));
    }

    /** @param array<string, string> $filters */
    public static function query(array $filters): string
    {
        $filters = array_filter($filters, static fn (string $v): bool => $v !== '');

        return $filters === [] ? '' : '?' . http_build_query($filters);
    }

    private function fail(Request $request, int $status, string $message, bool $reload): Response
    {
        if (!self::wantsJson($request)) {
            Session::flash('error', $message);

            return Response::redirect(url(self::BASE));
        }

        return Response::json(['ok' => false, 'message' => $message, 'reload' => $reload], $status);
    }

    private static function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }
}
