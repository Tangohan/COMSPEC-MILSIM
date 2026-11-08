<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAiProfileRepository;
use App\Services\Sse\SseAiProfileService;
use PHPUnit\Framework\TestCase;

/** Profils d'IA : valeurs bornées, ligne de liste du mod, isolation par communauté, câblage statique des bulles. */
final class SseAiProfileTest extends TestCase
{
    public function testNormalizeRefusesUnknownValues(): void
    {
        $r = SseAiProfileRepository::normalize(['label' => '  ', 'side' => 'mars', 'category' => 'x', 'papers' => 'y', 'behaviour' => 'z', 'identity_id' => 'abc']);
        self::assertSame('Profil sans nom', $r['label']);
        self::assertSame('civ', $r['side']);
        self::assertSame('civilian', $r['category']);
        self::assertSame('identity', $r['papers']);
        self::assertSame('auto', $r['behaviour']);
        self::assertNull($r['identity_id']);
    }

    public function testListLineIsOneLineAndParseSimpleArraySafe(): void
    {
        $line = SseAiProfileService::listLine([['id' => 3, 'ref' => 'PRF-0003', 'label' => "Dit \"le|berger\"\nnord", 'name' => 'A B', 'side' => 'civ', 'category' => 'civilian']]);
        self::assertStringNotContainsString('|', $line);
        self::assertStringNotContainsString("\n", $line);
        self::assertStringContainsString('""le', $line);
    }

    public function testListLineStaysUnderResponseSize(): void
    {
        $rows = array_fill(0, 500, ['id' => 1, 'ref' => 'PRF-0001', 'label' => str_repeat('x', 100), 'name' => str_repeat('y', 40), 'side' => 'civ', 'category' => 'civilian']);
        self::assertLessThanOrEqual(7400, strlen(SseAiProfileService::listLine($rows)));
    }

    public function testBubbleIsRegisteredAndCalledBySse(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/';
        self::assertStringContainsString('class aiBubble {}', (string) file_get_contents($root . 'COMSPEC_ATAK_Native/Sources/addons/main/config.cpp'));
        self::assertStringContainsString('comspec_atak_native_fnc_aiBubble', (string) file_get_contents($root . '@COMSPEC_SSE/addons/core/functions/fn_toast.sqf'));
        self::assertStringContainsString('class toast {}', (string) file_get_contents($root . '@COMSPEC_SSE/addons/core/config.cpp'));
        $client = (string) file_get_contents($root . '@COMSPEC_SSE/addons/identity/functions/fn_idClient.sqf');
        self::assertStringContainsString('comspec_sse_fnc_toast', $client);
        self::assertStringContainsString('HandleChatMessage', (string) file_get_contents($root . 'COMSPEC_ATAK_Native/Sources/addons/main/XEH_postInitClient.sqf'));
    }

    public function testViewerHasNoSimulationMentionForGameCountries(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/addons/identity/functions/fn_idViewerOnLoad.sqf');
        self::assertStringNotContainsString('SIMULATION · ', $src);
    }
}
