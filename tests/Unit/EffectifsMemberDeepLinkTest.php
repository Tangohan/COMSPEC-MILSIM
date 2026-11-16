<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\RhActionInboxService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Liens de renvoi vers les fiches : la fiche de la personne concernée, avec la bonne fenêtre ouverte d'emblée. */
final class EffectifsMemberDeepLinkTest extends TestCase
{
    public function testMemberUrlCarriesTheDialogAndAnchor(): void
    {
        $base = effectifs_workspace_url('membres/12');
        self::assertSame($base, effectifs_member_url(12));
        self::assertSame($base . '?ouvrir=etat-civil', effectifs_member_url(12, 'etat-civil'));
        self::assertSame($base . '#grade', effectifs_member_url(12, '', 'grade'));
        self::assertSame($base . '?ouvrir=fonctions#jobs', effectifs_member_url(12, 'fonctions', '#jobs'));
        // Un nom douteux n'est jamais mis dans l'adresse.
        self::assertSame($base, effectifs_member_url(12, 'x&y=<script>'));
    }

    public function testGapIssueLinksOpenTheMatchingDialog(): void
    {
        $m = new ReflectionMethod(RhActionInboxService::class, 'issueLinks');
        $links = $m->invoke(null, 7, ['function', 'rank', 'operator_image']);
        $byKey = [];
        foreach ($links as $l) {
            $byKey[$l['key']] = $l['href'];
        }
        self::assertSame(effectifs_member_url(7, 'fonctions'), $byKey['function']);
        self::assertSame(effectifs_member_url(7, 'portrait'), $byKey['operator_image']);
        self::assertSame(effectifs_member_url(7, '', 'grade'), $byKey['rank']);
    }

    public function testMemberScriptOpensTheDialogFromTheQueryAndIgnoresUnknownNames(): void
    {
        $js = (string) file_get_contents(base_path('public/assets/js/eff-member.js'));
        self::assertStringContainsString("searchParams.get('ouvrir')", $js);
        self::assertStringContainsString("'etat-civil': 'eff-dlg-civil'", $js);
        self::assertStringContainsString('hasOwnProperty.call(DIALOG_ALIASES', $js);
        // Chaque alias vise une fenêtre qui existe sur la fiche.
        // La fiche et ses fenêtres découpées en partiels (État civil du personnage).
        $member = (string) file_get_contents(base_path('views/admin/effectifs_workspace/member.php'))
            . (string) file_get_contents(base_path('views/admin/effectifs_workspace/partials/member_civil_dialog.php'));
        preg_match_all("/'[a-z-]+': '(eff-dlg-[a-z-]+)'/", $js, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $member);
        }
    }

    public function testLegacyDataSectionLinksEachConcernedMember(): void
    {
        $h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $icon = static fn (string $n): string => '';
        $csrfToken = 'x';
        $render = static function (array $legacy) use ($h, $icon, $csrfToken): string {
            ob_start();
            require base_path('views/admin/organization/partials/legacy_data.php');

            return (string) ob_get_clean();
        };
        $line = ['key' => 'no_civil_name', 'count' => 0, 'text' => 'Sans nom.'];

        $one = $render(['counts' => ['no_civil_name' => 1], 'lines' => [$line], 'civilTargets' => [['id' => 5, 'label' => 'ALPHA']]]);
        self::assertStringContainsString(effectifs_member_url(5, 'etat-civil'), $one);

        $many = $render(['counts' => ['no_civil_name' => 3], 'lines' => [$line], 'civilTargets' => [['id' => 5, 'label' => 'ALPHA'], ['id' => 6, 'label' => 'BRAVO']]]);
        self::assertStringContainsString(effectifs_member_url(5, 'etat-civil'), $many);
        self::assertStringContainsString(effectifs_member_url(6, 'etat-civil'), $many);
        self::assertStringContainsString('1 autre(s) fiche(s)', $many);
    }
}
