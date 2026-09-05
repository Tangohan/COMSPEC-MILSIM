<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MemberEmailStaffNotificationPrivacyTest extends TestCase
{
    /**
     * @dataProvider memberRequestTemplateProvider
     */
    public function testMemberRequestEmailsDoNotExposeRequesterAddress(string $template): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/views/emails/' . $template . '.php');

        self::assertStringNotContainsString('$requesterEmail', $source);
        self::assertStringNotContainsString('mailto:', $source);
    }

    /** @return iterable<string, array{string}> */
    public static function memberRequestTemplateProvider(): iterable
    {
        yield 'effectifs elevation' => ['effectifs_elevation_request'];
        yield 'training elevation' => ['training_publish_elevation_request'];
        yield 'personnel correction' => ['personnel_correction_request_staff'];
    }

    public function testMemberAddressIsNotUsedAsReplyToForStaffRequests(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/EmailService.php');

        foreach (['effectifs_elevation', 'training_publish_elevation', 'personnel_correction_staff'] as $purpose) {
            self::assertMatchesRegularExpression(
                '/null,\s*\[\'purpose\' => \'' . preg_quote($purpose, '/') . '\'/s',
                $source
            );
        }
    }
}
