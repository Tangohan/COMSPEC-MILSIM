<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class OverwatchVanillaChatSilentAssetTest extends TestCase
{
    /** @var list<string> */
    private const CHAT_COMMANDS = [
        'systemChat',
        'sideChat',
        'globalChat',
        'groupChat',
        'vehicleChat',
        'commandChat',
        'customChat',
    ];

    public function testPollStillDeliversToAtakInboxNotVanillaChat(): void
    {
        $poll = $this->readRepo(
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf'
        );

        self::assertStringContainsString('COMSPEC_Athena_AlertInbox', $poll);
        self::assertStringContainsString('Iceman_ATAK_Group_messages', $poll);
        self::assertStringContainsString('_fnPushIcemanGroup', $poll);
        self::assertStringContainsString('(TOC)', $poll);
        $this->assertNoVanillaChatCalls($poll, 'fn_pollChatMessages.sqf');
    }

    public function testScreenNotificationsStayOffByDefaultAndDoNotMentionGameChat(): void
    {
        $pre = $this->readRepo(
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf'
        );
        $announce = $this->readRepo(
            'mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_announce.sqf'
        );

        self::assertMatchesRegularExpression(
            '/"comspec_overwatch_screen_notifications".{0,800}?"COMSPEC Overwatch", false/s',
            $pre
        );
        self::assertStringContainsString('N’écrit jamais dans le chat du jeu', $pre);
        $this->assertNoVanillaChatCalls($announce, 'fn_announce.sqf');
        self::assertStringContainsString('fnc_pushHtmlAlert', $announce);
    }

    public function testOverwatchAndSseSourcesDoNotInjectVanillaChat(): void
    {
        $roots = [
            'mod/UptoDate/Sources/comspec-overwatch-addons',
            'mod/@COMSPEC_SSE/addons',
        ];
        $hits = [];
        foreach ($roots as $rel) {
            $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_dir($dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            /** @var SplFileInfo $file */
            foreach ($it as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'sqf') {
                    continue;
                }
                $src = (string) file_get_contents($file->getPathname());
                $code = $this->stripSqfComments($src);
                foreach (self::CHAT_COMMANDS as $cmd) {
                    if (preg_match('/\b' . preg_quote($cmd, '/') . '\b/', $code) === 1) {
                        $hits[] = $file->getPathname() . ' → ' . $cmd;
                    }
                }
            }
        }

        self::assertSame([], $hits, "Vanilla chat injectors still present:\n" . implode("\n", $hits));
    }

    private function readRepo(string $relative): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return (string) file_get_contents($path);
    }

    private function assertNoVanillaChatCalls(string $src, string $label): void
    {
        $code = $this->stripSqfComments($src);
        foreach (self::CHAT_COMMANDS as $cmd) {
            self::assertDoesNotMatchRegularExpression(
                '/\b' . preg_quote($cmd, '/') . '\b/',
                $code,
                $label . ' still calls ' . $cmd
            );
        }
    }

    private function stripSqfComments(string $src): string
    {
        $withoutBlock = preg_replace('#/\*.*?\*/#s', '', $src) ?? $src;
        $lines = preg_split('/\R/', $withoutBlock) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $kept[] = preg_replace('#//.*$#', '', $line) ?? $line;
        }

        return implode("\n", $kept);
    }
}
