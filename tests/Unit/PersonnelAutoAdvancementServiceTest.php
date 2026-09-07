<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\PersonnelAutoAdvancementService;
use PHPUnit\Framework\TestCase;

final class PersonnelAutoAdvancementServiceTest extends TestCase
{
    public function testNextGradeStaysInTheSameCategory(): void
    {
        $svc = new PersonnelAutoAdvancementService();
        $grades = [
            ['id' => 1, 'grade_category_id' => 10, 'sort_order' => 10, 'label_short' => 'Sdt'],
            ['id' => 2, 'grade_category_id' => 10, 'sort_order' => 20, 'label_short' => 'Cpl'],
            ['id' => 3, 'grade_category_id' => 11, 'sort_order' => 30, 'label_short' => 'Slt'],
        ];
        $next = $svc->nextGrade($grades, 1);
        self::assertNotNull($next);
        self::assertSame(2, (int) $next['id']);

        $top = $svc->nextGrade($grades, 2);
        self::assertNull($top);
    }
}
