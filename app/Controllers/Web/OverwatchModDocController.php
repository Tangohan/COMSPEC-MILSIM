<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Documentation intégrée au site — mod COMSPEC Overwatch (guide + formation).
 */
final class OverwatchModDocController
{
    public const GUIDE_REVISION = 1;

    public const GUIDE_REVISION_LABEL = '28 juillet 2026';

    public const FORMATION_REVISION = 1;

    public const PACK_VERSION_LABEL = '1.3.0';

    private function requireMember(): ?Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');

            return Response::redirect(url('login'));
        }
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }

        return null;
    }

    public function guide(Request $request, array $params = []): Response
    {
        $block = $this->requireMember();
        if ($block !== null) {
            return $block;
        }

        return Response::view('layout.main', [
            'content' => 'atak.mod.guide.index',
            'title' => 'Guide Overwatch — mod Arma',
            'overwatchModDocsPage' => true,
            'owGuideRevision' => self::GUIDE_REVISION,
            'owGuideRevisionLabel' => self::GUIDE_REVISION_LABEL,
            'owPackVersion' => self::PACK_VERSION_LABEL,
            'atakModUrl' => url('atak/mod'),
            'atakFormationUrl' => url('atak/mod/formation'),
            'atakUrl' => url('atak'),
        ]);
    }

    public function formation(Request $request, array $params = []): Response
    {
        $block = $this->requireMember();
        if ($block !== null) {
            return $block;
        }

        return Response::view('layout.main', [
            'content' => 'atak.mod.formation.index',
            'title' => 'Formation Overwatch — parcours opérateur',
            'overwatchModDocsPage' => true,
            'owFormationRevision' => self::FORMATION_REVISION,
            'owPackVersion' => self::PACK_VERSION_LABEL,
            'atakModUrl' => url('atak/mod'),
            'atakGuideUrl' => url('atak/mod/guide'),
            'atakSetupUrl' => url('atak/setup'),
            'atakUrl' => url('atak'),
        ]);
    }
}
