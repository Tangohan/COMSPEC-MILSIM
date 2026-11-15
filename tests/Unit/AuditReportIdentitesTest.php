<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tools/lib/audit-report-kit.php';
require_once __DIR__ . '/../../tools/audit-report-identites.php';

/** Rapport en lecture seule des membres sans prénom ni nom de personnage (identités affichées). */
final class AuditReportIdentitesTest extends TestCase
{
    /** @param list<array<string, mixed>> $members */
    private function db(array $members, bool $legal = true): \AuditReadOnlyDb
    {
        return new class ($members, $legal) extends \AuditReadOnlyDb {
            /** @var list<string> */
            public array $queries = [];

            /** @param list<array<string, mixed>> $members */
            public function __construct(private array $members, private bool $legal)
            {
            }

            public function hasTable(string $table): bool
            {
                return $table !== 'user_legal_identities' || $this->legal;
            }

            public function rows(string $sql, array $params = []): array
            {
                \AuditReadOnlyDb::assertReadOnlySql($sql);
                $this->queries[] = $sql;

                return $this->members;
            }
        };
    }

    public function testReportsMembersWhoseNameDoesNotComeFromFirstAndLastName(): void
    {
        $db = $this->db([
            ['user_id' => 1, 'tenant_id' => 1, 'display_name' => 'dami', 'callsign' => 'YB1', 'first_name' => 'Damian', 'last_name' => 'Stones', 'legal_first_name' => 'Damian', 'legal_last_name' => 'Stones'],
            ['user_id' => 2, 'tenant_id' => 1, 'display_name' => 'Ghost', 'callsign' => 'Reaper', 'first_name' => '', 'last_name' => '', 'legal_first_name' => null, 'legal_last_name' => null],
            ['user_id' => 3, 'tenant_id' => 1, 'display_name' => 'jean@example.org', 'callsign' => '', 'first_name' => null, 'last_name' => null, 'legal_first_name' => null, 'legal_last_name' => null],
            ['user_id' => 4, 'tenant_id' => 1, 'display_name' => 'Zed', 'callsign' => '', 'first_name' => 'Zed', 'last_name' => '', 'legal_first_name' => null, 'legal_last_name' => null],
            ['user_id' => 5, 'tenant_id' => 1, 'display_name' => 'Al', 'callsign' => '', 'first_name' => 'Al', 'last_name' => 'Pha', 'legal_first_name' => 'Alice', 'legal_last_name' => 'Real'],
        ]);
        $kit = new \AuditReportKit('audit-report-identites', 'test', 'test');
        $kit->capture = true;

        $code = audit_report_identites($kit, $db);

        self::assertSame(3, $code);
        $sections = array_column($kit->captured, null, 'title');
        $noCivil = $sections['Membres sans prénom ni nom (nom affiché probablement incomplet)']['rows'];
        self::assertSame([2, 3], array_column($noCivil, 'user_id'));
        self::assertSame('indicatif', $noCivil[0]['shown_from']);
        self::assertSame('« Membre » (rien d’autre)', $noCivil[1]['shown_from']);
        $partial = $sections['Membres avec un prénom ou un nom seulement']['rows'];
        self::assertSame([4], array_column($partial, 'user_id'));
        self::assertSame('nom manquant', $partial[0]['problem']);
        self::assertSame([3], array_column($sections['Nom du compte qui est une adresse e-mail']['rows'], 'user_id'));
        $divergent = $sections['Prénom/nom du personnage différents de l’identité légale']['rows'];
        self::assertSame([5], array_column($divergent, 'user_id'));
        self::assertStringNotContainsString('Alice', (string) json_encode($divergent));
    }

    public function testQueryIsReadOnlyAndHasNoLegalNameInTheReportColumns(): void
    {
        $db = $this->db([]);
        $kit = new \AuditReportKit('audit-report-identites', 'test', 'test');
        $kit->capture = true;
        self::assertSame(0, audit_report_identites($kit, $db));
        self::assertCount(1, $db->queries);
        self::assertStringStartsWith('SELECT', $db->queries[0]);
        $src = (string) file_get_contents(__DIR__ . '/../../tools/audit-report-identites.php');
        self::assertStringNotContainsString("'legal_first_name' =>", $src);
        try {
            \AuditReadOnlyDb::assertReadOnlySql('UPDATE user_profiles SET first_name = 1');
            self::assertTrue(false, 'Une écriture doit être refusée.');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
    }
}
