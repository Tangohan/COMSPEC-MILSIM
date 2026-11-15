<?php

declare(strict_types=1);

namespace App\Services\Sse\Attach;

use App\Core\Csrf;
use App\Services\Sse\Links\SseLinkSessionViewer;
use App\Services\Sse\Links\SseLinkViewer;
use App\Support\SseAttachCatalog as A;
use App\Support\SseLinkCatalog as C;

/**
 * Fenêtre « Rattacher » : le composant unique de rattachement du bureau SSE.
 *
 *   Une ligne d'inclusion par page (la fenêtre n'est rendue qu'une fois, quel que soit le nombre d'appels) :
 *       <?php \App\Services\Sse\Attach\SseAttachWindow::render(); ?>
 *   Un bouton par pièce (ou par sélection) :
 *       <?= \App\Services\Sse\Attach\SseAttachWindow::button('note', $id) ?>
 *       <button type="button" data-ra-open data-ra-type="person" data-ra-collect="input[name='ids[]']:checked">…</button>
 *   La section « Éléments rattachés » d'une cible :
 *       <?php \App\Services\Sse\Attach\SseAttachWindow::section('case', $id); ?>
 *
 * Vues partielles views/atak/sse/_attach_window.php et _attached_section.php, script public/assets/js/sse-rattacher.js, styles
 * public/assets/css/sse-rattacher.css (préfixe ra-). Jamais bloquant : une erreur est journalisée et la page s'affiche sans.
 */
final class SseAttachWindow
{
    private static bool $rendered = false;

    private static ?SseLinkViewer $viewerOverride = null;

    /** Remplace le lecteur de la session (tests et rendus hors ligne). Sans argument : retour à la session. */
    public static function useViewer(?SseLinkViewer $viewer = null): void
    {
        self::$viewerOverride = $viewer;
        self::$rendered = false;
    }

    private static function viewer(): SseLinkViewer
    {
        return self::$viewerOverride ?? new SseLinkSessionViewer();
    }

    /** La fenêtre (une seule par page). */
    public static function render(bool $return = false): ?string
    {
        if (self::$rendered) {
            return $return ? '' : null;
        }
        self::$rendered = true;
        $level = ob_get_level();
        try {
            $viewer = self::viewer();
            $ra = [
                'csrf' => Csrf::token(),
                'guest' => $viewer->isGuest(),
                'ready' => SseAttachService::ready(),
                'urls' => [
                    'context' => \url('atak/sse/rattacher/contexte'), 'targets' => \url('atak/sse/rattacher/cibles'), 'target' => \url('atak/sse/rattacher/cible'),
                    'preview' => \url('atak/sse/rattacher/apercu'), 'attach' => \url('atak/sse/rattacher'), 'undo' => \url('atak/sse/rattacher/annuler'),
                    'create' => \url('atak/sse/rattacher/creer'), 'base' => \url('atak/sse/rattacher'),
                ],
                'natures' => A::NATURES, 'scopes' => A::SCOPES, 'colors' => A::TAG_COLORS, 'templates' => A::REASON_TEMPLATES, 'levels' => A::LEVELS,
            ];
            ob_start();
            require \base_path('views/atak/sse/_attach_window.php');
            $html = (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            error_log('Rattacher SSE : fenêtre indisponible : ' . $e->getMessage());

            return $return ? '' : null;
        }
        if ($return) {
            return $html;
        }
        echo $html;

        return null;
    }

    /**
     * Bouton qui ouvre la fenêtre pour une pièce. La page appelle render() une fois, hors de tout formulaire.
     *
     * @param array<string, string> $options label, short (libellé court sur téléphone), class, title
     */
    public static function button(string $type, int|string $id, array $options = []): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        if (!A::isSource($type) || (int) $id < 1) {
            return '';
        }
        $label = (string) ($options['label'] ?? 'Rattacher');
        $html = '<button type="button" class="' . $h($options['class'] ?? 'btn btn--ghost') . ' ra-open" data-ra-open data-ra-pieces="' . $h(C::ref($type, (string) (int) $id)) . '" aria-haspopup="dialog"'
            . (isset($options['title']) ? ' title="' . $h($options['title']) . '"' : '') . '>'
            . '<svg class="ra-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>'
            . '<span>' . $h($label) . '</span></button>';

        return $html;
    }

    /**
     * Section « Éléments rattachés » d'une cible : liste triable et filtrable, actions ouvrir, modifier, détacher, déplacer.
     *
     * @param array<string, mixed> $options return (true : rend le HTML), back (adresse de retour des formulaires sans script), slim (true : en-tête d'une ligne, pour une colonne étroite)
     */
    public static function section(string $type, int|string $id, array $options = []): ?string
    {
        $level = ob_get_level();
        try {
            $viewer = self::viewer();
            $svc = new SseAttachService($viewer);
            $as = $svc->attached($type, (string) $id);
            if ($as === null) {
                return !empty($options['return']) ? '' : null;
            }
            $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
            $pos = strpos($path, 'atak/sse');
            $as['back'] = (string) ($options['back'] ?? ($pos !== false ? substr($path, $pos) : 'atak/sse'));
            $as['csrf'] = Csrf::token();
            $as['type'] = $type;
            $as['id'] = (string) $id;
            $as['base'] = \url('atak/sse/rattacher');
            $as['source_ok'] = A::isSource($type);
            // Le bouton « Rattacher à… » n'apparaît que si le lecteur peut écrire dans la section de cet élément.
            $as['can_attach'] = !$viewer->isGuest() && $as['source_ok'] && $viewer->canWrite(C::TYPES[$type]['section'] ?? '');
            $as['natures'] = A::NATURES;
            $as['slim'] = !empty($options['slim']);
            $as['uid'] = 'ras' . substr(sha1($type . ':' . $id), 0, 8);
            ob_start();
            require \base_path('views/atak/sse/_attached_section.php');
            $html = (string) ob_get_clean() . (string) self::render(true);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            error_log('Rattacher SSE : section indisponible : ' . $e->getMessage());

            return !empty($options['return']) ? '' : null;
        }
        if (!empty($options['return'])) {
            return $html;
        }
        echo $html;

        return null;
    }
}
