<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\DiscordCompanionController;
use App\Core\Request;
use App\Repositories\DiscordBotRepository;
use App\Repositories\UserDiscordLinkRepository;
use App\Services\Integrations\DiscordBotBureau;
use App\Services\Integrations\DiscordBotInvite;
use App\Services\Integrations\DiscordCompanionSettings;
use App\Services\Integrations\DiscordLinkedRoles;
use App\Support\Migrations\SchemaInspector;
use App\Support\SecretCipher;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/** Bot Discord Athena : rattachement serveur et communauté, state OAuth, droits des commandes, réponses, rappels, rôles. */
final class DiscordBotTest extends TestCase
{
    private const GUILD = '111111111111111111';
    private const OTHER_GUILD = '222222222222222222';
    private const BOSS = '900000000000000001';
    private const MEMBER = '900000000000000002';
    private const STRANGER = '900000000000000003';
    private const OTHER_TENANT_USER = '900000000000000004';
    private const NOW = 1_800_000_000;

    private MysqlDdlSqlitePdo $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MysqlDdlSqlitePdo();
        (new SchemaInspector($this->pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_17_100_discord_bot.sql');
        foreach ([
            'CREATE TABLE discord_companion_guilds (guild_id TEXT PRIMARY KEY, tenant_id INTEGER UNIQUE, guild_name TEXT, channel_id TEXT, channels_json TEXT, synced_at TEXT)',
            'CREATE TABLE community_event_discord (tenant_id INTEGER, destination TEXT, channel_id TEXT, message_id TEXT, reactions_seeded INTEGER)',
            'CREATE TABLE discord_companion_pairings (token_hash TEXT, tenant_id INTEGER, expires_at TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT, status TEXT, grade_id INTEGER, is_platform_admin INTEGER DEFAULT 0, created_at TEXT)',
            'CREATE TABLE grades (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT)',
            'CREATE TABLE user_discord_links (user_id INTEGER PRIMARY KEY, discord_user_id TEXT)',
            'CREATE TABLE personnel_absences (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, starts_on TEXT, ends_on TEXT, status TEXT)',
            'CREATE TABLE enlistments (id INTEGER PRIMARY KEY, tenant_id INTEGER, first_name TEXT, last_name TEXT, email TEXT, callsign TEXT, status TEXT, reviewed_at TEXT, created_at TEXT)',
            'CREATE TABLE enlistment_retro_feedbacks (id INTEGER PRIMARY KEY, tenant_id INTEGER, enlistment_id INTEGER)',
            'CREATE TABLE member_integrations (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, status TEXT, progress_percent INTEGER, next_appointment_at TEXT, overdue_count INTEGER)',
            'CREATE TABLE community_events (id INTEGER PRIMARY KEY, tenant_id INTEGER, title TEXT, starts_at TEXT, cancelled_at TEXT)',
            'CREATE TABLE community_event_rsvps (id INTEGER PRIMARY KEY, event_id INTEGER, user_id INTEGER, status TEXT)',
            'CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY, user_id INTEGER, qualification_name TEXT, status TEXT, expires_at TEXT)',
            'CREATE TABLE data_campaigns (id INTEGER PRIMARY KEY, tenant_id INTEGER, title TEXT, status TEXT, due_on TEXT)',
            'CREATE TABLE data_campaign_members (id INTEGER PRIMARY KEY, campaign_id INTEGER, tenant_id INTEGER, user_id INTEGER, confirmed_at TEXT)',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $now = date('Y-m-d H:i:s', self::NOW);
        $this->pdo->exec("INSERT INTO grades VALUES (1, 5, 'Sergent')");
        foreach ([
            [1, 5, 'boss@secret.test', 'Chef Boss', 'Boss', 'active', 1, self::BOSS],
            [2, 5, 'member@secret.test', 'Ann Member', 'Ghost', 'active', 1, self::MEMBER],
            [3, 5, 'stranger@secret.test', 'Zed', 'Zed', 'active', null, self::STRANGER],
            [4, 6, 'other@secret.test', 'Other', 'Ghost', 'active', null, self::OTHER_TENANT_USER],
            [5, 5, 'pending@secret.test', 'Newbie', 'Newbie', 'pending', null, null],
            [6, 5, 'gone@secret.test', 'Gone', 'Gone', 'archived', null, null],
        ] as [$id, $tenant, $email, $display, $callsign, $status, $grade, $discord]) {
            $this->pdo->prepare('INSERT INTO users (id, tenant_id, email, display_name, callsign, status, grade_id, created_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$id, $tenant, $email, $display, $callsign, $status, $grade, '2025-03-04 10:00:00']);
            if ($discord !== null) {
                $this->pdo->prepare('INSERT INTO user_discord_links VALUES (?, ?)')->execute([$id, $discord]);
            }
        }
        $this->pdo->exec("INSERT INTO discord_companion_guilds VALUES ('" . self::GUILD . "', 5, 'Escouade', '', '{\"10\":\"annonces\",\"11\":\"recrutement\",\"12\":\"journal\"}', '$now')");
        $this->pdo->exec("INSERT INTO discord_bot_settings (tenant_id, reminders_enabled, sync_roles, welcome_enabled, roles_json, bot_seen_at) VALUES (5, 1, 0, 0, '{\"20\":\"Membre\",\"21\":\"Etat-major\"}', '$now')");
    }

    private function repo(): DiscordBotRepository
    {
        return new DiscordBotRepository($this->pdo);
    }

    /** Droits du site : seul le chef (1) a tous les droits ; le membre (2) n'a que ceux de la liste « membre ». */
    private function bureau(): DiscordBotBureau
    {
        $lookup = static function (int $tenant, array $slugs): array {
            if ($tenant !== 5) {
                return [];
            }
            $memberRights = ['personnel.profile.view'];

            return array_values(array_unique(array_merge([1], array_intersect($slugs, $memberRights) !== [] ? [2] : [])));
        };

        return new DiscordBotBureau($this->pdo, $this->repo(), new UserDiscordLinkRepository($this->pdo), $lookup, static fn (string $p): string => 'https://athena.test/' . $p);
    }

    private function discord(?array $guilds = null, int $tokenStatus = 200): DiscordLinkedRoles
    {
        $http = static function (string $method, string $url, array $headers, ?string $body) use ($guilds, $tokenStatus): array {
            if (str_ends_with($url, '/oauth2/token')) {
                return ['status' => $tokenStatus, 'body' => json_encode(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 600])];
            }
            if (str_ends_with($url, '/users/@me/guilds')) {
                return ['status' => 200, 'body' => json_encode($guilds ?? [])];
            }

            return ['status' => 200, 'body' => '{}'];
        };
        $settings = [DiscordLinkedRoles::SETTING_CLIENT_ID => '123456789012345678', DiscordLinkedRoles::SETTING_SECRET => SecretCipher::encrypt('AbCdEf0123456789_xyz')];

        return new DiscordLinkedRoles($http, static fn (string $k): string => $settings[$k] ?? '');
    }

    private function invite(?array $guilds = null, int $tokenStatus = 200): DiscordBotInvite
    {
        return new DiscordBotInvite($this->discord($guilds, $tokenStatus), $this->repo(), 'test-key');
    }

    // ---- State OAuth et invitation -------------------------------------------------------------------------------------

    public function testStateIsBoundToCommunityUserAndSessionAndExpires(): void
    {
        $invite = $this->invite();
        $state = $invite->state(5, 1, 'nonce-a', self::NOW);
        self::assertSame(['tenant' => 5, 'user' => 1], $invite->verifyState($state, 'nonce-a', self::NOW + 60));
        self::assertNull($invite->verifyState($state, 'nonce-b', self::NOW + 60), 'autre session');
        self::assertNull($invite->verifyState($state, '', self::NOW + 60));
        self::assertNull($invite->verifyState($state, 'nonce-a', self::NOW + DiscordBotInvite::STATE_TTL + 1), 'expiré');
        [$payload, $sig] = explode('.', $state);
        self::assertNull($invite->verifyState($payload . '.' . strrev($sig), 'nonce-a', self::NOW + 60), 'signature altérée');
        $forged = rtrim(strtr(base64_encode('6|1|' . (self::NOW + 600) . '|nonce-a'), '+/', '-_'), '=');
        self::assertNull($invite->verifyState($forged . '.' . $sig, 'nonce-a', self::NOW + 60), 'communauté changée sans la clé');
        self::assertNull((new DiscordBotInvite($this->discord(), $this->repo(), 'autre-cle'))->verifyState($state, 'nonce-a', self::NOW + 60));
    }

    public function testInviteLinkAsksForTheBotCommandsAndTheManagerCheckButNeverAdministrator(): void
    {
        $url = $this->invite()->inviteUrl('STATE');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        self::assertSame('123456789012345678', $q['client_id']);
        self::assertSame('bot applications.commands identify guilds', $q['scope']);
        self::assertSame('code', $q['response_type']);
        self::assertSame('STATE', $q['state']);
        self::assertSame(DiscordBotInvite::redirectUri(), $q['redirect_uri']);
        self::assertSame('85056', $q['permissions']);
        foreach ([0x8, 1 << 28, 1 << 5, 1 << 1, 1 << 2] as $forbidden) {
            self::assertSame(0, ((int) $q['permissions']) & $forbidden, 'permission trop large : ' . $forbidden);
        }
        parse_str((string) parse_url($this->invite()->inviteUrl('S', true), PHP_URL_QUERY), $withRoles);
        self::assertSame((string) (85056 | (1 << 28)), $withRoles['permissions']);
    }

    public function testInviteIsUnavailableUntilTheDiscordApplicationIsSet(): void
    {
        $bare = new DiscordBotInvite(new DiscordLinkedRoles(null, static fn (): string => ''), $this->repo(), 'k');
        self::assertFalse($bare->configured());
        self::assertSame('', $bare->inviteUrl('S'));
    }

    // ---- Retour OAuth : rattachement ----------------------------------------------------------------------------------

    public function testManagerOfTheServerLinksItToTheCommunity(): void
    {
        $this->pdo->exec('DELETE FROM discord_companion_guilds');
        $this->pdo->exec('DELETE FROM discord_bot_settings');
        $guilds = [['id' => self::GUILD, 'name' => 'Escouade <b>', 'owner' => false, 'permissions' => (string) 0x20]];
        $invite = $this->invite($guilds);
        $result = $invite->complete('code', $invite->state(5, 1, 'n', time()), self::GUILD, 'n', 5, 1);
        self::assertTrue($result['ok']);
        self::assertSame(5, $this->repo()->tenantForGuild(self::GUILD));
        self::assertSame('pending', $this->repo()->status(5), 'lié mais bot pas encore vu');
        self::assertSame(1, (int) $this->repo()->settings(5)['linked_by_user_id']);
    }

    public function testOnlyThoseWhoManageTheServerCanLinkIt(): void
    {
        $this->pdo->exec('DELETE FROM discord_companion_guilds');
        foreach ([
            'simple membre' => [['id' => self::GUILD, 'name' => 'S', 'owner' => false, 'permissions' => (string) (1 << 11)], 'not_manager'],
            'propriétaire' => [['id' => self::GUILD, 'name' => 'S', 'owner' => true, 'permissions' => '0'], null],
            'administrateur' => [['id' => self::GUILD, 'name' => 'S', 'owner' => false, 'permissions' => (string) 0x8], null],
            'autre serveur' => [['id' => self::OTHER_GUILD, 'name' => 'S', 'owner' => true, 'permissions' => '0'], 'not_member'],
        ] as $label => [$guilds, $error]) {
            $this->pdo->exec('DELETE FROM discord_companion_guilds');
            $invite = $this->invite([$guilds]);
            $result = $invite->complete('code', $invite->state(5, 1, 'n', time()), self::GUILD, 'n', 5, 1);
            if ($error === null) {
                self::assertTrue($result['ok'], $label);
            } else {
                self::assertSame($error, $result['error'] ?? '', $label);
                self::assertNull($this->repo()->tenantForGuild(self::GUILD), $label);
            }
        }
    }

    public function testReturnWithAnotherSessionOrCommunityOrBadCodeLinksNothing(): void
    {
        $this->pdo->exec('DELETE FROM discord_companion_guilds');
        $guilds = [['id' => self::GUILD, 'name' => 'S', 'owner' => true, 'permissions' => '0']];
        $invite = $this->invite($guilds);
        $state = $invite->state(5, 1, 'n', time());
        self::assertSame('state', $invite->complete('c', $state, self::GUILD, 'autre', 5, 1)['error'], 'autre session');
        self::assertSame('state', $invite->complete('c', $state, self::GUILD, 'n', 6, 1)['error'], 'autre communauté');
        self::assertSame('state', $invite->complete('c', $state, self::GUILD, 'n', 5, 2)['error'], 'autre utilisateur');
        self::assertSame('state', $invite->complete('c', 'garbage', self::GUILD, 'n', 5, 1)['error']);
        self::assertSame('no_guild', $invite->complete('c', $state, 'abc', 'n', 5, 1)['error']);
        self::assertSame('oauth', $this->invite($guilds, 400)->complete('c', $state, self::GUILD, 'n', 5, 1)['error']);
        self::assertNull($this->repo()->tenantForGuild(self::GUILD));
    }

    public function testOneServerForOneCommunityInBothDirections(): void
    {
        $guilds = [['id' => self::GUILD, 'name' => 'S', 'owner' => true, 'permissions' => '0'], ['id' => self::OTHER_GUILD, 'name' => 'T', 'owner' => true, 'permissions' => '0']];
        $invite = $this->invite($guilds);
        // Le serveur est déjà celui de la communauté 5 : la communauté 6 ne peut pas le prendre.
        self::assertSame('already_bound', $invite->complete('c', $invite->state(6, 9, 'n', time()), self::GUILD, 'n', 6, 9)['error']);
        // La communauté 5 a déjà un serveur : elle ne peut pas en prendre un second sans délier.
        self::assertSame('already_bound', $invite->complete('c', $invite->state(5, 1, 'n', time()), self::OTHER_GUILD, 'n', 5, 1)['error']);
        self::assertSame(5, $this->repo()->tenantForGuild(self::GUILD));
        self::assertNull($this->repo()->tenantForGuild(self::OTHER_GUILD));
        // Revenir sur le même serveur est accepté (nouvelle invitation, nom mis à jour).
        self::assertTrue($invite->complete('c', $invite->state(5, 1, 'n', time()), self::GUILD, 'n', 5, 1)['ok']);
    }

    // ---- Réglages, état, retrait -------------------------------------------------------------------------------------------

    public function testStatusFollowsTheBotPresence(): void
    {
        $repo = $this->repo();
        self::assertSame('none', $repo->status(6));
        self::assertSame('connected', $repo->status(5, self::NOW));
        self::assertSame('absent', $repo->status(5, self::NOW + DiscordBotRepository::SEEN_TTL + 5));
        $repo->markRemoved(self::GUILD);
        self::assertSame('absent', $repo->status(5, self::NOW));
        $repo->markSeen(self::GUILD, ['20' => 'Membre'], date('Y-m-d H:i:s', self::NOW));
        self::assertSame('connected', $repo->status(5, self::NOW));
        self::assertNull($repo->markSeen(self::OTHER_GUILD, null), 'serveur inconnu');
    }

    public function testSettingsOnlyAcceptChannelsAndRolesTheBotReportedOnThisServer(): void
    {
        $repo = $this->repo();
        self::assertSame('unknown_channel', $repo->saveSettings(5, ['announce_channel_id' => '999'])['error']);
        self::assertSame('unknown_role', $repo->saveSettings(5, ['member_role_id' => '999'])['error']);
        self::assertSame('same_role', $repo->saveSettings(5, ['member_role_id' => '20', 'manager_role_id' => '20'])['error']);
        self::assertSame('no_guild_linked', $repo->saveSettings(6, [])['error']);
        self::assertTrue($repo->saveSettings(5, ['announce_channel_id' => '10', 'applications_channel_id' => '11', 'events_channel_id' => '12', 'member_role_id' => '20', 'manager_role_id' => '21', 'sync_roles' => '1', 'reminders_enabled' => '1'])['ok']);
        $s = $repo->settings(5);
        self::assertSame(['10', '11', '12'], [$s['announce_channel_id'], $s['applications_channel_id'], $s['events_channel_id']]);
        self::assertSame(1, (int) $s['sync_roles']);
        self::assertSame(0, (int) $s['welcome_enabled']);
        self::assertSame('12', $repo->guildForTenant(5)['channel_id'], 'le salon des événements devient la destination par défaut');
    }

    public function testUnlinkingForgetsTheServerAndItsSettingsButNotTheOtherCommunity(): void
    {
        $this->pdo->exec("INSERT INTO discord_companion_guilds VALUES ('" . self::OTHER_GUILD . "', 6, 'Autre', '', '{}', '')");
        $this->repo()->markReminder(5, 'retro30', 'w1');
        $this->repo()->unlink(5);
        self::assertNull($this->repo()->guildForTenant(5));
        self::assertSame('none', $this->repo()->status(5));
        self::assertFalse($this->repo()->reminderSent(5, 'retro30', 'w1'));
        self::assertNotNull($this->repo()->guildForTenant(6));
    }

    public function testDashboardNudgeCanBeDismissedPerUser(): void
    {
        $repo = $this->repo();
        self::assertFalse($repo->dismissed(5, 1));
        $repo->dismiss(5, 1);
        $repo->dismiss(5, 1);
        self::assertTrue($repo->dismissed(5, 1));
        self::assertFalse($repo->dismissed(5, 2));
        self::assertFalse($repo->dismissed(6, 1));
    }

    public function testMigrationIsRerunnableAndRollbackRemovesOnlyItsTables(): void
    {
        $schema = new SchemaInspector($this->pdo);
        $before = $this->pdo->changes;
        $schema->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_17_100_discord_bot.sql');
        self::assertSame($before, $this->pdo->changes, 'la relance ne change rien');
        foreach (['discord_bot_settings', 'discord_bot_reminders', 'discord_bot_audit', 'discord_bot_dismissals'] as $table) {
            self::assertTrue($schema->tableExists($table), $table);
        }
        $down = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/rollback/discord_bot_down.sql');
        foreach (['discord_bot_settings', 'discord_bot_reminders', 'discord_bot_audit', 'discord_bot_dismissals'] as $table) {
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $table . ';', $down);
        }
        self::assertStringNotContainsString('discord_companion_guilds', preg_replace('/^--.*$/m', '', $down));
        self::assertStringNotContainsString('ADD UNIQUE', (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_11_17_100_discord_bot.sql'));
    }

    // ---- Commandes : liaison, droits, tenant, limite --------------------------------------------------------------------

    public function testCommandsRefuseUnlinkedServersAccountsAndOutsiders(): void
    {
        $b = $this->bureau();
        self::assertSame('unknown_command', $b->command('drop', self::GUILD, self::BOSS)['error']);
        self::assertSame('not_linked', $b->command('effectifs', self::OTHER_GUILD, self::BOSS)['error'], 'serveur non relié');
        self::assertSame('link_required', $b->command('effectifs', self::GUILD, '999999999999999999')['error'], 'compte Discord non relié à Athena');
        self::assertSame('not_member', $b->command('effectifs', self::GUILD, self::OTHER_TENANT_USER)['error'], 'compte d’une autre communauté');
        self::assertSame('forbidden', $b->command('effectifs', self::GUILD, self::STRANGER)['error'], 'pas le droit sur le site');
        self::assertTrue($b->command('effectifs', self::GUILD, self::BOSS)['ok']);
    }

    public function testRightsFollowTheSiteCommandByCommand(): void
    {
        $b = $this->bureau();
        // Le membre n'a que « personnel.profile.view » sur le site.
        self::assertTrue($b->command('membre', self::GUILD, self::MEMBER, ['query' => 'Boss'])['ok']);
        self::assertTrue($b->command('effectifs', self::GUILD, self::MEMBER)['ok'], 'personnel.profile.view suffit pour le résumé');
        self::assertSame('forbidden', $b->command('candidatures', self::GUILD, self::MEMBER)['error']);
        // Les commandes ouvertes à tout membre le restent, sans droit particulier.
        self::assertTrue($b->command('evenements', self::GUILD, self::STRANGER)['ok']);
        self::assertTrue($b->command('integration', self::GUILD, self::STRANGER)['ok']);
        self::assertSame('forbidden', $b->command('membre', self::GUILD, self::STRANGER, ['query' => 'Boss'])['error']);
    }

    public function testCommandsAreRateLimitedAndEveryAttemptIsAuditedWithoutContent(): void
    {
        $b = $this->bureau();
        for ($i = 0; $i < DiscordBotBureau::RATE_PER_MINUTE; $i++) {
            $b->command('effectifs', self::GUILD, self::BOSS, [], time());
        }
        self::assertSame('rate_limited', $b->command('effectifs', self::GUILD, self::BOSS, [], time())['error']);
        self::assertTrue($b->command('effectifs', self::GUILD, self::MEMBER)['ok'], 'la limite est par personne');
        $rows = $this->pdo->query('SELECT * FROM discord_bot_audit')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(DiscordBotBureau::RATE_PER_MINUTE + 1, $rows);
        self::assertSame(['id', 'tenant_id', 'guild_id', 'discord_user_id', 'command', 'outcome', 'created_at'], array_keys($rows[0]));
        self::assertSame('5', (string) $rows[0]['tenant_id']);
        $b->command('candidatures', self::GUILD, self::STRANGER);
        self::assertSame('forbidden', $this->pdo->query("SELECT outcome FROM discord_bot_audit WHERE command = 'candidatures'")->fetchColumn());
    }

    public function testTheCommunityComesFromTheServerNeverFromThePayload(): void
    {
        // Un compte de la communauté 6 qui parle depuis le serveur de la communauté 5 est refusé, même avec une saisie trompeuse.
        $b = $this->bureau();
        $result = $b->command('effectifs', self::GUILD, self::OTHER_TENANT_USER, ['tenant_id' => 6, 'tenant' => 6]);
        self::assertSame('not_member', $result['error']);
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email, display_name, callsign, status, created_at) VALUES (40, 6, 'x@x.test', 'Ghost Autre', 'GhostSix', 'active', '2025-01-01')");
        $names = array_column($b->command('membre', self::GUILD, self::BOSS, ['query' => 'Ghost'])['data']['members'], 'name');
        self::assertSame(['Ghost'], $names, 'aucune fiche de l’autre communauté');
    }

    // ---- Réponses : contenu et formatage -------------------------------------------------------------------------------------

    public function testStaffSummaryCountsOnlyTheCommunityAndOnlyWhatIsOpen(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("INSERT INTO personnel_absences VALUES (1, 5, 2, '2000-01-01', NULL, 'active')");
        $this->pdo->exec("INSERT INTO personnel_absences VALUES (2, 5, 3, '2000-01-01', '2000-02-01', 'active')");
        $this->pdo->exec("INSERT INTO personnel_absences VALUES (3, 6, 4, '2000-01-01', NULL, 'active')");
        $this->pdo->exec("INSERT INTO enlistments (id, tenant_id, callsign, status, created_at) VALUES (1, 5, 'A', 'submitted', '$today'), (2, 5, 'B', 'rejected', '$today'), (3, 6, 'C', 'submitted', '$today')");
        $this->pdo->exec("INSERT INTO member_integrations VALUES (1, 5, 2, 'in_progress', 40, NULL, 0), (2, 5, 3, 'completed', 100, NULL, 0), (3, 6, 4, 'in_progress', 0, NULL, 0)");
        $data = $this->bureau()->command('effectifs', self::GUILD, self::BOSS)['data'];
        self::assertSame(['active' => 3, 'pending' => 1, 'absent' => 1, 'applications' => 1, 'integrations' => 1], array_intersect_key($data, array_flip(['active', 'pending', 'absent', 'applications', 'integrations'])));
        self::assertSame('https://athena.test/back-office/ressources/effectifs', $data['link']);
    }

    public function testMemberCardHasNoCivilIdentityEmailOrOtherCommunity(): void
    {
        $data = $this->bureau()->command('membre', self::GUILD, self::BOSS, ['query' => 'gho'])['data'];
        self::assertCount(1, $data['members']);
        self::assertSame(['name' => 'Ghost', 'grade' => 'Sergent', 'status' => 'active', 'since' => '2025-03-04', 'absent' => false], $data['members'][0]);
        $json = json_encode($data);
        foreach (['secret.test', 'Ann Member', 'email', 'display_name', 'password'] as $leak) {
            self::assertStringNotContainsString($leak, (string) $json);
        }
        self::assertSame([], $this->bureau()->membre(5, 'g')['members'], 'au moins deux lettres');
        self::assertSame([], $this->bureau()->membre(5, '%')['members'], 'le joker n’est pas interprété');
        self::assertSame([], $this->bureau()->membre(5, 'Gone')['members'], 'seulement les membres actifs');
    }

    public function testApplicationsListHasNoNameOrEmailAndLinksBackToTheSiteForTheDecision(): void
    {
        $today = date('Y-m-d');
        $this->pdo->exec("INSERT INTO enlistments (id, tenant_id, first_name, last_name, email, callsign, status, created_at) VALUES (7, 5, 'Jeanne', 'Dupont', 'jeanne@secret.test', 'Rook', 'submitted', '$today')");
        $data = $this->bureau()->command('candidatures', self::GUILD, self::BOSS)['data'];
        self::assertSame(7, $data['items'][0]['id']);
        self::assertSame('https://athena.test/back-office/recruitments/7', $data['items'][0]['link']);
        $json = (string) json_encode($data);
        foreach (['Jeanne', 'Dupont', 'secret.test'] as $leak) {
            self::assertStringNotContainsString($leak, $json);
        }
        self::assertSame(['id', 'callsign', 'status', 'date', 'link'], array_keys($data['items'][0]), 'aucune action de décision depuis Discord');
    }

    public function testEventsAndOnboardingShowTheirOwnState(): void
    {
        $this->pdo->exec("INSERT INTO community_events VALUES (1, 5, 'Entraînement', '2027-01-20 20:00:00', NULL), (2, 5, 'Annulé', '2027-01-21 20:00:00', '2027-01-01'), (3, 6, 'Autre communauté', '2027-01-22 20:00:00', NULL)");
        $this->pdo->exec('INSERT INTO community_event_rsvps VALUES (1, 1, 2, \'yes\')');
        $events = $this->bureau()->command('evenements', self::GUILD, self::MEMBER, [], 1_790_000_000)['data']['items'];
        self::assertSame([['title' => 'Entraînement', 'starts_at' => '2027-01-20 20:00:00', 'rsvp' => 'yes', 'link' => 'https://athena.test/evenements#event-1']], $events);
        $this->pdo->exec("INSERT INTO member_integrations VALUES (1, 5, 2, 'waiting_staff', 60, '2026-12-01 10:00:00', 2), (2, 5, 3, 'waiting_staff', 10, NULL, 0)");
        $mine = $this->bureau()->command('integration', self::GUILD, self::MEMBER)['data'];
        self::assertSame(['status' => 'waiting_staff', 'percent' => 60, 'next_appointment' => '2026-12-01 10:00:00', 'overdue' => 2], $mine['mine']);
        self::assertArrayNotHasKey('staff_waiting', $mine, 'la file de l’équipe n’est pas pour tout le monde');
        self::assertSame(2, $this->bureau()->command('integration', self::GUILD, self::BOSS)['data']['staff_waiting']);
    }

    // ---- Rappels -----------------------------------------------------------------------------------------------------------------

    public function testRemindersAreSentOnceThenAcknowledgedAndRespectTheSwitch(): void
    {
        $this->repo()->saveSettings(5, ['applications_channel_id' => '11', 'reminders_enabled' => '1']);
        $now = self::NOW;
        $recent = date('Y-m-d H:i:s', $now - 3600);
        $this->pdo->exec("INSERT INTO enlistments (id, tenant_id, callsign, status, created_at) VALUES (1, 5, 'Rook', 'submitted', '$recent'), (2, 6, 'Autre', 'submitted', '$recent')");
        $b = $this->bureau();
        $items = $b->reminders(self::GUILD, $now);
        self::assertCount(1, $items);
        self::assertSame(['kind' => 'applications_new', 'ref' => '1', 'target' => 'channel', 'to' => '11'], array_intersect_key($items[0], array_flip(['kind', 'ref', 'target', 'to'])));
        self::assertTrue($b->acknowledge(self::GUILD, 'applications_new', '1'));
        self::assertSame([], $b->reminders(self::GUILD, $now));
        self::assertFalse($b->acknowledge(self::GUILD, 'format_disk', '1'));
        self::assertFalse($b->acknowledge(self::OTHER_GUILD, 'applications_new', '2'));
        $this->repo()->saveSettings(5, ['applications_channel_id' => '11']);
        $this->pdo->exec("INSERT INTO enlistments (id, tenant_id, callsign, status, created_at) VALUES (3, 5, 'Late', 'submitted', '$recent')");
        self::assertSame([], $b->reminders(self::GUILD, $now), 'rappels coupés');
    }

    public function testRemindersGoToNobodyWhenNoChannelIsChosen(): void
    {
        $recent = date('Y-m-d H:i:s', self::NOW - 60);
        $this->pdo->exec("INSERT INTO enlistments (id, tenant_id, callsign, status, created_at) VALUES (1, 5, 'Rook', 'submitted', '$recent')");
        self::assertSame([], $this->bureau()->reminders(self::GUILD, self::NOW));
    }

    public function testQualificationAndCampaignRemindersGoByDirectMessageToLinkedMembersOnly(): void
    {
        $now = self::NOW;
        $this->repo()->saveSettings(5, ['log_channel_id' => '12', 'reminders_enabled' => '1']);
        $in = date('Y-m-d', $now + 5 * 86400);
        $this->pdo->exec("INSERT INTO personnel_qualifications VALUES (1, 2, 'JTAC', 'valid', '$in'), (2, 5, 'Sans compte', 'valid', '$in'), (3, 4, 'Autre communauté', 'valid', '$in')");
        $due = date('Y-m-d', $now + 2 * 86400);
        $this->pdo->exec("INSERT INTO data_campaigns VALUES (1, 5, 'Campagne 2026', 'open', '$due')");
        $this->pdo->exec('INSERT INTO data_campaign_members VALUES (1, 1, 5, 2, NULL), (2, 1, 5, 3, \'2026-11-01\'), (3, 1, 5, 5, NULL)');
        $items = $this->bureau()->reminders(self::GUILD, $now);
        $dm = array_values(array_filter($items, static fn (array $i): bool => $i['target'] === 'dm'));
        $byKind = [];
        foreach ($dm as $i) {
            $byKind[$i['kind']][] = $i['to'];
        }
        self::assertSame([self::MEMBER], $byKind['qualification_due']);
        self::assertSame([self::MEMBER], $byKind['campaign'], 'ni les membres qui ont déjà confirmé, ni ceux sans compte Discord lié');
        $campaign = array_values(array_filter($dm, static fn (array $i): bool => $i['kind'] === 'campaign'))[0];
        self::assertSame('j3', $campaign['data']['slot']);
        $staff = array_values(array_filter($items, static fn (array $i): bool => $i['kind'] === 'qualification_staff'));
        self::assertSame(['channel', '12', 1], [$staff[0]['target'], $staff[0]['to'], $staff[0]['data']['count']], 'compteur pour la gestion, sans nom');
    }

    public function testCampaignSlotsAreFourteenThreeAndDayOfTheDeadline(): void
    {
        $day = 86400;
        $due = date('Y-m-d', self::NOW + 20 * $day);
        self::assertNull(DiscordBotBureau::campaignSlot($due, self::NOW));
        self::assertSame('j14', DiscordBotBureau::campaignSlot($due, self::NOW + 6 * $day));
        self::assertSame('j14', DiscordBotBureau::campaignSlot($due, self::NOW + 16 * $day));
        self::assertSame('j3', DiscordBotBureau::campaignSlot($due, self::NOW + 17 * $day));
        self::assertSame('j0', DiscordBotBureau::campaignSlot($due, self::NOW + 20 * $day));
        self::assertNull(DiscordBotBureau::campaignSlot($due, self::NOW + 21 * $day), 'échéance dépassée : plus de rappel');
        self::assertNull(DiscordBotBureau::campaignSlot('pas une date', self::NOW));
    }

    public function testTestButtonQueuesOneMessageForTheStaffChannelThenClearsIt(): void
    {
        $this->repo()->saveSettings(5, ['log_channel_id' => '12', 'reminders_enabled' => '1']);
        $this->repo()->requestTest(5, '2027-01-01 10:00:00');
        $b = $this->bureau();
        $items = $b->reminders(self::GUILD, self::NOW);
        self::assertSame(['test', 'channel', '12'], [$items[0]['kind'], $items[0]['target'], $items[0]['to']]);
        self::assertTrue($b->acknowledge(self::GUILD, 'test', $items[0]['ref']));
        self::assertSame([], $b->reminders(self::GUILD, self::NOW));
    }

    // ---- Rôles et accueil ------------------------------------------------------------------------------------------------------

    public function testRolePlanCopiesTheSiteLevelAndIsOffByDefault(): void
    {
        $b = $this->bureau();
        self::assertFalse($b->rolesPlan(self::GUILD)['enabled']);
        self::assertFalse($b->rolesPlan(self::OTHER_GUILD)['enabled']);
        $this->repo()->saveSettings(5, ['member_role_id' => '20', 'manager_role_id' => '21', 'sync_roles' => '1']);
        $this->pdo->exec('UPDATE users SET status = \'archived\' WHERE id = 3');
        $plan = $b->rolesPlan(self::GUILD);
        self::assertTrue($plan['enabled']);
        self::assertSame(['20', '21'], [$plan['member_role_id'], $plan['manager_role_id']]);
        $levels = array_column($plan['users'], 'level', 'discord_user_id');
        self::assertSame('member', $levels[self::MEMBER]);
        self::assertSame('none', $levels[self::STRANGER], 'un membre parti perd les rôles gérés');
        self::assertArrayNotHasKey(self::OTHER_TENANT_USER, $levels, 'seulement la communauté du serveur');
        self::assertSame('manager', $levels[self::BOSS], 'seul le niveau d’administration du site donne le rôle responsable');
    }

    public function testRolePlanNeedsAtLeastOneChosenRole(): void
    {
        $this->repo()->saveSettings(5, ['sync_roles' => '1']);
        self::assertFalse($this->bureau()->rolesPlan(self::GUILD)['enabled']);
    }

    public function testWelcomeOnlyKnowsTheChannelAndTheLinkingAddress(): void
    {
        $b = $this->bureau();
        self::assertFalse($b->welcome(self::GUILD)['enabled']);
        $this->repo()->saveSettings(5, ['announce_channel_id' => '10', 'welcome_enabled' => '1']);
        $w = $b->welcome(self::GUILD);
        self::assertSame(['enabled', 'channel_id', 'link'], array_keys($w));
        self::assertTrue($w['enabled']);
        self::assertSame('10', $w['channel_id']);
        self::assertFalse($b->welcome(self::OTHER_GUILD)['enabled']);
    }

    // ---- API privée du bot -----------------------------------------------------------------------------------------------------

    private function call(array $post, ?DiscordBotBureau $bureau = null, string $auth = 'Bearer ' . self::KEY): array
    {
        $before = [$_POST, $_SERVER];
        $_POST = $post;
        $_SERVER['HTTP_AUTHORIZATION'] = $auth;
        try {
            $settings = new DiscordCompanionSettings(static fn (): string => SecretCipher::encrypt(self::KEY));
            $response = (new DiscordCompanionController($settings, $bureau ?? $this->bureau()))->handle(new Request());

            return ['status' => $response->statusCode(), 'body' => json_decode($response->body(), true)];
        } finally {
            [$_POST, $_SERVER] = $before;
        }
    }

    private const KEY = 'k-0123456789012345678901234567890123456789';

    public function testPrivateApiRunsCommandsForTheLinkedServerAndRejectsBadCallers(): void
    {
        $payload = static fn (array $d): string => json_encode($d, JSON_THROW_ON_ERROR);
        self::assertSame(401, $this->call(['op' => 'cmd', 'payload' => '{}'], null, 'Bearer faux')['status']);
        $ok = $this->call(['op' => 'cmd', 'payload' => $payload(['guild_id' => self::GUILD, 'user_id' => self::BOSS, 'command' => 'effectifs'])]);
        self::assertSame(200, $ok['status']);
        self::assertTrue($ok['body']['ok']);
        self::assertSame(3, $ok['body']['data']['active']);
        $refused = $this->call(['op' => 'cmd', 'payload' => $payload(['guild_id' => self::GUILD, 'user_id' => self::STRANGER, 'command' => 'candidatures'])]);
        self::assertSame(['ok' => false, 'error' => 'forbidden'], $refused['body']);
        $bad = $this->call(['op' => 'cmd', 'payload' => $payload(['guild_id' => 'x; DROP', 'user_id' => self::BOSS, 'command' => 'effectifs'])]);
        self::assertSame(422, $bad['status']);
        self::assertSame('invalid_id', $bad['body']['error']);
        $badUser = $this->call(['op' => 'cmd', 'payload' => $payload(['guild_id' => self::GUILD, 'user_id' => 'abc', 'command' => 'effectifs'])]);
        self::assertSame(422, $badUser['status']);
    }

    public function testPrivateApiServesRemindersRolesAndWelcomeAndRecordsAcknowledgements(): void
    {
        $this->repo()->saveSettings(5, ['announce_channel_id' => '10', 'applications_channel_id' => '11', 'welcome_enabled' => '1', 'reminders_enabled' => '1']);
        $recent = date('Y-m-d H:i:s', time() - 60);
        $this->pdo->exec("INSERT INTO enlistments (id, tenant_id, callsign, status, created_at) VALUES (1, 5, 'Rook', 'submitted', '$recent')");
        $p = static fn (array $d): string => json_encode($d + ['guild_id' => self::GUILD], JSON_THROW_ON_ERROR);
        $reminders = $this->call(['op' => 'reminders', 'payload' => $p([])])['body']['reminders'];
        self::assertSame('applications_new', $reminders[0]['kind']);
        self::assertTrue($this->call(['op' => 'reminder_ack', 'payload' => $p(['kind' => 'applications_new', 'ref' => '1'])])['body']['ok']);
        self::assertSame([], $this->call(['op' => 'reminders', 'payload' => $p([])])['body']['reminders']);
        self::assertFalse($this->call(['op' => 'roles_plan', 'payload' => $p([])])['body']['enabled']);
        self::assertTrue($this->call(['op' => 'welcome', 'payload' => $p([])])['body']['enabled']);
        self::assertTrue($this->call(['op' => 'guild_removed', 'payload' => $p([])])['body']['ok']);
        self::assertSame('absent', $this->repo()->status(5, self::NOW));
    }

    public function testBotTokenNeverAppearsInTheSiteSources(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            glob($root . '/app/Services/Integrations/DiscordBot*.php') ?: [],
            glob($root . '/app/Repositories/DiscordBotRepository.php') ?: [],
            glob($root . '/app/Controllers/Admin/Organization/OrganizationDiscordBotController.php') ?: [],
            glob($root . '/views/admin/organization/discord_bot.php') ?: [],
            glob($root . '/deploy/discord-bot/*') ?: [],
            glob($root . '/deploy/systemd/*') ?: [],
        );
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $text = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/[MN][A-Za-z\d_-]{23,25}\.[\w-]{6}\.[\w-]{27,}/', $text, basename($file) . ' : forme d’un jeton de bot');
        }
    }
}
