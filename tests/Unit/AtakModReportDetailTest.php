<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakModReportDetail;
use PHPUnit\Framework\TestCase;

/** Détail technique des rapports du mod : guillemets SQF, JSON collé, accents cassés. */
final class AtakModReportDetailTest extends TestCase
{
    public function testSqfArrayWithAthenaJsonIsDecoded(): void
    {
        $q = static fn (int $n): string => str_repeat('"', $n);
        $raw = '[' . $q(2) . '[' . $q(3) . 'ERROR' . $q(3) . ',' . $q(3) . 'HTTP 503: {'
            . $q(8) . 'ok' . $q(8) . ':false,' . $q(8) . 'message' . $q(8) . ':' . $q(8)
            . "Le poste est momentan\u{FFFD}ment indisponible. R\u{FFFD}essayez dans un instant." . $q(8) . ','
            . $q(8) . 'request_id' . $q(8) . ':' . $q(8) . 'fd4f7defd49b1774' . $q(8) . '}' . $q(3) . ']' . $q(2) . ',0,0]';

        $p = AtakModReportDetail::parse($raw);
        $facts = array_column($p['facts'], 'value', 'label');

        self::assertSame('ERROR', $facts['Niveau']);
        self::assertSame('503', $facts['Code HTTP']);
        self::assertSame('Le poste est momentanément indisponible. Réessayez dans un instant.', $facts['Message du serveur']);
        self::assertSame('fd4f7defd49b1774', $facts['Identifiant de requête']);
        self::assertSame('Niveau', $p['facts'][0]['label']);
        self::assertStringContainsString('"request_id": "fd4f7defd49b1774"', (string) $p['json']);
        self::assertSame('', $p['text']);
        self::assertTrue($p['repaired']);
    }

    public function testPipeHttpFailure(): void
    {
        $p = AtakModReportDetail::parse('""400|/public/api/atak/telemetry/batch|0""');
        $facts = array_column($p['facts'], 'value', 'label');

        self::assertSame('400', $facts['Code HTTP']);
        self::assertSame('/public/api/atak/telemetry/batch', $facts['Adresse appelée']);
        self::assertSame('0 s', $facts['Il y a']);
        self::assertNull($p['json']);
    }

    public function testDiagnosticLineAndLatin1Bytes(): void
    {
        $p = AtakModReportDetail::parse("unauthorized\nDiagnostic Athena : session jeu " . "\xE9" . 'chue.');

        self::assertSame([['label' => 'Diagnostic Athena', 'value' => 'session jeu échue.']], $p['facts']);
        self::assertSame('unauthorized', $p['text']);
    }

    public function testPlainTextStaysAsIs(): void
    {
        $p = AtakModReportDetail::parse('Photo introuvable, fichier supprimé');

        self::assertFalse($p['readable']);
        self::assertSame('Photo introuvable, fichier supprimé', $p['raw']);
        self::assertSame('Photo introuvable, fichier supprimé', AtakModReportDetail::summary('Photo introuvable, fichier supprimé'));
    }
}
