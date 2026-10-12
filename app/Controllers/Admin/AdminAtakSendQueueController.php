<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakSendQueueRepository;
use App\Services\Tactical\AtakActivityLogService;

/**
 * Back-office ATAK : files d'envoi de COMSPEC Link vers Athena.
 *   /back-office/atak/files-envoi                      état d'Athena, historique 6 h, files par joueur / serveur
 *   /back-office/atak/files-envoi?uid=…&type=client    envois suivis d'un client et leurs étapes
 */
final class AdminAtakSendQueueController
{
    public function __construct(
        private ?AtakSendQueueRepository $queues = null,
    ) {
        $this->queues ??= new AtakSendQueueRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $ready = $this->queues->schemaReady();
        $now = time();
        $snapshots = $ready ? $this->queues->listSnapshots($tenantId) : [];

        $detail = null;
        $uid = trim((string) ($request->query('uid') ?? ''));
        $type = (string) ($request->query('type') ?? 'client');
        if ($ready && $uid !== '' && preg_match('/^(7656\d{13}|' . AtakSendQueueRepository::SERVER_KEY . ')$/', $uid)) {
            $detail = $this->queues->findSnapshot($tenantId, $uid, in_array($type, AtakSendQueueRepository::CLIENT_KINDS, true) ? $type : 'client');
        }

        return Response::view('layout.main', [
            'content' => 'admin.atak.send_queues',
            'title' => 'Files d’envoi Athena',
            'pageTitle' => 'Files d’envoi Athena',
            'sqReady' => $ready,
            'sqNow' => $now,
            'sqSnapshots' => $snapshots,
            'sqStatus' => AtakSendQueueRepository::computeStatus($snapshots, $now),
            'sqHistory' => $ready ? $this->queues->historyBuckets($tenantId, 6, 15, $now) : [],
            'sqServer429' => $this->server429Count($tenantId, $now),
            'sqDetail' => $detail,
            'sqDetailMissing' => $uid !== '' && $detail === null,
        ]);
    }

    /**
     * Refus « trop de requêtes » donnés par Athena aux envois du jeu ces 15 dernières minutes
     * (journal Liaison, écrit par AtakArmaWriteGuard). Null si le journal n'est pas lisible.
     */
    private function server429Count(int $tenantId, int $now): ?int
    {
        try {
            $res = (new AtakActivityLogService())->listFiltered($tenantId, AtakActivityLogService::AUTH_MAP_ID, [
                'type' => AtakActivityLogService::TYPE_AUTH,
                'q' => 'ralentie',
                'from' => date('Y-m-d H:i:s', $now - 900),
                'include_archived' => true,
                'limit' => 1,
            ]);

            return (int) ($res['total'] ?? 0);
        } catch (\Throwable) {
            return null;
        }
    }
}
