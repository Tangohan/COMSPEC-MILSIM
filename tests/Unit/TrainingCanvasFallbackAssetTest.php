<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrainingCanvasFallbackAssetTest extends TestCase
{
    public function testCanvasFramesAreAddressableWhenSwiperIsUnavailable(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/training/partials/canvas_lesson_player.php');
        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/training_canvas_player.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/training-parcours-deck.css');

        self::assertStringContainsString('data-lms-slide-frame', $view);
        self::assertStringContainsString("root.classList.add('lms-canvas-player--fallback')", $script);
        self::assertStringContainsString("frame.hidden = j !== idx", $script);
        self::assertStringContainsString('[data-lms-slide-frame][hidden]', $css);
    }

    public function testSeededRecruitmentCourseRepublishesEditorialContent(): void
    {
        $seed = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/training_bureau_recrutement_course_seed.php');

        self::assertStringContainsString('training_bureau_recrutement_sync_seeded_lessons', $seed);
        self::assertStringContainsString('Deux espaces, deux voix', $seed);
        self::assertStringContainsString('Une décision doit fermer une question', $seed);
    }
}
