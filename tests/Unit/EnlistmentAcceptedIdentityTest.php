<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EnlistmentAcceptedIdentity;
use PHPUnit\Framework\TestCase;

final class EnlistmentAcceptedIdentityTest extends TestCase
{
    public function testFormDisplayNameIgnoresPlaceholders(): void
    {
        self::assertSame('', EnlistmentAcceptedIdentity::formDisplayName([
            'first_name' => '—',
            'last_name' => '—',
        ]));
        self::assertSame('Jean Dupont', EnlistmentAcceptedIdentity::formDisplayName([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]));
    }

    public function testDisplayNameStripsReviewerNickWhenFormHasCandidateName(): void
    {
        $next = EnlistmentAcceptedIdentity::displayNameForNewMembership(
            'MORPHIDE',
            'Jean Dupont',
            ['MORPHIDE'],
            false
        );
        self::assertSame('Jean Dupont', $next);
    }

    public function testDisplayNameClearsReviewerNickWhenFormIsEmpty(): void
    {
        $next = EnlistmentAcceptedIdentity::displayNameForNewMembership(
            'MORPHIDE',
            '',
            ['MORPHIDE'],
            false
        );
        self::assertSame('', $next);
    }

    public function testDisplayNameKeepsCandidateNickWhenDifferentFromReviewer(): void
    {
        $next = EnlistmentAcceptedIdentity::displayNameForNewMembership(
            'Noopy',
            'Jean Dupont',
            ['MORPHIDE'],
            false
        );
        self::assertNull($next);
    }

    public function testDoesNotStripWhenMemberIsTheReviewer(): void
    {
        $next = EnlistmentAcceptedIdentity::displayNameForNewMembership(
            'MORPHIDE',
            'Autre',
            ['MORPHIDE'],
            true
        );
        self::assertNull($next);
        self::assertFalse(EnlistmentAcceptedIdentity::shouldClearCharacterName('MORPHIDE', ['MORPHIDE'], true));
    }

    public function testClearsCharacterNameCopiedFromReviewer(): void
    {
        self::assertTrue(EnlistmentAcceptedIdentity::shouldClearCharacterName('MORPHIDE', ['MORPHIDE'], false));
        self::assertTrue(EnlistmentAcceptedIdentity::shouldClearCharacterName('morphide', ['MORPHIDE'], false));
        self::assertFalse(EnlistmentAcceptedIdentity::shouldClearCharacterName('Jake', ['MORPHIDE'], false));
    }
}
