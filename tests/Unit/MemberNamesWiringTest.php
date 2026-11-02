<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Câblage du format de nom unique (« Prénom N. » / « Prénom NOM », trigramme) dans les pages du site,
 * et C2 laissé sur l’indicatif.
 */
final class MemberNamesWiringTest extends TestCase
{
    private static function src(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testOrbatLabelsAndTrigramsComeFromTheFormatterWithMaskingGuards(): void
    {
        $repo = self::src('app/Repositories/UnitRepository.php');
        self::assertStringContainsString("MemberNameService(\$this->pdo()))->labelsFor(\$tenantId, array_keys(\$out), 'list')", $repo);

        $payload = self::src('app/Support/OrbatRosterPayload.php');
        // Trigrammes posés par le même passage que les grades : après le masquage, jamais sur une unité aux noms masqués.
        self::assertMatchesRegularExpression('/redactPeopleForViewer\(.*attachGrades\(\$root, self::attempt\(\'trigrammes\'/s', $payload);
        self::assertStringContainsString("empty(\$node['viewerNamesRedacted'])", $payload);

        $js = self::src('public/assets/js/orbat-app.js');
        self::assertStringContainsString("if (p && p.confidential) return confBadge(p.confidential, true);", $js);
        self::assertStringContainsString('orb-tri', $js);
        self::assertStringContainsString("'trigram' =>", self::src('lang/en/orbat.php'));

        $vis = self::src('app/Services/Organization/OrgVisibilityService.php');
        self::assertStringContainsString("\$member['character_name'], \$member['trigram']", $vis);
    }

    public function testDirectoryRosterAndMemberFileShowFirstNameFirst(): void
    {
        $dir = self::src('views/personnel/directory.php');
        self::assertStringContainsString("member_name(\$nameRow, 'list', null, false)", $dir);
        self::assertStringContainsString("MemberName::withIdentity(array_diff_key(\$row, ['first_name' => 1, 'last_name' => 1])", $dir);
        self::assertStringContainsString('pd-tri', $dir);
        self::assertStringContainsString("'memberIdentities' =>", self::src('app/Controllers/Web/PersonnelController.php'));

        $roster = self::src('views/admin/effectifs_workspace/roster.php');
        self::assertStringContainsString("member_name(\$nameRow, 'list', null, false)", $roster);
        self::assertStringContainsString("'rosterIdentities' =>", self::src('app/Controllers/Admin/EffectifsWorkspaceController.php'));

        $file = self::src('views/personnel/file.php');
        self::assertStringContainsString("member_name(\$nameRow, 'card', null, false)", $file);
        self::assertStringContainsString("'anonymized' => !empty(\$personnelAnonymizedForViewer)", $file);
        self::assertStringContainsString('>Trigramme</span>', $file);
        self::assertStringContainsString('name="member_trigram"', self::src('views/personnel/edit.php'));
    }

    public function testAcademyAndDocumentsUseNamesAndTrigrams(): void
    {
        $academy = self::src('app/Controllers/Web/AcademyController.php');
        self::assertStringContainsString("member_name(\$row, 'list', \$tenantId)", $academy);
        self::assertStringNotContainsString("trim((string) (\$member['callsign'] ?? '')) !== '' ? (string) \$member['callsign']", $academy);

        $vars = self::src('app/Services/Courrier/TemplateVariableService.php');
        self::assertStringContainsString("\$resolved['user.trigram']", $vars);
        self::assertStringContainsString("\$resolved['user.short_name']", $vars);
        self::assertStringContainsString("\$resolved['superior.trigram']", $vars);
    }

    public function testBackOfficePageIsRoutedListedAndMigrationRegistered(): void
    {
        $routes = self::src('routes/web.php');
        foreach (["'/back-office/organisation/noms'", "'/back-office/organisation/noms/attribuer'", "'/back-office/organisation/noms/trigramme'"] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        self::assertStringContainsString("'back-office/organisation/noms'", self::src('config/back_office_pages.php'));
        self::assertStringContainsString("url('back-office/organisation/noms')", self::src('views/partials/ath_sidebar_nav.php'));
        self::assertStringContainsString("'/back-office/organisation/noms'", self::src('app/Middleware/OrganizationAdminMiddleware.php'));
        self::assertFileExists(dirname(__DIR__, 2) . '/views/admin/organization/member_names.php');

        $registry = require dirname(__DIR__, 2) . '/database/steps/registry.php';
        $ids = array_column($registry, 'id');
        self::assertContains('member_names_trigrams', $ids);
        self::assertSame(count($ids), count(array_unique($ids)));
        self::assertContains('3590_member_names_trigrams.php', array_column($registry, 'file'));
    }
}
