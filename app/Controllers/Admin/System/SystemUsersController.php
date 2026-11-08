<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Account\AccountDeletionService;
use App\Services\Account\AccountPurgeService;
use App\Services\Admin\PlatformUserProfileService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Identity\UserIdentityMergeService;
use App\Services\Admin\PlatformDataVolumeService;
use App\Support\CsvSafe;
use App\Support\PlatformUserColumns;
use App\Support\PlatformUsersDirectoryQuery;
use App\Support\PortalAccessChoice;
use App\Repositories\AccountPurgeRequestRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\PlatformAccountBanRepository;
use App\Services\Account\PlatformAccountBanService;

/**
 * Annuaire global des comptes (toutes communautés) — administration plateforme.
 */
final class SystemUsersController
{
    public function __construct(
        private UserRepository $users,
        private TenantRepository $tenants,
        private ?AuditService $audit = null,
        private ?AccountDeletionService $accountDeletion = null,
        private ?AccountPurgeService $accountPurge = null,
        private ?AccountPurgeRequestRepository $purgeRequests = null,
        private ?PlatformAccountBanRepository $banRepository = null,
        private ?PlatformAccountBanService $banService = null,
        private ?LoginAttemptRepository $loginAttemptRepository = null,
    ) {
        $this->audit ??= new AuditService();
        $this->purgeRequests ??= new AccountPurgeRequestRepository();
    }

    private function deletionService(): AccountDeletionService
    {
        return $this->accountDeletion ??= \App\Core\Container::get(AccountDeletionService::class);
    }

    private function purgeService(): AccountPurgeService
    {
        return $this->accountPurge ??= new AccountPurgeService();
    }

    /** États d’une personne dans le tableau (clé => libellé). Le bannissement l’emporte sur tout le reste. */
    public const STATE_LABELS = [
        'banni' => 'Banni du site',
        'actif' => 'Actif',
        'verif' => 'E-mail pas encore vérifié',
        'suspendu' => 'Accès suspendu',
        'anonymise' => 'Compte anonymisé',
    ];

    /** Nombre maximal de lignes d’un export CSV. */
    private const EXPORT_MAX_ROWS = 5000;

    public function index(Request $request, array $params = []): Response
    {
        // Sanctions temporaires échues : les fiches sont remises en état avant d’afficher l’état des comptes.
        $this->banService()->expireDue(null, 50);

        $filters = PlatformUsersDirectoryQuery::normalize($request->queryParams());
        $columns = $filters['cols'] ?? PlatformUserColumns::DEFAULT;
        $page = $filters['page'];
        $perPage = $filters['par'];

        $result = $this->users->listGroupedAccountsForPlatformDirectory(
            $filters['q'] !== '' ? $filters['q'] : null,
            $filters['status'] !== '' ? $filters['status'] : null,
            $filters['tenant_id'] > 0 ? $filters['tenant_id'] : null,
            $page,
            $perPage,
            $filters['inactif'] !== '' ? $filters['inactif'] : null,
            $this->directoryOptions($filters)
        );
        $total = (int) ($result['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $groups = $this->decorateDirectoryGroups(
            is_array($result['groups'] ?? null) ? $result['groups'] : [],
            in_array('volume', $columns, true)
        );
        $realTenants = array_values(array_filter(
            $this->tenants->listOverviewForPlatform(),
            static fn (array $t): bool => !PortalAccessChoice::isPlaceholderTenant($t)
        ));

        return Response::view('layout.main', [
            'title' => 'Comptes utilisateurs',
            'content' => 'admin.system.users',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin-users.css'],
            'platformUserGroups' => $groups,
            'platformUsersTotal' => $total,
            'platformUsersPage' => $page,
            'platformUsersPages' => $pages,
            'platformUsersFilters' => $filters,
            'platformUserColumns' => $columns,
            'platformTenants' => $realTenants,
            'pendingPurgeRequests' => $this->purgeRequests->listPending(50),
            'pendingPurgeRequestsCount' => $this->purgeRequests->countPending(),
            'platformBansCount' => $this->bans()->countActive(),
            'platformBanSupportsEnd' => $this->bans()->supportsEndDate(),
        ]);
    }

    /**
     * Export CSV des comptes : mêmes filtres et colonnes que l’écran (GET), ou les personnes cochées (POST).
     * Données personnelles : même droit que la page (administration du site), cellules protégées contre les
     * formules de tableur, et chaque export est inscrit au journal d’audit.
     */
    public function exportCsv(Request $request, array $params = []): Response
    {
        $selected = [];
        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return Response::redirect(url('admin/users'));
            }
            $raw = $request->input('emails', []);
            foreach (is_array($raw) ? $raw : [] as $email) {
                if (is_string($email) && filter_var(strtolower(trim($email)), FILTER_VALIDATE_EMAIL) !== false) {
                    $selected[] = strtolower(trim($email));
                }
            }
            if ($selected === []) {
                Session::flash('error', 'Cochez au moins une personne à exporter.');

                return Response::redirect($this->backUrl($request));
            }
        }
        $filters = PlatformUsersDirectoryQuery::normalize($request->all());
        $columns = $filters['cols'] ?? PlatformUserColumns::DEFAULT;

        $groups = [];
        if ($selected !== []) {
            $groups = $this->users->platformDirectoryGroupsByEmails($selected);
        } else {
            $limit = self::EXPORT_MAX_ROWS;
            for ($page = 1; count($groups) < $limit; $page++) {
                $result = $this->users->listGroupedAccountsForPlatformDirectory(
                    $filters['q'] !== '' ? $filters['q'] : null,
                    $filters['status'] !== '' ? $filters['status'] : null,
                    $filters['tenant_id'] > 0 ? $filters['tenant_id'] : null,
                    $page,
                    500,
                    $filters['inactif'] !== '' ? $filters['inactif'] : null,
                    $this->directoryOptions($filters) + ['max_per_page' => 500]
                );
                $chunk = is_array($result['groups'] ?? null) ? $result['groups'] : [];
                if ($chunk === []) {
                    break;
                }
                array_push($groups, ...$chunk);
                if (count($chunk) < 500) {
                    break;
                }
            }
            $groups = array_slice($groups, 0, $limit);
        }
        $groups = $this->decorateDirectoryGroups($groups, in_array('volume', $columns, true));

        $header = [];
        foreach ($columns as $col) {
            $header[] = $col === 'volume' ? 'Données reçues 30 jours (octets)' : PlatformUserColumns::LABELS[$col];
        }
        $rows = [];
        foreach ($groups as $g) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = match ($col) {
                    'nom' => $this->groupDisplayName($g),
                    'indicatif' => trim((string) ($g['callsign'] ?? '')),
                    'email' => (string) ($g['email'] ?? ''),
                    'communautes' => implode(' | ', (array) ($g['community_names'] ?? [])),
                    'etat' => self::STATE_LABELS[(string) ($g['state'] ?? '')] ?? '',
                    'connexion' => (string) ($g['last_login'] ?? ''),
                    'creation' => (string) ($g['created'] ?? ''),
                    'volume' => (int) ($g['volume_bytes'] ?? 0),
                    'sanctions' => (int) ($g['sanctions_count'] ?? 0),
                    default => '',
                };
            }
            $rows[] = $line;
        }
        $csv = CsvSafe::build($header, $rows);

        AuditService::logPlatformAction(AuditAction::PLATFORM_USERS_EXPORTED, [
            'mode' => $selected !== [] ? 'selection' : 'filtre',
            'lignes' => count($rows),
            'colonnes' => $columns,
            'filtres' => array_keys(array_filter(
                ['recherche' => $filters['q'], 'etat' => $filters['status'], 'communaute' => $filters['tenant_id'], 'inactivite' => $filters['inactif'], 'creation' => $filters['cree'], 'sanctions' => $filters['sanction']],
                static fn ($v): bool => $v !== '' && $v !== 0
            )),
        ]);

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="comptes-' . gmdate('Y-m-d') . '.csv"')
            ->header('Cache-Control', 'no-store')
            ->setBody($csv);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{sort: string, dir: string, created: string, sanctioned: string}
     */
    private function directoryOptions(array $filters): array
    {
        return [
            'sort' => (string) $filters['tri'],
            'dir' => (string) $filters['sens'],
            'created' => (string) $filters['cree'],
            'sanctioned' => (string) $filters['sanction'],
        ];
    }

    /** @param array<string, mixed> $group */
    private function groupDisplayName(array $group): string
    {
        $name = trim((string) ($group['display_name'] ?? ''));

        return $name !== '' ? $name : trim((string) ($group['callsign'] ?? ''));
    }

    /**
     * État d’une personne d’après ses fiches (hors bannissement, ajouté par l’appelant).
     *
     * @param list<array<string, mixed>> $memberships
     */
    public static function stateFromMemberships(array $memberships): string
    {
        $live = array_values(array_filter(
            $memberships,
            static fn (array $m): bool => empty($m['deleted_at']) && (string) ($m['status'] ?? '') !== 'merged'
        ));
        if ($live === []) {
            return $memberships === [] ? 'actif' : 'anonymise';
        }
        $statuses = array_map(static fn (array $m): string => (string) ($m['status'] ?? ''), $live);
        if (in_array('active', $statuses, true)) {
            return 'actif';
        }

        return in_array('pending_verification', $statuses, true) ? 'verif' : 'suspendu';
    }

    /**
     * Prépare l’affichage : le sas « pas d’organisation » n’est jamais présenté comme une communauté,
     * on note les bannissements en cours, les sanctions passées et, si la colonne est demandée, le volume de données.
     *
     * @param list<array<string, mixed>> $groups
     * @return list<array<string, mixed>>
     */
    private function decorateDirectoryGroups(array $groups, bool $withVolume = false): array
    {
        $emails = array_map(static fn (array $g): string => (string) ($g['email'] ?? ''), $groups);
        $bans = $this->bans()->activeByEmails($emails);
        $counts = $this->bans()->countsByEmails($emails);
        $volumes = [];
        if ($withVolume) {
            $steams = [];
            foreach ($groups as $group) {
                foreach ((array) ($group['memberships'] ?? []) as $m) {
                    $steam = trim((string) ($m['steam_id'] ?? ''));
                    if ($steam !== '') {
                        $steams[] = $steam;
                    }
                }
            }
            try {
                $volumes = (new PlatformDataVolumeService())->bytesBySteam($steams, UserRepository::PLATFORM_VOLUME_DAYS);
            } catch (\Throwable $e) {
                error_log('[admin.users] volume : ' . $e->getMessage());
            }
        }
        foreach ($groups as &$group) {
            $memberships = is_array($group['memberships'] ?? null) ? $group['memberships'] : [];
            $real = [];
            $placeholders = [];
            foreach ($memberships as $m) {
                if (PortalAccessChoice::membershipIsPlaceholder($m)) {
                    $placeholders[] = $m;
                } else {
                    $real[] = $m;
                }
            }
            $hasLive = false;
            $names = [];
            foreach ($real as $m) {
                if (empty($m['deleted_at']) && (string) ($m['membership_status'] ?? 'active') === 'active') {
                    $hasLive = true;
                    $name = trim((string) ($m['tenant_name'] ?? ''));
                    if ($name !== '') {
                        $names[$name] = true;
                    }
                }
            }
            $lastLogin = '';
            $created = '';
            $steamSeen = [];
            $volume = 0;
            foreach ($memberships as $m) {
                $login = trim((string) ($m['last_login_at'] ?? ''));
                if ($login !== '' && $login > $lastLogin) {
                    $lastLogin = $login;
                }
                $made = trim((string) ($m['created_at'] ?? ''));
                if ($made !== '' && ($created === '' || $made < $created)) {
                    $created = $made;
                }
                $steam = trim((string) ($m['steam_id'] ?? ''));
                if ($steam !== '' && !isset($steamSeen[$steam])) {
                    $steamSeen[$steam] = true;
                    $volume += (int) ($volumes[$steam] ?? 0);
                }
            }
            $group['memberships'] = $real;
            $group['account_anchor'] = $real[0] ?? $placeholders[0] ?? $memberships[0] ?? null;
            $group['no_org'] = !$hasLive;
            $key = strtolower(trim((string) ($group['email'] ?? '')));
            $group['ban'] = $bans[$key] ?? null;
            $group['sanctions_count'] = (int) ($counts[$key] ?? 0);
            $group['community_names'] = array_map('strval', array_keys($names));
            $group['last_login'] = $lastLogin;
            $group['created'] = $created;
            $group['volume_bytes'] = $volume;
            $group['has_steam'] = $steamSeen !== [];
            $group['state'] = $group['ban'] !== null ? 'banni' : self::stateFromMemberships($memberships);
        }
        unset($group);

        return $groups;
    }

    private function bans(): PlatformAccountBanRepository
    {
        return $this->banRepository ??= new PlatformAccountBanRepository();
    }

    private function banService(): PlatformAccountBanService
    {
        return $this->banService ??= new PlatformAccountBanService($this->users, $this->bans());
    }

    private function loginAttempts(): ?LoginAttemptRepository
    {
        try {
            return $this->loginAttemptRepository ??= new LoginAttemptRepository();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retire la personne d’une communauté sans rien effacer : l’appartenance passe « quittée »,
     * l’accès à cette communauté est coupé, la fiche et l’historique restent (réintégration possible).
     */
    public function removeMembership(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas vous retirer vous-même depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }
        $target = $this->users->findById($userId, null);
        $tenant = $this->tenants->findById($tenantId);
        if ($target === null || $tenant === null) {
            Session::flash('error', 'Compte ou communauté introuvable.');

            return Response::redirect($this->backUrl($request));
        }

        $memberships = $this->users->communityMemberships();
        $memberships->leave($userId, $tenantId);
        $memberships->upsertProfile($userId, $tenantId, ['status' => 'inactive']);
        try {
            // Retiré de la communauté : ses traces de jeu y disparaissent aussi (carte, registre, statistiques).
            (new \App\Services\Admin\PlayerTracePurgeService())->purgeUser($userId, $tenantId);
        } catch (\Throwable) {
        }
        if ((int) ($target['tenant_id'] ?? 0) === $tenantId) {
            // Session ouverte sur cette communauté : elle sera fermée à la requête suivante.
            $this->users->bumpSessionEpoch($userId);
        }

        $this->audit->logChange(
            AuditAction::USER_MEMBERSHIP_REMOVED,
            $tenantId,
            $actorId,
            'user',
            $userId,
            ['membership' => 'active', 'tenant_id' => $tenantId],
            ['membership' => 'left', 'tenant_id' => $tenantId, 'platform_directory' => true],
        );

        $who = $this->personLabel($target);
        Session::flash(
            'success',
            $who . ' ne fait plus partie de « ' . community_display_name($tenant) . ' ». Sa fiche reste dans l’annuaire :'
            . ' le bouton « Réintégrer » annule ce retrait.'
        );

        return Response::redirect($this->backUrl($request));
    }

    /** Annule un retrait : l’appartenance redevient active et la fiche retrouve son nom. */
    public function restoreMembership(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $target = $userId > 0 ? $this->users->findById($userId, null) : null;
        $tenant = $tenantId > 0 ? $this->tenants->findById($tenantId) : null;
        if ($target === null || $tenant === null || !empty($target['deleted_at'])) {
            Session::flash('error', 'Compte ou communauté introuvable (un compte anonymisé ne peut pas être réintégré).');

            return Response::redirect($this->backUrl($request));
        }
        $ban = $this->bans()->findActiveForEmail((string) ($target['email'] ?? ''));
        if ($ban !== null) {
            Session::flash('error', 'Cette personne est bannie du site : levez d’abord le bannissement.');

            return Response::redirect($this->backUrl($request));
        }

        $memberships = $this->users->communityMemberships();
        $profile = ['status' => 'active'];
        $current = $memberships->findProfile($userId, $tenantId) ?? [];
        if ((string) ($current['display_name'] ?? '') === AccountDeletionService::DELETED_DISPLAY_NAME) {
            $global = trim((string) ($target['display_name'] ?? ''));
            if ($global !== '' && $global !== AccountDeletionService::DELETED_DISPLAY_NAME) {
                $profile['display_name'] = $global;
            }
            $callsign = trim((string) ($target['callsign'] ?? ''));
            if ($callsign !== '') {
                $profile['callsign'] = $callsign;
            }
        }
        $memberships->ensureMembership($userId, $tenantId, $profile);

        $this->audit->logChange(
            AuditAction::USER_MEMBERSHIP_RESTORED,
            $tenantId,
            $actorId,
            'user',
            $userId,
            ['membership' => 'left', 'tenant_id' => $tenantId],
            ['membership' => 'active', 'tenant_id' => $tenantId, 'platform_directory' => true],
        );
        Session::flash(
            'success',
            $this->personLabel($target) . ' fait de nouveau partie de « ' . community_display_name($tenant) . ' ».'
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Bannit la personne de tout le site (voir PlatformAccountBanService), pour toujours ou jusqu’à une date.
     * Le motif est obligatoire et la case de confirmation doit être cochée : sans elles rien n’est fait.
     */
    public function ban(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorId = (int) Session::get('user_id');
        $email = strtolower($request->inputString('email'));
        $email = trim($email);
        if ($request->inputString('confirm_ban') !== '1') {
            Session::flash('error', 'Cochez la case de confirmation : le bannissement n’a pas été appliqué.');

            return Response::redirect($this->backUrl($request));
        }
        $end = PlatformAccountBanService::resolveEnd($request->inputString('duree', 'definitif'), $request->inputString('fin'));
        if (!$end['ok']) {
            Session::flash('error', (string) ($end['error'] ?? 'Durée de sanction invalide.'));

            return Response::redirect($this->backUrl($request));
        }
        $endsAt = $end['endsAt'] ?? null;
        $ips = $request->input('block_ips', []);
        $ips = is_array($ips) ? array_map('strval', array_filter($ips, 'is_scalar')) : [];
        $extra = trim($request->inputString('extra_ips'));
        if ($extra !== '') {
            $ips[] = $extra;
        }
        $reason = trim($request->inputString('reason'));
        $result = $this->banService()->ban(
            $email,
            $reason,
            $ips,
            $request->inputString('block_steam') === '1',
            $actorId,
            $endsAt
        );
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Bannissement impossible.'));

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_BANNED,
            0,
            $actorId,
            'user',
            0,
            ['email' => $email],
            [
                'email' => $email,
                'ban_id' => (int) ($result['ban_id'] ?? 0),
                'reason' => mb_substr($reason, 0, 500),
                'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
                'blocked_ips' => (int) ($result['blocked_ips'] ?? 0),
                'steam_blocked' => !empty($result['steam_blocked']),
            ],
        );

        $bits = ['connexion et inscription refusées pour ' . $email];
        if (!empty($result['steam_blocked'])) {
            $bits[] = 'Steam ID refusé par le mod';
        }
        if ((int) ($result['blocked_ips'] ?? 0) > 0) {
            $bits[] = (int) $result['blocked_ips'] . ' adresse(s) réseau bloquée(s)';
        }
        $until = $endsAt !== null
            ? ' Sanction temporaire : elle prend fin le ' . $endsAt->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i') . ' (heure de Paris).'
            : ' Sanction sans date de fin.';
        Session::flash('success', 'Personne bannie du site : ' . implode(', ', $bits) . '. Ses sessions ouvertes sont fermées.' . $until);

        return Response::redirect($this->backUrl($request));
    }

    public function unban(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorId = (int) Session::get('user_id');
        $email = strtolower(trim($request->inputString('email')));
        $note = mb_substr(trim($request->inputString('note')), 0, 500);
        $result = $this->banService()->lift($email, $actorId, $note !== '' ? $note : null);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Levée impossible.'));

            return Response::redirect($this->backUrl($request));
        }
        $this->audit->logChange(
            AuditAction::USER_UNBANNED,
            0,
            $actorId,
            'user',
            0,
            ['email' => $email, 'banned' => true],
            ['email' => $email, 'banned' => false, 'restored_accounts' => (int) ($result['restored'] ?? 0), 'note' => $note],
        );
        Session::flash('success', 'Bannissement levé : la personne peut de nouveau se connecter, ses fiches ont retrouvé leur état d’avant.');

        return Response::redirect($this->backUrl($request));
    }

    /** Bloque une adresse réseau sur tout le site (connexion, inscription, mod). */
    public function blockIp(Request $request, array $params = []): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $back = $email !== '' ? url('admin/users/person') . '?email=' . rawurlencode($email) . '#reseau' : url('admin/users');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($back);
        }
        $ip = trim((string) $request->input('ip', ''));
        $reason = trim((string) $request->input('reason', ''));
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            Session::flash('error', 'Adresse réseau invalide.');

            return Response::redirect($back);
        }
        try {
            /** @var \App\Services\Moderation\IndicatorBlocklistService $blocklist */
            $blocklist = Container::get(\App\Services\Moderation\IndicatorBlocklistService::class);
            /** @var \App\Repositories\BlockedIndicatorRepository $indicators */
            $indicators = Container::get(\App\Repositories\BlockedIndicatorRepository::class);
            if ($indicators->isIpBlockedGlobally($ip)) {
                Session::flash('success', 'Cette adresse réseau était déjà bloquée.');

                return Response::redirect($back);
            }
            $blocklist->addIpBlock(
                (int) Session::get('user_id'),
                'global',
                null,
                $ip,
                $reason !== '' ? $reason : ('Bloquée depuis le dossier de ' . $email),
                null
            );
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($back);
        }
        Session::flash('success', 'Adresse réseau bloquée sur tout le site. Pour la débloquer : Liste de restriction.');

        return Response::redirect($back);
    }

    /**
     * Adresses réseau des dernières connexions réussies, avec l’état de blocage.
     *
     * @return list<array{ip: string, last_seen: string, logins: int, blocked: bool}>
     */
    private function knownIpsWithBlockState(string $email): array
    {
        $attempts = $this->loginAttempts();
        if ($attempts === null) {
            return [];
        }
        try {
            $rows = $attempts->listRecentSuccessIpsForEmail($email, 180, 20);
            /** @var \App\Repositories\BlockedIndicatorRepository $indicators */
            $indicators = Container::get(\App\Repositories\BlockedIndicatorRepository::class);
            $out = [];
            foreach ($rows as $row) {
                $ip = (string) ($row['ip'] ?? '');
                if ($ip === '') {
                    continue;
                }
                $out[] = [
                    'ip' => $ip,
                    'last_seen' => (string) ($row['last_seen'] ?? ''),
                    'logins' => (int) ($row['logins'] ?? 0),
                    'blocked' => $indicators->isIpBlockedGlobally($ip),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $user */
    private function personLabel(array $user): string
    {
        foreach (['callsign', 'display_name', 'email'] as $key) {
            $v = trim((string) ($user[$key] ?? ''));
            if ($v !== '' && $v !== AccountDeletionService::DELETED_DISPLAY_NAME) {
                return $v;
            }
        }

        return 'Cette personne';
    }

    /**
     * Dossier personne multi-communautés (toutes les fiches users du même e-mail).
     */
    /** Steam en double (porté par une autre personne) ou signalé : état affiché avec « Résoudre ». */
    private function steamConflictState(string $steam): string
    {
        if ($steam === '') {
            return \App\Services\Account\SteamConflictService::STATE_NONE;
        }
        try {
            return (new \App\Services\Account\SteamConflictService())->stateFor($steam);
        } catch (\Throwable) {
            return \App\Services\Account\SteamConflictService::STATE_NONE;
        }
    }

    public function showPerson(Request $request, array $params = []): Response
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $userId = (int) $request->query('user_id', 0);
        if ($email === '' && $userId > 0) {
            $anchor = $this->users->findById($userId, null);
            if ($anchor !== null) {
                $email = strtolower(trim((string) ($anchor['email'] ?? '')));
            }
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '@deleted.invalid')) {
            Session::flash('error', 'Personne introuvable.');

            return Response::redirect(url('admin/users'));
        }

        // Sanction temporaire échue : on remet les fiches en état avant d'afficher le dossier.
        $this->banService()->expireDue($email);

        $memberships = $this->users->listAllMembershipsByEmail($email);
        if ($memberships === []) {
            Session::flash('error', 'Aucune appartenance trouvée pour cette adresse.');

            return Response::redirect(url('admin/users'));
        }

        /** @var UserProfileRepository $profiles */
        $profiles = Container::get(UserProfileRepository::class);
        /** @var UserLegalIdentityRepository $legalRepo */
        $legalRepo = Container::get(UserLegalIdentityRepository::class);
        /** @var PersonnelProfileRepository $personnelProfiles */
        $personnelProfiles = Container::get(PersonnelProfileRepository::class);
        /** @var PersonnelExtrasRepository $personnelExtras */
        $personnelExtras = Container::get(PersonnelExtrasRepository::class);
        /** @var PersonnelAssignmentRepository $assignments */
        $assignments = Container::get(PersonnelAssignmentRepository::class);
        /** @var GradeRepository $grades */
        $grades = Container::get(GradeRepository::class);

        $visibleMemberships = PortalAccessChoice::personFileDossiers($memberships);
        $dossierMemberships = [];
        $globalSteam = '';
        $globalAthena = '';
        $displayName = '';
        $callsign = '';
        foreach ($memberships as $m) {
            $steam = trim((string) ($m['steam_id'] ?? ''));
            if ($steam !== '' && $globalSteam === '') {
                $globalSteam = $steam;
            }
            $athena = trim((string) ($m['athena_identifier'] ?? ''));
            if ($athena !== '' && $globalAthena === '') {
                $globalAthena = $athena;
            }
            $dn = trim((string) ($m['display_name'] ?? ''));
            if ($dn !== '' && $dn !== 'Compte supprimé' && $displayName === '') {
                $displayName = $dn;
            }
            $cs = trim((string) ($m['callsign'] ?? ''));
            if ($cs !== '' && $callsign === '') {
                $callsign = $cs;
            }
        }
        foreach ($visibleMemberships as $m) {
            $uid = (int) ($m['id'] ?? 0);
            $tid = (int) ($m['tenant_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }

            $gradeId = (int) ($m['grade_id'] ?? 0);
            $grade = $gradeId > 0 ? $grades->findById($gradeId, $tid) : null;
            $pp = $personnelProfiles->getByUserId($uid, $tid) ?? [];
            $extras = $personnelExtras->getByUserId($uid, $tid) ?? [];
            $primaryAssignment = $assignments->getPrimaryAssignment($uid, $tid);
            $roleIds = $this->users->listOrganizationRoleIdsForUser($uid, $tid);
            $roleNames = [];
            $roleName = trim((string) ($m['role_name'] ?? ''));
            if ($roleName !== '') {
                $roleNames[] = $roleName;
            }

            $dossierMemberships[] = [
                'user' => $m,
                'profile' => $profiles->getByUserId($uid) ?? [],
                'legal' => $legalRepo->getByUserId($uid) ?? [],
                'personnel_profile' => is_array($pp) ? $pp : [],
                'personnel_extras' => is_array($extras) ? $extras : [],
                'grade' => is_array($grade) ? $grade : null,
                'primary_assignment' => is_array($primaryAssignment) ? $primaryAssignment : null,
                'role_names' => $roleNames,
                'org_role_ids' => $roleIds,
            ];
        }

        // Identité civile : première fiche légale non vide
        $civil = ['first_name' => '', 'last_name' => '', 'phone' => '', 'birth_date' => '', 'nationality' => ''];
        foreach ($dossierMemberships as $pack) {
            $legal = is_array($pack['legal'] ?? null) ? $pack['legal'] : [];
            foreach (array_keys($civil) as $k) {
                $v = trim((string) ($legal[$k] ?? ''));
                if ($v !== '' && $civil[$k] === '') {
                    $civil[$k] = $v;
                }
            }
            $prof = is_array($pack['profile'] ?? null) ? $pack['profile'] : [];
            foreach (['first_name', 'last_name', 'phone'] as $k) {
                $v = trim((string) ($prof[$k] ?? ''));
                if ($v !== '' && $civil[$k] === '') {
                    $civil[$k] = $v;
                }
            }
        }

        $hasLiveOrg = $this->users->emailHasActiveNonDefaultMembership($email);

        /** @var UserIdentityMergeService $mergeService */
        $mergeService = Container::get(UserIdentityMergeService::class);
        $survivorPreviewId = (int) $request->query('survivor_id', 0);
        $mergePreview = $mergeService->previewEmailMerge(
            $email,
            $survivorPreviewId > 0 ? $survivorPreviewId : null
        );

        return Response::view('layout.main', [
            'title' => 'Dossier personne',
            'content' => 'admin.system.user_person',
            'personEmail' => $email,
            'personDisplayName' => $displayName,
            'personCallsign' => $callsign,
            'personSteamId' => $globalSteam,
            'personSteamState' => $this->steamConflictState($globalSteam),
            'personAthenaId' => $globalAthena,
            'personCivil' => $civil,
            'personHasLiveOrg' => $hasLiveOrg,
            'personMemberships' => $dossierMemberships,
            'personMergePreview' => $mergePreview,
            'personIsPlatformAdmin' => $this->users->emailHasPlatformAdmin($email),
            'personBan' => $this->bans()->findActiveForEmail($email),
            'personBanHistory' => $this->bans()->historyForEmail($email),
            'personBanSupportsEnd' => $this->bans()->supportsEndDate(),
            'personLiveFiches' => count($this->users->listIdsByEmailNormalized($email)),
            'personKnownIps' => $this->knownIpsWithBlockState($email),
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin-users.css'],
        ]);
    }

    public function platformAdmins(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Administrateurs du site',
            'content' => 'admin.system.platform_admins',
            'platformAdmins' => $this->users->listPlatformAdminPeople(),
            'platformAdminConfirmGrant' => \App\Services\Rbac\PlatformAdminFlag::CONFIRM_GRANT,
            'platformAdminConfirmRevoke' => \App\Services\Rbac\PlatformAdminFlag::CONFIRM_REVOKE,
        ]);
    }

    public function setPlatformAdmin(Request $request, array $params = []): Response
    {
        $back = url('admin/system/administrateurs-site');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $enabled = $request->input('action') === 'grant';
        $expected = $enabled
            ? \App\Services\Rbac\PlatformAdminFlag::CONFIRM_GRANT
            : \App\Services\Rbac\PlatformAdminFlag::CONFIRM_REVOKE;
        if (!\App\Services\Rbac\PlatformAdminFlag::confirmMatches((string) $request->input('confirmation', ''), $expected)) {
            Session::flash('error', 'Recopiez la phrase de confirmation pour continuer.');

            return Response::redirect($back);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Indiquez l’adresse d’un compte existant.');

            return Response::redirect($back);
        }

        $before = $this->users->emailHasPlatformAdmin($email);
        $result = $this->users->setPlatformAdminForEmail($email, $enabled);
        if ($result['ok'] === false) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));

            return Response::redirect($back);
        }
        if ($before === $enabled) {
            Session::flash('success', $enabled
                ? 'Cette personne administrait déjà le site.'
                : 'Cette personne n’administrait pas le site.');

            return Response::redirect($back);
        }

        $actorId = (int) Session::get('user_id');
        $actorTenantId = (int) Session::get('tenant_id');
        $this->audit->logChange(
            AuditAction::PLATFORM_ADMIN_UPDATED,
            $actorTenantId > 0 ? $actorTenantId : 0,
            $actorId,
            'user',
            0,
            ['is_platform_admin' => $before ? 1 : 0, 'email' => $email],
            ['is_platform_admin' => $enabled ? 1 : 0, 'email' => $email],
        );

        Session::flash(
            'success',
            $enabled
                ? 'Cette personne peut désormais administrer le site.'
                : 'Cette personne n’administre plus le site.'
        );

        return Response::redirect($back);
    }

    /**
     * Fusionne plusieurs fiches users partageant le même e-mail en un compte survivant.
     */
    public function mergeAccounts(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/users'));
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        $survivorUserId = (int) $request->input('survivor_user_id', 0);
        $confirmation = strtolower(trim((string) $request->input('confirm_email', '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $confirmation !== $email) {
            Session::flash('error', 'Adresse de confirmation incorrecte : aucune fusion effectuée.');

            return Response::redirect(
                $email !== '' ? url('admin/users/person') . '?email=' . rawurlencode($email) : url('admin/users')
            );
        }

        /** @var UserIdentityMergeService $mergeService */
        $mergeService = Container::get(UserIdentityMergeService::class);
        try {
            $result = $mergeService->mergeAccountsForEmail(
                $email,
                $survivorUserId > 0 ? $survivorUserId : null
            );
        } catch (\Throwable $e) {
            Session::flash('error', 'Fusion impossible : ' . $e->getMessage());

            return Response::redirect(url('admin/users/person') . '?email=' . rawurlencode($email));
        }

        $actorId = (int) Session::get('user_id');
        $actorTenantId = (int) Session::get('tenant_id');
        $this->audit->logChange(
            AuditAction::USER_IDENTITY_MERGED,
            $actorTenantId > 0 ? $actorTenantId : 0,
            $actorId,
            'user',
            (int) $result['survivor_id'],
            ['email' => $email],
            [
                'platform_directory' => true,
                'merged_count' => (int) ($result['merged'] ?? 0),
                'survivor_user_id' => (int) ($result['survivor_id'] ?? 0),
                'steam_collisions' => $result['steam_collisions'] ?? [],
            ],
        );

        $message = 'Fusion terminée — compte survivant #' . (int) $result['survivor_id']
            . ', ' . (int) ($result['merged'] ?? 0) . ' fiche(s) absorbée(s).';
        if (($result['steam_collisions'] ?? []) !== []) {
            $message .= ' ' . count($result['steam_collisions']) . ' conflit(s) Steam ignoré(s) — voir le journal.';
        }
        Session::flash('success', $message);

        return Response::redirect(url('admin/users/person') . '?email=' . rawurlencode($email));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $userId = (int) ($params['id'] ?? 0);
        if ($userId < 1) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('admin/users'));
        }

        /** @var PlatformUserProfileService $editor */
        $editor = Container::get(PlatformUserProfileService::class);
        $pack = $editor->load($userId);
        if ($pack === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect(url('admin/users'));
        }
        $user = is_array($pack['user'] ?? null) ? $pack['user'] : [];
        if (!empty($user['deleted_at'])) {
            Session::flash('error', 'Ce compte a déjà été anonymisé : il ne peut plus être modifié.');
            $email = strtolower(trim((string) ($user['email'] ?? '')));

            return Response::redirect(
                $email !== '' && !str_ends_with($email, '@deleted.invalid')
                    ? url('admin/users/person') . '?email=' . rawurlencode($email)
                    : url('admin/users')
            );
        }

        $label = trim((string) ($user['display_name'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($user['callsign'] ?? ''));
        }
        if ($label === '') {
            $label = trim((string) ($user['email'] ?? ''));
        }

        return Response::view('layout.main', [
            'title' => 'Modifier le compte — ' . ($label !== '' ? $label : 'fiche'),
            'content' => 'admin.system.user_edit',
            'platformEdit' => $pack,
            'platformEditUserId' => $userId,
            'platformEditAtak' => (static function () use ($userId): ?array {
                try {
                    return (new \App\Services\Admin\AdminUserAtakService())->overview($userId);
                } catch (\Throwable) {
                    return null;
                }
            })(),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $userId = (int) ($params['id'] ?? 0);
        $editUrl = url('admin/users/' . $userId . '/edit');
        if ($userId < 1) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('admin/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($editUrl);
        }

        $actorId = (int) Session::get('user_id');
        $actorTenantId = (int) Session::get('tenant_id');
        $before = $this->users->findById($userId, null);
        if ($before === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect(url('admin/users'));
        }

        /** @var PlatformUserProfileService $editor */
        $editor = Container::get(PlatformUserProfileService::class);
        $result = $editor->save($userId, $actorId, $request->all());
        if ($result['ok'] === false) {
            Session::flash('error', (string) ($result['error'] ?? 'Enregistrement impossible.'));

            return Response::redirect($editUrl);
        }

        $after = $this->users->findById($userId, null) ?? [];
        $this->audit->logChange(
            AuditAction::USER_PROFILE_UPDATED,
            $actorTenantId > 0 ? $actorTenantId : (int) ($before['tenant_id'] ?? 0),
            $actorId,
            'user',
            $userId,
            [
                'email' => (string) ($before['email'] ?? ''),
                'display_name' => (string) ($before['display_name'] ?? ''),
                'status' => (string) ($before['status'] ?? ''),
                'callsign' => (string) ($before['callsign'] ?? ''),
            ],
            [
                'email' => (string) ($after['email'] ?? ''),
                'display_name' => (string) ($after['display_name'] ?? ''),
                'status' => (string) ($after['status'] ?? ''),
                'callsign' => (string) ($after['callsign'] ?? ''),
                'platform_directory' => true,
            ],
        );

        $notes = is_array($result['notes'] ?? null) ? $result['notes'] : [];
        Session::flash(
            'success',
            'Fiche enregistrée.' . ($notes !== [] ? ' ' . implode(' ', $notes) : '')
        );

        $email = strtolower(trim((string) ($after['email'] ?? $before['email'] ?? '')));
        if ($email !== '' && !str_ends_with($email, '@deleted.invalid')) {
            return Response::redirect(url('admin/users/person') . '?email=' . rawurlencode($email));
        }

        return Response::redirect($editUrl);
    }

    public function setStatus(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $status = trim((string) $request->input('status', ''));
        if ($userId < 1 || $tenantId < 1 || !in_array($status, ['active', 'inactive'], true)) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId && $status === 'inactive') {
            Session::flash('error', 'Vous ne pouvez pas désactiver votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }
        if (!empty($target['deleted_at'])) {
            Session::flash('error', 'Ce compte a déjà été supprimé.');

            return Response::redirect($this->backUrl($request));
        }

        $ok = $this->users->update($userId, $tenantId, ['status' => $status]);
        if (!$ok) {
            Session::flash('error', 'Impossible de mettre à jour ce compte.');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_STATUS_UPDATED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            ['status' => (string) ($target['status'] ?? '')],
            ['status' => $status, 'platform_directory' => true],
        );

        Session::flash(
            'success',
            $status === 'active'
                ? 'Le compte a été réactivé.'
                : 'Le compte a été désactivé. La personne ne pourra plus se connecter avec cet accès.'
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Suppression douce (anonymisation).
     *
     * scope=org  → uniquement la fiche de cette communauté
     * scope=site → toutes les fiches partageant le même e-mail (site entier)
     */
    public function delete(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $scope = strtolower(trim((string) $request->input('scope', 'site')));
        if (!in_array($scope, ['org', 'site'], true)) {
            $scope = 'site';
        }
        if ($userId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }

        $result = $scope === 'org'
            ? $this->deletionService()->softDeleteMembership($userId, $tenantId, $actorId)
            : $this->deletionService()->softDeleteAccount($userId, $tenantId, $actorId);
        if (!$result['ok']) {
            Session::flash('error', 'Impossible de supprimer ce compte.');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_DELETED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            ['status' => (string) ($target['status'] ?? ''), 'email' => (string) ($target['email'] ?? '')],
            [
                'status' => 'deleted',
                'platform_directory' => true,
                'scope' => $scope,
                'anonymized_user_ids' => $result['anonymized_user_ids'],
            ],
        );

        $handoverNote = AccountDeletionService::describeHandover($result['handover'] ?? []);
        Session::flash(
            'success',
            ($scope === 'org'
                ? 'Appartenance retirée de cette communauté. Les autres communautés de la personne sont intactes.'
                : 'Compte supprimé définitivement sur tout le site : données personnelles, compte de jeu et rôles effacés. Une nouvelle inscription avec cette adresse repart de zéro.')
            . ($handoverNote !== '' ? ' ' . $handoverNote : '')
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Suppression définitive.
     *
     * scope=org  → purge uniquement cette fiche users (une communauté)
     * scope=site → purge toutes les fiches du même e-mail
     */
    public function purge(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $userId = (int) $request->input('user_id');
        $tenantId = (int) $request->input('tenant_id');
        $scope = strtolower(trim((string) $request->input('scope', 'site')));
        if (!in_array($scope, ['org', 'site'], true)) {
            $scope = 'site';
        }
        if ($userId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($userId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cet écran.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect($this->backUrl($request));
        }

        $email = strtolower(trim((string) ($target['email'] ?? '')));
        $confirmation = strtolower(trim((string) $request->input('confirm_email', '')));
        if ($email === '' || $confirmation !== $email) {
            Session::flash('error', 'Adresse de confirmation incorrecte : le compte n’a pas été touché.');

            return Response::redirect($this->backUrl($request));
        }

        $siblingIds = [$userId];
        if ($scope === 'site' && $email !== '' && !str_ends_with($email, '@deleted.invalid')) {
            $siblingIds = $this->users->listIdsByEmailNormalized($email);
            if ($siblingIds === []) {
                $siblingIds = [$userId];
            }
        }

        $this->audit->logChange(
            AuditAction::USER_PURGED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $userId,
            [
                'status' => (string) ($target['status'] ?? ''),
                'email' => $email,
                'display_name' => (string) ($target['display_name'] ?? ''),
            ],
            [
                'status' => 'purged',
                'platform_directory' => true,
                'scope' => $scope,
                'target_user_ids' => array_map('intval', $siblingIds),
            ],
        );

        $handover = $this->deletionService()->releaseIdentityBeforePurge(
            $siblingIds,
            $email,
            $scope === 'org' ? $tenantId : null
        );
        $report = $this->purgeService()->purge($userId, $scope === 'org' ? [] : $siblingIds);
        if (!$report['ok'] && $report['purged_user_ids'] === []) {
            Session::flash(
                'error',
                'Suppression définitive impossible : ' . ($report['errors'][0] ?? 'erreur inconnue') . '.'
            );

            return Response::redirect($this->backUrl($request));
        }

        $scopeLabel = $scope === 'org' ? 'dans cette communauté' : 'sur tout le site';
        $message = 'Compte supprimé définitivement ' . $scopeLabel . ' — '
            . count($report['purged_user_ids']) . ' fiche' . (count($report['purged_user_ids']) > 1 ? 's' : '')
            . ', ' . $report['rows_deleted'] . ' ligne' . ($report['rows_deleted'] > 1 ? 's' : '') . ' effacée'
            . ($report['rows_deleted'] > 1 ? 's' : '')
            . ', ' . $report['rows_detached'] . ' référence' . ($report['rows_detached'] > 1 ? 's' : '')
            . ' détachée' . ($report['rows_detached'] > 1 ? 's' : '') . '.';
        $handoverNote = AccountDeletionService::describeHandover($handover);
        if ($handoverNote !== '') {
            $message .= ' ' . $handoverNote;
        }
        if ($report['errors'] !== []) {
            $message .= ' ' . count($report['errors']) . ' avertissement'
                . (count($report['errors']) > 1 ? 's' : '') . ' — voir le journal serveur.';
            foreach (array_slice($report['errors'], 0, 20) as $error) {
                error_log('[account_purge] user #' . $userId . ' : ' . $error);
            }
        }
        Session::flash('success', $message);

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Purge en série les fiches « Compte supprimé » laissées par l’anonymisation.
     */
    public function purgeAnonymized(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        if (strtolower(trim((string) $request->input('confirm_phrase', ''))) !== 'supprimer definitivement') {
            Session::flash('error', 'Phrase de confirmation incorrecte : aucun compte n’a été touché.');

            return Response::redirect($this->backUrl($request));
        }

        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');

        $this->audit->logChange(
            AuditAction::USER_PURGED,
            $actorTenantId > 0 ? $actorTenantId : 0,
            $actorId,
            'user',
            0,
            ['scope' => 'anonymized_accounts'],
            ['status' => 'purged', 'platform_directory' => true, 'bulk' => true],
        );

        $result = $this->purgeService()->purgeAnonymizedAccounts();
        foreach (array_slice($result['errors'], 0, 20) as $error) {
            error_log('[account_purge_bulk] ' . $error);
        }

        if ($result['purged'] === 0 && $result['failed'] === 0) {
            Session::flash('success', 'Aucune fiche anonymisée à retirer des annuaires.');

            return Response::redirect($this->backUrl($request));
        }

        Session::flash(
            $result['failed'] > 0 ? 'error' : 'success',
            $result['purged'] . ' fiche' . ($result['purged'] > 1 ? 's' : '') . ' anonymisée'
            . ($result['purged'] > 1 ? 's' : '') . ' retirée' . ($result['purged'] > 1 ? 's' : '')
            . ' des annuaires — historique conservé sous « Ancien membre »'
            . ($result['rows_reassigned'] > 0
                ? ' (' . $result['rows_reassigned'] . ' lien' . ($result['rows_reassigned'] > 1 ? 's' : '') . ' réassigné'
                . ($result['rows_reassigned'] > 1 ? 's' : '') . ')'
                : '')
            . ($result['failed'] > 0 ? ' · ' . $result['failed'] . ' en échec, voir le journal serveur.' : '.')
        );

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Approuve une demande organisateur : retire la fiche anonymisée de l’orga en
     * conservant l’historique sous « Ancien membre ».
     */
    public function approvePurgeRequest(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorTenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $requestId = (int) $request->input('request_id', $params['id'] ?? 0);
        if ($requestId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        $row = $this->purgeRequests->findById($requestId);
        if ($row === null || (string) ($row['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Demande introuvable ou déjà traitée.');

            return Response::redirect($this->backUrl($request));
        }
        $targetUserId = (int) ($row['target_user_id'] ?? 0);
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        if ($targetUserId < 1 || $tenantId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        if ($targetUserId === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas purger votre propre compte.');

            return Response::redirect($this->backUrl($request));
        }

        $target = $this->users->findById($targetUserId, $tenantId);
        if ($target === null) {
            $this->purgeRequests->resolve($requestId, 'approved', $actorId, 'Cible déjà absente — demande clôturée.');
            Session::flash('success', 'La fiche ciblée n’existe plus ; la demande a été clôturée.');

            return Response::redirect($this->backUrl($request));
        }
        if (!AccountDeletionService::isAnonymizedUser($target)) {
            $anon = $this->deletionService()->softDeleteMembership($targetUserId, $tenantId, $actorId);
            if (!$anon['ok']) {
                Session::flash('error', 'Impossible d’anonymiser ce compte avant de le retirer de la communauté.');

                return Response::redirect($this->backUrl($request));
            }
        }

        $resolutionNote = trim((string) $request->input('resolution_note', ''));
        if (!$this->purgeRequests->resolve(
            $requestId,
            'approved',
            $actorId,
            $resolutionNote !== '' ? $resolutionNote : 'Approuvée — purge orga'
        )) {
            Session::flash('error', 'Impossible de clôturer la demande (peut-être déjà traitée).');

            return Response::redirect($this->backUrl($request));
        }

        $this->audit->logChange(
            AuditAction::USER_PURGED,
            $actorTenantId > 0 ? $actorTenantId : $tenantId,
            $actorId,
            'user',
            $targetUserId,
            [
                'status' => (string) ($target['status'] ?? ''),
                'email' => (string) ($target['email'] ?? ''),
                'display_name' => (string) ($target['display_name'] ?? ''),
                'purge_request_id' => $requestId,
            ],
            [
                'status' => 'purged',
                'platform_directory' => true,
                'scope' => 'org',
                'via' => 'org_purge_request',
                'purge_request_id' => $requestId,
            ],
        );

        $report = $this->purgeService()->purgeFromTenantPreservingHistory($targetUserId);
        if (!$report['ok'] && $report['purged_user_ids'] === []) {
            Session::flash(
                'error',
                'Demande approuvée, mais la purge a échoué : ' . ($report['errors'][0] ?? 'erreur inconnue') . '.'
            );

            return Response::redirect($this->backUrl($request));
        }

        $message = 'Demande approuvée — fiche retirée de la communauté, historique conservé sous « Ancien membre »'
            . ($report['rows_reassigned'] > 0
                ? ' (' . $report['rows_reassigned'] . ' lien' . ($report['rows_reassigned'] > 1 ? 's' : '')
                . ' réassigné' . ($report['rows_reassigned'] > 1 ? 's' : '') . ')'
                : '')
            . '.';
        if ($report['errors'] !== []) {
            $message .= ' ' . count($report['errors']) . ' avertissement'
                . (count($report['errors']) > 1 ? 's' : '') . ' — voir le journal serveur.';
            foreach (array_slice($report['errors'], 0, 20) as $error) {
                error_log('[account_purge_request] request #' . $requestId . ' : ' . $error);
            }
        }
        Session::flash('success', $message);

        return Response::redirect($this->backUrl($request));
    }

    /**
     * Refuse une demande organisateur de suppression définitive.
     */
    public function rejectPurgeRequest(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($this->backUrl($request));
        }
        $actorId = (int) Session::get('user_id');
        $requestId = (int) $request->input('request_id', $params['id'] ?? 0);
        if ($requestId < 1) {
            Session::flash('error', 'Demande invalide.');

            return Response::redirect($this->backUrl($request));
        }
        $row = $this->purgeRequests->findById($requestId);
        if ($row === null || (string) ($row['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Demande introuvable ou déjà traitée.');

            return Response::redirect($this->backUrl($request));
        }
        $resolutionNote = trim((string) $request->input('resolution_note', ''));
        if (!$this->purgeRequests->resolve(
            $requestId,
            'rejected',
            $actorId,
            $resolutionNote !== '' ? $resolutionNote : 'Refusée par la plateforme'
        )) {
            Session::flash('error', 'Impossible de refuser la demande.');

            return Response::redirect($this->backUrl($request));
        }
        Session::flash('success', 'Demande de suppression définitive refusée.');

        return Response::redirect($this->backUrl($request));
    }

    private function backUrl(Request $request): string
    {
        $person = strtolower(trim((string) $request->input('return_person', '')));
        if ($person !== '' && filter_var($person, FILTER_VALIDATE_EMAIL)) {
            return url('admin/users/person') . '?email=' . rawurlencode($person);
        }
        // Retour sur la même vue du tableau : filtres, tri, page et colonnes viennent de listes blanches.
        $src = [];
        $returnQs = $request->inputString('return_qs');
        if ($returnQs !== '' && strlen($returnQs) <= 1500) {
            parse_str($returnQs, $src);
        } else {
            $src = [
                'q' => $request->inputString('return_q'),
                'status' => $request->inputString('return_status'),
                'tenant_id' => $request->inputString('return_tenant_id'),
                'inactif' => $request->inputString('return_inactif'),
                'page' => $request->inputString('return_page'),
            ];
        }
        $query = PlatformUsersDirectoryQuery::query(PlatformUsersDirectoryQuery::normalize($src));

        return url('admin/users') . ($query !== '' ? '?' . $query : '');
    }
}
