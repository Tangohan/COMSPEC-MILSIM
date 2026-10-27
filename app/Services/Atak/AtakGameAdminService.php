<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Core\Gate;
use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Repositories\UserRepository;
use App\Services\Rbac\RbacService;

/**
 * App « Admin » de l'ATAK natif (COMSPEC Link « GameAdmin.Fleet » / « GameAdmin.Action ») : le parc ATAK de la
 * communauté vu et géré depuis le jeu, avec les mêmes règles que /back-office/atak/parc-atak.
 * Réservé au joueur connecté par sa propre session de jeu qui est gestionnaire de la communauté
 * (admin.organization / admin.access) ou administrateur de la plateforme. La clé d'accès commune d'un serveur
 * ne suffit jamais : elle ne dit pas qui joue.
 * La gestion peut bloquer dans sa communauté, lever ses blocages, déconnecter, imposer un message, déclarer ou annuler
 * une compromission. L'administrateur de la plateforme peut en plus lever un blocage posé pour toutes les communautés.
 * Le retrait définitif et le blocage toutes communautés restent sur le site.
 */
final class AtakGameAdminService
{
    /** Fiches renvoyées par page (la réponse passe par le rappel de la DLL, qui doit rester court). */
    public const PAGE = 40;

    public const ACTIONS = ['block', 'unblock', 'disconnect', 'message', 'compromise', 'undo_compromise'];

    /** Comptes sans accès (users.status). */
    private const CLOSED = ['banned', 'deleted', 'disabled', 'suspended', 'inactive'];

    public function __construct(
        private Fleet $fleet = new Fleet(),
        private ?UserRepository $users = null,
    ) {}

    /**
     * Droit du joueur sur le parc de la communauté : "platform", "manager" ou null.
     *
     * @param array<string, mixed>|null $raw ligne users déjà lue (tests)
     */
    public function role(int $tenantId, ?int $userId, ?array $raw = null): ?string
    {
        if ($tenantId < 1 || $userId === null || $userId < 1) {
            return null;
        }
        $users = $this->users ??= new UserRepository();
        $raw ??= $users->findById($userId);
        if ($raw === null || in_array((string) ($raw['status'] ?? 'active'), self::CLOSED, true)) {
            return null;
        }
        $member = $users->findById($userId, $tenantId);
        $gate = Gate::getInstance();
        $before = [$gate->permissionSlugs(), $gate->getUnitPermissionMap(), $gate->isPlatformAdmin()];
        try {
            (new RbacService())->setPermissionsForGateFromUserRow(($member ?? $raw) + ['tenant_id' => $tenantId], $users);
            if ($gate->isPlatformAdmin() || $gate->allows('admin.system')) {
                return 'platform';
            }
            if ($member === null) {
                return null;
            }

            return $gate->allows('admin.organization') || $gate->allows('admin.access') ? 'manager' : null;
        } catch (\Throwable) {
            return null;
        } finally {
            // Requête d'API : on rend la porte telle qu'on l'a trouvée.
            $gate->setFullRbacState($before[0], $before[1]);
            $gate->setPlatformAdmin($before[2]);
        }
    }

    /**
     * ATAK de la communauté, plus récents d'abord.
     *
     * @return array{total: int, rows: list<array<string, mixed>>}
     */
    public function fleet(int $tenantId, string $search = '', int $offset = 0): array
    {
        $this->fleet->ready();
        $terminals = $this->fleet->listTerminals($search, $tenantId, 2000);
        $total = count($terminals);
        $terminals = array_slice($terminals, max(0, $offset), self::PAGE);
        $metrics = $this->fleet->metricsForUsers(array_map(static fn (array $t): int => (int) ($t['user_id'] ?? 0), $terminals));
        $blocks = $this->fleet->activeBlocks();
        $pending = $this->fleet->pendingCountByUser();
        $now = time();
        $rows = [];
        foreach ($terminals as $t) {
            $key = $tenantId . ':' . (int) ($t['user_id'] ?? 0);
            $block = Fleet::blockMatching($t, $blocks);
            $seen = \App\Support\UtcDateTime::timestamp($t['last_seen_at'] ?? null);
            $rows[] = [
                'id' => (int) $t['id'],
                'label' => Fleet::playerLabel($t),
                'steam' => trim((string) ($t['steam_id'] ?? '')),
                'terminal' => (string) $t['terminal_uid'],
                'account' => (int) ($t['user_id'] ?? 0) > 0 ? (in_array((string) ($t['user_status'] ?? 'active'), self::CLOSED, true) ? 'disabled' : 'ok') : 'none',
                'compromise' => in_array((string) ($t['compromise_state'] ?? ''), ['captured', 'compromised'], true) ? (string) $t['compromise_state'] : 'none',
                'block_id' => $block !== null ? (int) ($block['id'] ?? 0) : 0,
                'block_scope' => $block === null ? '' : (($block['tenant_id'] ?? null) === null ? 'all' : 'community'),
                'block_reason' => $block !== null ? (string) ($block['reason'] ?? '') : '',
                'pending' => (int) ($t['user_id'] ?? 0) > 0 ? (int) ($pending[$key] ?? 0) : 0,
                'seen_ago' => $seen !== null ? max(0, $now - $seen) : -1,
                'play_h' => (int) round(((int) ($metrics[$key]['play'] ?? 0)) / 3600),
                'mod' => trim((string) ($t['mod_version'] ?? '')),
                'link' => trim((string) ($t['extension_version'] ?? '')),
            ];
        }

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Action sur un ATAK de la communauté.
     *
     * @param array{0: ?int, 1: string} $actor
     * @param array<string, mixed> $in
     * @return array{ok: bool, message: string}
     */
    public function act(int $tenantId, string $role, array $actor, string $action, array $in): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            return ['ok' => false, 'message' => 'Action inconnue.'];
        }
        if (!$this->fleet->ready()) {
            return ['ok' => false, 'message' => 'Tables du parc ATAK absentes sur le site.'];
        }
        [$actorId, $actorLabel] = $actor;
        $actorLabel .= ' (en jeu)';
        if ($action === 'unblock') {
            return $this->unblock($tenantId, $role, (int) ($in['block_id'] ?? 0), $actorId, $actorLabel);
        }
        $terminal = $this->fleet->findTerminal((int) ($in['terminal_id'] ?? 0));
        if ($terminal === null || (int) $terminal['tenant_id'] !== $tenantId) {
            return ['ok' => false, 'message' => 'ATAK introuvable dans la communauté.'];
        }
        $text = static fn (string $k, int $max): string => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);

        switch ($action) {
            case 'block':
                $days = max(0, min(3650, (int) ($in['days'] ?? 0)));
                $reason = $text('reason', 500);
                $id = $this->fleet->createBlock([
                    'tenant_id' => $tenantId,
                    'user_id' => ((int) ($terminal['user_id'] ?? 0)) ?: null,
                    'steam_uid' => trim((string) ($terminal['steam_id'] ?? '')) ?: null,
                    'terminal_uid' => (string) $terminal['terminal_uid'] !== '' ? (string) $terminal['terminal_uid'] : null,
                    'label' => Fleet::playerLabel($terminal),
                    'reason' => $reason,
                    'player_message' => $text('player_message', 500),
                    'expires_at' => $days > 0 ? gmdate('Y-m-d H:i:s', time() + $days * 86400) : null,
                    'actor_id' => $actorId,
                ]);
                if ($id < 1) {
                    return ['ok' => false, 'message' => 'Blocage impossible.'];
                }
                $closed = (int) ($terminal['user_id'] ?? 0) > 0 ? $this->fleet->revokeGameSessions($tenantId, (int) $terminal['user_id']) : 0;
                $this->fleet->log($terminal, 'block', implode(' · ', array_filter([
                    'Sa communauté', 'Depuis le jeu', $days > 0 ? $days . ' jour(s)' : 'Sans fin',
                    $reason !== '' ? 'Motif : ' . $reason : '', $closed > 0 ? $closed . ' session(s) de jeu fermée(s)' : '',
                ])), $actorId, $actorLabel);

                return ['ok' => true, 'message' => 'ATAK bloqué dans la communauté : le jeu est refusé à la prochaine connexion ou au prochain envoi.'];

            case 'disconnect':
                $n = $this->fleet->revokeGameSessions($tenantId, (int) ($terminal['user_id'] ?? 0));
                $this->fleet->log($terminal, 'sessions', $n . ' session(s) fermée(s) · depuis le jeu', $actorId, $actorLabel);

                return ['ok' => true, 'message' => $n . ' session(s) de jeu fermée(s). Le jeu se reconnecte tout seul s’il n’est pas bloqué.'];

            case 'message':
                $title = $text('title', 120);
                $body = $text('body', 2000);
                if ($title === '' || $body === '') {
                    return ['ok' => false, 'message' => 'Le titre et le texte du message sont obligatoires.'];
                }
                $level = isset(Fleet::LEVELS[(string) ($in['level'] ?? '')]) ? (string) $in['level'] : 'info';
                $id = $this->fleet->createMessage([
                    'tenant_id' => $tenantId,
                    'user_id' => ((int) ($terminal['user_id'] ?? 0)) ?: null,
                    'steam_uid' => trim((string) ($terminal['steam_id'] ?? '')) ?: null,
                    'terminal_uid' => (string) $terminal['terminal_uid'] !== '' ? (string) $terminal['terminal_uid'] : null,
                    'title' => $title,
                    'body' => $body,
                    'level' => $level,
                    'require_ack' => true,
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 72 * 3600),
                    'actor_id' => $actorId,
                ]);
                if ($id < 1) {
                    return ['ok' => false, 'message' => 'Message non enregistré.'];
                }
                $this->fleet->log($terminal, 'message', $title . ' · depuis le jeu', $actorId, $actorLabel);

                return ['ok' => true, 'message' => 'Message envoyé : il s’affiche dans l’ATAK du joueur (30 s au plus).'];

            case 'compromise':
                $state = ($in['state'] ?? '') === 'compromised' ? 'compromised' : 'captured';
                $reason = $text('reason', 255);
                $done = $this->fleet->markCompromised($terminal, $state, $reason, $state === 'compromised', $actorId, $actorLabel, 'ingame');
                $this->fleet->log($terminal, 'compromise', implode(' · ', array_filter([
                    Fleet::COMPROMISE_STATES[$state], 'Depuis le jeu', $reason !== '' ? 'Motif : ' . $reason : '',
                    $done['revoked'] > 0 ? $done['revoked'] . ' certificat(s) révoqué(s)' : '',
                ])), $actorId, $actorLabel);
                $this->journal($terminal, ($state === 'compromised' ? 'Appareil compromis' : 'Appareil capturé') . ' — déclaré en jeu par ' . $actorLabel, $state);
                $this->fleet->notifyPlayer($terminal, 'compromise', $state === 'compromised' ? 'ATAK compromis' : 'ATAK capturé',
                    $reason !== '' ? $reason : 'Déclarée par la gestion de la communauté', $actorId);

                return ['ok' => true, 'message' => ($state === 'compromised' ? 'ATAK déclaré compromis' : 'ATAK déclaré capturé')
                    . ($done['revoked'] > 0 ? ' : ' . $done['revoked'] . ' certificat(s) révoqué(s).' : '.')];

            case 'undo_compromise':
                if (in_array((string) ($terminal['compromise_state'] ?? 'none'), ['', 'none'], true)) {
                    return ['ok' => false, 'message' => 'Cet ATAK n’est pas compromis.'];
                }
                $done = $this->fleet->undoCompromise($terminal, null, true, 'Levée en jeu', $actorId, $actorLabel, 'ingame');
                $this->fleet->log($terminal, 'compromise_undo', implode(' · ', array_filter([
                    'Compromission en cours', 'Depuis le jeu', $done['restored'] > 0 ? $done['restored'] . ' certificat(s) rétabli(s)' : '',
                ])), $actorId, $actorLabel);
                if ($done['cleared']) {
                    $this->journal($terminal, 'Contrôle appareil rétabli en jeu par ' . $actorLabel, 'none');
                }
                if ($done['cleared'] || $done['restored'] > 0) {
                    $this->fleet->notifyPlayer($terminal, 'compromise_cleared', 'Compromission levée',
                        $done['restored'] > 0 ? $done['restored'] . ' certificat(s) rétabli(s)' : '', $actorId);
                }

                return ['ok' => true, 'message' => 'Compromission levée' . ($done['restored'] > 0 ? ' : ' . $done['restored'] . ' certificat(s) rétabli(s).' : '.')];
        }

        return ['ok' => false, 'message' => 'Action inconnue.'];
    }

    /** @return array{ok: bool, message: string} */
    private function unblock(int $tenantId, string $role, int $blockId, ?int $actorId, string $actorLabel): array
    {
        $block = $this->fleet->findBlock($blockId);
        if ($block === null) {
            return ['ok' => false, 'message' => 'Blocage introuvable ou déjà levé.'];
        }
        $blockTenant = $block['tenant_id'] ?? null;
        // La gestion lève les blocages de sa communauté ; ceux posés pour toutes les communautés restent à la plateforme.
        if ($blockTenant === null ? $role !== 'platform' : (int) $blockTenant !== $tenantId) {
            return ['ok' => false, 'message' => 'Ce blocage a été posé par l’administration de la plateforme : seule elle peut le lever.'];
        }
        if (!$this->fleet->liftBlock((int) $block['id'], $actorId)) {
            return ['ok' => false, 'message' => 'Blocage introuvable ou déjà levé.'];
        }
        $this->fleet->log($block, 'unblock', trim((string) ($block['reason'] ?? '') . ' · depuis le jeu', ' ·'), $actorId, $actorLabel);

        return ['ok' => true, 'message' => 'Blocage levé : le joueur peut se reconnecter.'];
    }

    /** Ligne dans le journal d'activité ATAK de la communauté (le poste de commandement voit le changement). */
    private function journal(array $terminal, string $label, string $state): void
    {
        try {
            (new \App\Services\Tactical\AtakActivityLogService())->record(
                (int) $terminal['tenant_id'],
                \App\Services\Tactical\AtakActivityLogService::AUTH_MAP_ID,
                \App\Services\Tactical\AtakActivityLogService::TYPE_INTEL,
                $label,
                Fleet::playerLabel($terminal),
                ['terminal_uid' => (string) $terminal['terminal_uid'], 'compromise_state' => $state, 'source' => 'ingame']
            );
        } catch (\Throwable) {
        }
    }
}
