<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakMarkerDetection;
use PHPUnit\Framework\TestCase;

final class AtakMarkerDetectionTest extends TestCase
{
    public function testPrefixEqualsContainsAndType(): void
    {
        $prefix = [
            'is_active' => true,
            'match_mode' => AtakMarkerDetection::MATCH_LABEL_PREFIX,
            'match_value' => 'EXFIL',
            'label' => 'Extraction',
            'radius_m' => 50,
            'confirm_arrival' => true,
        ];
        $equals = [
            'is_active' => true,
            'match_mode' => AtakMarkerDetection::MATCH_LABEL_EQUALS,
            'match_value' => 'LZ',
            'label' => 'Zone d’atterrissage',
            'radius_m' => 20,
        ];
        $contains = [
            'is_active' => true,
            'match_mode' => AtakMarkerDetection::MATCH_LABEL_CONTAINS,
            'match_value' => 'DANGER',
            'label' => 'Danger',
            'radius_m' => 100,
        ];
        $type = [
            'is_active' => true,
            'match_mode' => AtakMarkerDetection::MATCH_MARKER_TYPE,
            'match_value' => 'mil_warning',
            'label' => 'Avertissement',
            'radius_m' => 20,
        ];
        $paused = $prefix;
        $paused['is_active'] = false;

        self::assertTrue(AtakMarkerDetection::matches($prefix, ['text' => 'EXFIL Nord']));
        self::assertTrue(AtakMarkerDetection::matches($prefix, ['text' => 'exfil-1']));
        self::assertFalse(AtakMarkerDetection::matches($prefix, ['text' => 'NORD EXFIL']));
        self::assertTrue(AtakMarkerDetection::matches($equals, ['text' => 'lz']));
        self::assertFalse(AtakMarkerDetection::matches($equals, ['text' => 'LZ 1']));
        self::assertTrue(AtakMarkerDetection::matches($contains, ['text' => 'Zone DANGER immédiat']));
        self::assertTrue(AtakMarkerDetection::matches($type, ['type' => 'mil_warning']));
        self::assertFalse(AtakMarkerDetection::matches($type, ['type' => 'mil_dot']));

        $hit = AtakMarkerDetection::firstMatch([$paused, $prefix], ['text' => 'EXFIL A']);
        self::assertNotNull($hit);
        self::assertSame('Extraction', $hit['label']);

        $annotated = AtakMarkerDetection::annotate(['text' => 'EXFIL A'], $prefix);
        self::assertTrue($annotated['detection']);
        self::assertSame(50, $annotated['detection_radius_m']);
        self::assertSame('Extraction', $annotated['detection_label']);
    }

    public function testUnknownRadiusFallsBackToTwenty(): void
    {
        self::assertSame(20, AtakMarkerDetection::normalizeRadius(33));
        self::assertSame(100, AtakMarkerDetection::normalizeRadius(100));
    }
}
