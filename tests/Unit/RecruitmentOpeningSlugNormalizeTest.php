<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\RecruitmentOpeningRepository;
use PHPUnit\Framework\TestCase;

final class RecruitmentOpeningSlugNormalizeTest extends TestCase
{
    public function testStripsTrailingQuotEntityWithoutSemicolon(): void
    {
        $s = RecruitmentOpeningRepository::normalizePublicPageSlugFromRequest('athenasys-1resection-rec-002-2026&quot');
        self::assertSame('athenasys-1resection-rec-002-2026', $s);
    }

    public function testStripsTrailingQuotEntityWithSemicolonAndQuotes(): void
    {
        $s = RecruitmentOpeningRepository::normalizePublicPageSlugFromRequest('my-offer-slug&quot;%22"');
        self::assertSame('my-offer-slug', $s);
    }

    public function testLeavesCleanSlugUntouched(): void
    {
        $s = RecruitmentOpeningRepository::normalizePublicPageSlugFromRequest('athenasys-1resection-rec-002-2026');
        self::assertSame('athenasys-1resection-rec-002-2026', $s);
    }
}
