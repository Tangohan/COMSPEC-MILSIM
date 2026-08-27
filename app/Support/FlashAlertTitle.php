<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Titre (eyebrow) des alertes flash — langage métier, pas de faux « Accès refusé ».
 */
final class FlashAlertTitle
{
    public static function for(string $variant, string $message): string
    {
        $variant = strtolower(trim($variant));
        $message = trim($message);

        if ($variant === 'success') {
            return 'Succès';
        }
        if ($variant === 'warning') {
            return 'Attention';
        }
        if ($variant !== 'error') {
            return 'Information';
        }

        if ($message === '') {
            return 'Erreur';
        }

        if (preg_match('/confirmez votre adresse|e-mail avant de vous connecter|vérification.*e-mail/iu', $message) === 1) {
            return 'Confirmation requise';
        }

        if (preg_match('/session (?:expirée|invalide)|session a expiré/iu', $message) === 1) {
            return 'Session expirée';
        }

        if (preg_match('/authentification requise|connectez-vous pour|connexion requise/iu', $message) === 1) {
            return 'Connexion requise';
        }

        if (preg_match('/compte (?:n’|ne )?existe plus|compte n’est plus actif|compte verrouillé|programmé pour suppression/iu', $message) === 1) {
            return 'Compte inaccessible';
        }

        if (preg_match('/accès (?:refusé|réservé|restreint)|(?:est |sont )?réservé[es]? aux|non autorisé|pas (?:les |l[’\'])droits|autorisation insuffisante|pas l[’\']autorisation|pas habilité|habilitées?|règles de sécurité/iu', $message) === 1) {
            return 'Accès refusé';
        }

        if (preg_match('/introuvable|n’est plus (?:disponible|accessible)|n’existe pas/iu', $message) === 1) {
            return 'Introuvable';
        }

        return 'Erreur';
    }

    /** Sous-texte optionnel pour les bandeaux flash (pas les toasts). */
    public static function descriptionFor(string $variant, string $message, string $title): ?string
    {
        if (strtolower(trim($variant)) !== 'error') {
            return null;
        }

        return match ($title) {
            'Connexion requise' => 'Connectez-vous avec votre compte pour continuer.',
            'Session expirée' => 'Rechargez la page ou reconnectez-vous, puis réessayez.',
            'Accès refusé' => 'Votre profil actuel ne permet pas cette action. Contactez un administrateur si besoin.',
            'Compte inaccessible' => 'Reconnectez-vous avec un compte valide, ou contactez le support de votre communauté.',
            'Introuvable' => 'Vérifiez le lien ou revenez à la page précédente.',
            default => null,
        };
    }
}
