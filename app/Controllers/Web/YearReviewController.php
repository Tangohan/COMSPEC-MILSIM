<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserUiTourRepository;
use App\Services\Community\YearReviewAccess;
use App\Services\Community\YearReviewService;
use App\Support\YearReviewCalendar;
use App\Support\YearReviewStory;

/**
 * Rétrospective annuelle du joueur (façon « Wrapped ») : stories plein écran, individuelles pour tous,
 * avec le bilan de l'unité quand la formule de la communauté le comprend.
 *
 *   GET  /retrospective              dernière année publiée
 *   GET  /retrospective/{year}       une année ; ?apercu=1 pour l'administration du site avant publication
 *   GET  /retrospective/{year}/unite bilan de l'unité seul
 *   POST /retrospective/{year}/vu    « déjà vue » : ne s'ouvre plus d'elle-même à la connexion
 */
final class YearReviewController
{
    public function __construct(private ?YearReviewService $service = null)
    {
        $this->service ??= new YearReviewService();
    }

    public function latest(Request $request, array $params = []): Response
    {
        $year = YearReviewAccess::calendar()->currentYear(time());
        if ($year === null) {
            Session::flash('error', 'La rétrospective de l’année n’est pas encore publiée.');

            return Response::redirect(url('account'));
        }

        return Response::redirect(url('retrospective/' . $year));
    }

    public function show(Request $request, array $params = []): Response
    {
        return $this->render($request, (int) ($params['year'] ?? 0), false);
    }

    public function community(Request $request, array $params = []): Response
    {
        return $this->render($request, (int) ($params['year'] ?? 0), true);
    }

    public function dismiss(Request $request, array $params = []): Response
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        $year = (int) ($params['year'] ?? 0);
        $ajax = (string) $request->header('X-Requested-With') === 'fetch';
        if ($userId > 0 && $year >= YearReviewCalendar::FIRST_YEAR && Csrf::validate(is_string($t = $request->input('_csrf_token')) ? $t : null)) {
            self::markSeen($userId, $year);
        }
        if ($ajax) {
            return Response::json(['ok' => true]);
        }
        if ((string) $request->input('go', '') === 'open') {
            return Response::redirect(url('retrospective/' . $year));
        }

        return Response::redirect(self::sameSiteReferer() ?? url('/'));
    }

    public static function markSeen(int $userId, int $year): void
    {
        try {
            (new UserUiTourRepository())->dismiss($userId, YearReviewCalendar::seenKey($year), true);
        } catch (\Throwable) {
        }
        Session::set('year_review_seen_' . $year, 1);
    }

    private function render(Request $request, int $year, bool $communityOnly): Response
    {
        $userId = (int) (Session::get('user_id') ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($userId < 1) {
            return Response::redirect(url('login'));
        }
        $now = time();
        $calendar = YearReviewAccess::calendar();
        $admin = YearReviewAccess::isPlatformAdmin();
        $preview = $admin && (string) $request->query('apercu', '') === '1';
        // Administration du site : aperçu du bilan de n'importe quelle communauté.
        $otherTenant = $preview ? max(0, (int) $request->query('communaute', 0)) : 0;
        if ($otherTenant > 0 && $otherTenant !== $tenantId) {
            $tenantId = $otherTenant;
            $communityOnly = true;
        }
        if ($year < YearReviewCalendar::FIRST_YEAR || $year > (int) date('Y', $now) || (!$calendar->isPublished($year, $now) && !$preview)) {
            Session::flash('error', 'Cette rétrospective n’est pas encore publiée.');

            return Response::redirect(url('account'));
        }

        $collectiveAllowed = YearReviewAccess::collectiveAllowed($tenantId);
        if ($communityOnly && !$collectiveAllowed && !$preview) {
            if (YearReviewAccess::isManager()) {
                return \App\Support\PlanFeatureDenial::upgradeView(YearReviewAccess::FEATURE);
            }

            return Response::redirect(url('retrospective/' . $year));
        }

        $review = $tenantId > 0 ? $this->service->forTenant($tenantId, $year, $now) : null;
        $player = $communityOnly || $review === null ? null : ($review['players']['u' . $userId] ?? null);
        if (is_array($player) && empty($player['active'])) {
            $player = null;
        }
        $tenantName = '';
        try {
            $tenantName = (string) ((new TenantRepository())->findById($tenantId)['name'] ?? '');
        } catch (\Throwable) {
        }
        if (!$communityOnly && !$preview) {
            self::markSeen($userId, $year);
        }

        $story = YearReviewStory::slides(
            $year,
            $player,
            $review['community'] ?? null,
            [
                'tenant_name' => $tenantName,
                'partial' => (bool) ($review['partial'] ?? false),
                'until' => (string) ($review['until'] ?? ''),
                'collective' => $collectiveAllowed || $preview,
                'upsell' => !$collectiveAllowed && YearReviewAccess::isManager(),
                'community_only' => $communityOnly,
            ]
        );

        return Response::view('year_review.page', [
            'yrYear' => $year,
            'yrSlides' => $story,
            'yrCallsign' => is_array($player) ? (string) $player['name'] : '',
            'yrTenantName' => $tenantName,
            'yrCloseUrl' => self::sameSiteReferer() ?? url('account'),
            'yrPreview' => $preview,
            'yrYears' => $calendar->publishedYears($now),
        ]);
    }

    private static function sameSiteReferer(): ?string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($ref === '' || $host === '' || str_contains($ref, '/retrospective')) {
            return null;
        }
        $refHost = parse_url($ref, PHP_URL_HOST);
        $hostOnly = strtolower(explode(':', $host)[0]);

        return is_string($refHost) && strtolower($refHost) === $hostOnly ? $ref : null;
    }
}
