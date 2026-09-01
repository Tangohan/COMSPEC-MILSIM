<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Core\Gate;
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
        $this->gate ??= Gate::getInstance();
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
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Organisation',
            'boPageTitle' => 'Progression & carrière',
            'boPageKicker' => 'PERSONNEL · CARRIÈRE',
            'boPageSubtitle' => 'Parcours, validations, indicatifs et capacité opérationnelle.',
            'progressionStats' => $stats,
            'canConfigure' => $this->canConfigure(),
            'canValidate' => $this->gate->allows('personnel.progression.validate') || $this->canConfigure(),
            'canCallsign' => $this->gate->allows('personnel.callsign.manage') || $this->canConfigure(),
            'showPortalFooter' => false,
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
        try {
            $pdo = \App\Core\Database::getPdo();
        } catch (\Throwable) {
            return [
                'schema_ready' => 0,
                'axes_schema_ready' => 0,
                'tracks' => 0,
                'published_tracks' => 0,
                'pending_requests' => 0,
                'sequences' => 0,
                'holds' => 0,
                'non_current_quals' => 0,
                'billets' => 0,
            ];
        }
        $count = static function (PDO $pdo, string $sql, array $params): int {
            try {
                $st = $pdo->prepare($sql);
                $st->execute($params);
                return (int) $st->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };

        $tableExists = static function (PDO $pdo, string $table): bool {
            try {
                $st = $pdo->prepare(
                    'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
                );
                $st->execute([$table]);

                return (bool) $st->fetchColumn();
            } catch (\Throwable) {
                return false;
            }
        };

        $schema = $tableExists($pdo, 'personnel_progression_tracks');
        $axesSchema = $tableExists($pdo, 'orbat_billets');
        $sequencesSchema = $tableExists($pdo, 'organization_callsign_sequences');

        return [
            'schema_ready' => $schema ? 1 : 0,
            'axes_schema_ready' => $axesSchema ? 1 : 0,
            'tracks' => $schema ? $count($pdo, 'SELECT COUNT(*) FROM personnel_progression_tracks WHERE tenant_id = ?', [$tenantId]) : 0,
            'published_tracks' => $schema ? $count($pdo, "SELECT COUNT(*) FROM personnel_progression_tracks WHERE tenant_id = ? AND status = 'PUBLISHED'", [$tenantId]) : 0,
            'pending_requests' => $schema ? $count($pdo, "SELECT COUNT(*) FROM personnel_progression_requests WHERE tenant_id = ? AND status IN ('ELIGIBLE','WAITING_VALIDATION')", [$tenantId]) : 0,
            'sequences' => $sequencesSchema ? $count($pdo, 'SELECT COUNT(*) FROM organization_callsign_sequences WHERE tenant_id = ?', [$tenantId]) : 0,
            'holds' => $schema ? $count($pdo, 'SELECT COUNT(*) FROM personnel_progression_holds WHERE tenant_id = ? AND (ends_at IS NULL OR ends_at > NOW())', [$tenantId]) : 0,
            'non_current_quals' => $axesSchema ? $count($pdo, "SELECT COUNT(*) FROM personnel_qualifications WHERE tenant_id = ? AND currency_status = 'NON_CURRENT'", [$tenantId]) : 0,
            'billets' => $axesSchema ? $count($pdo, 'SELECT COUNT(*) FROM orbat_billets WHERE tenant_id = ? AND is_active = 1', [$tenantId]) : 0,
        ];
    }
}
