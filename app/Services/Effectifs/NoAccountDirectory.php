<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\MemberProfileInviteRepository;
use App\Repositories\RosterFicheEmailRepository;
use App\Repositories\RosterFicheEventRepository;
use App\Repositories\UserRepository;
use App\Support\RosterOnlyMember;

/**
 * Personnes sans compte utilisable d'une communauté : fiches tenues par l'état-major (adresse réservée), comptes créés
 * mais jamais activés, et fiches avec un lien d'invitation en cours.
 *
 * Statuts : fiche (aucun compte, aucun lien), invitee (lien envoyé, pas encore activé), en_attente (adresse réelle,
 * compte jamais activé). Les comptes activés, fusionnés, supprimés ou en service n'y figurent pas.
 */
final class NoAccountDirectory
{
    public const STATUS_FICHE = 'fiche';
    public const STATUS_INVITEE = 'invitee';
    public const STATUS_PENDING = 'en_attente';
    /** Compte créé depuis la fiche : la personne n'est plus « sans compte », la ligne reste consultable. */
    public const STATUS_ACCOUNT = 'compte';

    public const STATUSES = [
        self::STATUS_FICHE => 'Sans compte',
        self::STATUS_INVITEE => 'Invitation envoyée',
        self::STATUS_PENDING => 'Compte à activer',
        self::STATUS_ACCOUNT => 'Compte actif',
    ];

    public const SOURCES = [
        'manual' => 'Saisie à la main',
        'import' => 'Import de tableur',
        'invite' => 'Invitation',
        'legacy' => 'Fiche existante',
    ];

    public function __construct(
        private ?UserRepository $users = null,
        private ?RosterFicheEventRepository $events = null,
        private ?MemberProfileInviteRepository $invites = null,
        private ?RosterFicheEmailRepository $emails = null,
    ) {
        $this->users ??= \App\Core\Container::get(UserRepository::class);
        $this->events ??= new RosterFicheEventRepository();
    }

    /**
     * Statut d'une ligne users, ou null si la personne a un compte utilisable. Calcul pur.
     *
     * @param array<string, mixed> $user
     * @param string|null $inviteStatus état du lien le plus récent (MemberProfileInviteService::STATUS_*), s'il y en a un
     */
    public static function classify(array $user, ?string $inviteStatus = null): ?string
    {
        $status = strtolower(trim((string) ($user['status'] ?? '')));
        if (in_array($status, ['merged', 'deleted', 'left', 'banned'], true)) {
            return null;
        }
        $email = (string) ($user['email'] ?? '');
        if (RosterOnlyMember::isRosterEmail($email)) {
            return in_array($inviteStatus, [MemberProfileInviteService::STATUS_ACTIVE, MemberProfileInviteService::STATUS_OPENED, MemberProfileInviteService::STATUS_USED], true)
                ? self::STATUS_INVITEE
                : self::STATUS_FICHE;
        }
        if ($status === 'pending_verification' && trim((string) ($user['last_login_at'] ?? '')) === '') {
            return self::STATUS_PENDING;
        }

        return null;
    }

    /** Origine affichée : événement d'historique, sinon « Fiche existante ». */
    public static function sourceLabel(string $source): string
    {
        return self::SOURCES[$source] ?? self::SOURCES['legacy'];
    }

    /**
     * $withConverted ajoute les personnes dont le compte a été créé depuis leur fiche (statut « Compte actif »).
     *
     * @return list<array{id: int, name: string, callsign: string, email: string, status: string, status_label: string, invite_id: int, invite_at: string, invite_expired: bool, grade_id: int, source: string, source_label: string, last_activity: string, created_at: string}>
     */
    public function list(int $tenantId, bool $withConverted = false): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $rows = $this->users->allForTenant($tenantId);
        $invitesByUser = $this->latestInvites($tenantId);
        $emails = ($this->emails ??= new RosterFicheEmailRepository())->mapForTenant($tenantId);
        $converted = $withConverted ? $this->events->accountCreatedMap($tenantId) : [];
        $candidates = [];
        foreach ($rows as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $st = self::classify($u, $invitesByUser[$id]['status'] ?? null);
            if ($st === null && isset($converted[$id]) && !in_array(strtolower(trim((string) ($u['status'] ?? ''))), ['merged', 'deleted', 'left', 'banned'], true)) {
                $st = self::STATUS_ACCOUNT;
            }
            if ($st !== null) {
                $candidates[$id] = [$u, $st];
            }
        }
        $summary = $this->events->summaryForUsers($tenantId, array_keys($candidates));
        $out = [];
        foreach ($candidates as $id => [$u, $st]) {
            $name = trim((string) ($u['display_name'] ?? ''));
            if ($name === '') {
                $name = trim(trim((string) ($u['first_name'] ?? '')) . ' ' . trim((string) ($u['last_name'] ?? '')));
            }
            $source = (string) ($summary[$id]['source'] ?? '');
            $last = max(
                (string) ($summary[$id]['last_at'] ?? ''),
                (string) ($u['updated_at'] ?? ''),
                (string) ($u['last_login_at'] ?? ''),
                (string) ($u['created_at'] ?? '')
            );
            $inv = $invitesByUser[$id] ?? null;
            $realEmail = RosterOnlyMember::isRosterEmail((string) ($u['email'] ?? '')) ? '' : trim((string) ($u['email'] ?? ''));
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : ('Fiche #' . $id),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'email' => $st === self::STATUS_ACCOUNT || $st === self::STATUS_PENDING ? $realEmail : (string) ($emails[$id] ?? ''),
                'invite_id' => $inv !== null ? (int) $inv['id'] : 0,
                'invite_at' => $inv !== null ? (string) $inv['sent_at'] : '',
                'invite_expired' => $inv !== null && $inv['status'] === MemberProfileInviteService::STATUS_EXPIRED && $st === self::STATUS_FICHE,
                'status' => $st,
                'status_label' => self::STATUSES[$st],
                'source' => $source !== '' ? $source : 'legacy',
                'source_label' => self::sourceLabel($source),
                'last_activity' => $last,
                'created_at' => (string) ($u['created_at'] ?? ''),
                'grade_id' => (int) ($u['grade_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Filtre et trie. Calcul pur.
     *
     * @param list<array<string, mixed>> $rows
     * @param array{q?: string, status?: string, source?: string, sort?: string} $f
     * @return list<array<string, mixed>>
     */
    public static function filter(array $rows, array $f): array
    {
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')), 'UTF-8');
        $status = (string) ($f['status'] ?? '');
        $source = (string) ($f['source'] ?? '');
        $rows = array_values(array_filter($rows, static function (array $r) use ($q, $status, $source): bool {
            if ($status !== '' && $r['status'] !== $status) {
                return false;
            }
            if ($status === '' && $r['status'] === self::STATUS_ACCOUNT) {
                return false;
            }
            if ($source !== '' && $r['source'] !== $source) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($r['name'] . ' ' . $r['callsign'] . ' ' . ($r['email'] ?? ''), 'UTF-8'), $q);
        }));
        $sort = (string) ($f['sort'] ?? 'activite');
        usort($rows, static function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'nom' => strcasecmp((string) $a['name'], (string) $b['name']),
                'statut' => [$a['status'], $a['name']] <=> [$b['status'], $b['name']],
                default => [$b['last_activity'], $a['name']] <=> [$a['last_activity'], $b['name']],
            };
        });

        return $rows;
    }

    /** @return array<int, array{id: int, status: string, sent_at: string}> dernier lien de chaque personne */
    private function latestInvites(int $tenantId): array
    {
        try {
            $svc = new MemberProfileInviteService();
            if (!$svc->available()) {
                return [];
            }
            $repo = $this->invites ?? new MemberProfileInviteRepository();
            $best = [];
            foreach ($repo->listForTenant($tenantId, 500) as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid > 0 && (!isset($best[$uid]) || (int) $row['id'] > (int) $best[$uid]['id'])) {
                    $best[$uid] = $row;
                }
            }

            return array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'status' => $svc->statusOf($r),
                'sent_at' => (string) (($r['last_sent_at'] ?? '') !== '' ? $r['last_sent_at'] : ($r['created_at'] ?? '')),
            ], $best);
        } catch (\Throwable) {
            return [];
        }
    }
}
