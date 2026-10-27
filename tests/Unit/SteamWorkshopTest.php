<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SteamWorkshop;
use PHPUnit\Framework\TestCase;

/** Liens Steam Workshop du mod Athena et de la collection d'une communauté. */
final class SteamWorkshopTest extends TestCase
{
    public function testParseId(): void
    {
        self::assertSame('3684656708', SteamWorkshop::parseId('3684656708'));
        self::assertSame('3012345678', SteamWorkshop::parseId(' https://steamcommunity.com/sharedfiles/filedetails/?id=3012345678&searchtext= '));
        self::assertSame('3012345678', SteamWorkshop::parseId('https://steamcommunity.com/workshop/filedetails/?l=french&id=3012345678'));
        self::assertSame('3012345678', SteamWorkshop::parseId('steam://url/CommunityFilePage/3012345678'));
        self::assertSame('', SteamWorkshop::parseId('  '));
        self::assertSame(null, SteamWorkshop::parseId('https://example.com/?id=3012345678'));
    }

    public function testItemsAndUrls(): void
    {
        $items = SteamWorkshop::items();
        self::assertSame('atak', $items[0]['key']);
        self::assertSame(SteamWorkshop::ATAK_NATIVE_ID, $items[0]['id']);
        self::assertSame('steam://url/CommunityFilePage/3684656708', SteamWorkshop::clientUrl('3684656708'));
        self::assertSame('https://steamcommunity.com/sharedfiles/filedetails/?id=3684656708', SteamWorkshop::webUrl('3684656708'));
        self::assertSame('', SteamWorkshop::webUrl(''));
    }
}
