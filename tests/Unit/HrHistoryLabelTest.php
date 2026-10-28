<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HrHistoryLabel;
use PHPUnit\Framework\TestCase;

final class HrHistoryLabelTest extends TestCase
{
    /** @var array{set: bool, locale: mixed} */
    private array $savedLocale = ['set' => false, 'locale' => null];

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedLocale = [
            'set' => array_key_exists('__app_locale', $GLOBALS),
            'locale' => $GLOBALS['__app_locale'] ?? null,
        ];
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

    public function testFrenchHistoryLinesAreTranslatedPieceByPieceOnEnglishPages(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        self::assertSame('Assignment: Not set → DAG-1 (A)', HrHistoryLabel::forPage('Affectation : Non renseigné → DAG-1 (A)'));
        self::assertSame('Promotion confirmed', HrHistoryLabel::forPage('Élévation confirmée'));
    }

    public function testFrenchPagesKeepTheStoredText(): void
    {
        $GLOBALS['__app_locale'] = 'fr';
        self::assertSame('Affectation : Non renseigné → DAG-1 (A)', HrHistoryLabel::forPage('Affectation : Non renseigné → DAG-1 (A)'));
    }
}
