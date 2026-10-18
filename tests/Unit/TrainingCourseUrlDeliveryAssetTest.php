<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Formations « lien » : redirection simple ou visionneuse choisies dans la fiche Studio.
 */
final class TrainingCourseUrlDeliveryAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testStudioFicheOffersThreeDeliveryTypes(): void
    {
        $form = $this->read('views/admin/training/partials/studio_fiche_form.php');
        self::assertStringContainsString('Type de formation', $form);
        self::assertStringContainsString('name="delivery_mode"', $form);
        self::assertStringContainsString('name="delivery_url"', $form);
        self::assertStringContainsString("'url_redirect'", $form);
        self::assertStringContainsString("'url_viewer'", $form);

        $ctrl = $this->read('app/Controllers/Admin/AdminTrainingStudioController.php');
        self::assertStringContainsString('deliveryPatchFromRequest', $ctrl);
        self::assertStringContainsString('TrainingUrlDeliverySchema::ensure', $ctrl);

        $repo = $this->read('app/Repositories/TrainingCourseRepository.php');
        self::assertStringContainsString("'delivery_mode', 'delivery_url'", $repo);
    }

    public function testLearnerIsRedirectedOrShownTheViewer(): void
    {
        $ctrl = $this->read('app/Controllers/Web/TrainingController.php');
        self::assertStringContainsString('training_course_delivery($course)', $ctrl);
        self::assertStringContainsString('openCourseDelivery', $ctrl);
        self::assertStringContainsString("'training.course_url_viewer'", $ctrl);
        self::assertStringContainsString('training_lms_resource_external_href($url)', $ctrl);

        $view = $this->read('views/training/course_url_viewer.php');
        self::assertStringContainsString('views/training/partials/url_viewer.php', $view);
        self::assertStringContainsString('$viewerForceFrame = true', $view);
        self::assertStringContainsString('Retour au catalogue', $view);

        self::assertStringContainsString('courseDeliveryOpenUrl', $this->read('views/training/course.php'));
    }

    public function testDeliveryUrlValidation(): void
    {
        if (!function_exists('training_course_delivery_url_is_valid')) {
            require_once dirname(__DIR__, 2) . '/app/Support/training_lms.php';
        }
        self::assertTrue(training_course_delivery_url_is_valid('https://docs.google.com/document/d/abc/edit'));
        self::assertTrue(training_course_delivery_url_is_valid('/formations/page/guide'));
        self::assertFalse(training_course_delivery_url_is_valid(''));
        self::assertFalse(training_course_delivery_url_is_valid('javascript:alert(1)'));
        self::assertFalse(training_course_delivery_url_is_valid('//evil.example/x'));
        self::assertFalse(training_course_delivery_url_is_valid('https://exemple.fr/a b'));

        self::assertNull(training_course_delivery(['delivery_mode' => 'parcours', 'delivery_url' => 'https://a.fr']));
        self::assertNull(training_course_delivery(['delivery_mode' => 'url_redirect', 'delivery_url' => '']));
        self::assertNull(training_course_delivery([]));
        self::assertSame(
            ['mode' => 'url_viewer', 'url' => 'https://a.fr/doc.pdf'],
            training_course_delivery(['delivery_mode' => 'url_viewer', 'delivery_url' => ' https://a.fr/doc.pdf '])
        );
    }
}
