<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\GradeSelectOptions;
use PHPUnit\Framework\TestCase;

final class GradeSelectOptionsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        // Ordre du catalogue : officiers du plus bas au plus haut, sous-officiers dans l'autre sens.
        return [
            ['id' => 1, 'label_short' => 'Sous-lieutenant', 'label_long' => 'Sous-lieutenant', 'label_otan' => 'OF-1', 'category_code' => 'OFFICIER', 'sort_order' => 11, 'country_code' => 'FR'],
            ['id' => 2, 'label_short' => 'Lieutenant', 'label_long' => 'Lieutenant', 'label_otan' => 'OF-1', 'category_code' => 'OFFICIER', 'sort_order' => 12, 'country_code' => 'FR'],
            ['id' => 3, 'label_short' => 'Colonel', 'label_long' => 'Colonel', 'label_otan' => 'OF-5', 'category_code' => 'OFFICIER', 'sort_order' => 16, 'country_code' => 'FR'],
            ['id' => 4, 'label_short' => 'Major', 'label_long' => 'Major', 'label_otan' => 'OR-9', 'category_code' => 'SOUS_OFFICIER', 'sort_order' => 21, 'country_code' => 'FR'],
            ['id' => 5, 'label_short' => 'Sgt', 'label_long' => 'Sergent', 'label_otan' => 'OR-5', 'category_code' => 'SOUS_OFFICIER', 'sort_order' => 25, 'country_code' => 'FR'],
            ['id' => 6, 'label_short' => 'Cpl', 'label_long' => 'Caporal', 'label_otan' => 'OR-3', 'category_code' => 'MDR', 'sort_order' => 32, 'country_code' => 'FR'],
            ['id' => 7, 'label_short' => 'Gen.', 'label_long' => 'General', 'label_otan' => 'O-10', 'category_code' => 'OFFICIER', 'sort_order' => 20, 'country_code' => 'US'],
            ['id' => 8, 'label_short' => 'Captain', 'label_long' => 'Captain', 'label_otan' => 'O-3', 'category_code' => 'OFFICIER', 'sort_order' => 13, 'country_code' => 'US'],
        ];
    }

    public function testGradesAreSortedFromHighestToLowestByCategory(): void
    {
        $ids = array_map(static fn (array $g): int => (int) $g['id'], GradeSelectOptions::sorted(array_slice($this->catalog(), 0, 6)));

        self::assertSame([3, 2, 1, 4, 5, 6], $ids);
    }

    public function testGroupsPutTheCommunitySystemFirstAndNameCountriesOnlyWhenMixed(): void
    {
        $groups = GradeSelectOptions::groups($this->catalog(), 'US');
        $labels = array_map(static fn (array $g): string => $g['label'], $groups);

        self::assertSame(['États-Unis · Officiers', 'France · Officiers', 'France · Sous-officiers', 'France · Militaires du rang'], $labels);
        self::assertSame([7, 8], array_map(static fn (array $g): int => (int) $g['id'], $groups[0]['grades']));

        $frOnly = GradeSelectOptions::groups(array_slice($this->catalog(), 0, 6));
        self::assertSame('Officiers', $frOnly[0]['label']);
    }

    public function testOptionLabelShowsLongThenShortName(): void
    {
        self::assertSame('Sergent (Sgt)', GradeSelectOptions::label(['id' => 5, 'label_short' => 'Sgt', 'label_long' => 'Sergent']));
        self::assertSame('Colonel', GradeSelectOptions::label(['id' => 3, 'label_short' => 'Colonel', 'label_long' => 'Colonel']));
    }
}
