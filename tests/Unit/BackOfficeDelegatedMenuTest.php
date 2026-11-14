<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BackOfficeDelegatedStaff;
use PHPUnit\Framework\TestCase;

/** Menu « cadre » (audit du back-office, §9) : un cadre délégué voit les pages de gestion que ses droits ouvrent. */
final class BackOfficeDelegatedMenuTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    /** @param list<string> $held */
    private static function can(array $held): callable
    {
        return static fn (string $perm): bool => in_array($perm, $held, true);
    }

    public function testDelegatedProfilesAreRecognised(): void
    {
        foreach (['personnel.status.manage', 'organization.recruitment.manage', 'admin.roles.manage', 'admin.audit.view', 'admin.members.moderate', 'operations.missions.manage', 'atak.sse.case.manage', 'personnel.equipment.manage', 'comms.email.broadcast'] as $perm) {
            self::assertTrue(BackOfficeDelegatedStaff::is(self::can([$perm])), $perm);
        }
    }

    public function testPlainMembersAndFullManagersAreNotDelegated(): void
    {
        self::assertFalse(BackOfficeDelegatedStaff::is(self::can([])));
        self::assertFalse(BackOfficeDelegatedStaff::is(self::can(['personnel.profile.view'])));
        // Un gestionnaire a déjà le menu complet.
        self::assertFalse(BackOfficeDelegatedStaff::is(self::can(['admin.organization', 'personnel.status.manage'])));
        self::assertFalse(BackOfficeDelegatedStaff::is(self::can(['admin.access'])));
        self::assertFalse(BackOfficeDelegatedStaff::is(self::can(['site.support', 'admin.audit.view'])));
    }

    /** Chaque droit du middleware d'accès aux pages d'effectifs ouvre aussi le menu. */
    public function testListCoversTheMiddlewareRights(): void
    {
        $mw = (string) file_get_contents(self::ROOT . 'app/Middleware/OrganizationAdminMiddleware.php');
        preg_match_all("/allows\('([a-z_.]+)'\)/", $mw, $m);
        $inMenu = array_flip(BackOfficeDelegatedStaff::PERMISSIONS);
        // Lecture seule : ne bascule pas le menu (page « Disponibilité »).
        $global = ['admin.organization', 'admin.access', 'site.support', 'organization.orbat.view', 'personnel.progression.view'];
        $missing = [];
        foreach (array_unique($m[1]) as $perm) {
            if (!in_array($perm, $global, true) && !isset($inMenu[$perm]) && !str_starts_with($perm, 'atak.') && !str_starts_with($perm, 'admin.system')) {
                $missing[] = $perm;
            }
        }
        // Les droits de formation et de médias passent par leurs propres aides (TrainingLmsStaffAccess, CommunityMediaStaffAccess).
        $missing = array_values(array_filter($missing, static fn (string $p): bool => !str_starts_with($p, 'training.') && !str_starts_with($p, 'media.') && !str_starts_with($p, 'organization.academy')));
        self::assertSame([], $missing, 'droits du middleware absents de la liste du menu : ' . implode(', ', $missing));
    }

    public function testMenuMergesManagerAndPersonalGroups(): void
    {
        $nav = (string) file_get_contents(self::ROOT . 'views/partials/ath_sidebar_nav.php');
        self::assertStringContainsString('$isDelegatedStaff = $isOperatorBoNav && !empty($isDelegatedStaff);', $nav);
        self::assertStringContainsString('if (!$isOperatorBoNav || $isDelegatedStaff) {', $nav);
        self::assertStringContainsString("\$opGroup['key'] = 'mon-' .", $nav);
        // Chaque page reste filtrée par ses droits ; le tableau de bord n'est pas doublé.
        self::assertGreaterThan(strpos($nav, '$opNavGroups !== null'), strpos($nav, 'BackOfficeNavTree::filter($athNavGroups, $boHrefAllowed)'));
        self::assertStringContainsString("if ((\$opGroup['key'] ?? '') === 'pilotage') {", $nav);
        $side = (string) file_get_contents(self::ROOT . 'views/partials/back_office_sidebar.php');
        self::assertStringContainsString("'MON PÉRIMÈTRE'", $side);
        self::assertStringContainsString('BackOfficeDelegatedStaff::is(', $side);
    }
}
