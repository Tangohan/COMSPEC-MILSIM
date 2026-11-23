<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Câblage de l'éditeur de documents SSE (thèmes, verrous, références, signature, images) : contrôles lus dans les sources, sans base.
 */
final class SseDocumentProWiringTest extends TestCase
{
    private static function src(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    private static function method(string $src, string $name): string
    {
        $start = strpos($src, 'function ' . $name . '(');
        self::assertNotFalse($start, $name);
        $next = strpos($src, "\n    public function ", (int) $start + 10);
        $next2 = strpos($src, "\n    private function ", (int) $start + 10);
        $end = min($next === false ? PHP_INT_MAX : $next, $next2 === false ? PHP_INT_MAX : $next2);

        return substr($src, (int) $start, $end === PHP_INT_MAX ? null : $end - (int) $start);
    }

    public function testSaveEnforcesLockedTextOnTheServer(): void
    {
        $c = self::src('app/Controllers/Web/SsePortalController.php');
        foreach (['documentStore', 'documentUpdate'] as $m) {
            $body = self::method($c, $m);
            self::assertStringContainsString('SseDocumentLocks::violations(', $body, $m);
            self::assertStringContainsString('canUnlock()', $body, $m);
        }
        self::assertMatchesRegularExpression('~function canUnlock\(\): bool\s*\{\s*return \$this->canManage\(\) && \$this->canGrant\(\);~', $c);
        self::assertStringContainsString('SseDocumentTemplates::body($modelCode)', self::method($c, 'documentStore'), 'comparaison avec le modèle choisi');
    }

    public function testSignatureUsesTheSessionUserAndNeverAFormUserId(): void
    {
        $body = self::method(self::src('app/Controllers/Web/SsePortalController.php'), 'documentSign');
        self::assertStringContainsString("Session::get('user_id')", $body);
        self::assertDoesNotMatchRegularExpression("~input\\('(user_id|signer|signer_label|user)'~", $body);
        self::assertStringContainsString('Csrf::validate', $body);
        self::assertStringContainsString('canManage()', $body);
    }

    public function testSignedDocumentIsReadOnly(): void
    {
        $c = self::src('app/Controllers/Web/SsePortalController.php');
        foreach (['documentEditForm', 'documentUpdate'] as $m) {
            self::assertStringContainsString('SseDocumentSignatureService())->isSigned(', self::method($c, $m), $m);
        }
    }

    public function testRoutesAreBehindThePortalMiddlewareAndMediaRoutesComeBeforeTheIdRoute(): void
    {
        $r = self::src('routes/web.php');
        foreach (['documentMediaUpload', 'documentMediaImage', 'documentSign', 'documentSignatureImage'] as $m) {
            self::assertMatchesRegularExpression('~SsePortalController::class, \'' . $m . '\'\], \$mwSsePortal\)~', $r, $m);
        }
        self::assertLessThan(strpos($r, "'/atak/sse/documents/{id}', [SsePortalController::class, 'documentShow']"), strpos($r, "'/atak/sse/documents/medias/{token}'"));
    }

    public function testStep5070IsUniqueSafeAndRerunnable(): void
    {
        $registry = self::src('database/steps/registry.php');
        self::assertSame(1, substr_count($registry, "'file' => '5070_"));
        self::assertMatchesRegularExpression("~'id' => 'sse_document_pro'.*'file' => '5070_sse_document_pro\\.php', 'rerun' => true\\]~", $registry);
        self::assertDoesNotMatchRegularExpression('~require~i', '5070_sse_document_pro.php');
        $sql = self::src('migrations/2026_11_26_100_sse_document_pro.sql');
        self::assertStringContainsString('DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $sql);
        self::assertSame(2, substr_count($sql, 'CREATE TABLE IF NOT EXISTS'));
        self::assertDoesNotMatchRegularExpression('~COMMENT\s+\'[^\']*;~', $sql, 'pas de point-virgule dans un commentaire de colonne');
        $step = self::src('database/steps/5070_sse_document_pro.php');
        self::assertStringContainsString('addColumnIfMissing(\'sse_documents\', \'sheet_theme\'', $step);
    }

    public function testPdfAndPaperShowTheSignaturesAndTheThemeIsStoredWithTheDocument(): void
    {
        self::assertStringContainsString('writeSignatures(', self::src('app/Services/Sse/SseDocumentPdfService.php'));
        self::assertStringContainsString('SseThemedTcpdf::themed(', self::src('app/Services/Sse/SseDocumentPdfService.php'));
        self::assertStringContainsString("'sheet_theme'", self::src('app/Repositories/SseDocumentRepository.php'));
        self::assertStringContainsString('SseSheetThemes::cssClass(', self::src('views/atak/sse/partials/document_paper.php'));
        $form = self::src('views/atak/sse/document_form.php');
        self::assertSame(1, substr_count($form, '<h1'), 'un seul h1');
        self::assertStringContainsString('name="sheet_theme"', $form);
    }

    public function testNoKickerEyebrowClassInTheNewMarkup(): void
    {
        foreach (['views/atak/sse/document_form.php', 'views/atak/sse/partials/document_paper.php', 'public/assets/css/sse-doc-themes.css'] as $f) {
            $own = preg_replace('~sse-desk-hero__kicker~', '', self::src($f)) ?? '';
            self::assertDoesNotMatchRegularExpression('~(kicker|eyebrow|overline|surtitre)~i', $own, $f);
        }
    }
}
