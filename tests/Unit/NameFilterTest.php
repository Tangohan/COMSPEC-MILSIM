<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Moderation\NameFilter;
use PHPUnit\Framework\TestCase;

final class NameFilterTest extends TestCase
{
    private function filter(array $allowed = []): NameFilter
    {
        return new NameFilter(NameFilter::DEFAULT_WORDS, $allowed);
    }

    public function testFlagsTheReportedUnitName(): void
    {
        $f = $this->filter();
        self::assertSame(['penis'], $f->matchedWords('67nd of penis'));
        self::assertSame('67nd of •••••', $f->maskText('67nd of penis'));
    }

    public function testCatchesCaseAccentsLeetAndSeparators(): void
    {
        $f = $this->filter();
        self::assertTrue($f->isFlagged('67ND OF PENIS'));
        self::assertTrue($f->isFlagged('Escouade P3N1S'));
        self::assertTrue($f->isFlagged('Team p.e.n.i.s'));
        self::assertTrue($f->isFlagged('Les Enculés'));
        self::assertTrue($f->isFlagged('Fuuuck Squad'));
        self::assertTrue($f->isFlagged('Sieg  Heil company'));
    }

    public function testLeavesNormalMilsimNamesAlone(): void
    {
        $f = $this->filter();
        foreach ([
            '24th STS Gold Team SOF TACP',
            '101st Airborne Division',
            'Task Force Technique',
            'Dispute Resolution Cell',
            'Frostbite Recon',
            'Scunthorpe Rangers',
            'Compagnie de Commandement',
            '1er RPIMa',
        ] as $name) {
            self::assertFalse($f->isFlagged($name), $name);
            self::assertSame($name, $f->maskText($name));
        }
    }

    public function testWholeWordModeAcceptsPlurals(): void
    {
        $f = $this->filter();
        self::assertTrue($f->isFlagged('Les Bites'));
        self::assertTrue($f->isFlagged('Couilles Molles'));
    }

    public function testMaskKeepsLengthWithMultibyteText(): void
    {
        $f = $this->filter();
        $masked = $f->maskText('Équipe « merde » Ü');
        self::assertSame(mb_strlen('Équipe « merde » Ü'), mb_strlen($masked));
        self::assertSame('Équipe « ••••• » Ü', $masked);
    }

    public function testBlurHtmlEscapesAndNeverLeaksTheWord(): void
    {
        $f = $this->filter();
        $html = $f->blurHtml('<b>67nd</b> of penis');
        self::assertStringNotContainsString('penis', $html);
        self::assertStringContainsString('&lt;b&gt;67nd&lt;/b&gt;', $html);
        self::assertStringContainsString('class="ath-name-blur"', $html);
        self::assertStringContainsString('filter:blur', $html);
        // Déjà masqué (texte passé par name_mask_text) : même rendu.
        self::assertSame($html, $f->blurHtml($f->maskText('<b>67nd</b> of penis')));
        self::assertSame('Alpha &amp; Bravo', $f->blurHtml('Alpha & Bravo'));
    }

    public function testAllowedNameIsNotMasked(): void
    {
        $f = $this->filter(['Shark Bite']);
        self::assertFalse($f->isFlagged('Shark Bite'));
        self::assertTrue($f->isFlagged('Les Bites'));
    }

    public function testCustomListAndPartialMode(): void
    {
        $f = new NameFilter([['word' => 'Tango', 'mode' => NameFilter::MODE_PARTIAL]]);
        self::assertSame('Mega•••••Unit', $f->maskText('MegaTangoUnit'));
        $w = new NameFilter([['word' => 'tango', 'mode' => NameFilter::MODE_WORD]]);
        self::assertFalse($w->isFlagged('MegaTangoUnit'));
        self::assertTrue($w->isFlagged('Mega Tango Unit'));
    }
}
