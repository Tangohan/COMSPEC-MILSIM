<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;
use App\Services\Recruitment\InviteLinkService;

/**
 * Lien d'invitation en cours d'usage (/i/<code>) conservé pendant l'inscription, la confirmation de l'adresse e-mail
 * et la connexion, pour ramener le visiteur sur l'action du lien. Le code voyage aussi dans l'adresse
 * (register?invite=…, lien de confirmation) : la session n'est qu'un relais quand l'adresse l'a perdu.
 */
final class PendingInviteLink
{
    private const SESSION_KEY = 'pending_invite_link';

    /** Une semaine : de quoi recevoir et ouvrir l'e-mail de confirmation. */
    private const TTL_SEC = 604800;

    public static function remember(string $code): void
    {
        $code = strtolower(trim($code));
        if (!InviteLinkService::isWellFormedCode($code)) {
            return;
        }
        Session::set(self::SESSION_KEY, ['code' => $code, 'expires_at' => time() + self::TTL_SEC]);
    }

    public static function code(): ?string
    {
        $raw = Session::get(self::SESSION_KEY);
        if (!is_array($raw)) {
            return null;
        }
        $code = strtolower(trim((string) ($raw['code'] ?? '')));
        if ((int) ($raw['expires_at'] ?? 0) < time() || !InviteLinkService::isWellFormedCode($code)) {
            self::forget();

            return null;
        }

        return $code;
    }

    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /** Code reçu en paramètre s'il est bien formé, sinon celui de la session. */
    public static function fromParamOrSession(string $param): ?string
    {
        $param = strtolower(trim($param));
        if ($param !== '' && InviteLinkService::isWellFormedCode($param)) {
            return $param;
        }

        return self::code();
    }

    /** Adresse de retour après connexion : la page du lien. */
    public static function continueUrl(): ?string
    {
        $code = self::code();

        return $code !== null ? InviteLinkService::publicUrl($code) : null;
    }
}
