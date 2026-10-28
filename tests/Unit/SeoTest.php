<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Seo;
use PHPUnit\Framework\TestCase;

final class SeoTest extends TestCase
{
    public function testPrivatePathsAreNeverIndexed(): void
    {
        self::assertTrue(Seo::isPrivatePath('/back-office/organisation'));
        self::assertTrue(Seo::isPrivatePath('/admin/system'));
        self::assertTrue(Seo::isPrivatePath('/locale/en'));
        self::assertTrue(Seo::isPrivatePath('/c/alpha/forum'));
        self::assertTrue(Seo::isPrivatePath('/c/alpha/setup'));
        self::assertFalse(Seo::isPrivatePath('/c/alpha'));
        self::assertFalse(Seo::isPrivatePath('/c/alpha/unite/bravo'));
        self::assertFalse(Seo::isPrivatePath('/communities'));
        self::assertFalse(Seo::isPrivatePath('/contact'));
    }

    public function testLanguageVariantsOfAUrl(): void
    {
        $alt = Seo::alternates('https://example.test/c/alpha?page=2');
        self::assertSame('https://example.test/c/alpha?page=2&lang=fr', $alt['fr']);
        self::assertSame('https://example.test/c/alpha?page=2&lang=en', $alt['en']);
        self::assertSame('https://example.test/c/alpha?page=2', $alt['x-default']);
        self::assertSame('https://example.test/c/alpha', Seo::withoutLang('https://example.test/c/alpha?lang=en'));
        self::assertSame('https://example.test/c/alpha?lang=en', Seo::withLang('https://example.test/c/alpha?lang=fr', 'en'));
    }

    public function testHiddenCommunityIsNotIndexable(): void
    {
        self::assertTrue(Seo::communityIndexable([]));
        self::assertTrue(Seo::communityIndexable(['registry_listed' => true]));
        self::assertFalse(Seo::communityIndexable(['registry_listed' => false]));
    }
}
