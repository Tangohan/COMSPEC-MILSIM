<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Aar\AarSynthesisService;
use PHPUnit\Framework\TestCase;

final class AarSynthesisServiceTest extends TestCase
{
    public function testCondenseGroupsTextsAndDropsDuplicates(): void
    {
        $reports = [
            [
                'reference_code' => 'CR-1', 'title' => 'Aigle', 'operation_label' => 'Aigle noir',
                'weaknesses' => ['Radio saturée'], 'open_actions' => [['label' => 'Revoir les fréquences']],
                'lessons_learned' => 'Briefer plus tôt', 'conclusion_text' => 'Mission remplie',
            ],
            [
                'reference_code' => 'CR-2', 'title' => 'Faucon', 'operation_label' => '',
                'weaknesses' => ['radio  saturée', 'Retard au départ'], 'open_actions' => [],
                'lessons_learned' => '', 'conclusion_text' => '',
            ],
        ];

        $out = AarSynthesisService::condense($reports);

        self::assertSame(
            "[CR-1 · Aigle noir] Radio saturée\n[CR-1 · Aigle noir] Revoir les fréquences\n[CR-2 · Faucon] Retard au départ",
            $out['improvements']
        );
        self::assertSame('[CR-1 · Aigle noir] Briefer plus tôt', $out['lessons']);
        self::assertSame('[CR-1 · Aigle noir] Mission remplie', $out['conclusion']);
    }

    public function testCondenseOfNothingIsEmpty(): void
    {
        self::assertSame(['improvements' => '', 'lessons' => '', 'conclusion' => ''], AarSynthesisService::condense([]));
    }
}
