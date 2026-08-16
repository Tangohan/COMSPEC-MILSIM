<?php

declare(strict_types=1);

/**
 * Traduction d’une erreur technique en message d’aide affichable par l’utilisateur.
 * Chargé très tôt (avant le bootstrap complet) pour rester disponible même quand la
 * panne survient pendant l’amorçage.
 */

if (!function_exists('athena_error_hint')) {
    /**
     * Message d’aide affiché à l’utilisateur (jamais le détail technique) pour les pannes
     * dont la cause est identifiable et actionnable : schéma non migré, BDD injoignable.
     * Chaîne vide = on s’en tient au message générique de la page 500.
     */
    function athena_error_hint(string $raw): string
    {
        $english = function_exists('locale') && locale() === 'en';

        $schemaOutdated = str_contains($raw, "doesn't exist")
            || str_contains($raw, 'Base table')
            || str_contains($raw, '1146')
            || str_contains($raw, '42S02')
            || str_contains($raw, 'Unknown column')
            || str_contains($raw, '42S22');
        if ($schemaOutdated) {
            return $english
                ? 'This feature is not ready on the server yet: the database is missing a recent update. Ask an administrator to run the database update, then try again.'
                : 'Cette fonctionnalité n’est pas encore prête sur le serveur : la base de données n’a pas reçu une mise à jour récente. Demandez à un administrateur de lancer la mise à jour de la base, puis réessayez.';
        }

        $dbUnreachable = str_contains($raw, 'SQLSTATE[HY000] [2002]')
            || str_contains($raw, 'SQLSTATE[HY000] [1045]')
            || str_contains($raw, 'Connection refused')
            || str_contains($raw, 'server has gone away')
            || str_contains($raw, 'Database connection failed')
            || str_contains($raw, 'Operation not permitted');
        if ($dbUnreachable) {
            return $english
                ? 'The database is temporarily unreachable. The technical team has been notified — please try again in a few minutes.'
                : 'La base de données est momentanément injoignable. L’équipe technique a été prévenue — réessayez dans quelques minutes.';
        }

        $routesMissing = str_contains($raw, 'routes/web.php')
            || str_contains($raw, 'Fichier de routage manquant');
        if ($routesMissing) {
            return $english
                ? 'The site is being updated on the server. Please try again in a few minutes. If the problem continues, ask an administrator to redeploy the application.'
                : 'Le site est en cours de mise à jour sur le serveur. Réessayez dans quelques minutes. Si le problème continue, demandez à un administrateur de redéployer l’application.';
        }

        return '';
    }
}
