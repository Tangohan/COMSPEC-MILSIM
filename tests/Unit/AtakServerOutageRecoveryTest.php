<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * NET-023 / NET-015 : reprise après une panne d'Athena entier en 5xx (nginx sans PHP : 502/504 ; base injoignable :
 * 503 sans « maintenance ») et gigue de la pause par adresse.
 *  - Avant, chaque 5xx ne mettait en pause que son adresse (30, 60, 120 puis 300 s, sans gigue ni sortie anticipée) :
 *    après une panne de 2 min, la 1re position revenait 91 à 97 s après la fin (bancs gw502_long et db503_long), et les
 *    joueurs refusés ensemble redemandaient ensemble (banc S4b à 6 joueurs : 38 à 39 % des relances à moins d'une
 *    seconde de celle d'un autre joueur, 19 à 25 % refusées à nouveau).
 *  - Maintenant, 5xx sur au moins deux adresses (sondages compris) sans réponse acceptée depuis 10 s : pause commune
 *    des envois (échelle courte, sortie au premier succès), pauses par adresse de la panne levées ; sinon pause de
 *    l'adresse seule, avec 0 à 20 % de gigue, sans dépasser le 1er palier (30 s) pour les adresses de position.
 * Le comportement est mesuré par tools/send-backoff-check (S1 à S11) et par les bancs ; ce test garde la forme.
 */
final class AtakServerOutageRecoveryTest extends TestCase
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

    public function testServerWideOutageTakesTheSharedPauseAndLiftsPerAddressWaits(): void
    {
        $ext = self::source('Extension.cs');
        $note = self::body($ext, 'private static bool TryNoteEndpointServerError(string url, HttpResponseMessage? response)');

        // La maintenance reste traitée à part (pause commune par NoteRateLimited), avant tout le reste.
        $seen = strpos($note, 'RecentServerErrorTicks[key] = now;');
        $maintenance = strpos($note, 'if (code == 503 && LooksLikeMaintenance(response)) return false;');
        self::assertNotFalse($seen, 'le 5xx de l\'adresse doit être noté pour la détection de panne');
        self::assertNotFalse($maintenance);
        self::assertLessThan($seen, $maintenance, 'la maintenance doit être écartée avant');
        self::assertMatchesRegularExpression(
            '/if \(IsServerWideOutage\(now\)\)\s*\{.*?'
            . 'EndpointCooldowns\[kv\.Key\] = \(0, kv\.Value\.Step\);\s*'
            . 'NoteSendFailure\(code == 503 \? 503 : 0, url, response\);\s*return true;\s*\}/s',
            $note
        );
    }

    public function testPerAddressWaitGetsUpToTwentyPercentJitterNeverLessThanAsked(): void
    {
        $note = self::body(self::source('Extension.cs'), 'private static bool TryNoteEndpointServerError(string url, HttpResponseMessage? response)');
        self::assertStringContainsString(
            'var sec = Math.Max(EndpointCooldownLadderSec[step], Math.Min(ParseRetryAfterSeconds(response, 0), 300));',
            $note
        );
        self::assertMatchesRegularExpression(
            '/var ticks = TimeSpan\.FromSeconds\(sec\)\.Ticks;\s*'
            . 'EndpointCooldowns\[key\] = \(now \+ ticks \+ Random\.Shared\.NextInt64\(ticks \/ 5 \+ 1\), step\);/',
            $note
        );
    }

    public function testOutageNeedsTwoAddressesAndTenQuietSeconds(): void
    {
        $ext = self::source('Extension.cs');
        self::assertStringContainsString('private const int ServerOutageMinAddresses = 2;', $ext);
        self::assertStringContainsString('private const int ServerOutageQuietSec = 10;', $ext);
        $outage = self::body($ext, 'private static bool IsServerWideOutage(long nowTicks)');
        self::assertStringContainsString('if (nowTicks - lastOk < TimeSpan.FromSeconds(ServerOutageQuietSec).Ticks)', $outage);
        self::assertStringContainsString('if (kv.Value > lastOk && ++n >= ServerOutageMinAddresses)', $outage);
    }

    public function testAcceptedResponsesAndFailingPollsFeedTheDetector(): void
    {
        $ext = self::source('Extension.cs');
        self::assertStringContainsString('NoteAthenaOk();', self::body($ext, 'private static void NoteSendSuccess()'));
        $poll = self::body($ext, 'private static void NotePollResult(PollGetSlot slot, int code)');
        self::assertMatchesRegularExpression(
            '/if \(code >= 500 && !string\.IsNullOrEmpty\(slot\.Url\)\)\s*'
            . 'RecentServerErrorTicks\[EndpointKey\(slot\.Url\)\] = DateTime\.UtcNow\.Ticks;/',
            $poll
        );
        self::assertMatchesRegularExpression('/else if \(code is >= 200 and < 400\)\s*\{\s*NoteAthenaOk\(\);/', $poll);
    }

    public function testDrainStopsWhenTheOutageSetsTheSharedPause(): void
    {
        $queued = self::body(self::source('Extension.cs'), 'private static bool TrySendQueuedPost(');
        self::assertMatchesRegularExpression(
            '/if \(TryNoteEndpointServerError\(item\.Url, response\)\)\s*\{.*?return !IsPostPausedNow\(\);\s*\}/s',
            $queued
        );

        $batch = self::body(self::source('Extension_Telemetry.cs'), 'private static bool TryDrainTelemetryBatch()');
        self::assertMatchesRegularExpression(
            '/if \(IsPostPausedNow\(\)\)\s*RequeueTelemetryItems\(items\);\s*else\s*FallbackTelemetryUnitary\(items\);/',
            $batch
        );
    }

    public function testPositionAddressesStayOnTheFirstStepWhenOnlyTheirRouteFails(): void
    {
        $note = self::body(self::source('Extension.cs'), 'private static bool TryNoteEndpointServerError(string url, HttpResponseMessage? response)');
        // Plafond court des adresses de position (lot de télémétrie, envoi unitaire) : posé après le calcul du palier,
        // avant la durée, pour qu'un Retry-After plus long reste respecté.
        self::assertMatchesRegularExpression(
            '/step = Math\.Min\(prev\.Step \+ 1, EndpointCooldownLadderSec\.Length - 1\);.*?'
            . 'if \(IsPositionEndpoint\(url\) \|\| IsTelemetryBatchEndpoint\(url\)\)\s*step = 0;\s*'
            . 'var sec = Math\.Max\(EndpointCooldownLadderSec\[step\], Math\.Min\(ParseRetryAfterSeconds\(response, 0\), 300\)\);/s',
            $note
        );
    }
}
