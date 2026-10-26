<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\TenantRepository;
use App\Services\Community\YearReviewAccess;
use App\Services\Community\YearReviewService;
use App\Support\YearReviewCalendar;

/**
 * Administration du site : rétrospective annuelle des joueurs (date de publication, fenêtre à la connexion,
 * aperçu avant publication, recalcul des chiffres).
 */
final class SystemYearReviewController
{
    public function index(Request $request, array $params = []): Response
    {
        $now = time();
        $calendar = YearReviewAccess::calendar();
        $thisYear = (int) date('Y', $now);
        $nextYear = $calendar->publishesInYear() ? $thisYear : $thisYear - 1;
        if ($calendar->isPublished($nextYear, $now)) {
            $nextYear++;
        }
        $tenants = [];
        try {
            $tenants = (new TenantRepository())->listBasicAll();
        } catch (\Throwable) {
        }

        return Response::view('layout.main', [
            'title' => 'Rétrospective annuelle',
            'content' => 'admin.system.year_review',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'yrCalendar' => $calendar,
            'yrNow' => $now,
            'yrCurrent' => $calendar->currentYear($now),
            'yrNext' => max(YearReviewCalendar::FIRST_YEAR, $nextYear),
            'yrPreviewYear' => max(YearReviewCalendar::FIRST_YEAR, min($thisYear, $calendar->currentYear($now) ?? $thisYear)),
            'yrTenants' => $tenants,
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $back = url('admin/system/retrospective');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $preset = (string) $request->input('publish_preset', '');
        $md = YearReviewCalendar::normalizeMd($preset === 'autre' ? (string) $request->input('publish_md', '') : $preset);
        if ($md === null) {
            Session::flash('error', 'Date de publication invalide : utilisez le format MM-JJ (par exemple 12-01 pour le 1er décembre).');

            return Response::redirect($back);
        }
        $days = max(0, min(366, (int) $request->input('popup_days', YearReviewCalendar::DEFAULT_POPUP_DAYS)));
        try {
            (new PlatformSettingsRepository())->setMany([
                YearReviewCalendar::SETTING_ENABLED => (string) $request->input('enabled', '0') === '1' ? '1' : '0',
                YearReviewCalendar::SETTING_PUBLISH_MD => $md,
                YearReviewCalendar::SETTING_POPUP_DAYS => (string) $days,
            ]);
            Session::flash('success', 'Réglages de la rétrospective enregistrés.');
        } catch (\Throwable) {
            Session::flash('error', 'Les réglages n’ont pas pu être enregistrés (table platform_settings absente ?).');
        }

        return Response::redirect($back);
    }

    public function refresh(Request $request, array $params = []): Response
    {
        $back = url('admin/system/retrospective');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $n = (new YearReviewService())->clearCache();
        Session::flash('success', $n > 0 ? 'Chiffres recalculés à la prochaine ouverture.' : 'Rien en cache : les chiffres sont déjà à jour.');

        return Response::redirect($back);
    }
}
