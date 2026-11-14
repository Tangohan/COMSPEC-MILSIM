<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Workflow\ApproverResolver;
use App\Services\Workflow\WorkflowCircuits;
use App\Services\Workflow\WorkflowEngine;
use App\Support\EffectifsLmsAccess;
use App\Support\PlanFeatureDenial;
use Throwable;

/**
 * Effectifs › Circuits de validation : la communauté compose les étapes d'avis de chaque type de demande (lot 4 du
 * moteur de circuits, module de formule « workflow_circuits »).
 *
 * Lecture : pilotage des effectifs. Écriture : gestion des accès (canManageRoles). La page est servie par
 * EffectifsWorkspaceController::circuits ; les étapes donnent un avis, la décision reste aux écrans existants.
 */
final class WorkflowCircuitsController
{
    public function __construct(private ?WorkflowCircuits $circuits = null, private ?ApproverResolver $resolver = null)
    {
        $this->circuits ??= Container::get(WorkflowCircuits::class);
        $this->resolver ??= Container::get(ApproverResolver::class);
    }

    public static function url(): string
    {
        return effectifs_workspace_url('circuits');
    }

    /**
     * Données de la page.
     *
     * @return array<string, mixed>
     */
    public function pageData(int $tenantId): array
    {
        $custom = [];
        $members = [];
        $ready = false;
        try {
            $ready = $this->circuits->ready();
            $custom = $this->circuits->customFor($tenantId);
            $members = $this->resolver->memberLabels($tenantId);
        } catch (Throwable $e) {
            error_log('[circuits] lecture : ' . $e->getMessage());
        }
        asort($members, SORT_NATURAL | SORT_FLAG_CASE);
        $subjects = [];
        foreach (WorkflowCircuits::SUBJECTS as $subject) {
            $subjects[] = [
                'key' => $subject,
                'label' => WorkflowEngine::subjectLabel($subject),
                'custom' => array_key_exists($subject, $custom),
                'steps' => $custom[$subject] ?? WorkflowCircuits::defaultSteps($subject),
                'default_steps' => WorkflowCircuits::defaultSteps($subject),
            ];
        }

        return [
            'circuitSubjects' => $subjects,
            'circuitMembers' => $members,
            'circuitReady' => $ready,
            'circuitCanManage' => EffectifsLmsAccess::canManageRoles(Gate::getInstance()),
            'circuitMaxSteps' => WorkflowCircuits::MAX_STEPS,
            'csrfToken' => Csrf::token(),
        ];
    }

    /** POST effectifs/circuits : enregistre (ou remet par défaut) le circuit d'un type de demande. */
    public function save(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(self::url());
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', 'Seuls les responsables des accès composent les circuits de validation.');

            return Response::redirect(self::url());
        }
        if (!$this->circuits->allowedByPlan($tenantId)) {
            return PlanFeatureDenial::upgradeView(WorkflowCircuits::FEATURE, 'Pro');
        }
        if (!$this->circuits->ready()) {
            Session::flash('error', 'Les circuits personnalisés arrivent avec la prochaine mise à jour du site (étape workflow_circuits).');

            return Response::redirect(self::url());
        }
        $subject = (string) $request->input('subject', '');
        if (!in_array($subject, WorkflowCircuits::SUBJECTS, true)) {
            Session::flash('error', 'Type de demande inconnu.');

            return Response::redirect(self::url());
        }
        $label = WorkflowEngine::subjectLabel($subject);
        try {
            if ((string) $request->input('action', '') === 'reset') {
                $this->circuits->reset($tenantId, $subject);
                Session::flash('success', $label . ' : circuit par défaut rétabli.');

                return Response::redirect(self::url() . '#circuit-' . $subject);
            }
            $members = $this->resolver->memberLabels($tenantId);
            $steps = [];
            $kinds = $request->input('kind', []);
            $users = $request->input('user', []);
            $kinds = is_array($kinds) ? array_values($kinds) : [];
            $users = is_array($users) ? array_values($users) : [];
            foreach ($kinds as $i => $kind) {
                $kind = is_string($kind) ? $kind : '';
                if ($kind === WorkflowCircuits::KIND_USER) {
                    $uid = (int) ($users[$i] ?? 0);
                    if (!isset($members[$uid])) {
                        Session::flash('error', 'Étape ' . ($i + 1) . ' : choisissez un membre de la communauté.');

                        return Response::redirect(self::url() . '#circuit-' . $subject);
                    }
                    $steps[] = ['kind' => $kind, 'user_id' => $uid];
                } elseif ($kind === WorkflowCircuits::KIND_COMMANDER || $kind === WorkflowCircuits::KIND_UPPER) {
                    $steps[] = ['kind' => $kind];
                }
            }
            $this->circuits->save($tenantId, $subject, $steps, $userId);
            $this->audit($tenantId, $subject, count(WorkflowCircuits::normalize($steps)));
            Session::flash('success', $steps === []
                ? $label . ' : aucune étape d’avis, la demande va directement aux responsables.'
                : $label . ' : circuit enregistré. Il s’applique aux nouvelles demandes.');
        } catch (Throwable $e) {
            error_log('[circuits] enregistrement : ' . $e->getMessage());
            Session::flash('error', 'Le circuit n’a pas pu être enregistré. Réessayez dans un instant.');
        }

        return Response::redirect(self::url() . '#circuit-' . $subject);
    }

    private function audit(int $tenantId, string $subject, int $count): void
    {
        try {
            (new \App\Services\Audit\AuditService())->log(
                'workflow_circuits.saved',
                $tenantId,
                (int) Session::get('user_id') ?: null,
                'workflow_circuit',
                null,
                null,
                $subject . ' · ' . $count . ' étape(s)'
            );
        } catch (Throwable $e) {
            error_log('[circuits] audit : ' . $e->getMessage());
        }
    }
}
