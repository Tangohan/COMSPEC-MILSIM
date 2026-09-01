<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Support\DocumentManuscript;
use PHPUnit\Framework\TestCase;

final class DocumentManuscriptTest extends TestCase
{
    public function testAuthoredOriginAndCoverFieldsSurviveRoundTrip(): void
    {
        $_POST = [
            'manuscript_codes' => "FM 3-05.211\nMCWP 3-15.6\n",
            'manuscript_issue_date' => 'APRIL 2005',
            'manuscript_issuing_authority' => 'Headquarters, Department of the Army',
            'manuscript_distribution' => 'Distribution authorized to U.S. Government agencies only.',
            'manuscript_destruction' => 'Destroy by any method that will prevent reconstruction.',
            'manuscript_foreword' => 'This publication has been prepared under the direction of the commands listed below.',
            'manuscript_sig_name' => ['James W. Parker', 'Ronald E. Keys'],
            'manuscript_sig_rank' => ['Major General, USA', 'Lieutenant General, USAF'],
            'manuscript_sig_command' => ['USAJFKSWCS', 'Headquarters USAF'],
            'manuscript_body' => "Chapter 1\n\nFree-fall operations require a complete briefing.",
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $payload = DocumentManuscript::fromRequest($request, 'Special Forces Military Free-Fall Operations', 'Headquarters');
        $encoded = DocumentManuscript::encode($payload);
        $view = DocumentManuscript::forView([
            'origin' => DocumentManuscript::ORIGIN_AUTHORED,
            'authored_json' => $encoded,
            'title' => 'Special Forces Military Free-Fall Operations',
        ]);

        self::assertTrue(DocumentManuscript::isAuthored(['origin' => 'authored']));
        self::assertSame(['FM 3-05.211', 'MCWP 3-15.6'], $view['publication_codes']);
        self::assertSame('APRIL 2005', $view['issue_date']);
        self::assertCount(2, DocumentManuscript::filledSignatures($view));
        self::assertStringContainsString('Free-fall operations', $view['body']);
        self::assertStringContainsString('<p>', $view['body']);
        unset($_POST);
    }

    public function testSanitizeStripsScripts(): void
    {
        $html = DocumentManuscript::sanitizeHtml('<p>OK</p><script>alert(1)</script>');
        self::assertStringContainsString('<p>OK</p>', $html);
        self::assertStringNotContainsString('script', strtolower($html));
    }
}
