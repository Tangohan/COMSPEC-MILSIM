<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAiProfileRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/** Profils d'IA sur une vraie base : création, duplication, suppression, portée du paquet, éléments gardés, isolation. */
final class SseAiProfileRepositoryTest extends TestCase
{
    private SseAiProfileRepository $repo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        (require dirname(__DIR__, 2) . '/bootstrap/atak_sse_ai_profiles_migration.php')($pdo);
        $this->repo = new SseAiProfileRepository();
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    public function testCrudIsBoundToCommunity(): void
    {
        $id = $this->repo->create(1, ['label' => 'Berger', 'side' => 'civ', 'behaviour' => 'nerveux'], 7);
        self::assertSame('PRF-' . sprintf('%04d', $id), $this->repo->find($id, 1)['ref']);
        self::assertNull($this->repo->find($id, 2), 'une autre communauté ne voit pas le profil');
        self::assertFalse($this->repo->update($id, 2, ['label' => 'Volé']));
        self::assertFalse($this->repo->archive($id, 2));
        self::assertNull($this->repo->duplicate($id, 2, 7));
        self::assertTrue($this->repo->update($id, 1, ['label' => 'Berger du nord', 'behaviour' => 'fuit']));
        self::assertSame('fuit', $this->repo->find($id, 1)['behaviour']);
        $copy = $this->repo->duplicate($id, 1, 7);
        self::assertSame('Copie de Berger du nord', $this->repo->find((int) $copy, 1)['label']);
        self::assertTrue($this->repo->archive($id, 1));
        self::assertNull($this->repo->find($id, 1));
        self::assertCount(1, $this->repo->list(1));
        self::assertSame([], $this->repo->list(2));
    }

    public function testPackScopeFollowsMapAndOperation(): void
    {
        $all = $this->repo->create(1, ['label' => 'Partout'], null);
        $this->repo->create(1, ['label' => 'Autre carte', 'context_id' => 9], null);
        $this->repo->create(1, ['label' => 'Op', 'context_id' => 3, 'op_key' => 'NEPTUNE'], null);
        $this->repo->create(2, ['label' => 'Autre communauté'], null);
        self::assertSame([$all], array_column($this->repo->forPack(1, 3, null), 'id'));
        self::assertSame(['Partout', 'Op'], array_column($this->repo->forPack(1, 3, 'NEPTUNE'), 'label'));
    }

    public function testKeptElementsAreBoundToCommunityAndMap(): void
    {
        $id = $this->repo->addPlacement(1, 3, null, ['kind' => 'ai', 'var_name' => 'garde1', 'class_name' => 'C_man_1', 'x' => 10.5, 'y' => 20, 'label' => 'Garde', 'config' => ['behaviour' => 'refuse']], 7);
        self::assertSame([], $this->repo->placementsForPack(2, 3, null));
        self::assertSame([], $this->repo->placementsForPack(1, 4, null));
        $rows = $this->repo->placementsForPack(1, 3, null);
        self::assertSame('garde1', $rows[0]['var_name']);
        self::assertSame('refuse', $rows[0]['config']['behaviour']);
        self::assertFalse($this->repo->removePlacement($id, 2));
        self::assertTrue($this->repo->removePlacement($id, 1));
        self::assertSame([], $this->repo->placementsForPack(1, 3, null));
    }
}
