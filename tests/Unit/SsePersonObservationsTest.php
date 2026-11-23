<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SsePersonObservations;
use PHPUnit\Framework\TestCase;

final class SsePersonObservationsTest extends TestCase
{
    public function testTransmittedFieldsRemainSeparateFromCivilIdentity(): void
    {
        $person = ['display_name' => 'Nom déclaré', 'age_estimated' => 0,
            'statements' => "Première ligne\nDeuxième ligne", 'distinguishing_marks' => 'Cicatrice',
            'equipment' => ['Radio', 'Sac'], 'nationality' => '', 'submitter_steam_id' => 'privé'];
        $rows = SsePersonObservations::rows($person);
        self::assertContains(['Signes particuliers', 'Cicatrice'], $rows);
        self::assertContains(['Âge estimé', '0'], $rows);
        self::assertContains(['Déclarations et notes', "Première ligne\nDeuxième ligne"], $rows);
        self::assertContains(['Équipement relevé', 'Radio · Sac'], $rows);
        self::assertNotContains('Nationalité déclarée', array_column($rows, 0));
        self::assertStringNotContainsString('privé', json_encode($rows));
        self::assertSame('', $person['nationality']);
    }
}
