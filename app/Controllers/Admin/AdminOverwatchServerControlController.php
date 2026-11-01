<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\AtakRelayRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Audit\AuditService;
use App\Services\Tactical\AtakBridgeModulesService;
use App\Services\Tactical\AtakExperienceService;
use App\Support\ModuleFeatureAccess;
use App\Support\ServerControlPresenter;

/**
 * Contrôle de mission : règles imposées aux joueurs, fonctions reliées, apps du téléphone et relais,
 * avec pour chaque réglage ce qu'il fait réellement en jeu (ServerControlWiring).
 */
final class AdminOverwatchServerControlController
{
    public const AUDIT_UPDATED = 'atak.server_control.updated';
    public const AUDIT_NETWORK_ADOPTED = 'atak.server_control.network_adopted';

    public function __construct(
        private ?TenantAtakConfigRepository $atakConfig = null,
        private ?AtakRelayRepository $relays = null,
        private ?AtakMapRepository $maps = null,
    ) {
        $this->atakConfig ??= new TenantAtakConfigRepository();
        $this->relays ??= new AtakRelayRepository();
        $this->maps ??= new AtakMapRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $exp = new AtakExperienceService();
        $modules = new AtakBridgeModulesService();
        $expState = $exp->get($tenantId);
        $config = $this->atakConfig->getByTenantId($tenantId) ?? [];
        $mapLabels = [];
        foreach ($this->maps->getAll() as $map) {
            $id = (int) ($map['id'] ?? 0);
            if ($id > 0) {
                $mapLabels[$id] = (string) ($map['label'] ?? $map['slug'] ?? ('Carte ' . $id));
            }
        }
        $relayRows = [];
        foreach ($this->relays->listForTenant($tenantId) as $row) {
            $mapId = (int) ($row['map_id'] ?? 0);
            $row['map_label'] = $mapLabels[$mapId] ?? ('Carte ' . $mapId);
            $relayRows[] = $row;
        }

        $now = time();
        $presence = ServerControlPresenter::presence($this->terminals($tenantId), $this->serverPresence($tenantId), $now);
        $view = ServerControlPresenter::themes($exp->catalogWithState($tenantId), $modules->catalogWithState($tenantId), $presence['versions']);
        $appsPolicy = $expState['settings']['apps_policy'] ?? [];

        return Response::view('layout.main', [
            'content' => 'admin.atak.server_control',
            'title' => 'Contrôle de mission',
            'pageTitle' => 'Contrôle de mission',
            'backOfficePageCss' => ['back-office-server-control.css'],
            'csrfToken' => Csrf::token(),
            'experienceSchemaReady' => $this->atakConfig->isExperienceSchemaReady(),
            'themes' => $view['themes'],
            'soon' => $view['soon'],
            'stateCounts' => $view['counts'],
            'preview' => ServerControlPresenter::preview($view, $appsPolicy, $exp->phoneApps()),
            'presence' => $presence,
            'now' => $now,
            'netLive' => $expState['settings']['net_live'] ?? [],
            'phoneApps' => $exp->phoneApps(),
            'appsPolicy' => $appsPolicy,
            'relays' => $relayRows,
            'maintenanceEnabled' => (int) ($config['maintenance_enabled'] ?? 0) === 1,
            'savedAt' => max((string) $expState['updated_at'], (string) $modules->get($tenantId)['updated_at']),
            'journal' => ServerControlPresenter::journal($this->auditRows($tenantId)),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    /** Enregistre le formulaire de la page : règles, fonctions reliées et apps (seuls les champs envoyés changent). */
    public function storeRules(Request $request, array $params = []): Response
    {
        return $this->save($request, '#regles');
    }

    /** Ancienne adresse du formulaire « Fonctions actives » : même enregistrement. */
    public function storeModules(Request $request, array $params = []): Response
    {
        return $this->save($request, '#fonctions');
    }

    /** Ancienne adresse du formulaire « Apps du téléphone » : même enregistrement. */
    public function storeApps(Request $request, array $params = []): Response
    {
        return $this->save($request, '#apps');
    }

    /**
     * Garde comme règles de la communauté les dernières conditions réseau appliquées en jeu (menu Échap).
     */
    public function adoptNetwork(Request $request, array $params = []): Response
    {
        $redirect = $this->guardPost($request);
        if ($redirect instanceof Response) {
            return $redirect;
        }
        $tenantId = $this->tenantId();
        $exp = new AtakExperienceService();
        $before = $exp->get($tenantId)['settings'];
        $n = $exp->adoptLiveNetwork($tenantId);
        if ($n > 0) {
            $this->markControlReviewed($tenantId);
            $after = $exp->get($tenantId)['settings'];
            $changes = ServerControlPresenter::changes(
                $this->itemsByKey($exp, new AtakBridgeModulesService()),
                $this->prefixed($before, 'rule:'),
                $this->prefixed($after, 'rule:')
            );
            $this->audit(self::AUDIT_NETWORK_ADOPTED, $tenantId, $changes);
            Session::flash('success', $n . ' condition(s) réseau reprise(s) comme règles de la communauté. Les joueurs en liaison les reçoivent en moins de 2 minutes.');
        } else {
            Session::flash('error', 'Aucune condition réseau appliquée en jeu à reprendre.');
        }

        return Response::redirect(url('back-office/atak/controle-serveur') . '#conditions-en-jeu');
    }

    /**
     * @deprecated Le réglage « liaison par relais » est géré dans back-office/atak/roleplay.
     */
    public function storeRelays(Request $request, array $params = []): Response
    {
        $redirect = $this->guardPost($request);
        if ($redirect instanceof Response) {
            return $redirect;
        }
        Session::flash('error', 'Ce réglage est maintenant dans la page Mode roleplay.');

        return Response::redirect(url('back-office/atak/roleplay'));
    }

    /**
     * Valeurs reçues du formulaire, champ par champ : un champ absent ne change rien.
     *
     * @param array<string, mixed> $post
     * @param list<array<string, mixed>> $ruleCatalog
     * @param list<array<string, mixed>> $moduleCatalog
     * @param list<array<string, mixed>> $phoneApps
     * @return array{rules: array<string, mixed>, modules: array<string, bool>, apps: array<string, string>}
     */
    public static function incomingFromPost(array $post, array $ruleCatalog, array $moduleCatalog, array $phoneApps): array
    {
        $scalar = static fn (mixed $v): ?string => is_scalar($v) ? trim((string) $v) : null;
        $rules = [];
        foreach ($ruleCatalog as $row) {
            $id = (string) $row['id'];
            $v = $scalar($post['experience_' . $id] ?? null);
            if ($v !== null) {
                $rules[$id] = ($row['type'] ?? 'bool') === 'bool' ? $v === '1' : $v;
            }
        }
        $modules = [];
        foreach ($moduleCatalog as $row) {
            $v = $scalar($post['module_' . $row['id']] ?? null);
            if ($v !== null) {
                $modules[(string) $row['id']] = $v === '1';
            }
        }
        $apps = [];
        foreach ($phoneApps as $app) {
            $v = $scalar($post['app_' . $app['id']] ?? null);
            if ($v !== null) {
                $apps[(string) $app['id']] = $v;
            }
        }

        return ['rules' => $rules, 'modules' => $modules, 'apps' => $apps];
    }

    private function save(Request $request, string $anchor): Response
    {
        $redirect = $this->guardPost($request);
        if ($redirect instanceof Response) {
            return $redirect;
        }
        $tenantId = $this->tenantId();
        $back = url('back-office/atak/controle-serveur') . $anchor;
        $exp = new AtakExperienceService();
        $mods = new AtakBridgeModulesService();
        $in = self::incomingFromPost($request->all(), $exp->catalog(), $mods->catalog(), $exp->phoneApps());
        if (($in['rules'] !== [] || $in['apps'] !== []) && !$this->atakConfig->isExperienceSchemaReady()) {
            Session::flash('error', 'Rien n’a été enregistré : la base n’est pas à jour. Contactez le support, puis réessayez.');

            return Response::redirect($back);
        }

        $settingsBefore = $exp->get($tenantId)['settings'];
        $before = $this->prefixed($settingsBefore, 'rule:')
            + $this->prefixed($mods->get($tenantId)['modules'], 'module:')
            + $this->prefixed($settingsBefore['apps_policy'] ?? [], 'app:');
        if ($in['rules'] !== []) {
            $exp->put($tenantId, $in['rules'] + ['server_control_reviewed' => true]);
        }
        if ($in['modules'] !== []) {
            $mods->put($tenantId, $in['modules']);
        }
        if ($in['apps'] !== []) {
            $exp->putAppPolicy($tenantId, $in['apps']);
        }
        $settingsAfter = $exp->get($tenantId)['settings'];
        $after = array_intersect_key($this->prefixed($settingsAfter, 'rule:'), $this->prefixed($in['rules'], 'rule:'))
            + $this->prefixed(array_intersect_key($mods->get($tenantId)['modules'], $in['modules']), 'module:');
        foreach ($in['apps'] as $id => $_) {
            $after['app:' . $id] = $settingsAfter['apps_policy'][$id] ?? 'player';
        }

        $changes = ServerControlPresenter::changes($this->itemsByKey($exp, $mods), $before, $after);
        $this->markControlReviewed($tenantId);
        if ($changes === []) {
            Session::flash('success', 'Aucun changement : les réglages étaient déjà ceux-ci.');

            return Response::redirect($back);
        }
        $this->audit(self::AUDIT_UPDATED, $tenantId, $changes);
        $n = count($changes);
        Session::flash('success', sprintf(
            '%d réglage%s enregistré%s : %s%s. Les joueurs en liaison les reçoivent en moins de 2 minutes.',
            $n,
            $n > 1 ? 's' : '',
            $n > 1 ? 's' : '',
            implode(', ', array_column(array_slice($changes, 0, 3), 'label')),
            $n > 3 ? ' et ' . ($n - 3) . ' autre(s)' : ''
        ));

        return Response::redirect($back);
    }

    /**
     * Tous les réglages de la page par id préfixé (rule:, module:, app:), avec ce qu'il faut pour lire leurs valeurs.
     *
     * @return array<string, array<string, mixed>>
     */
    private function itemsByKey(AtakExperienceService $exp, AtakBridgeModulesService $mods): array
    {
        $items = [];
        foreach ($exp->catalog() as $row) {
            $items['rule:' . $row['id']] = $row;
        }
        foreach ($mods->catalog() as $row) {
            $items['module:' . $row['id']] = $row + ['type' => 'module', 'default' => true];
        }
        $appChoices = [['value' => 'player', 'label' => 'Au choix'], ['value' => 'required', 'label' => 'Obligatoire'], ['value' => 'forbidden', 'label' => 'Interdite']];
        foreach ($exp->phoneApps() as $app) {
            $items['app:' . $app['id']] = ['label' => 'App ' . $app['name'], 'type' => 'list', 'default' => 'player', 'choices' => $appChoices];
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private function prefixed(array $values, string $prefix): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            $out[$prefix . $k] = $v;
        }

        return $out;
    }

    /** @param list<array{label: string, from: string, to: string}> $changes */
    private function audit(string $action, int $tenantId, array $changes): void
    {
        if ($changes === []) {
            return;
        }
        try {
            (new AuditService())->logChange(
                $action,
                $tenantId,
                (int) (Session::get('user_id') ?? 0) ?: null,
                'tenant',
                $tenantId,
                array_column($changes, 'from', 'label'),
                array_column($changes, 'to', 'label'),
            );
        } catch (\Throwable) {
            // Le journal ne doit jamais bloquer l’enregistrement d’un réglage.
        }
    }

    /** @return list<array<string, mixed>> */
    private function auditRows(int $tenantId): array
    {
        try {
            return (new \App\Repositories\AuditLogRepository())->listForTenant($tenantId, ['action_domain' => 'atak.server_control'], 1, 8)['rows'];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function terminals(int $tenantId): array
    {
        try {
            return (new \App\Repositories\AtakAdminControlRepository())->listTerminals('', $tenantId, 300);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private function serverPresence(int $tenantId): array
    {
        try {
            return (new \App\Repositories\TenantGameServerRepository())->recentPresence($tenantId, 24, 1);
        } catch (\Throwable) {
            return [];
        }
    }

    private function tenantId(): int
    {
        return (int) (Session::get('tenant_id') ?? 0);
    }

    private function guardPost(Request $request): ?Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée : rien n’a été enregistré. Rechargez la page puis réessayez.');

            return Response::redirect(url('back-office/atak/controle-serveur'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage', 'back-office/atak');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        return null;
    }

    private function markControlReviewed(int $tenantId): void
    {
        try {
            \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class)
                ->markCompleted($tenantId, 'OVERWATCH_SERVER_CONTROL_V1', (int) (Session::get('user_id') ?? 0) ?: null);
        } catch (\Throwable) {
        }
    }
}
