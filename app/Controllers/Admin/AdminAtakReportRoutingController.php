<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakReportRoutingRepository;
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
        'SPOTREP' => 'Observation (SPOTREP)',
        'SITREP' => 'Situation (SITREP)',
        'SALUTE' => 'Compte rendu SALUTE',
        'CONTACT' => 'Prise de contact',
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

        return Response::view('layout.main', [
            'content' => 'admin.atak_report_routing.index',
            'title' => 'Diffusion des rapports',
            'routingRules' => $this->routing->listRules($tenantId),
            'routingReportTypes' => self::REPORT_TYPES,
            'routingPriorities' => self::PRIORITIES,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('admin/atak-diffusion-rapports');

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
            'report_types' => self::cleanList($request->input('report_types'), array_keys(self::REPORT_TYPES)),
            'priorities' => self::cleanList($request->input('priorities'), array_keys(self::PRIORITIES)),
            'keywords' => self::splitLines((string) $request->input('keywords', '')),
            'auto_assign_to_roles' => self::splitLines((string) $request->input('roles', '')),
            'auto_assign_to_units' => self::splitLines((string) $request->input('units', '')),
            'send_notification' => (string) $request->input('send_notification', '0') === '1',
            'escalate_after_minutes' => (int) $request->input('escalate_after_minutes', 0),
        ];

        // Une règle sans destinataire ne route vers personne : elle donnerait
        // l'illusion d'une diffusion en place tout en n'en produisant aucune.
        if ($data['auto_assign_to_roles'] === [] && $data['auto_assign_to_units'] === []) {
            Session::flash('error', 'Indiquez au moins une fonction ou une unité destinataire — sans destinataire, la règle ne diffuse rien.');

            return Response::redirect($back);
        }

        $this->routing->saveRule($tenantId, $data, $id > 0 ? $id : null);
        Session::flash('success', $id > 0 ? 'Règle mise à jour.' : 'Règle créée.');

        return Response::redirect($back);
    }

    public function toggle(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('admin/atak-diffusion-rapports');

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

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $back = url('admin/atak-diffusion-rapports');

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
