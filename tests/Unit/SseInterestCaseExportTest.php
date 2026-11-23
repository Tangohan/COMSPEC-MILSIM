<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseInterestCaseHistoryRepository;
use App\Services\Sse\SseInterestCasePdfService;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseInterestFixture;

final class SseInterestCaseExportTest extends TestCase
{
    public function testBinaryExportIncludesPhotoAndPaginatedGraph(): void
    {
        if (!extension_loaded('gd') || !\App\Support\TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            self::markTestSkipped('GD et TCPDF requis pour le contrôle du binaire PDF');
        }
        $fixture = new SseInterestFixture();
        $path = null;
        try {
            $id = $fixture->open(10);
            $detail = $fixture->service->exportDetail($fixture->actor(10), $id);
            $root = 'interest:' . $id;
            $detail['graph'] = ['root' => $root, 'nodes' => [$root => ['title' => 'Dossier', 'restricted' => false]], 'edges' => []];
            for ($i = 1; $i <= 9; ++$i) {
                $detail['graph']['nodes']['note:' . $i] = ['title' => 'Fiche numéro ' . $i, 'restricted' => false];
                $detail['graph']['edges'][] = ['from' => $root, 'to' => 'note:' . $i];
            }
            $dir = base_path('public/uploads/sse');
            if (!is_dir($dir)) { mkdir($dir, 0775, true); }
            $name = 'export-test-' . bin2hex(random_bytes(6)) . '.jpg';
            $path = $dir . '/' . $name;
            $image = imagecreatetruecolor(40, 60);
            imagefill($image, 0, 0, imagecolorallocate($image, 30, 70, 90));
            imagejpeg($image, $path);
            imagedestroy($image);
            $detail['media'] = [['path' => 'uploads/sse/' . $name, 'caption' => 'Image de contrôle du test', 'subject' => 'Sujet d’essai']];
            $pdf = \App\Support\TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(
                static fn (): string => (new SseInterestCasePdfService())->binary($detail, 'Bureau d’essai')
            );
            self::assertStringStartsWith('%PDF', $pdf);
            self::assertGreaterThanOrEqual(1, preg_match_all('~/Subtype\s*/Image\b~', $pdf));
            self::assertGreaterThanOrEqual(4, preg_match_all('~/Type\s*/Page\b~', $pdf));
        } finally {
            if ($path !== null && is_file($path)) { unlink($path); }
            $fixture->close();
        }
    }

    public function testExportIncludesAllHistoryDiscussionAnalysisAndDraftText(): void
    {
        $fixture = new SseInterestFixture();
        try {
            $id = $fixture->open(10);
            $history = new SseInterestCaseHistoryRepository();
            for ($i = 0; $i < 510; ++$i) {
                $history->add(1, $id, ['action' => 'update', 'reason' => 'Événement ' . $i]);
            }
            for ($i = 0; $i < 110; ++$i) {
                $fixture->repo->addUpdate(1, $id, 'Discussion ' . $i, 'Analyste', 10);
            }
            $detail = $fixture->service->exportDetail($fixture->actor(10), $id);
            self::assertGreaterThanOrEqual(510, count($detail['history']));
            self::assertCount(110, $detail['updates']);
            $detail['case']['analysis_facts'] = 'Fait unique à conserver';
            $detail['case']['analysis_questions'] = 'Question encore ouverte';
            $detail['drafts'] = [['title' => 'Rédaction en cours', 'model' => 'vigilance', 'subject_label' => 'Sujet A', 'updated_at' => '2026-11-17 12:00:00', 'texts' => ['notes' => '<script>Jamais du HTML</script>']]];
            $detail['graph'] = ['nodes' => ['interest:' . $id => ['title' => 'Dossier racine', 'type_label' => 'Dossier', 'summary' => 'En analyse', 'restricted' => false], 'restricted:1' => ['title' => 'NOM MASQUÉ', 'restricted' => true]], 'edges' => []];
            $html = (new SseInterestCasePdfService())->html($detail);
            foreach (['Fait unique à conserver', 'Question encore ouverte', 'Rédaction en cours', 'Brouillon en cours', 'Discussion 0', 'Discussion 109', 'Événement 0', 'Événement 509'] as $text) {
                self::assertStringContainsString($text, $html);
            }
            self::assertStringNotContainsString('NOM MASQUÉ', $html);
            self::assertStringNotContainsString('<script>', $html);
        } finally {
            $fixture->close();
        }
    }
}
