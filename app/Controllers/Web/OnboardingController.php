<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class OnboardingController
{
    public function index(Request $request, array $params = []): Response
    {
        $done = (bool) Session::get('onboarding_done', false);

        return Response::view('layout.main', [
            'title' => 'Premiers pas',
            'content' => 'portal.onboarding',
            'onboarding_done' => $done,
        ]);
    }

    public function complete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('onboarding'));
        }

        Session::set('onboarding_done', true);
        Session::flash('success', 'Parcours d’accueil terminé. Bienvenue dans votre espace.');

        return Response::redirect(url('hub'));
    }
}
