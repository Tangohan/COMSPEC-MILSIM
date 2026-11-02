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
use App\Services\Billing\PlanOfferSummary;
use App\Services\Billing\PlanPaymentService;
use App\Services\Billing\SubscriptionPlanAdminService;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;

/**
 * Gestion des paliers d’accès (table subscription_plans) — super-admin uniquement.
 * Tableau comparatif des formules, fiche par formule (onglets par groupe de paramètres), aperçu de la page publique,
 * avertissement de dépassement des plafonds, historique (journal d'audit) et copie d'une formule.
 */
final class SystemSubscriptionPlansController
{
    private const BASE = 'admin/system/subscription-plans';

    public function __construct(
        private ?SubscriptionPlanRepository $plans = null,
        private ?AuditService $audit = null,
        private ?SubscriptionPlanAdminService $admin = null,
    ) {
        $this->plans ??= new SubscriptionPlanRepository();
        $this->audit ??= new AuditService();
        $this->admin ??= new SubscriptionPlanAdminService();
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->plans->tableExists()) {
            Session::flash('error', 'La table des formules d’accès n’est pas disponible (migrations).');

            return Response::redirect(url('admin'));
        }
        $rows = $this->plans->allOrdered();
        $values = [];
        foreach ($rows as $r) {
            $values[(string) $r['slug']] = SubscriptionPlanFeaturesCatalog::planValues($r);
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.subscription_plans_index',
            'title' => 'Formules d’accès (paliers)',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'admin-subscription-plans.css'],
            'subscriptionPlansRows' => $rows,
            'subscriptionPlansValues' => $values,
            'subscriptionPlansSubscribers' => $this->admin->subscribersBySlug(),
            'subscriptionPlansSearch' => $request->queryString('q'),
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

            return Response::redirect(url(self::BASE));
        }
        $slug = (string) ($row['slug'] ?? '');

        return Response::view('layout.main', [
            'content' => 'admin.system.subscription_plans_form',
            'title' => 'Modifier la formule — ' . (string) ($row['name'] ?? ''),
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'admin-subscription-plans.css'],
            'subscriptionPlanRow' => $row,
            'subscriptionPlanValues' => SubscriptionPlanFeaturesCatalog::planValues($row),
            'subscriptionPlanUnknownKeys' => SubscriptionPlanFeaturesCatalog::unknownKeys(SubscriptionPlanFeaturesCatalog::decodeFeatures($row)),
            'subscriptionPlanSubscribers' => $this->admin->subscribersBySlug()[$slug] ?? [],
            'subscriptionPlanHistory' => $this->admin->history($id),
            'subscriptionPlanPreview' => $this->previewData($row, null),
            'subscriptionPlanTab' => $request->queryString('onglet', 'members'),
            'subscriptionPlanFormAction' => url(self::BASE . '/' . $id . '/update'),
            'subscriptionPlanPreviewAction' => url(self::BASE . '/' . $id . '/apercu'),
            'subscriptionPlanDuplicateAction' => url(self::BASE . '/' . $id . '/dupliquer'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        if (!$this->plans->tableExists()) {
            return Response::redirect(url('admin'));
        }
        $id = (int) ($params['id'] ?? 0);
        $before = $id > 0 ? $this->plans->findById($id) : null;
        if ($before === null) {
            Session::flash('error', 'Formule introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $back = url(self::BASE . '/' . $id . '/edit') . $this->tabQuery($request);

        $name = trim((string) $request->input('name', ''));
        if ($name === '' || mb_strlen($name) > 100) {
            Session::flash('error', 'Le libellé est obligatoire (100 caractères maximum).');

            return Response::redirect($back);
        }
        $sortOrder = (int) $request->input('sort_order', 0);

        $input = $request->all();
        $errors = SubscriptionPlanFeaturesCatalog::validateForm($input);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));

            return Response::redirect($back);
        }
        // Clés inconnues du catalogue : conservées telles quelles.
        $features = SubscriptionPlanFeaturesCatalog::buildFeaturesFromForm($input, SubscriptionPlanFeaturesCatalog::decodeFeatures($before), (string) ($before['slug'] ?? ''));
        $featuresJson = json_encode($features, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $limitsRaw = trim((string) $request->input('limits_json', ''));
        $limitsJson = $this->normalizeJsonField($limitsRaw, 'Quotas périodiques');
        if ($limitsJson === false) {
            return Response::redirect($back);
        }

        $priceMonthly = self::eurosToCents((string) $request->input('price_monthly', ''));
        $priceYearly = self::eurosToCents((string) $request->input('price_yearly', ''));
        if ($priceMonthly === false || $priceYearly === false) {
            Session::flash('error', 'Prix invalide : indiquez un montant en euros (ex. 9,99), ou laissez vide.');

            return Response::redirect($back);
        }

        $overages = $this->admin->overages(
            (string) $before['slug'],
            SubscriptionPlanFeaturesCatalog::planValues($before)['values'],
            SubscriptionPlanFeaturesCatalog::planValues(['slug' => $before['slug'], 'features_json' => $featuresJson])['values'],
        );

        // Les anciens identifiants Stripe / PayPal (abonnements) ne sont plus saisis : on garde les valeurs en base.
        $payload = [
            'name' => $name,
            'sort_order' => $sortOrder,
            'features_json' => $featuresJson,
            'limits_json' => $limitsJson,
            'stripe_price_id_monthly' => $before['stripe_price_id_monthly'] ?? null,
            'stripe_price_id_yearly' => $before['stripe_price_id_yearly'] ?? null,
            'paypal_plan_id_monthly' => $before['paypal_plan_id_monthly'] ?? null,
            'paypal_plan_id_yearly' => $before['paypal_plan_id_yearly'] ?? null,
        ];
        $this->plans->update($id, $payload);
        $this->plans->updatePrices($id, $priceMonthly, $priceYearly);

        $after = $this->plans->findById($id) ?? [];
        $this->audit->logChange(
            AuditAction::SUBSCRIPTION_PLAN_UPDATED,
            null,
            $this->actorId(),
            'subscription_plan',
            $id,
            $this->auditSnapshot($before),
            $this->auditSnapshot($after),
        );

        Session::flash('success', 'Formule enregistrée.');
        if ($overages !== []) {
            $names = array_values(array_unique(array_column($overages, 'tenant_name')));
            Session::flash('warning', count($names) . ' communauté(s) dépassent déjà un nouveau plafond : '
                . implode(', ', array_slice($names, 0, 8)) . (count($names) > 8 ? '…' : '')
                . '. Rien n’est retiré ; seuls les nouveaux ajouts sont refusés.');
        }

        return Response::redirect(url(self::BASE . '/' . $id . '/edit') . $this->tabQuery($request));
    }

    /**
     * Aperçu (fragment HTML) des valeurs saisies, avant enregistrement : texte de la page publique et communautés qui
     * dépasseraient un plafond abaissé.
     */
    public function preview(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Réessayez.'], 419);
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 && $this->plans->tableExists() ? $this->plans->findById($id) : null;
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'Formule introuvable.'], 404);
        }
        $input = $request->all();
        $features = SubscriptionPlanFeaturesCatalog::buildFeaturesFromForm($input, SubscriptionPlanFeaturesCatalog::decodeFeatures($row), (string) ($row['slug'] ?? ''));
        $after = SubscriptionPlanFeaturesCatalog::planValues(['slug' => $row['slug'], 'features_json' => json_encode($features)])['values'];

        return Response::view('admin.system.partials.subscription_plan_preview', [
            'previewData' => $this->previewData($row, $features),
            'previewErrors' => SubscriptionPlanFeaturesCatalog::validateForm($input),
            'previewOverages' => $this->admin->overages(
                (string) $row['slug'],
                SubscriptionPlanFeaturesCatalog::planValues($row)['values'],
                $after,
            ),
            'previewSubscribers' => count($this->admin->subscribersBySlug()[(string) $row['slug']] ?? []),
        ])->header('Cache-Control', 'no-store');
    }

    /** Copie d'une formule (paramètres et quotas, sans prix) puis ouverture de sa fiche. */
    public function duplicate(Request $request, array $params = []): Response
    {
        if (! Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        $id = (int) ($params['id'] ?? 0);
        $src = $id > 0 && $this->plans->tableExists() ? $this->plans->findById($id) : null;
        if ($src === null) {
            Session::flash('error', 'Formule introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $slug = SubscriptionPlanAdminService::copySlug((string) $src['slug'], fn (string $s): bool => $this->plans->slugExists($s));
        $name = mb_substr(trim((string) $src['name']) . ' (copie)', 0, 100);
        $newId = $this->plans->insert([
            'slug' => $slug,
            'name' => $name,
            'sort_order' => (int) ($src['sort_order'] ?? 0) + 1,
            // Valeurs appliquées complètes (défauts du palier d'origine inclus) + clés inconnues conservées.
            'features_json' => json_encode(
                array_merge(SubscriptionPlanFeaturesCatalog::decodeFeatures($src), SubscriptionPlanFeaturesCatalog::planValues($src)['values']),
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            'limits_json' => ($src['limits_json'] ?? null) !== '' ? ($src['limits_json'] ?? null) : null,
        ]);
        $this->audit->logChange(
            AuditAction::SUBSCRIPTION_PLAN_DUPLICATED,
            null,
            $this->actorId(),
            'subscription_plan',
            $newId,
            ['copie_de' => (string) $src['slug']],
            $this->auditSnapshot($this->plans->findById($newId) ?? []),
        );
        Session::flash('success', 'Copie créée : « ' . $name . ' ». Elle n’est pas proposée à la vente ; vous pouvez l’offrir depuis Formules offertes.');

        return Response::redirect(url(self::BASE . '/' . $newId . '/edit'));
    }

    /**
     * Cartes de la page publique, la formule éditée prenant les valeurs proposées ($features) ; si elle n'est pas
     * proposée au public, sa carte est ajoutée seule.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $features
     *
     * @return array{cards: list<array<string, mixed>>, isPublic: bool, slug: string}
     */
    private function previewData(array $row, ?array $features): array
    {
        $slug = (string) ($row['slug'] ?? '');
        $onSale = [];
        try {
            foreach (\App\Core\Container::get(PlanPaymentService::class)->offers() as $o) {
                if (!empty($o['on_sale'])) {
                    $onSale[] = (string) $o['slug'];
                }
            }
        } catch (\Throwable) {
            $onSale = PlanPaymentService::PAID_PLANS;
        }
        $public = PlanOfferSummary::publicPlans($this->plans->allOrdered(), $onSale);
        $isPublic = in_array($slug, array_map(static fn (array $p): string => (string) $p['slug'], $public), true);
        $plans = $isPublic ? $public : [$row];
        $overrides = $features !== null ? [$slug => $features] : [];

        return ['cards' => PlanOfferSummary::cards($plans, $overrides), 'isPublic' => $isPublic, 'slug' => $slug];
    }

    private function tabQuery(Request $request): string
    {
        $tab = (string) $request->input('onglet', '');

        return preg_match('/^[a-z_]{1,20}$/', $tab) === 1 ? '?onglet=' . $tab : '';
    }

    private function actorId(): ?int
    {
        $actorId = Session::get('user_id');

        return is_numeric($actorId) && (int) $actorId > 0 ? (int) $actorId : null;
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
            'price_monthly_cents' => (string) ($row['price_monthly_cents'] ?? ''),
            'price_yearly_cents' => (string) ($row['price_yearly_cents'] ?? ''),
        ];
    }

    /** « 9,99 » / « 9.99 » / « 10 » → centimes ; vide → null ; false si invalide. */
    public static function eurosToCents(string $raw): int|null|false
    {
        $raw = str_replace([' ', "\u{00A0}", '€'], '', trim($raw));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{1,5}([.,]\d{1,2})?$/', $raw) !== 1) {
            return false;
        }
        $cents = (int) round((float) str_replace(',', '.', $raw) * 100);

        return $cents > 0 ? $cents : null;
    }

    /** @return string|null|false false = flash error already set */
    private function normalizeJsonField(string $raw, string $label): string|null|false
    {
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Session::flash('error', $label . ' : format invalide.');

            return false;
        }
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
}
