<?php

declare(strict_types=1);

namespace App\Services\Email;

final class EmailEvents
{
    public const USER_REGISTER_CONFIRMATION = 'USER_REGISTER_CONFIRMATION';
    public const NEW_COMMUNITY_MEMBER = 'NEW_COMMUNITY_MEMBER';
    public const SECURITY_ALERT = 'SECURITY_ALERT';
    public const NEW_DEVICE_LOGIN = 'NEW_DEVICE_LOGIN';
    public const MULTIPLE_LOGIN_ATTEMPTS = 'MULTIPLE_LOGIN_ATTEMPTS';
    public const EMAIL_VERIFICATION = 'EMAIL_VERIFICATION';
    public const COMMUNITY_INVITATION = 'COMMUNITY_INVITATION';
    public const PASSWORD_RESET = 'PASSWORD_RESET';
    public const COMMUNITY_CONTACT = 'COMMUNITY_CONTACT';

    /** @var list<string> */
    public const EMAIL_EVENTS = [
        self::USER_REGISTER_CONFIRMATION,
        self::NEW_COMMUNITY_MEMBER,
        self::SECURITY_ALERT,
        self::NEW_DEVICE_LOGIN,
        self::MULTIPLE_LOGIN_ATTEMPTS,
        self::EMAIL_VERIFICATION,
        self::COMMUNITY_INVITATION,
        self::PASSWORD_RESET,
        self::COMMUNITY_CONTACT,
    ];
}
