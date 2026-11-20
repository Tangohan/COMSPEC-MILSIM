<?php

declare(strict_types=1);

namespace Tests\Unit;

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

    public function testNoticeTemplatesCanBeSentAndDatesAreDisplayedInParisTime(): void
    {
        $url = 'https://example.test/contester-sanction?token=fixture';
        $mail = (new EmailTemplateEngine())->render('platform_ban_appeal_link', ['appealUrl' => $url]);

        self::assertStringContainsString($url, $mail['html']);
        self::assertStringContainsString($url, $mail['text']);
        self::assertSame('20/11/2026 à 13:00 (heure de Paris)', PlatformBanNotice::endLabel('2026-11-20 12:00:00'));
        self::assertSame('Sans date de fin', PlatformBanNotice::endLabel(null));
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
