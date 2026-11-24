<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\PlatformBanAppealRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Account\PlatformBanAppealCaseService;

/**
 * Dossiers de contestation côté modération.
 *
 * Deux périmètres : l'administrateur de la plateforme voit tous les dossiers et décide ; l'administrateur d'une
 * communauté ne voit que les dossiers dont la sanction a été posée pour sa communauté, peut échanger, demander des
 * précisions, annoter et assigner, mais ne lève ni ne maintient une sanction du site. Aucun accès inter-communautés.
 */
final class BanAppealCaseController
{
    private const PLATFORM_ACTIONS = ['reply', 'info', 'note', 'assign', 'accept', 'uphold', 'close'];
    private const COMMUNITY_ACTIONS = ['reply', 'info', 'note', 'assign'];

    public function __construct(
        private ?PlatformBanAppealRepository $appeals = null,
        private ?PlatformAccountBanRepository $bans = null,
        private ?PlatformBanAppealCaseService $cases = null,
    ) {
        $this->appeals ??= new PlatformBanAppealRepository();
        $this->bans ??= new PlatformAccountBanRepository();
        $this->cases ??= new PlatformBanAppealCaseService($this->appeals, $this->bans);
    }

    public function platformIndex(Request $request, array $params = []): Response
    {
        return $this->index($request, null);
    }

    public function platformShow(Request $request, array $params = []): Response
    {
        return $this->show((int) ($params['id'] ?? 0), null);
    }

    public function platformAct(Request $request, array $params = []): Response
    {
        return $this->act($request, (int) ($params['id'] ?? 0), (string) ($params['action'] ?? ''), null);
    }

    public function platformExport(Request $request, array $params = []): Response
    {
        $rows = $this->appeals->casesReady() ? $this->appeals->listQueue($this->filters($request), null, $this->actor(), 300) : [];
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['reference', 'statut', 'depose_le', 'mis_a_jour_le', 'decision_n', 'communaute_origine', 'assigne_a', 'non_lu'], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, [
                self::csvCell((string) $row['reference']), self::csvCell((string) $row['status']), $row['created_at'], $row['updated_at'],
                (int) $row['ban_id'], (int) ($row['source_tenant_id'] ?? 0) ?: '', (int) ($row['assigned_to'] ?? 0) ?: '', (int) $row['unread_staff'],
            ], ';', '"', '');
        }
        rewind($out);
        $csv = "\xEF\xBB\xBF" . stream_get_contents($out);
        fclose($out);

        return (new Response())->setBody($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="contestations-' . gmdate('Ymd') . '.csv"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function communityIndex(Request $request, array $params = []): Response
    {
        $tenant = $this->communityTenant();

        return $tenant === null ? $this->denied() : $this->index($request, $tenant);
    }

    public function communityShow(Request $request, array $params = []): Response
    {
        $tenant = $this->communityTenant();

        return $tenant === null ? $this->denied() : $this->show((int) ($params['id'] ?? 0), $tenant);
    }

    public function communityAct(Request $request, array $params = []): Response
    {
        $tenant = $this->communityTenant();

        return $tenant === null ? $this->denied() : $this->act($request, (int) ($params['id'] ?? 0), (string) ($params['action'] ?? ''), $tenant);
    }

    /** Neutralise l'injection de formule d'un tableur dans l'export. */
    private static function csvCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
    }

    private function communityTenant(): ?int
    {
        $gate = Gate::getInstance();
        $tenant = (int) Session::get('tenant_id');

        return $tenant > 1 && ($gate->allows('admin.organization') || $gate->allows('admin.access')) ? $tenant : null;
    }

    private function denied(): Response
    {
        Session::flash('error', 'Accès réservé aux administrateurs de la communauté.');

        return Response::redirect(url('back-office'));
    }

    private function actor(): int
    {
        return max(0, (int) Session::get('user_id'));
    }

    private function base(?int $tenant): string
    {
        return $tenant === null ? 'admin/sanctions/dossiers' : 'back-office/contestations';
    }

    /** @return array{status: string, age_days: int, tenant: int, assigned: string} */
    private function filters(Request $request): array
    {
        $status = $request->queryString('statut', 'open');
        $assigned = $request->queryString('assigne');

        return [
            'status' => $status === 'all' || $status === 'open' || $status === 'unread' || in_array($status, PlatformBanAppealRepository::STATUSES, true) ? $status : 'open',
            'age_days' => max(0, min(365, (int) $request->queryString('anciennete'))),
            'tenant' => max(0, (int) $request->queryString('communaute')),
            'assigned' => in_array($assigned, ['me', 'none'], true) ? $assigned : '',
        ];
    }

    private function index(Request $request, ?int $tenant): Response
    {
        $ready = $this->appeals->casesReady();
        $filters = $this->filters($request);

        return Response::view('layout.main', [
            'title' => 'Contestations de bannissement',
            'content' => 'admin.system.ban_appeal_queue',
            'backOfficePageCss' => ['platform-admin.css', 'platform-admin-sanctions.css', 'platform-admin-appeal-cases.css'],
            'ready' => $ready,
            'rows' => $ready ? $this->appeals->listQueue($filters, $tenant, $this->actor()) : [],
            'filters' => $filters,
            'openCount' => $ready ? $this->appeals->countOpen($tenant) : 0,
            'unreadCount' => $ready ? $this->appeals->countUnread($tenant) : 0,
            'tenants' => $tenant === null && $ready ? \App\Core\Container::get(TenantRepository::class)->listBasicAll() : [],
            'base' => $this->base($tenant),
            'platform' => $tenant === null,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed>|null le dossier si le périmètre y donne accès */
    private function scopedCase(int $banId, ?int $tenant): ?array
    {
        if ($banId < 1 || !$this->appeals->casesReady()) {
            return null;
        }
        $case = $this->appeals->findCase($banId);
        if ($case === null || ($tenant !== null && (int) ($case['source_tenant_id'] ?? 0) !== $tenant)) {
            return null;
        }

        return $case;
    }

    private function show(int $banId, ?int $tenant): Response
    {
        $case = $this->scopedCase($banId, $tenant);
        if ($case === null) {
            Session::flash('error', 'Dossier introuvable.');

            return Response::redirect(url($this->base($tenant)));
        }
        $this->cases->markRead($banId);
        $history = $this->bans->historyForEmail((string) $case['email_norm'], 20);
        if ($tenant !== null) {
            // Un administrateur de communauté ne voit pas qui, à la plateforme, a décidé ou levé.
            foreach ($history as &$entry) {
                unset($entry['banned_by_name'], $entry['banned_by_callsign'], $entry['lifted_by_name'], $entry['lifted_by_callsign'], $entry['banned_by_user_id'], $entry['lifted_by_user_id']);
            }
            unset($entry);
        }
        $assignee = (int) ($case['assigned_to'] ?? 0) > 0 ? \App\Core\Container::get(UserRepository::class)->findById((int) $case['assigned_to'], null) : null;

        return Response::view('layout.main', [
            'title' => 'Dossier ' . $case['reference'],
            'content' => 'admin.system.ban_appeal_case',
            'backOfficePageCss' => ['platform-admin.css', 'platform-admin-sanctions.css', 'platform-admin-appeal-cases.css'],
            'case' => $case,
            'messages' => $this->appeals->messages($banId, true),
            'history' => $history,
            'assigneeName' => $assignee !== null ? (string) ($assignee['display_name'] ?? '') : '',
            'actorId' => $this->actor(),
            'base' => $this->base($tenant),
            'platform' => $tenant === null,
            'open' => PlatformBanAppealRepository::isOpen($case),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function act(Request $request, int $banId, string $action, ?int $tenant): Response
    {
        $back = Response::redirect(url($this->base($tenant) . '/' . $banId), 303);
        $allowed = $tenant === null ? self::PLATFORM_ACTIONS : self::COMMUNITY_ACTIONS;
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Le formulaire a expiré. Réessayez.');

            return $back;
        }
        $case = $this->scopedCase($banId, $tenant);
        if ($case === null || !in_array($action, $allowed, true) || $this->actor() === 0) {
            Session::flash('error', 'Action impossible sur ce dossier.');

            return Response::redirect(url($this->base($tenant)));
        }
        $actor = $this->actor();
        $text = $request->inputString('message');
        $result = match ($action) {
            'reply' => $this->cases->staffReply($banId, $actor, $text),
            'info' => $this->cases->staffReply($banId, $actor, $text, true),
            'note' => $this->cases->note($banId, $actor, $text),
            'assign' => $this->cases->assign($banId, $actor, $request->inputString('assignee') === 'none' ? null : $actor),
            'accept' => $this->cases->decide($banId, $actor, 'lift', $text),
            'uphold' => $this->cases->decide($banId, $actor, 'uphold', $text),
            default => $this->cases->decide($banId, $actor, 'closed', $text),
        };
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Dossier ' . $case['reference'] . ' mis à jour.' : (string) ($result['error'] ?? 'Action impossible.'));

        return $back;
    }
}
