<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AirBriefService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Reprise dans l'app JTAC du téléphone des 9-LINE et 5-LINE rédigés par le même JTAC sur le TOC web, la version mobile ou en
 * jeu (GET /api/cas?assignedTo=~jtac|…, fonction GetCASForCallsign de la DLL inchangée) : sélection par indicatif d'auteur ou
 * par l'indicatif JTAC enregistré du membre, carte et communauté courantes, MEDEVAC exclus, plus récents d'abord, source,
 * statut le plus avancé des destinataires, tableau SQF à dix colonnes (la boîte du pilote garde ses neuf colonnes).
 * Dépôt réel sur sqlite.
 */
final class AtakJtacTocImportTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE atak_nine_line (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1,
            mission_kind TEXT NOT NULL DEFAULT 'cas', mission_id TEXT, author TEXT NOT NULL, author_user_id INTEGER, assigned_aircraft TEXT,
            line1 TEXT, line2 TEXT, line3 TEXT, line4 TEXT, line5 TEXT, line6 TEXT, line7 TEXT, line8 TEXT, line9 TEXT, remarks TEXT,
            lines_checked TEXT, geo_json TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT)");
        $this->pdo->exec("CREATE TABLE atak_nine_line_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, nine_line_id INTEGER NOT NULL,
            callsign TEXT NOT NULL, label TEXT, crew_names TEXT, status TEXT NOT NULL DEFAULT 'sent', received_at TEXT, read_at TEXT, ack_at TEXT,
            ack_by TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT, UNIQUE (nine_line_id, callsign))");
        $this->pdo->exec('CREATE TABLE atak_brief_callsigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, scope TEXT NOT NULL,
            owner_key TEXT NOT NULL, callsign TEXT NOT NULL, updated_by_user_id INTEGER, updated_at TEXT, UNIQUE (tenant_id, scope, owner_key))');
    }

    /** @param array<string, mixed> $over */
    private function row(array $over): int
    {
        $row = array_merge([
            'tenant_id' => 7, 'map_id' => 1, 'mission_kind' => 'cas', 'mission_id' => null, 'author' => 'HAMMER 11', 'author_user_id' => null,
            'assigned_aircraft' => 'HOG 21', 'line1' => 'IP BRAVO', 'line2' => '245° M', 'line3' => '4.2 NM', 'line4' => '450 ft MSL', 'line5' => '2 BTR',
            'line6' => '04512 07788', 'line7' => 'LASER 1688', 'line8' => 'SUD 600 m', 'line9' => 'NORD', 'remarks' => 'FAH 200',
            'created_at' => date('Y-m-d H:i:s', time() - 600),
        ], $over);
        $cols = array_keys($row);
        $this->pdo->prepare('INSERT INTO atak_nine_line (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    public function testOnlyMyBriefsOfThisMapAndCommunityComeBackNewestFirst(): void
    {
        $web = $this->row(['author_user_id' => 5, 'created_at' => date('Y-m-d H:i:s', time() - 900)]);
        $game = $this->row(['mission_id' => 'CAS-12345678-99001', 'created_at' => date('Y-m-d H:i:s', time() - 300)]);
        $five = $this->row(['mission_kind' => '5line', 'line1' => 'HAMMER 11, 5-LINE', 'line6' => null, 'created_at' => date('Y-m-d H:i:s', time() - 200)]);
        $this->row(['mission_kind' => 'medevac']);
        $this->row(['author' => 'WARHAWK 21']);
        $this->row(['map_id' => 2]);
        $this->row(['tenant_id' => 3]);
        $this->row(['created_at' => date('Y-m-d H:i:s', time() - 13 * 3600)]);
        // Rédigé sur le site sous un autre libellé, par le membre dont l'indicatif JTAC est SABRE 1.
        $this->pdo->exec("INSERT INTO atak_brief_callsigns (tenant_id, scope, owner_key, callsign) VALUES (7, 'jtac', '9', 'SABRE 1')");
        $mine = $this->row(['author' => 'Cpt Martin', 'author_user_id' => 9, 'created_at' => date('Y-m-d H:i:s', time() - 100)]);
        $this->pdo->exec("INSERT INTO atak_nine_line_recipients (tenant_id, nine_line_id, callsign, status) VALUES (7, {$web}, 'HOG 21', 'read'), (7, {$web}, 'VIPER 11', 'wilco')");

        $svc = new AirBriefService($this->pdo);
        $items = $svc->authored(7, 1, ['Hammer  11', 'SABRE 1', 'Operateur']);
        self::assertSame([$mine, $five, $game, $web], array_column($items, 'id'), 'les plus récents d’abord, MEDEVAC, autres auteurs, cartes et communautés exclus');
        $byId = array_column($items, null, 'id');
        self::assertSame('web', $byId[$web]['source']);
        self::assertSame('jeu', $byId[$game]['source']);
        self::assertSame('CAS-12345678-99001', $byId[$game]['ref']);
        self::assertSame('wilco', $byId[$web]['status'], 'statut le plus avancé des destinataires');
        self::assertSame('sent', $byId[$game]['status']);
        self::assertSame('5line', $byId[$five]['format']);
        self::assertCount(5, $byId[$five]['lines']);
        self::assertCount(9, $byId[$web]['lines']);
        self::assertSame('HOG 21', $byId[$web]['recipient']);
        self::assertSame([], $svc->authored(7, 1, ['Operateur', 'JTAC', '']), 'indicatifs génériques ignorés');
        self::assertCount(5, $svc->authored(7, 0, ['HAMMER 11', 'SABRE 1']), 'carte 0 : toutes les cartes');
    }

    public function testAuthoredListIsATenColumnSqfArrayAndThePilotInboxKeepsNine(): void
    {
        $this->row(['author_user_id' => 5, 'line5' => 'Toit "rouge" | coin']);
        $sqf = AirBriefService::inboxSqf((new AirBriefService($this->pdo))->authored(7, 1, ['HAMMER 11']));
        self::assertStringStartsWith('[[', $sqf);
        self::assertStringEndsWith(',"web"]]', $sqf);
        self::assertStringContainsString('"Toit ""rouge"" coin"', $sqf, 'guillemets doublés, séparateur de la DLL retiré');
        $pilot = AirBriefService::inboxSqf([['ref' => 'R', 'id' => 1, 'format' => '9line', 'from' => 'J', 'time' => '12:00', 'lines' => ['a'], 'remarks' => '', 'status' => 'sent', 'recipient' => 'HOG 21']]);
        self::assertSame('[["R",1,"9line","J","12:00",["a"],"","sent","HOG 21"]]', $pilot);
    }

    public function testCasIndexAndPhoneAreWiredWithoutNewDllFunction(): void
    {
        $root = dirname(__DIR__, 2);
        $api = str_replace("\r\n", "\n", (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php'));
        self::assertTrue(str_contains($api, 'str_starts_with($assignedTo, \App\Services\Tactical\AirBriefService::AUTHORED_PREFIX)'));
        self::assertTrue(str_contains($api, '->authored($tenantId, (int) $mapId, $names)'));
        self::assertSame('~jtac|', AirBriefService::AUTHORED_PREFIX);
        $sqf = str_replace("\r\n", "\n", (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/fn_jtacTocImport.sqf'));
        self::assertTrue(str_contains($sqf, '["COMSPECExtension", "GetCASForCallsign", [_q, _mapId]] call comspec_atak_native_fnc_extAwait'));
        self::assertTrue(str_contains($sqf, 'format ["~jtac|%1", _names joinString "|"]'));
        self::assertTrue(str_contains($sqf, 'IMPORTÉE DU TOC'));
        self::assertTrue(str_contains($sqf, 'RÉCUPÉRER DEPUIS LE TOC / MOBILE'));
        self::assertTrue(str_contains($sqf, '"tocConfirm"'), 'mise à jour depuis le serveur : second appui si la fiche diffère');
        $page = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/fn_pageJtac.sqf');
        self::assertTrue(str_contains($page, '_rows append (["rows"] call comspec_atak_native_fnc_jtacTocImport);'));
    }
}
