<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakExplosiveTimerRepository;
use PHPUnit\Framework\TestCase;

final class AtakExplosiveTriggerKindTest extends TestCase
{
    public function testAtakKindIsRecognizedAndLabeledForOperators(): void
    {
        $repo = new AtakExplosiveTimerRepository();

        self::assertSame('atak', $repo->normalizeTriggerKind('atak'));
        self::assertSame('atak', $repo->normalizeTriggerKind('ATAK_ONLY'));
        self::assertSame('atak', $repo->normalizeTriggerKind('athena'));
        self::assertSame('command', $repo->normalizeTriggerKind('toc'));
        self::assertSame('clacker', $repo->normalizeTriggerKind('m57'));
        self::assertSame('', $repo->normalizeTriggerKind('unknown'));

        self::assertSame('Uniquement depuis ATAK', $repo->triggerLabelFr('atak'));
        self::assertSame('Déclencheur', $repo->triggerLabelFr('clacker'));
        self::assertSame('À la demande', $repo->triggerLabelFr('command'));
        self::assertSame('À retardement', $repo->triggerLabelFr('timer'));
    }
}
