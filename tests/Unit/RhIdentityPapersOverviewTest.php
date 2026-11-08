<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\Effectifs\RhIdentityPapersOverview;
use PHPUnit\Framework\TestCase;

/**
 * Documents RH > Documents d'identité : papiers FICTIFS de COMSPEC Identity. Un infiltré ne doit pas être démasqué
 * par une consultation RH. Données fictives uniquement.
 */
final class RhIdentityPapersOverviewTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return [
            ['uid' => 1, 'display_name' => 'Membre Un', 'callsign' => '', 'doc_id' => 11, 'doc_type' => 'passport', 'workflow' => 'issued', 'st_validity' => 'valid', 'st_report' => 'none', 'st_origin' => 'official', 'expires_on' => '2099-01-01', 'identity_kind' => 'civil'],
            ['uid' => 1, 'display_name' => 'Membre Un', 'callsign' => '', 'doc_id' => 12, 'doc_type' => 'id_card', 'workflow' => 'issued', 'st_validity' => 'valid', 'st_report' => 'wanted', 'st_origin' => 'forged', 'expires_on' => '2099-01-01', 'identity_kind' => 'cover'],
            ['uid' => 2, 'display_name' => 'Membre Deux', 'callsign' => '', 'doc_id' => 13, 'doc_type' => 'id_card', 'workflow' => 'issued', 'st_validity' => 'expired', 'st_report' => 'lost', 'st_origin' => 'official', 'expires_on' => '2020-01-01', 'identity_kind' => 'civil'],
            ['uid' => 3, 'display_name' => 'Membre Trois', 'callsign' => '', 'doc_id' => 14, 'doc_type' => 'driving_license', 'workflow' => 'draft', 'st_validity' => 'valid', 'st_report' => 'none', 'st_origin' => 'cover', 'expires_on' => '', 'identity_kind' => 'alias'],
            ['uid' => 4, 'display_name' => 'Membre Quatre', 'callsign' => '', 'doc_id' => 15, 'doc_type' => 'id_card', 'workflow' => 'issued', 'st_validity' => 'valid', 'st_report' => 'none', 'st_origin' => 'generated', 'expires_on' => '', 'identity_kind' => 'civil'],
        ];
    }

    public function testWithoutClearanceCoversAliasesAndForgeriesAreNeitherShownNorCounted(): void
    {
        $out = RhIdentityPapersOverview::group($this->rows(), false);

        self::assertSame([1, 2], array_column($out['members'], 'id'), 'les membres qui n’ont que des papiers cachés n’apparaissent pas');
        self::assertSame(2, $out['summary']['members']);
        self::assertSame(2, $out['summary']['documents']);
        self::assertSame(0, $out['summary']['secret']);
        $first = $out['members'][0]['documents'];
        self::assertCount(1, $first, 'la couverture du membre un est invisible');
        self::assertSame('Passeport', $first[0]['type']);
        self::assertFalse($first[0]['secret']);
        $dump = json_encode($out, JSON_UNESCAPED_UNICODE);
        foreach (['Membre Trois', 'Membre Quatre', 'cover', 'forged', 'wanted', 'alias'] as $leak) {
            self::assertStringNotContainsString($leak, (string) $dump);
        }
    }

    public function testWithClearanceSecretPapersAreShownAndFlagged(): void
    {
        $out = RhIdentityPapersOverview::group($this->rows(), true);

        self::assertSame(4, $out['summary']['members']);
        self::assertSame(5, $out['summary']['documents']);
        self::assertSame(3, $out['summary']['secret']);
        $flags = [];
        foreach ($out['members'] as $m) {
            foreach ($m['documents'] as $d) {
                $flags[$d['id']] = $d['secret'];
            }
        }
        self::assertFalse($flags[11]);
        self::assertTrue($flags[12]);
        self::assertTrue($flags[14]);
    }

    public function testWatchAndWantedFlagsNeverReachThePage(): void
    {
        $rows = [['uid' => 1, 'display_name' => 'Membre Un', 'doc_id' => 1, 'doc_type' => 'passport', 'workflow' => 'issued', 'st_validity' => 'valid', 'st_report' => 'watch', 'st_origin' => 'official', 'expires_on' => '', 'identity_kind' => 'civil']];
        $doc = RhIdentityPapersOverview::group($rows, true)['members'][0]['documents'][0];
        self::assertSame('', $doc['report']);
        $rows[0]['st_report'] = 'lost';
        self::assertSame('Déclaré perdu', RhIdentityPapersOverview::group($rows, false)['members'][0]['documents'][0]['report']);
    }

    public function testMissingKindOrOriginIsTreatedAsConfidential(): void
    {
        $out = RhIdentityPapersOverview::group([['uid' => 1, 'display_name' => 'Membre Un', 'doc_id' => 1, 'doc_type' => 'passport']], false);
        self::assertSame([], $out['members']);
    }

    public function testExpiredAndSoonExpiringPapersAreToRenew(): void
    {
        $out = RhIdentityPapersOverview::group($this->rows(), false, true, strtotime('2026-06-01'));
        self::assertSame(1, $out['summary']['to_renew']);
        self::assertTrue($out['members'][1]['documents'][0]['to_renew']);
        self::assertSame('Expiré', $out['members'][1]['documents'][0]['validity']);
    }

    public function testTheQueryItselfExcludesSecretPapersAndStaysInTheCommunity(): void
    {
        $seen = [];
        $db = $this->createMock(Database::class);
        $db->method('fetchOne')->willReturn(['n' => 2]);
        $db->method('fetchAll')->willReturnCallback(function (string $sql, array $params = []) use (&$seen): array {
            $seen[] = [$sql, $params];

            return [];
        });

        (new RhIdentityPapersOverview($db))->forTenant(7, false);
        (new RhIdentityPapersOverview($db))->forTenant(7, true);

        self::assertCount(2, $seen);
        [$sqlPublic, $paramsPublic] = $seen[0];
        [$sqlTruth] = $seen[1];
        self::assertStringContainsString("i.kind = 'civil'", $sqlPublic);
        self::assertStringContainsString("d.st_origin = 'official'", $sqlPublic);
        self::assertStringNotContainsString("i.kind = 'civil'", $sqlTruth);
        self::assertSame(['t' => 7], $paramsPublic);
        self::assertStringContainsString('d.tenant_id = :t', $sqlPublic);
        self::assertStringContainsString('u.tenant_id = d.tenant_id', $sqlPublic);
        self::assertStringNotContainsString('email', strtolower($sqlPublic));
        self::assertStringNotContainsString('doc_number', $sqlPublic, 'aucun numéro de papier');
    }

    public function testTheViewNeverPrintsSecretsWithoutTheClearanceFlag(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/rh_identity_papers.php');
        self::assertStringContainsString('$truth && !empty($d[\'secret\'])', $view);
        self::assertStringContainsString('Ce ne sont pas de vraies pièces d’identité', $view);
        self::assertStringNotContainsString('doc_number', $view);
        self::assertStringContainsString("url('atak/sse/papiers/atelier/'", $view);
    }
}
