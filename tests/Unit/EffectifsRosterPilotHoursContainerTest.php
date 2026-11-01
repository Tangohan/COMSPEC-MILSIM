<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Repositories\AtakDistanceRepository;
use PHPUnit\Framework\TestCase;

/**
 * Le tableur des effectifs lit les heures de vol via le conteneur : sans enregistrement, « Unknown service »
 * était avalé par un try/catch et la colonne « Heures de vol » restait vide pour tout le monde.
 */
final class EffectifsRosterPilotHoursContainerTest extends TestCase
{
    public function testContainerResolvesTheFlightHoursRepository(): void
    {
        self::assertInstanceOf(AtakDistanceRepository::class, Container::get(AtakDistanceRepository::class));
    }
}
