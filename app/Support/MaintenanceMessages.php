<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\I18n\Translator;

/** Textes partagés par la page publique et les modèles de l’administration. */
final class MaintenanceMessages
{
    private const PRESETS = ['standard', 'short', 'friendly', 'security', 'infra', 'hotfix'];

    // Reconnaître l’ancien modèle déjà enregistré sans remplacer les messages personnalisés.
    private const LEGACY_DEFAULT = 'Athena est momentanément fermé le temps d’une mise à jour. Nous simplifions la gestion des communautés : un accès plus clair par membre, des rôles mieux alignés, des dossiers plus simples à suivre. Vos données restent en sécurité. Vous n’avez rien à faire de votre côté. Pendant cette période, le site et les outils associés restent inaccessibles. Merci de votre patience, et de la confiance que vous accordez à Athena.';

    /** @return array<string, array{label: string, title: string, message: string}> */
    public static function presets(string $language = 'fr'): array
    {
        $translator = new Translator();
        $presets = [];
        foreach (self::PRESETS as $key) {
            $prefix = 'maintenance.presets.' . $key . '.';
            $presets[$key] = [
                'label' => $translator->get($prefix . 'label', [], $language),
                'title' => $translator->get($prefix . 'title', [], $language),
                'message' => $translator->get($prefix . 'message', [], $language),
            ];
        }

        return $presets;
    }

    /**
     * Traduire les modèles reconnus, conserver le Markdown saisi librement par l’administrateur.
     *
     * @return array{title: string, message: string}
     */
    public static function resolve(string $title, string $message, string $language = 'fr'): array
    {
        $title = trim($title);
        $message = trim($message);
        $presets = self::presets($language);
        $normalizedMessage = self::normalize($message);
        $genericMessages = [
            '',
            'Le service est momentanément indisponible.',
            'Maintenance en cours. Merci de réessayer dans quelques minutes.',
            'Nos équipes déploient une mise à jour stratégique. Le service revient très bientôt.',
            'Athena est momentanément fermé le temps d’une mise à jour.',
            'Athena is temporarily closed for an update.',
            self::LEGACY_DEFAULT,
            'Athena is temporarily closed for an update. We are simplifying community management: clearer access per member, better-aligned roles, files that are easier to follow. Your data stays safe. There is nothing you need to do. During this period, the site and its related tools are unavailable. Thank you for your patience, and for the trust you place in Athena.',
            'Athena is temporarily closed for an update. We are simplifying community management: clearer access per member, better-aligned roles, and easier-to-follow records. Your data remains safe. You don’t need to do anything. During this period, the site and associated tools remain unavailable. Thank you for your patience, and for the trust you place in Athena.',
        ];
        if (in_array($normalizedMessage, array_map(self::normalize(...), $genericMessages), true)) {
            $message = $presets['standard']['message'];
        }

        foreach (['fr', 'en'] as $sourceLanguage) {
            foreach (self::presets($sourceLanguage) as $key => $preset) {
                if (self::normalize($title) === self::normalize($preset['title'])) {
                    $title = $presets[$key]['title'];
                }
                if ($normalizedMessage === self::normalize($preset['message'])) {
                    $message = $presets[$key]['message'];
                }
            }
        }

        if ($title === '' || in_array($title, ['Maintenance en cours', 'Maintenance globale en cours'], true)) {
            $title = $presets['standard']['title'];
        }

        return ['title' => $title, 'message' => $message];
    }

    private static function normalize(string $text): string
    {
        $text = str_replace(['’', '\\n', '\\r'], ["'", ' ', ' '], $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
