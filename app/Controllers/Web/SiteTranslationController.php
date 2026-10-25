<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\I18n\CommunityLanguages;
use App\Services\I18n\SiteTranslationService;
use App\Services\Security\FileRateLimiter;

/**
 * Traduction du site par la communauté (/traduire-site) : liste des langues et avancement, espace de traduction
 * d'une langue (textes du site page par page, recherche, filtres), API de l'espace (lecture, enregistrement,
 * propositions) et dictionnaire JavaScript d'une langue (textes posés par les scripts des pages).
 */
final class SiteTranslationController
{
    public function __construct(
        private ?SiteTranslationService $service = null,
        private ?FileRateLimiter $limiter = null,
    ) {
    }

    private function limiter(): FileRateLimiter
    {
        return $this->limiter ??= new FileRateLimiter();
    }

    public function hub(Request $request, array $params = []): Response
    {
        $ready = $this->ready();

        return Response::view('layout.marketing', [
            'content' => 'site.site_translate',
            'title' => __('site.sitetr_meta_title'),
            'meta_description' => __('site.sitetr_meta_description'),
            'marketingActive' => 'traduire-site',
            'marketingStyles' => [asset_url('assets/css/site-contribute.css')],
            'sitetrReady' => $ready,
            'sitetrLanguages' => $ready ? $this->safe(fn (): array => $this->service()->languages(), []) : [],
            'sitetrUser' => ContributorController::user(),
        ]);
    }

    public function workspace(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        $lang = $this->ready() && CommunityLanguages::validCode($code) ? $this->safe(fn (): ?array => $this->service()->language($code), null) : null;
        if ($lang === null) {
            return Response::redirect(url('traduire-site'));
        }
        $user = ContributorController::user();
        $uid = (int) ($user['id'] ?? 0);

        return Response::view('layout.marketing', [
            'content' => 'site.site_translate_workspace',
            'title' => __('site.sitetr_ws_title', ['lang' => $lang['name']]),
            'meta_description' => __('site.sitetr_meta_description'),
            'marketingActive' => 'traduire-site',
            'marketingStyles' => [asset_url('assets/css/site-contribute.css')],
            'marketingScripts' => [asset_url('assets/js/site-translate.js')],
            'sitetrLang' => $lang,
            'sitetrUser' => $user,
            'sitetrCanReview' => $this->safe(fn (): bool => $this->service()->canReview($uid, $code), false),
        ]);
    }

    /** GET /traduire-site/{code}/phrases?q=&f=&p= */
    public function phrases(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        if (!$this->ready() || $this->service()->language($code) === null) {
            return Response::json(['ok' => false, 'error' => 'unknown_language'], 404);
        }
        $uid = (int) (ContributorController::user()['id'] ?? 0);
        $page = $this->service()->page(
            $code,
            mb_substr((string) $request->query('q', ''), 0, 200),
            (string) $request->query('f', 'all'),
            (int) $request->query('p', 1),
            $uid,
        );

        return Response::json(['ok' => true, 'can_review' => $this->service()->canReview($uid, $code)] + $page)
            ->header('Cache-Control', 'no-store');
    }

    /** POST /traduire-site/{code}/phrases {h, text} */
    public function save(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        $uid = (int) (ContributorController::user()['id'] ?? 0);
        $body = self::body($request);
        if ($uid < 1) {
            return Response::json(['ok' => false, 'error' => 'login'], 401);
        }
        if (!Csrf::verify($request) || !$this->ready() || $this->service()->language($code) === null) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        // Garde-fou contre les envois en rafale : 600 textes par heure et par membre.
        if ($this->limiter()->tooManyAttempts('site-tr:save:' . $uid, 600, 3600)) {
            return Response::json(['ok' => false, 'errors' => ['rate']], 429);
        }
        $this->limiter()->hit('site-tr:save:' . $uid, 3600);

        return Response::json($this->service()->save($code, (string) ($body['h'] ?? ''), (string) ($body['text'] ?? ''), $uid));
    }

    /** POST /traduire-site/{code}/phrases/effacer {h} (traducteur de la langue, administration) */
    public function clear(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        $uid = (int) (ContributorController::user()['id'] ?? 0);
        if ($uid < 1 || !Csrf::verify($request) || !$this->ready()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        return Response::json($this->service()->clear($code, (string) (self::body($request)['h'] ?? ''), $uid));
    }

    /** POST /traduire-site/propositions/{id} {action: accept|reject} */
    public function decide(Request $request, array $params = []): Response
    {
        $uid = (int) (ContributorController::user()['id'] ?? 0);
        if ($uid < 1 || !Csrf::verify($request) || !$this->ready()) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        return Response::json($this->service()->decide((int) ($params['id'] ?? 0), (string) (self::body($request)['action'] ?? '') === 'accept', $uid));
    }

    /**
     * GET /traduire-site/dico/{code}.js : textes validés de la langue, posés sur le dictionnaire anglais du
     * traducteur des scripts (assets/js/i18n_dom.js) ; chargé par HtmlTranslator::withDomScripts.
     */
    public function dictionary(Request $request, array $params = []): Response
    {
        $code = (string) ($params['code'] ?? '');
        $exact = [];
        $tpl = [];
        if (CommunityLanguages::validCode($code)) {
            foreach (CommunityLanguages::dictionary($code)['t'] as $fr => $text) {
                if (preg_match('/%\d/', (string) $fr)) {
                    $tpl[] = [(string) $fr, (string) $text];
                } else {
                    $exact[(string) $fr] = (string) $text;
                }
            }
        }
        $data = json_encode(['exact' => (object) $exact, 'tpl' => $tpl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        $js = "/* Textes de la langue « " . (CommunityLanguages::validCode($code) ? $code : '?') . " » traduits par la communauté (/traduire-site). */\n"
            . "(function (d, o) {\n"
            . "    if (!d || !o) { return; }\n"
            . "    d.exact = d.exact || {};\n"
            . "    Object.keys(o.exact).forEach(function (k) { d.exact[k] = o.exact[k]; });\n"
            . "    var seen = {};\n"
            . "    o.tpl.forEach(function (p) { seen[p[0]] = true; });\n"
            . "    d.tpl = (d.tpl || []).filter(function (p) { return !seen[p[0]]; }).concat(o.tpl);\n"
            . "})(window.ATHENA_I18N_EN, " . ($data !== false ? $data : 'null') . ");\n";

        return (new Response())
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=31536000, immutable')
            ->setBody($js);
    }

    /** @return array<string, mixed> */
    private static function body(Request $request): array
    {
        $raw = (string) file_get_contents('php://input');
        $data = $raw !== '' ? json_decode($raw, true) : null;

        return is_array($data) ? $data : $request->all();
    }

    private function ready(): bool
    {
        try {
            return $this->service()->repo()->schemaReady();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @param T $fallback
     * @return T
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function service(): SiteTranslationService
    {
        return $this->service ??= new SiteTranslationService();
    }
}
