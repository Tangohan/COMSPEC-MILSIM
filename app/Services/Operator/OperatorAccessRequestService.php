<?php

declare(strict_types=1);

namespace App\Services\Operator;

use App\Core\Database;

/**
 * Demande d'accès à un outil opérateur grisé (/operateur).
 *
 * Rien de parallèle : la demande est une ligne de elevation_requests (type « droits », le membre demande pour lui-même,
 * droits demandés dans proposed_permission_ids). Elle se traite donc dans la page existante du back-office
 * « Demandes d'élévation » (/back-office/ressources/effectifs/elevations), où les droits demandés sont pré-cochés, et
 * le droit s'accorde par le mécanisme du site : une surcharge individuelle (user_permission_overrides, lue par
 * RbacService), posée par ElevationApprovalService.
 *
 * Toute requête est bornée à la communauté et au membre. Un droit se retrouve par son identifiant dans la table
 * permissions de la communauté : rien ne passe d'une communauté à l'autre.
 */
final class OperatorAccessRequestService
{
    /** Après un refus, le même droit se redemande au bout de ce délai. */
    public const RETRY_DAYS = 7;
    public const NOTE_MAX = 300;
    /** Droits qui permettent de décider d'une demande (ceux de la page « Demandes d'élévation »). */
    public const MANAGER_PERMISSIONS = ['admin.organization', 'admin.access', 'admin.roles.manage'];
    public const NOTIFY_REQUEST = 'access_request';
    public const NOTIFY_DECISION = 'access_request_decision';

    private Database $db;

    public function __construct(
        ?Database $db = null,
        /** @var (\Closure(int, int, string, array<string, mixed>): void)|null cloche du membre (member_notifications) */
        private ?\Closure $notify = null,
        /** @var (\Closure(int): list<int>)|null membres qui peuvent décider, dans la communauté */
        private ?\Closure $managers = null,
        /** @var (\Closure(string, int, int, int, array<string, mixed>): void)|null journal d'audit */
        private ?\Closure $audit = null,
        /** @var (\Closure(): int)|null heure courante (tests) */
        private ?\Closure $now = null,
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->notify ??= static function (int $tenantId, int $userId, string $type, array $payload): void {
            (new \App\Repositories\MemberNotificationRepository())->create($tenantId, $userId, $type, $payload);
        };
        $this->managers ??= static fn (int $tenantId): array => (new \App\Repositories\UserRepository())
            ->listActiveUserIdsWithAnyPermissionSlug($tenantId, self::MANAGER_PERMISSIONS);
        $this->audit ??= static function (string $action, int $tenantId, int $actorId, int $requestId, array $details): void {
            (new \App\Services\Audit\AuditService())->logChange($action, $tenantId, $actorId, 'elevation_request', $requestId, [], $details);
        };
        $this->now ??= static fn (): int => time();
    }

    /**
     * État de la demande du membre pour chaque droit : none (rien en cours, demandable), pending (envoyée), refused
     * (refusée : motif, date à partir de laquelle redemander), unavailable (droit inconnu de la communauté).
     *
     * @param list<string> $slugs
     * @return array<string, array{state: string, at: string, note: string, retry_at: string, can_retry: bool}>
     */
    public function states(int $tenantId, int $userId, array $slugs): array
    {
        $none = ['state' => 'none', 'at' => '', 'note' => '', 'retry_at' => '', 'can_retry' => true];
        $ids = $this->permissionIds($tenantId, $slugs);
        $out = [];
        foreach ($slugs as $slug) {
            $out[$slug] = isset($ids[$slug]) ? $none : ['state' => 'unavailable'] + $none;
        }
        if ($ids === []) {
            return $out;
        }
        $bySlug = array_flip($ids);
        $seen = [];
        // De la plus récente à la plus ancienne : la première demande qui cite le droit donne son état.
        foreach ($this->ownRequests($tenantId, $userId) as $row) {
            foreach ($row['permission_ids'] as $pid) {
                $slug = $bySlug[$pid] ?? null;
                if ($slug === null || isset($seen[$slug])) {
                    continue;
                }
                $seen[$slug] = true;
                if (in_array($row['status'], ['pending', 'in_review'], true)) {
                    $out[$slug] = ['state' => 'pending', 'at' => $row['created_at'], 'note' => '', 'retry_at' => '', 'can_retry' => false];
                } elseif ($row['status'] === 'rejected') {
                    $retry = (int) strtotime($row['resolved_at'] !== '' ? $row['resolved_at'] : $row['created_at']) + self::RETRY_DAYS * 86400;
                    $out[$slug] = [
                        'state' => 'refused',
                        'at' => $row['resolved_at'],
                        'note' => $row['resolution_note'],
                        'retry_at' => date('Y-m-d H:i:s', $retry),
                        'can_retry' => ($this->now)() >= $retry,
                    ];
                }
                // approved : le droit est accordé (la tuile n'est plus grisée) ; s'il a été retiré depuis, il se redemande.
            }
        }

        return $out;
    }

    /**
     * Crée une demande pour les droits donnés (déjà restreints par l'appelant aux droits des outils grisés du membre).
     * Écarte un droit inconnu de la communauté, déjà demandé (une seule demande en attente par droit et par membre),
     * ou refusé depuis moins de RETRY_DAYS jours.
     *
     * @param list<string> $slugs
     * @return array{ok: bool, error?: string, id?: int, slugs?: list<string>}
     */
    public function request(int $tenantId, int $userId, array $slugs, string $note, string $memberName = ''): array
    {
        $slugs = array_values(array_unique($slugs));
        if ($tenantId < 1 || $userId < 1 || $slugs === []) {
            return ['ok' => false, 'error' => 'Choisissez au moins un accès à demander.'];
        }
        $ids = $this->permissionIds($tenantId, $slugs);
        $states = $this->states($tenantId, $userId, $slugs);
        $keep = [];
        $why = '';
        foreach ($slugs as $slug) {
            $s = $states[$slug];
            if ($s['state'] === 'unavailable') {
                $why = $why ?: 'Cet accès ne se demande pas dans votre communauté. Voyez un responsable.';
            } elseif ($s['state'] === 'pending') {
                $why = 'Une demande est déjà en attente pour cet accès.';
            } elseif ($s['state'] === 'refused' && !$s['can_retry']) {
                $why = 'Cet accès vous a été refusé. Vous pourrez le redemander le ' . self::dateFr($s['retry_at']) . '.';
            } else {
                $keep[$slug] = $ids[$slug];
            }
        }
        if ($keep === []) {
            return ['ok' => false, 'error' => $why ?: 'Demande impossible.'];
        }
        $note = mb_substr(trim(preg_replace('/\s+/u', ' ', $note) ?? ''), 0, self::NOTE_MAX);
        $id = $this->db->insert(
            "INSERT INTO elevation_requests (tenant_id, target_user_id, requested_by, kind, note, proposed_permission_ids, status, created_at)
             VALUES (:t, :u, :by, 'droits', :note, :ids, 'pending', CURRENT_TIMESTAMP)",
            ['t' => $tenantId, 'u' => $userId, 'by' => $userId, 'note' => $note !== '' ? $note : null, 'ids' => json_encode(array_values($keep))]
        );
        $labels = self::labels(array_keys($keep));
        $this->safely(fn () => ($this->audit)('operator.access_request.created', $tenantId, $userId, $id, ['permissions' => array_keys($keep), 'note' => $note]));
        $this->safely(function () use ($tenantId, $userId, $id, $labels, $note, $memberName): void {
            foreach (($this->managers)($tenantId) as $managerId) {
                // Le demandeur ne se prévient pas lui-même (il ne peut pas non plus décider de sa propre demande).
                if ((int) $managerId !== $userId) {
                    ($this->notify)($tenantId, (int) $managerId, self::NOTIFY_REQUEST, [
                        'request_id' => $id, 'member' => $memberName, 'rights' => $labels, 'note' => $note,
                    ]);
                }
            }
        });

        return ['ok' => true, 'id' => $id, 'slugs' => array_keys($keep)];
    }

    /**
     * Le membre annule sa demande en attente pour un droit : le droit sort de la demande, qui disparaît si elle ne
     * demande plus rien. Ne touche qu'une demande faite par ce membre pour lui-même, dans cette communauté.
     */
    public function cancel(int $tenantId, int $userId, string $slug): bool
    {
        $pid = $this->permissionIds($tenantId, [$slug])[$slug] ?? 0;
        if ($pid < 1) {
            return false;
        }
        $done = false;
        foreach ($this->ownRequests($tenantId, $userId) as $row) {
            if (!in_array($row['status'], ['pending', 'in_review'], true) || !in_array($pid, $row['permission_ids'], true)) {
                continue;
            }
            $rest = array_values(array_diff($row['permission_ids'], [$pid]));
            $where = " WHERE id = :id AND tenant_id = :t AND target_user_id = :u AND requested_by = :by AND status IN ('pending', 'in_review')";
            $key = ['id' => $row['id'], 't' => $tenantId, 'u' => $userId, 'by' => $userId];
            if ($rest === []) {
                $this->db->execute('DELETE FROM elevation_requests' . $where, $key);
            } else {
                $this->db->execute('UPDATE elevation_requests SET proposed_permission_ids = :ids' . $where, $key + ['ids' => json_encode($rest)]);
            }
            $this->safely(fn () => ($this->audit)('operator.access_request.cancelled', $tenantId, $userId, $row['id'], ['permission' => $slug]));
            $done = true;
        }

        return $done;
    }

    /**
     * Après la décision d'un responsable (page « Demandes d'élévation ») : entrée au journal d'audit et cloche du
     * membre (et du demandeur, si quelqu'un d'autre avait demandé pour lui).
     *
     * @param array<string, mixed> $request ligne de elevation_requests avant la décision
     * @param list<int> $requestedIds droits demandés
     * @param list<int> $grantedIds droits accordés (vide pour un refus)
     */
    public function afterDecision(int $tenantId, array $request, string $status, array $requestedIds, array $grantedIds, string $note, int $actorId): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return;
        }
        $requestId = (int) ($request['id'] ?? 0);
        $grantedIds = $status === 'approved' ? array_values(array_unique(array_map('intval', $grantedIds))) : [];
        $refusedIds = array_values(array_diff(array_map('intval', $requestedIds), $grantedIds));
        $names = $this->permissionNames($tenantId, array_merge($grantedIds, $refusedIds));
        $pick = static fn (array $ids): array => array_values(array_filter(array_map(static fn (int $i): string => $names[$i] ?? '', $ids)));
        $outcome = $status === 'rejected' ? 'refused' : ($refusedIds !== [] && $requestedIds !== [] ? 'partial' : 'granted');
        $this->safely(fn () => ($this->audit)('operator.access_request.decided', $tenantId, $actorId, $requestId, [
            'outcome' => $outcome,
            'member_user_id' => (int) ($request['target_user_id'] ?? 0),
            'granted' => $pick($grantedIds),
            'refused' => $pick($refusedIds),
            'note' => $note,
        ]));
        $this->safely(function () use ($tenantId, $request, $requestId, $outcome, $pick, $grantedIds, $refusedIds, $note, $actorId): void {
            $recipients = array_unique([(int) ($request['target_user_id'] ?? 0), (int) ($request['requested_by'] ?? 0)]);
            foreach ($recipients as $uid) {
                if ($uid > 0 && $uid !== $actorId) {
                    ($this->notify)($tenantId, $uid, self::NOTIFY_DECISION, [
                        'request_id' => $requestId, 'outcome' => $outcome, 'granted' => $pick($grantedIds), 'refused' => $pick($refusedIds), 'note' => $note,
                    ]);
                }
            }
        });
    }

    /**
     * Personne ne s'accorde un droit à soi-même : vrai si l'acteur est le membre concerné et que la décision lui
     * donnerait un rôle ou un droit.
     *
     * @param array<string, mixed> $proposal role_id, permission_ids
     */
    public static function grantsToSelf(int $targetUserId, ?int $actorUserId, array $proposal): bool
    {
        $rights = (int) ($proposal['role_id'] ?? 0) > 0 || (is_array($proposal['permission_ids'] ?? null) && $proposal['permission_ids'] !== []);

        return $rights && $actorUserId !== null && $actorUserId > 0 && $actorUserId === $targetUserId;
    }

    /**
     * Texte de la cloche pour les deux notifications (ActivityHubPresentationService).
     *
     * @param array<string, mixed> $payload
     * @return array{title: string, detail: string, path: string}
     */
    public static function notificationText(string $type, array $payload, bool $fr = true): array
    {
        $list = static fn (mixed $v): string => implode(', ', array_map('strval', is_array($v) ? $v : []));
        $note = trim((string) ($payload['note'] ?? ''));
        if ($type === self::NOTIFY_REQUEST) {
            $member = trim((string) ($payload['member'] ?? '')) ?: ($fr ? 'Un membre' : 'A member');
            $rights = $list($payload['rights'] ?? []);

            return [
                'title' => $fr ? 'Demande d’accès à décider' : 'Access request to decide',
                'detail' => $member . ($fr ? ' demande : ' : ' asks for: ') . $rights . ($note !== '' ? ($fr ? '. Motif : ' : '. Reason: ') . $note : ''),
                'path' => 'back-office/ressources/effectifs/elevations',
            ];
        }
        $granted = $list($payload['granted'] ?? []);
        $refused = $list($payload['refused'] ?? []);
        $outcome = (string) ($payload['outcome'] ?? '');
        $title = match ($outcome) {
            'granted' => $fr ? 'Votre demande d’accès est acceptée' : 'Your access request is accepted',
            'partial' => $fr ? 'Votre demande d’accès est acceptée en partie' : 'Your access request is partly accepted',
            default => $fr ? 'Votre demande d’accès est refusée' : 'Your access request is refused',
        };
        $parts = [];
        if ($granted !== '') {
            $parts[] = ($fr ? 'Accordé : ' : 'Granted: ') . $granted;
        }
        if ($refused !== '') {
            $parts[] = ($fr ? 'Non accordé : ' : 'Not granted: ') . $refused;
        }
        if ($note !== '') {
            $parts[] = ($fr ? 'Motif : ' : 'Reason: ') . $note;
        }

        return ['title' => $title, 'detail' => implode('. ', $parts), 'path' => 'operateur'];
    }

    /**
     * @param list<string> $slugs
     * @return list<string> libellés en clair
     */
    public static function labels(array $slugs): array
    {
        return array_values(array_map(static fn (string $s): string => OperatorHubCatalog::PERMISSION_LABELS[$s] ?? $s, $slugs));
    }

    public static function dateFr(string $sql): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $sql, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] : '';
    }

    /**
     * Identifiants des droits dans la communauté.
     *
     * @param list<string> $slugs
     * @return array<string, int>
     */
    private function permissionIds(int $tenantId, array $slugs): array
    {
        $slugs = array_values(array_unique(array_filter($slugs, static fn (mixed $s): bool => is_string($s) && $s !== '')));
        if ($slugs === []) {
            return [];
        }
        $params = ['t' => $tenantId];
        $in = [];
        foreach ($slugs as $n => $slug) {
            $in[] = ':s' . $n;
            $params['s' . $n] = $slug;
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT id, slug FROM permissions WHERE tenant_id = :t AND slug IN (' . implode(', ', $in) . ')', $params) as $r) {
            $out[(string) $r['slug']] = (int) $r['id'];
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return array<int, string> libellé en clair du droit (à défaut son nom dans la communauté)
     */
    private function permissionNames(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT id, slug, name FROM permissions WHERE tenant_id = :t AND id IN (' . implode(', ', $ids) . ')', ['t' => $tenantId]) as $r) {
            $out[(int) $r['id']] = OperatorHubCatalog::PERMISSION_LABELS[(string) $r['slug']] ?? (string) $r['name'];
        }

        return $out;
    }

    /**
     * Demandes que le membre a faites pour lui-même, la plus récente d'abord.
     *
     * @return list<array{id: int, status: string, permission_ids: list<int>, created_at: string, resolved_at: string, resolution_note: string}>
     */
    private function ownRequests(int $tenantId, int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, status, proposed_permission_ids, created_at, resolved_at, resolution_note FROM elevation_requests
              WHERE tenant_id = :t AND target_user_id = :u AND requested_by = :by ORDER BY id DESC LIMIT 60',
            ['t' => $tenantId, 'u' => $userId, 'by' => $userId]
        );
        $out = [];
        foreach ($rows as $r) {
            $ids = json_decode((string) ($r['proposed_permission_ids'] ?? ''), true);
            $out[] = [
                'id' => (int) $r['id'],
                'status' => (string) $r['status'],
                'permission_ids' => is_array($ids) ? array_values(array_map('intval', $ids)) : [],
                'created_at' => (string) ($r['created_at'] ?? ''),
                'resolved_at' => (string) ($r['resolved_at'] ?? ''),
                'resolution_note' => trim((string) ($r['resolution_note'] ?? '')),
            ];
        }

        return $out;
    }

    /** Journal et cloche ne font jamais échouer la demande ni la décision. */
    private function safely(callable $do): void
    {
        try {
            $do();
        } catch (\Throwable $e) {
            error_log('Demande d’accès opérateur : ' . $e->getMessage());
        }
    }
}
