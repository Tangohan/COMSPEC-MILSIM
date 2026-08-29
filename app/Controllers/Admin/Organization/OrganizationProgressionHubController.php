<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\Rbac\Gate;
use PDO;

/**
 * Hub Progression & carrière (BO) — lot 1 : tableau de bord + liens vers les sous-modules.
 */
final class OrganizationProgressionHubController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?Gate $gate = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->gate ??= Container::get(Gate::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!$this->canView()) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour la progression des personnels.');

            return Response::redirect(url('back-office/organisation-effectifs'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $stats = $this->loadStats($tenantId);

        return Response::view('layout.main', [
            'title' => 'Progression & carrière',
            'content' => 'admin.organization.progression_hub',
            'progressionStats' => $stats,
            'canConfigure' => $this->canConfigure(),
            'canValidate' => $this->gate->allows('personnel.progression.validate') || $this->canConfigure(),
            'canCallsign' => $this->gate->allows('personnel.callsign.manage') || $this->canConfigure(),
        ]);
    }

    private function canView(): bool
    {
        return $this->gate->allows('personnel.progression.view')
            || $this->gate->allows('personnel.progression.manage')
            || $this->gate->allows('personnel.progression.configure')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.access');
    }

    private function canConfigure(): bool
    {
        return $this->gate->allows('personnel.progression.configure')
            || $this->gate->allows('personnel.progression.manage')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.access');
    }

    /** @return array<string, int|bool> */
    private function loadStats(int $tenantId): array
    {
        $pdo = \App\Core\Database::getPdo();
        $count = static function (PDO $pdo, string $sql, array $params): int {
            try {
                $st = $pdo->prepare($sql);
                $st->execute($params);
                return (int) $st->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };

        $schema = false;
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_progression_tracks' LIMIT 1"
            );
            $schema = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $schema = false;
        }

        return [
            'schema_ready' => $schema ? 1 : 0,
            'tracks' => $schema ? $count($pdo, 'SELECT COUNT(*) FROM personnel_progression_tracks WHERE tenant_id = ?', [$tenantId]) : 0,
            'published_tracks' => $schema ? $count($pdo, "SELECT COUNT(*) FROM personnel_progression_tracks WHERE tenant_id = ? AND status = 'PUBLISHED'", [$tenantId]) : 0,
            'pending_requests' => $schema ? $count($pdo, "SELECT COUNT(*) FROM personnel_progression_requests WHERE tenant_id = ? AND status IN ('ELIGIBLE','WAITING_VALIDATION')", [$tenantId]) : 0,
            'sequences' => $count($pdo, 'SELECT COUNT(*) FROM organization_callsign_sequences WHERE tenant_id = ?', [$tenantId]),
            'holds' => $schema ? $count($pdo, 'SELECT COUNT(*) FROM personnel_progression_holds WHERE tenant_id = ? AND (ends_at IS NULL OR ends_at > NOW())', [$tenantId]) : 0,
        ];
    }
}
