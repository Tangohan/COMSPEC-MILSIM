<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ArsenalWardrobeRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Favoris de la page Équipement : un membre met en favori ses tenues (colonne is_favorite, lue aussi par le jeu) et
 * celles des autres membres de sa communauté (table arsenal_wardrobe_favorites), jamais une tenue réservée à son
 * propriétaire ni celle d'une autre communauté. Dépôt réel sur sqlite (information_schema, DATABASE() et NOW() simulés).
 */
final class EquipmentFavoritesTest extends TestCase
{
    private const TENANT = 4;

    private const ME = 10;

    private const OTHER = 11;

    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = self::schema(true);
    }

    public function testOwnTenueUsesTheWardrobeColumn(): void
    {
        $repo = new ArsenalWardrobeRepository($this->pdo);
        $mine = $this->insert(self::TENANT, self::ME, 'Assaut');

        self::assertTrue($repo->setFavorite(self::TENANT, self::ME, $mine, true));
        self::assertSame(1, (int) $this->pdo->query('SELECT is_favorite FROM arsenal_wardrobes WHERE id = ' . $mine)->fetchColumn());
        self::assertSame([], $repo->favoriteIds(self::TENANT, self::ME), 'ses propres favoris ne passent pas par la table');
        self::assertTrue($repo->setFavorite(self::TENANT, self::ME, $mine, false));
        self::assertSame(0, (int) $this->pdo->query('SELECT is_favorite FROM arsenal_wardrobes WHERE id = ' . $mine)->fetchColumn());
    }

    public function testSharedTenueOfAnotherMemberIsFavoritedInTheTableAndOnlyForMe(): void
    {
        $repo = new ArsenalWardrobeRepository($this->pdo);
        $theirs = $this->insert(self::TENANT, self::OTHER, 'Nuit');

        self::assertTrue($repo->setFavorite(self::TENANT, self::ME, $theirs, true));
        self::assertTrue($repo->setFavorite(self::TENANT, self::ME, $theirs, true), 'deux clics : une seule ligne');
        self::assertSame([$theirs], $repo->favoriteIds(self::TENANT, self::ME));
        self::assertSame([], $repo->favoriteIds(self::TENANT, self::OTHER), 'le favori d’un membre n’est pas celui des autres');
        self::assertSame(0, (int) $this->pdo->query('SELECT is_favorite FROM arsenal_wardrobes WHERE id = ' . $theirs)->fetchColumn(), 'la tenue du propriétaire ne change pas');
        self::assertTrue($repo->setFavorite(self::TENANT, self::ME, $theirs, false));
        self::assertSame([], $repo->favoriteIds(self::TENANT, self::ME));
    }

    public function testPrivateTenueAndOtherCommunityTenueCannotBeFavorited(): void
    {
        $repo = new ArsenalWardrobeRepository($this->pdo);
        $private = $this->insert(self::TENANT, self::OTHER, 'Secrète', 'personal');
        $foreign = $this->insert(self::TENANT + 1, self::OTHER, 'Ailleurs');

        self::assertFalse($repo->setFavorite(self::TENANT, self::ME, $private, true));
        self::assertFalse($repo->setFavorite(self::TENANT, self::ME, $foreign, true), 'filtré par communauté');
        self::assertSame([], $repo->favoriteIds(self::TENANT, self::ME));
    }

    public function testWithoutTheFavoritesTableOnlyOwnTenuesCanBeFavorited(): void
    {
        $pdo = self::schema(false);
        $repo = new ArsenalWardrobeRepository($pdo);
        $this->pdo = $pdo;
        $mine = $this->insert(self::TENANT, self::ME, 'Assaut');
        $theirs = $this->insert(self::TENANT, self::OTHER, 'Nuit');

        self::assertFalse($repo->favoritesReady());
        self::assertTrue($repo->setFavorite(self::TENANT, self::ME, $mine, true));
        self::assertFalse($repo->setFavorite(self::TENANT, self::ME, $theirs, true));
        self::assertSame([], $repo->favoriteIds(self::TENANT, self::ME));
    }

    private function insert(int $tenantId, int $userId, string $name, string $visibility = 'tenant'): int
    {
        $payload = '[[],[],[],["U_B_CombatUniform_mcam",[]],[],[],"","",[],["","","","","",""]]';
        $this->pdo->prepare(
            'INSERT INTO arsenal_wardrobes (tenant_id, user_id, name, slug, visibility, payload_text, payload_sha256, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([$tenantId, $userId, $name, ArsenalWardrobeRepository::slugify($name), $visibility, $payload, hash('sha256', $payload)]);

        return (int) $this->pdo->lastInsertId();
    }

    private static function schema(bool $withFavorites): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CHAR_LENGTH', static fn (?string $s): int => mb_strlen((string) $s), 1);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $tables = ['arsenal_wardrobes', 'arsenal_equipment_collections', 'arsenal_collection_wardrobes', 'users'];
        if ($withFavorites) {
            $tables[] = 'arsenal_wardrobe_favorites';
        }
        foreach ($tables as $table) {
            $pdo->prepare("INSERT INTO information_schema.TABLES VALUES ('main', ?)")->execute([$table]);
        }
        foreach (['cover_image_path', 'description', 'gallery_json', 'visibility'] as $column) {
            $pdo->prepare("INSERT INTO information_schema.COLUMNS VALUES ('main', 'arsenal_wardrobes', ?)")->execute([$column]);
        }
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, callsign TEXT, display_name TEXT)');
        $pdo->exec('CREATE TABLE arsenal_equipment_collections (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, owner_user_id INTEGER, name TEXT NOT NULL,
            slug TEXT NOT NULL, description TEXT, cover_image_path TEXT, visibility TEXT NOT NULL DEFAULT \'personal\',
            tags_json TEXT, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE arsenal_collection_wardrobes (collection_id INTEGER NOT NULL, wardrobe_id INTEGER NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE TABLE arsenal_wardrobes (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL, steam_uid TEXT,
            collection_id INTEGER, visibility TEXT NOT NULL DEFAULT \'tenant\', name TEXT NOT NULL, slug TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT \'ace_arsenal\', payload_format TEXT NOT NULL DEFAULT \'arma_loadout_str\',
            payload_text TEXT NOT NULL, payload_sha256 TEXT NOT NULL, notes TEXT, description TEXT, cover_image_path TEXT,
            gallery_json TEXT, is_favorite INTEGER NOT NULL DEFAULT 0, last_synced_at TEXT, created_at TEXT, updated_at TEXT)');
        if ($withFavorites) {
            $pdo->exec('CREATE TABLE arsenal_wardrobe_favorites (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL, wardrobe_id INTEGER NOT NULL,
                created_at TEXT, UNIQUE (tenant_id, user_id, wardrobe_id))');
        }

        return $pdo;
    }
}
