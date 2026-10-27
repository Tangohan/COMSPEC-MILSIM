<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * NET-020 / NET-022 : un 403 prévu par conception ne doit plus suspendre toute la liaison.
 *  - « feature_unavailable » (fonction hors offre) : la DLL appelait NoteAccessDenied AVANT NoteRefusal, donc une pause
 *    commune de 15 à 30 s (positions comprises) et le rappel AccessDenied, qui suspend aussi les boucles SQF.
 *  - « feature_disabled » (fonction coupée par la communauté, GameFeatureGate) n'était jamais verrouillé : pause de
 *    20 s qui revenait sans fin, envoi remis en file et gardé dans queue.jsonl d'une session à l'autre.
 * Le verrou décide maintenant d'abord ; feature_disabled ne fait taire que les envois (le site ne refuse que les POST
 * du jeu, la lecture continue). Le comportement est mesuré par tools/send-backoff-check (L1 à L4) et par le banc réseau ;
 * ce test garde la forme du correctif.
 */
final class AtakRefusalLatchSendTest extends TestCase
{
    private static function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/' . $file);
    }

    /** Corps de la méthode dont la signature est donnée (accolades équilibrées). */
    private static function body(string $src, string $signature): string
    {
        $at = strpos($src, $signature);
        self::assertNotFalse($at, 'méthode introuvable : ' . $signature);
        $open = strpos($src, '{', $at);
        self::assertNotFalse($open);
        $depth = 0;
        $len = strlen($src);
        for ($i = $open; $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}' && --$depth === 0) {
                return substr($src, $open, $i - $open + 1);
            }
        }
        self::fail('accolades non équilibrées : ' . $signature);
    }

    /** Le refus est noté (et verrouillé) avant toute pause commune. */
    private static function assertLatchDecidesFirst(string $block, string $refusal, string $denied): void
    {
        $r = strpos($block, $refusal);
        $d = strpos($block, $denied);
        self::assertNotFalse($r, 'absent : ' . $refusal);
        self::assertNotFalse($d, 'absent : ' . $denied);
        self::assertLessThan($d, $r, 'NoteRefusal doit précéder NoteAccessDenied');
    }

    public function testFeatureDisabledLatchesSendsOnlyForThirtyMinutesOrTheSession(): void
    {
        $latch = self::source('Extension_RefusalLatch.cs');
        $note = self::body($latch, 'private static void NoteRefusal(string url, int status, string body)');

        self::assertMatchesRegularExpression(
            '/if \(status == 403 && code == "feature_disabled"\)\s*\{\s*'
            . 'SendLatchUntilMs\[RefusalEndpointKey\(url \?\? ""\)\] = Environment\.TickCount64 \+ FeatureLatchMs;\s*'
            . 'return;\s*\}/',
            $note
        );
        // Envois : tous les verrous ; sondages : sans celui des envois (une fonction coupée se lit encore).
        self::assertStringContainsString(
            'private static bool IsRefusalLatched(string url) => IsPollRefusalLatched(url) || IsSendLatched(url);',
            $latch
        );
        self::assertStringContainsString(
            'private static bool IsPollRefusalLatched(string url) => IsAuthLatched() || IsFeatureLatched(url);',
            $latch
        );
        // Session suivante : tout repart.
        $clear = self::body($latch, 'private static void ClearRefusalLatches()');
        self::assertStringContainsString('SendLatchUntilMs.Clear();', $clear);
    }

    public function testLatchedRefusalSetsNeitherTheSharedPauseNorTheSqfPause(): void
    {
        $ext = self::source('Extension.cs');

        $post = self::body($ext, 'private static void HandlePostResponse(HttpResponseMessage? response, string url, string jsonBody)');
        self::assertLatchDecidesFirst($post, 'NoteRefusal(url, 403, RefusalBody(response));', 'NoteAccessDenied(response, url);');
        self::assertMatchesRegularExpression(
            '/NoteRefusal\(url, 403, RefusalBody\(response\)\);\s*if \(IsRefusalLatched\(url\)\)\s*return;\s*'
            . 'NoteAccessDenied\(response, url\);\s*if \(!IsBestEffortEndpoint\(url\)\)\s*EnqueueForRetry\(url, jsonBody\);/',
            $post
        );

        $queued = self::body($ext, 'private static bool TrySendQueuedPost(');
        self::assertStringContainsString('if (IsFeatureLatched(item.Url) || IsSendLatched(item.Url))', $queued);
        self::assertLatchDecidesFirst($queued, 'NoteRefusal(item.Url, 403, RefusalBody(response));', 'NoteAccessDenied(response, item.Url);');
        self::assertMatchesRegularExpression(
            '/NoteRefusal\(item\.Url, 403, RefusalBody\(response\)\);\s*if \(IsRefusalLatched\(item\.Url\)\)\s*return true;/',
            $queued
        );

        $weather = self::body($ext, 'private static void HandleWeatherPostResponse(HttpResponseMessage? response, string url)');
        self::assertMatchesRegularExpression(
            '/NoteRefusal\(url, 403, RefusalBody\(response\)\);\s*if \(!IsRefusalLatched\(url\)\)\s*NoteAccessDenied\(response, url\);/',
            $weather
        );

        // Route PATCH du pilote : même ordre.
        self::assertMatchesRegularExpression(
            '/NoteRefusal\(url, code, RefusalBody\(resp\)\);\s*if \(code == 403 && !IsRefusalLatched\(url\)\)\s*NoteAccessDenied\(resp, url\);/',
            $ext
        );
    }

    public function testPollsAndSelfTestIgnoreTheSendOnlyLatch(): void
    {
        $poll = self::body(self::source('Extension.cs'), 'private static string ServePollGet(string cacheKey, string url, Func<string, int, string> format)');
        self::assertStringContainsString('if (IsFeatureLatched(url))', $poll);
        self::assertStringNotContainsString('IsRefusalLatched(', $poll);

        self::assertStringContainsString('var stopped = IsPollRefusalLatched(c.Url);', self::source('Extension_SelfTest.cs'));
    }
}
