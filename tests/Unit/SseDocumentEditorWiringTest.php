<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseDocumentRepository;
use PHPUnit\Framework\TestCase;

/**
 * Éditeur de documents SSE : branchements (vue, routes, script, étape de schéma) et garde-fous d'accessibilité et de sécurité.
 */
final class SseDocumentEditorWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $path);
    }

    public function testFormHasOneH1NoKickerAndAnAccessibleToolbar(): void
    {
        $view = self::read('views/atak/sse/document_form.php');
        self::assertSame(1, preg_match_all('/<h1\b/', $view), 'un seul h1 dans la vue');
        self::assertStringNotContainsString('kicker', $view);
        self::assertStringNotContainsString('eyebrow', $view);
        self::assertStringContainsString('role="toolbar"', $view);
        self::assertStringContainsString('role="textbox"', $view);
        self::assertStringContainsString('aria-multiline="true"', $view);
        self::assertStringContainsString('aria-pressed', $view);
        self::assertStringContainsString('aria-live="polite"', $view);
        self::assertStringContainsString('sse-doc-editor.js', $view);
        self::assertStringContainsString('sse-doc-editor.css', $view);
    }

    public function testEditorScriptKeepsToTheSafeRules(): void
    {
        $js = self::read('public/assets/js/sse-doc-editor.js');
        // Les raccourcis standard sont gérés.
        foreach (["k === 'z'", "k === 'y'", "k === 's'", "k === 'b'", "k === 'i'", "k === 'u'"] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        // Aucun contenu extérieur n'entre dans la feuille sans passer par la liste blanche.
        self::assertStringContainsString('DOMParser', $js);
        self::assertStringNotContainsString('eval(', $js);
        self::assertStringNotContainsString('document.write', $js);
        self::assertDoesNotMatchRegularExpression('/\bvar\s+\w+\s*=\s*\(\s*\)\s*=>/', $js, 'ES5 : pas de fonction fléchée');
        self::assertDoesNotMatchRegularExpression('/\b(let|const)\s/', $js, 'ES5 : pas de let ni const');
        self::assertStringContainsString("'sse-pagebreak'", $js);
    }

    public function testRoutesDraftsAndPdfAreRegisteredBeforeTheIdRoutes(): void
    {
        $routes = self::read('routes/web.php');
        $draft = strpos($routes, "'/atak/sse/documents/brouillon'");
        $byId = strpos($routes, "'/atak/sse/documents/{id}'");
        self::assertNotFalse($draft);
        self::assertNotFalse($byId);
        self::assertLessThan($byId, $draft, 'brouillon avant {id}');
        self::assertStringContainsString("'/atak/sse/documents/{id}/pdf'", $routes);
    }

    public function testStepNumberIsUniqueAndTheStepIsRerunnable(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertSame(1, preg_match_all("/'file' => '5030_/", $registry));
        self::assertSame(1, preg_match_all("/'id' => 'sse_document_drafts'/", $registry));
        self::assertMatchesRegularExpression("/'file' => '5030_sse_document_drafts\\.php', 'rerun' => true/", $registry);
        self::assertSame([], array_values(array_diff(
            array_map(static fn (string $m): string => $m, self::duplicates($registry)),
            []
        )));
        $sql = self::read('migrations/2026_11_24_100_sse_document_drafts.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS sse_document_drafts', $sql);
        self::assertStringContainsString('UNIQUE KEY', $sql);
        self::assertStringContainsString('tableExists', self::read('database/steps/5030_sse_document_drafts.php'));
    }

    public function testRichBodyIsSanitisedWhenRenderedOnThePaper(): void
    {
        $html = SseDocumentRepository::bodyToHtml('<!--sse-rich--><p>Bonjour<script>alert(1)</script><img src=x onerror=alert(2)></p>');
        self::assertStringContainsString('Bonjour', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('onerror', $html);
        // Un document historique (texte) garde son rendu.
        self::assertStringContainsString('sse-doc-paper__', SseDocumentRepository::bodyToHtml("NOTE D’ANALYSE\n\nTexte"));
    }

    /** @return list<string> */
    private static function duplicates(string $registry): array
    {
        preg_match_all("/'file' => '(\\d+)_/", $registry, $m);
        $counts = array_count_values($m[1]);

        return array_keys(array_filter($counts, static fn (int $n): bool => $n > 1));
    }
}
