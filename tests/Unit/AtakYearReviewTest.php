<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\AtakYearReviewService;
use App\Support\YearReviewStory;
use PHPUnit\Framework\TestCase;

/**
 * App « Bilan de l'année » du téléphone ATAK : écrans de la rétrospective du site → GET /api/atak/year-review →
 * GetYearReview (DLL) → fn_yearReview → fn_pageYearReview. Et lecteur « Vidéo YouTube » (fn_youtube, fn_pageYoutube).
 */
final class AtakYearReviewTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testCompactGardeLesEcransDuSiteSansLeursInvitationsPayantes(): void
    {
        $slides = [
            ['key' => 'intro', 'tone' => 'night', 'kicker' => 'Rétrospective 2026', 'name' => "Alpha\x001", 'title' => 'Voici ton année.', 'lead' => 'x'],
            ['key' => 'play', 'tone' => 'olive', 'kicker' => 'Temps de jeu', 'big' => '12 h', 'big_label' => 'passées en jeu', 'title' => 'Présent.',
                'stats' => [['Missions jouées', '8'], ['Mois', 'Mars']], 'bars' => [['label' => 'J', 'pct' => 100], ['label' => 'F', 'pct' => 250], ['label' => 'M', 'pct' => -5]]],
            ['key' => 'locked', 'tone' => 'slate', 'title' => 'Bilan de l’unité', 'locked' => true],
            ['key' => 'boards', 'tone' => 'night', 'title' => 'Ils ont porté l’unité.', 'boards' => [['label' => 'Heures', 'rows' => [['rank' => 1, 'name' => 'Bravo', 'value' => '40 h']]]]],
        ];
        $out = AtakYearReviewService::compact(2026, 'Unité', false, $slides);
        self::assertSame('OK', $out['state']);
        self::assertSame(2026, $out['year']);
        self::assertCount(3, $out['slides'], 'l’écran « formule payante » n’est pas montré');
        self::assertSame(['intro', 'play', 'boards'], array_column($out['slides'], 'key'));
        self::assertSame('Alpha 1', $out['slides'][0]['name'], 'caractères de contrôle retirés');
        self::assertSame([['J', 100], ['F', 100], ['M', 0]], $out['slides'][1]['bars'], 'pourcentages bornés à 0..100');
        self::assertSame([['Missions jouées', '8'], ['Mois', 'Mars']], $out['slides'][1]['stats']);
        self::assertSame([['Heures', [[1, 'Bravo', '40 h']]]], $out['slides'][2]['boards']);
    }

    public function testCompactAccepteLesEcransReelsDeLaRetrospective(): void
    {
        $player = [
            'name' => 'Alpha 1', 'active' => true, 'play_s' => 7200, 'missions' => 3, 'days' => 2, 'total_m' => 0,
            'months' => array_fill(0, 12, 0), 'persona' => ['title' => 'Fantassin'],
        ];
        $player['months'][2] = 7200;
        $slides = YearReviewStory::slides(2026, $player, null, ['tenant_name' => 'Unité', 'collective' => false, 'upsell' => false]);
        $out = AtakYearReviewService::compact(2026, 'Unité', true, $slides);
        self::assertNotEmpty($out['slides']);
        self::assertTrue($out['partial']);
        self::assertSame('intro', $out['slides'][0]['key']);
        self::assertLessThanOrEqual(AtakYearReviewService::MAX_SLIDES, count($out['slides']));
        self::assertNotFalse(json_encode($out));
    }

    public function testRouteEtDroits(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("'/api/atak/year-review', [AtakApiController::class, 'yearReview']", $routes);
        $ctl = self::read('app/Controllers/Api/AtakApiController.php');
        $i = strpos($ctl, 'public function yearReview');
        self::assertNotFalse($i);
        $body = substr($ctl, $i, 1400);
        self::assertStringContainsString('requireTenant', $body);
        self::assertStringContainsString('matchedUserId', $body, 'compte relié au joueur par la clé du jeu, jamais par un paramètre');
        self::assertStringNotContainsString('$request->query', $body);
        $svc = self::read('app/Services/Atak/AtakYearReviewService.php');
        self::assertStringContainsString('YearReviewAccess::calendar()', $svc);
        self::assertStringContainsString('YearReviewAccess::collectiveAllowed', $svc);
    }

    public function testDllEtLienSansRequeteAvantLicence(): void
    {
        $cs = self::read('mod/UptoDate/COMSPECExtension/Extension_YearReview.cs');
        self::assertStringContainsString('/api/atak/year-review', $cs);
        self::assertStringContainsString('ERR|use_async', $cs);
        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('function == "GetYearReview"', $ext);
        self::assertStringContainsString('function == "GetYearReviewChunk"', $ext);
        self::assertMatchesRegularExpression('/GetYearReview,GetYearReviewChunk[^"]*"/', $ext);
        self::assertStringContainsString('"GetYearReviewChunk"', self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extFast.sqf'));
        $sqf = self::read(self::MAIN . '/functions/pages/fn_yearReview.sqf');
        self::assertLessThan(
            (int) strpos($sqf, '"GetYearReview"'),
            (int) strpos($sqf, 'COMSPEC_AthenaReady'),
            'la liaison Athena autorisée est vérifiée avant toute lecture'
        );
    }

    public function testAppsDeclareesAvecAbregeIconeEtReglage(): void
    {
        $cfg = self::read(self::MAIN . '/config.cpp');
        self::assertMatchesRegularExpression('/class YearReview\s+\{ name="Bilan de l\'année"; short="Bilan"; page="YEARREVIEW";[^}]*function="comspec_atak_native_fnc_pageYearReview";[^}]*icon="\\\\z\\\\comspec_atak_native\\\\addons\\\\main\\\\data\\\\app_reports\.paa"/', $cfg);
        self::assertMatchesRegularExpression('/class Youtube\s+\{ name="Vidéo YouTube"; short="YT"; page="YOUTUBE";[^}]*icon="[^"]+\.paa"/', $cfg);
        foreach (['yearReview', 'pageYearReview', 'youtube', 'pageYoutube'] as $fn) {
            self::assertStringContainsString("class {$fn} {}", $cfg);
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . '/functions/pages/fn_' . $fn . '.sqf');
        }
        $pre = self::read(self::MAIN . '/XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/"comspec_atak_native_youtube", "CHECKBOX",.*?_team, false, 1\]/s', $pre, 'décoché par défaut, imposé par le serveur');
        // Navigateur à adresses externes : seulement pour l'app YouTube.
        $ctl = self::read(self::MAIN . '/ui/controls.hpp');
        self::assertStringContainsString('class COMSPEC_RscWebBrowser { type=106;', $ctl);
        self::assertMatchesRegularExpression('/class COMSPEC_RscWebBrowser \{.*?allowExternalURL=0;/', $ctl);
        self::assertStringContainsString('COMSPEC_RscWebBrowserExt: COMSPEC_RscWebBrowser { allowExternalURL=1; }', $ctl);
        self::assertSame(1, substr_count(self::read(self::MAIN . '/functions/pages/fn_pageYoutube.sqf'), 'COMSPEC_RscWebBrowserExt'));
    }

    public function testYoutubeValidationStricteEtPasDeRequeteAvantLecture(): void
    {
        $yt = self::read(self::MAIN . '/functions/pages/fn_youtube.sqf');
        self::assertStringContainsString('(count _s) isEqualTo 11', $yt);
        self::assertStringContainsString('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-', $yt);
        self::assertStringContainsString('["youtube.com", "youtube-nocookie.com", "youtu.be"]', $yt);
        self::assertStringContainsString('["embed", "shorts", "live", "v"]', $yt);
        self::assertStringContainsString('"watch"', $yt);
        $page = self::read(self::MAIN . '/functions/pages/fn_pageYoutube.sqf');
        // L'adresse du lecteur n'est construite qu'à partir d'un identifiant validé, et seulement après LIRE.
        self::assertStringContainsString('["embed", _id]', $page);
        self::assertLessThan((int) strpos($page, 'ctrlSetURL'), (int) strpos($page, '"enabled"'));
        self::assertLessThan((int) strpos($page, 'ctrlSetURL'), (int) strpos($page, 'if (_id isEqualTo "") exitWith'));
        // Jamais de diffusion du lien aux autres joueurs, jamais de contact avec la radio.
        foreach ([$yt, $page] as $src) {
            self::assertStringNotContainsString('publicVariable', $src);
            self::assertStringNotContainsString('remoteExec', $src);
            self::assertStringNotContainsString('radioGuard', $src);
        }
    }

    public function testTextesEnAnglais(): void
    {
        $en = self::read(self::MAIN . '/i18n/en.txt');
        foreach (['Bilan de l\'année', 'Vidéo YouTube', 'Vidéos YouTube dans le téléphone', 'Mois par mois', 'LIRE'] as $k) {
            self::assertStringContainsString('["' . $k . '", "', $en, $k);
        }
    }
}
