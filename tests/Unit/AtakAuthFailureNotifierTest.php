<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakModReportRepository;
use App\Services\Monitoring\AtakAuthFailureNotifier;
use App\Support\UtcDateTime;
use PDO;
use PHPUnit\Framework\TestCase;

/** Rapports auto « accès refusé par Athena » : diagnostic serveur et e-mail au joueur, une fois par jour. */
final class AtakAuthFailureNotifierTest extends TestCase
{
    private const STEAM = '76561198000000001';
    private const NOW = 1791969600; // 2026-10-14 09:20:00 UTC

    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE atak_mod_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT, severity TEXT DEFAULT 'error', workflow_status TEXT DEFAULT 'new',
            channel TEXT DEFAULT 'Core', message TEXT, detail_text TEXT, context_json TEXT, fingerprint TEXT,
            source TEXT DEFAULT 'auto', steam_uid TEXT, player_uid TEXT, player_name TEXT, callsign TEXT, client_ip TEXT,
            mod_version TEXT, extension_version TEXT, arma_build TEXT, hit_count INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP, last_seen_at TEXT DEFAULT '2026-10-14 09:00:00',
            status_changed_at TEXT, reply_text TEXT, replied_at TEXT, replied_by TEXT, reply_channels TEXT)");
        $this->pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT, steam_id TEXT, status TEXT DEFAULT 'active')");
        $this->pdo->exec('CREATE TABLE athena_accounts (id INTEGER PRIMARY KEY, email TEXT, steam_id TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, mod_version TEXT,
            created_at TEXT, expires_at TEXT, refresh_expires_at TEXT, revoked_at TEXT)');
        $this->pdo->exec('CREATE TABLE tenant_atak_config (tenant_id INTEGER PRIMARY KEY, access_key TEXT)');
        $this->pdo->exec('CREATE TABLE atak_player_auto_notices (id INTEGER PRIMARY KEY AUTOINCREMENT, steam_uid TEXT NOT NULL,
            problem TEXT NOT NULL, sent_on TEXT NOT NULL, report_id INTEGER, diagnosis TEXT, created_at TEXT,
            UNIQUE (steam_uid, problem, sent_on))');
        $this->pdo->exec("INSERT INTO users (id, tenant_id, email, display_name, callsign, steam_id) VALUES (7, 3, 'wulff@example.com', 'Wulff', 'WULFF', '" . self::STEAM . "')");
        $this->pdo->exec("INSERT INTO tenant_atak_config VALUES (3, 'cle-communaute-0123456789')");
        $this->pdo->exec("INSERT INTO atak_mod_reports (message, source, steam_uid) VALUES ('ÉCHEC · fiche opérateur — unauthorized', 'auto', '" . self::STEAM . "')");
    }

    private function session(string $expires, string $refreshExpires, ?string $revoked = null): void
    {
        $st = $this->pdo->prepare('INSERT INTO game_sessions (tenant_id, steam_id, mod_version, created_at, expires_at, refresh_expires_at, revoked_at) VALUES (3, ?, ?, ?, ?, ?, ?)');
        $st->execute([self::STEAM, '1.6.16', '2026-10-14 07:00:00', $expires, $refreshExpires, $revoked]);
    }

    /**
     * @param list<array{0:string, 1:array<string, mixed>}> $mails
     */
    private function notifier(array &$mails, bool $sendOk = true): AtakAuthFailureNotifier
    {
        $send = static function (string $to, array $vars) use (&$mails, $sendOk): bool {
            $mails[] = [$to, $vars];

            return $sendOk;
        };

        return new AtakAuthFailureNotifier($this->pdo, new AtakModReportRepository($this->pdo), $send, self::NOW);
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return ['id' => 1, 'steam_uid' => self::STEAM, 'message' => 'ÉCHEC · fiche opérateur — unauthorized',
            'mod_version' => '1.6.16', 'extension_version' => '2.0.84'];
    }

    public function testRecognisesAuthFailureReportsOnly(): void
    {
        self::assertTrue(AtakAuthFailureNotifier::isAuthFailure('auto', 'ÉCHEC · fiche opérateur — unauthorized', ''));
        self::assertTrue(AtakAuthFailureNotifier::isAuthFailure('auto', 'Session refusée par le poste — transmissions arrêtées', ''));
        self::assertTrue(AtakAuthFailureNotifier::isAuthFailure('auto', 'ÉCHEC · position', '["ERROR","unauthorized"]'));
        self::assertFalse(AtakAuthFailureNotifier::isAuthFailure('auto', 'Timeout Link', ''));
        // Signalement fait par le joueur lui-même : l'exploitation lui répond déjà.
        self::assertFalse(AtakAuthFailureNotifier::isAuthFailure('player', 'unauthorized', ''));
    }

    public function testDiagnosisReadsSessionDatesAsUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
        try {
            $mails = [];
            // Jeton valable 2 h, écrit en UTC : lu à l'heure de Paris, il paraissait expiré dès sa création.
            $this->session(gmdate('Y-m-d H:i:s', self::NOW + 7200 - 60), gmdate('Y-m-d H:i:s', self::NOW + 86400));
            self::assertSame(AtakAuthFailureNotifier::DIAG_SESSION_VALID, $this->notifier($mails)->diagnose(self::STEAM)['code']);
            self::assertFalse(UtcDateTime::isPast(gmdate('Y-m-d H:i:s', time() + 7000)));
            self::assertTrue(UtcDateTime::isPast(gmdate('Y-m-d H:i:s', time() - 60)));
            self::assertTrue(UtcDateTime::isPast(''));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testDiagnosisCodes(): void
    {
        $mails = [];
        $n = $this->notifier($mails);
        self::assertSame(AtakAuthFailureNotifier::DIAG_NO_SESSION, $n->diagnose(self::STEAM)['code']);

        $this->session('2026-10-14 08:00:00', '2026-11-13 07:00:00');
        $d = $n->diagnose(self::STEAM);
        self::assertSame(AtakAuthFailureNotifier::DIAG_ACCESS_EXPIRED, $d['code']);
        self::assertStringContainsString('14/10/2026 08:00', $d['text']);
        self::assertFalse($d['no_access_key']);

        $this->session('2026-10-14 08:00:00', '2026-11-13 07:00:00', '2026-10-14 08:30:00');
        self::assertSame(AtakAuthFailureNotifier::DIAG_REVOKED, $n->diagnose(self::STEAM)['code']);

        $this->session('2026-09-01 08:00:00', '2026-10-01 07:00:00');
        self::assertSame(AtakAuthFailureNotifier::DIAG_REFRESH_EXPIRED, $n->diagnose(self::STEAM)['code']);

        $this->pdo->exec("UPDATE users SET status = 'disabled'");
        self::assertSame(AtakAuthFailureNotifier::DIAG_ACCOUNT_DISABLED, $n->diagnose(self::STEAM)['code']);
    }

    public function testMissingCommunityKeyIsReported(): void
    {
        if (\App\Support\ComspecApiKeyAuth::expectedSecret() !== '') {
            self::markTestSkipped('clé plateforme définie dans l’environnement');
        }
        $this->pdo->exec('DELETE FROM tenant_atak_config');
        $mails = [];
        $d = $this->notifier($mails)->diagnose(self::STEAM, 3);
        self::assertTrue($d['no_access_key']);
        $steps = AtakAuthFailureNotifier::adviceFor($d)['steps'];
        self::assertStringContainsString('clé d’accès ATAK', $steps[count($steps) - 1]);
    }

    public function testMailsLinkedPlayerOncePerDayAndKeepsItOnTheReport(): void
    {
        $mails = [];
        $n = $this->notifier($mails);
        $first = $n->handle($this->report());
        self::assertTrue($first['mailed']);
        self::assertCount(1, $mails);
        self::assertSame('wulff@example.com', $mails[0][0]);
        self::assertSame('WULFF', $mails[0][1]['displayName']);
        self::assertSame('Mod 1.6.16 · Link 2.0.84', $mails[0][1]['versions']);
        self::assertNotEmpty($mails[0][1]['steps']);

        // Même joueur, même problème, même jour (autre rapport « Session refusée ») : pas de second e-mail.
        $second = $n->handle(['id' => 1, 'steam_uid' => self::STEAM, 'message' => 'Session refusée par le poste — transmissions arrêtées']);
        self::assertFalse($second['mailed']);
        self::assertCount(1, $mails);

        $row = (new AtakModReportRepository($this->pdo))->findById(1);
        self::assertSame('email', $row['reply_channels']);
        self::assertSame('E-mail automatique', $row['replied_by']);

        // Le lendemain, il peut être prévenu à nouveau.
        $tomorrow = new AtakAuthFailureNotifier($this->pdo, new AtakModReportRepository($this->pdo),
            static function (string $to, array $vars) use (&$mails): bool {
                $mails[] = [$to, $vars];

                return true;
            }, self::NOW + 86400);
        self::assertTrue($tomorrow->handle($this->report())['mailed']);
        self::assertCount(2, $mails);
    }

    public function testFailedSendDoesNotUseTheDailySlot(): void
    {
        $mails = [];
        self::assertFalse($this->notifier($mails, false)->handle($this->report())['mailed']);
        self::assertTrue($this->notifier($mails, true)->handle($this->report())['mailed']);
        self::assertCount(2, $mails);
    }

    public function testUnlinkedSteamGetsDiagnosisButNoMail(): void
    {
        $mails = [];
        $result = $this->notifier($mails)->handle(['id' => 1, 'steam_uid' => '76561198000000099', 'message' => 'unauthorized']);
        self::assertFalse($result['mailed']);
        self::assertNotNull($result['diagnosis']);
        self::assertSame([], $mails);
    }
}
