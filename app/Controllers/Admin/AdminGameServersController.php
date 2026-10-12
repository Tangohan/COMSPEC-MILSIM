<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantGameServerRepository;
use App\Services\Atak\CommunityServerMatcher;
use App\Services\Auth\AuthService;

/**
 * Gestion › Serveurs de jeu : adresses (et noms) des serveurs Arma de la communauté. Le téléphone ATAK prévient le
 * joueur relié à Athena qui joue sur un autre serveur ; la page liste aussi les serveurs où les joueurs ont été vus.
 */
final class AdminGameServersController
{
    private const BACK = 'back-office/ressources/serveurs-de-jeu';

    public function __construct(
        private TenantGameServerRepository $servers,
        private AuthService $authService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $servers = $tenantId > 0 ? $this->servers->listForTenant($tenantId) : [];
        $presence = $tenantId > 0 ? $this->servers->recentPresence($tenantId, 72, 150) : [];

        // Serveurs non déclarés où des joueurs ont été vus : proposés à l'enregistrement en un clic.
        $unknown = [];
        foreach ($presence as $p) {
            if (!empty($p['matched_server_id'])) {
                continue;
            }
            $ip = (string) ($p['server_ip'] ?? '');
            $name = (string) ($p['server_name'] ?? '');
            if ($ip === '' && $name === '') {
                continue;
            }
            $key = $ip !== '' ? 'ip:' . $ip : 'name:' . CommunityServerMatcher::normalizeName($name);
            if (!isset($unknown[$key])) {
                $unknown[$key] = ['server_ip' => $ip, 'server_name' => $name, 'players' => 0, 'last_seen' => (string) ($p['seen_at'] ?? '')];
            }
            $unknown[$key]['players']++;
        }

        $edit = null;
        $editId = (int) $request->query('modifier', 0);
        if ($editId > 0 && $tenantId > 0) {
            $edit = $this->servers->find($tenantId, $editId);
        }

        return Response::view('layout.main', [
            'title' => 'Serveurs de jeu',
            'content' => 'admin.game-servers.index',
            'servers' => $servers,
            'presence' => $presence,
            'unknownServers' => array_values($unknown),
            'editServer' => $edit,
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) $request->input('server_id');
        $label = mb_substr(trim((string) $request->input('label')), 0, 120, 'UTF-8');
        $rawIp = trim((string) $request->input('ip_address'));
        $name = mb_substr(trim((string) $request->input('server_name')), 0, 190, 'UTF-8');
        $portRaw = trim((string) $request->input('port'));
        $port = $portRaw === '' ? null : (int) $portRaw;

        $ip = '';
        if ($rawIp !== '') {
            if (preg_match('/^(.+):(\d{2,5})$/', $rawIp, $m) && substr_count($rawIp, ':') === 1) {
                $rawIp = $m[1];
                $port ??= (int) $m[2];
            }
            $ip = CommunityServerMatcher::normalizeHost($rawIp);
            if ($ip === '') {
                return $this->back('error', 'Adresse invalide : saisissez une adresse IP (ex. 203.0.113.10) ou un nom d’hôte (ex. arma.macommu.fr).');
            }
        }
        if ($ip === '' && $name === '') {
            return $this->back('error', 'Indiquez au moins l’adresse IP du serveur ou son nom dans la liste des serveurs Arma.');
        }
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return $this->back('error', 'Port invalide (1 à 65535).');
        }
        if ($label === '') {
            $label = $name !== '' ? mb_substr($name, 0, 120, 'UTF-8') : $ip;
        }

        try {
            if ($id > 0) {
                if ($this->servers->find($tenantId, $id) === null) {
                    return $this->back('error', 'Serveur introuvable.');
                }
                $this->servers->update($tenantId, $id, $label, $ip ?: null, $name ?: null, $port);

                return $this->back('success', 'Serveur « ' . $label . ' » mis à jour.');
            }
            $actor = $this->authService->user();
            $this->servers->create($tenantId, $label, $ip ?: null, $name ?: null, $port, $actor ? (int) $actor['id'] : null);
        } catch (\Throwable) {
            return $this->back('error', 'Enregistrement impossible. Lancez run-migrations.php si la table des serveurs n’existe pas encore.');
        }

        return $this->back('success', 'Serveur « ' . $label . ' » enregistré. Les joueurs reliés à Athena seront prévenus s’ils jouent ailleurs.');
    }

    public function toggle(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $row = $this->servers->find($tenantId, (int) $request->input('server_id'));
        if ($row === null) {
            return $this->back('error', 'Serveur introuvable.');
        }
        $on = (int) ($row['is_active'] ?? 0) !== 1;
        $this->servers->setActive($tenantId, (int) $row['id'], $on);

        return $this->back('success', 'Serveur « ' . (string) $row['label'] . ' » ' . ($on ? 'réactivé.' : 'mis en pause : il ne compte plus comme serveur de la communauté.'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $row = $this->servers->find($tenantId, (int) $request->input('server_id'));
        if ($row === null) {
            return $this->back('error', 'Serveur introuvable.');
        }
        $this->servers->delete($tenantId, (int) $row['id']);

        return $this->back('success', 'Serveur « ' . (string) $row['label'] . ' » supprimé.');
    }

    private function guard(Request $request): int|Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return $this->back('error', 'Session expirée. Réessayez.');
        }
        if (!$this->authService->user()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $this->back('error', 'Communauté introuvable.');
        }

        return $tenantId;
    }

    private function back(string $kind, string $message): Response
    {
        Session::flash($kind, $message);

        return Response::redirect(url(self::BACK));
    }
}
