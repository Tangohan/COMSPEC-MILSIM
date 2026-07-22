<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * QR code data-URI pour vérification (QrPngGenerator : Endroid / phpqrcode / PNG zlib).
 */
final class CourrierQrService
{
    public function dataUriForText(string $text, int $sizePixels = 120): ?string
    {
        $png = (new \App\Services\Qr\QrPngGenerator())->png($text, $sizePixels, 8);
        if ($png === null) {
            return null;
        }

        return 'data:' . $png['mime'] . ';base64,' . base64_encode($png['body']);
    }
}
