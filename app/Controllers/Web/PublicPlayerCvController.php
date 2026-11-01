<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserPublicCvRepository;
use App\Repositories\UserRepository;
use App\Services\PublicCv\PublicCvDataLoader;
use App\Support\CommunityMediaDetails;
use App\Support\PublicCv\PublicCvPresenter;
use App\Support\PublicCv\PublicCvSettings;

/**
 * Page publique de C.V. du joueur, consultable sans connexion, et son réglage dans l’espace compte.
 *
 *   GET  /joueur/{slug}           page publique (404 si désactivée ou inconnue — sans distinguer les deux cas)
 *   GET  /account/page-publique   réglage (activer, adresse, présentation, sections visibles)
 *   POST /account/page-publique   enregistrement
 */
final class PublicPlayerCvController
{
    public function __construct(
        private ?UserPublicCvRepository $repository = null,
        private ?PublicCvDataLoader $loader = null,
    ) {
    }

    private function repo(): UserPublicCvRepository
    {
        return $this->repository ??= new UserPublicCvRepository();
    }

    private function loader(): PublicCvDataLoader
    {
        return $this->loader ??= new PublicCvDataLoader();
    }

    public function show(Request $request, array $params = []): Response
    {
        $slug = strtolower(trim((string) ($params['slug'] ?? '')));
        if (!PublicCvSettings::isValidSlug($slug)) {
            return self::notFound();
        }
        try {
            $settings = $this->repo()->findBySlug($slug);
            if ($settings === null || (int) ($settings['enabled'] ?? 0) !== 1) {
                return self::notFound();
            }
            $raw = $this->loader()->load((int) ($settings['user_id'] ?? 0), (int) ($settings['tenant_id'] ?? 0));
        } catch (\Throwable) {
            return self::notFound();
        }
        if ($raw === null) {
            return self::notFound();
        }

        $tenantSlug = trim((string) (is_array($raw['tenant'] ?? null) ? ($raw['tenant']['slug'] ?? '') : ''));
        $cv = PublicCvPresenter::present($raw, $settings, [
            'page_url' => url('joueur/' . rawurlencode($slug)),
            'community_url' => $tenantSlug !== '' ? url('c/' . rawurlencode($tenantSlug)) : null,
            'badge_url' => static fn (string $p): string => (new \App\Services\Personnel\QualificationBadgeStorageService())->publicUrl($p),
            'decoration_url' => static fn (string $p): string => (new \App\Services\Personnel\DecorationMotifStorageService())->publicUrl($p),
            'photo_url' => static fn (string $p): ?string => CommunityMediaDetails::publicUrl($p),
            'locale' => function_exists('html_lang') ? substr((string) html_lang(), 0, 2) : 'fr',
        ]);

        return Response::view('public_cv.show', ['cv' => $cv])
            ->header('X-Robots-Tag', 'noindex, follow')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public static function notFound(): Response
    {
        return Response::view('public_cv.not_found')
            ->setStatusCode(404)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Adresse de la page publique du compte connecté, seulement si elle est ouverte. */
    public static function activeUrlForUser(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }
        try {
            $row = (new UserPublicCvRepository())->findByUserId($userId);
        } catch (\Throwable) {
            return null;
        }
        $slug = trim((string) ($row['slug'] ?? ''));

        return $row !== null && (int) ($row['enabled'] ?? 0) === 1 && $slug !== '' ? url('joueur/' . rawurlencode($slug)) : null;
    }

    public function settings(Request $request, array $params = []): Response
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($userId < 1) {
            return Response::redirect(url('login'));
        }
        $repo = $this->repo();
        $ready = $repo->tableExists();
        $row = $ready ? $repo->findByUserId($userId) : null;
        $accountUser = null;
        try {
            $accountUser = $tenantId > 0 ? (new UserRepository())->findById($userId, $tenantId) : null;
        } catch (\Throwable) {
            $accountUser = null;
        }
        $callsign = trim((string) ($accountUser['callsign'] ?? ''));

        $form = [
            'enabled' => (int) ($row['enabled'] ?? 0) === 1,
            'slug' => (string) ($row['slug'] ?? ''),
            'bio' => (string) ($row['bio'] ?? ''),
            'sections' => PublicCvSettings::decodeSections(isset($row['sections_json']) ? (string) $row['sections_json'] : null),
        ];
        if ($form['slug'] === '') {
            $form['slug'] = PublicCvSettings::normalizeSlug($callsign);
        }
        $errors = [];

        if ($request->isPost()) {
            if (!Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null)) {
                Session::flash('error', 'Votre session a expiré. Rechargez la page et réessayez.');

                return Response::redirect(url('account/page-publique'));
            }
            $ready = $tenantId > 0 && $repo->ensureTable();
            if (!$ready) {
                Session::flash('error', 'Vos réglages n’ont pas pu être enregistrés pour le moment. Réessayez dans quelques minutes.');

                return Response::redirect(url('account/page-publique'));
            }
            $form['enabled'] = (string) $request->input('enabled', '0') === '1';
            $form['slug'] = PublicCvSettings::normalizeSlug((string) $request->input('slug', ''));
            $form['bio'] = PublicCvSettings::normalizeBio((string) $request->input('bio', ''));
            $form['sections'] = PublicCvSettings::sectionsFromForm($request->input('sections', []));

            if ($form['enabled'] || $form['slug'] !== '') {
                $slugError = PublicCvSettings::slugError($form['slug']);
                if ($slugError === null && $repo->slugTakenByOther($form['slug'], $userId)) {
                    $slugError = 'Cette adresse est déjà prise par un autre joueur. Essayez une variante.';
                }
                if ($slugError !== null) {
                    $errors['slug'] = $slugError;
                }
            }

            if ($errors === []) {
                try {
                    $repo->save($userId, $tenantId, $form['enabled'], $form['slug'], $form['bio'], PublicCvSettings::encodeSections($form['sections']));
                } catch (\PDOException) {
                    $errors['slug'] = 'Cette adresse vient d’être prise par un autre joueur. Essayez une variante.';
                }
            }
            if ($errors === []) {
                Session::flash('success', $form['enabled']
                    ? 'Votre page publique est en ligne.'
                    : 'Réglages enregistrés. Votre page publique est fermée : personne ne peut la consulter.');

                return Response::redirect(url('account/page-publique'));
            }
        }

        return Response::view('layout.main', [
            'content' => 'account.public_cv',
            'title' => 'Ma page publique',
            'accountHubPage' => true,
            'accountUser' => $accountUser,
            'publicCvForm' => $form,
            'publicCvErrors' => $errors,
            'publicCvReady' => $tenantId > 0,
            'publicCvBaseUrl' => url('joueur') . '/',
            'publicCvLiveUrl' => ($form['enabled'] && $row !== null && (int) ($row['enabled'] ?? 0) === 1 && (string) ($row['slug'] ?? '') !== '')
                ? url('joueur/' . rawurlencode((string) $row['slug']))
                : null,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }
}
