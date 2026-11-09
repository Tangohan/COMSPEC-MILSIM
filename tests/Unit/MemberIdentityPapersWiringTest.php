<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Câblage « Identité & papiers » : routes du back-office et du registre des pays, onglet de la fiche membre et
 * « Mes papiers », étape de migration 3740 (rejouable), kits du pack documentés pour le serveur de jeu.
 */
final class MemberIdentityPapersWiringTest extends TestCase
{
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testBackOfficeAndCountryRoutesAreRegistered(): void
    {
        $routes = self::read('routes/web.php');
        $c = '[\\App\\Controllers\\Admin\\MemberIdentityPapersController::class, ';
        $mw = '[AuthMiddleware::class, OrganizationAdminMiddleware::class]';
        foreach ([
            "post('/back-office/ressources/effectifs/membres/{id}/papiers/identite-principale', {$c}'savePrimary'], {$mw})",
            "post('/back-office/ressources/effectifs/membres/{id}/papiers/identites', {$c}'createAlias'], {$mw})",
            "get('/back-office/ressources/effectifs/membres/{id}/papiers/documents/nouveau', {$c}'documentForm'], {$mw})",
            "get('/back-office/ressources/effectifs/membres/{id}/papiers/documents/{docId}/modifier', {$c}'documentForm'], {$mw})",
            "post('/back-office/ressources/effectifs/membres/{id}/papiers/documents', {$c}'documentStore'], {$mw})",
            "post('/back-office/ressources/effectifs/membres/{id}/papiers/documents/{docId}/action', {$c}'documentAction'], {$mw})",
            "post('/back-office/ressources/effectifs/membres/{id}/papiers/documents/{docId}', {$c}'documentUpdate'], {$mw})",
            "post('/back-office/ressources/effectifs/membres/{id}/papiers/kit', {$c}'kitSave'], {$mw})",
            "get('/back-office/ma-situation/mes-papiers', {$c}'mine'], {$mw})",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        self::assertLessThan(strpos($routes, "/papiers/documents/{docId}'"), strpos($routes, "/papiers/documents/{docId}/action'"));
        foreach (['countriesIndex', 'countryForm', 'countryStore', 'countryUpdate', 'countryArchive'] as $m) {
            self::assertMatchesRegularExpression("#'/atak/sse/papiers/pays[^']*', \\[\\\\App\\\\Controllers\\\\Web\\\\SseIdentityRegistryController::class, '{$m}'\\], \\\$mwSsePortal\\)#", $routes);
        }
        self::assertLessThan(strpos($routes, "'/atak/sse/papiers/pays/{id}'"), strpos($routes, "'/atak/sse/papiers/pays/nouveau'"));
    }

    public function testMemberFileTabAndMemberSituationPageAreWired(): void
    {
        self::assertStringContainsString("'memberPapers' => MemberIdentityPapersController::sectionFor(\$tenantId, \$user)", self::read('app/Controllers/Admin/EffectifsWorkspaceController.php'));
        $member = self::read('views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString('<a href="#papiers">Identité &amp; papiers</a>', $member);
        self::assertStringContainsString("require __DIR__ . '/partials/member_papers.php';", $member);
        $partial = self::read('views/admin/effectifs_workspace/partials/member_papers.php');
        self::assertStringContainsString('id="papiers-kit"', $partial);
        self::assertStringContainsString('SIMULATION', $partial);
        self::assertStringContainsString('name="_csrf_token"', $partial);
        self::assertStringContainsString("url('back-office/ma-situation/mes-papiers')", self::read('views/partials/ath_sidebar_nav.php'));
        self::assertStringContainsString("'pays' => ['pays', 'Pays émetteurs']", self::read('views/atak/sse/papers/_subnav.php'));
        self::assertStringContainsString('enctype="multipart/form-data"', self::read('views/atak/sse/papers/country_form.php'));
    }

    public function testMigrationStep3740IsQueuedAndReplayable(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'atak_sse_identity_member_papers'", $registry);
        self::assertStringContainsString("'file' => '3740_atak_sse_identity_member_papers.php', 'rerun' => true]", $registry);
        self::assertLessThan(strpos($registry, '3740_atak_sse_identity_member_papers'), strpos($registry, '3730_atak_sse_identity.php'));
        self::assertStringContainsString("/bootstrap/atak_sse_identity_member_papers_migration.php'", self::read('database/steps/3740_atak_sse_identity_member_papers.php'));
        self::assertStringContainsString("atak_sse_identity_member_papers_migration.php'));", self::read('app/Repositories/SseIdentityRepository.php'));

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        try {
            $second = SseIdentitySqlite::migrate($pdo);
            self::assertStringContainsString('sse_id_kits (déjà présente)', $second);
            self::assertStringNotContainsString('sse_identities.user_id', $second, 'colonnes déjà là : rien à refaire');
            $cols = array_column($pdo->query('PRAGMA table_info(sse_identities)')->fetchAll(), 'name');
            self::assertContains('user_id', $cols);
            $cols = array_column($pdo->query('PRAGMA table_info(sse_id_countries)')->fetchAll(), 'name');
            foreach (['theme', 'languages', 'doc_types', 'validity_json', 'flag_path', 'color_primary', 'archived_at'] as $col) {
                self::assertContains($col, $cols);
            }
            self::assertContains('subject_steam_id', array_column($pdo->query('PRAGMA table_info(sse_id_checks)')->fetchAll(), 'name'));
        } finally {
            SseIdentitySqlite::close();
        }
    }

    public function testPackKitsAndContractChangeAreDocumented(): void
    {
        $pack = self::read('app/Services/Sse/SseIdentityPackService.php');
        self::assertStringContainsString("'kits' => \$kits,", $pack);
        self::assertStringContainsString('$this->kits->forPack($tenantId, $mapId, $opKey)', $pack);
        self::assertStringContainsString('$this->kits->completeForPack(', $pack);
        self::assertStringContainsString('getPlayerUID', $pack);
        self::assertStringContainsString("'subject_steam_id' =>", self::read('app/Services/Sse/SseIdCheckService.php'));
        self::assertStringContainsString('JOIN users u ON u.id = k.user_id', self::read('app/Repositories/SseIdKitRepository.php'));
    }
}
