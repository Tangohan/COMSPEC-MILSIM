<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakReportRoutingRepository;
use App\Support\AtakIcemanReportCatalog;
use App\Services\Tactical\AtakReportRoutingAdminService;
use App\Support\ModuleFeatureAccess;

/**
 * Règles de diffusion des rapports tactiques.
 *
 * Le moteur d'application est branché depuis la phase A, mais il tourne à vide
 * tant qu'aucune règle n'existe : une table de règles vide *est* l'état
 * désactivé. Cet écran est ce qui manquait pour que la fonctionnalité serve.
 */
final class AdminAtakReportRoutingController
{
    /** Types de rapport reconnus par le moteur, tels que déclarés en base. */
    public const REPORT_TYPES = [
        'TIC' => 'Contact',
        'EAGLE_DOWN' => 'Opérateur à terre',
        'BDA' => 'Bilan des dégâts',
        'FRAGO' => 'Ordre fragmentaire',
        'SALUTE' => 'Compte rendu SALUTE',
        'SPOTREP' => 'Observation',
        'SITREP' => 'Situation',
        'CONTACT' => 'Prise de contact',
        'PATROLREP' => 'Fin de patrouille',
        'LOGREP' => 'État logistique',
        'UXO' => 'Engin explosif',
        'NBC1' => 'Attaque NRBC',
        'OTHER' => 'Autre',
    ];

    /** @var array<string, string> */
    public const PRIORITIES = [
        'ROUTINE' => 'Routine',
        'PRIORITY' => 'Prioritaire',
        'IMMEDIATE' => 'Immédiat',
        'FLASH' => 'Flash',
    ];

    public function __construct(private ?AtakReportRoutingRepository $routing = null)
    {
        $this->routing ??= new AtakReportRoutingRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $admin = new AtakReportRoutingAdminService($tenantId);
        $rules = $this->routing->listRules($tenantId);
        foreach ($rules as &$rule) {
            foreach (['ROLE' => 'auto_assign_to_roles', 'UNIT' => 'auto_assign_to_units', 'USER' => 'auto_assign_to_users', 'ESC' => 'escalate_to_roles'] as $type => $col) {
                $rule['_' . $col] = array_map(
                    fn ($id): array => $admin->resolve($type === 'ESC' ? 'ROLE' : $type, (string) $id) + ['type' => $type === 'ESC' ? 'ROLE' : $type],
                    AtakReportRoutingAdminService::jsonList($rule[$col] ?? null)
                );
            }
            $cond = json_decode((string) ($rule['trigger_conditions'] ?? ''), true);
            $rule['_conditions'] = is_array($cond) ? $cond : [];
        }
        unset($rule);
        $tab = (string) $request->query('vue', 'regles');
        $journalRule = (int) $request->query('regle', 0);
        $waiting = (string) $request->query('attente', '') === '1';

        return Response::view('layout.main', [
            'content' => 'admin.atak_report_routing.index',
            'title' => 'Diffusion des rapports',
            'routingRules' => $rules,
            'routingReportTypes' => self::REPORT_TYPES,
            'routingTypeGroups' => AtakReportRoutingAdminService::reportTypeGroups(self::REPORT_TYPES),
            'routingPriorities' => self::PRIORITIES,
            'routingDirectory' => $admin->directory(),
            'routingStats' => $admin->stats(30),
            'routingJournal' => $tab === 'journal' ? $admin->journal(200, $journalRule ?: null, $waiting) : [],
            'routingRecentReports' => $tab === 'simulateur' ? $admin->recentReports() : [],
            'routingTab' => in_array($tab, ['regles', 'journal', 'simulateur'], true) ? $tab : 'regles',
            'routingJournalRule' => $journalRule,
            'routingJournalWaiting' => $waiting,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('back-office/ressources/atak-diffusion-rapports');

        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $id = (int) $request->input('id', 0);
        $data = [
            'rule_name' => (string) $request->input('rule_name', ''),
            'is_active' => (string) $request->input('is_active', '0') === '1',
            'priority_order' => (int) $request->input('priority_order', 100),
            'report_types' => self::cleanList($request->input('report_types'), array_merge(...array_map('array_keys', array_values(AtakReportRoutingAdminService::reportTypeGroups(self::REPORT_TYPES))))),
            'priorities' => self::cleanList($request->input('priorities'), array_keys(self::PRIORITIES)),
            'keywords' => self::splitLines((string) $request->input('keywords', '')),
            // Choisis dans l'annuaire (cases) + valeurs libres héritées des anciennes règles.
            'auto_assign_to_roles' => self::mergeLists($request->input('roles'), $request->input('roles_extra')),
            'auto_assign_to_units' => self::mergeLists($request->input('units'), $request->input('units_extra')),
            'auto_assign_to_users' => array_values(array_filter(array_map('intval', is_array($request->input('users')) ? $request->input('users') : []), static fn (int $v): bool => $v > 0)),
            'send_notification' => true,
            'escalate_after_minutes' => (int) $request->input('escalate_after_minutes', 0),
            'escalate_to_roles' => self::mergeLists($request->input('escalate_roles'), null),
        ];

        // Une règle sans destinataire ne route vers personne : elle donnerait
        // l'illusion d'une diffusion en place tout en n'en produisant aucune.
        if ($data['auto_assign_to_roles'] === [] && $data['auto_assign_to_units'] === [] && $data['auto_assign_to_users'] === []) {
            Session::flash('error', 'Choisissez au moins un destinataire (fonction, unité ou membre) — sans destinataire, la règle ne diffuse rien.');

            return Response::redirect($back);
        }
        // Une escalade sans destinataire ne ferait rien en silence.
        if ($data['escalate_after_minutes'] > 0 && $data['escalate_to_roles'] === []) {
            Session::flash('error', 'Escalade : choisissez au moins une fonction à prévenir si personne n’accuse réception.');

            return Response::redirect($back);
        }

        $this->routing->saveRule($tenantId, $data, $id > 0 ? $id : null);
        Session::flash('success', $id > 0 ? 'Règle mise à jour.' : 'Règle créée.');

        return Response::redirect($back);
    }

    public function toggle(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('back-office/ressources/atak-diffusion-rapports');

        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $active = (string) $request->input('active', '0') === '1';
        $this->routing->toggleRule((int) ($params['id'] ?? 0), $tenantId, $active);
        Session::flash('success', $active ? 'Règle activée.' : 'Règle désactivée.');

        return Response::redirect($back);
    }

    /** POST …/{id}/dupliquer */
    public function duplicate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('back-office/ressources/atak-diffusion-rapports');
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        $rule = $this->routing->findRule((int) ($params['id'] ?? 0), $tenantId);
        if ($rule === null) {
            Session::flash('error', 'Règle introuvable.');

            return Response::redirect($back);
        }
        $cond = json_decode((string) ($rule['trigger_conditions'] ?? ''), true);
        $cond = is_array($cond) ? $cond : [];
        $this->routing->saveRule($tenantId, [
            'rule_name' => mb_substr('Copie de ' . (string) $rule['rule_name'], 0, 200),
            'is_active' => false,
            'priority_order' => (int) $rule['priority_order'] + 1,
            'report_types' => $cond['report_types'] ?? [],
            'priorities' => $cond['priorities'] ?? [],
            'keywords' => $cond['keywords'] ?? [],
            'auto_assign_to_roles' => AtakReportRoutingAdminService::jsonList($rule['auto_assign_to_roles'] ?? null),
            'auto_assign_to_units' => AtakReportRoutingAdminService::jsonList($rule['auto_assign_to_units'] ?? null),
            'auto_assign_to_users' => AtakReportRoutingAdminService::jsonList($rule['auto_assign_to_users'] ?? null),
            'send_notification' => true,
            'escalate_after_minutes' => (int) ($rule['escalate_after_minutes'] ?? 0),
            'escalate_to_roles' => AtakReportRoutingAdminService::jsonList($rule['escalate_to_roles'] ?? null),
        ]);
        Session::flash('success', 'Règle dupliquée (désactivée) : ajustez-la puis activez-la.');

        return Response::redirect($back);
    }

    /** POST …/ordre : ids[] dans l'ordre voulu. */
    public function reorder(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('back-office/ressources/atak-diffusion-rapports');
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        $ids = array_values(array_filter(array_map('intval', is_array($request->input('ids')) ? $request->input('ids') : [])));
        $orders = [];
        foreach ($ids as $i => $id) {
            $orders[$id] = ($i + 1) * 10;
        }
        $this->routing->reorderRules($tenantId, $orders);
        Session::flash('success', 'Ordre d’application enregistré.');

        return Response::redirect($back);
    }

    /** POST …/simuler (JSON) : quelles règles s'appliqueraient à ce rapport. */
    public function simulate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return Response::json(['ok' => false, 'message' => 'Session expirée.'], 419);
        }
        $forbidden = ModuleFeatureAccess::guardAtak('view');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        $res = (new AtakReportRoutingAdminService($tenantId))->simulate([
            'report_id' => (int) $request->input('report_id', 0),
            'report_type' => (string) $request->input('report_type', ''),
            'priority' => (string) $request->input('priority', ''),
            'text' => mb_substr((string) $request->input('text', ''), 0, 2000),
        ], $this->routing->listRules($tenantId));

        return Response::json(['ok' => true] + $res);
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('back-office/ressources/atak-diffusion-rapports');

        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $forbidden = ModuleFeatureAccess::guardAtak('manage');
        if ($forbidden instanceof Response) {
            return $forbidden;
        }

        $this->routing->deleteRule((int) ($params['id'] ?? 0), $tenantId);
        Session::flash('success', 'Règle supprimée. Les diffusions déjà effectuées restent au journal.');

        return Response::redirect($back);
    }

    /**
     * @return list<string>
     */
    private static function cleanList(mixed $raw, array $allowed): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_intersect(array_map('strval', $raw), $allowed));
    }

    /**
     * Cases cochées dans l'annuaire + saisie libre (anciennes règles, valeurs hors annuaire).
     *
     * @return list<string>
     */
    private static function mergeLists(mixed $picked, mixed $extra): array
    {
        $out = [];
        foreach (is_array($picked) ? $picked : [] as $v) {
            $v = trim((string) $v);
            if ($v !== '' && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        foreach (is_string($extra) ? self::splitLines($extra) : [] as $v) {
            if (!in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return array_slice($out, 0, 100);
    }

    /**
     * Une valeur par ligne ou séparée par des virgules — on accepte les deux
     * plutôt que d'imposer une syntaxe que personne ne retient.
     *
     * @return list<string>
     */
    private static function splitLines(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }

        return $out;
    }
}
