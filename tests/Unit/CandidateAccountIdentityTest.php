<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Recruitment\CandidateAccountIdentity;
use App\Support\ColorContrast;
use PHPUnit\Framework\TestCase;

/** Identité tirée du compte, manques du profil, et contraste des couleurs de marque. Données fictives. */
final class CandidateAccountIdentityTest extends TestCase
{
    private const NOW = '2026-06-15 12:00:00';

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    public function testCompleteProfileAsksForNothing(): void
    {
        $id = CandidateAccountIdentity::fromAccount(
            ['email' => 'a@example.invalid', 'display_name' => 'Pseudo'],
            ['first_name' => 'Alex', 'last_name' => 'Test', 'birth_date' => '2000-06-15', 'nationality' => 'Française'],
            false,
            $this->now()
        );

        self::assertSame([], $id['missing']);
        self::assertSame('Alex Test', $id['display_name']);
        self::assertSame(26, $id['age']);
        self::assertSame('a@example.invalid', $id['email']);
    }

    public function testMissingFieldsAreListedAndTheNameIsSuggestedFromTheDisplayName(): void
    {
        $id = CandidateAccountIdentity::fromAccount(['email' => 'a@example.invalid', 'display_name' => 'Alex Test'], null, true, $this->now());

        self::assertSame(['first_name', 'last_name', 'birth_date', 'nationality', 'discord_handle'], $id['missing']);
        self::assertSame('Alex', $id['suggest_first_name']);
        self::assertSame('Test', $id['suggest_last_name']);
        self::assertSame('Alex Test', $id['display_name']);
        self::assertNull($id['age']);
    }

    public function testDiscordHandleIsOnlyAskedInDiscordMode(): void
    {
        $id = CandidateAccountIdentity::fromAccount(['email' => 'a@example.invalid'], ['first_name' => 'A', 'last_name' => 'B', 'birth_date' => '2000-01-01', 'nationality' => 'X'], false, $this->now());

        self::assertSame([], $id['missing']);
    }

    public function testCompleteIgnoresInputForFieldsTheProfileAlreadyKnows(): void
    {
        $id = CandidateAccountIdentity::fromAccount(['email' => 'a@example.invalid'], ['first_name' => 'Alex', 'last_name' => 'Test', 'birth_date' => '', 'nationality' => 'Française'], false, $this->now());

        $done = CandidateAccountIdentity::complete($id, [
            'profile_first_name' => 'Forgé',
            'profile_nationality' => 'Forgée',
            'profile_birth_date' => '1999-12-31',
        ], $this->now());

        self::assertNull($done['error']);
        self::assertSame('Alex', $done['identity']['first_name']);
        self::assertSame('Française', $done['identity']['nationality']);
        self::assertSame(['birth_date' => '1999-12-31'], $done['profile_updates']);
        self::assertSame(26, $done['identity']['age']);
    }

    public function testMissingNameIsRequiredAndBadDatesAreRefused(): void
    {
        $id = CandidateAccountIdentity::fromAccount(['email' => 'a@example.invalid'], null, false, $this->now());

        self::assertNotNull(CandidateAccountIdentity::complete($id, [], $this->now())['error']);
        self::assertNotNull(CandidateAccountIdentity::complete($id, ['profile_first_name' => 'A', 'profile_last_name' => 'B', 'profile_birth_date' => '2999-01-01'], $this->now())['error']);
        self::assertNotNull(CandidateAccountIdentity::complete($id, ['profile_first_name' => 'A', 'profile_last_name' => 'B', 'profile_birth_date' => 'pas-une-date'], $this->now())['error']);

        $ok = CandidateAccountIdentity::complete($id, ['profile_first_name' => ' A ', 'profile_last_name' => 'B'], $this->now());
        self::assertNull($ok['error']);
        self::assertSame(['first_name' => 'A', 'last_name' => 'B'], $ok['profile_updates']);
    }

    public function testBrandColorsAreLightenedUntilReadableOnTheDarkBackground(): void
    {
        $bg = '#0b1220';
        self::assertLessThan(ColorContrast::MIN_TEXT_RATIO, ColorContrast::ratio('#1e3a8a', $bg));

        $readable = ColorContrast::readableOn('#1e3a8a', $bg, '#6ee7b7');

        self::assertGreaterThanOrEqual(ColorContrast::MIN_TEXT_RATIO, ColorContrast::ratio($readable, $bg));
        self::assertSame('#6ee7b7', ColorContrast::readableOn('pas-une-couleur', $bg, '#6ee7b7'));
        self::assertSame('#6ee7b7', ColorContrast::readableOn('#6ee7b7', $bg, '#ffffff'));
    }

    public function testStylesheetTextColorsMeetTheContrastThreshold(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/community-enlistment.css');
        $bg = '#0b1220';
        foreach (['#c5d0de', '#9aa8ba', '#e8eef7', '#6ee7b7'] as $text) {
            self::assertGreaterThanOrEqual(ColorContrast::MIN_TEXT_RATIO, ColorContrast::ratio($text, $bg), $text);
        }
        // Sur-titre et numéros de l'écran « Avant de candidater » : couleur de texte lisible, pas l'accent brut.
        self::assertMatchesRegularExpression('/\.ce-kicker\s*\{[^}]*color:\s*var\(--ce-accent-text\)/', $css);
        self::assertMatchesRegularExpression('/\.ce-att-card__n\s*\{[^}]*color:\s*var\(--ce-accent-text\)/', $css);
    }
}
