<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakExperienceService;
use App\Support\AtakCommunityBrief;
use PHPUnit\Framework\TestCase;

/**
 * App Communauté : texte saisi sur le site → GET /api/atak/community-brief → GetCommunityBrief (DLL) → téléphone.
 */
final class AtakCommunityBriefTest extends TestCase
{
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testNotesLuesAvecGenreTitreEtTexte(): void
    {
        $notes = AtakCommunityBrief::parseNotes("ordre | Départ | H-10 au hangar\ntip | Radio | Canal 2\nFréquence 41.0\nTitre | Texte\n\n   \n");

        self::assertSame(['kind' => 'ordre', 'title' => 'Départ', 'body' => 'H-10 au hangar'], $notes[0]);
        self::assertSame(['kind' => 'tip', 'title' => 'Radio', 'body' => 'Canal 2'], $notes[1]);
        self::assertSame(['kind' => 'consigne', 'title' => '', 'body' => 'Fréquence 41.0'], $notes[2]);
        self::assertSame(['kind' => 'consigne', 'title' => 'Titre', 'body' => 'Texte'], $notes[3]);
        self::assertCount(4, $notes);
    }

    public function testNotesSansBaliseEtPlafonnees(): void
    {
        $notes = AtakCommunityBrief::parseNotes('consigne | <b>Gras</b> | <script>x</script>corps');
        self::assertSame('Gras', $notes[0]['title']);
        self::assertStringNotContainsString('<', $notes[0]['body']);

        $long = AtakCommunityBrief::parseNotes('tip | ' . str_repeat('t', 200) . ' | ' . str_repeat('é', 2000));
        self::assertSame(AtakCommunityBrief::MAX_TITLE, mb_strlen($long[0]['title']));
        self::assertSame(AtakCommunityBrief::MAX_BODY, mb_strlen($long[0]['body']));

        self::assertCount(AtakCommunityBrief::MAX_NOTES, AtakCommunityBrief::parseNotes(str_repeat("une note\n", 100)));
    }

    public function testKitLuAvecVariantesNombreEtLibelle(): void
    {
        $kit = AtakCommunityBrief::parseKit("ACE_fieldDressing*2 : Pansements\nItemMap, ACRE_PRC343/TFAR_anprc152");

        self::assertSame(['classes' => ['ACE_fieldDressing'], 'count' => 2, 'label' => 'Pansements'], $kit[0]);
        self::assertSame(['classes' => ['ItemMap'], 'count' => 1, 'label' => ''], $kit[1]);
        self::assertSame(['classes' => ['ACRE_PRC343', 'TFAR_anprc152'], 'count' => 1, 'label' => ''], $kit[2]);
    }

    public function testKitRefuseLesClassesInvalidesEtBorneLeNombre(): void
    {
        self::assertSame([], AtakCommunityBrief::parseKit('pas une classe*2, <b>x</b>, ../../etc'));
        self::assertSame(AtakCommunityBrief::MAX_COUNT, AtakCommunityBrief::parseKit('ItemMap*5000')[0]['count']);
        self::assertSame(1, AtakCommunityBrief::parseKit('ItemMap*0')[0]['count']);
        self::assertCount(AtakCommunityBrief::MAX_KIT, AtakCommunityBrief::parseKit(str_repeat("ItemMap\n", 100)));
    }

    public function testChargeUtileDeLApi(): void
    {
        $payload = AtakCommunityBrief::payload(['community_notes' => "ordre | A | B\ntip | C | D", 'community_kit' => 'ItemMap'], '2026-11-08T10:00:00+00:00');

        self::assertSame(['id' => 1, 'kind' => 'ordre', 'title' => 'A', 'body' => 'B', 'updated_at' => '2026-11-08T10:00:00+00:00'], $payload['notes'][0]);
        self::assertSame(2, $payload['notes'][1]['id']);
        self::assertSame('ItemMap', $payload['kit'][0]['classes'][0]);
        self::assertSame('2026-11-08T10:00:00+00:00', $payload['updated_at']);

        self::assertSame(['notes' => [], 'kit' => [], 'updated_at' => ''], AtakCommunityBrief::payload([], ''));
    }

    public function testSaisieGardeeDansLExperienceEtAbsenteDuGuide(): void
    {
        $svc = new AtakExperienceService();
        $merged = $svc->mergeSettings([], ['community_notes' => " ordre | A | B \n", 'community_kit' => str_repeat('x', AtakCommunityBrief::MAX_SOURCE + 50)]);

        self::assertSame('ordre | A | B', $merged['community_notes']);
        self::assertSame(AtakCommunityBrief::MAX_SOURCE, mb_strlen($merged['community_kit']));

        $kept = $svc->mergeSettings($merged, ['realism' => true]);
        self::assertSame('ordre | A | B', $kept['community_notes']);
    }

    public function testCablageSiteDllEtMod(): void
    {
        self::assertStringContainsString("get('/api/atak/community-brief', [AtakApiController::class, 'communityBrief'])", self::read('routes/web.php'));
        self::assertStringContainsString('function communityBrief(', self::read('app/Controllers/Api/AtakApiController.php'));
        self::assertStringContainsString('name="note_body"', self::read('views/admin/atak-brief/_live.php'));
        self::assertStringContainsString('name="class_manual"', self::read('views/admin/atak-brief/_live.php'));

        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('if (function == "GetCommunityBrief")', $ext);
        self::assertStringContainsString('/api/atak/community-brief', $ext);
        self::assertMatchesRegularExpression('/\|[^"]*GetCommunityBrief,[^"]*"/', $ext);

        $mod = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/fn_tenantAction.sqf');
        self::assertStringContainsString('"GetCommunityBrief"', $mod);
        self::assertStringContainsString('COMSPEC_ATAK_TenantBrief', $mod);
    }
}
