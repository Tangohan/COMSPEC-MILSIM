<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Repositories\BadgeRepository;
use App\Repositories\PaymentLedgerRepository;

/**
 * Donateurs du site et communautés qui soutiennent Athena.
 *
 * Un donateur est une personne (reconnue par son e-mail, donc sur toutes ses communautés) qui a fait au moins
 * un don payé : don Stripe ATAK, soutien du cœur validé, ou don saisi par l'administration (PayPal, virement…).
 * Son palier dépend du total donné. Il reçoit le badge « Donateur Athena » sur chacune de ses communautés,
 * et un liseré sur son nom (annuaire, effectifs, barre latérale).
 *
 * Une communauté est « soutien » si elle a une formule payée en cours, un soutien du cœur validé depuis moins
 * d'un an, ou des dons rattachés.
 *
 * Le résultat est gardé dans storage/cache/donors.json (recalculé après chaque don, attribution, retrait
 * et chaque jour par la tâche planifiée) pour que l'affichage ne coûte aucune requête.
 */
final class DonorService
{
    public const BADGE_SLUG = 'donateur';
    public const BADGE_NAME = 'Donateur Athena';

    /** Paliers : seuil en centimes (croissant). */
    public const TIERS = [
        'soutien' => ['label' => 'Soutien', 'min' => 1],
        'allie' => ['label' => 'Allié', 'min' => 2000],
        'pilier' => ['label' => 'Pilier', 'min' => 10000],
    ];

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function __construct(
        private ?PaymentLedgerRepository $ledger = null,
        private ?BadgeRepository $badges = null,
    ) {
    }

    public static function tierFor(int $totalCents): ?string
    {
        $tier = null;
        foreach (self::TIERS as $key => $t) {
            if ($totalCents >= $t['min']) {
                $tier = $key;
            }
        }

        return $tier;
    }

    public static function tierLabel(?string $tier): string
    {
        return $tier !== null ? (self::TIERS[$tier]['label'] ?? $tier) : '';
    }

    /**
     * Donateurs à partir des entrées du registre (pur : testable).
     * Une révocation manuelle (e-mail listé dans $revoked) retire la personne, une attribution manuelle
     * ($granted : e-mail => palier) l'ajoute même sans don enregistré.
     *
     * @param list<array<string, mixed>> $ledger
     * @param list<string> $revoked
     * @param array<string, string> $granted
     * @return array<string, array{email: string, name: string, total_cents: int, count: int, last_at: ?string, tier: string, manual: bool, user_ids: list<int>}>
     */
    public static function donorsFromLedger(array $ledger, array $revoked = [], array $granted = []): array
    {
        $out = [];
        foreach ($ledger as $e) {
            $isDonation = in_array($e['source'] ?? '', ['heart', 'stripe_donation', 'donation'], true);
            if (!$isDonation || ($e['status'] ?? '') !== 'activated') {
                continue;
            }
            $email = strtolower(trim((string) ($e['payer_email'] ?? '')));
            $key = $email !== '' ? $email : 'u' . (int) ($e['user_id'] ?? 0);
            if ($key === 'u0') {
                continue;
            }
            $out[$key] ??= ['email' => $email, 'name' => '', 'total_cents' => 0, 'count' => 0, 'last_at' => null, 'tier' => 'soutien', 'manual' => false, 'user_ids' => []];
            $out[$key]['total_cents'] += (int) ($e['amount_cents'] ?? 0);
            $out[$key]['count']++;
            if ($out[$key]['name'] === '' && trim((string) ($e['payer_name'] ?? '')) !== '') {
                $out[$key]['name'] = trim((string) $e['payer_name']);
            }
            $at = (string) ($e['paid_at'] ?? $e['created_at'] ?? '');
            if ($at !== '' && ($out[$key]['last_at'] === null || $at > $out[$key]['last_at'])) {
                $out[$key]['last_at'] = $at;
            }
            if ((int) ($e['user_id'] ?? 0) > 0) {
                $out[$key]['user_ids'][] = (int) $e['user_id'];
            }
        }
        foreach ($granted as $email => $tier) {
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }
            $out[$email] ??= ['email' => $email, 'name' => '', 'total_cents' => 0, 'count' => 0, 'last_at' => null, 'tier' => 'soutien', 'manual' => true, 'user_ids' => []];
            $out[$email]['manual'] = true;
            $out[$email]['forced_tier'] = isset(self::TIERS[$tier]) ? $tier : 'soutien';
        }
        foreach ($out as $key => &$d) {
            if (in_array($d['email'], $revoked, true)) {
                unset($out[$key]);
                continue;
            }
            $auto = self::tierFor($d['total_cents']) ?? 'soutien';
            $forced = $d['forced_tier'] ?? null;
            // Le palier attribué à la main ne fait jamais descendre un palier mérité par les dons.
            $d['tier'] = $forced !== null && self::TIERS[$forced]['min'] > self::TIERS[$auto]['min'] ? $forced : $auto;
            $d['user_ids'] = array_values(array_unique($d['user_ids']));
            unset($d['forced_tier']);
        }
        unset($d);
        uasort($out, static fn (array $a, array $b): int => [$b['total_cents'], $b['last_at'] ?? ''] <=> [$a['total_cents'], $a['last_at'] ?? '']);

        return $out;
    }

    /**
     * Communautés qui soutiennent Athena (pur) : formule payée en cours, cœur validé depuis moins d'un an, dons rattachés.
     *
     * @param list<array<string, mixed>> $ledger
     * @param list<int> $paidTenantIds communautés avec une formule payée active
     * @return array<int, array{kind: string, label: string, since: ?string}>
     */
    public static function supportersFromLedger(array $ledger, array $paidTenantIds, ?int $now = null): array
    {
        $now ??= time();
        $out = [];
        foreach ($paidTenantIds as $tid) {
            if ((int) $tid > 0) {
                $out[(int) $tid] = ['kind' => 'plan', 'label' => 'Formule soutien', 'since' => null];
            }
        }
        foreach ($ledger as $e) {
            $tid = (int) ($e['tenant_id'] ?? 0);
            if ($tid < 1 || ($e['status'] ?? '') !== 'activated' || isset($out[$tid])) {
                continue;
            }
            $at = (string) ($e['paid_at'] ?? $e['created_at'] ?? '');
            $src = (string) ($e['source'] ?? '');
            if ($src === 'heart' && $at !== '' && strtotime($at) >= $now - 365 * 86400) {
                $out[$tid] = ['kind' => 'heart', 'label' => 'Soutien du cœur', 'since' => $at];
            } elseif (in_array($src, ['donation', 'stripe_donation'], true)) {
                $out[$tid] = ['kind' => 'donation', 'label' => 'Communauté donatrice', 'since' => $at];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ état enregistré

    private static function cachePath(): string
    {
        return base_path('storage/cache/donors.json');
    }

    /** @return array{users: array<int, string>, tenants: array<int, string>, updated_at: string} */
    public static function cached(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $data = [];
        $path = self::cachePath();
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $data = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }

        return self::$cache = [
            'users' => array_map('strval', is_array($data['users'] ?? null) ? $data['users'] : []),
            'tenants' => array_map('strval', is_array($data['tenants'] ?? null) ? $data['tenants'] : []),
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }

    /** Palier du compte (id de ligne users, quelle que soit la communauté), ou null. */
    public static function userTier(int $userId): ?string
    {
        $t = self::cached()['users'][$userId] ?? null;

        return is_string($t) && $t !== '' ? $t : null;
    }

    /** Type de soutien d'une communauté (plan | heart | donation), ou null. */
    public static function tenantSupport(int $tenantId): ?string
    {
        $t = self::cached()['tenants'][$tenantId] ?? null;

        return is_string($t) && $t !== '' ? $t : null;
    }

    /** Réglages manuels : e-mails retirés, attributions sans don. */
    public function overrides(): array
    {
        try {
            $raw = json_decode((string) (new \App\Repositories\PlatformSettingsRepository())->get('donor_overrides', '{}'), true);
        } catch (\Throwable) {
            $raw = [];
        }

        return [
            'revoked' => array_values(array_map('strval', is_array($raw['revoked'] ?? null) ? $raw['revoked'] : [])),
            'granted' => array_map('strval', is_array($raw['granted'] ?? null) ? $raw['granted'] : []),
        ];
    }

    private function saveOverrides(array $o): void
    {
        (new \App\Repositories\PlatformSettingsRepository())->setMany(['donor_overrides' => json_encode([
            'revoked' => array_values(array_unique($o['revoked'])),
            'granted' => $o['granted'],
        ], JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Recalcule donateurs et communautés, aligne les badges, réécrit le cache.
     *
     * @param list<int>|null $paidTenantIds null : recalculées
     * @return array{donors: int, tenants: int, granted: int, revoked: int}
     */
    public function rebuild(?array $paidTenantIds = null): array
    {
        if ($paidTenantIds === null) {
            try {
                $paidTenantIds = \App\Core\Container::get(PlanPaymentService::class)->paidTenantIds();
            } catch (\Throwable) {
                $paidTenantIds = array_map('intval', array_keys(array_filter(self::cached()['tenants'], static fn ($k) => $k === 'plan')));
            }
        }
        $ledger = $this->ledger()->ledger();
        $o = $this->overrides();
        $donors = self::donorsFromLedger($ledger, $o['revoked'], $o['granted']);
        $emails = array_values(array_filter(array_map(static fn ($d) => $d['email'], $donors)));
        $memberships = $this->ledger()->membershipsForEmails($emails);

        $users = [];
        $granted = 0;
        foreach ($donors as $d) {
            $rows = $memberships[$d['email']] ?? [];
            foreach ($d['user_ids'] as $uid) {
                $rows[] = ['id' => $uid, 'tenant_id' => 0];
            }
            foreach ($rows as $m) {
                $users[(int) $m['id']] = $d['tier'];
                if ((int) $m['tenant_id'] > 0 && $this->grantBadge((int) $m['tenant_id'], (int) $m['id'])) {
                    $granted++;
                }
            }
        }
        // Badges des personnes qui ne sont plus donatrices (retrait manuel, remboursement).
        $revoked = 0;
        $previous = array_keys(self::cached()['users']);
        $gone = array_values(array_diff(array_map('intval', $previous), array_keys($users)));
        if ($gone !== []) {
            $revoked = $this->ledger()->revokeBadge(self::BADGE_SLUG, $gone);
        }

        $tenants = [];
        foreach (self::supportersFromLedger($ledger, $paidTenantIds) as $tid => $s) {
            $tenants[$tid] = $s['kind'];
        }
        $payload = ['users' => $users, 'tenants' => $tenants, 'updated_at' => gmdate('c')];
        $dir = dirname(self::cachePath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(self::cachePath(), json_encode($payload), LOCK_EX);
        self::$cache = null;

        return ['donors' => count($donors), 'tenants' => count($tenants), 'granted' => $granted, 'revoked' => $revoked];
    }

    /** Attribue le rôle à une personne sans don enregistré (ou relève son palier). */
    public function grantManually(string $email, string $tier): void
    {
        $email = strtolower(trim($email));
        $o = $this->overrides();
        $o['revoked'] = array_values(array_diff($o['revoked'], [$email]));
        $o['granted'][$email] = isset(self::TIERS[$tier]) ? $tier : 'soutien';
        $this->saveOverrides($o);
    }

    /** Retire le rôle (les dons restent enregistrés). */
    public function revoke(string $email): void
    {
        $email = strtolower(trim($email));
        $o = $this->overrides();
        unset($o['granted'][$email]);
        $o['revoked'][] = $email;
        $this->saveOverrides($o);
        $ids = array_map(static fn (array $m): int => (int) $m['id'], $this->ledger()->membershipsForEmails([$email])[$email] ?? []);
        $this->ledger()->revokeBadge(self::BADGE_SLUG, $ids);
    }

    /** Annule un retrait : la personne redevient donatrice si ses dons le justifient. */
    public function restore(string $email): void
    {
        $email = strtolower(trim($email));
        $o = $this->overrides();
        $o['revoked'] = array_values(array_diff($o['revoked'], [$email]));
        $this->saveOverrides($o);
    }

    private function grantBadge(int $tenantId, int $userId): bool
    {
        try {
            return $this->badges()->ensureAndGrant(
                $tenantId,
                $userId,
                self::BADGE_SLUG,
                self::BADGE_NAME,
                'A soutenu Athena par un don.',
                null,
                null
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function ledger(): PaymentLedgerRepository
    {
        return $this->ledger ??= new PaymentLedgerRepository();
    }

    private function badges(): BadgeRepository
    {
        return $this->badges ??= new BadgeRepository();
    }

    // ------------------------------------------------------------------ affichage

    /** Classe CSS à poser sur un nom ou une carte de membre. */
    public static function nameClass(int $userId): string
    {
        $t = self::userTier($userId);

        return $t !== null ? ' is-donor is-donor--' . $t : '';
    }

    /** Petite marque « donateur » (cœur + palier) à côté d'un nom. */
    public static function mark(int $userId, bool $withLabel = false): string
    {
        $t = self::userTier($userId);
        if ($t === null) {
            return '';
        }
        $label = 'Donateur Athena · ' . self::tierLabel($t);

        return '<span class="ath-donor-mark ath-donor-mark--' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z" fill="currentColor"/></svg>'
            . ($withLabel ? '<span>' . htmlspecialchars(self::tierLabel($t), ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="ath-donor-mark__sr">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>')
            . '</span>';
    }
}
