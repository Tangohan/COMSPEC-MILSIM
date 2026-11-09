<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Effectifs\MemberProfileInviteSender;
use App\Services\Effectifs\MemberProfileInviteService;
use App\Services\Effectifs\NewMemberChecks;
use App\Support\EffectifsLmsAccess;

/**
 * Effectifs › liens « compléter son profil » : création depuis la fiche, puis copier, envoyer (Discord ou e-mail sur
 * demande), prolonger, régénérer, révoquer. Réponses JSON pour les boutons de la page, redirection sinon.
 */
final class EffectifsInvitesController
{
    private const ACTIONS = ['lien', 'prolonger', 'regenerer', 'revoquer', 'discord', 'email'];

    public function __construct(
        private ?MemberProfileInviteService $invites = null,
        private ?UserRepository $users = null,
        private ?MemberProfileInviteSender $sender = null,
    ) {
        $this->invites ??= new MemberProfileInviteService();
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) ($params['id'] ?? 0);
        $back = $this->backUrl($request, $userId);
        $denied = $this->guard($request, $back);
        if ($denied !== null) {
            return $denied;
        }
        $user = $userId > 0 ? $this->users()->findById($userId, $tenantId) : null;
        if ($user === null) {
            return $this->reply($request, false, 'Membre introuvable.', $back);
        }
        try {
            $issued = $this->invites->issue(
                $tenantId,
                $userId,
                $actorId,
                MemberProfileInviteService::normalizeHours($request->input('duration_hours', 48)),
                (string) $request->input('single_use', '') === '1'
            );
        } catch (\Throwable $e) {
            error_log('[profile-invite] création impossible : ' . $e->getMessage());

            return $this->reply($request, false, 'Impossible de créer le lien pour le moment.', $back);
        }
        $messages = ['Lien créé, valable jusqu’au ' . date('d/m/Y à H:i', strtotime($issued['expires_at']) ?: time()) . '.'];
        $ok = true;
        $row = $this->invites->findUsableByToken($issued['token']);
        if ($row !== null) {
            foreach (self::requestedChannels($request) as $channel) {
                $res = $channel === 'discord'
                    ? $this->sender()->sendDiscord($tenantId, $row, $actorId, (string) $request->input('destination', ''))
                    : $this->sender()->sendEmail($tenantId, $row, $actorId);
                $messages[] = $res['message'];
                $ok = $ok && $res['ok'];
            }
        }
        (new \App\Repositories\RosterFicheEventRepository())->record($tenantId, $userId, $actorId, 'invited', 'invite', 'lien créé (' . MemberProfileInviteService::normalizeHours($request->input('duration_hours', 48)) . ' h)');
        Session::flash('profile_invite_new', $issued['id']);

        return $this->reply($request, $ok, implode(' ', $messages), $back, ['invite_id' => $issued['id'], 'url' => $issued['url']]);
    }

    public function act(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $inviteId = (int) ($params['inviteId'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        $back = $this->backUrl($request, 0);
        $denied = $this->guard($request, $back);
        if ($denied !== null) {
            return $denied;
        }
        if (!in_array($action, self::ACTIONS, true) || !$this->invites->available()) {
            return $this->reply($request, false, 'Action inconnue.', $back);
        }
        $repo = new \App\Repositories\MemberProfileInviteRepository();
        $row = $repo->find($tenantId, $inviteId);
        if ($row === null) {
            return $this->reply($request, false, 'Lien introuvable.', $back);
        }
        $back = $this->backUrl($request, (int) $row['user_id']);

        switch ($action) {
            case 'lien':
                $url = $this->invites->linkFor($row);
                if ($url === null) {
                    return $this->reply($request, false, 'Ce lien n’est plus valable. Régénérez-le pour en obtenir un nouveau.', $back);
                }
                $this->invites->recordSent($row, 'link', $actorId);

                return $this->reply($request, true, 'Lien copié.', $back, ['url' => $url]);
            case 'prolonger':
                $hours = MemberProfileInviteService::normalizeHours($request->input('duration_hours', $row['duration_hours'] ?? 48));
                $ok = $this->invites->extend($tenantId, $inviteId, $hours, $actorId);

                return $this->reply($request, $ok, $ok ? 'Lien prolongé de ' . $hours . ' h, à partir de maintenant.' : 'Ce lien ne peut plus être prolongé : régénérez-le.', $back);
            case 'regenerer':
                $new = $this->invites->regenerate($tenantId, $inviteId, $actorId);
                if ($new === null) {
                    return $this->reply($request, false, 'Ce lien ne peut pas être régénéré (profil déjà complété).', $back);
                }
                Session::flash('profile_invite_new', $new['id']);

                return $this->reply($request, true, 'Nouveau lien créé : l’ancien ne fonctionne plus.', $back, ['invite_id' => $new['id'], 'url' => $new['url']]);
            case 'revoquer':
                $ok = $this->invites->revoke($tenantId, $inviteId, $actorId);

                return $this->reply($request, $ok, $ok ? 'Lien révoqué : il ne fonctionne plus.' : 'Ce lien était déjà révoqué.', $back);
            case 'discord':
                $res = $this->sender()->sendDiscord($tenantId, $row, $actorId, (string) $request->input('destination', ''));

                return $this->reply($request, $res['ok'], $res['message'], $back);
            default:
                $res = $this->sender()->sendEmail($tenantId, $row, $actorId);

                return $this->reply($request, $res['ok'], $res['message'], $back);
        }
    }

    /** Vérifications en direct du formulaire « Ajouter un membre » (indicatif, e-mail, Steam déjà pris). */
    public function checkNewMember(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('user_id') || !EffectifsLmsAccess::canCreateMembers(Gate::getInstance())) {
            return Response::json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return Response::json(['ok' => false, 'message' => 'Session expirée. Rechargez la page.'], 419);
        }
        $checks = new NewMemberChecks($this->users());

        return Response::json(['ok' => true, 'fields' => $checks->run((int) Session::get('tenant_id'), [
            'callsign' => (string) $request->input('callsign', ''),
            'email' => (string) $request->input('email', ''),
            'steam_id' => (string) $request->input('steam_id', ''),
        ])]);
    }

    /** @return list<string> */
    public static function requestedChannels(Request $request): array
    {
        $out = [];
        if ((string) $request->input('send_discord', '') === '1') {
            $out[] = 'discord';
        }
        if ((string) $request->input('send_email', '') === '1') {
            $out[] = 'email';
        }

        return $out;
    }

    private function guard(Request $request, string $back): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::canInviteMembers(Gate::getInstance())) {
            return $this->reply($request, false, 'Vous n’êtes pas habilité à inviter des membres.', $back, [], 403);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->reply($request, false, 'Session expirée. Rechargez la page.', $back, [], 419);
        }
        if (!$this->invites->available()) {
            return $this->reply($request, false, 'Les liens d’invitation seront disponibles après la prochaine mise à jour de la base.', $back, [], 503);
        }

        return null;
    }

    /** @param array<string, mixed> $extra */
    private function reply(Request $request, bool $ok, string $message, string $back, array $extra = [], int $status = 0): Response
    {
        if (self::wantsJson($request)) {
            return Response::json(['ok' => $ok, 'message' => $message] + $extra, $status > 0 ? $status : ($ok ? 200 : 422));
        }
        Session::flash($ok ? 'success' : 'error', $message);

        return Response::redirect($back);
    }

    private static function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }

    private function backUrl(Request $request, int $userId): string
    {
        $from = (string) $request->input('return_to', '');
        if ($from === 'invitations') {
            return effectifs_workspace_url('invitations');
        }
        if ($from === 'sans-comptes') {
            return effectifs_workspace_url('sans-comptes') . ($userId > 0 ? '?fiche=' . $userId : '');
        }

        return $userId > 0 ? effectifs_workspace_url('membres/' . $userId) . '#invitations' : effectifs_workspace_url('invitations');
    }

    private function users(): UserRepository
    {
        return $this->users ??= \App\Core\Container::get(UserRepository::class);
    }

    private function sender(): MemberProfileInviteSender
    {
        return $this->sender ??= new MemberProfileInviteSender($this->invites, $this->users());
    }
}
