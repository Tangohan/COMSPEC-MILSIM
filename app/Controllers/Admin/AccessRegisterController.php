<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Services\Platform\FeatureGateService;
use App\Services\Rbac\AccessRegisterService;
use App\Support\EffectifsLmsAccess;
use App\Support\PlanFeatureDenial;
use InvalidArgumentException;
use Throwable;

/**
 * Effectifs › Registre des droits : qui détient quel droit, sur quel périmètre, accordé par qui, depuis quand,
 * jusqu'à quand, avec la revue et l'historique des mouvements.
 *
 * Lecture : pilotage des effectifs (EffectifsLmsAccess::allows). Écriture : gestion des accès (canManageRoles).
 * Module de formule « access_register ». La page est servie par EffectifsWorkspaceController::registreDroits.
 */
final class AccessRegisterController
{
    public const FEATURE = 'access_register';
    public const VIEW_CURRENT = 'vigueur';
    public const VIEW_HISTORY = 'historique';

    public function __construct(private ?AccessRegisterService $register = null)
    {
        $this->register ??= self::service();
    }

    public static function service(): AccessRegisterService
    {
        return new AccessRegisterService(
            Database::getPdo(),
            Container::get(UserRepository::class),
            Container::get(RoleRepository::class),
            Container::get(PersonnelDutyPositionService::class),
        );
    }

    public static function allowedByPlan(int $tenantId): bool
    {
        try {
            return Container::get(FeatureGateService::class)->allowsWithCatalogDefault($tenantId, self::FEATURE);
        } catch (Throwable) {
            return false;
        }
    }

    public static function url(string $suffix = ''): string
    {
        return effectifs_workspace_url('registre-droits' . ($suffix !== '' ? '/' . ltrim($suffix, '/') : ''));
    }

    /**
     * Filtres lus dans l'adresse (aucune valeur libre n'arrive dans le SQL).
     *
     * @return array{view: string, q: string, kind: string, state: string, year: string}
     */
    public static function filtersOf(Request $request): array
    {
        $view = (string) $request->query('vue', self::VIEW_CURRENT);
        $kind = (string) $request->query('nature', '');
        $state = (string) $request->query('etat', '');
        $year = (string) $request->query('annee', '');

        return [
            'view' => $view === self::VIEW_HISTORY ? self::VIEW_HISTORY : self::VIEW_CURRENT,
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 120),
            'kind' => isset(AccessRegisterService::kindLabels()[$kind]) ? $kind : '',
            'state' => isset(AccessRegisterService::stateLabels()[$state]) ? $state : '',
            'year' => preg_match('/^\d{4}$/', $year) === 1 ? $year : '',
        ];
    }

    /**
     * Données de la page.
     *
     * @return array<string, mixed>
     */
    public function pageData(Request $request): array
    {
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();
        $filters = self::filtersOf($request);
        $canManage = EffectifsLmsAccess::canManageRoles($gate);
        $swept = 0;
        try {
            $swept = $this->register->sweepExpired($tenantId);
        } catch (Throwable $e) {
            error_log('[registre-droits] balayage : ' . $e->getMessage());
        }
        $isHistory = $filters['view'] === self::VIEW_HISTORY;
        $all = [];
        $error = '';
        try {
            $all = $isHistory ? $this->register->history($tenantId) : $this->register->current($tenantId);
        } catch (Throwable $e) {
            error_log('[registre-droits] lecture : ' . $e->getMessage());
            $error = 'Le registre n’a pas pu être lu. Réessayez dans un instant.';
        }
        $dateKey = $isHistory ? 'date' : 'since';
        $rows = array_values(array_filter($all, static fn (array $r): bool => AccessRegisterService::matches($r, $filters, $dateKey)));
        $counts = ['total' => count($all), 'expiring' => 0, 'upcoming' => 0, 'unreviewed' => 0, 'individual' => 0];
        if (!$isHistory) {
            foreach ($all as $r) {
                $counts['expiring'] += $r['state'] === AccessRegisterService::STATE_EXPIRING ? 1 : 0;
                $counts['upcoming'] += $r['state'] === AccessRegisterService::STATE_UPCOMING ? 1 : 0;
                $counts['unreviewed'] += $r['can_review'] && $r['reviewed'] === '' ? 1 : 0;
                $counts['individual'] += in_array($r['kind'], [AccessRegisterService::KIND_GRANT, AccessRegisterService::KIND_DENY], true) ? 1 : 0;
            }
        }
        $members = [];
        if ($canManage && !$isHistory) {
            foreach (Container::get(UserRepository::class)->allForTenant($tenantId) as $u) {
                if (strtolower((string) ($u['status'] ?? '')) === 'active') {
                    $label = trim((string) ($u['display_name'] ?? '')) ?: trim(trim((string) ($u['first_name'] ?? '')) . ' ' . trim((string) ($u['last_name'] ?? '')));
                    $cs = trim((string) ($u['callsign'] ?? ''));
                    $members[(int) $u['id']] = ($label !== '' ? $label : 'Membre #' . (int) $u['id']) . ($cs !== '' ? ' (' . $cs . ')' : '');
                }
            }
            asort($members, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return [
            'regFilters' => $filters,
            'regRows' => array_slice($rows, 0, AccessRegisterService::MAX_ROWS),
            'regMatched' => count($rows),
            'regCounts' => $counts,
            'regYears' => AccessRegisterService::years($all, $dateKey),
            'regColumns' => $isHistory ? AccessRegisterService::historyColumns() : AccessRegisterService::columns(),
            'regCanManage' => $canManage,
            'regTargets' => $canManage && !$isHistory ? $this->register->grantableTargets($tenantId) : [],
            'regMembers' => $members,
            'regUnits' => $canManage && !$isHistory ? $this->register->units($tenantId) : [],
            'regSwept' => $swept,
            'regError' => $error,
            'csrfToken' => Csrf::token(),
        ];
    }

    /** CSV des lignes filtrées et des colonnes choisies (1 500 lignes au plus). */
    public function export(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->deny(false);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $filters = self::filtersOf($request);
        $isHistory = $filters['view'] === self::VIEW_HISTORY;
        $all = $isHistory ? $this->register->history($tenantId) : $this->register->current($tenantId);
        $rows = array_values(array_filter($all, static fn (array $r): bool => AccessRegisterService::matches($r, $filters, $isHistory ? 'date' : 'since')));
        $catalog = $isHistory ? AccessRegisterService::historyColumns() : AccessRegisterService::columns();
        $picked = array_values(array_filter((array) $request->query('cols', []), static fn ($c): bool => is_string($c) && isset($catalog[$c])));
        $columns = [];
        foreach ($catalog as $key => [$label, $visible]) {
            if ($picked === [] ? $visible : in_array($key, $picked, true)) {
                $columns[$key] = $label;
            }
        }
        $csv = AccessRegisterService::csv($rows, $columns);
        $this->audit($tenantId, 'access_register.exported', null, count($rows) . ' ligne(s), vue ' . $filters['view']);

        return (new Response())->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="registre-droits-' . ($isHistory ? 'historique-' : '') . gmdate('Ymd-His') . '.csv"')
            ->header('Cache-Control', 'no-store')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }

    public function grant(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->deny(true, $request);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id', 0);
        try {
            $msg = $this->register->grant(
                $tenantId,
                $actorId,
                $userId,
                (string) $request->input('target', ''),
                (int) $request->input('unit_id', 0),
                (string) $request->input('valid_from', ''),
                (string) $request->input('valid_until', ''),
                (string) $request->input('reason', '')
            );
            $this->audit($tenantId, 'access_register.granted', $userId, (string) $request->input('target', ''));
            Session::flash('success', $msg);
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[registre-droits] attribution : ' . $e->getMessage());
            Session::flash('error', 'Attribution impossible. Réessayez ou ouvrez la fiche du membre.');
        }

        return Response::redirect($this->back($request));
    }

    public function update(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->deny(true, $request);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $ref = (string) $request->input('ref', '');
        try {
            $this->register->update(
                $tenantId,
                (int) Session::get('user_id'),
                $ref,
                (string) $request->input('valid_from', ''),
                (string) $request->input('valid_until', ''),
                (string) $request->input('reason', '')
            );
            $this->audit($tenantId, 'access_register.updated', null, $ref);
            Session::flash('success', 'Droit mis à jour.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[registre-droits] modification : ' . $e->getMessage());
            Session::flash('error', 'Modification impossible. Réessayez.');
        }

        return Response::redirect($this->back($request));
    }

    /** Retrait d'un droit, ou de plusieurs (« refs[] ») depuis la sélection. */
    public function revoke(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->deny(true, $request);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $refs = $this->refsOf($request);
        $reason = (string) $request->input('reason', '');
        $ok = 0;
        $errors = [];
        foreach ($refs as $ref) {
            try {
                $this->register->revoke($tenantId, $actorId, $ref, $reason);
                $this->audit($tenantId, 'access_register.revoked', null, $ref . ($reason !== '' ? ' · ' . AccessRegisterService::cleanReason($reason) : ''));
                $ok++;
            } catch (InvalidArgumentException $e) {
                $errors[$e->getMessage()] = true;
            } catch (Throwable $e) {
                error_log('[registre-droits] retrait : ' . $e->getMessage());
                $errors['Un retrait n’a pas pu être enregistré.'] = true;
            }
        }
        if ($ok > 0) {
            Session::flash('success', $ok === 1 ? 'Droit retiré. Le retrait est inscrit à l’historique.' : $ok . ' droits retirés. Les retraits sont inscrits à l’historique.');
        }
        if ($errors !== []) {
            Session::flash('error', implode(' ', array_keys($errors)));
        }
        if ($refs === []) {
            Session::flash('error', 'Choisissez au moins un droit.');
        }

        return Response::redirect($this->back($request));
    }

    /** Revue des droits : marque les lignes choisies comme revues aujourd'hui. */
    public function review(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->deny(true, $request);
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $refs = $this->refsOf($request);
        $done = $this->register->review($tenantId, (int) Session::get('user_id'), $refs);
        $this->audit($tenantId, 'access_register.reviewed', null, $done . ' ligne(s)');
        Session::flash($done > 0 ? 'success' : 'error', $done > 0
            ? ($done === 1 ? '1 droit marqué comme revu.' : $done . ' droits marqués comme revus.')
            : 'Aucun droit à marquer : les droits individuels se revoient en les retirant ou en les gardant.');

        return Response::redirect($this->back($request));
    }

    /** @return list<string> */
    private function refsOf(Request $request): array
    {
        $refs = $request->input('refs', []);
        $refs = is_array($refs) ? $refs : [];
        $one = (string) $request->input('ref', '');
        if ($one !== '') {
            $refs[] = $one;
        }

        return array_values(array_unique(array_filter($refs, static fn ($r): bool => is_string($r) && preg_match('/^(R-\d+|U-\d+-\d+|D-\d+)$/', $r) === 1)));
    }

    private function deny(bool $write, ?Request $request = null): ?Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!(int) Session::get('user_id') || $tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::allows($gate)) {
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }
        if (!self::allowedByPlan($tenantId)) {
            return PlanFeatureDenial::upgradeView(self::FEATURE, 'Standard');
        }
        if ($write) {
            if (!EffectifsLmsAccess::canManageRoles($gate)) {
                Session::flash('error', 'La gestion des droits est réservée aux responsables des accès.');

                return Response::redirect(self::url());
            }
            if ($request === null || !Csrf::validate((string) $request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée. Réessayez.');

                return Response::redirect(self::url());
            }
        }

        return null;
    }

    /** Retour sur la même vue filtrée (seuls nos paramètres connus sont repris). */
    private function back(Request $request): string
    {
        $raw = (string) $request->input('return_query', '');
        parse_str($raw, $q);
        $keep = [];
        foreach (['vue', 'q', 'nature', 'etat', 'annee'] as $k) {
            if (isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '') {
                $keep[$k] = mb_substr($q[$k], 0, 120);
            }
        }

        return self::url() . ($keep !== [] ? '?' . http_build_query($keep) : '');
    }

    private function audit(int $tenantId, string $action, ?int $userId, string $detail): void
    {
        try {
            (new \App\Services\Audit\AuditService())->log(
                $action,
                $tenantId,
                (int) Session::get('user_id') ?: null,
                'access_register',
                $userId,
                null,
                $detail
            );
        } catch (Throwable $e) {
            error_log('[registre-droits] audit : ' . $e->getMessage());
        }
    }
}
