<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\Personnel\CallsignSequenceService;
use App\Services\Rbac\Gate;

final class OrganizationCallsignSequencesController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?CallsignSequenceService $callsignService = null,
        private ?Gate $gate = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->callsignService ??= Container::get(CallsignSequenceService::class);
        $this->gate ??= Container::get(Gate::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour gérer les indicatifs.');

            return Response::redirect(url('back-office/organisation-effectifs'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $schemaReady = $this->callsignService->schemaReady();
        $sequences = $schemaReady ? $this->callsignService->listSequences($tenantId) : [];
        $previews = [];
        foreach ($sequences as $seq) {
            $id = (int) ($seq['id'] ?? 0);
            if ($id > 0) {
                $previews[$id] = $this->callsignService->previewNext($tenantId, $id);
            }
        }

        return Response::view('layout.main', [
            'title' => 'Règles d’indicatifs',
            'content' => 'admin.organization.callsign_sequences',
            'callsignSchemaReady' => $schemaReady,
            'callsignSequences' => $sequences,
            'callsignPreviews' => $previews,
            'callsignModes' => CallsignSequenceService::MODES,
            'callsignCsrf' => Csrf::token(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$this->authService->check() || !$this->canManage()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/organisation/indicatifs'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !$this->callsignService->schemaReady()) {
            Session::flash('error', 'Module indicatifs indisponible.');

            return Response::redirect(url('back-office/organisation/indicatifs'));
        }

        $ranges = $this->parseRanges((string) $request->input('reserved_ranges_text', ''));
        $id = $this->callsignService->createSequence($tenantId, [
            'name' => (string) $request->input('name', ''),
            'code' => (string) $request->input('code', ''),
            'mode' => (string) $request->input('mode', 'PREFIX_NUMERIC'),
            'prefix' => (string) $request->input('prefix', ''),
            'suffix' => (string) $request->input('suffix', ''),
            'pattern' => (string) $request->input('pattern', '{PREFIX}-{NUMBER:02}'),
            'start_number' => (int) $request->input('start_number', 10),
            'current_number' => (int) $request->input('start_number', 10),
            'increment_by' => (int) $request->input('increment_by', 1),
            'padding' => (int) $request->input('padding', 2),
            'reuse_released' => (bool) $request->input('reuse_released'),
            'allow_manual_override' => (bool) $request->input('allow_manual_override', 1),
            'unit_change_policy' => (string) $request->input('unit_change_policy', 'keep'),
            'is_default' => (bool) $request->input('is_default'),
            'is_active' => true,
        ], $ranges);

        Session::flash(
            $id !== null ? 'success' : 'error',
            $id !== null ? 'Séquence d’indicatif créée.' : 'Impossible de créer la séquence (nom/code requis ou code déjà pris).'
        );

        return Response::redirect(url('back-office/organisation/indicatifs'));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!$this->authService->check() || !$this->canManage()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/organisation/indicatifs'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId < 1 || $id < 1 || !$this->callsignService->schemaReady()) {
            Session::flash('error', 'Séquence introuvable.');

            return Response::redirect(url('back-office/organisation/indicatifs'));
        }

        $ranges = $this->parseRanges((string) $request->input('reserved_ranges_text', ''));
        $ok = $this->callsignService->updateSequence($tenantId, $id, [
            'name' => (string) $request->input('name', ''),
            'code' => (string) $request->input('code', ''),
            'mode' => (string) $request->input('mode', 'PREFIX_NUMERIC'),
            'prefix' => (string) $request->input('prefix', ''),
            'suffix' => (string) $request->input('suffix', ''),
            'pattern' => (string) $request->input('pattern', '{PREFIX}-{NUMBER:02}'),
            'start_number' => (int) $request->input('start_number', 1),
            'increment_by' => (int) $request->input('increment_by', 1),
            'padding' => (int) $request->input('padding', 2),
            'reuse_released' => (bool) $request->input('reuse_released'),
            'allow_manual_override' => (bool) $request->input('allow_manual_override'),
            'unit_change_policy' => (string) $request->input('unit_change_policy', 'keep'),
            'is_default' => (bool) $request->input('is_default'),
            'is_active' => (bool) $request->input('is_active', 1),
        ], $ranges);

        Session::flash($ok ? 'success' : 'error', $ok ? 'Séquence mise à jour.' : 'Échec de la mise à jour.');

        return Response::redirect(url('back-office/organisation/indicatifs'));
    }

    private function canManage(): bool
    {
        return $this->gate->allows('personnel.callsign.manage')
            || $this->gate->allows('personnel.progression.configure')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.access');
    }

    /**
     * Format texte : une plage par ligne « 1-9|Commandement|command »
     *
     * @return list<array{range_start: int, range_end: int, label: string, purpose: string}>
     */
    private function parseRanges(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $bounds = preg_split('/\s*[-–:]\s*/', $parts[0] ?? '') ?: [];
            $start = (int) ($bounds[0] ?? 0);
            $end = (int) ($bounds[1] ?? $start);
            if ($start < 1 || $end < $start) {
                continue;
            }
            $out[] = [
                'range_start' => $start,
                'range_end' => $end,
                'label' => $parts[1] ?? ('Réservé ' . $start . '-' . $end),
                'purpose' => $parts[2] ?? 'command',
            ];
        }

        return $out;
    }
}
