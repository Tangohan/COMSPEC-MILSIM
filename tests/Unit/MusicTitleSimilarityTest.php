<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MusicTitleSimilarity;
use PHPUnit\Framework\TestCase;

final class MusicTitleSimilarityTest extends TestCase
{
    public function testDetectsUsualDuplicateForms(): void
    {
        self::assertTrue(MusicTitleSimilarity::isSimilar('Fortunate Son', 'fortunate_son (Official Video)'));
        self::assertTrue(MusicTitleSimilarity::isSimilar('Fortunate Son', 'Fortunate Son - Remastered 2011'));
        self::assertTrue(MusicTitleSimilarity::isSimilar('Paint It Black', 'Paint it, Black'));
        self::assertTrue(MusicTitleSimilarity::isSimilar('Ma Bénédiction', 'ma benediction.mp3'));
        self::assertTrue(MusicTitleSimilarity::isSimilar('Run Through the Jungle', 'Run Thru the Jungle'));
    }

    public function testKeepsDistinctTitlesApart(): void
    {
        self::assertFalse(MusicTitleSimilarity::isSimilar('Thunderstruck', 'Thunder'));
        self::assertFalse(MusicTitleSimilarity::isSimilar('Sweet Home Alabama', 'Sweet Home Chicago'));
        self::assertFalse(MusicTitleSimilarity::isSimilar('', 'Anything'));
        self::assertSame('Mon morceau', MusicTitleSimilarity::titleFromFilename('Mon_morceau.mp3'));
    }

    public function testUploadIsGuardedByDoubleConfirmation(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/MusicAdminController.php');
        $view = (string) file_get_contents($root . '/views/admin/music/index.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'confirm_duplicates'", $ctrl);
        self::assertStringContainsString('findSimilarTitles', $ctrl);
        self::assertStringContainsString('data-msca-dup-ok', $view);
        self::assertStringContainsString('window.confirm(', $view);
        self::assertStringContainsString('name="uploader_alias"', $view);
        self::assertStringContainsString("'/admin/musique/doublons'", $routes);
    }
}
