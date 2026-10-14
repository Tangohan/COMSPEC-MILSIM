<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Monitoring\AtakModReportGravity as Gravity;
use PHPUnit\Framework\TestCase;

/** Gravité des rapports du mod : classement à la réception et règles d'e-mail. */
final class AtakModReportGravityTest extends TestCase
{
    public function testClassification(): void
    {
        self::assertSame(Gravity::HIGH, Gravity::classify('player', 'bug', 'La carte reste noire'));
        self::assertSame(Gravity::CRITICAL, Gravity::classify('player', 'bug', 'Plantage du jeu en ouvrant la tablette'));
        self::assertSame(Gravity::CRITICAL, Gravity::classify('auto', 'error', 'callExtension : extension introuvable'));
        self::assertSame(Gravity::NORMAL, Gravity::classify('auto', 'error', 'ÉCHEC · fiche opérateur — unauthorized'));
        self::assertSame(Gravity::NORMAL, Gravity::classify('auto', 'error', 'Session refusée par le poste — transmissions arrêtées'));
        self::assertSame(Gravity::NORMAL, Gravity::classify('auto', 'error', 'ÉCHEC · photo — invalid_url'));
        self::assertSame(Gravity::LOW, Gravity::classify('auto', 'error', 'ÉCHEC · positions — timeout'));
        self::assertSame(Gravity::LOW, Gravity::classify('auto', 'error', 'Athena hors ligne, nouvel essai dans 30 s'));
        self::assertSame(Gravity::LOW, Gravity::classify('auto', 'error', 'ÉCHEC · marqueurs — HTTP 503'));
        self::assertSame(Gravity::LOW, Gravity::classify('auto', 'warn', 'Pack ancien'));
        self::assertSame(Gravity::LOW, Gravity::classify('auto', 'info', 'Démarrage'));
        // Un mot « passager » dans le seul journal ne rend pas l'erreur faible.
        self::assertSame(Gravity::NORMAL, Gravity::classify('auto', 'error', 'ÉCHEC · fiche — invalid', "--- journal ---\ntimeout il y a 2 h"));
        // Refus « formule sans la carte » : attendu, faible (pas de ticket ni d'e-mail).
        self::assertSame(Gravity::LOW, Gravity::classify('auto', 'error', 'ÉCHEC · PhotoUpload — http_403 · COMSPEC_859_60136.png', 'ERR|http_403|COMSPEC_859_60136.png|feature_unavailable'));
        self::assertSame(Gravity::HIGH, Gravity::classify('player', 'bug', 'Photo refusée', 'feature_unavailable'));
        // Gravité proposée par le mod : prioritaire si elle est valable.
        self::assertSame(Gravity::LOW, Gravity::classify('auto', 'error', 'Plantage', '', 'low'));
        self::assertSame(Gravity::CRITICAL, Gravity::classify('auto', 'error', 'Plantage', '', 'nimporte'));
    }

    public function testMailRulesAndLabels(): void
    {
        self::assertTrue(Gravity::mailsOps('player', Gravity::LOW));
        self::assertTrue(Gravity::mailsOps('auto', Gravity::CRITICAL));
        self::assertFalse(Gravity::mailsOps('auto', Gravity::HIGH));
        self::assertFalse(Gravity::mailsOps('auto', Gravity::NORMAL));
        self::assertFalse(Gravity::mailsPlayer(Gravity::LOW));
        self::assertTrue(Gravity::mailsPlayer(Gravity::NORMAL));
        self::assertTrue(Gravity::mailsPlayer(''));
        self::assertSame('Critique', Gravity::label('critical'));
        self::assertSame('Moyenne', Gravity::label('???'));
        self::assertSame(0, Gravity::rank('critical'));
        self::assertSame(3, Gravity::rank('faible'));
    }
}
