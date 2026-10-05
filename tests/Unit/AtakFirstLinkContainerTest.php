<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakFirstLinkController;
use App\Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * /back-office/ma-situation/premiere-liaison délègue au contrôleur via le conteneur :
 * sans enregistrement, « Unknown service » et erreur 500.
 */
final class AtakFirstLinkContainerTest extends TestCase
{
    public function testContainerResolvesTheFirstLinkController(): void
    {
        self::assertInstanceOf(AtakFirstLinkController::class, Container::get(AtakFirstLinkController::class));
    }
}
