<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakTranslationPackRepository;
use App\Services\Security\FileRateLimiter;
use App\Support\AtakReleaseInfo;
use App\Support\AtakTranslationModBuilder;
use App\Support\AtakTranslationTemplate;
use App\Support\AtakTranslationWorkshop;

/**
 * Workshop des langues de l'ATAK natif (pages publiques, sans compte) : catalogue des packs de langue publiés par
 * la communauté depuis l'atelier /traduire-atak, fiche d'un pack, téléchargement direct du mod prêt à jouer.
 * L'abonnement Steam passe par l'objet Workshop que l'auteur a publié (identifiant renseigné à la publication).
 * L'administration du site peut retirer un pack du catalogue (contenu inapproprié).
 */
final class AtakTranslationWorkshopController
{
    private const SORTS = ['popular', 'recent', 'progress', 'name'];

    public function __construct(
        private ?AtakTranslationPackRepository $packs = null,
        private ?FileRateLimiter $limiter = null,
    ) {
        $this->packs ??= new AtakTranslationPackRepository();
        $this->limiter ??= new FileRateLimiter();
    }

    /** GET /traduire-atak/workshop?q=&tri= */
    public function catalog(Request $request, array $params = []): Response
    {
        $sort = (string) $request->query('tri', 'popular');
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'popular';
        $query = AtakTranslationPackRepository::cleanLabel($request->query('q', ''), 40);
        $ready = $this->ready();

        return Response::view('layout.marketing', [
            'content' => 'site.atak_translate_workshop',
            'cards' => $ready ? (new AtakTranslationWorkshop($this->packs))->catalog($query, $sort) : [],
            'ready' => $ready,
            'sort' => $sort,
            'query' => $query,
            'official' => AtakTranslationTemplate::officialLanguages(),
            'title' => __('site.atakw_meta_title'),
            'meta_description' => __('site.atakw_meta_description'),
            'marketingActive' => 'traduire-atak',
            'marketingStyles' => [asset_url('assets/css/atak-native.css'), asset_url('assets/css/atak-translate.css') . '?v=3'],
        ]);
    }

    /** GET /traduire-atak/workshop/{id} */
    public function item(Request $request, array $params = []): Response
    {
        $row = $this->find($params);
        if ($row === null) {
            return self::notFound();
        }
        $ws = new AtakTranslationWorkshop($this->packs);

        return Response::view('layout.marketing', [
            'content' => 'site.atak_translate_item',
            'card' => $ws->card($row),
            'hidden' => (int) $row['listed'] !== 1 || (int) $row['blocked'] === 1,
            'blocked' => (int) $row['blocked'] === 1,
            'isAdmin' => self::isAdmin(),
            'releases' => $this->repo()->releases((int) $row['id']),
            'contributors' => $this->repo()->contributors((int) $row['id']),
            'currentAtak' => (string) (AtakReleaseInfo::latest()['atak_native'] ?? ''),
            'title' => __('site.atakw_item_meta_title', ['name' => (string) $row['lang_name']]),
            'meta_description' => __('site.atakw_item_meta_description', ['name' => (string) $row['lang_name']]),
            'marketingActive' => 'traduire-atak',
            'marketingStyles' => [asset_url('assets/css/atak-native.css'), asset_url('assets/css/atak-translate.css') . '?v=3'],
        ]);
    }

    /** GET /traduire-atak/workshop/{id}/telecharger : mod prêt à jouer (zip), fabriqué contre le modèle actuel. */
    public function download(Request $request, array $params = []): Response
    {
        if ($this->limiter !== null && $this->limiter->tooManyAttempts('atak-tr:download:' . $request->ip(), 40, 3600)) {
            return (new Response())->setStatusCode(429)->header('Content-Type', 'text/plain; charset=UTF-8')->setBody('Too many downloads, try again later.');
        }
        $row = $this->find($params);
        if ($row === null || (int) $row['listed'] !== 1 || (int) $row['blocked'] === 1) {
            return self::notFound();
        }
        $ws = new AtakTranslationWorkshop($this->packs);
        $built = AtakTranslationModBuilder::playerZip($ws->entries(), $this->repo()->releasePayload((int) $row['release_id']), [
            'code' => (string) $row['lang_code'],
            'name' => (string) $row['lang_name'],
            'game' => (string) $row['game_language'],
            'author' => (string) $row['author_label'],
            'version' => (string) (AtakReleaseInfo::latest()['atak_native'] ?? $row['atak_version']),
            'site' => url('traduire-atak'),
            'release' => (int) $row['version'],
            'page' => url('traduire-atak/workshop/' . $row['public_id']),
        ], (int) (strtotime((string) $row['released_at']) ?: time()), AtakTranslationTemplate::stringtable());
        $this->repo()->countDownload((int) $row['id']);

        return (new Response())
            ->header('Content-Type', 'application/zip')
            ->header('Content-Disposition', 'attachment; filename="' . $built['file'] . '"')
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex')
            ->setBody($built['zip']);
    }

    /** POST /traduire-atak/workshop/{id}/moderer (administration du site) : retirer / remettre dans le catalogue. */
    public function moderate(Request $request, array $params = []): Response
    {
        $id = AtakTranslationPackRepository::validPublicId($params['id'] ?? null);
        if ($id === null || !self::isAdmin() || !Csrf::verify($request) || !$this->ready()) {
            return (new Response())->setStatusCode(403)->setBody('Forbidden');
        }
        $row = $this->repo()->listedPack($id, true);
        if ($row === null) {
            return self::notFound();
        }
        $this->repo()->setBlocked((int) $row['id'], (string) $request->input('action') === 'block');

        return Response::redirect(url('traduire-atak/workshop/' . $id));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function find(array $params): ?array
    {
        $id = AtakTranslationPackRepository::validPublicId($params['id'] ?? null);
        if ($id === null || !$this->ready()) {
            return null;
        }

        // Un pack retiré reste visible pour l'administration du site (pour le remettre).
        return $this->repo()->listedPack($id, self::isAdmin());
    }

    private function ready(): bool
    {
        try {
            return $this->repo()->schemaReady() && $this->repo()->workshopReady();
        } catch (\Throwable) {
            return false;
        }
    }

    private function repo(): AtakTranslationPackRepository
    {
        return $this->packs ?? new AtakTranslationPackRepository();
    }

    private static function isAdmin(): bool
    {
        try {
            return Gate::getInstance()->isPlatformAdmin();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function notFound(): Response
    {
        return Response::redirect(url('traduire-atak/workshop'));
    }
}
