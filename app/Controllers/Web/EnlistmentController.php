<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\EnlistmentRepository;
use App\Repositories\TenantRepository;

class EnlistmentController
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private TenantRepository $tenantRepository
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        return Response::view('enlistment');
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('enlistment'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('enlistment_error', 'Session expirée. Veuillez recharger la page et soumettre à nouveau le formulaire.');
            return Response::redirect(url('enlistment/error'));
        }
        if (!$request->input('no_ai_confirmed')) {
            Session::flash('enlistment_error', 'Vous devez confirmer l\'absence d\'IA dans ce rapport (case à cocher obligatoire).');
            return Response::redirect(url('enlistment/error'));
        }
        $tenant = $this->tenantRepository->getDefaultTenant();
        if (!$tenant) {
            Session::flash('enlistment_error', 'Organisation non configurée. Merci de réessayer plus tard.');
            return Response::redirect(url('enlistment/error'));
        }
        $fullName = trim((string) $request->input('full_name'));
        $first = $fullName;
        $last = '';
        if ($fullName !== '' && str_contains($fullName, ' ')) {
            $pos = strpos($fullName, ' ');
            $first = substr($fullName, 0, $pos);
            $last = trim(substr($fullName, $pos));
        }
        if ($first === '' && trim((string) $request->input('first_name')) !== '') {
            $first = trim((string) $request->input('first_name'));
            $last = trim((string) $request->input('last_name'));
        }
        try {
            $this->enlistmentRepository->create((int) $tenant['id'], [
                'first_name' => $first ?: '—',
                'last_name' => $last ?: '—',
                'email' => trim((string) $request->input('email')),
                'callsign' => trim((string) $request->input('callsign')) ?: null,
                'country' => trim((string) $request->input('country')) ?: null,
                'experience' => trim((string) $request->input('experience')) ?: null,
                'specialty' => trim((string) $request->input('specialty')) ?: null,
                'platform' => trim((string) $request->input('platform')) ?: null,
                'availability' => trim((string) $request->input('availability')) ?: null,
                'notes' => trim((string) $request->input('notes')) ?: null,
                'age' => $request->input('age'),
                'timezone' => trim((string) $request->input('timezone')) ?: null,
                'weekly_availability' => trim((string) $request->input('weekly_availability')) ?: null,
                'system_config' => trim((string) $request->input('system_config')) ?: null,
                'microphone_quality' => trim((string) $request->input('microphone_quality')) ?: null,
                'past_milsim_experience' => trim((string) $request->input('past_milsim_experience')) ?: null,
                'ace_acre_level' => trim((string) $request->input('ace_acre_level')) ?: null,
                'motivation_why_join' => trim((string) $request->input('motivation_why_join')) ?: null,
                'motivation_accountability' => trim((string) $request->input('motivation_accountability')) ?: null,
                'commitment_effort' => trim((string) $request->input('commitment_effort')) ?: null,
                'availability_wed_sat' => trim((string) $request->input('availability_wed_sat')) ?: null,
                'no_ai_confirmed' => $request->input('no_ai_confirmed'),
            ]);
        } catch (\Throwable $e) {
            Session::flash('enlistment_error', 'Une erreur technique a empêché l\'enregistrement de votre candidature. Veuillez réessayer ou contacter le support.');
            return Response::redirect(url('enlistment/error'));
        }
        return Response::redirect(url('enlistment/success'));
    }

    public function success(Request $request, array $params = []): Response
    {
        return Response::view('enlistment.success');
    }

    public function error(Request $request, array $params = []): Response
    {
        $message = Session::getFlash('enlistment_error', 'Une erreur est survenue lors de la soumission.');
        return Response::view('enlistment.error', ['message' => $message]);
    }
}
