<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fonctions Overwatch coupées par la communauté (Configuration ATAK > Connexion en jeu > Fonctions autorisées).
 *
 * Seuls les envois venant du jeu (clé communauté ou jeton de session Athena) sont refusés ;
 * la Tacmap web n'est pas concernée. Le suivi des positions n'est jamais coupé : il porte la liaison elle-même.
 */
final class GameFeatureGate
{
    /** Fonction => préfixes de routes POST concernés. */
    public const ROUTES = [
        'chat_enabled' => ['api/chat'],
        'jtac_enabled' => ['api/nine-line', 'api/cas', 'api/atak/designator'],
        'intel_enabled' => ['api/atak/sigint'],
        'photos_enabled' => ['api/recon/images', 'api/intel/photos'],
        'markers_enabled' => ['api/atak/marker', 'api/markers'],
    ];

    public const LABELS = [
        'chat_enabled' => 'la messagerie',
        'jtac_enabled' => 'l’appui feu (9-line, CAS, désignation)',
        'intel_enabled' => 'le renseignement',
        'photos_enabled' => 'l’envoi de photos',
        'markers_enabled' => 'l’envoi de marqueurs',
    ];

    /** Fonction visée par une requête, ou null. Les sous-routes (accusés, suppressions…) restent libres. */
    public static function featureFor(string $method, string $path): ?string
    {
        if (strtoupper($method) !== 'POST') {
            return null;
        }
        $path = trim($path, '/');
        foreach (self::ROUTES as $feature => $prefixes) {
            foreach ($prefixes as $prefix) {
                if ($path === $prefix) {
                    return $feature;
                }
            }
        }

        return null;
    }

    /** La requête vient-elle du jeu (et non d'un navigateur connecté au portail) ? */
    public static function isGameClient(): bool
    {
        if ((int) (\App\Core\Session::get('user_id') ?? 0) > 0) {
            return false;
        }

        return ComspecApiKeyAuth::presentedAuthCandidates() !== [];
    }

    /**
     * Réponse de refus si la fonction est coupée pour la communauté, sinon null.
     *
     * @param array<string, mixed> $experience GameOverwatchExperienceService::get()
     * @return array{error: string, feature: string, message: string}|null
     */
    public static function denial(array $experience, ?string $feature): ?array
    {
        if ($feature === null || !array_key_exists($feature, $experience) || !empty($experience[$feature])) {
            return null;
        }

        return [
            'error' => 'feature_disabled',
            'feature' => $feature,
            'message' => 'Votre communauté a désactivé ' . (self::LABELS[$feature] ?? 'cette fonction') . ' depuis le jeu.',
        ];
    }
}
