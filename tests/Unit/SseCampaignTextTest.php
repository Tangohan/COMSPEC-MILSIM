<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseCampaignService;
use App\Support\SqfArrayText;
use PHPUnit\Framework\TestCase;

final class SseCampaignTextTest extends TestCase
{
    /** Texte tel que le produit le module Zeus « Campagne : exporter » (str d'un paquet bioPack). */
    private const SAMPLE = '["COMSPEC_SSE_CAMPAIGN",1,["#M",[["id","ALTIS"],["name","Altis ""Nord"""],["type","permanente"],'
        . '["seed","ALTIS-4821"],["missions",3],["log",[["2026-10-12 20:00","op_faucon","Altis","fin de mission","1 organisation(s)"]]],'
        . '["roster",["#M",[["P12",["#M",[["name","Karim Haddad"],["alias","Le Comptable"],["status","vivant"]]]]]]],'
        . '["orgs",["#M",[["Réseau Faucon",["#M",[["id","Réseau Faucon"],["name","Réseau Faucon"],["kind","reseau"],["parent",""],["threat","elevee"],["known",1],["how","exploitation"]]]]]]],'
        . '["members",["#M",[["P12",["#M",[["key","P12"],["org","Réseau Faucon"],["role","financier"],["known",2],["how","identification biométrique"],["netId","2:31"]]]]]]],'
        . '["seen",["#M",[["P12",""]]]],["custody",["#M",[["P-ALTIS-0001",["#M",[["id","P-ALTIS-0001"],["seal","SC-000001"],["state","SCELLE"],["events",[["DTG","SAISIE","Cpl Martin","765","012345","tél.",123]]],["tampered",false]]]]]]],'
        . '["custodyN",1],["ratio",0.5]]]]';

    public function testRoundTripKeepsTheExactGameText(): void
    {
        $decoded = SqfArrayText::decode(self::SAMPLE);
        self::assertSame(self::SAMPLE, SqfArrayText::encode($decoded));
        self::assertSame('Altis "Nord"', SqfArrayText::unpack($decoded[2])['name']);
    }

    public function testPackAfterUnpackIsIdentical(): void
    {
        $decoded = SqfArrayText::decode(self::SAMPLE);
        self::assertSame($decoded[2], SqfArrayText::pack(SqfArrayText::unpack($decoded[2])));
    }

    public function testPresentReadsOrganisationsPeopleAndPieces(): void
    {
        $svc = new SseCampaignService();
        $d = $svc->present(['payload' => self::SAMPLE, 'seed' => '']);
        self::assertNull($d['error']);
        self::assertSame(1, $d['stats']['people']);
        self::assertSame(1, $d['stats']['known']);
        self::assertSame('Financier', $d['people'][0]['role_label']);
        self::assertSame('Karim Haddad', $d['orgs']['Réseau Faucon']['members'][0]['name']);
        self::assertSame('SC-000001', $d['pieces'][0]['seal']);
        self::assertSame($svc->personGlobalId('ALTIS-4821', 'P12'), $d['people'][0]['global_id']);
    }

    public function testWithoutDiscoveriesKeepsStructureOnly(): void
    {
        $svc = new SseCampaignService();
        $c = $svc->withoutDiscoveries($svc->parse(self::SAMPLE));
        $again = $svc->present(['payload' => $svc->toText($c)]);
        self::assertSame(0, $again['stats']['known']);
        self::assertSame(0, $again['stats']['pieces']);
        self::assertSame(1, $again['stats']['orgs']);
        self::assertSame('ALTIS-4821', $again['seed']);
    }

    public function testRejectsForeignText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SseCampaignService())->parse('["AUTRE",1,[]]');
    }
}
