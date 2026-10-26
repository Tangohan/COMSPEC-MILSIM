<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * NET-006 / TEST-010 : après une courte panne d'Athena, la pause d'envoi commune de la DLL était réappliquée à chaque
 * succès (deux succès par palier, paliers de 45 s à 10 min, sans gigue) : sur le banc, la cadence normale des positions
 * ne revenait que 1 à 8 min après une panne de 30 s à 4 min, et tous les joueurs reprenaient dans la même seconde. Le
 * frein demandé par Athena (429, 403) reste exactement comme avant. Le comportement est mesuré par
 * tools/send-backoff-check (DLL chargée par réflexion) et par le banc réseau ; ce test garde la forme du correctif.
 */
final class AtakSendBackoffRecoveryTest extends TestCase
{
    private static function extension(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
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

    public function testOutageUsesShortRungsAndThrottleKeepsTheLongLadder(): void
    {
        $cs = self::extension();

        self::assertStringContainsString('OutageBackoffLadderSec = { 10, 20, 30, 45 }', $cs);
        self::assertStringContainsString('SendBackoffLadderSec = { 45, 75, 150, 300, 600 }', $cs);
        self::assertStringContainsString('_sendBackoffThrottled ? SendBackoffLadderSec : OutageBackoffLadderSec', $cs);

        $fail = self::body($cs, 'private static void NoteSendFailure(int code, string url, HttpResponseMessage? response)');
        self::assertMatchesRegularExpression('/if \(code is 429 or 403\)\s+_sendBackoffThrottled = true;/', $fail);
        self::assertStringContainsString('_sendBackoffStep < CurrentSendBackoffLadder().Length', $fail);
        self::assertStringContainsString('ApplySendPause(delaySec, jitterSec);', $fail);
        // Gigue : moitié du palier de panne (3 à 15 s), 20 % d'un Retry-After (15 s au plus), aucune pour un frein.
        self::assertStringContainsString('asked ? Math.Min(15, delaySec / 5)', $fail);
        self::assertStringContainsString(': Math.Clamp(delaySec / 2, 3, 15);', $fail);

        // La maintenance (503) est une panne : elle n'arrive plus déguisée en 429.
        $limited = self::body($cs, 'private static void NoteRateLimited(HttpResponseMessage? response = null)');
        self::assertStringContainsString('(int)response.StatusCode == 503 ? 503 : 429', $limited);
    }

    public function testOutageTurningIntoAThrottleRestartsAtTheFirstLongRung(): void
    {
        // Un 429 ou un 403 qui arrive pendant une panne déjà montée sur l'échelle courte gardait le palier de la panne,
        // relu sur l'échelle longue (palier 3 = 150 s, palier 4 = 300 s). Le frein repart du premier palier de l'échelle
        // longue (45 s, ou le Retry-After s'il est plus long) et l'heure d'escalade est notée : une rafale de 429 ne
        // monte pas aussitôt d'un palier. Comportement mesuré par tools/send-backoff-check (H2, H3, H4).
        $fail = self::body(
            self::extension(),
            'private static void NoteSendFailure(int code, string url, HttpResponseMessage? response)'
        );

        self::assertStringContainsString(
            'var outageTurnsToThrottle = (code is 429 or 403) && _sendBackoffStep > 0 && !_sendBackoffThrottled;',
            $fail
        );
        self::assertMatchesRegularExpression(
            '/if \(outageTurnsToThrottle\)\s*\{\s*_sendBackoffStep = 1;\s*'
            . 'System\.Threading\.Interlocked\.Exchange\(ref _lastBackoffEscalateTicks, DateTime\.UtcNow\.Ticks\);\s*\}'
            . '\s*else if \(_sendBackoffStep > 0\)/',
            $fail
        );
        // Le passage se calcule avant que le drapeau du frein soit posé, sinon il ne se verrait jamais.
        $turn = strpos($fail, 'var outageTurnsToThrottle');
        $flag = strpos($fail, 'if (code is 429 or 403)');
        self::assertNotFalse($turn);
        self::assertNotFalse($flag);
        self::assertLessThan($flag, $turn);
    }

    public function testFirstSuccessEndsAnOutagePause(): void
    {
        $ok = self::body(self::extension(), 'private static void NoteSendSuccess()');

        self::assertStringContainsString('if (_sendBackoffThrottled && _sendOkStreak < SendOkStreakToStepDown)', $ok);
        self::assertStringContainsString('_sendBackoffStep = _sendBackoffThrottled ? _sendBackoffStep - 1 : 0;', $ok);
        self::assertStringNotContainsString('_sendBackoffStep--;', $ok);
        // Le SQF reprend la production des positions (COMSPEC_SendBackoffSec à 0) et les sondages en attente de panne
        // repartent.
        self::assertStringContainsString('InvokeCallback("RateLimitClear", "");', $ok);
        self::assertStringContainsString('NotifySendBackoff(0);', $ok);
        self::assertStringContainsString('ResetPollOutageWaits();', $ok);
    }

    public function testOutagePausesAreJitteredAndThrottlesKeepExactDelays(): void
    {
        $cs = self::extension();

        // Jamais plus courte que demandé ; gigue seulement hors frein (429, 403 : délai exact, comme avant).
        $pause = self::body($cs, 'private static void ApplySendPause(int delaySec, int jitterMaxSec = -1)');
        self::assertStringContainsString('sec * 1000L + Random.Shared.NextInt64(jitterMs + 1)', $pause);
        self::assertStringContainsString('var jitterMs = _sendBackoffThrottled ? 0 : ', $pause);
        self::assertStringNotContainsString('AddSeconds(', $pause);

        // Canaux sondés : gigue sur l'attente d'une panne, attente exacte après un refus (401, 403, 429).
        $poll = self::body($cs, 'private static void NotePollResult(PollGetSlot slot, int code)');
        self::assertStringContainsString('slot.RefusedOutage = code < 0 || code >= 500;', $poll);
        self::assertMatchesRegularExpression(
            '/if \(slot\.RefusedOutage\)\s+waitMs \+= Random\.Shared\.NextInt64\(waitMs \/ 5 \+ 1\);/',
            $poll
        );

        $reset = self::body($cs, 'private static void ResetPollOutageWaits()');
        self::assertStringContainsString('if (!slot.RefusedOutage) continue;', $reset);
    }

    public function testPollBackFromOutageEndsAnOutagePauseButNotAThrottle(): void
    {
        $cs = self::extension();

        // Seul un canal qui a lui-même vu la panne prouve le retour d'Athena (maintenance sans fin annoncée :
        // Retry-After de 900 s, plafonné à 600 s, au lieu du retour réel).
        $poll = self::body($cs, 'private static void NotePollResult(PollGetSlot slot, int code)');
        self::assertMatchesRegularExpression(
            '/var backFromOutage = slot\.RefusedOutage;.*if \(backFromOutage\)\s+NoteAthenaBackFromPoll\(\);/s',
            $poll
        );

        $back = self::body($cs, 'private static void NoteAthenaBackFromPoll()');
        self::assertStringContainsString('if (_sendBackoffStep <= 0 || _sendBackoffThrottled)', $back);
        self::assertStringContainsString('NoteSendSuccess();', $back);
    }
}
