<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OrbatMaskMode;
use App\Support\VisibilityLevel;
use PHPUnit\Framework\TestCase;

final class VisibilityLevelTest extends TestCase
{
    public function testNormalizeAliasesAndFallback(): void
    {
        self::assertSame(VisibilityLevel::NORMAL, VisibilityLevel::normalize('normale'));
        self::assertSame(VisibilityLevel::NORMAL, VisibilityLevel::normalize('visible'));
        self::assertSame(VisibilityLevel::ANONYMIZED, VisibilityLevel::normalize('anonymisé'));
        self::assertSame(VisibilityLevel::ANONYMIZED, VisibilityLevel::normalize('anonymize'));
        self::assertSame(VisibilityLevel::RESTRICTED, VisibilityLevel::normalize('restreinte'));
        self::assertSame(VisibilityLevel::RESTRICTED, VisibilityLevel::normalize('scope_section'));
        self::assertSame(VisibilityLevel::HIDDEN, VisibilityLevel::normalize('masquée'));
        self::assertSame(VisibilityLevel::HIDDEN, VisibilityLevel::normalize('hidden_all'));
        self::assertSame(VisibilityLevel::NORMAL, VisibilityLevel::normalize('inconnu'));
        self::assertSame(VisibilityLevel::NORMAL, VisibilityLevel::normalize(null));
    }

    public function testMostRestrictiveKeepsSecretLevel(): void
    {
        self::assertSame(
            VisibilityLevel::HIDDEN,
            VisibilityLevel::mostRestrictive(VisibilityLevel::ANONYMIZED, VisibilityLevel::HIDDEN)
        );
        self::assertSame(
            VisibilityLevel::RESTRICTED,
            VisibilityLevel::mostRestrictive(VisibilityLevel::NORMAL, VisibilityLevel::RESTRICTED)
        );
        self::assertSame(
            VisibilityLevel::ANONYMIZED,
            VisibilityLevel::mostRestrictive(VisibilityLevel::ANONYMIZED, VisibilityLevel::NORMAL)
        );
    }

    public function testFromAndToOrbatMaskModeRoundTrip(): void
    {
        self::assertSame(VisibilityLevel::HIDDEN, VisibilityLevel::fromOrbatMaskMode(OrbatMaskMode::HIDDEN_ALL));
        self::assertSame(VisibilityLevel::ANONYMIZED, VisibilityLevel::fromOrbatMaskMode(OrbatMaskMode::ANONYMIZE));
        self::assertSame(VisibilityLevel::RESTRICTED, VisibilityLevel::fromOrbatMaskMode(OrbatMaskMode::SCOPE_TEAM));
        self::assertSame(VisibilityLevel::NORMAL, VisibilityLevel::fromOrbatMaskMode(OrbatMaskMode::NONE));

        self::assertSame(OrbatMaskMode::HIDDEN_ALL, VisibilityLevel::toOrbatMaskMode(VisibilityLevel::HIDDEN));
        self::assertSame(OrbatMaskMode::ANONYMIZE, VisibilityLevel::toOrbatMaskMode(VisibilityLevel::ANONYMIZED));
        self::assertSame(OrbatMaskMode::SCOPE_SECTION, VisibilityLevel::toOrbatMaskMode(VisibilityLevel::RESTRICTED));
        self::assertSame(OrbatMaskMode::NONE, VisibilityLevel::toOrbatMaskMode(VisibilityLevel::NORMAL));
    }

    public function testLabelsAndConsequences(): void
    {
        self::assertSame('Masquée', VisibilityLevel::label(VisibilityLevel::HIDDEN));
        self::assertStringContainsString('absent', VisibilityLevel::consequence(VisibilityLevel::HIDDEN, 'personnel'));
        self::assertStringContainsString('Unité restreinte', VisibilityLevel::consequence(VisibilityLevel::ANONYMIZED, 'unit'));
    }
}
