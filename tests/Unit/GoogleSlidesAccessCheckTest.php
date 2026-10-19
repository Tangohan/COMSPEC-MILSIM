<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\GoogleSlidesAccessCheck as Check;
use PHPUnit\Framework\TestCase;

/** Lien de briefing Google Slides : vérifié à l'enregistrement pour éviter les google_deck_error en jeu. */
final class GoogleSlidesAccessCheckTest extends TestCase
{
    private const DECK = 'https://docs.google.com/presentation/d/1EAYk18WDjIG-zp_0vLm3CsfQh_i8eXc67Jo2O9C6Vuc/edit#slide=id.p';

    public function testPublishToWebLinkIsRefused(): void
    {
        $url = 'https://docs.google.com/presentation/d/e/2PACX-1vQabcdefghijklmnopqrstuvwxyz/pub?start=false';
        self::assertTrue(Check::isPublishLink($url));
        self::assertSame(Check::PUBLISH_LINK, (new Check(static fn (): array => ['status' => 200, 'type' => 'image/png']))->check($url));
    }

    public function testSharedDeckAnswersWithAnImage(): void
    {
        $seen = '';
        $check = new Check(static function (string $u) use (&$seen): array {
            $seen = $u;

            return ['status' => 200, 'type' => 'image/png'];
        });
        self::assertSame(Check::OK, $check->check(self::DECK));
        self::assertStringStartsWith('https://docs.google.com/presentation/d/1EAYk18WDjIG-zp_0vLm3CsfQh_i8eXc67Jo2O9C6Vuc/export/png', $seen);
    }

    public function testPrivateDeckReturnsTheLoginPage(): void
    {
        self::assertSame(Check::PRIVATE, (new Check(static fn (): array => ['status' => 200, 'type' => 'text/html; charset=utf-8']))->check(self::DECK));
        self::assertSame(Check::PRIVATE, (new Check(static fn (): array => ['status' => 403, 'type' => 'text/html']))->check(self::DECK));
    }

    public function testNetworkProblemDoesNotBlock(): void
    {
        self::assertSame(Check::UNKNOWN, (new Check(static fn (): array => ['status' => 0, 'type' => '']))->check(self::DECK));
        self::assertSame(Check::UNKNOWN, (new Check(static fn (): array => ['status' => 429, 'type' => 'text/html']))->check(self::DECK));
    }
}
