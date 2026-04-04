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
        $tenant = $this->resolveTenant($params);
        if (!$tenant) {
            return Response::view('enlistment.error', ['message' => 'Organisation introuvable.']);
        }

        $communityConfig = $this->communityConfig($tenant);
        if (!empty($communityConfig['community_locked'])) {
            return Response::view('enlistment.error', ['message' => 'Le recrutement est verrouillé pour cette communauté.']);
        }

        $mode = ($communityConfig['registration_mode'] ?? 'milsim') === 'simple' ? 'simple' : 'milsim';

        return Response::view($mode === 'simple' ? 'enlistment.simple' : 'enlistment', [
            'tenant' => $tenant,
            'communityConfig' => $communityConfig,
            'formAction' => $this->enlistmentActionUrl($tenant),
        ]);
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

        $tenant = $this->resolveTenant($params);
        if (!$tenant) {
            Session::flash('enlistment_error', 'Organisation non configurée. Merci de réessayer plus tard.');
            return Response::redirect(url('enlistment/error'));
        }

        $communityConfig = $this->communityConfig($tenant);
        if (!empty($communityConfig['community_locked'])) {
            Session::flash('enlistment_error', 'Le recrutement est verrouillé pour cette communauté.');
            return Response::redirect(url('enlistment/error'));
        }

        $requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
        if ($requireAiAck && !$request->input('no_ai_confirmed')) {
            Session::flash('enlistment_error', 'Vous devez confirmer l\'absence d\'IA dans ce rapport (case à cocher obligatoire).');
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
                'no_ai_confirmed' => $requireAiAck ? $request->input('no_ai_confirmed') : 1,
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

    private function resolveTenant(array $params): ?array
    {
        $slug = trim((string) ($params['slug'] ?? ''));
        if ($slug !== '') {
            return $this->tenantRepository->findBySlug($slug);
        }
        return $this->tenantRepository->getDefaultTenant();
    }

    /** @param array<string,mixed> $tenant */
    private function communityConfig(array $tenant): array
    {
        $raw = $tenant['settings'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return is_array($decoded['community'] ?? null) ? $decoded['community'] : [];
    }

    /** @param array<string,mixed> $tenant */
    private function enlistmentActionUrl(array $tenant): string
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        if ($slug === '') {
            return url('enlistment');
        }
        return url('c/' . $slug . '/enlistment');
    }
}
