<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelCorrectionsQueueAssetTest extends TestCase
{
    public function testQueueUsesLightBackOfficeChrome(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/personnel/corrections_queue.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-corrections.css');

        self::assertStringContainsString("'path' => 'back-office/personnel/corrections'", $pages);
        self::assertStringContainsString('Corrections de fiches', $pages);
        self::assertStringContainsString('back-office-corrections.css', $pages);
        self::assertStringContainsString('rh-corr__empty', $view);
        self::assertStringContainsString('Valider', $view);
        self::assertStringContainsString('Confirmer le refus', $view);
        self::assertStringContainsString('required', $view, 'le motif d’un refus est obligatoire');
        self::assertStringContainsString('?vue=historique', $view);
        self::assertStringContainsString('data-rh-batch', $view);
        self::assertStringContainsString('Refuser', $view);
        self::assertStringContainsString('Actuellement', $view);
        self::assertStringContainsString('Proposé', $view);
        self::assertStringContainsString('rh-corr__sides', $view);
        self::assertStringNotContainsString('unit_id', $view);
        self::assertStringContainsString('Corriger un dossier sans demande', $view);
        self::assertStringContainsString('Ouvrir le dossier', $view);
        self::assertStringContainsString('corrections/appliquer', $view);
        self::assertStringContainsString('correction_form.php', $view);
        self::assertStringContainsString('.rh-corr__direct', $css);
        self::assertStringContainsString('72rem', $css);
        self::assertStringNotContainsString('text-white', $view);
        self::assertStringNotContainsString('bg-slate-900', $view);
        self::assertStringContainsString('var(--ath-ink', $css, 'thème sombre par les jetons du portail');
        self::assertDoesNotMatchRegularExpression('/background:\s*#fff/', $css);
        self::assertStringContainsString('.rh-corr__empty', $css);
    }
}
