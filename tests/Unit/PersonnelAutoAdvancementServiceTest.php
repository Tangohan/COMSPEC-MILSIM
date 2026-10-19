<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ElevationRequestRepository;
use App\Repositories\GradeRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\PersonnelAutoAdvancementService;
use PHPUnit\Framework\TestCase;

final class PersonnelAutoAdvancementServiceTest extends TestCase
{
    public function testNextGradeStaysInTheSameCategory(): void
    {
        // nextGrade() est pur : les dépôts sont doublés pour ne jamais ouvrir de connexion MySQL
        // (CI sans .env ni base de données).
        $svc = new PersonnelAutoAdvancementService(
            $this->createStub(TenantRepository::class),
            $this->createStub(UserRepository::class),
            $this->createStub(GradeRepository::class),
            $this->createStub(ElevationRequestRepository::class),
        );
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
