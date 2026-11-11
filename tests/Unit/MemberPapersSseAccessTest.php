<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\MemberIdentityPapersController;
use App\Core\Gate;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseClearanceService;
use App\Support\EffectifsLmsAccess;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Bureau SSE › « Papiers des membres » : accès direct d'un responsable RENS sans droit Effectifs (page papiers oui,
 * fiche membre complète non), refus sans droit SSE, couverture masquée sans l'habilitation Confidentiel, et câblage.
 */
final class MemberPapersSseAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
        if (extension_loaded('pdo_sqlite')) {
            SseIdentitySqlite::close();
        }
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** @param list<string> $levels */
    private function clearance(array $levels): SseClearanceService
    {
        $c = $this->createMock(SseClearanceService::class);
        $c->method('allows')->willReturnCallback(static fn (string $l): bool => in_array($l, $levels, true));

        return $c;
    }

    public function testRensManagerWithoutEffectifsReachesPapersButNotTheMemberFile(): void
    {
        Gate::getInstance()->setPermissions(['atak.sse.case.manage']);
        $rights = MemberIdentityPapersController::currentRights($this->clearance([]));
        self::assertTrue($rights['sse']);
        self::assertTrue($rights['manage']);
        self::assertFalse($rights['truth']);
        self::assertFalse($rights['cover']);
        // La fiche membre complète reste fermée (EffectifsWorkspaceController::denyUnlessAccess).
        self::assertFalse(EffectifsLmsAccess::allows(Gate::getInstance()));
    }

    public function testWithoutSseRightsTheDeskRefuses(): void
    {
        Gate::getInstance()->setPermissions(['personnel.profile.update']);
        $rights = MemberIdentityPapersController::currentRights($this->clearance([]));
        self::assertFalse($rights['sse']);
        Gate::getInstance()->setPermissions([]);
        self::assertFalse(MemberIdentityPapersController::currentRights($this->clearance([]))['sse']);
        // L'habilitation Encadrement seule ouvre la lecture ; Confidentiel ajoute les couvertures.
        $cmd = MemberIdentityPapersController::currentRights($this->clearance([SseCaseRepository::CLASS_COMMAND]));
        self::assertTrue($cmd['sse']);
        self::assertFalse($cmd['manage']);
    }

    public function testCoverIsHiddenWithoutConfidentialInRosterAndSection(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        $pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Joueur'), (11, 1, NULL, 'Sans Steam')");
        $s = new MemberIdentityPapersService(null, null, null, null, null, static function (array $e): void {
        });
        $primary = $s->savePrimary(1, 10, ['last_name' => 'Dupont', 'first_name' => 'Jean', 'nationality' => 'ALT'], 1)['id'];
        $cover = $s->createAlias(1, 10, ['kind' => 'cover', 'last_name' => 'Markos', 'first_name' => 'Petros', 'nationality' => 'ALT'], true, 1)['id'];
        $doc = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $primary, true), null, ['doc_type' => 'id_card', 'country_code' => 'ALT'], true, 1)['id'];
        $coverDoc = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $cover, true), null, ['doc_type' => 'passport', 'country_code' => 'ALT'], true, 1)['id'];
        foreach ([$doc, $coverDoc] as $d) {
            self::assertTrue($s->action(1, 10, $s->ownedDocument(1, 10, $d, true), 'issue', '', 1)['ok']);
        }
        $s->saveKit(1, 10, null, null, [$coverDoc], true, 1);

        $staff = $s->roster(1, [10, 11], false);
        self::assertSame(['steam_linked' => true, 'issued' => 1, 'kit' => false, 'last_check' => null], $staff[10]);
        self::assertSame(['steam_linked' => false, 'issued' => 0, 'kit' => false, 'last_check' => null], $staff[11]);
        $cleared = $s->roster(1, [10], true);
        self::assertSame(2, $cleared[10]['issued']);
        self::assertTrue($cleared[10]['kit']);
        // Autre communauté : rien.
        self::assertSame(0, $s->roster(2, [10], true)[10]['issued']);

        $names = array_map(static fn (array $i): string => (string) $i['last_name'], $s->section(1, 10, '76561198000000010', false)['identities']);
        self::assertNotContains('Markos', $names);
    }

    public function testSseDeskRoutesViewsAndNavAreWired(): void
    {
        $routes = self::read('routes/web.php');
        $r = '[\\App\\Controllers\\Web\\SseIdentityRegistryController::class, ';
        $m = '[\\App\\Controllers\\Admin\\MemberIdentityPapersController::class, ';
        $b = '/atak/sse/papiers/membres/{id}';
        foreach ([
            "get('/atak/sse/papiers/membres', {$r}'membersIndex'], \$mwSsePortal)",
            "get('{$b}', {$r}'memberShow'], \$mwSsePortal)",
            "post('{$b}/identite-principale', {$m}'savePrimary'], \$mwSsePortal)",
            "post('{$b}/identites', {$m}'createAlias'], \$mwSsePortal)",
            "get('{$b}/documents/nouveau', {$m}'documentForm'], \$mwSsePortal)",
            "get('{$b}/documents/{docId}/modifier', {$m}'documentForm'], \$mwSsePortal)",
            "post('{$b}/documents', {$m}'documentStore'], \$mwSsePortal)",
            "post('{$b}/documents/{docId}/action', {$m}'documentAction'], \$mwSsePortal)",
            "post('{$b}/documents/{docId}', {$m}'documentUpdate'], \$mwSsePortal)",
            "post('{$b}/kit', {$m}'kitSave'], \$mwSsePortal)",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        self::assertLessThan(strpos($routes, "'{$b}/documents/{docId}'"), strpos($routes, "'{$b}/documents/{docId}/action'"));
    }

    public function testMemberPageReusesThePartialWithoutTheRestOfTheMemberFile(): void
    {
        $view = self::read('views/atak/sse/papers/member.php');
        self::assertStringContainsString("/admin/effectifs_workspace/partials/member_papers.php'", $view);
        self::assertStringNotContainsString('member.php', str_replace('member_papers.php', '', $view));
        self::assertStringContainsString('$memberFileUrl', $view);
        self::assertStringContainsString("/admin/effectifs_workspace/member_paper_form.php'", self::read('views/atak/sse/papers/member_paper_form.php'));
        $ctrl = self::read('app/Controllers/Web/SseIdentityRegistryController.php');
        self::assertStringContainsString('MemberIdentityPapersController::sectionFor($tenantId, $member, true)', $ctrl);
        self::assertStringContainsString("EffectifsLmsAccess::allows(\\App\\Core\\Gate::getInstance()) ? effectifs_workspace_url('membres/'", $ctrl);
    }

    public function testControllerSwitchesToTheSseSurfaceAndKeepsCsrfAndGuests(): void
    {
        $c = self::read('app/Controllers/Admin/MemberIdentityPapersController.php');
        self::assertStringContainsString("str_starts_with(ltrim(\$request->path(), '/'), 'atak/sse/')", $c);
        self::assertStringContainsString("Csrf::validate((string) \$request->input('_csrf_token', ''))", $c);
        self::assertStringContainsString('->isGuest()', $c);
        self::assertSame(url('atak/sse/papiers/membres/7'), MemberIdentityPapersController::baseUrl(7, true));
        // Lien dans la navigation du bureau, réservé au même droit que la page.
        self::assertContains('atak/sse/papiers/membres', array_column(\App\Support\SsePortalNav::sections(false, false, false, true)[5]['items'], 'path'));
        self::assertNotContains('atak/sse/papiers/membres', array_column(\App\Support\SsePortalNav::sections(false, false, false, false)[5]['items'], 'path'));
    }
}
