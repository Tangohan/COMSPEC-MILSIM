<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\RegistryDirectory;

/**
 * Annuaire public (/communities) côté administration du site (/admin/system/annuaire) :
 * épingler des communautés (ordre, durée, note interne) et lire la tendance des 30 derniers jours.
 * Réservé à l'administrateur de la plateforme ; la gestion d'une communauté n'y a pas accès.
 */
final class SystemRegistryController
{
    private const BASE = 'admin/system/annuaire';

    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenants,
        private ?RegistryDirectory $directory = null,
    ) {}

    private function directory(): RegistryDirectory
    {
        return $this->directory ??= new RegistryDirectory();
    }

    public function index(Request $request, array $params = []): Response
    {
        $listed = $this->tenants->listForRegistry();
        $pins = $this->directory()->pins();
        $active = $this->directory()->activePinsByTenant();
        $signals = $this->directory()->signals(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $listed));
        $rows = RegistryDirectory::decorate($listed, $active, $signals);

        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        // Toutes les épingles enregistrées, même expirées ou sur une communauté retirée de l'annuaire (sans effet public).
        $pinRows = [];
        foreach ($pins as $pin) {
            $tid = $pin['tenant_id'];
            $tenant = $byId[$tid] ?? $this->tenants->findById($tid);
            $pinRows[] = [
                'pin' => $pin,
                'name' => (string) ($tenant['name'] ?? ('Communauté #' . $tid)),
                'slug' => (string) ($tenant['slug'] ?? ''),
                'exists' => $tenant !== null,
                'listed' => isset($byId[$tid]),
                'active' => RegistryDirectory::isPinActive($pin),
            ];
        }

        $trending = array_values(array_filter($rows, static fn (array $r): bool => (int) ($r['registry_trending_rank'] ?? 0) > 0));
        usort($trending, static fn (array $a, array $b): int => $a['registry_trending_rank'] <=> $b['registry_trending_rank']);

        return Response::view('layout.main', [
            'title' => 'Annuaire public : épinglage et tendance',
            'content' => 'admin.system.registry_pins',
            'registryRows' => $rows,
            'registryPinRows' => $pinRows,
            'registryTrending' => $trending,
            'registryPinsBase' => self::BASE,
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'admin-registry.css'],
        ]);
    }

    public function pin(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $request->input('tenant_id');
        $listedIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $this->tenants->listForRegistry());
        if (!in_array($tenantId, $listedIds, true)) {
            // Jamais d'exposition d'une communauté qui a choisi de ne pas apparaître dans l'annuaire.
            Session::flash('error', 'Cette communauté n’apparaît pas dans l’annuaire public : elle ne peut pas être épinglée.');

            return Response::redirect(url(self::BASE));
        }
        $until = self::untilFromRequest((string) $request->input('duration', ''), (string) $request->input('until', ''));
        $ok = $this->directory()->pin(
            $tenantId,
            (int) $request->input('position', 1),
            $until,
            (string) $request->input('note', ''),
            (int) $actor['id']
        );
        Session::flash($ok ? 'success' : 'error', $ok ? 'Épinglage enregistré.' : 'Épinglage impossible.');

        return Response::redirect(url(self::BASE) . '#epinglees');
    }

    public function unpin(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, rechargez la page.');

            return Response::redirect(url(self::BASE));
        }
        $ok = $this->directory()->unpin((int) $request->input('tenant_id'));
        Session::flash($ok ? 'success' : 'error', $ok ? 'Communauté désépinglée.' : 'Aucune épingle sur cette communauté.');

        return Response::redirect(url(self::BASE) . '#epinglees');
    }

    /**
     * Date de fin (incluse) depuis le choix de durée : vide = sans limite, « 7 » = 7 jours, « date » = date saisie.
     */
    public static function untilFromRequest(string $duration, string $date, ?\DateTimeImmutable $today = null): ?string
    {
        $today ??= new \DateTimeImmutable('today');
        $duration = trim($duration);
        if ($duration === 'date') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));

            return $d !== false && $d >= $today ? $d->format('Y-m-d') : null;
        }
        $days = (int) $duration;
        if ($days < 1) {
            return null;
        }

        return $today->modify('+' . min(365, $days) . ' days')->format('Y-m-d');
    }
}
