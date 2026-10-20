<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakModReportRepository;
use App\Repositories\AtakRealismRepository;
use App\Repositories\AthenaAccountRepository;
use App\Repositories\GameAtakPairingRepository;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\UserRepository;
use App\Services\Game\GameAtakPairingService;
use App\Services\Game\GameAuthService;
use App\Services\Monitoring\AtakAuthFailureNotifier;
use App\Support\AtakDevicePresenter;
use App\Support\SteamId;
use PDO;

/**
 * ATAK d'un compte, vu depuis l'administration du site (/admin/users/{id}/edit).
 *
 * Lecture : état de la liaison jeu (Steam, compte Athena, sessions), terminaux avec leurs contrôles,
 * conflits Steam, blocages, journal de l'appareil, incidents du mod, demandes de liaison en attente.
 *
 * Actions : valider une demande de liaison pour ce compte, générer un code de liaison, forcer la
 * liaison d'un Steam (et d'un ATAK) sur ce compte, fermer les sessions de jeu, lever les blocages,
 * délier le Steam. La liaison du jeu repose sur users.steam_id (dans la communauté du compte) et
 * athena_accounts.steam_id (unique) : la liaison forcée aligne les deux et retire ce Steam partout ailleurs.
 */
final class AdminUserAtakService
{
    private ?PDO $pdo = null;

    public function __construct(
        private ?UserRepository $users = null,
        private ?AtakRealismRepository $realism = null,
        private ?AtakAdminControlRepository $control = null,
        private ?GameAtakPairingRepository $challenges = null,
        private ?TacticalGameLinkRepository $gameLinks = null,
    ) {
        $this->users ??= new UserRepository();
        $this->realism ??= new AtakRealismRepository();
        $this->control ??= new AtakAdminControlRepository();
        $this->challenges ??= new GameAtakPairingRepository();
        $this->gameLinks ??= new TacticalGameLinkRepository();
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // ================================================================= lecture

    /**
     * @return array<string, mixed>|null
     */
    public function overview(int $userId): ?array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return null;
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $steam = SteamId::normalize((string) ($user['steam_id'] ?? ''));

        $account = $this->rows(
            'SELECT a.id, a.public_id, a.email, a.steam_id, a.status FROM account_tenant_memberships m
             JOIN athena_accounts a ON a.id = m.account_id WHERE m.user_id = ? LIMIT 1',
            [$userId]
        )[0] ?? null;

        $sessions = $this->rows(
            'SELECT id, device_id, steam_id, mod_version, extension_version, created_at, last_seen_at, expires_at,
                    refresh_expires_at, revoked_at,
                    (revoked_at IS NULL AND refresh_expires_at > UTC_TIMESTAMP()) AS usable
             FROM game_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 8',
            [$userId]
        );

        $terminals = [];
        try {
            $terminals = $this->realism->listPhysicalTerminalsForUser($tenantId, $userId);
        } catch (\Throwable) {
        }
        foreach ($terminals as &$t) {
            $t['checks'] = AtakDevicePresenter::liaisonChecks($t);
            $t['verdict'] = AtakDevicePresenter::liaisonVerdict($t['checks']);
            $t['link'] = AtakDevicePresenter::linkState(isset($t['last_seen_at']) ? (string) $t['last_seen_at'] : null);
        }
        unset($t);
        $terminalUids = array_values(array_filter(array_map(static fn ($t) => (string) ($t['terminal_uid'] ?? ''), $terminals)));

        // Steam de ce compte tenu par d'autres fiches / comptes (empêche la liaison côté jeu).
        $conflicts = [];
        if ($steam !== null) {
            $conflicts = $this->rows(
                "SELECT u.id, u.tenant_id, t.name AS tenant_name, COALESCE(NULLIF(u.display_name, ''), u.callsign, u.email) AS label, 'fiche' AS kind
                 FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id
                 WHERE u.steam_id = ? AND u.id <> ? AND u.deleted_at IS NULL",
                [$steam, $userId]
            );
            if ($account !== null) {
                foreach ($this->rows(
                    "SELECT id, email AS label, 'compte' AS kind FROM athena_accounts WHERE steam_id = ? AND id <> ?",
                    [$steam, (int) $account['id']]
                ) as $r) {
                    $conflicts[] = $r + ['tenant_name' => null, 'tenant_id' => null];
                }
            }
        }

        $diagnosis = null;
        if ($steam !== null) {
            try {
                $diagnosis = (new AtakAuthFailureNotifier($this->pdo(), new AtakModReportRepository()))->diagnose($steam, $tenantId);
            } catch (\Throwable) {
            }
        }

        $activeBlock = null;
        $blocks = [];
        try {
            $activeBlock = $this->control->activeBlockFor($tenantId, $userId, $steam, $terminalUids[0] ?? null);
            $blocks = $this->control->blocksFor($userId, $steam, null, 0);
        } catch (\Throwable) {
        }

        $logs = [];
        if ($steam !== null || $terminalUids !== []) {
            $where = [];
            $params = [$tenantId];
            if ($steam !== null) {
                $where[] = 'steam_uid = ?';
                $params[] = $steam;
            }
            if ($terminalUids !== []) {
                $where[] = 'terminal_uid IN (' . implode(',', array_fill(0, count($terminalUids), '?')) . ')';
                array_push($params, ...$terminalUids);
            }
            $logs = $this->rows(
                'SELECT terminal_uid, level, channel, message, logged_at FROM atak_device_logs
                 WHERE tenant_id = ? AND (' . implode(' OR ', $where) . ') ORDER BY logged_at DESC LIMIT 25',
                $params
            );
        }

        $incidents = $steam === null ? [] : $this->rows(
            'SELECT id, severity, workflow_status, channel, message, mod_version, extension_version, hit_count, last_seen_at
             FROM atak_mod_reports WHERE steam_uid = ? ORDER BY last_seen_at DESC LIMIT 8',
            [$steam]
        );

        // Demandes de liaison en cours (code affiché dans Arma) : celles de ce Steam d'abord.
        $pending = $this->rows(
            "SELECT id, steam_id, terminal_uid, mod_version, created_at, expires_at,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), expires_at) AS left_s
             FROM game_atak_pair_challenges
             WHERE status = 'pending' AND expires_at > UTC_TIMESTAMP()
             ORDER BY (steam_id = ?) DESC, id DESC LIMIT 15",
            [$steam ?? '']
        );
        foreach ($pending as &$p) {
            $owner = SteamId::normalize((string) ($p['steam_id'] ?? '')) !== null
                ? $this->rows(
                    "SELECT u.id, COALESCE(NULLIF(u.display_name, ''), u.callsign, u.email) AS label, t.name AS tenant_name
                     FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.steam_id = ? AND u.deleted_at IS NULL ORDER BY u.id DESC LIMIT 1",
                    [(string) $p['steam_id']]
                )[0] ?? null
                : null;
            $p['current_owner'] = $owner;
            $p['matches_user'] = $steam !== null && (string) $p['steam_id'] === $steam;
        }
        unset($p);

        $linkCodes = $this->rows(
            'SELECT code, expires_at, redeemed_at, redeemed_steam_uid, created_at,
                    (redeemed_at IS NULL AND expires_at > UTC_TIMESTAMP()) AS usable
             FROM tactical_game_link_codes WHERE user_id = ? ORDER BY id DESC LIMIT 3',
            [$userId]
        );

        $actions = [];
        try {
            $actions = $this->control->recentActions(12, $userId, null, 0);
        } catch (\Throwable) {
        }

        $usable = array_filter($sessions, static fn ($s) => (int) ($s['usable'] ?? 0) === 1);

        return [
            'user' => $user,
            'tenant_id' => $tenantId,
            'steam' => $steam,
            'account' => $account,
            'sessions' => $sessions,
            'sessions_usable' => count($usable),
            'terminals' => $terminals,
            'conflicts' => $conflicts,
            'diagnosis' => $diagnosis,
            'active_block' => $activeBlock,
            'blocks' => $blocks,
            'logs' => $logs,
            'incidents' => $incidents,
            'pending' => $pending,
            'link_codes' => $linkCodes,
            'actions' => $actions,
            'verdict' => $this->verdict($user, $steam, $account, $conflicts, $activeBlock, count($usable), $terminals),
        ];
    }

    /**
     * Synthèse lisible : « liaison OK / à réparer » et ce qui bloque, dans l'ordre où le jeu le vérifie.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed>|null $account
     * @param list<array<string, mixed>> $conflicts
     * @param array<string, mixed>|null $block
     * @param list<array<string, mixed>> $terminals
     * @return array{state: string, problems: list<string>}
     */
    private function verdict(array $user, ?string $steam, ?array $account, array $conflicts, ?array $block, int $usableSessions, array $terminals): array
    {
        $problems = [];
        $status = strtolower((string) ($user['status'] ?? ''));
        if (in_array($status, ['banned', 'disabled', 'suspended', 'deleted', 'inactive'], true)) {
            $problems[] = 'Compte « ' . $status . ' » : le jeu refuse toute connexion.';
        }
        if ($steam === null) {
            $problems[] = 'Aucun Steam sur la fiche : le jeu répondra « Steam non relié à Athena ».';
        }
        if ($account !== null && ($account['status'] ?? 'active') !== 'active') {
            $problems[] = 'Compte Athena désactivé.';
        }
        if ($steam !== null && $account !== null && (string) ($account['steam_id'] ?? '') !== '' && (string) $account['steam_id'] !== $steam) {
            $problems[] = 'Le compte Athena porte un autre Steam (' . $account['steam_id'] . ') que la fiche : la reprise de session échouera.';
        }
        if ($conflicts !== []) {
            $problems[] = 'Ce Steam est aussi rattaché à ' . count($conflicts) . ' autre(s) fiche(s) ou compte(s) : la liaison peut partir sur le mauvais compte.';
        }
        if ($block !== null) {
            $problems[] = 'Blocage ATAK actif' . (trim((string) ($block['reason'] ?? '')) !== '' ? ' : ' . $block['reason'] : '') . '.';
        }
        foreach ($terminals as $t) {
            if (($t['compromise_state'] ?? 'none') !== 'none' && ($t['compromise_state'] ?? '') !== '') {
                $problems[] = 'ATAK « ' . ($t['terminal_label'] ?? $t['terminal_uid']) . ' » déclaré ' . $t['compromise_state'] . '.';
            }
        }
        $state = $problems === [] ? ($usableSessions > 0 ? 'ok' : 'idle') : 'bad';

        return ['state' => $state, 'problems' => $problems];
    }

    // ================================================================= actions

    /**
     * Valide une demande de liaison (code affiché dans Arma, ou demande choisie dans la liste) pour ce compte.
     *
     * @return array{ok: bool, message: string}
     */
    public function approveChallenge(int $userId, ?int $challengeId, string $userCode, int $actorId, string $actorLabel): array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $tenantId = (int) $user['tenant_id'];

        if (trim($userCode) !== '' && ($challengeId ?? 0) < 1) {
            /** @var GameAtakPairingService $pairing */
            $pairing = Container::get(GameAtakPairingService::class);
            $res = $pairing->approveFromWeb($userCode, $userId, $tenantId);
            if (empty($res['ok'])) {
                return ['ok' => false, 'message' => (string) ($res['payload']['message'] ?? 'Code refusé.')];
            }
            $row = $this->challenges->findById((int) ($res['payload']['challenge_id'] ?? 0)) ?? [];
            // La validation standard ne nettoie que la communauté du compte : on retire aussi ce Steam ailleurs.
            $codeSteam = SteamId::normalize((string) ($row['steam_id'] ?? ''));
            $account = $codeSteam !== null ? $this->auth()->ensureAccountForUser($userId, $tenantId) : null;
            if ($codeSteam !== null && $account !== null) {
                $this->bindSteam($this->users->findById($userId, null) ?? $user, (int) $account['id'], $codeSteam);
            }
            $this->afterLink($user, SteamId::normalize((string) ($row['steam_id'] ?? '')), (string) ($row['terminal_uid'] ?? ''), $actorId, $actorLabel, 'link_approve', 'Demande de liaison validée par l’administration (code saisi).');

            return ['ok' => true, 'message' => 'Liaison validée : le jeu termine la connexion tout seul dans les secondes qui viennent.'];
        }

        $row = $challengeId !== null ? $this->challenges->findById($challengeId) : null;
        if ($row === null || ($row['status'] ?? '') !== 'pending' || strtotime((string) $row['expires_at'] . ' UTC') <= time()) {
            return ['ok' => false, 'message' => 'Cette demande de liaison a expiré ou a déjà été traitée. Demandez au joueur d’en relancer une dans Arma.'];
        }
        $steam = SteamId::normalize((string) ($row['steam_id'] ?? ''));
        $block = $this->control->activeBlockFor($tenantId, $userId, $steam, (string) ($row['terminal_uid'] ?? '') ?: null);
        if ($block !== null) {
            return ['ok' => false, 'message' => 'Un blocage ATAK actif vise ce joueur : levez-le avant de valider la liaison.'];
        }
        $account = $this->auth()->ensureAccountForUser($userId, $tenantId);
        if ($account === null) {
            return ['ok' => false, 'message' => 'Impossible de créer le compte Athena de cette fiche.'];
        }
        if ($steam !== null) {
            $this->bindSteam($user, (int) $account['id'], $steam);
        }
        if (!$this->challenges->approve((int) $row['id'], (int) $account['id'], $userId, $tenantId)) {
            return ['ok' => false, 'message' => 'La demande vient d’expirer ou d’être utilisée.'];
        }
        $this->afterLink($user, $steam, (string) ($row['terminal_uid'] ?? ''), $actorId, $actorLabel, 'link_approve', 'Demande de liaison #' . $row['id'] . ' validée par l’administration.');

        return ['ok' => true, 'message' => 'Liaison validée : le jeu termine la connexion tout seul dans les secondes qui viennent.'];
    }

    /**
     * Code de liaison à saisir dans Arma, généré pour ce compte (30 min).
     *
     * @return array{ok: bool, message: string, code?: string}
     */
    public function generateLinkCode(int $userId, int $actorId, string $actorLabel): array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $row = $this->gameLinks->create((int) $user['tenant_id'], $userId);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Le code n’a pas pu être généré.'];
        }
        $this->control->log(['tenant_id' => $user['tenant_id'], 'user_id' => $userId, 'display_name' => $user['display_name'] ?? ''], 'link_code', 'Code de liaison généré par l’administration.', $actorId, $actorLabel);

        return ['ok' => true, 'message' => 'Code de liaison : ' . $row['code'] . ' (valable 30 min). Le joueur le saisit dans Arma, menu Liaison Athena.', 'code' => (string) $row['code']];
    }

    /**
     * Liaison forcée : ce Steam (et éventuellement cet ATAK) passe sur ce compte, et nulle part ailleurs.
     *
     * @return array{ok: bool, message: string}
     */
    public function forceLink(int $userId, string $steamRaw, string $terminalUid, bool $liftBlocks, int $actorId, string $actorLabel): array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $tenantId = (int) $user['tenant_id'];
        $steam = self::parseSteam($steamRaw) ?? SteamId::normalize((string) ($user['steam_id'] ?? ''));
        if ($steam === null) {
            return ['ok' => false, 'message' => 'Indiquez un identifiant Steam valide (SteamID64 ou adresse du profil /profiles/7656…).'];
        }
        $account = $this->auth()->ensureAccountForUser($userId, $tenantId);
        if ($account === null) {
            return ['ok' => false, 'message' => 'Impossible de créer le compte Athena de cette fiche.'];
        }
        $moved = $this->bindSteam($user, (int) $account['id'], $steam);

        $uid = trim($terminalUid);
        if ($uid !== '') {
            try {
                // Fiche existante : on ne touche qu'au rattachement (upsertTerminal réécrirait libellé et type).
                $st = $this->pdo()->prepare(
                    "UPDATE atak_terminals SET user_id = ?, status = 'active', linked_at = COALESCE(linked_at, UTC_TIMESTAMP())
                     WHERE tenant_id = ? AND terminal_uid = ?"
                );
                $st->execute([$userId, $tenantId, $uid]);
                if ($st->rowCount() === 0 && $this->rows('SELECT 1 FROM atak_terminals WHERE tenant_id = ? AND terminal_uid = ?', [$tenantId, $uid]) === []) {
                    $this->realism->upsertTerminal($tenantId, [
                        'terminal_uid' => $uid,
                        'user_id' => $userId,
                        'status' => 'active',
                        'terminal_label' => 'ATAK ' . ((string) ($user['callsign'] ?? '') ?: '#' . $userId),
                        'operator_callsign' => (string) ($user['callsign'] ?? ''),
                    ]);
                    $this->pdo()->prepare('UPDATE atak_terminals SET linked_at = UTC_TIMESTAMP() WHERE tenant_id = ? AND terminal_uid = ?')
                        ->execute([$tenantId, $uid]);
                }
            } catch (\Throwable) {
            }
        }

        $lifted = 0;
        if ($liftBlocks) {
            $lifted = $this->liftActiveBlocks($user, $steam, $actorId);
        }

        $detail = 'Liaison forcée du Steam ' . $steam . ($uid !== '' ? ' et de l’ATAK ' . $uid : '')
            . ($moved !== [] ? ' — retiré de ' . count($moved) . ' autre(s) fiche(s)/compte(s)' : '')
            . ($lifted > 0 ? ' — ' . $lifted . ' blocage(s) levé(s)' : '') . '.';
        $this->afterLink($user, $steam, $uid, $actorId, $actorLabel, 'link_force', $detail);

        return ['ok' => true, 'message' => $detail . ' Le joueur relance la connexion à Athena dans Arma (ou redémarre le jeu) : elle aboutit sans code.'];
    }

    /** @return array{ok: bool, message: string} */
    public function revokeSessions(int $userId, int $actorId, string $actorLabel): array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $n = $this->control->revokeGameSessions(0, $userId);
        $this->control->notifyPlayer(['tenant_id' => $user['tenant_id'], 'user_id' => $userId, 'steam_id' => $user['steam_id'] ?? ''], 'reset', 'Reconnexion demandée', 'L’administration a fermé vos sessions de jeu : reconnectez-vous à Athena.', $actorId);
        $this->control->log(['tenant_id' => $user['tenant_id'], 'user_id' => $userId, 'display_name' => $user['display_name'] ?? ''], 'sessions', $n . ' session(s) de jeu fermée(s) depuis la fiche compte.', $actorId, $actorLabel);

        return ['ok' => true, 'message' => $n . ' session(s) de jeu fermée(s). Le jeu se reconnecte avec le Steam lié.'];
    }

    /** @return array{ok: bool, message: string} */
    public function liftBlocks(int $userId, int $actorId, string $actorLabel): array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $n = $this->liftActiveBlocks($user, SteamId::normalize((string) ($user['steam_id'] ?? '')), $actorId);
        $this->control->log(['tenant_id' => $user['tenant_id'], 'user_id' => $userId, 'display_name' => $user['display_name'] ?? ''], 'unblock', $n . ' blocage(s) levé(s) depuis la fiche compte.', $actorId, $actorLabel);

        return ['ok' => true, 'message' => $n > 0 ? $n . ' blocage(s) levé(s).' : 'Aucun blocage actif.'];
    }

    /** @return array{ok: bool, message: string} */
    public function unlinkSteam(int $userId, int $actorId, string $actorLabel): array
    {
        $user = $this->users->findById($userId, null);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $steam = SteamId::normalize((string) ($user['steam_id'] ?? ''));
        try {
            $this->pdo()->prepare('UPDATE users SET steam_id = NULL WHERE id = ?')->execute([$userId]);
            $this->pdo()->prepare('UPDATE game_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL')->execute([$userId]);
            $acc = $this->rows('SELECT account_id FROM account_tenant_memberships WHERE user_id = ?', [$userId]);
            foreach ($acc as $a) {
                $this->pdo()->prepare('UPDATE athena_accounts SET steam_id = NULL WHERE id = ?')->execute([(int) $a['account_id']]);
                $this->pdo()->prepare('UPDATE game_device_pairings SET revoked_at = NOW() WHERE account_id = ? AND revoked_at IS NULL')->execute([(int) $a['account_id']]);
            }
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'La liaison n’a pas pu être retirée.'];
        }
        $this->control->log(['tenant_id' => $user['tenant_id'], 'user_id' => $userId, 'display_name' => $user['display_name'] ?? ''], 'unlink', 'Steam ' . ($steam ?? '—') . ' délié depuis la fiche compte (sessions fermées).', $actorId, $actorLabel);

        return ['ok' => true, 'message' => 'Steam délié et sessions de jeu fermées. Le joueur devra refaire la liaison.'];
    }

    // ================================================================= interne

    /**
     * Pose le Steam sur la fiche et sur son compte Athena, après l'avoir retiré de toute autre fiche
     * (toutes communautés : la reconnexion par Steam cherche dans toutes) et de tout autre compte Athena
     * (colonne unique). Les sessions des anciens détenteurs sont fermées.
     *
     * @param array<string, mixed> $user
     * @return list<string> détenteurs précédents
     */
    private function bindSteam(array $user, int $accountId, string $steam): array
    {
        $userId = (int) $user['id'];
        $moved = [];
        foreach ($this->rows('SELECT id, tenant_id FROM users WHERE steam_id = ? AND id <> ?', [$steam, $userId]) as $o) {
            $this->pdo()->prepare('UPDATE users SET steam_id = NULL WHERE id = ? AND steam_id = ?')->execute([(int) $o['id'], $steam]);
            $this->control->revokeGameSessions(0, (int) $o['id']);
            $moved[] = 'fiche #' . $o['id'];
        }
        foreach ($this->rows('SELECT id FROM athena_accounts WHERE steam_id = ? AND id <> ?', [$steam, $accountId]) as $a) {
            $this->pdo()->prepare('UPDATE athena_accounts SET steam_id = NULL WHERE id = ?')->execute([(int) $a['id']]);
            $this->pdo()->prepare('UPDATE game_sessions SET revoked_at = NOW() WHERE account_id = ? AND revoked_at IS NULL')->execute([(int) $a['id']]);
            $moved[] = 'compte #' . $a['id'];
        }
        if (SteamId::normalize((string) ($user['steam_id'] ?? '')) !== $steam) {
            $this->users->update($userId, (int) $user['tenant_id'], ['steam_id' => $steam]);
        }
        // Aligne le compte Athena (et ferme ses sessions : reconnexion propre avec le bon Steam).
        (new AthenaAccountRepository())->replaceSteamId($accountId, $steam);

        return $moved;
    }

    /** @param array<string, mixed> $user */
    private function liftActiveBlocks(array $user, ?string $steam, int $actorId): int
    {
        $n = 0;
        foreach ($this->control->blocksFor((int) $user['id'], $steam, null, 0) as $b) {
            $active = empty($b['lifted_at']) && (empty($b['expires_at']) || strtotime((string) $b['expires_at'] . ' UTC') > time());
            if ($active && $this->control->liftBlock((int) $b['id'], $actorId)) {
                $n++;
            }
        }

        return $n;
    }

    /** @param array<string, mixed> $user */
    private function afterLink(array $user, ?string $steam, string $terminalUid, int $actorId, string $actorLabel, string $action, string $detail): void
    {
        $this->control->log([
            'tenant_id' => $user['tenant_id'],
            'user_id' => $user['id'],
            'terminal_uid' => $terminalUid !== '' ? $terminalUid : null,
            'display_name' => $user['display_name'] ?? '',
            'user_callsign' => $user['callsign'] ?? '',
        ], $action, $detail, $actorId, $actorLabel);
        $this->control->notifyPlayer([
            'tenant_id' => $user['tenant_id'],
            'user_id' => $user['id'],
            'steam_id' => $steam ?? '',
            'terminal_uid' => $terminalUid,
        ], 'message', 'Liaison Athena', 'Votre ATAK a été relié à votre compte par l’administration.', $actorId);
    }

    private function auth(): GameAuthService
    {
        return Container::get(GameAuthService::class);
    }

    /** SteamID64, adresse de profil /profiles/7656…, ou format accepté par SteamId::normalize. */
    public static function parseSteam(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('#/profiles/(7656\d{13})#', $raw, $m) === 1) {
            return $m[1];
        }

        return SteamId::normalize($raw);
    }
}
