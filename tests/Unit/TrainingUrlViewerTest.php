<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlContentSanitizer;
use App\Support\TrainingFormationCustomPageRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Visionneuse d’adresses web dans les formations (leçons, annexes, Pages HTML).
 */
final class TrainingUrlViewerTest extends TestCase
{
    public function testGoogleAndDiagramLinksBecomeEmbeddableViewers(): void
    {
        self::assertSame(
            'https://docs.google.com/presentation/d/17pn1iQ8cGFQ/preview#slide=id.g3c697c0379b_0_0',
            training_url_viewer_embed_src('https://docs.google.com/presentation/d/17pn1iQ8cGFQ/edit?slide=id.g3c697c0379b_0_0#slide=id.g3c697c0379b_0_0')
        );
        self::assertSame(
            'https://docs.google.com/spreadsheets/d/abc123/preview?gid=42',
            training_url_viewer_embed_src('https://docs.google.com/spreadsheets/d/abc123/edit#gid=42')
        );
        self::assertSame('https://docs.google.com/document/d/XYZ/preview', training_url_viewer_embed_src('https://docs.google.com/document/d/XYZ/edit?usp=sharing'));
        self::assertSame('https://docs.google.com/drawings/d/DRW/preview', training_url_viewer_embed_src('https://docs.google.com/drawings/d/DRW/edit'));
        self::assertSame('https://drive.google.com/file/d/FID/preview', training_url_viewer_embed_src('https://drive.google.com/file/d/FID/view'));
        self::assertSame('https://viewer.diagrams.net/?nav=1&lightbox=1#G1abc', training_url_viewer_embed_src('https://app.diagrams.net/#G1abc'));
        self::assertSame('https://example.com/doc.pdf', training_url_viewer_embed_src('https://example.com/doc.pdf'));
        self::assertNull(training_url_viewer_embed_src('https://example.com/page'));
        self::assertNull(training_url_viewer_embed_src('javascript:alert(1)'));
        self::assertSame('Google Sheets', training_url_viewer_provider_label('https://docs.google.com/spreadsheets/d/abc/edit'));
    }

    public function testBareUrlContentIsDetected(): void
    {
        self::assertSame('https://docs.google.com/x/y?a=1&b=2', training_url_from_bare_content('<p>https://docs.google.com/x/y?a=1&amp;b=2</p>'));
        self::assertNull(training_url_from_bare_content('Voir https://docs.google.com/x'));
        self::assertNull(training_url_from_bare_content('<p>Texte</p>'));
    }

    public function testCustomPageEmbedSurvivesSanitizerAndRendersViewer(): void
    {
        $stored = HtmlContentSanitizer::sanitize(
            TrainingFormationCustomPageRenderer::embedSnippet('https://docs.google.com/document/d/XYZ/edit?a=1&b=2') . '<p>Texte</p>'
        );
        self::assertStringContainsString('athena-embed__link', $stored);
        self::assertStringNotContainsString('<iframe', $stored);

        $html = TrainingFormationCustomPageRenderer::expandEmbeds($stored);
        self::assertStringContainsString('<iframe src="https://docs.google.com/document/d/XYZ/preview"', $html);
        self::assertStringContainsString('target="_blank"', $html);
        self::assertSame('<p>sans lien</p>', TrainingFormationCustomPageRenderer::expandEmbeds('<p>sans lien</p>'));
    }

    public function testLessonViewShowsExternalUrlAndAnnexes(): void
    {
        $root = dirname(__DIR__, 2);
        $lesson = (string) file_get_contents($root . '/views/training/lesson.php');
        $footer = (string) file_get_contents($root . '/views/training/partials/lms_lesson_common_footer.php');
        self::assertStringContainsString('$lessonExtraViewerUrl', $lesson);
        self::assertStringContainsString('$lessonBareContentUrl', $lesson);
        self::assertStringContainsString('partials/url_viewer.php', $lesson);
        self::assertStringContainsString('Annexes du formateur', $footer);
        self::assertStringContainsString('partials/url_viewer.php', $footer);
    }
}
