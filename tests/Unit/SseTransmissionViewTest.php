<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseTransmissionView;
use PHPUnit\Framework\TestCase;

/**
 * Fiche de transmission : valeurs lisibles, images reconnues, identité liée (photo, iris, empreintes).
 */
final class SseTransmissionViewTest extends TestCase
{
    public function testImageValuesAreRecognised(): void
    {
        self::assertTrue(SseTransmissionView::isImageValue('https://cdn.example.com/sse/photo.jpg'));
        self::assertTrue(SseTransmissionView::isImageValue('/uploads/sse/evidence/12/a.webp?v=2'));
        self::assertTrue(SseTransmissionView::isImageValue('data:image/png;base64,iVBORw0KGgo='));
        self::assertFalse(SseTransmissionView::isImageValue('photo.jpg'));
        self::assertFalse(SseTransmissionView::isImageValue('//evil.example/a.jpg'));
        self::assertFalse(SseTransmissionView::isImageValue('Type : iris, Qualité : 92'));
        self::assertFalse(SseTransmissionView::isImageValue('https://example.com/page'));
    }

    public function testLongValuesAreFolded(): void
    {
        self::assertFalse(SseTransmissionView::isLong(str_repeat('a', 220)));
        self::assertTrue(SseTransmissionView::isLong(str_repeat('a', 221)));
        self::assertSame('abc', SseTransmissionView::excerpt('abc'));
        self::assertSame(141, mb_strlen(SseTransmissionView::excerpt(str_repeat('é', 300))));
        self::assertSame('missing', SseTransmissionView::bioTone('Non relevé'));
        self::assertSame('ok', SseTransmissionView::bioTone('Gauche + droit'));
    }

    public function testFicheShowsIdentityBiometricsAndReadableValues(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak/sse/transmission_show.php');
        foreach (['Identité liée', 'Iris', 'Empreintes', 'Images transmises', 'tx-kv', 'data-sse-gallery', 'Télécharger en PDF', 'Envoyer vers Discord'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        // Plus de tableau clé / valeur aligné à droite sur une seule ligne.
        self::assertStringNotContainsString('<table>', $view);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/SsePortalController.php');
        self::assertStringContainsString("'linkedPerson' => \$linkedPerson", $ctrl);
        self::assertStringContainsString("'payloadImages' => \$payloadImages", $ctrl);
        $css = (string) file_get_contents($root . '/public/assets/css/sse_shell.css');
        self::assertStringContainsString('th[scope="row"] + td:last-child', $css);
    }
}
