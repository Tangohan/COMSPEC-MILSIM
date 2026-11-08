<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** /traduire-atak : le compte fait foi, et une panne du serveur n'est jamais annoncée comme un enregistrement réussi. */
final class AtakTranslateAccountStorageAssetTest extends TestCase
{
    private function js(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-translate.js');
    }

    public function testDeviceKeepsOnlyWhatTheServerHasNotConfirmed(): void
    {
        $js = $this->js();

        self::assertStringContainsString('function keepUnconfirmed()', $js);
        self::assertStringContainsString('if (S.pack) { keepUnconfirmed(); return; }', $js);
        self::assertStringContainsString('if (S.holdLocal) { return; }', $js);
    }

    public function testLocalWorkIsReadBeforeOpeningTheAccountPack(): void
    {
        $js = $this->js();
        $open = substr($js, (int) strpos($js, 'function openAccount(id)'), 700);

        self::assertLessThan(strpos($open, 'openPack(true)'), strpos($open, 'loadLocal(mine.code)'));
    }

    public function testServerFailureIsNotShownAsSavedAndIsRetried(): void
    {
        $js = $this->js();

        self::assertStringContainsString('ensureAccount().catch(accountUnavailable)', $js);
        self::assertStringNotContainsString("S.pack = null;\n                    showWorkspace();\n                    setSync(tx('saved_local'), 'ok');", str_replace("\r\n", "\n", $js));
        self::assertStringContainsString('accountRetry = setTimeout', $js);
    }
}
