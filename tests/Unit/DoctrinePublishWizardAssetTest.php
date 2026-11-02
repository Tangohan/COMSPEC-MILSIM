<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Doctrine › Publier un document : assistant en étapes sur le même formulaire (POST + CSRF, champs inchangés).
 */
final class DoctrinePublishWizardAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testFormKeepsEveryFieldReadByTheController(): void
    {
        $view = $this->read('views/admin/documents/publish_form.php');
        self::assertStringContainsString('method="post" action="<?= $h(url(\'back-office/documents/publier\')) ?>"', $view);
        self::assertStringContainsString('name="_csrf_token"', $view);
        $controller = $this->read('app/Controllers/Admin/Organization/AdminDoctrineController.php');
        preg_match('~function publishSave.*?\R    }\R~s', $controller, $m);
        preg_match_all("~->input\\('([a-z_]+)'~", $m[0] ?? '', $inputs);
        self::assertNotEmpty($inputs[1]);
        // Lus par le contrôleur mais jamais proposés par cette page (valeurs par défaut du service).
        $notInForm = ['subdomain_id', 'issuing_authority_type', 'include_future_members'];
        foreach (array_diff(array_unique($inputs[1]), $notInForm) as $name) {
            self::assertStringContainsString('name="' . $name . '"', $view, $name);
        }
        foreach (['audience_mode', 'include_children', 'audience_units[]', 'audience_job_roles[]', 'audience_roles[]', 'audience_users[]'] as $name) {
            self::assertStringContainsString($name, $view, $name);
        }
        foreach (['save', 'submit_validation', 'publish'] as $action) {
            self::assertStringContainsString('data-dct-action="' . $action . '"', $view);
        }
        self::assertStringContainsString('source_reference', $view);
    }

    public function testWizardReusesTheSharedStepperAndScripts(): void
    {
        $view = $this->read('views/admin/documents/publish_form.php');
        self::assertStringContainsString("views/partials/ui/stepper.php", $view);
        self::assertSame(5, substr_count($view, 'data-wiz-step'));
        self::assertStringContainsString('assets/js/doctrine-publish-wizard.js', $view);
        self::assertStringContainsString('assets/js/document_rich_editor.js', $view);
        $js = $this->read('public/assets/js/doctrine-publish-wizard.js');
        foreach (['[data-doc-wizard]', 'doc-publish-action', 'localStorage', 'DOMParser', 'aria-current'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        self::assertStringContainsString('.dct-wiz .ds-stepper', $this->read('public/assets/css/doctrine-hub.css'));
    }

    public function testPageIsStillServedInGetWithQueryString(): void
    {
        $controller = $this->read('app/Controllers/Admin/Organization/AdminDoctrineController.php');
        self::assertStringContainsString("\$request->queryString('reference', '')", $controller);
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("get('/back-office/documents/publier', [\\App\\Controllers\\Admin\\Organization\\AdminDoctrineController::class, 'publishForm']", $routes);
        self::assertStringContainsString("post('/back-office/documents/publier', [\\App\\Controllers\\Admin\\Organization\\AdminDoctrineController::class, 'publishSave']", $routes);
    }
}
