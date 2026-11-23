<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LmsModuleEntry;
use PHPUnit\Framework\TestCase;

/**
 * Régression : le tutoriel d'entrée du bureau recrutement se rejouait à chaque visite.
 * Cause : fermer la modale (croix, Échap, « Continuer ») ne mémorisait « vu » que dans sessionStorage
 * (propre à l'onglet) ; seule la case « Ne plus afficher » écrivait dans localStorage.
 */
final class RecruitmentTutorialSeenTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testClosingOrContinuingPersistsSeenWithoutCheckbox(): void
    {
        $js = (string) file_get_contents($this->root . '/public/assets/js/lms_module_entry_modal.js');

        self::assertStringNotContainsString('skipInput', $js, 'La mémorisation ne dépend plus d\'une case à cocher.');
        self::assertMatchesRegularExpression('/markWelcomed\(pendingModule\);\s*markDismissed\(pendingModule\);/', $js);
        self::assertMatchesRegularExpression('/markWelcomed\(moduleKey\);\s*markDismissed\(moduleKey\);\s*\}\s*closeModal\(\);/', $js);
    }

    public function testSeenStateIsVersionedAndReplayable(): void
    {
        $js = (string) file_get_contents($this->root . '/public/assets/js/lms_module_entry_modal.js');

        self::assertStringContainsString('map[moduleKey] = currentVersion(moduleKey);', $js);
        self::assertStringContainsString('seen >= currentVersion(moduleKey)', $js);
        self::assertStringContainsString('data-lms-entry-replay', $js);
    }

    public function testClientConfigExposesExplicitTutorialVersion(): void
    {
        self::assertGreaterThanOrEqual(1, LmsModuleEntry::TUTORIAL_VERSION);
        foreach (LmsModuleEntry::clientConfig('recrutement')['modules'] as $module) {
            self::assertSame(LmsModuleEntry::TUTORIAL_VERSION, $module['version']);
        }
    }

    public function testRecruitmentDeskOffersReplayButton(): void
    {
        $view = (string) file_get_contents($this->root . '/views/admin/recruitment_workspace/dashboard.php');

        self::assertStringContainsString('data-lms-entry-replay="recrutement"', $view);
        self::assertStringContainsString('Revoir le tutoriel', $view);
    }
}
