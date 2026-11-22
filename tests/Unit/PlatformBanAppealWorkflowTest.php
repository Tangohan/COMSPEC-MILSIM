<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\PlatformBanAppealRepository;
use App\Services\Account\PlatformBanNotice;
use App\Services\EmailTemplateEngine;
use PDO;
use PHPUnit\Framework\TestCase;

final class PlatformBanAppealWorkflowTest extends TestCase
{
    public function testOnlyUnexpiredLinksAuthorizeTheirOwnDecision(): void
    {
        $pdo = $this->database();
        $appeals = new PlatformBanAppealRepository($pdo);
        $firstHash = hash('sha256', 'first');
        $expiredHash = hash('sha256', 'expired');
        self::assertTrue($appeals->ready());

        $appeals->storeLink(40, $firstHash, time() + 3600);
        $appeals->storeLink(41, $expiredHash, time() - 60);

        self::assertSame(40, (int) $appeals->findValidLink($firstHash)['ban_id']);
        self::assertNull($appeals->findValidLink($expiredHash));
        self::assertNull($appeals->findValidLink(hash('sha256', 'unknown')));
    }

    public function testEachSanctionKeepsItsFirstStatementAndDecision(): void
    {
        $appeals = new PlatformBanAppealRepository($this->database());

        self::assertTrue($appeals->submit(40, 'Première contestation.'));
        self::assertFalse($appeals->submit(40, 'Tentative de doublon.'));
        self::assertSame(1, $appeals->countPending());
        self::assertSame('Première contestation.', $appeals->find(40)['statement']);

        self::assertTrue($appeals->decide(40, 1, 'uphold', 'Réponse de la modération.'));
        self::assertFalse($appeals->decide(40, 2, 'lift', 'Autre décision.'));
        self::assertSame(0, $appeals->countPending());
        self::assertSame('uphold', $appeals->find(40)['decision']);
        self::assertSame('Réponse de la modération.', $appeals->find(40)['public_reply']);
    }

    public function testClosingAppealsBlocksNewSubmissionsAndPreservesExistingCases(): void
    {
        $pdo = $this->database();
        $pdo->exec('CREATE TABLE platform_account_bans (id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO platform_account_bans (id) VALUES (40), (41)');
        $migration = dirname(__DIR__, 2) . '/database/steps/4950_platform_ban_appeal_closure.php';
        require $migration;
        require $migration;

        $bans = new PlatformAccountBanRepository($pdo);
        $appeals = new PlatformBanAppealRepository($pdo);
        $linkHash = hash('sha256', 'issued-before-closure');
        $appeals->storeLink(40, $linkHash, time() + 3600);

        self::assertTrue($bans->supportsAppealClosure());
        self::assertFalse((bool) $bans->findById(40)['appeal_closed']);
        self::assertTrue($appeals->submit(41, 'Contestation déjà reçue.'));
        self::assertTrue($bans->setAppealClosed(40, true));
        self::assertFalse($bans->setAppealClosed(40, true));
        self::assertNull($appeals->submit(40, 'Tentative avec un ancien lien.'));
        self::assertNull($appeals->find(40));
        self::assertFalse($pdo->inTransaction());
        self::assertSame(40, (int) $appeals->findValidLink($linkHash)['ban_id']);

        self::assertTrue($bans->setAppealClosed(41, true));
        self::assertSame('Contestation déjà reçue.', $appeals->find(41)['statement']);
        self::assertTrue($appeals->decide(41, 1, 'uphold', 'Réponse enregistrée.'));
        self::assertTrue($bans->setAppealClosed(40, false));
        self::assertTrue($appeals->submit(40, 'Contestation après réouverture.'));
        self::assertFalse($appeals->submit(40, 'Tentative de doublon.'));
        self::assertFalse($pdo->inTransaction());
    }

    public function testNoticeTemplatesCanBeSentAndDatesAreDisplayedInParisTime(): void
    {
        $url = 'https://example.test/contester-sanction?token=fixture';
        $mail = (new EmailTemplateEngine())->render('platform_ban_appeal_link', ['appealUrl' => $url]);

        self::assertStringContainsString($url, $mail['html']);
        self::assertStringContainsString($url, $mail['text']);
        self::assertSame('20/11/2026 à 13:00 (heure de Paris)', PlatformBanNotice::endLabel('2026-11-20 12:00:00'));
        self::assertSame('Sans date de fin', PlatformBanNotice::endLabel(null));
    }

    public function testEnglishNoticesTranslatePresetsAndKeepCustomReasons(): void
    {
        $preset = PlatformBanNotice::MODELS['contenus'];
        $notice = PlatformBanNotice::publicDetails([
            'id' => 40,
            'rule_reference' => $preset['rule'],
            'public_reason' => $preset['text'],
            'ends_at' => '2026-11-20 12:00:00',
        ], 'en');

        self::assertSame('Athena Code of Conduct · Article 2', $notice['rule']);
        self::assertStringEndsWith('/code-de-conduite?lang=en#article-2', $notice['rule_url']);
        $historical = PlatformBanNotice::publicDetails(['id' => 41, 'rule_reference' => 'CGU Athena · Contenus utilisateurs et modération'], 'en');
        self::assertSame('Athena Terms of Use · User content and moderation', $historical['rule']);
        self::assertSame('', $historical['rule_url']);
        self::assertStringContainsString('the moderation team restricted your access.', $notice['message']);
        self::assertSame('20 Nov 2026 at 13:00 (Paris time)', $notice['end']);
        self::assertSame('No end date', PlatformBanNotice::endLabel(null, 'en'));
        self::assertSame('Motif personnalisé', PlatformBanNotice::publicDetails(['id' => 40, 'public_reason' => 'Motif personnalisé'], 'en')['message']);
        self::assertStringStartsWith('The Athena team', PlatformBanNotice::publicDetails(['id' => 40], 'en')['message']);

        $mail = (new EmailTemplateEngine())->render('platform_ban_appeal_link', [
            'appealUrl' => 'https://example.test/contester-sanction?token=fixture',
            'appealLanguage' => 'en',
        ]);
        self::assertStringContainsString('<html lang="en">', $mail['html']);
        self::assertStringContainsString('View decision', $mail['html']);
        self::assertStringContainsString('This link is private and expires in one hour.', $mail['text']);
    }

    public function testClosedNoticesRemoveTheInvitationFromBuiltInMessages(): void
    {
        $notice = PlatformBanNotice::publicDetails(['id' => 40, 'appeal_closed' => 1], 'en');
        self::assertTrue($notice['appeal_closed']);
        self::assertSame('The Athena team has restricted access to your account.', $notice['message']);
        self::assertSame('Motif personnalisé.', PlatformBanNotice::displayMessage('Motif personnalisé.', true));

        $mail = (new EmailTemplateEngine())->render('platform_ban_appeal_link', [
            'appealUrl' => 'https://example.test/contester-sanction?token=fixture',
            'appealLanguage' => 'en',
            'appealClosed' => true,
        ]);
        self::assertStringContainsString('track an appeal you have already submitted', $mail['text']);
        self::assertStringNotContainsString('submit an appeal', $mail['text']);
    }

    public function testInactivityMessagesDescribeTheCorrectTriggerAndRespectAppealClosure(): void
    {
        $first = PlatformBanNotice::MODELS['inactivite_initiale'];
        $notice = PlatformBanNotice::publicDetails(['id' => 1, 'public_reason' => $first['text'], 'rule_reference' => $first['rule']], 'en');
        self::assertStringContainsString('since it was created', $notice['message']);
        self::assertStringContainsString('review this decision', $notice['message']);
        self::assertStringEndsWith('/code-de-conduite?lang=en#article-7', $notice['rule_url']);
        $closed = PlatformBanNotice::displayMessage($first['text'], true, 'en');
        self::assertStringContainsString('since it was created', $closed);
        self::assertStringNotContainsString('review', $closed);
        self::assertStringContainsString('depuis une période prolongée', PlatformBanNotice::displayMessage(PlatformBanNotice::MODELS['inactivite']['text'], false));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE platform_ban_appeal_links (token_hash TEXT PRIMARY KEY, ban_id INTEGER, expires_at TEXT)');
        $pdo->exec('CREATE TABLE platform_ban_appeals (
            ban_id INTEGER PRIMARY KEY, statement TEXT, created_at TEXT,
            decision TEXT, public_reply TEXT, reviewed_at TEXT, reviewed_by INTEGER
        )');

        return $pdo;
    }
}
