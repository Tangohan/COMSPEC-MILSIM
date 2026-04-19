<?php

declare(strict_types=1);

namespace App\Services\Email;

final class EmailTokenPurpose
{
    public const REGISTER_CONFIRM = 'register_confirm';
    public const DEVICE_DENY = 'device_deny';
    /** Code à usage unique pour valider le consentement coopération inter-unités. */
    public const INTERTEAM_CONSENT_OTP = 'interteam_consent_otp';
    /** Code OTP de connexion pour les comptes sécurité. */
    public const LOGIN_SECURITY_OTP = 'login_security_otp';
    /** Code de test depuis les préférences (ne valide pas une connexion). */
    public const LOGIN_OTP_MAILBOX_SELF_TEST = 'login_otp_mailbox_self_test';
}
