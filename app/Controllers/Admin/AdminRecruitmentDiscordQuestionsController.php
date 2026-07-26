<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\RecruitmentDiscordQuestionRepository;

/**
 * Configuration, par tenant, des questions custom du formulaire de recrutement Discord
 * (select / question ouverte / fermée / réponse libre).
 */
class AdminRecruitmentDiscordQuestionsController
{
    public function __construct(
        private RecruitmentDiscordQuestionRepository $questionRepository,
        private EnlistmentRepository $enlistmentRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        return Response::view('layout.recruitment_lms', [
            'content' => 'admin.recruitments.discord_questions',
            'title' => 'Questions du formulaire Discord',
            'recruitmentLmsTitle' => 'Recrutement Discord',
            'recruitmentAdminNav' => 'discord',
            'recruitmentSidebarCounts' => $this->enlistmentRepository->countsByStatusForTenant((int) $tenantId),
            'showPortalFooter' => false,
            'discordQuestionsTableMissing' => !$this->questionRepository->tableExists(),
            'discordQuestions' => $this->questionRepository->listForTenant((int) $tenantId),
            'discordInviteMissing' => $this->isDiscordInviteMissing((int) $tenantId),
            'discordQuestionTypes' => [
                'select' => 'Liste déroulante (select)',
                'open' => 'Question ouverte (texte long)',
                'closed' => 'Question fermée (Oui / Non)',
                'free' => 'Réponse libre (texte court)',
            ],
        ]);
    }

    private function isDiscordInviteMissing(int $tenantId): bool
    {
        try {
            $settings = (new \App\Repositories\TenantRepository())->getSettings($tenantId);
            $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];

            return \App\Services\Community\TenantCommunityProfileService::needsDiscordInviteAlert($community);
        } catch (\Throwable) {
            return false;
        }
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'L’intitulé de la question est obligatoire.');

            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $type = (string) $request->input('type', 'open');
        $options = $this->parseOptions((string) $request->input('options', ''));
        $required = (string) $request->input('required', '0') === '1';

        $newId = $this->questionRepository->create((int) $tenantId, $type, $label, $options, $required);
        Session::flash(
            $newId > 0 ? 'success' : 'error',
            $newId > 0 ? 'Question ajoutée au formulaire Discord.' : 'Ce module n’est pas encore disponible sur cet environnement (migration à exécuter).'
        );

        return Response::redirect(url('back-office/recruitments/discord-questions'));
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $id = (int) ($params['id'] ?? 0);
        $label = trim((string) $request->input('label', ''));
        if ($id < 1 || $label === '') {
            Session::flash('error', 'Question invalide.');

            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $type = (string) $request->input('type', 'open');
        $options = $this->parseOptions((string) $request->input('options', ''));
        $required = (string) $request->input('required', '0') === '1';

        $ok = $this->questionRepository->update((int) $tenantId, $id, $type, $label, $options, $required);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Question mise à jour.' : 'Question introuvable.');

        return Response::redirect(url('back-office/recruitments/discord-questions'));
    }

    public function toggle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $id = (int) ($params['id'] ?? 0);
        $active = (string) $request->input('active', '1') === '1';
        if ($id < 1) {
            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $this->questionRepository->setActive((int) $tenantId, $id, $active);
        Session::flash('success', $active ? 'Question réactivée.' : 'Question désactivée.');

        return Response::redirect(url('back-office/recruitments/discord-questions'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $this->questionRepository->delete((int) $tenantId, $id);
        Session::flash('success', 'Question supprimée.');

        return Response::redirect(url('back-office/recruitments/discord-questions'));
    }

    public function reorder(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId || !$request->isPost()) {
            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/recruitments/discord-questions'));
        }
        $orderedIds = array_map('intval', (array) $request->input('ordered_ids', []));
        $this->questionRepository->reorder((int) $tenantId, $orderedIds);
        Session::flash('success', 'Ordre des questions mis à jour.');

        return Response::redirect(url('back-office/recruitments/discord-questions'));
    }

    /** @return list<string> */
    private function parseOptions(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $v = trim($line);
            if ($v !== '') {
                $out[] = mb_substr($v, 0, 120);
            }
        }

        return $out;
    }
}
