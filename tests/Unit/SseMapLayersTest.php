<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\SseMapLayersRepository;
use App\Services\Sse\SseMapLayerGate;
use App\Services\Sse\SseMapLayersService;
use App\Support\ShortCache;
use App\Support\SseMapLayersGeo;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Calques SSE des cartes : API GeoJSON (contrôles, lieux, pistes), conversions, sécurité, états vides, interface.
 */
final class SseMapLayersTest extends TestCase
{
    private PDO $pdo;

    private ?PDO $previousPdo = null;

    /** @var list<array<string, mixed>> */
    private array $audited = [];

    protected function setUp(): void
    {
        ShortCache::setEnabled(false);
        $this->audited = [];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE sse_id_checks (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL DEFAULT 1, person_id INTEGER NULL, identity_id INTEGER NULL, verdict TEXT NULL, grid TEXT NULL, pos_x REAL NULL, pos_y REAL NULL, game_time TEXT NULL, created_at TEXT NOT NULL)',
            'CREATE TABLE sse_sites (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL DEFAULT 1, name TEXT NOT NULL DEFAULT \'\', site_type TEXT NOT NULL DEFAULT \'habitation\', status TEXT NOT NULL DEFAULT \'ouvert\', grid_reference TEXT NULL, pos_x REAL NULL, pos_y REAL NULL, created_at TEXT NULL)',
            'CREATE TABLE sse_phone_extractions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, ref TEXT NULL, device_label TEXT NULL, owner_label TEXT NULL, owner_kind TEXT NOT NULL DEFAULT \'player\', context_id INTEGER NULL, status TEXT NOT NULL DEFAULT \'visible\', created_at TEXT NOT NULL)',
            'CREATE TABLE sse_phone_extraction_items (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, extraction_id INTEGER NOT NULL, category TEXT NOT NULL, at TEXT NULL, grid TEXT NULL)',
            'CREATE TABLE sse_persons (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, first_name TEXT NULL, last_name TEXT NULL, alias TEXT NULL)',
            'CREATE TABLE sse_identities (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, kind TEXT NOT NULL DEFAULT \'civil\', first_name TEXT NOT NULL DEFAULT \'\', last_name TEXT NOT NULL DEFAULT \'\')',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $this->previousPdo = $prop->getValue();
        $prop->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        ShortCache::setEnabled(null);
    }

    /** Garde de test : chaque droit se règle séparément. */
    private function gate(int $tenant = 7, bool $controls = true, bool $places = true, bool $names = true, bool $digital = true): SseMapLayerGate
    {
        return new class($tenant, $controls, $places, $names, $digital) implements SseMapLayerGate {
            public function __construct(private int $tenant, private bool $controls, private bool $places, private bool $names, private bool $digital)
            {
            }

            public function tenantId(): int
            {
                return $this->tenant;
            }

            public function userId(): ?int
            {
                return $this->tenant > 0 ? 42 : null;
            }

            public function actorLabel(): string
            {
                return 'Analyste';
            }

            public function canReadControls(): bool
            {
                return $this->controls;
            }

            public function canReadPlaces(): bool
            {
                return $this->places;
            }

            public function canReadPersonFiles(): bool
            {
                return $this->names;
            }

            public function canReadDigitalExploitation(): bool
            {
                return $this->digital;
            }
        };
    }
    private function service(?\Closure $audit = null): SseMapLayersService
    {
        return new SseMapLayersService(new SseMapLayersRepository(), $audit ?? function (array $r): void {
            $this->audited[] = $r;
        });
    }

    /** @return array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}} */
    private function query(string $types = 'controles,lieux,pistes', array $extra = []): array
    {
        $parsed = SseMapLayersService::parseQuery($extra + ['types' => $types, 'carte' => '1', 'periode' => 'tout']);
        self::assertArrayHasKey('query', $parsed, (string) ($parsed['error'] ?? ''));

        return $parsed['query'];
    }

    private function seed(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $old = gmdate('Y-m-d H:i:s', time() - 40 * 86400);
        $this->pdo->exec("INSERT INTO sse_persons (id, tenant_id, first_name, last_name) VALUES (1, 7, 'Karim', 'Benali'), (2, 8, 'Autre', 'Communaute')");
        $this->pdo->exec("INSERT INTO sse_identities (id, tenant_id, kind, first_name, last_name) VALUES (1, 7, 'civil', 'Paul', 'Durand'), (2, 7, 'cover', 'Faux', 'Nom')");
        $this->pdo->exec(
            "INSERT INTO sse_id_checks (id, tenant_id, context_id, person_id, identity_id, verdict, grid, pos_x, pos_y, game_time, created_at) VALUES
             (1, 7, 1, 1, NULL, 'ras', '180097', 18050, 9750, '2035-06-12 10:20', '$now'),
             (2, 7, 1, NULL, 2, 'recherche', '181098', 18150, 9850, '2035-06-12 10:30', '$now'),
             (3, 7, 1, NULL, 1, 'ras', '182099', 18250, 9950, NULL, '$old'),
             (4, 7, 2, 1, NULL, 'ras', NULL, 5000, 5000, NULL, '$now'),
             (5, 8, 1, 2, NULL, 'ras', NULL, 6000, 6000, NULL, '$now'),
             (6, 7, 1, NULL, NULL, 'ras', NULL, 0, 0, NULL, '$now'),
             (7, 7, 1, NULL, NULL, 'ras', NULL, NULL, NULL, NULL, '$now')"
        );
        $this->pdo->exec(
            "INSERT INTO sse_sites (id, tenant_id, context_id, name, site_type, status, grid_reference, pos_x, pos_y, created_at) VALUES
             (1, 7, 1, 'Entrepot nord', 'depot', 'en_cours', '120130', 12050, 13050, '$now'),
             (2, 8, 1, 'Site autre communaute', 'depot', 'ouvert', NULL, 100, 100, '$now'),
             (3, 7, 1, 'Sans position', 'autre', 'ouvert', NULL, NULL, NULL, '$now')"
        );
        $this->pdo->exec(
            "INSERT INTO sse_phone_extractions (id, tenant_id, ref, device_label, owner_label, context_id, status, created_at) VALUES
             (1, 7, 'EXT-000001', 'Telephone A', 'Marc Lambert', 1, 'visible', '$now'),
             (2, 7, 'EXT-000002', 'Telephone B', 'Autre', 1, 'hidden', '$now'),
             (3, 7, 'EXT-000003', 'Telephone C', 'Sans carte', NULL, 'visible', '$now'),
             (4, 8, 'EXT-000004', 'Telephone D', 'Autre communaute', 1, 'visible', '$now'),
             (5, 7, 'EXT-000005', 'Telephone E', 'Sans position', 1, 'visible', '$now')"
        );
        // Piste 1 : cinq positions, la troisième est la même que la deuxième (doublon) ; une grille illisible.
        $rows = [
            [1, '08:00', '180097'], [1, '08:10', '181097'], [1, '08:11', '181097'], [1, '08:20', 'xx'], [1, '08:30', '182100'], [1, '08:40', '18000970'],
            [2, '09:00', '100100'], [2, '09:05', '101100'],
            [4, '09:00', '100100'], [4, '09:05', '101100'],
        ];
        foreach ($rows as [$e, $at, $g]) {
            $tenant = $e === 4 ? 8 : 7;
            $this->pdo->exec("INSERT INTO sse_phone_extraction_items (tenant_id, extraction_id, category, at, grid) VALUES ($tenant, $e, 'positions', '$at', '$g')");
        }
        $this->pdo->exec("INSERT INTO sse_phone_extraction_items (tenant_id, extraction_id, category, at, grid) VALUES (7, 5, 'contacts', NULL, NULL)");
        $this->pdo->exec("INSERT INTO sse_phone_extraction_items (tenant_id, extraction_id, category, at, grid) VALUES (7, 3, 'positions', '10:00', '300300'), (7, 3, 'positions', '10:10', '301300')");
    }

    /** @return list<array<string, mixed>> */
    private function ofType(array $payload, string $type): array
    {
        return array_values(array_filter($payload['features'], static fn (array $f): bool => ($f['properties']['type'] ?? '') === $type));
    }

    // ------------------------------------------------------------------ Paramètres et conversions

    public function testTypesAreValidatedAndOrderedCanonically(): void
    {
        $allowed = SseMapLayersService::TYPES;
        self::assertSame(['controles', 'pistes'], SseMapLayersGeo::parseTypes('pistes, controles,pistes', $allowed));
        self::assertSame([], SseMapLayersGeo::parseTypes('', $allowed));
        self::assertNull(SseMapLayersGeo::parseTypes('controles,secret', $allowed));
        self::assertNull(SseMapLayersGeo::parseTypes("controles'; DROP TABLE x", $allowed));
    }

    public function testBboxIsValidated(): void
    {
        self::assertNull(SseMapLayersGeo::parseBbox(''));
        self::assertSame([0.0, 100.0, 5000.0, 6000.5], SseMapLayersGeo::parseBbox('0,100,5000,6000.5'));
        self::assertFalse(SseMapLayersGeo::parseBbox('0,100,5000'));
        self::assertFalse(SseMapLayersGeo::parseBbox('5000,0,0,5000'), 'xmin > xmax');
        self::assertFalse(SseMapLayersGeo::parseBbox('a,b,c,d'));
        self::assertFalse(SseMapLayersGeo::parseBbox('0,0,999999999,10'), 'hors du monde');
        self::assertFalse(SseMapLayersGeo::parseBbox('0,0,1e9,10'));
        self::assertFalse(SseMapLayersGeo::parseBbox('0,0,10,10 OR 1=1'));
    }

    public function testWindowDefaultsAndDates(): void
    {
        $now = new \DateTimeImmutable('2026-11-15 12:00:00', new \DateTimeZone('UTC'));
        $w = SseMapLayersGeo::window('', '', '', $now);
        self::assertSame('30j', $w['period'] ?? null);
        self::assertSame('2026-10-16 12:00:00', $w['since'] ?? null);
        self::assertNull(SseMapLayersGeo::window('', '', 'tout', $now)['since']);
        self::assertSame('2026-11-14 12:00:00', SseMapLayersGeo::window('', '', '24h', $now)['since'] ?? null);
        self::assertNull(SseMapLayersGeo::window('', '', 'jamais', $now), 'période inconnue');
        $c = SseMapLayersGeo::window('2026-11-01', '2026-11-10', '', $now);
        self::assertSame(['since' => '2026-11-01 00:00:00', 'until' => '2026-11-10 23:59:59', 'period' => 'custom'], $c);
        self::assertSame('2026-11-14 11:00:00', SseMapLayersGeo::window('2026-11-14T12:00:00+01:00', '', '', $now)['since'] ?? null, 'fuseau converti en UTC');
        self::assertNull(SseMapLayersGeo::window('2026-13-40', '', '', $now));
        self::assertNull(SseMapLayersGeo::window('demain', '', '', $now));
        self::assertNull(SseMapLayersGeo::window('2026-11-10', '2026-11-01', '', $now), 'fin avant début');
        self::assertNull(SseMapLayersGeo::window('2020-01-01', '2026-11-01', '', $now), 'plus de 366 jours');
    }

    public function testQueryParsingRejectsBadInput(): void
    {
        self::assertSame('invalid_types', SseMapLayersService::parseQuery(['types' => 'x', 'carte' => '1'])['error'] ?? null);
        self::assertSame('invalid_map', SseMapLayersService::parseQuery(['types' => 'lieux'])['error'] ?? null);
        self::assertSame('invalid_map', SseMapLayersService::parseQuery(['types' => 'lieux', 'carte' => '1 OR 1=1'])['error'] ?? null);
        self::assertSame('invalid_map', SseMapLayersService::parseQuery(['types' => 'lieux', 'carte' => '-3'])['error'] ?? null);
        self::assertSame('invalid_bbox', SseMapLayersService::parseQuery(['types' => 'lieux', 'carte' => '1', 'bbox' => '1,2'])['error'] ?? null);
        self::assertSame('invalid_window', SseMapLayersService::parseQuery(['types' => 'lieux', 'carte' => '1', 'depuis' => 'x'])['error'] ?? null);
        self::assertSame([], SseMapLayersService::parseQuery(['types' => ['controles'], 'carte' => '1'])['query']['types'] ?? null, 'un tableau n’est jamais interprété comme une liste de types');
        self::assertArrayHasKey('query', SseMapLayersService::parseQuery(['types' => '']), 'sans type : sonde des droits, aucune carte requise');
    }

    public function testGridToWorldKnownCases(): void
    {
        // 6 chiffres : cases de 100 m, centre de case. « 180097 » : est 180, nord 097.
        self::assertSame([18050.0, 9750.0], SseMapLayersGeo::gridToWorld('180097'));
        // 8 chiffres : cases de 10 m. « 18000970 » : est 1800, nord 0970.
        self::assertSame([18005.0, 9705.0], SseMapLayersGeo::gridToWorld('18000970'));
        // 10 chiffres : cases de 1 m.
        self::assertSame([12345.5, 67890.5], SseMapLayersGeo::gridToWorld('1234567890'));
        self::assertNull(SseMapLayersGeo::gridToWorld('12345'), 'moins de 6 chiffres');
        self::assertNull(SseMapLayersGeo::gridToWorld('1234567'), 'nombre impair de chiffres');
        self::assertNull(SseMapLayersGeo::gridToWorld('xx'));
        self::assertNull(SseMapLayersGeo::gridToWorld('12 <script> 34'));
        self::assertNull(SseMapLayersGeo::gridToWorld(null));
    }

    public function testWorldPointValidity(): void
    {
        self::assertTrue(SseMapLayersGeo::validWorldPoint(18050, '9750.5'));
        self::assertFalse(SseMapLayersGeo::validWorldPoint(0, 0), 'le jeu envoie (0, 0) quand il ne sait pas');
        self::assertFalse(SseMapLayersGeo::validWorldPoint(null, 5));
        self::assertFalse(SseMapLayersGeo::validWorldPoint('abc', 5));
        self::assertFalse(SseMapLayersGeo::validWorldPoint(1.0E+20, 5));
        self::assertFalse(SseMapLayersGeo::validWorldPoint(NAN, 5));
    }

    public function testTrackSimplificationKeepsEndsAndCorners(): void
    {
        $line = [];
        for ($i = 0; $i <= 100; $i++) {
            $line[] = [(float) ($i * 10), 0.0];
        }
        self::assertSame([0, 100], SseMapLayersGeo::simplifyIndices($line, 5.0, 150), 'une droite se réduit à ses extrémités');
        $bent = [[0.0, 0.0], [500.0, 0.0], [500.0, 500.0]];
        self::assertSame([0, 1, 2], SseMapLayersGeo::simplifyIndices($bent, 5.0, 150), 'un virage est gardé');
        $zig = [];
        for ($i = 0; $i < 400; $i++) {
            $zig[] = [(float) ($i * 50), $i % 2 === 0 ? 0.0 : 200.0];
        }
        $kept = SseMapLayersGeo::simplifyIndices($zig, 5.0, 60);
        self::assertLessThanOrEqual(60, count($kept), 'plafond de sommets');
        self::assertSame(0, $kept[0]);
        self::assertSame(399, $kept[count($kept) - 1]);
        $sorted = $kept;
        sort($sorted);
        self::assertSame($sorted, $kept, 'ordre conservé');
    }

    // ------------------------------------------------------------------ Service

    public function testControlsAreGeoJsonPointsFilteredByTenantMapAndPosition(): void
    {
        $this->seed();
        $payload = $this->service()->build($this->gate(), $this->query('controles'));
        self::assertSame('FeatureCollection', $payload['type']);
        self::assertSame(SseMapLayersGeo::CRS_NAME, $payload['crs']['properties']['name']);
        $ids = array_map(static fn (array $f): string => $f['id'], $payload['features']);
        sort($ids);
        self::assertSame(['controle-1', 'controle-2', 'controle-3'], $ids, 'autre carte, autre communauté, position nulle ou (0, 0) exclues');
        foreach ($payload['features'] as $f) {
            self::assertSame('Feature', $f['type']);
            self::assertSame('Point', $f['geometry']['type']);
            self::assertCount(2, $f['geometry']['coordinates']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $f['properties']['at']);
        }
        $first = $this->ofType($payload, 'controle')[0];
        self::assertSame('CTRL-' . substr($first['id'], 9), $first['properties']['ref']);
    }

    public function testControlNameShownOnlyWithPersonFilesRight(): void
    {
        $this->seed();
        $withNames = $this->service()->build($this->gate(7, true, true, true, true), $this->query('controles'));
        $byId = [];
        foreach ($withNames['features'] as $f) {
            $byId[$f['id']] = $f['properties'];
        }
        self::assertSame('Karim Benali', $byId['controle-1']['name'], 'fiche personne liée');
        self::assertFalse($byId['controle-1']['anonymous']);
        self::assertSame('atak/sse/identites/1', $byId['controle-1']['sheet']);
        self::assertNull($byId['controle-2']['name'], 'identité de couverture : jamais nommée');
        self::assertSame('Paul Durand', $byId['controle-3']['name'], 'identité ordinaire du registre');

        $anon = $this->service()->build($this->gate(7, true, true, false, true), $this->query('controles'));
        $json = (string) json_encode($anon);
        foreach ($anon['features'] as $f) {
            self::assertNull($f['properties']['name']);
            self::assertTrue($f['properties']['anonymous']);
            self::assertNull($f['properties']['sheet']);
            self::assertNull($f['properties']['detail'], 'pas de lien vers le contrôle sans droit sur les fiches');
        }
        foreach (['Karim', 'Benali', 'Durand', 'Faux'] as $leak) {
            self::assertStringNotContainsString($leak, $json, 'aucun nom sans le droit sur les fichiers de personnes');
        }
        self::assertStringNotContainsString('Faux', (string) json_encode($withNames), 'une couverture ne sort jamais');
    }

    public function testPlacesAreScopedAndLabelled(): void
    {
        $this->seed();
        $payload = $this->service()->build($this->gate(), $this->query('lieux'));
        self::assertCount(1, $payload['features']);
        $p = $payload['features'][0]['properties'];
        self::assertSame('Entrepot nord', $p['name']);
        self::assertSame('Dépôt', $p['kind_label']);
        self::assertSame('Exploitation en cours', $p['status_label']);
        self::assertSame([12050.0, 13050.0], $payload['features'][0]['geometry']['coordinates']);
        self::assertSame('atak/sse/sites/1', $p['detail']);
    }

    public function testTracksAreOrderedLineStringsWithVertexTimes(): void
    {
        $this->seed();
        $payload = $this->service()->build($this->gate(), $this->query('pistes'));
        $tracks = $this->ofType($payload, 'piste');
        $ids = array_map(static fn (array $f): int => (int) $f['properties']['extraction_id'], $tracks);
        sort($ids);
        self::assertSame([1], $ids, 'extractions masquées, d’une autre communauté, sans carte ou sans position exclues');
        $t = $tracks[0];
        self::assertSame('LineString', $t['geometry']['type']);
        // 180097 puis 181097 (le doublon est retiré), 182100 puis 18000970 ; la grille illisible est ignorée.
        self::assertSame([[18050.0, 9750.0], [18150.0, 9750.0], [18250.0, 10050.0], [18005.0, 9705.0]], $t['geometry']['coordinates']);
        self::assertSame(['08:00', '08:10', '08:30', '08:40'], $t['properties']['times'], 'un horodatage par sommet, dans l’ordre de réception');
        self::assertCount(count($t['geometry']['coordinates']), $t['properties']['times']);
        self::assertSame(4, $t['properties']['vertices_total']);
        self::assertSame('Marc Lambert', $t['properties']['label']);
        $meta = $payload['meta']['types']['pistes'];
        self::assertSame(1, $meta['unreadable_grids']);
        self::assertSame(1, $meta['unmapped_extractions'], 'extraction visible sans carte d’origine : comptée, jamais posée sur un théâtre');
    }

    public function testTrackLabelHiddenWithoutPersonFilesRight(): void
    {
        $this->seed();
        $payload = $this->service()->build($this->gate(7, true, true, false, true), $this->query('pistes'));
        $json = (string) json_encode($payload);
        self::assertStringNotContainsString('Marc Lambert', $json);
        self::assertStringNotContainsString('Telephone A', $json);
        self::assertNull($this->ofType($payload, 'piste')[0]['properties']['label']);
        self::assertNull($this->ofType($payload, 'piste')[0]['properties']['detail']);
    }

    public function testEachTypeRequiresItsOwnRight(): void
    {
        $this->seed();
        $q = $this->query();
        $noTracks = $this->service()->build($this->gate(7, true, true, true, false), $q);
        self::assertSame([], $this->ofType($noTracks, 'piste'));
        self::assertFalse($noTracks['meta']['types']['pistes']['allowed']);
        self::assertNotSame([], $this->ofType($noTracks, 'controle'));
        $noControls = $this->service()->build($this->gate(7, false, true, true, true), $q);
        self::assertSame([], $this->ofType($noControls, 'controle'));
        self::assertNotSame([], $this->ofType($noControls, 'lieu'));
        $noPlaces = $this->service()->build($this->gate(7, true, false, true, true), $q);
        self::assertSame([], $this->ofType($noPlaces, 'lieu'));
        self::assertFalse($noPlaces['meta']['access']['lieux']);
    }

    public function testClosedDoorServesNothing(): void
    {
        $this->seed();
        $gate = $this->gate(0, true, true, true, true);
        $payload = $this->service()->build($gate, $this->query());
        self::assertSame([], $payload['features']);
        self::assertSame(['controles' => false, 'lieux' => false, 'pistes' => false, 'noms' => false], $payload['meta']['access']);
        self::assertSame(['controles' => false, 'lieux' => false, 'pistes' => false, 'noms' => false], $this->service()->access($gate));
    }

    public function testNothingLeaksAcrossTenants(): void
    {
        $this->seed();
        $other = $this->service()->build($this->gate(8), $this->query());
        $json = (string) json_encode($other);
        self::assertStringContainsString('controle-5', $json);
        self::assertStringContainsString('lieu-2', $json);
        self::assertStringContainsString('piste-4', $json);
        foreach (['controle-1', 'controle-2', 'lieu-1', 'piste-1', 'Entrepot nord', 'Karim'] as $mine) {
            self::assertStringNotContainsString($mine, $json, 'la communauté 8 ne voit rien de la communauté 7');
        }
    }

    public function testCapsAndTruncationFlag(): void
    {
        for ($i = 1; $i <= SseMapLayersService::MAX_CONTROLS + 10; $i++) {
            $this->pdo->exec("INSERT INTO sse_id_checks (tenant_id, context_id, verdict, pos_x, pos_y, created_at) VALUES (7, 1, 'ras', " . (1000 + $i) . ', 2000, \'' . gmdate('Y-m-d H:i:s') . "')");
        }
        $payload = $this->service()->build($this->gate(), $this->query('controles'));
        self::assertCount(SseMapLayersService::MAX_CONTROLS, $payload['features']);
        self::assertTrue($payload['meta']['types']['controles']['truncated']);
    }

    public function testBboxAndPeriodFilters(): void
    {
        $this->seed();
        $inBox = $this->service()->build($this->gate(), $this->query('controles,lieux', ['bbox' => '18000,9700,18200,9900']));
        $ids = array_map(static fn (array $f): string => $f['id'], $inBox['features']);
        sort($ids);
        self::assertSame(['controle-1', 'controle-2'], $ids);
        $recent = $this->service()->build($this->gate(), $this->query('controles', ['periode' => '7j']));
        $recentIds = array_map(static fn (array $f): string => $f['id'], $recent['features']);
        self::assertNotContains('controle-3', $recentIds, 'contrôle de 40 jours hors de la fenêtre de 7 jours');
        $sinceUntil = SseMapLayersService::parseQuery(['types' => 'controles', 'carte' => '1', 'depuis' => gmdate('Y-m-d', time() - 60 * 86400), 'jusqua' => gmdate('Y-m-d', time() - 30 * 86400)]);
        $old = $this->service()->build($this->gate(), $sinceUntil['query']);
        self::assertSame(['controle-3'], array_map(static fn (array $f): string => $f['id'], $old['features']));
    }

    public function testEmptyStatesAreExplicit(): void
    {
        // Aucune donnée du tout, tables vides.
        $q = $this->query();
        $payload = $this->service()->build($this->gate(), $q);
        self::assertSame([], $payload['features']);
        self::assertSame('none', $payload['meta']['types']['controles']['empty']);
        self::assertSame('none', $payload['meta']['types']['lieux']['empty']);
        self::assertSame('no_extraction', $payload['meta']['types']['pistes']['empty']);

        // Extractions sans aucune position.
        $this->pdo->exec("INSERT INTO sse_phone_extractions (tenant_id, ref, context_id, status, created_at) VALUES (7, 'EXT-000001', 1, 'visible', '" . gmdate('Y-m-d H:i:s') . "')");
        self::assertSame('no_position', $this->service()->build($this->gate(), $q)['meta']['types']['pistes']['empty']);

        // Une seule position : pas de déplacement.
        $this->pdo->exec("INSERT INTO sse_phone_extraction_items (tenant_id, extraction_id, category, at, grid) VALUES (7, 1, 'positions', '08:00', '180097')");
        self::assertSame('no_movement', $this->service()->build($this->gate(), $q)['meta']['types']['pistes']['empty']);
    }

    public function testExtractionsWithoutMapAreReportedNotDrawn(): void
    {
        $this->pdo->exec("INSERT INTO sse_phone_extractions (tenant_id, ref, context_id, status, created_at) VALUES (7, 'EXT-000009', NULL, 'visible', '" . gmdate('Y-m-d H:i:s') . "')");
        $payload = $this->service()->build($this->gate(), $this->query('pistes'));
        self::assertSame([], $payload['features']);
        self::assertSame('unmapped', $payload['meta']['types']['pistes']['empty']);
    }

    public function testMissingTablesGiveEmptyResultsNotErrors(): void
    {
        foreach (['sse_id_checks', 'sse_sites', 'sse_phone_extractions', 'sse_phone_extraction_items', 'sse_persons', 'sse_identities'] as $t) {
            $this->pdo->exec('DROP TABLE ' . $t);
        }
        $payload = $this->service()->build($this->gate(), $this->query());
        self::assertSame([], $payload['features']);
        self::assertSame('FeatureCollection', $payload['type']);
    }

    public function testPhoneTrackReadsAreAuditedAndFailClosed(): void
    {
        $this->seed();
        $q = $this->query('pistes');
        $svc = $this->service();
        $payload = $svc->auditTrackAccess($this->gate(), $q, $svc->build($this->gate(), $q));
        self::assertCount(1, $this->audited);
        self::assertSame(SseMapLayersService::AUDIT_ACTION, $this->audited[0]['action']);
        self::assertSame(7, $this->audited[0]['tenant_id']);
        self::assertSame(42, $this->audited[0]['actor_user_id']);
        self::assertSame([1], $this->audited[0]['after']['extractions']);
        self::assertCount(1, $this->ofType($payload, 'piste'));

        // Sans piste servie, aucune ligne d'audit.
        $this->audited = [];
        $c = $this->query('controles');
        $svc->auditTrackAccess($this->gate(), $c, $svc->build($this->gate(), $c));
        self::assertSame([], $this->audited);

        // Journal indisponible : les pistes ne sont pas servies.
        $broken = $this->service(static function (array $r): void {
            throw new \RuntimeException('journal HS');
        });
        $out = $broken->auditTrackAccess($this->gate(), $q, $broken->build($this->gate(), $q));
        self::assertSame([], $this->ofType($out, 'piste'));
        self::assertSame('audit_unavailable', $out['meta']['types']['pistes']['empty']);
    }

    public function testCacheKeyDoesNotMixReadersWithDifferentRights(): void
    {
        $this->seed();
        ShortCache::setEnabled(true);
        try {
            $svc = $this->service();
            $q = $this->query('controles');
            $named = $svc->build($this->gate(7, true, true, true, true), $q);
            $anon = $svc->build($this->gate(7, true, true, false, true), $q);
            self::assertStringContainsString('Karim', (string) json_encode($named));
            self::assertStringNotContainsString('Karim', (string) json_encode($anon), 'un lecteur sans droit ne reçoit jamais l’entrée mise en cache pour un lecteur avec droit');
        } finally {
            ShortCache::setEnabled(false);
        }
    }

    // ------------------------------------------------------------------ Interface et intégration

    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testSharedScriptHasNoHardcodedFrenchAndNoHtmlInjection(): void
    {
        $js = $this->read('public/assets/js/sse-map-layers.js');
        // Aucun littéral de chaîne avec accent ni mot français courant : les textes viennent de la page.
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $js);
        preg_match_all('/\'([^\'\n]*)\'|"([^"\n]*)"/u', $code, $m);
        foreach (array_merge($m[1], $m[2]) as $literal) {
            self::assertSame(0, preg_match('/[àâäçéèêëîïôöùûüœÀÉÈÊ]/u', $literal), 'texte accentué dans un littéral JS : « ' . $literal . ' »');
            self::assertSame(0, preg_match('/\b(Aucun|Aucune|Contrôle|Ouvrir|Piste|Lieu|Dernières|Période|Fermer)\b/u', $literal), 'mot français dans un littéral JS : « ' . $literal . ' »');
        }
        self::assertStringNotContainsString('innerHTML', $js, 'les données lues ne sont jamais injectées en HTML');
        self::assertStringNotContainsString('document.write', $js);
        self::assertStringContainsString('/api/sse/v1/calques', $js);
        self::assertStringContainsString('document.hidden', $js, 'onglet masqué : pause');
        self::assertStringContainsString('SAFE_PATH', $js, 'liens limités au Bureau SSE');
    }

    public function testPanelIsRenderedWithEveryTextTheScriptReads(): void
    {
        $js = $this->read('public/assets/js/sse-map-layers.js');
        preg_match_all('/\btr\(\'([a-z_]+)\'\)/', $js, $m);
        $keys = array_unique($m[1]);
        // Clés composées lues par préfixe.
        foreach (['empty_none_controles', 'empty_none_lieux', 'empty_no_extraction', 'empty_no_position', 'empty_no_movement', 'empty_unmapped'] as $k) {
            $keys[] = $k;
        }
        $html = $this->renderPartial('toc-sse-ml', true);
        self::assertStringContainsString('id="toc-sse-ml"', $html);
        self::assertStringContainsString('hidden', $html);
        foreach ($keys as $k) {
            self::assertStringContainsString('data-k="' . $k . '"', $html, 'texte « ' . $k . ' » absent du panneau');
        }
        foreach (['controles', 'lieux', 'pistes'] as $t) {
            self::assertStringContainsString('data-sse-ml-toggle="' . $t . '"', $html);
            self::assertStringContainsString('data-sse-ml-row="' . $t . '" hidden', $html, 'case masquée tant que le droit n’est pas confirmé');
        }
        self::assertStringNotContainsString(' checked', $html, 'calques désactivés par défaut');
        self::assertStringContainsString('Aucune piste : les extractions de téléphones n’ont pas de positions.', $html);
        self::assertStringContainsString('data-sse-ml-close', $html);
        self::assertStringNotContainsString('data-sse-ml-close', $this->renderPartial('ow-sse-ml', false), 'bloc intégré : pas de bouton fermer');
    }

    private function renderPartial(string $id, bool $floating): string
    {
        $render = static function (string $file, string $id, bool $floating): string {
            $sseMlId = $id;
            $sseMlFloating = $floating;
            $sseMlBase = 'https://exemple.test';
            ob_start();
            try {
                require $file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(dirname(__DIR__, 2) . '/views/partials/sse_map_layers.php', $id, $floating);
    }

    public function testEveryEnglishEntryExistsForNewTexts(): void
    {
        $dict = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (['Aucune piste : les extractions de téléphones n’ont pas de positions.', 'Pistes de téléphones', 'Dernières 24 h', 'Curseur de temps', 'Fin de piste'] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
        }
    }

    public function testThreeMapsLoadThePartialAndTheSharedScript(): void
    {
        $toc = $this->read('views/atak/toc/console.php');
        $head = $this->read('views/atak/toc/_head.php');
        $tac = $this->read('views/tacmap.php');
        $ow = $this->read('views/atak-overwatch-beta.php');
        foreach ([$toc, $tac, $ow] as $view) {
            self::assertStringContainsString('views/partials/sse_map_layers.php', $view);
        }
        self::assertStringContainsString('sse-map-layers.js', $head);
        self::assertStringContainsString('sse-map-layers.css', $head);
        self::assertStringContainsString('sse-map-layers.js', $tac);
        self::assertStringContainsString('sse-map-layers.js', $ow);
        self::assertStringContainsString('mountToc', $this->read('public/assets/js/toc/toc-console.js'));
        self::assertStringContainsString('comspec:tacmap-ready', $this->read('public/assets/js/comspec-operational-map.js'));
    }

    public function testRouteIsReadOnlyAndSessionPollIsReadOnly(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("\$router->get('/api/sse/v1/calques', [\\App\\Controllers\\Api\\SseMapLayersApiController::class, 'index']);", $routes);
        self::assertStringNotContainsString("post('/api/sse/v1/calques'", $routes, 'aucune écriture : pas de CSRF à porter');
        self::assertTrue(\App\Core\Session::isReadOnlyRequest('GET', '/api/sse/v1/calques', false));
    }

    public function testControllerNeverReadsTheTenantFromTheRequest(): void
    {
        $src = $this->read('app/Controllers/Api/SseMapLayersApiController.php');
        self::assertStringNotContainsString('tenant_id', $src, 'la communauté vient de la session');
        self::assertStringNotContainsString('$request->input', $src);
        self::assertStringContainsString('tooManyAttempts', $src);
        self::assertStringContainsString('no-store', $src);
    }

    public function testViewsAndClassesImportWhatTheyUse(): void
    {
        foreach (['app/Services/Sse/SseMapLayersService.php', 'app/Services/Sse/SessionSseMapLayerGate.php', 'app/Repositories/SseMapLayersRepository.php', 'app/Controllers/Api/SseMapLayersApiController.php', 'app/Support/SseMapLayersGeo.php'] as $file) {
            $src = $this->read($file);
            preg_match('/^namespace ([\w\\\\]+);/m', $src, $ns);
            preg_match_all('/^use ([\w\\\\]+);/m', $src, $uses);
            $imported = array_map(static fn (string $u): string => substr($u, (int) strrpos($u, '\\') + 1), $uses[1]);
            // Chaque classe nommée « X:: » ou « new X » sans antislash vient d'un « use » ou de l'espace de noms du fichier.
            preg_match_all('/(?<![\\\\\w$>])(?:new\s+|instanceof\s+)?([A-Z][a-z][A-Za-z0-9]*)(?=::|\()/', $src, $named);
            foreach (array_unique($named[1]) as $class) {
                if (in_array($class, ['PDO', 'DateTimeImmutable', 'InvalidArgumentException'], true) || in_array($class, $imported, true)) {
                    continue;
                }
                self::assertTrue(
                    is_file(dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($ns[1], 4)) . '/' . $class . '.php'),
                    $file . ' : classe « ' . $class . ' » sans use ni voisine de même espace de noms'
                );
            }
        }
    }

    public function testApiDocumentExists(): void
    {
        $doc = $this->read('docs/sse-calques-api.md');
        foreach (['/api/sse/v1/calques', 'types', 'carte', 'depuis', 'jusqua', 'bbox', 'urn:comspec:crs:arma-world-m', 'journal'] as $needle) {
            self::assertStringContainsString($needle, $doc);
        }
    }
}
