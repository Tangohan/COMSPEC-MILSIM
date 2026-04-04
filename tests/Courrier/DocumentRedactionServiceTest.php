<?php

declare(strict_types=1);

namespace Tests\Courrier;

use App\Services\Courrier\DocumentRedactionService;
use PHPUnit\Framework\TestCase;

final class DocumentRedactionServiceTest extends TestCase
{
    public function testIrreversibleRemovesSecretText(): void
    {
        $s = new DocumentRedactionService();
        $in = '<p>Public [[REDACT]]secret text[[/REDACT]] fin</p>';
        $out = $s->applyIrreversibleForExport($in);
        $this->assertStringNotContainsString('secret', $out);
        $this->assertStringContainsString('courrier-redact-block', $out);
        $this->assertStringContainsString('Public', $out);
    }

    public function testVisualKeepsTextInSpan(): void
    {
        $s = new DocumentRedactionService();
        $in = '[[REDACT]]X[[/REDACT]]';
        $out = $s->applyVisualMarkers($in);
        $this->assertStringContainsString('X', $out);
        $this->assertStringContainsString('courrier-redact-visual', $out);
    }
}
