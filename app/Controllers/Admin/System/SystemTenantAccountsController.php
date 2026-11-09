<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;

/**
 * Annuaire des communautés › comptes d’UNE communauté (lecture seule, GET).
 * Le nombre affiché dans l’annuaire et le nombre de lignes ici viennent de la même définition
 * (UserRepository::sqlPlatformAccountFilter).
 */
final class SystemTenantAccountsController
{
    public const PER_PAGE = 25;
    public const SORTS = ['name', 'role', 'status', 'login', 'created'];

    public function __construct(
        private ?TenantRepository $tenants = null,
        private ?UserRepository $users = null,
    ) {
        $this->tenants ??= new TenantRepository();
        $this->users ??= new UserRepository();
    }

    /** @return array<string, string> filtre => libellé */
    public static function filterLabels(): array
    {
        return [
            'all' => 'Tous les comptes',
            'active' => 'Comptes actifs',
            'pending' => 'En attente de validation',
            'inactive' => 'Comptes inactifs',
            'recent' => 'Connectés ces 30 derniers jours',
            'dormant' => 'Non connectés depuis plus de 30 jours',
            'never' => 'Jamais connectés',
        ];
    }

    public function index(Request $request, array $params = []): Response
    {
        // Droit revérifié à chaque requête, en plus du middleware de la route.
        if (!Session::get('user_id') || Gate::getInstance()->deny('admin.system')) {
            Session::flash('error', 'Accès réservé à l’administration du site.');

            return Response::redirect(url('dashboard'));
        }
        $tenantId = (int) ($params['id'] ?? 0);
        $tenant = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($tenant === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }

        $filter = UserRepository::normalizePlatformAccountFilter($request->query('filter'));
        $search = trim((string) $request->query('q', ''));
        $search = mb_substr($search, 0, 100);
        $sort = (string) $request->query('sort', 'name');
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'name';
        }
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ((string) $request->query('export', '') === 'csv') {
            return $this->csv($tenant, $tenantId, $filter, $search, $sort, $dir);
        }

        $total = $this->users->countPlatformAccounts($tenantId, $filter, $search);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $rows = $this->users->listPlatformAccounts($tenantId, $filter, $search, $sort, $dir, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $counts = [];
        foreach (array_keys(self::filterLabels()) as $f) {
            $counts[$f] = $this->users->countPlatformAccounts($tenantId, $f, null);
        }
        $labels = self::filterLabels();

        return Response::view('layout.main', [
            'title' => 'Comptes de ' . (string) ($tenant['name'] ?? ''),
            'content' => 'admin.system.tenant_accounts',
            'accTenant' => ['id' => $tenantId, 'name' => (string) ($tenant['name'] ?? '')],
            'accRows' => $rows,
            'accFilter' => $filter,
            'accFilterLabel' => $labels[$filter],
            'accFilterLabels' => $labels,
            'accCounts' => $counts,
            'accSearch' => $search,
            'accSort' => $sort,
            'accDir' => $dir,
            'accPage' => $page,
            'accPages' => $pages,
            'accTotal' => $total,
            'backOfficePageCss' => ['platform-admin.css'],
        ]);
    }

    /** @param array<string, mixed> $tenant */
    private function csv(array $tenant, int $tenantId, string $filter, string $search, string $sort, string $dir): Response
    {
        $rows = $this->users->listPlatformAccounts($tenantId, $filter, $search, $sort, $dir, 1000, 0);
        $out = [['Nom', 'Indicatif', 'Matricule', 'E-mail', 'Rôle', 'Statut', 'Dernière connexion', 'Création']];
        foreach ($rows as $r) {
            $out[] = [
                (string) ($r['display_name'] ?? ''), (string) ($r['callsign'] ?? ''), (string) ($r['member_number'] ?? ''),
                (string) ($r['email'] ?? ''), (string) ($r['role_name'] ?? ''), (string) ($r['status'] ?? ''),
                self::date((string) ($r['last_login_at'] ?? '')), self::date((string) ($r['created_at'] ?? '')),
            ];
        }
        $csv = "\xEF\xBB\xBF";
        foreach ($out as $line) {
            $csv .= implode(';', array_map([self::class, 'csvCell'], $line)) . "\r\n";
        }
        $slug = preg_replace('/[^a-z0-9-]+/i', '', (string) ($tenant['slug'] ?? '')) ?: 'communaute';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="comptes-' . $slug . '-' . $filter . '-' . date('Y-m-d') . '.csv"')
            ->setBody($csv);
    }

    public static function csvCell(string $cell): string
    {
        // = + - @ en tête : neutralisé pour le tableur (injection de formule).
        if ($cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $cell = "'" . $cell;
        }

        return '"' . str_replace('"', '""', $cell) . '"';
    }

    private static function date(string $raw): string
    {
        $ts = $raw !== '' ? strtotime($raw) : false;

        return $ts ? date('d/m/Y H:i', $ts) : '';
    }
}
