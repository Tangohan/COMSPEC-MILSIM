<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseIdentifierGenerator as G;
use PHPUnit\Framework\TestCase;

final class SseIdentifierGeneratorTest extends TestCase
{
    public function testIdNumberAndGenericNameFormats(): void
    {
        self::assertSame('ID-2026-0042', G::idNumber(2026, 42));
        self::assertSame('ID-2026-12345', G::idNumber(2026, 12345));
        self::assertSame(['year' => 2026, 'seq' => 42], G::parseIdNumber('id-2026-0042'));
        self::assertNull(G::parseIdNumber('ID-26-1'));
        self::assertSame('Fiche personne ID-2026-0042', G::genericName('person', 'ID-2026-0042'));
    }

    public function testDseFormatAlphabetAndLengths(): void
    {
        foreach ([5, 6, 7, 8, 2, 20] as $wanted) {
            for ($i = 0; $i < 200; $i++) {
                $dse = G::dse($wanted);
                self::assertTrue(G::isValidDse($dse), $dse);
                self::assertMatchesRegularExpression('/^DSE-[A-HJ-NP-Z2-9]{5,8}$/', $dse);
                self::assertStringNotContainsString('0', $dse);
                self::assertStringNotContainsString('1', $dse);
                self::assertStringNotContainsString('O', substr($dse, 4));
                self::assertStringNotContainsString('I', substr($dse, 4));
            }
        }
        self::assertSame(10, strlen(G::dse(6)));
        self::assertSame(12, strlen(G::dse(99)));
    }

    public function testDseHasNoCollisionAmongManyDraws(): void
    {
        $seen = [];
        for ($i = 0; $i < 5000; $i++) {
            $seen[G::dse(8)] = true;
        }
        self::assertCount(5000, $seen);
    }

    public function testNormalizeDseAcceptsCasePrefixAndSpacesButRejectsAmbiguousCharacters(): void
    {
        self::assertSame('DSE-K7M4QX', G::normalizeDse(' dse-k7m4qx '));
        self::assertSame('DSE-K7M4QX', G::normalizeDse('K7M4QX'));
        self::assertSame('DSE-K7M4QX', G::normalizeDse('DSE K7M4QX'));
        self::assertNull(G::normalizeDse('DSE-K7M40X'));
        self::assertNull(G::normalizeDse('DSE-K7M41X'));
        self::assertNull(G::normalizeDse('DSE-KIM4QX'));
        self::assertNull(G::normalizeDse('DSE-ABCD'));
        self::assertNull(G::normalizeDse('DSE-ABCDEFGHJ'));
        self::assertNull(G::normalizeDse("DSE-AB'; DROP"));
    }

    public function testCodenameFormatAndGrowth(): void
    {
        for ($i = 0; $i < 300; $i++) {
            self::assertMatchesRegularExpression('/^[A-Z]+-[1-9][0-9]$/', G::codename(0));
        }
        self::assertMatchesRegularExpression('/^[A-Z]+-[1-9][0-9]{2}$/', G::codename(60));
        self::assertMatchesRegularExpression('/^[A-Z]+-[1-9][0-9]{3}$/', G::codename(120));
        self::assertMatchesRegularExpression('/^[A-Z]+-[1-9][0-9]{3}$/', G::codename(9999));
    }

    public function testWordListIsCleanUniqueAndRadioFriendly(): void
    {
        self::assertSame(count(G::WORDS), count(array_unique(G::WORDS)));
        foreach (G::WORDS as $word) {
            self::assertMatchesRegularExpression('/^[A-Z]{3,12}$/', $word);
            self::assertFalse(G::containsForbidden($word), $word);
            self::assertNotNull(G::normalizeCodename($word . '-12'), $word);
        }
    }

    public function testForbiddenWordsAreRefusedInManualCodenames(): void
    {
        self::assertNull(G::normalizeCodename('NAZI-1'));
        self::assertNull(G::normalizeCodename('mort subite'));
        self::assertNull(G::normalizeCodename('X'));
        self::assertNull(G::normalizeCodename("A'B"));
        self::assertSame('FAUCON-12', G::normalizeCodename(' faucon-12 '));
        self::assertSame('CONDOR-7', G::normalizeCodename('condor-7'));
        self::assertSame('ETE-SUD', G::normalizeCodename('été-sud'));
    }
}
