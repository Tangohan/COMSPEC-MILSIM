<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;

/**
 * Candidature commencée sans compte : on garde la communauté visée (et l'offre ou le lien d'invitation éventuels)
 * pendant la connexion ou la création du compte, puis on ramène la personne sur le formulaire de cette communauté.
 * La session ne sert que de relais : seuls l'identifiant public de la communauté, un numéro d'offre et un code de
 * lien bien formé sont acceptés, jamais une adresse libre.
 */
final class PendingEnlistmentReturn
{
    private const SESSION_KEY = 'pending_enlistment_return';

    /** Une semaine : de quoi recevoir et ouvrir l'e-mail de confirmation du compte. */
    private const TTL_SEC = 604800;

    public static function remember(string $slug, int $openingId = 0, string $inviteCode = ''): bool
    {
        $slug = self::cleanSlug($slug);
        if ($slug === null) {
            return false;
        }
        $inviteCode = strtolower(trim($inviteCode));
        if ($inviteCode !== '' && !\App\Services\Recruitment\InviteLinkService::isWellFormedCode($inviteCode)) {
            $inviteCode = '';
        }
        Session::set(self::SESSION_KEY, [
            'slug' => $slug,
            'opening' => max(0, $openingId),
            'invite' => $inviteCode,
            'expires_at' => time() + self::TTL_SEC,
        ]);

        return true;
    }

    /** Adresse du formulaire à rejoindre après la connexion, sans consommer le relais. */
    public static function continueUrl(): ?string
    {
        $raw = Session::get(self::SESSION_KEY);
        if (!is_array($raw)) {
            return null;
        }
        $slug = self::cleanSlug((string) ($raw['slug'] ?? ''));
        if ($slug === null || (int) ($raw['expires_at'] ?? 0) < time()) {
            self::forget();

            return null;
        }
        $query = [];
        $opening = (int) ($raw['opening'] ?? 0);
        if ($opening > 0) {
            $query[] = 'ouverture=' . $opening;
        }
        $invite = (string) ($raw['invite'] ?? '');
        if ($invite !== '' && \App\Services\Recruitment\InviteLinkService::isWellFormedCode($invite)) {
            $query[] = 'lien=' . rawurlencode($invite);
        }

        return url('c/' . rawurlencode($slug) . '/enlistment') . ($query !== [] ? '?' . implode('&', $query) : '');
    }

    /** Adresse de retour, puis oubli : à appeler une seule fois, au moment de la redirection. */
    public static function consumeUrl(): ?string
    {
        $url = self::continueUrl();
        self::forget();

        return $url;
    }

    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    private static function cleanSlug(string $slug): ?string
    {
        $slug = trim($slug);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,98}$/', $slug) === 1 ? $slug : null;
    }
}
