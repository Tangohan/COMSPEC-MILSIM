<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Stripe\StripeWebhookSignature;
use App\Support\Stripe\WebhookSignatureException;
use PHPUnit\Framework\TestCase;

final class StripeWebhookSignatureTest extends TestCase
{
    public function testVerifyRejectsInvalidSignature(): void
    {
        $this->expectException(WebhookSignatureException::class);
        $secret = 'whsec_' . base64_encode('testsecretbytes________');
        StripeWebhookSignature::verify('{"x":1}', 't=' . time() . ',v1=deadbeef', $secret, 600);
    }

    public function testVerifyAcceptsValidSignature(): void
    {
        $rawKey = 'testsecretbytes________';
        $secret = 'whsec_' . base64_encode($rawKey);
        $payload = '{"id":"evt_test"}';
        $t = time();
        $signedPayload = $t . '.' . $payload;
        $keyMaterial = base64_decode(substr($secret, strlen('whsec_')), true);
        $this->assertNotFalse($keyMaterial);
        $v1 = hash_hmac('sha256', $signedPayload, $keyMaterial, false);
        StripeWebhookSignature::verify($payload, 't=' . $t . ',v1=' . $v1, $secret, 600);
        $this->assertTrue(true);
    }
}
