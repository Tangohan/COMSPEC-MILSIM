<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsQualificationsCreateTrainingAssetTest extends TestCase
{
    public function testQualificationsPageExposesCreateTrainingButton(): void
    {
        $view = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/qualifications.php'
        );

        self::assertStringContainsString('Créer une formation', $view);
        self::assertStringContainsString('training_studio_url()', $view);
        self::assertStringContainsString('eff-catalog__btn--primary', $view);
        self::assertStringContainsString('Catalogue de formation', $view);
        self::assertStringContainsString('training.create', $view);
    }
}
