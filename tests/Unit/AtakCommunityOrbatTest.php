<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\AtakCommunityOrbatService;
use App\Services\Organization\OrgVisibilityService;
use App\Support\OrgVisibilityCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * ORBAT du site dans le BFT : arbre du site → GET /api/atak/community-orbat → GetCommunityOrbat (DLL) → fn_bftOrbat → fn_pageBft.
 */
final class AtakCommunityOrbatTest extends TestCase
{
    private const SALT = 'a1b2c3d4e5f60718';
    private const STEAM = '76561198000000001';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /**
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private static function unit(int $id, array $over = []): array
    {
        return $over + ['id' => 'unit-' . $id, 'unitId' => $id, 'label' => 'Unité ' . $id, 'unitCode' => 'U' . $id, 'echelon' => '', 'members' => [], 'children' => [], 'showOnPublicPage' => true];
    }

    /** @return array<int, array{name: string, callsign: string, steam: string}> */
    private static function identities(): array
    {
        return [
            7 => ['name' => 'Jean D.', 'callsign' => 'Alpha 1', 'steam' => self::STEAM],
            8 => ['name' => '', 'callsign' => 'Bravo 2', 'steam' => ''],
            9 => ['name' => 'Paul M.', 'callsign' => '', 'steam' => ''],
        ];
    }

    /**
     * @param array<string, mixed> $root
     * @return array<string, mixed>
     */
    private static function payload(array $root): array
    {
        return AtakCommunityOrbatService::fromRoster($root, self::identities(), self::SALT, '2026-11-08T18:00:00+00:00');
    }

    public function testArbreAPlatAvecPostesChefsEtMembres(): void
    {
        $root = self::unit(1, [
            'commanderUserId' => 9,
            'commanderGrade' => 'CNE',
            'billets' => [[
                'title' => 'Chef de groupe', 'org_callsign' => 'Alpha', 'key_post_kind' => 'deputy', 'vacant' => 1,
                'holders' => [['user_id' => 7, 'label' => 'ne pas reprendre', 'grade' => 'SGT', 'trigram' => 'JDU', 'id' => 44, 'starts_at' => '2020-01-01']],
            ]],
            'members' => [['user_id' => 7, 'label' => 'Jean', 'grade' => 'SGT'], ['user_id' => 8, 'label' => 'Bravo', 'grade' => 'CPL', 'readiness' => 50]],
            'children' => [self::unit(2)],
        ]);
        $p = self::payload($root);

        self::assertSame(AtakCommunityOrbatService::VERSION, $p['version']);
        self::assertFalse($p['truncated']);
        self::assertSame(self::SALT, $p['tag_salt']);
        self::assertSame([1, 2], array_column($p['units'], 'id'));
        self::assertSame([0, 1], array_column($p['units'], 'parent'));

        $u = $p['units'][0];
        self::assertSame('Chef d’unité', $u['posts'][0]['title']);
        self::assertSame('CNE', $u['posts'][0]['holders'][0]['grade']);
        self::assertSame('deputy', $u['posts'][1]['key']);
        self::assertSame(1, $u['posts'][1]['vacant']);
        self::assertSame('Jean D.', $u['posts'][1]['holders'][0]['name']);
        self::assertSame('Alpha 1', $u['posts'][1]['holders'][0]['callsign']);
        self::assertSame('SGT', $u['posts'][1]['holders'][0]['grade']);
        // Le titulaire n'est pas répété parmi les membres.
        self::assertSame([8], array_column($u['members'], 'user'));
        self::assertSame('Bravo 2', $u['members'][0]['callsign']);
    }

    public function testSymboleDeLUniteRepriParLeTelephone(): void
    {
        $root = self::unit(1, [
            'echelon' => 'section', 'symbolFunction' => 'medical', 'sidc' => 'SFGPUSM---A----',
            'children' => [self::unit(2, ['isAnonymizedUnit' => true, 'echelon' => 'groupe', 'symbolFunction' => 'infantry', 'sidc' => 'SFGPUCI---A----'])],
        ]);
        $units = self::payload($root)['units'];

        self::assertSame(['section', ''], array_column($units, 'echelon'));
        self::assertSame(['medical', ''], array_column($units, 'function'));
        self::assertSame([true, false], array_column($units, 'hq'));
    }

    public function testAucunEmailNiSteamNiChampInterne(): void
    {
        $root = self::unit(1, [
            'label' => 'contact@exemple.fr',
            'billets' => [['title' => 'Poste', 'holders' => [['user_id' => 0, 'label' => 'fuite@exemple.fr'], ['user_id' => 7, 'label' => 'autre@exemple.fr']]]],
            'members' => [['user_id' => 8, 'label' => 'membre@exemple.fr', 'readinessComponents' => ['medical' => 3], 'email' => 'x@y.fr']],
        ]);
        $json = (string) json_encode(self::payload($root), JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('@', $json);
        self::assertStringNotContainsString(self::STEAM, $json);
        foreach (['readiness', 'starts_at', 'email', 'steam', 'medical'] as $field) {
            self::assertStringNotContainsString('"' . $field, $json);
        }
    }

    public function testEmpreinteSteamEtAucuneSansSteamFiable(): void
    {
        $p = self::payload(self::unit(1, ['members' => [['user_id' => 7], ['user_id' => 8]]]));

        self::assertSame(AtakCommunityOrbatService::steamTag(self::SALT, self::STEAM), $p['units'][0]['members'][0]['tag']);
        self::assertSame(substr(hash('sha256', 'orbat|' . self::SALT . '|' . self::STEAM), 0, 24), $p['units'][0]['members'][0]['tag']);
        self::assertSame('', $p['units'][0]['members'][1]['tag']);

        $reliable = AtakCommunityOrbatService::reliableSteam(
            [1 => self::STEAM, 2 => self::STEAM, 3 => 'pas un steam', 4 => '', 5 => '', 6 => '76561198000000006'],
            [
                ['user_id' => 4, 'steam_id' => '76561198000000004'],
                ['user_id' => 5, 'steam_id' => '76561198000000005'],
                ['user_id' => 5, 'steam_id' => '76561198000000050'],
                ['user_id' => 6, 'steam_id' => '76561198000000060'],
            ]
        );
        // Steam partagé (1, 2) et deux Steam de session (5) : personne ; le Steam du compte prime sur la session (6).
        ksort($reliable);
        self::assertSame([4 => '76561198000000004', 6 => '76561198000000006'], $reliable);
    }

    public function testUniteNonPublieeEtBrancheMasqueeAbsentes(): void
    {
        $root = self::unit(1, ['children' => [
            self::unit(2, ['showOnPublicPage' => false, 'children' => [self::unit(3, ['members' => [['user_id' => 7]]])]]),
            ['id' => 'masked-x', 'unitId' => 0, 'label' => 'Unité masquée', 'isOrbatPlaceholder' => true, 'children' => []],
            self::unit(4),
        ]]);

        self::assertSame(['Unité 1', 'Unité 4'], array_column(self::payload($root)['units'], 'name'));
    }

    public function testConfidentialiteDuSiteConserveePourUnSimpleMembre(): void
    {
        $caps = new OrgVisibilityCapabilities();
        $tree = self::unit(1, ['visibilityLevel' => 'normal', 'children' => [
            self::unit(2, ['visibilityLevel' => 'hidden', 'members' => [['user_id' => 7, 'label' => 'Jean']]]),
            self::unit(3, ['visibilityLevel' => 'anonymized', 'unitCode' => 'SECRET', 'members' => [['user_id' => 7, 'label' => 'Jean', 'anonymized_label' => 'Opérateur']]]),
            self::unit(4, ['visibilityLevel' => 'restricted', 'members' => [['user_id' => 8, 'label' => 'Bravo']]]),
        ]]);
        $root = OrgVisibilityService::applyEffectiveVisibility($tree, $caps);
        self::assertIsArray($root);
        $p = self::payload($root);
        $json = (string) json_encode($p, JSON_UNESCAPED_UNICODE);

        self::assertCount(3, $p['units']);
        self::assertStringNotContainsString('Unité 2', $json);
        self::assertStringNotContainsString('SECRET', $json);
        self::assertStringNotContainsString('Jean', $json);
        self::assertStringNotContainsString('Alpha 1', $json);
        self::assertStringNotContainsString('Bravo', $json);

        foreach ([$p['units'][1], $p['units'][2]] as $u) {
            self::assertTrue($u['restricted']);
            $m = $u['members'][0];
            self::assertTrue($m['anonymized']);
            self::assertSame(0, $m['user']);
            self::assertSame('', $m['tag']);
        }
    }

    public function testTitulaireAnonymiseUneSeuleFois(): void
    {
        $root = self::unit(1, [
            'billets' => [['title' => 'Poste', 'holders' => [['user_id' => 0, 'label' => 'Identité protégée']]]],
            'members' => [['user_id' => 0, 'label' => 'Identité protégée'], ['user_id' => 0, 'label' => 'Identité protégée']],
        ]);
        $u = self::payload($root)['units'][0];

        self::assertCount(1, $u['posts'][0]['holders']);
        self::assertCount(1, $u['members']);
    }

    public function testPlafondsEtTailleBornee(): void
    {
        $children = [];
        for ($i = 2; $i <= AtakCommunityOrbatService::MAX_UNITS + 20; ++$i) {
            $children[] = self::unit($i, ['label' => str_repeat('é', 500)]);
        }
        $p = self::payload(self::unit(1, ['children' => $children]));
        self::assertTrue($p['truncated']);
        self::assertCount(AtakCommunityOrbatService::MAX_UNITS, $p['units']);
        self::assertSame(AtakCommunityOrbatService::MAX_TEXT, mb_strlen($p['units'][1]['name']));

        $people = [];
        for ($i = 1; $i <= AtakCommunityOrbatService::MAX_PEOPLE + 5; ++$i) {
            $people[] = ['user_id' => 1000 + $i];
        }
        $q = self::payload(self::unit(1, ['members' => $people]));
        self::assertTrue($q['truncated']);
        self::assertCount(AtakCommunityOrbatService::MAX_PEOPLE, $q['units'][0]['members']);
    }

    public function testSansOrbatEtRacineSynthetique(): void
    {
        $empty = AtakCommunityOrbatService::fromRoster(null, [], self::SALT, 't');
        self::assertSame([], $empty['units']);
        self::assertFalse($empty['truncated']);

        $p = self::payload(['id' => 'command', 'unitId' => 0, 'label' => 'Command', 'children' => [self::unit(1), self::unit(2)]]);
        self::assertSame(['Unité 1', 'Unité 2'], array_column($p['units'], 'name'));
        self::assertSame([0, 0], array_column($p['units'], 'parent'));
    }

    public function testRevisionStableSaufSiLeContenuChange(): void
    {
        $a = AtakCommunityOrbatService::fromRoster(self::unit(1), [], self::SALT, 'instant A');
        $b = AtakCommunityOrbatService::fromRoster(self::unit(1), [], self::SALT, 'instant B');
        $c = AtakCommunityOrbatService::fromRoster(self::unit(1, ['label' => 'Autre']), [], self::SALT, 'instant B');

        self::assertSame($a['revision'], $b['revision']);
        self::assertNotSame($a['revision'], $c['revision']);
    }

    public function testCablageSiteEtSessionExigee(): void
    {
        self::assertStringContainsString("get('/api/atak/community-orbat', [AtakApiController::class, 'communityOrbat'])", self::read('routes/web.php'));
        $ctrl = self::read('app/Controllers/Api/AtakApiController.php');
        $body = substr($ctrl, (int) strpos($ctrl, 'function communityOrbat('), 900);
        self::assertStringContainsString('requireTenant', $body);
        self::assertStringContainsString('matchedUserId', $body);
        self::assertStringContainsString("'session_required'", $body);
        self::assertStringContainsString('viewerMayRead', $body);
        self::assertStringNotContainsString('/api/atak/community-orbat', self::read('config/tactical_api.php'));
    }

    public function testCablageDllEtMod(): void
    {
        $ext = self::read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('function == "GetCommunityOrbat"', $ext);
        self::assertStringContainsString('function == "GetCommunityOrbatChunk"', $ext);
        self::assertMatchesRegularExpression('/\|[^"]*GetCommunityOrbat,GetCommunityOrbatChunk[^"]*"/', $ext);
        self::assertStringContainsString('Extension_CommunityOrbat.cs', self::read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj'));

        $dll = self::read('mod/UptoDate/COMSPECExtension/Extension_CommunityOrbat.cs');
        self::assertStringContainsString('/api/atak/community-orbat', $dll);
        self::assertStringContainsString('ERR|use_async', $dll);
        self::assertStringContainsString('ERR|busy', $dll);
        self::assertStringContainsString('ERR|not_linked', $dll);

        self::assertStringContainsString('"GetCommunityOrbatChunk"', self::read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extFast.sqf'));
        self::assertStringContainsString('class bftOrbat {};', self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp'));

        $mod = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_bftOrbat.sqf');
        self::assertStringContainsString('fnc_extAwait', $mod);
        // Même empreinte que le site, et jamais de rapprochement par le nom.
        self::assertStringContainsString('format ["orbat|%1|%2", _salt, _uid]', $mod);
        self::assertStringContainsString('_r select [3, 24]', $mod);
        self::assertStringNotContainsString('name _x', $mod);
        // Camp adverse : seuls les joueurs de mon camp sont rapprochés.
        self::assertStringContainsString('(side group _x) isEqualTo _side', $mod);
    }

    public function testActionsSelonEtatEtBoutons(): void
    {
        $bft = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_pageBft.sqf');
        // Carte et itinéraire : uniquement avec une piste. Sans piste, la fiche unique ne garde que SMS et vibreur.
        $card = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/ui/fn_unitCard.sqf');
        self::assertStringContainsString('if (_pSt isEqualTo "NOTRACK") then {', $bft);
        self::assertStringContainsString('_uCtx = "bftp";', $bft);
        self::assertStringContainsString('if !((_x select 0) in ["sms", "buzz"]) then { _x set [2, false]; };', $card);
        // Le vibreur suit le réseau et l'état hors ligne ; le texte des boutons reste au plancher de 14 px.
        self::assertStringContainsString('_net && {_dev} && {!_off}', $card);
        self::assertStringContainsString('((_l get "fontSmall") * 0.85) max COMSPEC_ATAK_FontMin', $card);
        self::assertStringNotContainsString('_rowAt', $bft);

        $act = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_bftAction.sqf');
        self::assertStringContainsString('_act in ["sms", "buzz", "buzzSend", "buzzCancel"]', $act);
    }
}
