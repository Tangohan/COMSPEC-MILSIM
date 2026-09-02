<?php

declare(strict_types=1);

namespace Tests\Courrier;

use App\Services\Courrier\DocumentSignatureService;
use PHPUnit\Framework\TestCase;

final class DocumentSignatureServiceTest extends TestCase
{
    public function testLegacyHashWithoutVerificationCode(): void
    {
        $svc = new DocumentSignatureService(
            $this->createMock(\App\Repositories\Courrier\CourrierDocumentRepository::class),
            $this->createMock(\App\Repositories\Courrier\UserSignatureRepository::class)
        );
        $doc = [
            'body_rendered' => 'Hello',
            'reference_number' => 'R1',
            'subject' => 'S',
            'issuer_label' => 'I',
            'destination_label' => 'D',
            'signature_data_json' => ['signature_image_path' => '/x.png'],
        ];
        $at = '2026-01-01 12:00:00';
        $h1 = $svc->computeContentHash($doc, $at);
        $h2 = $svc->computeContentHash($doc, $at);
        $this->assertSame($h1, $h2);
        $this->assertSame(64, strlen($h1));
    }

    public function testExtendedHashWithVerification(): void
    {
        $svc = new DocumentSignatureService(
            $this->createMock(\App\Repositories\Courrier\CourrierDocumentRepository::class),
            $this->createMock(\App\Repositories\Courrier\UserSignatureRepository::class)
        );
        $doc = [
            'body_rendered' => 'Hello',
            'reference_number' => 'R1',
            'subject' => 'S',
            'issuer_label' => 'I',
            'destination_label' => 'D',
            'classification_level' => 'interne',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'signed_by' => 42,
            'signature_data_json' => [
                'verification_code' => 'SIG-2026-01-01-ABCDEF12',
                'signature_image_path' => '/x.png',
            ],
        ];
        $h = $svc->computeContentHash($doc, '2026-01-01 12:00:00');
        $this->assertSame(64, strlen($h));
    }

    public function testDisplayNameUsesReadableDefault(): void
    {
        self::assertSame('Signature principale', DocumentSignatureService::displayName(''));
        self::assertSame('Signature principale', DocumentSignatureService::displayName('   '));
        self::assertSame('Signature de rentrée', DocumentSignatureService::displayName("  Signature   de\trentrée "));
        self::assertSame(80, mb_strlen(DocumentSignatureService::displayName(str_repeat('A', 120))));
    }
}
