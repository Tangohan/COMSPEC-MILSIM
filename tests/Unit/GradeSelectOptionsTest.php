<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\GradeSelectOptions;
use PHPUnit\Framework\TestCase;

final class GradeSelectOptionsTest extends TestCase
{
    /** @var array{set: bool, locale: mixed} */
    private array $savedLocale = ['set' => false, 'locale' => null];

    /** Libellés de groupe selon la langue de la page : on fige le français, puis on restaure. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->savedLocale = [
            'set' => array_key_exists('__app_locale', $GLOBALS),
            'locale' => $GLOBALS['__app_locale'] ?? null,
        ];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        if ($this->savedLocale['set']) {
            $GLOBALS['__app_locale'] = $this->savedLocale['locale'];
        } else {
            unset($GLOBALS['__app_locale']);
        }
        parent::tearDown();
    }

    public function testGroupLabelsFollowEnglishPages(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        $labels = array_column(GradeSelectOptions::groups(array_slice($this->catalog(), 0, 6)), 'label');
        self::assertContains('Officers', $labels);
        self::assertNotContains('Officiers', $labels);
    }

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

    public function testCustomSystemKeepsTheCommunityOrderAndItsOwnCategoryNames(): void
    {
        $custom = static fn (int $id, string $long, int $cat, string $catLabel, int $order): array => [
            'id' => $id, 'label_short' => $long, 'label_long' => $long, 'label_otan' => null,
            'category_code' => 'T9S1_' . $cat, 'category_label' => $catLabel, 'category_sort' => $cat,
            'sort_order' => $order, 'country_code' => 'ZZ', 'system_code' => 'T9_GAR', 'system_label' => 'Grand Army of the Republic',
            'system_owner_tenant_id' => 9,
        ];
        $grades = [
            $custom(11, 'Clone Trooper', 10, 'Clone Troopers', 10),
            $custom(12, 'Clone Captain', 20, 'Clone Officers', 20),
            $custom(13, 'Veteran Clone Trooper', 10, 'Clone Troopers', 20),
            $custom(14, 'Clone Commander', 20, 'Clone Officers', 30),
        ];

        $groups = GradeSelectOptions::groups($grades);
        self::assertSame(['Clone Officers', 'Clone Troopers'], array_map(static fn (array $g): string => $g['label'], $groups));
        self::assertSame([14, 12], array_map(static fn (array $g): int => (int) $g['id'], $groups[0]['grades']));
        self::assertSame([13, 11], array_map(static fn (array $g): int => (int) $g['id'], $groups[1]['grades']));

        $mixed = GradeSelectOptions::groups(array_merge($grades, array_slice($this->catalog(), 0, 1)), 'FR');
        self::assertSame('France · Officiers', $mixed[0]['label']);
        self::assertSame('Grand Army of the Republic · Clone Officers', $mixed[1]['label']);
    }

    public function testOptionLabelShowsLongThenShortName(): void
    {
        self::assertSame('Sergent (Sgt)', GradeSelectOptions::label(['id' => 5, 'label_short' => 'Sgt', 'label_long' => 'Sergent']));
        self::assertSame('Colonel', GradeSelectOptions::label(['id' => 3, 'label_short' => 'Colonel', 'label_long' => 'Colonel']));
    }
}
