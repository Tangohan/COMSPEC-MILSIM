<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseEntityLinkRepository;
use App\Services\Jnet\SseLinkJnetCitations;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkSessionViewer;
use App\Services\Sse\Links\SseLinkViewer;
use App\Services\Sse\Links\SseLinkWriter;
use App\Services\Sse\SseCerbereReport;
use App\Support\SseLinkCatalog as C;

/**
 * Widget « Liaisons » du portail SSE : recherche d'un élément à relier, pose et retrait d'un lien manuel, panneau « Cerbères ».
 *
 * La communauté vient de la session (jamais de la requête). Chaque écriture exige le jeton CSRF ; les deux éléments d'un lien
 * doivent être visibles du demandeur (un élément d'une autre communauté, ou qu'il n'a pas le droit de lire, répond comme
 * un élément qui n'existe pas), et le droit d'écrire de la section de l'élément de départ.
 */
final class SseLinksController
{
    /** Types proposés par défaut dans la recherche (les plus utiles pour un compte rendu). */
    private const DEFAULT_TYPES = ['person', 'identity', 'paper', 'check', 'case', 'interest', 'note', 'site', 'document'];

    public function __construct(private ?SseLinkViewer $viewer = null)
    {
    }

    private function viewer(): SseLinkViewer
    {
        return $this->viewer ??= new SseLinkSessionViewer();
    }

    private function graph(): SseLinkGraph
    {
        $v = $this->viewer();

        return new SseLinkGraph($v, null, null, null, new SseLinkJnetCitations($v->tenantId(), (int) $v->userId()));
    }

    /** Recherche d'éléments reliables (JSON) : référence ou nom, parmi ce que le demandeur a le droit de lire. */
    public function search(Request $request, array $params = []): Response
    {
        $q = trim(mb_substr($request->queryString('q'), 0, 80));
        $types = array_values(array_filter(array_map('trim', explode(',', $request->queryString('types'))), static fn (string $t): bool => isset(C::TYPES[$t]) && $t !== 'jnet'));
        $types = array_slice($types === [] ? self::DEFAULT_TYPES : $types, 0, 9);
        if (mb_strlen($q) < 2) {
            return Response::json(['items' => []]);
        }
        $graph = $this->graph();
        $items = [];
        foreach ($types as $type) {
            foreach ($graph->search($type, $q, 5) as $hit) {
                $items[] = $hit;
            }
        }

        return Response::json(['items' => array_slice($items, 0, 30)]);
    }

    /** Pose d'un lien manuel (formulaire du widget, avec ou sans JavaScript). */
    public function store(Request $request, array $params = []): Response
    {
        $back = self::safeBack($request->inputString('back'));
        if (!self::csrfOk($request)) {
            return $this->flash('error', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.', $back);
        }
        $from = C::parseRef($request->inputString('from'));
        $to = C::parseRef($request->inputString('to'));
        if ($from === null || $to === null) {
            return $this->flash('error', SseLinkWriter::NOT_FOUND, $back);
        }
        $res = (new SseLinkWriter($this->viewer(), $this->graph()))->add($from['type'], $from['id'], $to['type'], $to['id'], $request->inputString('relation'), $request->inputString('reason'));

        return $res['ok']
            ? $this->flash('success', 'Lien enregistré.', $back)
            : $this->flash('error', (string) $res['error'], $back);
    }

    /** Retrait d'un lien MANUEL (jamais un lien Cerbère ni un lien déduit). */
    public function remove(Request $request, array $params = []): Response
    {
        $back = self::safeBack($request->inputString('back'));
        if (!self::csrfOk($request)) {
            return $this->flash('error', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.', $back);
        }
        $res = (new SseLinkWriter($this->viewer(), $this->graph()))->remove((int) ($params['id'] ?? 0));

        return $res['ok']
            ? $this->flash('success', 'Lien retiré.', $back)
            : $this->flash('error', (string) $res['error'], $back);
    }

    /** « Cerbères » : ce que fait le système tout seul, s'il est actif, et ses derniers événements (lecture seule). */
    public function cerberes(Request $request, array $params = []): Response
    {
        $v = $this->viewer();
        if (!$v->canRead('analyse')) {
            Session::flash('error', 'Droit manquant : lire la section « Analyse ».');

            return Response::redirect(url('atak/sse'));
        }

        return (new SseIdentityRegistryController())->page('atak.sse.cerberes', [
            'title' => 'Cerbères',
            'cerbere' => (new SseCerbereReport())->build($v->tenantId()),
            'activeNav' => 'cerberes',
        ]);
    }

    private static function csrfOk(Request $request): bool
    {
        $token = $request->input('_csrf_token');

        return is_string($token) && $token !== '' && Csrf::validate($token);
    }

    /** Retour : une adresse du portail uniquement (jamais un domaine extérieur). */
    public static function safeBack(string $back): string
    {
        $back = ltrim(trim($back), '/');
        if ($back !== '' && preg_match('#^atak/sse(/[A-Za-z0-9/_.-]*)?(\?[A-Za-z0-9=&%_.,-]*)?(\#[A-Za-z0-9_-]*)?$#', $back) === 1 && !str_contains($back, '..')) {
            return url($back);
        }

        return url('atak/sse');
    }

    private function flash(string $type, string $message, string $to): Response
    {
        Session::flash($type, $message);

        return Response::redirect($to);
    }

    /** Disponibilité de la table des liens de provenance (pour le message du mode dégradé). */
    public static function manualReady(): bool
    {
        return SseEntityLinkRepository::ready();
    }
}
