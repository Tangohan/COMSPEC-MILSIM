<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WritingAssistantAssetTest extends TestCase
{
    public function testWritingAssistantIsReusedWithoutTechnicalEmptyCopy(): void
    {
        $partial = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/writing_assistant.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/courrier-editor.js');
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Courrier/CourrierSnippetService.php');
        $doctrine = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/doctrine.php');
        $courrier = (string) file_get_contents(dirname(__DIR__, 2) . '/views/courrier/editor.php');

        self::assertStringContainsString('Assistant rédactionnel', $partial);
        self::assertStringContainsString('data-writing-assistant', $partial);
        self::assertStringContainsString('writing_assistant.php', $doctrine);
        self::assertStringContainsString('doctrine-content-markdown', $doctrine);
        self::assertStringContainsString('writing_assistant.php', $courrier);
        self::assertStringContainsString('defaultSnippets', $service);
        self::assertStringContainsString('Objet de la directive', $service);
        self::assertStringContainsString('Aucune formule n’est proposée pour le moment', $js);
        self::assertStringNotContainsString('snippets.json', $js);
        self::assertStringNotContainsString('API snippets', $js);
    }
}
