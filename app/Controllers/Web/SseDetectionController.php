<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\Detection\SseDetectionService;
use App\Services\Sse\SseAccessCodeService;

/**
 * Bureau SSE › Analyse › « Détections Cerbère » (atak/sse/detections) : les règles de Cerbère-Lecture qui proposent le
 * type, les thèmes et l'urgence des remontées, par thème, avec leur état, leur poids, leurs exclusions, leurs déclenchements
 * sur 30 jours et leur taux de mauvaises détections ; « Testez un texte » ; historique des changements.
 *
 *   GET  atak/sse/detections            la page
 *   POST atak/sse/detections/tester     test en direct (rien n'est écrit) ; JSON {html} pour le script, sinon la page
 *   POST atak/sse/detections/regle      réglage d'une règle (fenêtre d'édition)
 *   POST atak/sse/detections/retablir   rétablir le lexique livré
 *
 * Droits : ceux de « Gestion des fiches » (SseFieldNoteAdminController::sessionRights) : lire la page et tester un texte avec la
 * lecture des fiches de renseignement ; régler et rétablir avec le droit « gérer », jamais en session invitée. Chaque écriture
 * vérifie le jeton et un débit limité, et s'inscrit au journal SSE (cerbere.lecture.rule, cerbere.lecture.reset).
 */
final class SseDetectionController
{
    public const PATH = 'atak/sse/detections';

    public const WRITES_PER_HOUR = 120;

    public const TABS = ['regles' => 'Règles', 'tester' => 'Testez un texte', 'historique' => 'Historique'];

    private SseDetectionService $service;

    /** @var \Closure(): array{tenant: int, user: int, guest: bool, read: bool, manage: bool, label: string} */
    private \Closure $rights;

    /** @var \Closure(string, int, int): bool */
    private \Closure $tooMany;

    public function __construct(?SseDetectionService $service = null, ?\Closure $rights = null, ?\Closure $tooMany = null)
    {
        $this->service = $service ?? new SseDetectionService();
        $this->rights = $rights ?? static fn (): array => SseFieldNoteAdminController::sessionRights();
        $this->tooMany = $tooMany ?? static fn (string $key, int $max, int $window): bool => (new FileRateLimiter())->tooManyAttempts($key, $max, $window);
    }

    /** Lien vers la page pour la session, ou null si elle ne peut pas la lire (cartes des propositions). */
    public static function manageUrlForSession(): ?string
    {
        try {
            $r = SseFieldNoteAdminController::sessionRights();

            return $r['tenant'] > 0 && $r['read'] ? url(self::PATH) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function index(Request $request, array $params = []): Response
    {
        $rights = ($this->rights)();
        if ($rights['tenant'] < 1 || !$rights['read']) {
            return $this->denied();
        }

        return $this->page($rights, null);
    }

    public function test(Request $request, array $params = []): Response
    {
        $rights = ($this->rights)();
        $fetch = $request->header('X-Requested-With') === 'fetch';
        if ($rights['tenant'] < 1 || !$rights['read']) {
            return $fetch ? Response::json(['html' => ''], 403) : $this->denied();
        }
        if (!self::csrfOk($request)) {
            return $fetch ? Response::json(['html' => ''], 419) : $this->back('error', 'Votre page est restée ouverte trop longtemps : rechargez-la puis recommencez.', 'tester');
        }
        $result = $this->service->liveTest($rights['tenant'], $request->inputString('text'), $request->inputString('kind'));
        if ($fetch) {
            return Response::json(['html' => self::render('atak/sse/_cerbere_detection_result', ['cdxTest' => $result, 'cdxManage' => $rights['manage']])]);
        }

        return $this->page($rights, $result);
    }

    public function saveRule(Request $request, array $params = []): Response
    {
        $rights = ($this->rights)();
        if ($guard = $this->writeGuard($request, $rights)) {
            return $guard;
        }
        $res = $this->service->saveRule($rights['tenant'], $request->all(), ['id' => $rights['user'], 'label' => $rights['label'] !== '' ? $rights['label'] : 'Bureau SSE']);
        $rule = preg_replace('/[^A-Z0-9-]/', '', (string) $request->inputString('rule_id'));

        return $this->back($res['ok'] ? 'success' : 'error', $res['message'], $res['ok'] && $rule !== '' ? 'regle-' . $rule : 'regles');
    }

    public function reset(Request $request, array $params = []): Response
    {
        $rights = ($this->rights)();
        if ($guard = $this->writeGuard($request, $rights)) {
            return $guard;
        }
        if ($request->inputString('confirm') !== '1') {
            return $this->back('error', 'Cochez la confirmation pour rétablir le lexique livré.', 'regles');
        }
        $res = $this->service->reset($rights['tenant'], ['id' => $rights['user'], 'label' => $rights['label'] !== '' ? $rights['label'] : 'Bureau SSE']);

        return $this->back($res['ok'] ? 'success' : 'error', $res['message'], 'regles');
    }

    /** @param array{tenant: int, user: int, guest: bool, read: bool, manage: bool, label: string} $rights */
    private function writeGuard(Request $request, array $rights): ?Response
    {
        if ($rights['tenant'] < 1 || !$rights['read']) {
            return $this->denied();
        }
        if (!self::csrfOk($request)) {
            return $this->back('error', 'Votre page est restée ouverte trop longtemps : rechargez-la puis recommencez.', 'regles');
        }
        if (!$rights['manage'] || $rights['guest']) {
            return $this->back('error', 'Régler les détections demande le droit « gérer » des fiches de renseignement.', 'regles');
        }
        if (($this->tooMany)('sse-detections:' . $rights['tenant'] . ':' . $rights['user'], self::WRITES_PER_HOUR, 3600)) {
            return $this->back('error', 'Trop de réglages en une heure : réessayez plus tard.', 'regles');
        }

        return null;
    }

    /**
     * @param array{tenant: int, user: int, guest: bool, read: bool, manage: bool, label: string} $rights
     * @param array<string, mixed>|null $test
     */
    private function page(array $rights, ?array $test): Response
    {
        $tenant = $rights['tenant'];

        return Response::view('atak.sse.cerbere_detections', [
            'title' => 'Détections Cerbère',
            'activeNav' => 'cerbere-detections',
            'tabs' => self::TABS,
            'overview' => $this->service->overview($tenant),
            'history' => $this->service->repo()->history($tenant),
            'falseFriends' => \App\Services\Sse\Detection\SseDetectionLexicon::shipped()['false_friends'],
            'cdxTest' => $test,
            'cdxManage' => $rights['manage'] && !$rights['guest'],
            'isGuest' => $rights['guest'],
            'clearanceUntil' => (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0),
            'guestLabel' => (string) Session::get('sse_guest_label', ''),
            'sseTheme' => function_exists('sse_ui_theme') ? sse_ui_theme() : 'bureau',
            'canManage' => $rights['manage'],
            'canGrant' => false,
            'canWrite' => $rights['manage'],
        ]);
    }

    /** @param array<string, mixed> $vars */
    public static function render(string $view, array $vars): string
    {
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };

        return $render(base_path('views/' . $view . '.php'), $vars);
    }

    private function denied(): Response
    {
        Session::flash('error', 'Vos droits ne permettent pas de lire les détections : il faut lire les fiches de renseignement.');

        return Response::redirect(url('atak/sse/accueil'));
    }

    private function back(string $type, string $message, string $anchor): Response
    {
        Session::flash($type, $message);

        return Response::redirect(url(self::PATH) . '#' . $anchor);
    }

    private static function csrfOk(Request $request): bool
    {
        $token = $request->input('_csrf_token');

        return is_string($token) && $token !== '' && Csrf::validate($token);
    }
}
