<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

/**
 * Cause lisible d'un rapport du mod, d'après son message et le diagnostic serveur ajouté à son détail :
 * distingue une vraie panne d'un refus attendu (formule, absence de communauté, session à renouveler).
 * Vide si la cause n'est pas connue.
 */
final class AtakModReportCause
{
    private const RULES = [
        'Formule sans la carte' => ['feature_unavailable'],
        'Sans communauté' => ['tenant_context_required', 'communauté non identifiée', 'communaute manquante'],
        'Compte désactivé' => ['compte athena ou fiche membre désactivé', 'account_disabled'],
        'Clé de communauté manquante' => ['aucune clé d’accès atak configurée'],
        'Joueur à reconnecter' => [
            'aucune session athena ouverte',
            'dernière session jeu fermée',
            'session jeu arrivée à son terme',
            'jeton d’accès expiré',
        ],
        'Jeton refusé, session valable' => ['session jeu valable jusqu’au'],
    ];

    public static function label(string $message, string $detail = ''): string
    {
        $blob = mb_strtolower($message . ' ' . $detail);
        foreach (self::RULES as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($blob, $needle)) {
                    return $label;
                }
            }
        }

        return preg_match('/\bhttp\s*(?:post\s*|get\s*)?(?:—\s*)?(?:code\s*)?5\d\d\b/u', $blob) === 1 ? 'Panne serveur' : '';
    }
}
