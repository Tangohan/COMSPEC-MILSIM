<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAbuseAlertRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Watch\WatchRules;
use App\Services\Watch\WatchSettings;

/**
 * /admin/system/alertes-mod : activités anormales détectées dans les envois du mod. Aucune sanction ni blocage
 * automatique : l'alerte est lue, prise en compte ou ignorée par un administrateur de la plateforme, qui décide
 * ensuite (Parc ATAK, liste de restriction, échange avec la communauté).
 */
final class SystemModAlertsController
{
    private const STATUSES = ['open' => 'À examiner', 'ack' => 'Prises en compte', 'dismissed' => 'Ignorées', 'all' => 'Toutes'];

    private const DETECT_KEYS = [
        WatchSettings::DETECT_ENABLED, WatchSettings::SPEED_MPS, WatchSettings::SPEED_HITS, WatchSettings::POS_LIMIT_M,
        WatchSettings::VOLUME_PER_MIN, WatchSettings::CRED_STEAMS, WatchSettings::REFUSALS_PER_MIN, WatchSettings::VERSION_CHANGES,
    ];

    public function index(Request $request, array $params = []): Response
    {
        $status = (string) $request->query('statut', 'open');
        $status = array_key_exists($status, self::STATUSES) ? $status : 'open';
        $repo = new PlatformAbuseAlertRepository();
        $rows = $repo->recent($status === 'all' ? '' : $status, PlatformAbuseAlertRepository::KIND_MOD, 150);
        foreach ($rows as &$row) {
            $row['rule_label'] = WatchRules::label((string) $row['rule']);
            $row['rule_explain'] = WatchRules::explain((string) $row['rule']);
            $row['lines'] = WatchRules::measuredLines((string) $row['measured_json']);
        }
        unset($row);

        return Response::view('layout.main', [
            'title' => 'Activités suspectes du mod',
            'content' => 'admin.system.mod_alerts',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'modAlertRows' => $rows,
            'modAlertStatus' => $status,
            'modAlertStatuses' => self::STATUSES,
            'modAlertCounts' => $repo->countsByStatus(PlatformAbuseAlertRepository::KIND_MOD),
            'modAlertSettings' => WatchSettings::load(),
            'modAlertRules' => WatchRules::modRules(),
        ]);
    }

    public function handle(Request $request, array $params = []): Response
    {
        $return = $request->inputString('retour', 'open');
        $back = url('admin/system/alertes-mod') . '?statut=' . rawurlencode(array_key_exists($return, self::STATUSES) ? $return : 'open');
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $id = (int) ($params['id'] ?? 0);
        $action = $request->inputString('action');
        $map = [
            'ack' => PlatformAbuseAlertRepository::STATUS_ACK,
            'dismiss' => PlatformAbuseAlertRepository::STATUS_DISMISSED,
            'reopen' => PlatformAbuseAlertRepository::STATUS_OPEN,
        ];
        if (!isset($map[$action])) {
            Session::flash('error', 'Action inconnue.');

            return Response::redirect($back);
        }
        $repo = new PlatformAbuseAlertRepository();
        $alert = $repo->find($id);
        $uid = (int) Session::get('user_id');
        if ($alert === null || !$repo->setStatus($id, $map[$action], $uid > 0 ? $uid : null)) {
            Session::flash('error', 'Alerte introuvable.');

            return Response::redirect($back);
        }
        AuditService::logPlatformAction(
            AuditAction::PLATFORM_ABUSE_ALERT_HANDLED,
            ['alerte' => $id, 'regle' => (string) $alert['rule'], 'decision' => $action],
            isset($alert['tenant_id']) ? (int) $alert['tenant_id'] : null
        );
        Session::flash('success', 'Alerte mise à jour. Aucune sanction n’a été appliquée.');

        return Response::redirect($back);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        $back = url('admin/system/alertes-mod');
        if (!Csrf::validate($request->inputString('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $before = WatchSettings::load();
        $after = WatchSettings::fromForm($request->all(), $before, [WatchSettings::DETECT_ENABLED]);
        foreach (WatchSettings::keys() as $key) {
            if (!in_array($key, self::DETECT_KEYS, true)) {
                $after[$key] = $before[$key];
            }
        }
        if (!SystemLoadController::persist(new PlatformSettingsRepository(), $after, $before, 'alertes-mod')) {
            Session::flash('error', 'Les réglages n’ont pas pu être enregistrés (table platform_settings absente ?).');

            return Response::redirect($back);
        }
        Session::flash('success', 'Seuils des détecteurs enregistrés.');

        return Response::redirect($back);
    }
}
