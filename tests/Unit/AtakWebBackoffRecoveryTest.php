<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * NET-006, client web Overwatch : après une courte panne d'Athena, la pause du poste était réappliquée à chaque
 * succès (deux succès par palier, paliers de 45 s à 10 min, sans gigue), et la page /atak ne relit rien pendant une
 * pause (atakPoll) : carte figée jusqu'à 150 s après le retour d'Athena. Le frein demandé par Athena (429, 403) reste
 * exactement comme avant. Le comportement est mesuré par tools/web-backoff-check/check.js (script chargé dans Node avec
 * une horloge et un fetch simulés) ; ce test garde la forme du correctif.
 */
final class AtakWebBackoffRecoveryTest extends TestCase
{
    private static function socket(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-socket.js');
    }

    /** Corps de la fonction JS nommée (accolades équilibrées). */
    private static function body(string $src, string $signature): string
    {
        $at = strpos($src, $signature);
        self::assertNotFalse($at, 'fonction introuvable : ' . $signature);
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
        $js = self::socket();

        self::assertStringContainsString('OUTAGE_BACKOFF_LADDER_SEC = [10, 20, 30, 45]', $js);
        self::assertStringContainsString('SEND_BACKOFF_LADDER_SEC = [45, 75, 150, 300, 600]', $js);
        self::assertStringContainsString('return throttled ? SEND_BACKOFF_LADDER_SEC : OUTAGE_BACKOFF_LADDER_SEC;', $js);
        self::assertStringContainsString('if (res.status === 429) throttled = true;', $js);

        $fail = self::body($js, 'function noteUnavailable(retrySec, kind)');
        self::assertStringContainsString("if (kind === 'forbidden' || kind === 'throttled') throttled = true;", $fail);
        self::assertStringContainsString('if (backoffStep < currentLadder().length) backoffStep += 1;', $fail);
        self::assertStringContainsString('applyPause(sec, jitterSec);', $fail);
        self::assertStringContainsString('jitterSec = asked ? Math.min(15, sec / 5) : Math.min(15, Math.max(3, sec / 2));', $fail);
    }

    public function testFirstSuccessEndsAnOutagePause(): void
    {
        $ok = self::body(self::socket(), 'function noteSendSuccess()');

        self::assertStringContainsString('if (throttled && okStreak < SEND_OK_STREAK_TO_STEP_DOWN)', $ok);
        self::assertStringContainsString('backoffStep = throttled ? backoffStep - 1 : 0;', $ok);
        self::assertStringNotContainsString('backoffStep -= 1;', $ok);
        self::assertStringContainsString('throttled = false;', $ok);
        self::assertStringContainsString('pauseUntil = 0;', $ok);
    }

    public function testOutagePausesAreJitteredAndThrottlesKeepExactDelays(): void
    {
        $pause = self::body(self::socket(), 'function applyPause(sec, jitterMaxSec)');

        // Jamais plus courte que demandé ; gigue seulement hors frein (429, 403 : délai exact, comme avant).
        self::assertStringContainsString('pauseUntil = Date.now() + n * 1000 + Math.floor(Math.random() * (jitterSec * 1000 + 1));', $pause);
        self::assertStringContainsString('var jitterSec = throttled ? 0 : jitterMaxSec == null ? n * 0.2 : Math.max(0, Number(jitterMaxSec) || 0);', $pause);
    }
}
