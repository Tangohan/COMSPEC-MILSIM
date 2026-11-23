<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\UserRepository;
use App\Services\Account\PlatformAccountBanService;
use App\Services\Account\PlatformBanNotice;
use App\Services\EmailTemplateEngine;
use App\Support\AccountAccessStatus;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Audit du bannissement et de la contestation : une régression par défaut corrigé.
 * Adresses fictives (example.test), aucune donnée réelle.
 */
final class PlatformBanAuditFixesTest extends TestCase
{
    private function source(string $relative): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative));
    }

    private function sqlite(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, status TEXT, updated_at TEXT)');

        return $pdo;
    }

    private function setStatuses(PDO $pdo, array $statuses, bool $closeSessions): void
    {
        $service = new PlatformAccountBanService(new UserRepository($pdo), new PlatformAccountBanRepository($pdo), null, null, $pdo);
        $method = new \ReflectionMethod($service, 'setStatuses');
        $method->setAccessible(true);
        $method->invoke($service, $statuses, $closeSessions);
    }

    /** Une fiche bannie est « inactive » : le jeu et l'API ATAK doivent la refuser comme une fiche « banned ». */
    public function testInactiveAccountsAreDeniedByTheGameAndAtakApi(): void
    {
        self::assertTrue(AccountAccessStatus::deniesGame('inactive'));
        self::assertTrue(AccountAccessStatus::deniesGame(' Banned '));
        self::assertTrue(AccountAccessStatus::deniesGame('merged'));
        self::assertFalse(AccountAccessStatus::deniesGame('active'));
        self::assertFalse(AccountAccessStatus::deniesGame(null));

        foreach (['app/Controllers/Api/AtakApiController.php', 'app/Support/AtakArmaWriteGuard.php'] as $file) {
            $code = $this->source($file);
            self::assertStringNotContainsString("['banned', 'disabled', 'suspended', 'deleted']", $code, $file);
            self::assertStringContainsString('AccountAccessStatus::deniesGame', $code, $file);
        }
    }

    /** Le jeton du jeu et la session navigateur des API tactiques cessent de valoir dès que la fiche n'est plus active. */
    public function testTacticalAuthRechecksTheAccountStatus(): void
    {
        $code = $this->source('app/Support/ComspecApiKeyAuth.php');
        self::assertStringContainsString('return self::userIsActive((int) $uid);', $code);
        self::assertStringContainsString('if ($uid > 0 && !self::userIsActive($uid)) {', $code);
        self::assertStringContainsString('SELECT status FROM users WHERE id = ?', $code);
    }

    public function testBanRevokesTheGameSessionsOfEveryAccountOfThePerson(): void
    {
        $pdo = $this->sqlite();
        $pdo->exec("INSERT INTO users (id, email, status) VALUES (2, 'joueur@example.test', 'active'), (3, 'joueur@example.test', 'active'), (4, 'autre@example.test', 'active')");
        $pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, user_id INTEGER, revoked_at TEXT)');
        $pdo->exec('INSERT INTO game_sessions (id, user_id) VALUES (1, 2), (2, 3), (3, 4)');

        $this->setStatuses($pdo, [2 => 'inactive', 3 => 'inactive'], true);

        $revoked = $pdo->query('SELECT id FROM game_sessions WHERE revoked_at IS NOT NULL ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame([1, 2], array_map('intval', $revoked));
        self::assertSame('inactive', (string) $pdo->query('SELECT status FROM users WHERE id = 2')->fetchColumn());
        self::assertSame('active', (string) $pdo->query('SELECT status FROM users WHERE id = 4')->fetchColumn());

        // Levée du bannissement : les statuts reviennent, sans toucher aux autres sessions.
        $this->setStatuses($pdo, [2 => 'active'], false);
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM game_sessions WHERE revoked_at IS NOT NULL')->fetchColumn());
    }

    public function testBanWithoutGameSessionsTableStillWorks(): void
    {
        $pdo = $this->sqlite();
        $pdo->exec("INSERT INTO users (id, email, status) VALUES (2, 'joueur@example.test', 'active')");

        $this->setStatuses($pdo, [2 => 'inactive'], true);

        self::assertSame('inactive', (string) $pdo->query('SELECT status FROM users WHERE id = 2')->fetchColumn());
    }

    /** Un administrateur de communauté ne sanctionne que les membres de sa communauté. */
    public function testCommunityModerationChecksMembershipBeforeAnySanction(): void
    {
        $code = $this->source('app/Controllers/Admin/Organization/ModerationOrganizationController.php');
        $check = strpos($code, '$this->userRepository->isMemberOfTenant($targetId, $tenantId)');
        self::assertNotFalse($check);
        self::assertLessThan((int) strpos($code, "if (\$type === 'warn')"), $check);
    }

    public function testNoticeShowsStartDateInParisTimeAndPermanence(): void
    {
        $notice = PlatformBanNotice::publicDetails(['id' => 7, 'created_at' => '2026-07-01 22:30:00', 'ends_at' => null]);
        self::assertSame('02/07/2026 à 00:30 (heure de Paris)', $notice['start']);
        self::assertTrue($notice['permanent']);
        self::assertSame('Sans date de fin', $notice['end']);

        $temporary = PlatformBanNotice::publicDetails(['id' => 8, 'created_at' => '2026-12-01 08:00:00', 'ends_at' => '2026-12-31 22:59:59'], 'en');
        self::assertSame('1 Dec 2026 at 09:00 (Paris time)', $temporary['start']);
        self::assertFalse($temporary['permanent']);
        self::assertSame('31 Dec 2026 at 23:59 (Paris time)', $temporary['end']);

        self::assertSame('', PlatformBanNotice::dateLabel(null));
        self::assertSame('', PlatformBanNotice::dateLabel('pas une date'));
        // Aucune donnée interne (modérateur, motif privé, adresse réseau) dans l'avis public.
        self::assertSame(
            ['id', 'rule', 'rule_url', 'locale', 'message', 'end', 'start', 'permanent', 'appeal_closed', 'appeal'],
            array_keys($notice)
        );
    }

    public function testAppealProgressMovesFromReceivedToDecision(): void
    {
        self::assertNull(PlatformBanNotice::appealProgress(null));
        $received = PlatformBanNotice::appealProgress(['created_at' => '2026-07-01 10:00:00', 'decision' => null]);
        self::assertSame('received', $received['state']);
        self::assertSame('01/07/2026 à 12:00 (heure de Paris)', $received['received']);
        self::assertSame('accepted', PlatformBanNotice::appealProgress(['created_at' => '2026-07-01 10:00:00', 'decision' => 'lift'])['state']);
        self::assertSame('upheld', PlatformBanNotice::appealProgress(['created_at' => '2026-07-01 10:00:00', 'decision' => 'uphold'])['state']);
    }

    public function testAppealUpdateMailNeverCarriesTheModerationReply(): void
    {
        $engine = new EmailTemplateEngine();
        foreach (['received', 'lift', 'uphold'] as $kind) {
            foreach (['fr', 'en'] as $language) {
                $mail = $engine->render('platform_ban_appeal_update', [
                    'updateKind' => $kind,
                    'appealUrl' => 'https://example.test/contester-sanction',
                    'appealLanguage' => $language,
                ]);
                self::assertStringContainsString('https://example.test/contester-sanction', $mail['html'], $kind . $language);
                self::assertStringContainsString('https://example.test/contester-sanction', $mail['text'], $kind . $language);
                self::assertStringNotContainsString('token=', $mail['text'], $kind . $language);
            }
        }
        $english = $engine->render('platform_ban_appeal_update', ['updateKind' => 'lift', 'appealUrl' => 'https://example.test/x', 'appealLanguage' => 'en']);
        self::assertStringContainsString('was accepted', $english['text']);
        $french = $engine->render('platform_ban_appeal_update', ['updateKind' => 'uphold', 'appealUrl' => 'https://example.test/x', 'appealLanguage' => 'fr']);
        self::assertStringContainsString('maintient la décision', $french['text']);
    }

    /** Dépôt refusé pour une sanction terminée, accusé de réception envoyé, décision notifiée. */
    public function testAppealFlowAcknowledgesDecidesAndStopsOnEndedBans(): void
    {
        $appeal = $this->source('app/Controllers/Auth/PlatformBanAppealController.php');
        self::assertStringContainsString('if (!self::isActive($ban)) {', $appeal);
        self::assertStringContainsString("'ban-appeal-submit:network:'", $appeal);
        self::assertStringContainsString('PlatformBanAppealMailer::RECEIVED', $appeal);
        $review = $this->source('app/Controllers/Admin/System/SystemPlatformBansController.php');
        self::assertStringContainsString('(new PlatformBanAppealMailer())->notify($ban, $decision)', $review);
    }

    /** Un compte banni dont la session était ouverte retrouve l'avis de suspension, pas un message vague. */
    public function testOpenSessionOfBannedAccountGetsTheSuspensionNotice(): void
    {
        $code = $this->source('app/Middleware/AuthMiddleware.php');
        self::assertStringContainsString("Session::flash('login_access_restriction', \$notice);", $code);
    }

    public function testLoginModalShowsScopeStartDurationAndAppealButton(): void
    {
        $view = $this->source('views/partials/auth/login_restriction.php');
        foreach (['auth.restriction_scope', 'auth.restriction_start', 'moderation.permanent', 'auth.restriction_appeal_button', 'auth.restriction_effects'] as $key) {
            self::assertStringContainsString($key, $view, $key);
        }
        self::assertStringNotContainsString('kicker', $view);
        self::assertStringNotContainsString('kicker', $this->source('views/auth/platform-ban-appeal.php'));
    }
}
