<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Câblage de la règle unique d'affichage des membres (MemberIdentity), du formulaire « État civil du personnage »
 * et des écrans alignés. Tests statiques : le rendu réel est vérifié à part (voir la PR).
 */
final class MemberIdentityWiringTest extends TestCase
{
    /** Vues modifiées ou ajoutées par ce lot. */
    private const VIEWS = [
        'views/personnel/edit.php',
        'views/personnel/file.php',
        'views/personnel/directory.php',
        'views/admin/effectifs_workspace/member.php',
        'views/admin/effectifs_workspace/roster.php',
        'views/admin/effectifs_workspace/qualifications.php',
        'views/admin/effectifs_workspace/rh_documents.php',
        'views/admin/effectifs_workspace/rh_roleplay.php',
        'views/admin/recruitments/onboarding.php',
        'views/admin/member_integration/index.php',
        'views/admin/organization/roleplay_followup.php',
        'views/admin/organization/roleplay_game_session_show.php',
        'views/admin/organization/qualifications/show.php',
        'views/partials/member_name_preview.php',
        'views/partials/dashboard_effectifs_table.php',
        'views/partials/personnel/file_tableau_admin_tab.php',
        'views/jnet/guide.php',
        'views/jnet/roster.php',
        'views/training/course_exchanges.php',
    ];

    private static function src(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Une vue qui appelle une classe sans l'importer (ni chemin complet) plante en production : erreur 500. */
    public function testEveryViewThatCallsAClassImportsIt(): void
    {
        $builtin = ['self', 'static', 'parent', 'DateTime', 'DateTimeImmutable', 'DateTimeZone', 'DateInterval', 'IntlDateFormatter', 'NumberFormatter', 'Collator', 'Normalizer', 'Transliterator', 'JsonException', 'PDO'];
        foreach (self::VIEWS as $view) {
            $src = self::src($view);
            // Sans les commentaires ni le contenu des chaînes : on ne regarde que le code appelant.
            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"~s', "''", $src);
            preg_match_all('/(?<![\\\\\w$>:])([A-Z][A-Za-z0-9_]*)::/', $code, $m);
            foreach (array_unique($m[1]) as $class) {
                if (in_array($class, $builtin, true)) {
                    continue;
                }
                $bs = preg_quote(chr(92), '/');
                self::assertMatchesRegularExpression('/^use [' . $bs . '\w]*' . $bs . preg_quote($class, '/') . '( as \w+)?;$|^use [' . $bs . '\w]*' . $bs . '\w+ as ' . preg_quote($class, '/') . ';$/m', $src, $view . ' appelle ' . $class . ' sans « use ».');
            }
        }
    }

    public function testMemberNameHelpersAllGoThroughTheSinglePoint(): void
    {
        $helpers = self::src('app/Support/helpers.php');
        self::assertStringContainsString("\\App\\Support\\MemberIdentity::display(\$row, \$context, \$tenantId, \$allowGrade, \$settings)['primary']", $helpers);
        self::assertStringContainsString('function member_label(', $helpers);
        self::assertStringContainsString('function member_identity(', $helpers);
        foreach (['app/Support/MemberIdentity.php', 'app/Support/MemberName.php'] as $file) {
            $code = (string) preg_replace('~/\*.*?\*/|//[^
]*~s', '', self::src($file));
            self::assertStringNotContainsString('character_name', $code, $file);
        }
    }

    public function testFamiliesOfScreensUseTheSinglePointInsteadOfHandMadeChains(): void
    {
        $expect = [
            'app/Services/Jnet/JnetDashboardService.php' => '\App\Support\MemberIdentity::display([',
            'app/Services/Jnet/JnetSpaceService.php' => 'member_label([',
            'app/Repositories/JnetUnitWorkspaceRepository.php' => 'member_label([',
            'app/Repositories/TenantMessageRepository.php' => 'member_label([',
            'app/Services/Effectifs/EffectifsSearchService.php' => 'MemberIdentity::labelForUser(',
            'app/Services/Effectifs/RhActionInboxService.php' => 'MemberIdentity::numberedLabelForUser(',
            'app/Controllers/Web/PersonnelCorrectionController.php' => 'MemberIdentity::numberedLabelForUser(',
            'app/Services/Cron/Jobs/RoleplayBilanDueCronJob.php' => 'member_label([',
            'app/Services/Personnel/RoleplayFollowupNotificationService.php' => 'member_label([',
            'views/partials/dashboard_effectifs_table.php' => 'MemberIdentity::labelForUser(',
            'views/jnet/roster.php' => 'member_label([',
        ];
        foreach ($expect as $file => $needle) {
            self::assertStringContainsString($needle, self::src($file), $file);
        }
        // Le nom du personnage n'est plus un repli dans la carte JNET.
        $jnet = self::src('app/Services/Jnet/JnetDashboardService.php');
        $card = substr($jnet, (int) strpos($jnet, 'function normalizePersonCard'), 1800);
        self::assertStringNotContainsString('character_name', $card);
    }

    public function testCivilStatusDialogHasFirstAndLastNameWritingToTheProfileTable(): void
    {
        $view = self::src('views/admin/effectifs_workspace/member.php');
        $dialog = substr($view, (int) strpos($view, 'id="eff-dlg-civil"'));
        $dialog = substr($dialog, 0, (int) strpos($dialog, '</dialog>'));
        self::assertStringContainsString('name="first_name"', $dialog);
        self::assertStringContainsString('name="last_name"', $dialog);
        // Prénom et nom en tête de la fenêtre, avant le surnom.
        self::assertTrue(strpos($dialog, 'name="first_name"') < strpos($dialog, 'name="nickname_primary"'));
        self::assertStringContainsString("'first_name' => 'Prénom'", $view);
        self::assertStringContainsString("'last_name' => 'Nom'", $view);

        $controller = self::src('app/Controllers/Admin/EffectifsWorkspaceController.php');
        $handler = substr($controller, (int) strpos($controller, 'function updateMemberCivilStatus'));
        $handler = substr($handler, 0, (int) strpos($handler, 'public function quickAssignment'));
        // Mêmes droits qu'avant, CSRF, écriture dans user_profiles (comme la fiche), nettoyage, audit.
        self::assertStringContainsString('EffectifsLmsAccess::canEditProfiles', $handler);
        self::assertStringContainsString('Csrf::validate', $handler);
        self::assertStringContainsString('UserProfileRepository::class', $handler);
        self::assertStringContainsString('->upsert($id, $nameUpsert)', $handler);
        self::assertStringContainsString('MemberIdentity::cleanNamePart(', $handler);
        self::assertStringContainsString("'nom_affiche:' . \$nameBefore", $handler);
        self::assertStringContainsString("'etat_civil'", $handler);
        // Aucune écriture sur le compte (display_name) ni sur l'identité légale par ce formulaire.
        self::assertStringNotContainsString('user_legal_identities', $handler);
        self::assertStringNotContainsString("'display_name'", $handler);
    }

    public function testFormsGiveEachFieldAPlainLabelAndAHelpAndAPreview(): void
    {
        $edit = self::src('views/personnel/edit.php');
        self::assertStringContainsString('État civil du personnage', $edit);
        self::assertStringContainsString('name="rp_first_name"', $edit);
        self::assertStringContainsString('name="rp_last_name"', $edit);
        self::assertStringContainsString('le nom que tout le monde voit', $edit);
        self::assertStringContainsString('Votre nom de radio', $edit);
        self::assertStringContainsString('views/partials/member_name_preview.php', $edit);
        self::assertStringNotContainsString('name="character_name"', $edit);
        self::assertStringNotContainsString('Nom du personnage (ancien champ)', $edit);

        $onboarding = self::src('views/admin/recruitments/onboarding.php');
        self::assertStringContainsString('État civil du personnage', $onboarding);
        self::assertStringContainsString('name="first_name"', $onboarding);
        self::assertStringContainsString('name="last_name"', $onboarding);

        $member = self::src('views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString('views/partials/member_name_preview.php', $member);

        $partial = self::src('views/partials/member_name_preview.php');
        self::assertStringContainsString('Votre nom s’affichera ainsi', $partial);
        $js = self::src('public/assets/js/member-name-preview.js');
        self::assertStringNotContainsString('innerHTML', $js);
        self::assertStringContainsString('textContent', $js);
    }

    public function testGuideHasTheMyIdentitiesChapterAndEnglishEntries(): void
    {
        $guide = self::src('views/jnet/guide.php');
        self::assertStringContainsString("'identites' => '10. Mes identités'", $guide);
        self::assertStringContainsString('id="identites"', $guide);
        $en = self::src('lang/en/site_auto.php');
        foreach (['10. Mes identités', 'Votre nom s’affichera ainsi :', 'Quel nom s’affiche pour moi ?'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' =>", $en);
        }
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/identites-membres.md');
        self::assertStringContainsString('prénom', mb_strtolower(self::src('docs/identites-membres.md')));
    }

    public function testCleanNamePartRejectsHtmlAndBoundsLength(): void
    {
        self::assertSame('', \App\Support\MemberIdentity::cleanNamePart('   '));
        self::assertSame('Jean Pierre', \App\Support\MemberIdentity::cleanNamePart("  Jean \n Pierre "));
        self::assertNull(\App\Support\MemberIdentity::cleanNamePart('<script>alert(1)</script>'));
        self::assertNull(\App\Support\MemberIdentity::cleanNamePart("Je\x00an"));
        self::assertSame(100, mb_strlen((string) \App\Support\MemberIdentity::cleanNamePart(str_repeat('é', 300))));
        self::assertSame('O’Neil', \App\Support\MemberIdentity::cleanNamePart('O’Neil'));
    }
}
