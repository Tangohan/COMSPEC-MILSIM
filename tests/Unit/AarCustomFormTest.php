<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AarCustomForm;
use PHPUnit\Framework\TestCase;

final class AarCustomFormTest extends TestCase
{
    public function testNormalizeFieldsKeepsSupportedTypesAndOptions(): void
    {
        $fields = AarCustomForm::normalizeFields([
            [
                'type' => 'select',
                'label' => 'Résultat global',
                'help' => 'Avis d’ensemble',
                'required' => '1',
                'options' => "Succès\nPartiel\nÉchec",
            ],
            [
                'type' => 'checkbox',
                'label' => 'Points à revoir',
                'options' => ['Liaison', 'Liaison', 'Munitions'],
            ],
            [
                'type' => 'textarea',
                'label' => 'Commentaire libre',
            ],
        ]);

        self::assertCount(3, $fields);
        self::assertSame(AarCustomForm::TYPE_SELECT, $fields[0]['type']);
        self::assertSame('Résultat global', $fields[0]['label']);
        self::assertTrue($fields[0]['required']);
        self::assertSame(['Succès', 'Partiel', 'Échec'], $fields[0]['options']);
        self::assertSame(['Liaison', 'Munitions'], $fields[1]['options']);
        self::assertSame(AarCustomForm::TYPE_TEXTAREA, $fields[2]['type']);
        self::assertSame('Zone de texte libre', AarCustomForm::typeLabel(AarCustomForm::TYPE_TEXTAREA));
        self::assertSame('Liste déroulante', AarCustomForm::typeLabel(AarCustomForm::TYPE_SELECT));
        self::assertSame('Cases à cocher', AarCustomForm::typeLabel(AarCustomForm::TYPE_CHECKBOX));
        self::assertSame('Question courte', AarCustomForm::typeLabel(AarCustomForm::TYPE_TEXT));
    }

    public function testNormalizeFieldsKeepsStableIds(): void
    {
        $fields = AarCustomForm::normalizeFields([
            ['id' => 'issue', 'type' => 'select', 'label' => 'Issue', 'options' => ['Succès']],
            ['id' => 'issue', 'type' => 'text', 'label' => 'Commentaire'],
        ]);

        self::assertSame('issue', $fields[0]['id']);
        self::assertSame('q2', $fields[1]['id']);
    }

    public function testCollectAndPresentAnswers(): void
    {
        $fields = AarCustomForm::normalizeFields([
            ['id' => 'q1', 'type' => 'text', 'label' => 'Indicatif', 'required' => true],
            ['id' => 'q2', 'type' => 'select', 'label' => 'Issue', 'options' => ['Succès', 'Échec'], 'required' => true],
            ['id' => 'q3', 'type' => 'checkbox', 'label' => 'Accord', 'required' => true],
            ['id' => 'q4', 'type' => 'checkbox', 'label' => 'Supports', 'options' => ['Radio', 'Carte']],
        ]);

        $bundle = AarCustomForm::collectAnswers($fields, [
            'q1' => 'Alpha',
            'q2' => 'Succès',
            'q3' => '1',
            'q4' => ['Radio', 'Inconnu'],
        ]);

        self::assertSame('Alpha', $bundle['answers']['q1']);
        self::assertSame('Succès', $bundle['answers']['q2']);
        self::assertTrue($bundle['answers']['q3']);
        self::assertSame(['Radio'], $bundle['answers']['q4']);
        self::assertSame([], AarCustomForm::missingRequired($fields, $bundle['answers']));

        $rows = AarCustomForm::presentAnswers($fields, $bundle['answers']);
        self::assertSame('Oui', $rows[2]['display']);
        self::assertSame('Radio', $rows[3]['display']);
    }

    public function testMissingRequiredDetectsBlankAndUnchecked(): void
    {
        $fields = AarCustomForm::normalizeFields([
            ['id' => 'q1', 'type' => 'text', 'label' => 'Indicatif', 'required' => true],
            ['id' => 'q2', 'type' => 'checkbox', 'label' => 'Accord', 'required' => true],
        ]);

        $bundle = AarCustomForm::collectAnswers($fields, []);
        $missing = AarCustomForm::missingRequired($fields, $bundle['answers']);

        self::assertContains('Indicatif', $missing);
        self::assertContains('Accord', $missing);
    }
}
