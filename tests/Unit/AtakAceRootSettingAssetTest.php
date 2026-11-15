<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAceRootSettingAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testThePersonalSettingExistsAndDefaultsToTheCurrentBehaviour(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/"comspec_atak_native_ace_root", "CHECKBOX",.*?"ATAK · Menu ACE"\], true, 0\] call comspec_atak_native_fnc_addSetting;/s', $pre);
    }

    public function testTheThreeRootEntriesHonourTheSetting(): void
    {
        foreach (['functions/shell/fn_aceMenu.sqf', 'functions/toc/fn_tocStation.sqf', 'functions/toc/fn_tocInbox.sqf'] as $file) {
            self::assertStringContainsString('comspec_atak_native_ace_root', self::read(self::MAIN . $file), $file);
        }
    }

    public function testEnglishEntriesAreInTheStrictFormat(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        self::assertStringContainsString('["Menu ACE : entrées ATAK à la racine", "ACE menu: ATAK entries at the root"],', $en);
        self::assertMatchesRegularExpression('/\["ATAK · Menu ACE", "ATAK · ACE menu"\]\R\]\s*$/', $en, 'aucune virgule après la dernière entrée');
        self::assertStringNotContainsString('\\"', $en);
    }

    public function testNoUserInterfaceNotificationIsAdded(): void
    {
        foreach (['functions/shell/fn_aceMenu.sqf', 'functions/toc/fn_tocStation.sqf', 'functions/toc/fn_tocInbox.sqf'] as $file) {
            self::assertDoesNotMatchRegularExpression('/\b(systemChat|hintSilent|cutText|titleText)\b/', self::read(self::MAIN . $file), $file);
        }
    }
}
