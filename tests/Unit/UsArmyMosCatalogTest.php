<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Rbac\MilitaryOperationalRoleCatalog;
use App\Services\Rbac\UsArmyMosCatalog;
use App\Support\MosInputValidator;
use PHPUnit\Framework\TestCase;

final class UsArmyMosCatalogTest extends TestCase
{
    public function testEveryMappedSlugExistsInOperationalCatalog(): void
    {
        $catalogSlugs = [];
        foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
            $catalogSlugs[$e['slug']] = true;
        }
        foreach (array_keys(UsArmyMosCatalog::byJobRoleSlug()) as $slug) {
            self::assertArrayHasKey($slug, $catalogSlugs, 'MOS défini pour un slug absent du catalogue : ' . $slug);
        }
    }

    public function testMappedCodesPassInputValidator(): void
    {
        foreach (UsArmyMosCatalog::byJobRoleSlug() as $slug => $pair) {
            $code = MosInputValidator::normalizeCode($pair[0]);
            self::assertSame(strtoupper($pair[0]), $code, 'Code MOS invalide pour ' . $slug);
            $title = MosInputValidator::normalizeSpecialtyTitle($pair[1]);
            self::assertSame($pair[1], $title, 'Intitulé MOS invalide pour ' . $slug);
        }
    }
}
