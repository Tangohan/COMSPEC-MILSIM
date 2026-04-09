<?php

declare(strict_types=1);

namespace App\Services\Email;

final class EmailTokenPurpose
{
    public const REGISTER_CONFIRM = 'register_confirm';
    public const DEVICE_DENY = 'device_deny';
    /** Code à usage unique pour valider le consentement coopération inter-unités. */
    public const INTERTEAM_CONSENT_OTP = 'interteam_consent_otp';
}
