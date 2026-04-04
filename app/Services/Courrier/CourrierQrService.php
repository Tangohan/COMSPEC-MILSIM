<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * QR code data-URI pour vérification (Endroid si disponible, sinon null).
 */
final class CourrierQrService
{
    public function dataUriForText(string $text, int $sizePixels = 120): ?string
    {
        if (!class_exists(\Endroid\QrCode\Builder\Builder::class)
            || !class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
            return null;
        }
        try {
            $result = \Endroid\QrCode\Builder\Builder::create()
                ->writer(new \Endroid\QrCode\Writer\PngWriter())
                ->data($text)
                ->size($sizePixels)
                ->margin(8)
                ->build();

            return $result->getDataUri();
        } catch (\Throwable) {
            return null;
        }
    }
}
