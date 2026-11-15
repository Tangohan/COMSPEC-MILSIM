<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Core\Database;
use App\Repositories\DiscordBotRepository;
use App\Repositories\UserDiscordLinkRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * « Bureau des effectifs » côté Discord : ce que le bot peut dire, à qui, et quand.
 *
 * Règles qui ne bougent pas :
 *  - la communauté vient toujours du serveur Discord lié, jamais d'une saisie ;
 *  - la personne qui demande doit avoir lié son compte Athena, appartenir à cette communauté et avoir, sur le site, le droit
 *    de la commande (mêmes droits que le back-office) ;
 *  - aucune réponse ne contient d'état civil, d'e-mail, de photo, de papier ni de donnée SSE : indicatif ou pseudo, grade,
 *    statut, dates et compteurs seulement ;
 *  - Discord ne décide jamais d'un droit du site : les rôles Discord sont une copie du niveau du site, jamais l'inverse ;
 *  - toute commande est journalisée (sans contenu) et plafonnée.
 */
final class DiscordBotBureau
{
    /** Droits du site (mêmes slugs que le back-office) acceptés pour chaque commande ; vide = tout membre lié de la communauté. */
    public const RIGHTS = [
        'effectifs' => ['organization.effectifs.hub.view', 'personnel.profile.view', 'admin.organization'],
        'membre' => ['personnel.profile.view', 'admin.organization'],
        'candidatures' => ['organization.recruitment.manage', 'admin.organization'],
        'evenements' => [],
        'integration' => [],
    ];
    public const STAFF_INTEGRATION_RIGHTS = ['member_integration.view', 'member_integration.manage', 'admin.organization'];
    public const RATE_PER_MINUTE = 12;
    public const OPEN_APPLICATION_STATUSES = ['submitted', 'interview_scheduled', 'on_hold', 'acceptance_onboarding'];
    public const OPEN_INTEGRATION_STATUSES = ['to_start', 'in_progress', 'waiting_member', 'waiting_staff', 'blocked'];
    public const REMINDER_KINDS = ['applications_new', 'integration_validate', 'qualification_due', 'campaign', 'retro30', 'qualification_staff', 'remontee'];

    /** @var \Closure(int, list<string>): list<int> */
    private \Closure $permissionLookup;

    /**
     * @param (\Closure(int, list<string>): list<int>)|null $permissionLookup membres actifs de la communauté ayant un des droits
     */
    public function __construct(
        private ?PDO $pdo = null,
        private ?DiscordBotRepository $repo = null,
        private ?UserDiscordLinkRepository $links = null,
        ?\Closure $permissionLookup = null,
        private ?\Closure $siteUrl = null,
    ) {
        $this->permissionLookup = $permissionLookup ?? static fn (int $tenant, array $slugs): array => (new UserRepository())->listActiveUserIdsWithAnyPermissionSlug($tenant, $slugs);
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private function repo(): DiscordBotRepository
    {
        return $this->repo ??= new DiscordBotRepository($this->db());
    }

    private function url(string $path): string
    {
        return $this->siteUrl !== null ? ($this->siteUrl)($path) : url($path);
    }

    // ---- Commandes lancées par une personne sur Discord ------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     * @return array{ok:bool, error?:string, data?:array<string, mixed>}
     *   erreurs : unknown_command, not_linked, rate_limited, link_required, not_member, forbidden
     */
    public function command(string $op, string $guild, string $discordUser, array $args = [], ?int $now = null): array
    {
        if (!array_key_exists($op, self::RIGHTS)) {
            return ['ok' => false, 'error' => 'unknown_command'];
        }
        $tenant = $this->repo()->tenantForGuild($guild);
        if ($tenant === null) {
            return ['ok' => false, 'error' => 'not_linked'];
        }
        if ($this->repo()->recentCommands($discordUser, 60, $now) >= self::RATE_PER_MINUTE) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }
        $fail = function (string $error) use ($tenant, $guild, $discordUser, $op): array {
            $this->repo()->audit($tenant, $guild, $discordUser, $op, $error);

            return ['ok' => false, 'error' => $error];
        };
        $userId = ($this->links ??= new UserDiscordLinkRepository($this->pdo))->findUserIdByDiscordId($discordUser);
        if ($userId === null) {
            return $fail('link_required');
        }
        $st = $this->db()->prepare('SELECT id, tenant_id, status FROM users WHERE id = ?');
        $st->execute([$userId]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user) || (int) $user['tenant_id'] !== $tenant || (string) $user['status'] !== 'active') {
            return $fail('not_member');
        }
        if (self::RIGHTS[$op] !== [] && !in_array($userId, ($this->permissionLookup)($tenant, self::RIGHTS[$op]), true)) {
            return $fail('forbidden');
        }
        $data = match ($op) {
            'effectifs' => $this->effectifs($tenant),
            'membre' => $this->membre($tenant, (string) ($args['query'] ?? '')),
            'candidatures' => $this->candidatures($tenant),
            'evenements' => $this->evenements($tenant, $userId, $now),
            'integration' => $this->integration($tenant, $userId),
        };
        $this->repo()->audit($tenant, $guild, $discordUser, $op, 'ok');

        return ['ok' => true, 'data' => $data];
    }

    /** @return array<string, mixed> */
    public function effectifs(int $tenant): array
    {
        $today = date('Y-m-d');

        return [
            'active' => $this->count("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active' AND COALESCE(is_platform_admin, 0) = 0", [$tenant]),
            'pending' => $this->count("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'pending'", [$tenant]),
            'absent' => $this->count("SELECT COUNT(DISTINCT user_id) FROM personnel_absences WHERE tenant_id = ? AND status = 'active' AND starts_on <= ? AND (ends_on IS NULL OR ends_on >= ?)", [$tenant, $today, $today]),
            'applications' => $this->count('SELECT COUNT(*) FROM enlistments WHERE tenant_id = ? AND status IN (' . $this->in(self::OPEN_APPLICATION_STATUSES) . ')', [$tenant, ...self::OPEN_APPLICATION_STATUSES]),
            'integrations' => $this->count('SELECT COUNT(*) FROM member_integrations WHERE tenant_id = ? AND status IN (' . $this->in(self::OPEN_INTEGRATION_STATUSES) . ')', [$tenant, ...self::OPEN_INTEGRATION_STATUSES]),
            'link' => $this->url('back-office/ressources/effectifs'),
        ];
    }

    /** Fiche courte : indicatif ou pseudo, grade, statut, ancienneté, absence en cours. Jamais d'état civil ni d'e-mail. @return array<string, mixed> */
    public function membre(int $tenant, string $query): array
    {
        $query = trim(mb_substr($query, 0, 40));
        if (mb_strlen($query) < 2) {
            return ['members' => [], 'query' => $query];
        }
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
        $st = $this->db()->prepare(
            "SELECT u.id, u.callsign, u.display_name, u.status, u.created_at, g.name AS grade
             FROM users u LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id
             WHERE u.tenant_id = ? AND u.status = 'active' AND COALESCE(u.is_platform_admin, 0) = 0
               AND (u.callsign LIKE ? ESCAPE '!' OR u.display_name LIKE ? ESCAPE '!')
             ORDER BY u.callsign ASC, u.display_name ASC LIMIT 5"
        );
        $st->execute([$tenant, $like, $like]);
        $today = date('Y-m-d');
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'name' => (string) (($row['callsign'] ?? '') !== '' ? $row['callsign'] : $row['display_name']),
                'grade' => (string) ($row['grade'] ?? ''),
                'status' => (string) $row['status'],
                'since' => substr((string) $row['created_at'], 0, 10),
                'absent' => $this->count("SELECT COUNT(*) FROM personnel_absences WHERE tenant_id = ? AND user_id = ? AND status = 'active' AND starts_on <= ? AND (ends_on IS NULL OR ends_on >= ?)", [$tenant, (int) $row['id'], $today, $today]) > 0,
            ];
        }

        return ['members' => $out, 'query' => $query, 'link' => $this->url('back-office/ressources/effectifs')];
    }

    /** Pas de nom ni d'e-mail du candidat : référence, indicatif, statut, date ; la décision se prend sur le site. @return array<string, mixed> */
    public function candidatures(int $tenant): array
    {
        $in = $this->in(self::OPEN_APPLICATION_STATUSES);
        $st = $this->db()->prepare("SELECT id, callsign, status, created_at FROM enlistments WHERE tenant_id = ? AND status IN ({$in}) ORDER BY created_at DESC LIMIT 10");
        $st->execute([$tenant, ...self::OPEN_APPLICATION_STATUSES]);
        $items = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = ['id' => (int) $r['id'], 'callsign' => (string) ($r['callsign'] ?? ''), 'status' => (string) $r['status'],
                'date' => substr((string) $r['created_at'], 0, 10), 'link' => $this->url('back-office/recruitments/' . (int) $r['id'])];
        }

        return ['items' => $items, 'total' => $this->count("SELECT COUNT(*) FROM enlistments WHERE tenant_id = ? AND status IN ({$in})", [$tenant, ...self::OPEN_APPLICATION_STATUSES]),
            'link' => $this->url('back-office/recruitments')];
    }

    /** @return array<string, mixed> */
    public function evenements(int $tenant, int $userId, ?int $now = null): array
    {
        $st = $this->db()->prepare(
            'SELECT e.id, e.title, e.starts_at, r.status AS rsvp
             FROM community_events e LEFT JOIN community_event_rsvps r ON r.event_id = e.id AND r.user_id = ?
             WHERE e.tenant_id = ? AND e.cancelled_at IS NULL AND e.starts_at >= ? ORDER BY e.starts_at ASC LIMIT 5'
        );
        $st->execute([$userId, $tenant, date('Y-m-d H:i:s', $now ?? time())]);
        $items = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = ['title' => mb_substr((string) $r['title'], 0, 120), 'starts_at' => (string) $r['starts_at'], 'rsvp' => (string) ($r['rsvp'] ?? ''),
                'link' => $this->url('evenements') . '#event-' . (int) $r['id']];
        }

        return ['items' => $items, 'link' => $this->url('evenements')];
    }

    /** Mon intégration ; pour la gestion, le nombre d'intégrations qui attendent l'équipe. @return array<string, mixed> */
    public function integration(int $tenant, int $userId): array
    {
        $in = $this->in(self::OPEN_INTEGRATION_STATUSES);
        $st = $this->db()->prepare("SELECT status, progress_percent, next_appointment_at, overdue_count FROM member_integrations WHERE tenant_id = ? AND user_id = ? AND status IN ({$in}) ORDER BY id DESC LIMIT 1");
        $st->execute([$tenant, $userId, ...self::OPEN_INTEGRATION_STATUSES]);
        $mine = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $data = ['mine' => $mine === null ? null : [
            'status' => (string) $mine['status'], 'percent' => (int) $mine['progress_percent'],
            'next_appointment' => (string) ($mine['next_appointment_at'] ?? ''), 'overdue' => (int) $mine['overdue_count'],
        ], 'link' => $this->url('jnet/moi')];
        if (in_array($userId, ($this->permissionLookup)($tenant, self::STAFF_INTEGRATION_RIGHTS), true)) {
            $data['staff_waiting'] = $this->count("SELECT COUNT(*) FROM member_integrations WHERE tenant_id = ? AND status = 'waiting_staff'", [$tenant]);
            $data['staff_link'] = $this->url('back-office/integration-membres');
        }

        return $data;
    }

    // ---- Appels du processus du bot (sans personne derrière) ---------------------------------------------------------

    /**
     * Rappels à publier pour un serveur : salon de l'équipe ou message privé d'un membre lié. Le bot les envoie puis les
     * acquitte ; un rappel acquitté ne revient pas.
     *
     * @return list<array<string, mixed>>
     */
    public function reminders(string $guild, ?int $now = null, int $limit = 25): array
    {
        $tenant = $this->repo()->tenantForGuild($guild);
        if ($tenant === null) {
            return [];
        }
        $s = $this->repo()->settings($tenant);
        // Remontées du renseignement : réglage à part (salon, événements, rôle), indépendant des rappels de gestion.
        $out = [];
        try {
            foreach ((new \App\Services\Intel\IntelIntakeDiscord($this->db(), $this->repo(), null, fn (string $p): string => $this->url($p)))->pending($tenant, $now, 10) as $m) {
                $out[] = ['kind' => 'remontee', 'ref' => $m['ref'], 'target' => 'channel', 'to' => $m['to'], 'data' => $m['data']];
            }
        } catch (\Throwable) {
        }
        if (empty($s['reminders_enabled'])) {
            return $out;
        }
        $staffChannel = (string) ($s['applications_channel_id'] ?: $s['log_channel_id'] ?: $s['announce_channel_id'] ?: '');
        $add = function (string $kind, string $ref, string $target, string $to, array $data) use (&$out, $tenant, $limit): void {
            if (count($out) >= $limit || $to === '' || $this->repo()->reminderSent($tenant, $kind, $ref)) {
                return;
            }
            $out[] = ['kind' => $kind, 'ref' => $ref, 'target' => $target, 'to' => $to, 'data' => $data];
        };
        $ts = $now ?? time();
        if (!empty($s['test_requested_at']) && $staffChannel !== '') {
            $out[] = ['kind' => 'test', 'ref' => (string) $s['test_requested_at'], 'target' => 'channel', 'to' => $staffChannel, 'data' => []];
        }
        $week = date('o-\WW', $ts);

        $in = $this->in(self::OPEN_APPLICATION_STATUSES);
        $st = $this->db()->prepare("SELECT id, callsign, created_at FROM enlistments WHERE tenant_id = ? AND status IN ({$in}) AND created_at >= ? ORDER BY created_at ASC LIMIT 20");
        $st->execute([$tenant, ...self::OPEN_APPLICATION_STATUSES, date('Y-m-d H:i:s', $ts - 7 * 86400)]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $add('applications_new', (string) $r['id'], 'channel', $staffChannel, ['id' => (int) $r['id'], 'callsign' => (string) ($r['callsign'] ?? ''),
                'link' => $this->url('back-office/recruitments/' . (int) $r['id'])]);
        }

        $n = $this->count("SELECT COUNT(*) FROM member_integrations WHERE tenant_id = ? AND status = 'waiting_staff'", [$tenant]);
        if ($n > 0) {
            $add('integration_validate', $week, 'channel', $staffChannel, ['count' => $n, 'link' => $this->url('back-office/integration-membres')]);
        }

        // Bilan 30 jours : candidatures acceptées depuis plus de 30 jours (et moins de 180) sans bilan rédigé.
        $n = $this->count(
            "SELECT COUNT(*) FROM enlistments e WHERE e.tenant_id = ? AND e.status = 'reviewed' AND e.reviewed_at <= ? AND e.reviewed_at >= ?
               AND NOT EXISTS (SELECT 1 FROM enlistment_retro_feedbacks f WHERE f.enlistment_id = e.id AND f.tenant_id = e.tenant_id)",
            [$tenant, date('Y-m-d H:i:s', $ts - 30 * 86400), date('Y-m-d H:i:s', $ts - 180 * 86400)]
        );
        if ($n > 0) {
            $add('retro30', $week, 'channel', $staffChannel, ['count' => $n, 'link' => $this->url('back-office/recruitments')]);
        }

        // Qualifications qui arrivent à échéance dans 14 jours : message privé au membre lié, total pour la gestion.
        $until = date('Y-m-d', $ts + 14 * 86400);
        $st = $this->db()->prepare(
            "SELECT q.id, q.qualification_name, q.expires_at, l.discord_user_id FROM personnel_qualifications q
             JOIN users u ON u.id = q.user_id AND u.tenant_id = ? AND u.status = 'active'
             JOIN user_discord_links l ON l.user_id = u.id
             WHERE q.expires_at IS NOT NULL AND q.expires_at >= ? AND q.expires_at <= ? AND q.status <> 'expired' ORDER BY q.expires_at ASC LIMIT 50"
        );
        $st->execute([$tenant, date('Y-m-d', $ts), $until]);
        $due = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($due as $r) {
            $add('qualification_due', $r['id'] . ':' . $r['expires_at'], 'dm', (string) $r['discord_user_id'], ['name' => mb_substr((string) $r['qualification_name'], 0, 80), 'expires_at' => (string) $r['expires_at']]);
        }
        $nq = $this->count("SELECT COUNT(*) FROM personnel_qualifications q JOIN users u ON u.id = q.user_id AND u.tenant_id = ? AND u.status = 'active' WHERE q.expires_at IS NOT NULL AND q.expires_at >= ? AND q.expires_at <= ?", [$tenant, date('Y-m-d', $ts), $until]);
        if ($nq > 0) {
            $add('qualification_staff', $week, 'channel', $staffChannel, ['count' => $nq, 'link' => $this->url('back-office/ressources/effectifs')]);
        }

        // Campagne de fiabilisation : J-14, J-3, jour J, pour les membres qui n'ont pas confirmé et ont lié leur compte Discord.
        $st = $this->db()->prepare("SELECT id, title, due_on FROM data_campaigns WHERE tenant_id = ? AND status = 'open' AND due_on IS NOT NULL");
        $st->execute([$tenant]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $slot = self::campaignSlot((string) $c['due_on'], $ts);
            if ($slot === null) {
                continue;
            }
            $m = $this->db()->prepare(
                'SELECT m.user_id, l.discord_user_id FROM data_campaign_members m JOIN user_discord_links l ON l.user_id = m.user_id
                 JOIN users u ON u.id = m.user_id AND u.tenant_id = m.tenant_id AND u.status = \'active\'
                 WHERE m.campaign_id = ? AND m.tenant_id = ? AND m.confirmed_at IS NULL LIMIT 100'
            );
            $m->execute([(int) $c['id'], $tenant]);
            foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $add('campaign', $c['id'] . ':' . $r['user_id'] . ':' . $slot, 'dm', (string) $r['discord_user_id'],
                    ['title' => mb_substr((string) $c['title'], 0, 120), 'due_on' => (string) $c['due_on'], 'slot' => $slot, 'link' => $this->url('jnet/moi')]);
            }
        }

        return $out;
    }

    /** J-14, J-3 ou jour J : le dernier créneau atteint, ou null avant J-14 et après l'échéance. */
    public static function campaignSlot(string $dueOn, int $now): ?string
    {
        $due = strtotime($dueOn . ' 00:00:00');
        if ($due === false) {
            return null;
        }
        $days = (int) floor(($due - strtotime(date('Y-m-d', $now) . ' 00:00:00')) / 86400);
        if ($days < 0 || $days > 14) {
            return null;
        }

        return $days === 0 ? 'j0' : ($days <= 3 ? 'j3' : 'j14');
    }

    public function acknowledge(string $guild, string $kind, string $ref): bool
    {
        $tenant = $this->repo()->tenantForGuild($guild);
        if ($tenant !== null && $kind === 'test') {
            $this->repo()->clearTest($tenant);

            return true;
        }
        if ($tenant === null || !in_array($kind, self::REMINDER_KINDS, true) || $ref === '') {
            return false;
        }
        $this->repo()->markReminder($tenant, $kind, $ref);

        return true;
    }

    /** Le bot a quitté le serveur (ou en a été retiré) : l'état passe à « bot absent ». */
    public function guildRemoved(string $guild): void
    {
        $this->repo()->markRemoved($guild);
    }

    /**
     * Plan de synchronisation des rôles Discord : le site dit qui est « responsable », « membre » ou « aucun » ; le bot applique
     * seulement les deux rôles que la communauté a désignés. Aucun droit du site ne naît d'un rôle Discord.
     *
     * @return array{enabled:bool, member_role_id:string, manager_role_id:string, users:list<array{discord_user_id:string, level:string}>}
     */
    public function rolesPlan(string $guild, int $limit = 200): array
    {
        $tenant = $this->repo()->tenantForGuild($guild);
        $none = ['enabled' => false, 'member_role_id' => '', 'manager_role_id' => '', 'users' => []];
        if ($tenant === null) {
            return $none;
        }
        $s = $this->repo()->settings($tenant);
        $member = (string) ($s['member_role_id'] ?? '');
        $manager = (string) ($s['manager_role_id'] ?? '');
        if (empty($s['sync_roles']) || ($member === '' && $manager === '')) {
            return $none;
        }
        $managers = ($this->permissionLookup)($tenant, ['admin.organization']);
        $st = $this->db()->prepare(
            'SELECT u.id, u.status, l.discord_user_id FROM user_discord_links l JOIN users u ON u.id = l.user_id WHERE u.tenant_id = ? ORDER BY u.id ASC LIMIT ' . max(1, min(500, $limit))
        );
        $st->execute([$tenant]);
        $users = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $level = (string) $r['status'] !== 'active' ? 'none' : (in_array((int) $r['id'], $managers, true) ? 'manager' : 'member');
            $users[] = ['discord_user_id' => (string) $r['discord_user_id'], 'level' => $level];
        }

        return ['enabled' => true, 'member_role_id' => $member, 'manager_role_id' => $manager, 'users' => $users];
    }

    /** Accueil d'un nouvel arrivant : le bot ne sait rien de lui, il donne le lien pour lier son compte Athena. @return array<string, mixed> */
    public function welcome(string $guild): array
    {
        $tenant = $this->repo()->tenantForGuild($guild);
        if ($tenant === null) {
            return ['enabled' => false];
        }
        $s = $this->repo()->settings($tenant);

        return ['enabled' => !empty($s['welcome_enabled']), 'channel_id' => (string) ($s['announce_channel_id'] ?? ''), 'link' => DiscordLinkedRoles::verificationUrl()];
    }

    // ---- Outils ------------------------------------------------------------------------------------------------------

    /** @param list<string> $values */
    private function in(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** @param list<mixed> $params null si la table n'existe pas encore (migration non passée). */
    private function count(string $sql, array $params): ?int
    {
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);

            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
    }
}
