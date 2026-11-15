<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

/**
 * Explications des tâches du tableau de bord (bloc « Tâches en attente »).
 *
 * Une seule source : la clé « task » de chaque pastille dans config/back_office_menu.php, pour les textes fixes ; un
 * fournisseur de pastille qui implémente PendingTaskDetailProvider compose lui-même les siens quand ils dépendent de la
 * communauté (formule : nom, dates). Une pastille sans explication garde l'affichage d'avant (titre et lien).
 *
 * Clé « task » :
 *   explain            quoi, pour qui ; chaîne, ou {one, other} selon le nombre ; %d = le nombre
 *   consequence        ce qui arrive si on ne fait rien
 *   consequence_urgent variante quand la pastille est rouge (facultatif)
 *   action             ce qu'il faut faire, en une phrase
 *   button             intitulé du bouton (verbe d'action)
 *   who                qui peut le faire (facultatif)
 *   help               aide « Qu'est-ce que c'est ? » (facultatif)
 *   path               page du bouton si ce n'est pas celle de la pastille (facultatif)
 */
final class PendingTaskExplainer
{
    public function __construct(private BackOfficeMenuBadges $badges)
    {
    }

    /**
     * @param string $locale « fr » ou « en » (les textes fixes restent en français : la page entière est traduite
     *                       à l'affichage ; seuls les fournisseurs qui composent leurs textes s'en servent)
     */
    public function describe(string $key, int $count, bool $important, int $tenantId, int $userId, string $locale = 'fr'): ?PendingTaskDetail
    {
        try {
            $provider = $this->badges->providerFor($key);
            if ($provider instanceof PendingTaskDetailProvider) {
                $detail = $provider->detail($tenantId, $userId, $locale);
                if ($detail !== null) {
                    return $detail;
                }
            }
        } catch (\Throwable $e) {
            error_log('[bo-menu] explication ' . $key . ' : ' . $e->getMessage());
        }

        return $this->fromConfig($key, $count, $important);
    }

    /** Explication fixe de la config, ou null si elle est absente ou incomplète. */
    public function fromConfig(string $key, int $count, bool $important): ?PendingTaskDetail
    {
        $task = $this->badges->taskConfig($key);
        $declared = $this->badges->declared()[$key] ?? null;
        if ($task === [] || $declared === null) {
            return null;
        }
        $explain = $task['explain'] ?? '';
        if (is_array($explain)) {
            $explain = $count === 1 ? ($explain['one'] ?? '') : ($explain['other'] ?? '');
        }
        $consequence = $important && self::text($task, 'consequence_urgent') !== ''
            ? self::text($task, 'consequence_urgent')
            : self::text($task, 'consequence');
        $explainText = is_string($explain) ? $explain : '';
        $action = self::text($task, 'action');
        $button = self::text($task, 'button');
        if (trim($explainText) === '' || $consequence === '' || $action === '' || $button === '') {
            return null;
        }
        $path = trim(self::text($task, 'path'), '/');

        return new PendingTaskDetail(
            '',
            str_replace('%d', (string) $count, $explainText),
            $consequence,
            $action,
            $button,
            $path !== '' ? $path : $declared['path'],
            self::text($task, 'who'),
            self::text($task, 'help'),
        );
    }

    /** @param array<string, mixed> $task */
    private static function text(array $task, string $key): string
    {
        $v = $task[$key] ?? '';

        return is_string($v) ? trim($v) : '';
    }
}
