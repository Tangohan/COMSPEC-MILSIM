<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SeniorityRepository;
use App\Services\Auth\AuthService;
use App\Services\Personnel\SeniorityDossierInferenceSyncService;
use App\Services\Personnel\SeniorityEnrollmentBootstrapService;
use App\Services\Personnel\SeniorityTenantDefaultsService;

final class OrganizationSeniorityAdminController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?SeniorityRepository $seniorityRepository = null,
        private ?SeniorityTenantDefaultsService $defaultsService = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->seniorityRepository ??= Container::get(SeniorityRepository::class);
        $this->defaultsService ??= new SeniorityTenantDefaultsService($this->seniorityRepository);
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $schemaReady = $this->seniorityRepository->schemaReady();
        $definitions = $schemaReady ? $this->seniorityRepository->listAllDefinitionsForTenant($tenantId) : [];
        $stats = $this->buildDefinitionStats($definitions);

        return Response::view('layout.main', [
            'title' => 'Indicateurs d’ancienneté',
            'content' => 'admin.organization.seniority',
            'senioritySchemaReady' => $schemaReady,
            'seniorityDefinitions' => $definitions,
            'seniorityDefinitionStats' => $stats,
            'seniorityCsrf' => Csrf::token(),
        ]);
    }

    public function seedDefaults(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez depuis le formulaire.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!$this->seniorityRepository->schemaReady()) {
            Session::flash('error', 'Le référentiel d’ancienneté n’est pas encore disponible sur cette installation.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }

        $result = $this->defaultsService->ensureStandardPack($tenantId);
        if ($result['created'] > 0) {
            Session::flash(
                'success',
                $result['created'] === 1
                    ? 'Un nouvel indicateur standard a été ajouté.'
                    : (string) $result['created'] . ' indicateurs standards ont été ajoutés.'
            );
        } else {
            Session::flash('success', 'Les indicateurs standards étaient déjà en place. Aucune ligne supplémentaire n’a été créée.');
        }

        return Response::redirect(url('back-office/organisation/anciennete'));
    }

    public function syncAllPersonnel(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez depuis le formulaire.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!$this->seniorityRepository->schemaReady()) {
            Session::flash('error', 'Le référentiel d’ancienneté n’est pas encore disponible sur cette installation.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }

        $bootstrap = Container::get(SeniorityEnrollmentBootstrapService::class);
        $stats = $bootstrap->syncTenureCommunityForAllActiveMembers($tenantId);

        $touched = $stats['inserted'] + $stats['updated'];
        $idle = $stats['unchanged'] + $stats['skipped_manual'];
        $msg = sprintf(
            'Synchronisation terminée : %d membre(s) actif(s) parcouru(s). Ancienneté dans la communauté recalculée pour %d profil(s) (%d nouvelle(s) période(s), %d mise(s) à jour). Sans date d’enrôlement ou d’entrée repérable sur le dossier : %d. Laissé inchangé (déjà aligné ou saisi autrement par l’encadrement) : %d.',
            $stats['members'],
            $touched,
            $stats['inserted'],
            $stats['updated'],
            $stats['skipped_no_date'],
            $idle
        );
        if (($stats['insert_failed'] ?? 0) > 0) {
            $msg .= ' Certaines écritures n’ont pas abouti ; réessayez ou contactez le support si le problème persiste.';
        }
        Session::flash('success', $msg);

        return Response::redirect(url('back-office/organisation/anciennete'));
    }

    public function syncDossierInference(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez depuis le formulaire.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!$this->seniorityRepository->schemaReady()) {
            Session::flash('error', 'Le référentiel d’ancienneté n’est pas encore disponible sur cette installation.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }

        $sync = Container::get(SeniorityDossierInferenceSyncService::class);
        $stats = $sync->syncForAllActiveMembers($tenantId);

        $touched = $stats['inserted'] + $stats['updated'] + $stats['cleared'];
        $msg = sprintf(
            'Complément depuis le dossier terminé : %d membre(s) actif(s) parcouru(s). Périodes créées ou mises à jour : %d (dont %d nouvelle(s), %d mise(s) à jour, %d retirée(s) faute de repère). Laissé inchangé (déjà saisi par l’encadrement ou sans indicateur) : %d. Repères manquants sur le dossier (indicateur absent du catalogue) : %d.',
            $stats['members'],
            $touched,
            $stats['inserted'],
            $stats['updated'],
            $stats['cleared'],
            $stats['skipped_manual'],
            $stats['skipped_no_definition']
        );
        if (($stats['insert_failed'] ?? 0) > 0) {
            $msg .= ' Certaines écritures n’ont pas abouti ; réessayez ou contactez le support si le problème persiste.';
        }
        if (($stats['unchanged'] ?? 0) > 0) {
            $msg .= sprintf(' Dates déjà alignées pour %d ligne(s).', $stats['unchanged']);
        }
        Session::flash('success', $msg);

        return Response::redirect(url('back-office/organisation/anciennete'));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez depuis le formulaire.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!$this->seniorityRepository->schemaReady()) {
            Session::flash('error', 'Le référentiel d’ancienneté n’est pas encore disponible sur cette installation.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }

        $rows = $request->input('rows');
        if (!is_array($rows)) {
            Session::flash('error', 'Données invalides.');

            return Response::redirect(url('back-office/organisation/anciennete'));
        }
        $updated = 0;
        foreach ($rows as $idStr => $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $id = (int) $idStr;
            if ($id < 1) {
                continue;
            }
            $active = !empty($payload['active']);
            $visible = !empty($payload['visible']);
            $sort = (int) ($payload['sort'] ?? 0);
            if ($this->seniorityRepository->updateDefinitionDisplay($tenantId, $id, $active, $visible, $sort)) {
                ++$updated;
            }
        }
        Session::flash('success', $updated > 0 ? 'Vos modifications ont été enregistrées.' : 'Aucune modification enregistrée.');

        return Response::redirect(url('back-office/organisation/anciennete'));
    }

    /**
     * @param list<array<string,mixed>> $definitions
     * @return array<string,int>
     */
    private function buildDefinitionStats(array $definitions): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'visible' => 0,
            'inactive' => 0,
            'hidden' => 0,
        ];
        foreach ($definitions as $def) {
            $stats['total']++;
            if (!empty($def['is_active'])) {
                $stats['active']++;
            } else {
                $stats['inactive']++;
            }
            if (!empty($def['is_visible'])) {
                $stats['visible']++;
            } else {
                $stats['hidden']++;
            }
        }

        return $stats;
    }
}
