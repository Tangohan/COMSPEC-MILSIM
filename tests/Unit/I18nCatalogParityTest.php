<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class I18nCatalogParityTest extends TestCase
{
    public function testEnglishCommonCatalogCoversFrenchKeys(): void
    {
        $root = dirname(__DIR__, 2);
        $fr = require $root . '/lang/fr/common.php';
        $en = require $root . '/lang/en/common.php';
        self::assertIsArray($fr);
        self::assertIsArray($en);
        foreach (array_keys($fr) as $key) {
            self::assertArrayHasKey($key, $en, 'Missing EN common key: ' . $key);
        }
    }

    public function testPortalNavigationPhrasesHaveEnglishEntries(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
        $root = dirname(__DIR__, 2);
        $en = require $root . '/lang/en/nav.php';
        $nav = require $root . '/config/navigation.php';
        self::assertIsArray($en);
        self::assertIsArray($nav);

        $phrases = [];
        $walk = static function ($node) use (&$walk, &$phrases): void {
            if (!is_array($node)) {
                return;
            }
            foreach (['label', 'title', 'description', 'placeholder', 'eyebrow', 'cta_label', 'empty_message'] as $k) {
                if (isset($node[$k]) && is_string($node[$k]) && trim($node[$k]) !== '') {
                    $phrases[] = trim($node[$k]);
                }
            }
            foreach ($node as $v) {
                if (is_array($v)) {
                    $walk($v);
                }
            }
        };
        $walk($nav);

        $missing = [];
        foreach (array_unique($phrases) as $phrase) {
            $slug = i18n_slug($phrase);
            if ($slug === '') {
                continue;
            }
            if (!isset($en[$slug])) {
                $missing[] = $slug . ' ← ' . $phrase;
            }
        }

        self::assertSame([], $missing);
    }
}
