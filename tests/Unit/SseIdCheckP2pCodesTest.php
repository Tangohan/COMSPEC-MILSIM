<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseIdCheckInsightService;
use PHPUnit\Framework\TestCase;

/**
 * Contrôles d'identité : un contrôle ou une présentation de papiers entre joueurs (mod SSE) remonte dans la forme d'un
 * contrôle, niveau N0 « non fait », avec des constats p2p. Ils se lisent en clair dans le détail du contrôle.
 */
final class SseIdCheckP2pCodesTest extends TestCase
{
    /** @param list<string> $codes */
    private static function row(array $codes): array
    {
        $rows = SseIdCheckInsightService::levelRows([
            'levels' => ['N0'],
            'results' => ['N0' => ['state' => 'na', 'verdict' => '', 'codes' => $codes, 'p2p' => ['kind' => 'player']]],
            'verdict' => null,
        ]);
        self::assertCount(1, $rows);

        return $rows[0];
    }

    public function testAcceptedCheckBetweenPlayersReadsInPlainWords(): void
    {
        $row = self::row(['p2p', 'p2p_accepted', 'p2p_show', 'p2p_docs:2']);
        self::assertSame(['Contrôle joueur à joueur', 'Papiers présentés à la demande', 'Papiers montrés', '2 papiers présentés'], $row['codes']);
        self::assertSame('N0', $row['code']);
        self::assertSame('Non fait', $row['state_label']);
        self::assertSame('off', $row['tone'], 'Ni conforme ni anomalie : aucun examen du papier n\'a encore eu lieu.');
        self::assertSame('', $row['verdict_label']);
    }

    public function testRefusalNoAnswerAndUnaskedPresentation(): void
    {
        self::assertSame(
            ['Contrôle joueur à joueur', 'Refus de présenter ses papiers', 'Motif : conteste l’autorité du contrôleur'],
            self::row(['p2p', 'p2p_refused', 'p2p_reason_no_authority'])['codes']
        );
        self::assertSame(['Contrôle joueur à joueur', 'Pas de réponse à la demande'], self::row(['p2p', 'p2p_no_answer'])['codes']);
        self::assertSame(
            ['Contrôle joueur à joueur', 'Papiers présentés de sa propre initiative', 'Papiers remis en main', '1 papier présenté'],
            self::row(['p2p', 'p2p_presented', 'p2p_give', 'p2p_docs:1'])['codes']
        );
    }

    public function testACheckBetweenPlayersIsNeitherADetectionNorAForgery(): void
    {
        $check = ['verdict' => null, 'results' => ['N0' => ['state' => 'na', 'verdict' => '', 'codes' => ['p2p', 'p2p_refused']]]];
        self::assertFalse(SseIdCheckInsightService::isDetection($check));
        self::assertFalse(SseIdCheckInsightService::isForgery($check));
    }

    public function testEveryReasonTheModCanSendHasALabel(): void
    {
        foreach (['no_papers', 'not_now', 'no_authority', 'superior'] as $reason) {
            self::assertArrayHasKey('p2p_reason_' . $reason, SseIdCheckInsightService::CODES);
        }
    }
}
