<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

/**
 * Registre de suivi des paiements et des dons (Administration > Paiements) :
 * journal d'événements, dons saisis à la main, et lectures agrégées de toutes les sources
 * (demandes PayPal des formules et du soutien du cœur, dons Stripe ATAK, dons saisis).
 */
final class PaymentLedgerRepository
{
    private static bool $migrated = false;

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        if (!self::$migrated) {
            self::$migrated = true;
            SilentSchemaMigration::run(base_path('bootstrap/payments_ledger_migration.php'), $this->pdo);
        }

        return $this->pdo ??= Database::getPdo();
    }

    public function hasTable(string $table): bool
    {
        if (!array_key_exists($table, $this->tables)) {
            try {
                $this->pdo()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
                $this->tables[$table] = true;
            } catch (\Throwable) {
                $this->tables[$table] = false;
            }
        }

        return $this->tables[$table];
    }

    // ------------------------------------------------------------------ journal

    /** @param array<string, mixed> $meta */
    public function log(string $kind, array $ids = [], ?int $actorId = null, string $note = '', array $meta = []): void
    {
        if (!$this->hasTable('payment_events')) {
            return;
        }
        $atak = self::posInt($ids['atak_donation_id'] ?? null);
        try {
            $this->pdo()->prepare(
                'INSERT INTO payment_events (request_id, donation_id, ' . ($atak !== null ? 'atak_donation_id, ' : '') . 'tenant_id, user_id, kind, actor_user_id, note, meta, created_at)
                 VALUES (?, ?, ' . ($atak !== null ? '?, ' : '') . '?, ?, ?, ?, ?, ?, ?)'
            )->execute(array_merge([
                self::posInt($ids['request_id'] ?? null),
                self::posInt($ids['donation_id'] ?? null),
            ], $atak !== null ? [$atak] : [], [
                self::posInt($ids['tenant_id'] ?? null),
                self::posInt($ids['user_id'] ?? null),
                mb_substr($kind, 0, 32),
                self::posInt($actorId),
                $note !== '' ? mb_substr($note, 0, 500) : null,
                $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                gmdate('Y-m-d H:i:s'),
            ]));
        } catch (\Throwable) {
            // Le journal ne doit jamais bloquer une action de paiement.
        }
    }

    /**
     * Événements des demandes et dons donnés, groupés par clé « r<id> » / « d<id> » / « s<id> » (don Stripe).
     *
     * @param list<int> $requestIds
     * @param list<int> $donationIds
     * @param list<int> $atakDonationIds
     * @return array<string, list<array<string, mixed>>>
     */
    public function eventsFor(array $requestIds, array $donationIds = [], array $atakDonationIds = []): array
    {
        $requestIds = array_values(array_filter(array_map('intval', $requestIds), static fn (int $i): bool => $i > 0));
        $donationIds = array_values(array_filter(array_map('intval', $donationIds), static fn (int $i): bool => $i > 0));
        $atakDonationIds = array_values(array_filter(array_map('intval', $atakDonationIds), static fn (int $i): bool => $i > 0));
        if (!$this->hasTable('payment_events') || !$this->hasColumn('payment_events', 'atak_donation_id')) {
            $atakDonationIds = [];
        }
        if (!$this->hasTable('payment_events') || ($requestIds === [] && $donationIds === [] && $atakDonationIds === [])) {
            return [];
        }
        $where = [];
        $args = [];
        if ($requestIds !== []) {
            $where[] = 'e.request_id IN (' . implode(',', array_fill(0, count($requestIds), '?')) . ')';
            $args = array_merge($args, $requestIds);
        }
        if ($donationIds !== []) {
            $where[] = 'e.donation_id IN (' . implode(',', array_fill(0, count($donationIds), '?')) . ')';
            $args = array_merge($args, $donationIds);
        }
        if ($atakDonationIds !== []) {
            $where[] = 'e.atak_donation_id IN (' . implode(',', array_fill(0, count($atakDonationIds), '?')) . ')';
            $args = array_merge($args, $atakDonationIds);
        }
        $st = $this->pdo()->prepare(
            'SELECT e.*, u.display_name AS actor_name FROM payment_events e
             LEFT JOIN users u ON u.id = e.actor_user_id
             WHERE ' . implode(' OR ', $where) . ' ORDER BY e.created_at ASC, e.id ASC'
        );
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = !empty($row['request_id']) ? 'r' . (int) $row['request_id']
                : (!empty($row['atak_donation_id']) ? 's' . (int) $row['atak_donation_id'] : 'd' . (int) $row['donation_id']);
            $out[$key][] = $row;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function recentEvents(int $limit = 40): array
    {
        if (!$this->hasTable('payment_events')) {
            return [];
        }
        $st = $this->pdo()->query(
            'SELECT e.*, u.display_name AS actor_name, t.name AS tenant_name FROM payment_events e
             LEFT JOIN users u ON u.id = e.actor_user_id
             LEFT JOIN tenants t ON t.id = e.tenant_id
             ORDER BY e.created_at DESC, e.id DESC LIMIT ' . max(1, min(200, $limit))
        );

        return $st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    // ------------------------------------------------------------------ dons saisis

    /** @param array<string, mixed> $data */
    public function createDonation(array $data): int
    {
        $this->pdo()->prepare(
            'INSERT INTO platform_donations
                (user_id, tenant_id, donor_name, donor_email, amount_cents, currency, channel, transaction_ref, note, is_public, received_at, recorded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            self::posInt($data['user_id'] ?? null),
            self::posInt($data['tenant_id'] ?? null),
            ($data['donor_name'] ?? '') !== '' ? mb_substr((string) $data['donor_name'], 0, 120) : null,
            ($data['donor_email'] ?? '') !== '' ? mb_substr(strtolower((string) $data['donor_email']), 0, 190) : null,
            max(1, (int) ($data['amount_cents'] ?? 0)),
            'EUR',
            (string) ($data['channel'] ?? 'paypal'),
            ($data['transaction_ref'] ?? '') !== '' ? mb_substr((string) $data['transaction_ref'], 0, 80) : null,
            ($data['note'] ?? '') !== '' ? mb_substr((string) $data['note'], 0, 500) : null,
            !empty($data['is_public']) ? 1 : 0,
            (string) ($data['received_at'] ?? gmdate('Y-m-d H:i:s')),
            self::posInt($data['recorded_by'] ?? null),
            gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findDonation(int $id): ?array
    {
        if (!$this->hasTable('platform_donations')) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM platform_donations WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function refundDonation(int $id): bool
    {
        $st = $this->pdo()->prepare('UPDATE platform_donations SET refunded_at = ? WHERE id = ? AND refunded_at IS NULL');
        $st->execute([gmdate('Y-m-d H:i:s'), $id]);

        return $st->rowCount() === 1;
    }

    // ------------------------------------------------------------------ dons Stripe ATAK

    /** Passages autorisés : statut cible => statuts de départ. */
    public const STRIPE_DONATION_TRANSITIONS = [
        'cancelled' => ['pending'],
        'rejected' => ['pending'],
        'paid' => ['pending'],
        'refunded' => ['paid'],
    ];

    /** @return array<string, mixed>|null */
    public function findStripeDonation(int $id): ?array
    {
        if (!$this->hasTable('atak_donations')) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_donations WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Décision de l'administration sur un don Stripe, seulement depuis un statut autorisé
     * (une ligne déjà traitée n'est jamais réécrite).
     */
    public function decideStripeDonation(int $id, string $to, ?int $adminId, string $note): bool
    {
        $from = self::STRIPE_DONATION_TRANSITIONS[$to] ?? null;
        if ($from === null || !$this->hasTable('atak_donations')) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        $set = ['status = ?'];
        $args = [$to];
        $hasDecision = $this->hasColumn('atak_donations', 'decided_at');
        if ($hasDecision) {
            $set[] = 'decided_at = ?';
            $set[] = 'decided_by = ?';
            $set[] = 'admin_note = COALESCE(?, admin_note)';
            array_push($args, $now, $adminId, $note !== '' ? mb_substr($note, 0, 500) : null);
        }
        if ($to === 'paid') {
            $set[] = 'paid_at = COALESCE(paid_at, ?)';
            $args[] = $now;
        }
        if ($to === 'refunded' && $hasDecision) {
            $set[] = 'refunded_at = ?';
            $args[] = $now;
        }
        $args[] = $id;
        $st = $this->pdo()->prepare(
            'UPDATE atak_donations SET ' . implode(', ', $set) . ' WHERE id = ? AND status IN (' . implode(',', array_fill(0, count($from), '?')) . ')'
        );
        $st->execute(array_merge($args, $from));

        return $st->rowCount() === 1;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->tables)) {
            try {
                $this->pdo()->query('SELECT `' . $column . '` FROM `' . $table . '` LIMIT 0');
                $this->tables[$key] = true;
            } catch (\Throwable) {
                $this->tables[$key] = false;
            }
        }

        return $this->tables[$key];
    }

    // ------------------------------------------------------------------ lectures agrégées

    /**
     * Toutes les entrées d'argent, sous une forme commune.
     * source : plan | heart | stripe_donation | donation
     *
     * @return list<array<string, mixed>>
     */
    public function ledger(int $limit = 2000): array
    {
        $out = [];
        if ($this->hasTable('plan_payment_requests')) {
            $st = $this->pdo()->query(
                'SELECT r.*, t.name AS tenant_name, t.slug AS tenant_slug, u.display_name AS user_display_name, u.email AS user_email
                 FROM plan_payment_requests r
                 LEFT JOIN tenants t ON t.id = r.tenant_id
                 LEFT JOIN users u ON u.id = r.user_id
                 ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, $limit)
            );
            foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $r) {
                $out[] = [
                    'key' => 'r' . (int) $r['id'],
                    'id' => (int) $r['id'],
                    'source' => (string) $r['kind'] === 'heart' ? 'heart' : 'plan',
                    'reference' => (string) $r['reference'],
                    'status' => (string) $r['status'],
                    'amount_cents' => (int) $r['amount_cents'],
                    'tenant_id' => (int) ($r['tenant_id'] ?? 0),
                    'tenant_name' => (string) ($r['tenant_name'] ?? ''),
                    'tenant_slug' => (string) ($r['tenant_slug'] ?? ''),
                    'user_id' => (int) ($r['user_id'] ?? 0),
                    'payer_name' => (string) ($r['user_display_name'] ?? ''),
                    'payer_email' => (string) (($r['payer_email'] ?? '') ?: ($r['user_email'] ?? '')),
                    'plan_slug' => (string) ($r['plan_slug'] ?? ''),
                    'interval' => (string) ($r['billing_interval'] ?? ''),
                    'channel' => 'paypal',
                    'transaction_ref' => (string) ($r['transaction_ref'] ?? ''),
                    'payer_note' => (string) ($r['payer_note'] ?? ''),
                    'admin_note' => (string) ($r['admin_note'] ?? ''),
                    'created_at' => $r['created_at'] ?? null,
                    'declared_at' => $r['declared_at'] ?? null,
                    'decided_at' => $r['decided_at'] ?? null,
                    'paid_at' => in_array((string) $r['status'], ['activated', 'refunded'], true) ? ($r['decided_at'] ?? null) : null,
                    'refunded_at' => $r['refunded_at'] ?? null,
                    'period_ends_at' => $r['period_ends_at'] ?? null,
                    'payer_reminded_at' => $r['payer_reminded_at'] ?? null,
                    'payer_reminder_count' => (int) ($r['payer_reminder_count'] ?? 0),
                ];
            }
        }
        if ($this->hasTable('atak_donations')) {
            $st = $this->pdo()->query(
                'SELECT d.*, u.display_name, u.email, t.name AS tenant_name, t.slug AS tenant_slug
                 FROM atak_donations d
                 LEFT JOIN users u ON u.id = d.user_id
                 LEFT JOIN tenants t ON t.id = d.tenant_id
                 ORDER BY d.created_at DESC, d.id DESC LIMIT ' . max(1, $limit)
            );
            foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $d) {
                $paid = (string) $d['status'] === 'paid';
                $out[] = [
                    'key' => 's' . (int) $d['id'],
                    'id' => (int) $d['id'],
                    'source' => 'stripe_donation',
                    'reference' => 'DON-S' . str_pad((string) (int) $d['id'], 5, '0', STR_PAD_LEFT),
                    'status' => $paid ? 'activated' : ((string) $d['status'] === 'pending' ? 'awaiting' : (string) $d['status']),
                    'amount_cents' => (int) $d['amount_cents'],
                    'tenant_id' => (int) ($d['tenant_id'] ?? 0),
                    'tenant_name' => (string) ($d['tenant_name'] ?? ''),
                    'tenant_slug' => (string) ($d['tenant_slug'] ?? ''),
                    'user_id' => (int) ($d['user_id'] ?? 0),
                    'payer_name' => (string) ($d['display_name'] ?? ''),
                    'payer_email' => (string) ($d['email'] ?? ''),
                    'plan_slug' => '',
                    'interval' => '',
                    'channel' => 'stripe',
                    'transaction_ref' => (string) ($d['stripe_payment_intent_id'] ?? ''),
                    'payer_note' => '',
                    'admin_note' => (string) ($d['admin_note'] ?? ''),
                    'created_at' => $d['created_at'] ?? null,
                    'declared_at' => null,
                    'decided_at' => ($d['decided_at'] ?? null) ?: ($d['paid_at'] ?? null),
                    'paid_at' => $paid || (string) $d['status'] === 'refunded' ? ($d['paid_at'] ?? $d['created_at']) : null,
                    'refunded_at' => $d['refunded_at'] ?? null,
                    'period_ends_at' => null,
                    'badge_granted' => !empty($d['badge_granted']),
                ];
            }
        }
        if ($this->hasTable('platform_donations')) {
            $st = $this->pdo()->query(
                'SELECT d.*, u.display_name, u.email AS user_email, t.name AS tenant_name, t.slug AS tenant_slug, rb.display_name AS recorder_name
                 FROM platform_donations d
                 LEFT JOIN users u ON u.id = d.user_id
                 LEFT JOIN users rb ON rb.id = d.recorded_by
                 LEFT JOIN tenants t ON t.id = d.tenant_id
                 ORDER BY d.received_at DESC, d.id DESC LIMIT ' . max(1, $limit)
            );
            foreach ($st !== false ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $d) {
                $refunded = !empty($d['refunded_at']);
                $out[] = [
                    'key' => 'd' . (int) $d['id'],
                    'id' => (int) $d['id'],
                    'source' => 'donation',
                    'reference' => 'DON-' . str_pad((string) (int) $d['id'], 5, '0', STR_PAD_LEFT),
                    'status' => $refunded ? 'refunded' : 'activated',
                    'amount_cents' => (int) $d['amount_cents'],
                    'tenant_id' => (int) ($d['tenant_id'] ?? 0),
                    'tenant_name' => (string) ($d['tenant_name'] ?? ''),
                    'tenant_slug' => (string) ($d['tenant_slug'] ?? ''),
                    'user_id' => (int) ($d['user_id'] ?? 0),
                    'payer_name' => (string) (($d['display_name'] ?? '') ?: ($d['donor_name'] ?? '')),
                    'payer_email' => (string) (($d['donor_email'] ?? '') ?: ($d['user_email'] ?? '')),
                    'plan_slug' => '',
                    'interval' => '',
                    'channel' => (string) ($d['channel'] ?? 'paypal'),
                    'transaction_ref' => (string) ($d['transaction_ref'] ?? ''),
                    'payer_note' => '',
                    'admin_note' => (string) ($d['note'] ?? ''),
                    'created_at' => $d['received_at'] ?? null,
                    'declared_at' => null,
                    'decided_at' => $d['received_at'] ?? null,
                    'paid_at' => $d['received_at'] ?? null,
                    'refunded_at' => $d['refunded_at'] ?? null,
                    'period_ends_at' => null,
                    'is_public' => !empty($d['is_public']),
                    'recorder_name' => (string) ($d['recorder_name'] ?? ''),
                ];
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return $out;
    }

    /**
     * Comptes membres liés à une adresse (toutes communautés) : id de ligne users par communauté.
     *
     * @param list<string> $emails
     * @return array<string, list<array{id: int, tenant_id: int}>>
     */
    public function membershipsForEmails(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(static fn ($e): string => strtolower(trim((string) $e)), $emails))));
        if ($emails === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($emails, 200) as $chunk) {
            $st = $this->pdo()->prepare(
                'SELECT id, tenant_id, LOWER(TRIM(email)) AS em FROM users WHERE LOWER(TRIM(email)) IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
            );
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[(string) $row['em']][] = ['id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id']];
            }
        }

        return $out;
    }

    /**
     * Profils affichables des comptes (nom public, slug de fiche, communauté) — sans e-mail.
     *
     * @param list<int> $ids
     * @return array<int, array{name: string, slug: string, tenant_id: int, tenant_name: string, tenant_slug: string}>
     */
    public function profilesForUserIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        $out = [];
        foreach (array_chunk($ids, 300) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            try {
                $st = $this->pdo()->prepare('SELECT u.id, u.display_name, u.callsign, u.profile_slug, u.tenant_id, t.name AS tenant_name, t.slug AS tenant_slug FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.id IN (' . $in . ')');
                $st->execute($chunk);
            } catch (\Throwable) {
                $st = $this->pdo()->prepare('SELECT u.id, u.display_name, u.callsign, NULL AS profile_slug, u.tenant_id, t.name AS tenant_name, t.slug AS tenant_slug FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.id IN (' . $in . ')');
                $st->execute($chunk);
            }
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['id']] = [
                    'name' => trim((string) (($r['display_name'] ?? '') ?: ($r['callsign'] ?? ''))),
                    'slug' => trim((string) ($r['profile_slug'] ?? '')),
                    'tenant_id' => (int) ($r['tenant_id'] ?? 0),
                    'tenant_name' => (string) ($r['tenant_name'] ?? ''),
                    'tenant_slug' => (string) ($r['tenant_slug'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return array<int, string> slug public par communauté
     */
    public function tenantSlugs(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT id, slug FROM tenants WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            if ((string) ($r['slug'] ?? '') !== '') {
                $out[(int) $r['id']] = (string) $r['slug'];
            }
        }

        return $out;
    }

    /** @return array<int, string> e-mail par id de compte */
    public function emailsForUserIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0));
        if ($ids === []) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT id, LOWER(TRIM(email)) AS em FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['id']] = (string) $row['em'];
        }

        return $out;
    }

    /** Retire le badge d'un slug pour des comptes donnés. */
    public function revokeBadge(string $slug, array $userIds): int
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0));
        if ($userIds === [] || !$this->hasTable('user_badges')) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'DELETE ub FROM user_badges ub INNER JOIN badges b ON b.id = ub.badge_id
             WHERE ' . \App\Support\SqlText::equals($this->pdo(), 'b.slug') . ' AND ub.user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')'
        );
        $st->execute(array_merge([$slug], $userIds));

        return $st->rowCount();
    }

    /** Recherche de membres pour l'attribution manuelle (nom ou e-mail). */
    public function searchUsers(string $q, int $limit = 12): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($q)) . '%';
        $st = $this->pdo()->prepare(
            'SELECT u.id, u.display_name, u.email, t.name AS tenant_name FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE LOWER(u.display_name) LIKE ? OR LOWER(u.email) LIKE ? ORDER BY u.display_name ASC LIMIT ' . max(1, min(30, $limit))
        );
        $st->execute([$like, $like]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markPayerReminded(int $requestId): void
    {
        try {
            $this->pdo()->prepare(
                'UPDATE plan_payment_requests SET payer_reminded_at = ?, payer_reminder_count = payer_reminder_count + 1 WHERE id = ?'
            )->execute([gmdate('Y-m-d H:i:s'), $requestId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Demandes jamais payées, à relancer : créées depuis plus de $afterDays jours, au plus $max relances,
     * dernière relance il y a plus de $everyDays jours.
     *
     * @return list<array<string, mixed>>
     */
    public function unpaidToRemind(int $afterDays, int $everyDays, int $max, ?int $now = null): array
    {
        if (!$this->hasTable('plan_payment_requests')) {
            return [];
        }
        $now ??= time();
        try {
            $st = $this->pdo()->prepare(
                "SELECT * FROM plan_payment_requests WHERE status = 'awaiting' AND created_at <= ?
                 AND payer_reminder_count < ? AND (payer_reminded_at IS NULL OR payer_reminded_at <= ?)
                 AND payer_email IS NOT NULL AND payer_email <> '' ORDER BY created_at ASC LIMIT 100"
            );
            $st->execute([gmdate('Y-m-d H:i:s', $now - $afterDays * 86400), $max, gmdate('Y-m-d H:i:s', $now - $everyDays * 86400)]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Demandes jamais payées trop anciennes : annulées automatiquement. */
    public function expireUnpaid(int $days, ?int $now = null): array
    {
        if (!$this->hasTable('plan_payment_requests')) {
            return [];
        }
        $cutoff = gmdate('Y-m-d H:i:s', ($now ?? time()) - $days * 86400);
        $st = $this->pdo()->prepare("SELECT id, tenant_id, reference FROM plan_payment_requests WHERE status = 'awaiting' AND created_at <= ? LIMIT 200");
        $st->execute([$cutoff]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows !== []) {
            $ids = array_map(static fn ($r): int => (int) $r['id'], $rows);
            $this->pdo()->prepare(
                "UPDATE plan_payment_requests SET status = 'cancelled', admin_note = ?, decided_at = ? WHERE status = 'awaiting' AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')'
            )->execute(array_merge(['Annulée automatiquement : jamais payée après ' . $days . ' jours.', gmdate('Y-m-d H:i:s')], $ids));
        }

        return $rows;
    }

    private static function posInt(mixed $v): ?int
    {
        $i = (int) $v;

        return $i > 0 ? $i : null;
    }
}
