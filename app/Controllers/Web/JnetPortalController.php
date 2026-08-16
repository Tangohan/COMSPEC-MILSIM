<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantMessageRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Jnet\JnetDashboardService;
use App\Services\Jnet\JnetMessagingService;
use App\Services\Rbac\RbacService;
use App\Support\PortalAccessChoice;

final class JnetPortalController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?RbacService $rbacService = null,
        private ?UserRepository $userRepository = null,
        private ?TenantRepository $tenantRepository = null,
        private ?TenantMessageRepository $messageRepository = null,
        private ?JnetDashboardService $jnet = null,
        private ?JnetMessagingService $messaging = null,
    ) {
        $this->authService ??= \App\Core\Container::get(AuthService::class);
        $this->rbacService ??= \App\Core\Container::get(RbacService::class);
        $this->userRepository ??= \App\Core\Container::get(UserRepository::class);
        $this->tenantRepository ??= \App\Core\Container::get(TenantRepository::class);
        $this->messageRepository ??= new TenantMessageRepository();
        $this->jnet ??= new JnetDashboardService();
        $this->messaging ??= new JnetMessagingService();
    }

    public function home(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $dash = $this->jnet->buildHome($ctx['tenant_id'], $ctx['user_id']);

        return $this->render('home', 'Tableau d’unité', $dash, 'home');
    }

    public function unit(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('unit', 'Unité', $this->jnet->buildUnitPage($ctx['tenant_id'], $ctx['user_id']), 'unit');
    }

    public function personnel(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $filter = strtolower(trim((string) $request->query('filtre', 'all')));
        $all = $this->jnet->loadPersonnelCards($ctx['tenant_id']);
        $filtered = array_values(array_filter($all, static function (array $p) use ($filter): bool {
            if ($filter === '' || $filter === 'all') {
                return true;
            }
            if ($filter === 'deployed') {
                return ($p['duty'] ?? '') === 'deployed';
            }
            if ($filter === 'off') {
                return ($p['duty'] ?? '') === 'off';
            }
            $hay = strtoupper((string) ($p['unit'] ?? '') . ' ' . ($p['function'] ?? '') . ' ' . ($p['role'] ?? ''));

            return str_contains($hay, strtoupper($filter));
        }));

        return $this->render('personnel', 'Personnel', [
            'personnel' => $filtered,
            'personnelFilter' => $filter,
            'personnelTotal' => count($all),
        ], 'personnel');
    }

    public function personnelShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        $card = $this->jnet->findPersonnelCard($ctx['tenant_id'], $id);
        if ($card === null) {
            Session::flash('error', 'Fiche personnel introuvable.');

            return Response::redirect(url('jnet/personnel'));
        }

        return $this->render('personnel_show', (string) ($card['name'] ?? 'Personnel'), [
            'person' => $card,
        ], 'personnel');
    }

    public function operations(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('operations', 'Opérations', [
            'operations' => $this->jnet->loadOperations($ctx['tenant_id']),
        ], 'operations');
    }

    public function operationShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $op = $this->jnet->findOperation($ctx['tenant_id'], (int) ($params['id'] ?? 0));
        if ($op === null) {
            Session::flash('error', 'Opération introuvable.');

            return Response::redirect(url('jnet/operations'));
        }

        return $this->render('operation_show', (string) ($op['title'] ?? 'Opération'), [
            'operation' => $op,
        ], 'operations');
    }

    public function intelligence(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $home = $this->jnet->buildHome($ctx['tenant_id'], $ctx['user_id']);

        return $this->render('intelligence', 'Renseignement', [
            'intelFeed' => $home['intelFeed'],
            'priorityTargets' => $home['priorityTargets'],
            'viewerLens' => $home['viewerLens'],
        ], 'intelligence');
    }

    public function targets(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $targets = $this->jnet->loadTargets($ctx['tenant_id']);

        return $this->render('targets', 'Cibles prioritaires', [
            'targets' => $targets,
            'targetsTotal' => count($targets),
        ], 'targets');
    }

    public function targetShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = rawurldecode((string) ($params['id'] ?? ''));
        $target = $this->jnet->findTarget($ctx['tenant_id'], $id);
        if ($target === null) {
            Session::flash('error', 'Dossier cible introuvable.');

            return Response::redirect(url('jnet/cibles'));
        }
        $tab = strtolower(trim((string) $request->query('onglet', 'profil')));

        return $this->render('target_show', (string) ($target['name'] ?? 'Cible'), [
            'target' => $target,
            'targetTab' => $tab,
        ], 'targets');
    }

    public function exploitation(Request $request, array $params = []): Response
    {
        return $this->render('exploitation', 'Exploitation', [
            'links' => [
                ['label' => 'Bureau SSE', 'desc' => 'Dossiers, identités, sites et preuves terrain', 'href' => url('atak/sse')],
                ['label' => 'Laboratoire numérique', 'desc' => 'Terminaux, acquisitions et artéfacts', 'href' => url('atak/sse/numerique')],
                ['label' => 'Croisements', 'desc' => 'Corrélations et listes de surveillance', 'href' => url('atak/sse/croisements')],
                ['label' => 'Transmission', 'desc' => 'Journaux de mission et comptes rendus', 'href' => url('transmission')],
            ],
        ], 'exploitation');
    }

    public function library(Request $request, array $params = []): Response
    {
        return $this->render('library', 'Bibliothèque', [
            'sections' => [
                ['label' => 'Doctrine & guides', 'items' => ['Procédures d’unité', 'Guide SSE', 'Normes de rédaction']],
                ['label' => 'Briefings', 'items' => ['Briefing courant', 'Intentions de commandement', 'Situations hebdomadaires']],
                ['label' => 'Archives', 'items' => ['Comptes rendus classés', 'Dossiers clos', 'Exports de mission']],
            ],
            'athenaDocs' => url('documents'),
            'sseGuide' => url('atak/sse/guide'),
        ], 'library');
    }

    public function mail(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];

        $box = (string) $request->query('boite', 'reception');
        if (!in_array($box, ['reception', 'non-lus', 'envoyes'], true)) {
            $box = 'reception';
        }
        $repoBox = match ($box) {
            'non-lus' => 'unread',
            'envoyes' => 'sent',
            default => 'inbox',
        };

        $threads = $this->messageRepository->listThreadsForUserByBox($tenantId, $userId, $repoBox);
        $selected = null;
        $messages = [];
        $participants = [];

        $threadId = (int) $request->query('fil', 0);
        if ($threadId <= 0 && $threads !== []) {
            $threadId = (int) ($threads[0]['id'] ?? 0);
        }
        if ($threadId > 0 && $this->messageRepository->userInThread($threadId, $userId)) {
            $selected = $this->messageRepository->findThread($threadId, $tenantId);
            if ($selected !== null) {
                $this->messageRepository->markThreadRead($threadId, $userId);
                $messages = $this->messageRepository->listMessages($threadId);
                $participants = $this->messageRepository->listThreadParticipants($threadId);
            }
        }

        return $this->render('mail', 'Messagerie', [
            'mailBox' => $box,
            'mailBoxes' => $this->mailBoxes($tenantId, $userId),
            'mailThreads' => $threads,
            'mailSelected' => $selected,
            'mailMessages' => $messages,
            'mailParticipants' => $participants,
            'mailCurrentUserId' => $userId,
            'mailFullUrl' => url('messages'),
            'mailCanSend' => $this->canSendMessages($tenantId, $userId),
            'mailPrecedences' => $this->messaging->precedences(),
        ], 'inbox');
    }

    public function mailCompose(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];

        if (!$this->canSendMessages($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas envoyer de message pour le moment.');

            return Response::redirect(url('jnet/courrier'));
        }

        $draft = Session::getFlash('jnet_mail_draft');
        $draft = is_array($draft) ? $draft : [];
        $replyTo = (int) $request->query('repondre', 0);
        if ($replyTo > 0 && $this->messageRepository->userInThread($replyTo, $userId)) {
            $thread = $this->messageRepository->findThread($replyTo, $tenantId);
            if ($thread !== null && ($draft['subject'] ?? '') === '') {
                $draft['subject'] = 'RE : ' . (string) ($thread['subject'] ?? '');
            }
        }

        return $this->render('mail_compose', 'Nouveau message', [
            'mailBoxes' => $this->mailBoxes($tenantId, $userId),
            'mailBox' => 'redaction',
            'mailGroups' => $this->messaging->groups($tenantId),
            'mailDirectory' => $this->messaging->directory($tenantId, $userId),
            'mailPrecedences' => $this->messaging->precedences(),
            'mailDraft' => [
                'subject' => (string) ($draft['subject'] ?? ''),
                'body' => (string) ($draft['body'] ?? ''),
                'precedence' => (string) ($draft['precedence'] ?? 'routine'),
                'groups' => array_map('strval', (array) ($draft['groups'] ?? [])),
                'members' => array_map('intval', (array) ($draft['members'] ?? [])),
            ],
            'mailEmailLimit' => $this->messaging->emailNotificationLimit(),
        ], 'inbox');
    }

    public function mailSend(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];

        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, le message n’a pas été envoyé.');

            return Response::redirect(url('jnet/courrier/nouveau'));
        }
        if (!$this->canSendMessages($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas envoyer de message pour le moment.');

            return Response::redirect(url('jnet/courrier'));
        }

        $subject = trim((string) $request->input('subject', ''));
        $body = trim((string) $request->input('body', ''));
        $precedence = (string) $request->input('precedence', 'routine');
        if (!array_key_exists($precedence, $this->messaging->precedences())) {
            $precedence = 'routine';
        }
        $groupKeys = array_map('strval', (array) $request->input('groups', []));
        $memberIds = array_map('intval', (array) $request->input('members', []));

        $keepDraft = static function () use ($subject, $body, $precedence, $groupKeys, $memberIds): void {
            Session::flash('jnet_mail_draft', [
                'subject' => $subject,
                'body' => $body,
                'precedence' => $precedence,
                'groups' => $groupKeys,
                'members' => $memberIds,
            ]);
        };

        if ($body === '') {
            Session::flash('error', 'Le corps du message est vide.');
            $keepDraft();

            return Response::redirect(url('jnet/courrier/nouveau'));
        }

        $resolved = $this->messaging->resolveSelection($tenantId, $userId, $groupKeys, $memberIds);
        if ($resolved['error'] !== null) {
            Session::flash('error', $resolved['error']);
            $keepDraft();

            return Response::redirect(url('jnet/courrier/nouveau'));
        }

        $threadId = $this->messageRepository->createDiffusionThread(
            $tenantId,
            $userId,
            $subject !== '' ? $subject : 'Sans objet',
            $resolved['recipients'],
            $precedence,
            $resolved['summary'],
            'jnet'
        );
        $this->messageRepository->addMessage($threadId, $userId, $body);
        $this->messageRepository->markThreadRead($threadId, $userId);
        $this->notifyThread($tenantId, $threadId, $userId, $body, count($resolved['recipients']));

        $count = count($resolved['recipients']);
        Session::flash('success', 'Message transmis à ' . $count . ' destinataire' . ($count > 1 ? 's' : '') . '.');

        return Response::redirect(url('jnet/courrier') . '?fil=' . $threadId);
    }

    public function mailReply(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];
        $threadId = (int) ($params['id'] ?? 0);

        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, la réponse n’a pas été envoyée.');

            return Response::redirect(url('jnet/courrier'));
        }
        if (!$this->canSendMessages($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas envoyer de message pour le moment.');

            return Response::redirect(url('jnet/courrier'));
        }
        if ($threadId <= 0 || !$this->messageRepository->userInThread($threadId, $userId)) {
            Session::flash('error', 'Conversation introuvable.');

            return Response::redirect(url('jnet/courrier'));
        }
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            Session::flash('error', 'La réponse est vide.');

            return Response::redirect(url('jnet/courrier') . '?fil=' . $threadId);
        }

        $this->messageRepository->addMessage($threadId, $userId, $body);
        $this->messageRepository->markThreadRead($threadId, $userId);
        $recipients = max(0, count($this->messageRepository->listParticipantUserIds($threadId)) - 1);
        $this->notifyThread($tenantId, $threadId, $userId, $body, $recipients);
        Session::flash('success', 'Réponse envoyée.');

        return Response::redirect(url('jnet/courrier') . '?fil=' . $threadId);
    }

    public function mailMarkAllRead(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré.');

            return Response::redirect(url('jnet/courrier'));
        }
        $this->messageRepository->markAllThreadsReadForUser($ctx['tenant_id'], $ctx['user_id']);
        Session::flash('success', 'Tous les messages sont marqués comme lus.');

        return Response::redirect(url('jnet/courrier'));
    }

    /**
     * @return list<array{key: string, label: string, count: int, href: string}>
     */
    private function mailBoxes(int $tenantId, int $userId): array
    {
        $inbox = $this->messageRepository->listThreadsForUserByBox($tenantId, $userId, 'inbox');
        $unread = 0;
        foreach ($inbox as $thread) {
            if (!empty($thread['has_unread'])) {
                $unread++;
            }
        }
        $sent = $this->messageRepository->listThreadsForUserByBox($tenantId, $userId, 'sent');

        return [
            ['key' => 'reception', 'label' => 'Réception', 'count' => count($inbox), 'href' => url('jnet/courrier')],
            ['key' => 'non-lus', 'label' => 'Non lus', 'count' => $unread, 'href' => url('jnet/courrier') . '?boite=non-lus'],
            ['key' => 'envoyes', 'label' => 'Messages envoyés', 'count' => count($sent), 'href' => url('jnet/courrier') . '?boite=envoyes'],
        ];
    }

    private function canSendMessages(int $tenantId, int $userId): bool
    {
        try {
            $featureGate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
            if (!$featureGate->allows($tenantId, 'messages')) {
                return false;
            }
        } catch (\Throwable) {
        }
        try {
            $resolver = \App\Core\Container::get(\App\Services\Moderation\ModerationRestrictionResolver::class);

            return $resolver->canSendMessages($tenantId, $userId);
        } catch (\Throwable) {
            return true;
        }
    }

    /** Au-delà d’une petite diffusion, le message reste dans la messagerie sans doublon par e-mail. */
    private function notifyThread(int $tenantId, int $threadId, int $senderId, string $body, int $recipientCount): void
    {
        if ($recipientCount > $this->messaging->emailNotificationLimit()) {
            return;
        }
        try {
            $notifier = \App\Core\Container::get(\App\Services\Community\TenantInternalMessageNotificationService::class);
            $notifier->notifyAfterMessage($tenantId, $threadId, $senderId, $body);
        } catch (\Throwable) {
        }
    }

    public function system(Request $request, array $params = []): Response
    {
        return $this->render('system', 'Système', [
            'canTba' => PortalAccessChoice::canAccessTba(),
            'preferredPortal' => PortalAccessChoice::remembered(),
        ], 'system');
    }

    public function switchPortal(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('jnet/systeme'));
        }
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('jnet/systeme'));
        }
        $action = (string) $request->input('action', '');
        if ($action === 'clear_preference') {
            PortalAccessChoice::clearRemembered();
            Session::flash('success', 'Le choix d’espace mémorisé a été effacé.');

            return Response::redirect(url('login/choisir-espace'));
        }
        if ($action === 'tba') {
            if (!PortalAccessChoice::canAccessTba()) {
                Session::flash('error', 'Accès administratif indisponible pour votre compte.');

                return Response::redirect(url('jnet/systeme'));
            }
            PortalAccessChoice::remember(PortalAccessChoice::PORTAL_TBA, false);

            return Response::redirect(url('back-office'));
        }
        if ($action === 'chooser') {
            return Response::redirect(url('login/choisir-espace'));
        }

        return Response::redirect(url('jnet/systeme'));
    }

    /** @deprecated keep redirects for old bookmarks */
    public function theatre(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/operations'));
    }

    public function sources(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/renseignement'));
    }

    public function intents(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/renseignement'));
    }

    public function roster(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/personnel'));
    }

    public function apps(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/bibliotheque'));
    }

    public function sync(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet'));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function render(string $view, string $title, array $extra = [], ?string $activeNav = null): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        $tenant = $this->tenantRepository->findById($ctx['tenant_id']);
        $tenantName = is_array($tenant) ? community_display_name($tenant) : 'Communauté';
        $nodeId = $this->nodeIdForTenant($ctx['tenant_id'], is_array($tenant) ? (string) ($tenant['slug'] ?? '') : '');
        $nav = $activeNav ?? $view;
        $unread = $this->unreadMailCount($ctx['tenant_id'], $ctx['user_id']);

        $payload = array_merge([
            'content' => 'jnet._bo_content',
            'jnetInnerView' => 'jnet.' . $view,
            'title' => $title . ' — Extranet d’unité',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Unité',
            'boPageKicker' => 'UNITÉ · EXTRANET',
            'boPageTitle' => $title,
            'boPageSubtitle' => 'Situation, personnel, opérations et renseignement de l’unité.',
            'boPageQuick' => [],
            'backOfficePageCss' => [
                'jnet_portal.css',
                'jnet_bo_embed.css',
            ],
            'activeNav' => $nav,
            'jnetTenantName' => $tenantName,
            'jnetDisplayName' => (string) Session::get('display_name', ''),
            'jnetCallsign' => (string) Session::get('callsign', ''),
            'jnetNodeId' => $nodeId,
            'jnetCanTba' => PortalAccessChoice::canAccessTba(),
            'jnetUnreadMail' => $unread,
            'jnetDtg' => strtoupper(gmdate('dHi') . 'Z' . gmdate('M y')),
        ], $extra);

        return Response::view('layout.main', $payload);
    }

    private function ensureAuth(): array|Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $ctx = $this->sessionContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }

        return $ctx;
    }

    /** @return array{tenant_id:int,user_id:int}|null */
    private function sessionContext(): ?array
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return null;
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }

    private function unreadMailCount(int $tenantId, int $userId): int
    {
        try {
            return $this->messageRepository->unreadCountForUser($tenantId, $userId);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function nodeIdForTenant(int $tenantId, string $slug): string
    {
        $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $slug) ?? '');
        if ($base === '') {
            $base = 'NODE';
        }
        $base = substr($base, 0, 4);
        $suffix = strtoupper(substr(dechex(($tenantId * 7919) % 65535), -3));

        return $base . $suffix;
    }
}
