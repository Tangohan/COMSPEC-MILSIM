<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Portal\OnboardingPersonaCatalog;
use App\Repositories\UserProfileRepository;

final class OnboardingController
{
    public function __construct(private UserProfileRepository $userProfileRepository) {}

    public function index(Request $request, array $params = []): Response
    {
        $userId = (int) Session::get('user_id', 0);
        $persisted = $this->userProfileRepository->getOnboardingState($userId);
        $persistedPersona = OnboardingPersonaCatalog::normalize((string) ($persisted['persona'] ?? ''));
        $done = $persistedPersona !== null
            ? !empty($persisted['completed_at'])
            : (bool) Session::get('onboarding_done', false);
        $requested = OnboardingPersonaCatalog::normalize((string) $request->query('persona', ''));
        $selected = $requested
            ?? $persistedPersona
            ?? OnboardingPersonaCatalog::normalize((string) Session::get('onboarding_persona', ''));
        // Le lien de la vitrine constitue déjà un choix explicite. Le conserver permet
        // de retrouver le même parcours après la connexion et lors d'une visite ultérieure.
        if ($requested !== null) {
            $previous = OnboardingPersonaCatalog::normalize((string) Session::get('onboarding_persona', ''));
            Session::set('onboarding_persona', $requested);
            if ($previous !== null && $previous !== $requested) {
                Session::set('onboarding_done', false);
                $done = false;
            }
            if ($persistedPersona !== $requested) {
                $this->saveState($requested, [], false);
                $done = false;
            }
        }

        return Response::view('layout.main', [
            'title' => 'Premiers pas',
            'content' => 'portal.onboarding',
            'onboarding_done' => $done,
            'onboarding_personas' => OnboardingPersonaCatalog::all(),
            'onboarding_persona' => $selected,
            'onboarding_completed_steps' => $selected !== null ? $this->completedSteps($selected) : [],
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
        $this->saveState($persona, [], false);

        return Response::redirect(url('onboarding') . '?persona=' . rawurlencode($persona));
    }

    public function toggleStep(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('onboarding'));
        }

        $persona = OnboardingPersonaCatalog::normalize((string) Session::get('onboarding_persona', ''));
        $step = (int) $request->input('step', -1);
        $catalog = OnboardingPersonaCatalog::all();
        if ($persona === null || !isset($catalog[$persona]['steps'][$step])) {
            Session::flash('error', 'Étape d’accueil inconnue.');

            return Response::redirect(url('onboarding'));
        }

        $completed = $this->completedSteps($persona);
        if (in_array($step, $completed, true)) {
            $completed = array_values(array_filter($completed, static fn (int $index): bool => $index !== $step));
        } else {
            $completed[] = $step;
            sort($completed);
        }
        Session::set('onboarding_steps_' . $persona, $completed);
        Session::set('onboarding_done', false);
        $this->saveState($persona, $completed, false);

        return Response::redirect(url('onboarding') . '?persona=' . rawurlencode($persona) . '#persona-journey');
    }

    public function complete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('onboarding'));
        }

        $persona = OnboardingPersonaCatalog::normalize((string) Session::get('onboarding_persona', ''));
        if ($persona === null || count($this->completedSteps($persona)) < count(OnboardingPersonaCatalog::all()[$persona]['steps'])) {
            Session::flash('error', 'Validez les étapes recommandées avant de terminer le parcours.');

            return Response::redirect(url('onboarding') . ($persona !== null ? '?persona=' . rawurlencode($persona) . '#persona-journey' : ''));
        }

        Session::set('onboarding_done', true);
        $this->saveState($persona, $this->completedSteps($persona), true);
        Session::flash('success', 'Parcours d’accueil terminé. Bienvenue dans votre espace.');

        return Response::redirect(url('hub'));
    }

    /** @return list<int> */
    private function completedSteps(string $persona): array
    {
        $userId = (int) Session::get('user_id', 0);
        $persisted = $this->userProfileRepository->getOnboardingState($userId);
        $stored = OnboardingPersonaCatalog::normalize((string) ($persisted['persona'] ?? '')) === $persona
            ? ($persisted['steps'] ?? [])
            : Session::get('onboarding_steps_' . $persona, []);
        $stepCount = count(OnboardingPersonaCatalog::all()[$persona]['steps'] ?? []);

        return OnboardingPersonaCatalog::normalizeCompletedSteps($stored, $stepCount);
    }

    /** @param list<int> $steps */
    private function saveState(string $persona, array $steps, bool $completed): void
    {
        $userId = (int) Session::get('user_id', 0);
        $this->userProfileRepository->saveOnboardingState($userId, $persona, $steps, $completed);
    }
}
