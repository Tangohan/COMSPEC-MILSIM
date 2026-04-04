<?php

declare(strict_types=1);

namespace App\Support\Stripe;

/**
 * Signature Stripe-Signature invalide ou hors fenêtre temporelle.
 */
final class WebhookSignatureException extends \RuntimeException
{
}
