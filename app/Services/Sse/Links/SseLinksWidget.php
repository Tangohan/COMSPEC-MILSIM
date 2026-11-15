<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Core\Csrf;
use App\Repositories\SseEntityLinkRepository;
use App\Services\Jnet\SseLinkJnetCitations;
use App\Support\SseCerbere;
use App\Support\SseLinkCatalog as C;

/**
 * Widget « Liaisons » : UNE ligne dans chaque page d'élément du portail SSE.
 *
 *     <?php \App\Services\Sse\Links\SseLinksWidget::render('person', $pid); ?>
 *
 * Calcule le chemin de liaison de l'élément pour le lecteur de la session (SseLinkGraph : droits, confidentialité), puis rend
 * la vue partielle views/atak/sse/_links_widget.php (barre « Liaisons (N) », tiroir à onglets, fenêtre « Ajouter un lien »).
 * Jamais bloquant : une erreur est journalisée et la page s'affiche sans le widget.
 */
final class SseLinksWidget
{
    private static ?SseLinkViewer $viewerOverride = null;

    private static ?SseLinkJnetBridge $bridgeOverride = null;

    /** Remplace le lecteur de la session et la passerelle JNET (tests et rendus hors ligne). Sans argument : retour à la session. */
    public static function useViewer(?SseLinkViewer $viewer = null, ?SseLinkJnetBridge $bridge = null): void
    {
        self::$viewerOverride = $viewer;
        self::$bridgeOverride = $bridge;
    }


    /**
     * @param array<string, mixed> $options viewer (SseLinkViewer), graph (SseLinkGraph), back (adresse de retour), return (true : rend le HTML au lieu de l'écrire)
     */
    public static function render(string $type, int|string $id, array $options = []): ?string
    {
        $level = ob_get_level();
        try {
            $sl = self::data($type, (string) $id, $options);
            if ($sl === null) {
                return null;
            }
            ob_start();
            require \base_path('views/atak/sse/_links_widget.php');
            // L'indentation du gabarit pèserait la moitié de la page (jusqu'à 200 éléments) : une suite d'espaces ne vaut qu'un espace.
            $html = (string) preg_replace('/\s{2,}/', ' ', (string) ob_get_clean());
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            error_log('Liaisons SSE : widget indisponible : ' . $e->getMessage());

            return null;
        }
        if (!empty($options['return'])) {
            return $html;
        }
        echo $html;

        return null;
    }

    /**
     * Données du widget, ou null si l'élément est introuvable ou illisible pour ce lecteur (la page elle-même est alors refusée).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public static function data(string $type, string $id, array $options = []): ?array
    {
        if (!isset(C::TYPES[$type]) || $type === 'jnet' || $id === '') {
            return null;
        }
        $viewer = $options['viewer'] ?? self::$viewerOverride ?? new SseLinkSessionViewer();
        $graph = $options['graph'] ?? new SseLinkGraph($viewer, null, null, null, self::$bridgeOverride ?? new SseLinkJnetCitations($viewer->tenantId(), (int) $viewer->userId()));
        $g = $graph->resolve($type, $id);
        if (empty($g['found'])) {
            return null;
        }
        $root = $g['nodes'][$g['root']];
        $ready = SseEntityLinkRepository::ready();
        $first = $id;
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $pos = strpos($path, 'atak/sse');
        $back = (string) ($options['back'] ?? ($pos !== false ? substr($path, $pos) : 'atak/sse'));
        $citeId = \App\Services\Jnet\JnetLinkRules::sseIdFromRef(C::ref($type, $first));

        return [
            'type' => $type,
            'id' => $id,
            'ref' => C::ref($type, $id),
            'g' => $g,
            'root' => $root,
            'degraded' => !$ready,
            'canWrite' => $ready && !$viewer->isGuest() && $viewer->canWrite(C::TYPES[$type]['section']),
            'guest' => $viewer->isGuest(),
            'csrf' => Csrf::token(),
            'back' => $back,
            'cite' => $citeId !== null ? '[[sse:' . $citeId . '|' . self::neutral($root) . ']]' : '',
            'uid' => 'sl' . substr(sha1($type . ':' . $id), 0, 8),
            'urls' => [
                'search' => \url('atak/sse/liaisons/recherche'),
                'add' => \url('atak/sse/liaisons'),
                'removeBase' => \url('atak/sse/liaisons'),
                'cerberes' => \url('atak/sse/cerberes'),
                'jnet' => \url('jnet'),
                'self' => \url($back),
            ],
            'cerbere' => SseCerbere::TREATMENTS,
            'relations' => array_map(static fn (string $r): string => C::relationLabel($r, true), array_combine(C::MANUAL_RELATIONS, C::MANUAL_RELATIONS)),
            'types' => array_map(static fn (string $t): string => C::typeLabel($t), array_combine(['person', 'identity', 'paper', 'check', 'case', 'interest', 'note', 'site', 'document'], ['person', 'identity', 'paper', 'check', 'case', 'interest', 'note', 'site', 'document'])),
        ];
    }

    /** Libellé NEUTRE d'une citation JNET : la référence seule (jamais un nom), copiée dans un contenu lu par d'autres. */
    private static function neutral(array $root): string
    {
        $ref = (string) ($root['ref'] ?? '');

        return $ref !== '' ? preg_replace('/[\[\]|\r\n]+/', ' ', $ref) : (string) $root['type_label'];
    }
}
