<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\OrganizationMemberNumberController;
use App\Services\Personnel\TenantMemberNumberService;
use App\Support\MemberNumberAnalysis;
use PHPUnit\Framework\TestCase;

/**
 * Registre des matricules : reconnaissance du modèle (identique à la génération), doublons,
 * trous de séquence, séquence en retard, export réimportable.
 */
final class MemberNumberAnalysisTest extends TestCase
{
    /** format() n’utilise aucune dépendance : pas besoin de base. */
    private function service(): TenantMemberNumberService
    {
        return (new \ReflectionClass(TenantMemberNumberService::class))->newInstanceWithoutConstructor();
    }

    /** @return list<array{string, string, string|null}> */
    public static function patterns(): array
    {
        return [
            ['{PREFIX}-{NUMBER:4}', 'GEND', null],
            ['{PREFIX}-{NUMBER:4}', '', null],
            ['{PREFIX}/{YEAR}/{NUMBER}', 'OPS', null],
            ['{TENANT}-{YEAR:2}-{MONTH}-{NUMBER:3}', '', 'ALPHA'],
            ['{UNIT}-{GRADE}-{NUMBER:5}', 'X', null],
            ['A.{NUMBER}+B', 'p', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('patterns')]
    public function testRegexRecognisesEveryGeneratedValue(string $pattern, string $prefix, ?string $tenant): void
    {
        $svc = $this->service();
        $regex = MemberNumberAnalysis::patternRegex($pattern, $prefix, $tenant);
        foreach ([1, 7, 42, 1234, 99999] as $n) {
            $value = $svc->format($pattern, $prefix, $n, $tenant);
            self::assertSame(1, preg_match($regex, $value, $m), $pattern . ' → ' . $value);
            self::assertSame($n, (int) $m['seq'], $value);
        }
    }

    public function testAnalyzeFindsDuplicatesGapsOffFormatAndLateSequence(): void
    {
        $members = [
            ['id' => 1, 'tenant_member_number' => 'GEND-0001'],
            ['id' => 2, 'tenant_member_number' => 'gend-0002'],
            ['id' => 3, 'tenant_member_number' => 'GEND-0002'],
            ['id' => 4, 'tenant_member_number' => 'GEND-0007'],
            ['id' => 5, 'tenant_member_number' => 'ANCIEN-12'],
            ['id' => 6, 'tenant_member_number' => ''],
            ['id' => 7, 'tenant_member_number' => null],
        ];

        $a = MemberNumberAnalysis::analyze($members, '{PREFIX}-{NUMBER:4}', 'GEND', null, 5);

        self::assertSame(7, $a['total']);
        self::assertSame(5, $a['assigned']);
        self::assertSame(2, $a['missing']);
        self::assertSame(['gend-0002' => [2, 3]], $a['duplicates']);
        self::assertSame(2, $a['duplicate_members']);
        self::assertSame([5], $a['off_format']);
        self::assertSame(7, $a['max_sequence']);
        self::assertSame([3, 4, 5, 6], $a['gaps']);
        self::assertSame(4, $a['gap_count']);
        self::assertTrue($a['sequence_behind']);
        self::assertSame(8, $a['suggested_next']);
        self::assertSame(['3 à 6'], MemberNumberAnalysis::ranges($a['gaps']));
    }

    public function testHugeTypedNumberDoesNotWalkTheWholeSequence(): void
    {
        $start = microtime(true);
        $a = MemberNumberAnalysis::analyze([
            ['id' => 1, 'tenant_member_number' => '99999999'],
            ['id' => 2, 'tenant_member_number' => '00000002'],
            ['id' => 3, 'tenant_member_number' => '00000000'],
        ], '{NUMBER:8}', '', null, 1);

        self::assertLessThan(1.0, microtime(true) - $start);
        self::assertSame(99999999 - 2, $a['gap_count']);
        self::assertSame(MemberNumberAnalysis::GAP_LIST_LIMIT, count($a['gaps']));
        self::assertSame(1, $a['gaps'][0]);
        self::assertSame(3, $a['gaps'][1]);
        self::assertSame(100000000, $a['suggested_next']);
    }

    public function testCleanRegistryAndPatternWithoutNumber(): void
    {
        $ok = MemberNumberAnalysis::analyze([['id' => 1, 'tenant_member_number' => 'X-0001']], '{PREFIX}-{NUMBER:4}', 'X', null, 2);
        self::assertSame([], $ok['duplicates']);
        self::assertSame([], $ok['gaps']);
        self::assertFalse($ok['sequence_behind']);
        self::assertSame(2, $ok['suggested_next']);

        $free = MemberNumberAnalysis::analyze([['id' => 1, 'tenant_member_number' => 'ABC']], '{PREFIX}', 'ABC', null, 1);
        self::assertFalse($free['format_checked']);
        self::assertSame(0, $free['max_sequence']);
        self::assertSame(['1', '3 à 4', '9'], MemberNumberAnalysis::ranges([9, 3, 1, 4]));
    }

    public function testSaveRefusesGeneratedModeWithoutNumber(): void
    {
        // Dépôts sans base : le refus doit intervenir avant toute écriture (aucune connexion n’est fournie).
        $configClass = new \ReflectionClass(\App\Repositories\TenantMemberNumberConfigRepository::class);
        $ready = $configClass->getProperty('schemaReady');
        $userClass = new \ReflectionClass(\App\Repositories\UserRepository::class);
        $column = $userClass->getProperty('hasTenantMemberNumberColumn');
        $previous = [$ready->getValue(), $column->getValue()];
        $ready->setValue(null, true);
        $column->setValue(null, true);
        $svc = new TenantMemberNumberService(
            $configClass->newInstanceWithoutConstructor(),
            $userClass->newInstanceWithoutConstructor(),
            (new \ReflectionClass(\App\Repositories\TenantRepository::class))->newInstanceWithoutConstructor(),
            null
        );
        try {
            $r = $svc->saveConfig(3, ['mode' => 'automatic', 'pattern' => '{PREFIX}-{YEAR}', 'enabled' => true]);
        } finally {
            $ready->setValue(null, $previous[0]);
            $column->setValue(null, $previous[1]);
        }

        self::assertFalse($r['ok']);
        self::assertStringContainsString('numéro', $r['error']);
    }

    public function testPageRendersRegistryChecksAndActions(): void
    {
        $members = [
            ['id' => 1, 'display_name' => 'Alpha <b>', 'callsign' => 'A1', 'email' => 'a@x.fr', 'athena_identifier' => 'ATH1', 'tenant_member_number' => 'G-0001'],
            ['id' => 2, 'display_name' => 'Bravo', 'callsign' => '', 'email' => 'b@x.fr', 'athena_identifier' => '', 'tenant_member_number' => 'G-0001'],
            ['id' => 3, 'display_name' => '', 'callsign' => '', 'email' => 'c@x.fr', 'athena_identifier' => '', 'tenant_member_number' => ''],
            ['id' => 4, 'display_name' => 'Delta', 'callsign' => '', 'email' => 'd@x.fr', 'athena_identifier' => '', 'tenant_member_number' => 'G-0009'],
        ];
        $vars = [
            'memberNumberSchemaReady' => true,
            'memberNumberConfig' => ['enabled' => 1, 'mode' => 'assisted', 'pattern' => '{PREFIX}-{NUMBER:4}', 'prefix' => 'G', 'next_sequence' => 3, 'label' => 'Matricule', 'unique_required' => 1, 'required' => 1],
            'memberNumberPreview' => 'G-0003',
            'memberNumberModes' => TenantMemberNumberService::MODES,
            'memberNumberCsrf' => 'tok',
            'memberNumberMembers' => $members,
            'memberNumberAnalysis' => MemberNumberAnalysis::analyze($members, '{PREFIX}-{NUMBER:4}', 'G', null, 3),
            'memberNumberAudit' => [['user_id' => 2, 'member_name' => 'Bravo', 'old_value' => null, 'new_value' => 'G-0001', 'source' => 'import', 'created_at' => '2026-10-01 10:00:00', 'actor_name' => 'Chef']],
            'memberNumberTenantToken' => 'ALPHA',
        ];
        $html = (static function (array $vars): string {
            extract($vars);
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/organization/member_numbers.php';

            return (string) ob_get_clean();
        })($vars);

        self::assertStringContainsString('Alpha &lt;b&gt;', $html);
        self::assertStringNotContainsString('Alpha <b>', $html);
        self::assertStringContainsString('1 matricule(s) attribué(s) à plusieurs membres', $html);
        self::assertStringContainsString('La prochaine séquence est en retard', $html);
        self::assertStringContainsString('Reprendre à 10', $html);
        self::assertStringContainsString('2 à 8', $html);
        self::assertStringContainsString('Attribuer à tous les membres sans matricule (1)', $html);
        self::assertStringContainsString('matricules/export', $html);
        self::assertStringContainsString('data-state="dup"', $html);
        self::assertStringContainsString('data-state="sans"', $html);
        self::assertStringContainsString('Import · par Chef', $html);
    }

    public function testExportIsReimportableAndNeutralisesFormulas(): void
    {
        $csv = OrganizationMemberNumberController::registryCsv([
            ['id' => 12, 'display_name' => 'Alpha', 'callsign' => '=CMD()', 'email' => 'a@x.fr', 'tenant_member_number' => 'G-0001', 'athena_identifier' => 'ATH1'],
            ['id' => 13, 'display_name' => 'Bravo; "B"', 'callsign' => '', 'email' => 'b@x.fr', 'tenant_member_number' => '', 'athena_identifier' => ''],
        ]);

        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = preg_split('/\R/', trim(substr($csv, 3)));
        self::assertSame('nom;indicatif;id_membre;tenant_member_number;matricule_plateforme', $lines[0]);
        self::assertSame(['Alpha', "'=CMD()", '12', 'G-0001', 'ATH1'], str_getcsv($lines[1], ';', '"', '\\'));
        self::assertSame(['Bravo; "B"', '', '13', '', ''], str_getcsv($lines[2], ';', '"', '\\'));
    }
}
