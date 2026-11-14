<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Services\Rbac\AccessRegisterService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccessRegisterServiceTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testStateFollowsValidityDates(): void
    {
        $at = static fn (int $days): string => date('Y-m-d H:i:s', self::NOW + $days * 86400);
        self::assertSame(AccessRegisterService::STATE_ACTIVE, AccessRegisterService::stateFor(null, null, self::NOW));
        self::assertSame(AccessRegisterService::STATE_ACTIVE, AccessRegisterService::stateFor($at(-10), $at(90), self::NOW));
        self::assertSame(AccessRegisterService::STATE_EXPIRING, AccessRegisterService::stateFor(null, $at(10), self::NOW));
        self::assertSame(AccessRegisterService::STATE_UPCOMING, AccessRegisterService::stateFor($at(3), $at(10), self::NOW));
        self::assertSame(AccessRegisterService::STATE_EXPIRED, AccessRegisterService::stateFor(null, $at(-1), self::NOW));
        self::assertSame(AccessRegisterService::STATE_ACTIVE, AccessRegisterService::stateFor('0000-00-00 00:00:00', '', self::NOW));
    }

    public function testKindSeparatesAccessPositionUnitAndOtherRoles(): void
    {
        self::assertSame(AccessRegisterService::KIND_ACCESS, AccessRegisterService::kindForRole('community_owner', null));
        self::assertSame(AccessRegisterService::KIND_ACCESS, AccessRegisterService::kindForRole('access-custom-pilote', null));
        self::assertSame(AccessRegisterService::KIND_UNIT, AccessRegisterService::kindForRole('hr', 12));
        self::assertSame(AccessRegisterService::KIND_POSITION, AccessRegisterService::kindForRole(PersonnelDutyPositionService::SLUG_INACTIVE, null));
        self::assertSame(AccessRegisterService::KIND_ROLE, AccessRegisterService::kindForRole('veteran', null));
    }

    public function testDatesAreDayBoundsAndPeriodIsChecked(): void
    {
        self::assertNull(AccessRegisterService::normalizeDate('', true));
        self::assertSame('2027-03-01 00:00:00', AccessRegisterService::normalizeDate('2027-03-01', false));
        self::assertSame('2027-03-01 23:59:59', AccessRegisterService::normalizeDate('2027-03-01', true));
        try {
            AccessRegisterService::normalizeDate('01/03/2027', false);
            self::fail('Une date hors format doit être refusée.');
        } catch (InvalidArgumentException) {
        }
        try {
            AccessRegisterService::assertValidPeriod(null, date('Y-m-d H:i:s', self::NOW - 60), self::NOW);
            self::fail('Une fin passée doit être refusée.');
        } catch (InvalidArgumentException) {
        }
        try {
            AccessRegisterService::assertValidPeriod('2099-05-02 00:00:00', '2099-05-01 23:59:59', self::NOW);
            self::fail('Une fin avant le début doit être refusée.');
        } catch (InvalidArgumentException) {
        }
        AccessRegisterService::assertValidPeriod('2099-05-01 00:00:00', '2099-05-01 23:59:59', self::NOW);
        self::assertTrue(true);
    }

    public function testFiltersSearchIgnoresAccentsAndCaseAndCombinesWords(): void
    {
        $row = ['member' => 'Élise Durand', 'callsign' => 'Faucon 2', 'right' => 'Gestionnaire', 'scope' => 'Toute la communauté',
            'kind' => AccessRegisterService::KIND_ACCESS, 'state' => AccessRegisterService::STATE_EXPIRING, 'since' => '2026-02-11'];
        self::assertTrue(AccessRegisterService::matches($row, ['q' => 'elise faucon']));
        self::assertFalse(AccessRegisterService::matches($row, ['q' => 'elise alpha']));
        self::assertTrue(AccessRegisterService::matches($row, ['kind' => AccessRegisterService::KIND_ACCESS, 'year' => '2026']));
        self::assertFalse(AccessRegisterService::matches($row, ['year' => '2025']));
        self::assertFalse(AccessRegisterService::matches($row, ['state' => AccessRegisterService::STATE_ACTIVE]));
        self::assertSame(['2026', '2024'], AccessRegisterService::years([['since' => '2024-01-02'], ['since' => '2026-05-01'], ['since' => '']], 'since'));
    }

    public function testCsvNeutralisesFormulasAndIsCapped(): void
    {
        $csv = AccessRegisterService::csv([['member' => '=HYPERLINK("x")', 'right' => 'Membre']], ['member' => 'Membre', 'right' => 'Droit']);
        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringStartsWith('Membre;Droit', $csv);
        self::assertSame('-12', AccessRegisterService::safeCell('-12'));
        $many = array_fill(0, AccessRegisterService::MAX_ROWS + 50, ['member' => 'x']);
        self::assertSame(AccessRegisterService::MAX_ROWS + 1, substr_count(AccessRegisterService::csv($many, ['member' => 'Membre']), "\n"));
    }

    public function testRegisterIsAPaidModule(): void
    {
        self::assertContains('access_register', SubscriptionPlanFeaturesCatalog::BOOL_FEATURES);
        self::assertFalse(SubscriptionPlanFeaturesCatalog::defaultsForSlug('free')['access_register']);
        foreach (['standard', 'pro', 'pro_plus'] as $slug) {
            self::assertTrue(SubscriptionPlanFeaturesCatalog::defaultsForSlug($slug)['access_register'], $slug);
        }
    }

    public function testWiringAccessControlAndViews(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['registre-droits', 'registre-droits/export.csv', 'registre-droits/attribuer', 'registre-droits/modifier', 'registre-droits/retirer', 'registre-droits/revue', 'position-administrative'] as $path) {
            self::assertStringContainsString("/back-office/ressources/effectifs/" . ($path === 'position-administrative' ? 'membres/{id}/' : '') . $path . "'", $routes, $path);
        }
        // Une attribution bornée ne donne aucun droit hors de sa période.
        $rbac = (string) file_get_contents($root . '/app/Services/Rbac/RbacService.php');
        self::assertStringContainsString('(valid_from IS NULL OR valid_from <= ?) AND (valid_until IS NULL OR valid_until > ?)', $rbac);
        // La synchronisation des rôles garde les attributions conservées (dates, notes du registre).
        $users = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('org_unit_id IS NULL AND role_id NOT IN', $users);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/registre_droits.php');
        foreach (['Rafraîchir', 'Registre annuel', 'Colonnes activées', 'CSV (max.', 'Aide', 'Plein écran', 'Aucune donnée à afficher', 'Marquer comme revus'] as $label) {
            self::assertStringContainsString($label, $view, $label);
        }
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        self::assertStringContainsString("'registre-droits'", $shell);
        $cron = (string) file_get_contents($root . '/app/Core/Container.php');
        self::assertStringContainsString('AccessRegisterExpiryCronJob', $cron);
    }
}
