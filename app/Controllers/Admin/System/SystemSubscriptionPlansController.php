<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SubscriptionPlanRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;

/**
 * Gestion des paliers d’accès (table subscription_plans) — super-admin uniquement.
 */
final class SystemSubscriptionPlansController
{
    public function __construct(
        private ?SubscriptionPlanRepository $plans = null,
        private ?AuditService $audit = null,
    ) {
        $this->plans ??= new SubscriptionPlanRepository();
        $this->audit ??= new AuditService();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->plans->tableExists()) {
            Session::flash('error', 'La table des formules d’accès n’est pas disponible (migrations).');

            return Response::redirect(url('admin'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.subscription_plans_index',
            'title' => 'Formules d’accès (paliers)',
            'subscriptionPlansRows' => $this->plans->allOrdered(),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (!$this->plans->tableExists()) {
            Session::flash('error', 'La table des formules d’accès n’est pas disponible.');

            return Response::redirect(url('admin'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->plans->findById($id) : null;
        if ($row === null) {
            Session::flash('error', 'Formule introuvable.');

            return Response::redirect(url('admin/system/subscription-plans'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.subscription_plans_form',
            'title' => 'Modifier la formule — ' . (string) ($row['name'] ?? ''),
            'subscriptionPlanRow' => $row,
            'subscriptionPlanFormAction' => url('admin/system/subscription-plans/' . $id . '/update'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/subscription-plans'));
        }
        if (!$this->plans->tableExists()) {
            return Response::redirect(url('admin'));
        }
        $id = (int) ($params['id'] ?? 0);
        $before = $id > 0 ? $this->plans->findById($id) : null;
        if ($before === null) {
            Session::flash('error', 'Formule introuvable.');

            return Response::redirect(url('admin/system/subscription-plans'));
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '' || strlen($name) > 100) {
            Session::flash('error', 'Le libellé est obligatoire (100 caractères maximum).');

            return Response::redirect(url('admin/system/subscription-plans/' . $id . '/edit'));
        }
        $sortOrder = (int) $request->input('sort_order', 0);

        $existingFeatures = [];
        $rawExisting = (string) ($before['features_json'] ?? '');
        if ($rawExisting !== '') {
            $decoded = json_decode($rawExisting, true);
            if (is_array($decoded)) {
                $existingFeatures = $decoded;
            }
        }
        $features = SubscriptionPlanFeaturesCatalog::buildFeaturesFromForm($request->all(), $existingFeatures);
        $featuresJson = json_encode($features, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $limitsRaw = trim((string) $request->input('limits_json', ''));
        $limitsJson = $this->normalizeJsonField($limitsRaw, 'Quotas avancés');
        if ($limitsJson === false) {
            return Response::redirect(url('admin/system/subscription-plans/' . $id . '/edit'));
        }

        $stripeM = $this->nullableExternalId($request->input('stripe_price_id_monthly'));
        $stripeY = $this->nullableExternalId($request->input('stripe_price_id_yearly'));
        $paypalM = $this->nullableExternalId($request->input('paypal_plan_id_monthly'));
        $paypalY = $this->nullableExternalId($request->input('paypal_plan_id_yearly'));
        if ($stripeM === false || $stripeY === false || $paypalM === false || $paypalY === false) {
            Session::flash('error', 'Les identifiants de paiement ne peuvent dépasser 100 caractères.');

            return Response::redirect(url('admin/system/subscription-plans/' . $id . '/edit'));
        }

        $payload = [
            'name' => $name,
            'sort_order' => $sortOrder,
            'features_json' => $featuresJson,
            'limits_json' => $limitsJson,
            'stripe_price_id_monthly' => $stripeM,
            'stripe_price_id_yearly' => $stripeY,
            'paypal_plan_id_monthly' => $paypalM,
            'paypal_plan_id_yearly' => $paypalY,
        ];
        $this->plans->update($id, $payload);

        $after = $this->plans->findById($id) ?? [];
        $actorId = Session::get('user_id');
        $actorId = is_numeric($actorId) ? (int) $actorId : null;
        if ($actorId !== null && $actorId < 1) {
            $actorId = null;
        }
        $this->audit->logChange(
            AuditAction::SUBSCRIPTION_PLAN_UPDATED,
            null,
            $actorId,
            'subscription_plan',
            $id,
            $this->auditSnapshot($before),
            $this->auditSnapshot($after),
        );

        Session::flash('success', 'Formule enregistrée.');

        return Response::redirect(url('admin/system/subscription-plans'));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return [
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'features_json' => (string) ($row['features_json'] ?? ''),
            'limits_json' => (string) ($row['limits_json'] ?? ''),
            'stripe_price_id_monthly' => (string) ($row['stripe_price_id_monthly'] ?? ''),
            'stripe_price_id_yearly' => (string) ($row['stripe_price_id_yearly'] ?? ''),
            'paypal_plan_id_monthly' => (string) ($row['paypal_plan_id_monthly'] ?? ''),
            'paypal_plan_id_yearly' => (string) ($row['paypal_plan_id_yearly'] ?? ''),
        ];
    }

    /** @return string|null|false false = flash error already set */
    private function normalizeJsonField(string $raw, string $label): string|null|false
    {
        if ($raw === '') {
            return null;
        }
        json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Session::flash('error', $label . ' : format invalide.');

            return false;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Session::flash('error', $label . ' : structure invalide.');

            return false;
        }

        $enc = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        if ($enc === false) {
            Session::flash('error', $label . ' : impossible d’enregistrer.');

            return false;
        }

        return $enc;
    }

    /** @return string|null|false */
    private function nullableExternalId(mixed $v): string|null|false
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (strlen($s) > 100) {
            return false;
        }

        return $s;
    }
}
