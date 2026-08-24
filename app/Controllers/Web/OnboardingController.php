<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Portal\OnboardingPersonaCatalog;

final class OnboardingController
{
    public function index(Request $request, array $params = []): Response
    {
        $done = (bool) Session::get('onboarding_done', false);
        $selected = OnboardingPersonaCatalog::normalize((string) $request->query('persona', ''))
            ?? OnboardingPersonaCatalog::normalize((string) Session::get('onboarding_persona', ''));

        return Response::view('layout.main', [
            'title' => 'Premiers pas',
            'content' => 'portal.onboarding',
            'onboarding_done' => $done,
            'onboarding_personas' => OnboardingPersonaCatalog::all(),
            'onboarding_persona' => $selected,
        ]);
    }

    public function choosePersona(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('onboarding'));
        }

        $persona = OnboardingPersonaCatalog::normalize((string) $request->input('persona', ''));
        if ($persona === null) {
            Session::flash('error', 'Choisissez un parcours proposé.');

            return Response::redirect(url('onboarding'));
        }

        Session::set('onboarding_persona', $persona);
        Session::set('onboarding_done', false);

        return Response::redirect(url('onboarding') . '?persona=' . rawurlencode($persona));
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
