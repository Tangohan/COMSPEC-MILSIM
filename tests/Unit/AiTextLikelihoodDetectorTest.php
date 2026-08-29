<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Moderation\AiTextLikelihoodDetector;
use PHPUnit\Framework\TestCase;

final class AiTextLikelihoodDetectorTest extends TestCase
{
    public function testShortHumanishTextIsNotFlagged(): void
    {
        $detector = new AiTextLikelihoodDetector();
        $result = $detector->analyze('Salut, je joue depuis 2 ans en milsim, dispo mercredi et samedi. Motivé.');

        self::assertSame(0, $result['score']);
        self::assertFalse($detector->shouldFlag($result));
    }

    public function testChatGptStyleEssayIsFlagged(): void
    {
        $detector = new AiTextLikelihoodDetector();
        $text = <<<'TXT'
Dans le cadre de ma candidature, je me permets de vous exposer mon parcours.
Il est important de noter que je suis convaincu que votre communauté incarne des valeurs qui me tiennent à cœur.
Dans un premier temps, j'ai développé un leadership collaboratif au sein d'environnements stimulants.
Dans un second temps, j'ai appris à valoriser mon parcours et à contribuer de manière significative.
Par ailleurs, il est essentiel de souligner mon engagement indéfectible et mon mindset résilient.
En conclusion, je reste à votre disposition et n'hésitez pas à me contacter pour toute information complémentaire.
Cette opportunité unique me permettrait d'enrichir mutuellement notre expérience immersive et de mettre à profit mes compétences.
TXT;

        $result = $detector->analyze($text);
        self::assertGreaterThanOrEqual(AiTextLikelihoodDetector::FLAG_THRESHOLD, $result['score']);
        self::assertTrue($detector->shouldFlag($result));
        self::assertNotSame(AiTextLikelihoodDetector::LEVEL_NONE, $result['level']);
        self::assertNotEmpty($result['signals']);
    }

    public function testAnalyzeFieldsAggregatesMotivationBlocks(): void
    {
        $detector = new AiTextLikelihoodDetector();
        $result = $detector->analyzeFields([
            'motivation_why_join' => str_repeat('Il est important de noter mon engagement indéfectible. ', 8),
            'commitment_effort' => 'Je suis convaincu que cette opportunité unique est idéale. ' . str_repeat('En outre, ', 5) . 'je reste à votre disposition.',
        ]);

        self::assertGreaterThan(0, $result['fields_scanned']);
        self::assertGreaterThan(0, $result['score']);
    }
}
