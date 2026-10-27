<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Indicateurs de l'administration des paiements, calculés à partir du registre commun
 * (PaymentLedgerRepository::ledger) et des formules en cours. Logique pure : testable sans base.
 */
final class PaymentAnalyticsService
{
    public const DONATION_SOURCES = ['heart', 'stripe_donation', 'donation'];

    /**
     * @param list<array<string, mixed>> $ledger
     * @param list<array<string, mixed>> $subscriptions PlanPaymentService::activeManualSubscriptions()
     * @param array<string, array{monthly: ?int, yearly: ?int}> $prices prix par formule (centimes)
     * @return array<string, mixed>
     */
    public static function kpis(array $ledger, array $subscriptions, array $prices, ?int $now = null): array
    {
        $now ??= time();
        $monthStart = strtotime(gmdate('Y-m-01 00:00:00', $now) . ' UTC');
        $prevStart = strtotime('-1 month', $monthStart);
        $yearAgo = $now - 365 * 86400;
        $q = $now - 90 * 86400;

        $month = $prev = $year = $donations12 = $refunds90 = 0;
        $toVerify = 0;
        $oldestDeclared = null;
        $unpaid = 0;
        $created90 = $activated90 = 0;
        $delays = [];
        $donors = [];
        foreach ($ledger as $e) {
            $amount = (int) ($e['amount_cents'] ?? 0);
            $status = (string) ($e['status'] ?? '');
            $paidTs = self::ts($e['paid_at'] ?? null);
            $createdTs = self::ts($e['created_at'] ?? null);
            if ($status === 'activated' && $paidTs !== null) {
                if ($paidTs >= $monthStart) {
                    $month += $amount;
                } elseif ($paidTs >= $prevStart) {
                    $prev += $amount;
                }
                if ($paidTs >= $yearAgo) {
                    $year += $amount;
                    if (in_array($e['source'] ?? '', self::DONATION_SOURCES, true)) {
                        $donations12 += $amount;
                        $donors[strtolower((string) ($e['payer_email'] ?? '')) ?: 'u' . (int) ($e['user_id'] ?? 0)] = true;
                    }
                }
            }
            if ($status === 'refunded') {
                $rTs = self::ts($e['refunded_at'] ?? null) ?? self::ts($e['decided_at'] ?? null);
                if ($rTs !== null && $rTs >= $q) {
                    $refunds90 += $amount;
                }
            }
            if ($status === 'declared') {
                $toVerify++;
                $d = self::ts($e['declared_at'] ?? null);
                if ($d !== null && ($oldestDeclared === null || $d < $oldestDeclared)) {
                    $oldestDeclared = $d;
                }
            }
            if ($status === 'awaiting' && in_array($e['source'] ?? '', ['plan', 'heart'], true)) {
                $unpaid++;
            }
            if (in_array($e['source'] ?? '', ['plan', 'heart'], true) && $createdTs !== null && $createdTs >= $q) {
                $created90++;
                if (in_array($status, ['activated', 'refunded'], true)) {
                    $activated90++;
                }
            }
            $dTs = self::ts($e['declared_at'] ?? null);
            $decTs = self::ts($e['decided_at'] ?? null);
            if ($dTs !== null && $decTs !== null && $decTs >= $dTs && in_array($status, ['activated', 'rejected', 'refunded'], true)) {
                $delays[] = ($decTs - $dTs) / 3600;
            }
        }
        sort($delays);
        $mrr = 0;
        $soon = 0;
        foreach ($subscriptions as $s) {
            $p = $prices[(string) ($s['plan_slug'] ?? '')] ?? ['monthly' => null, 'yearly' => null];
            if (($s['interval'] ?? 'monthly') === 'yearly') {
                $mrr += $p['yearly'] !== null ? (int) round($p['yearly'] / 12) : (int) ($p['monthly'] ?? 0);
            } else {
                $mrr += (int) ($p['monthly'] ?? 0);
            }
            if ((int) ($s['days_left'] ?? 99) <= 14) {
                $soon++;
            }
        }

        return [
            'month_cents' => $month,
            'prev_month_cents' => $prev,
            'month_delta_pct' => $prev > 0 ? (int) round(100 * ($month - $prev) / $prev) : null,
            'year_cents' => $year,
            'mrr_cents' => $mrr,
            'arr_cents' => $mrr * 12,
            'active_subscriptions' => count($subscriptions),
            'renewals_soon' => $soon,
            'to_verify' => $toVerify,
            'oldest_declared_hours' => $oldestDeclared !== null ? (int) floor(($now - $oldestDeclared) / 3600) : null,
            'unpaid' => $unpaid,
            'conversion_pct' => $created90 > 0 ? (int) round(100 * $activated90 / $created90) : null,
            'verify_median_hours' => $delays !== [] ? round($delays[intdiv(count($delays), 2)], 1) : null,
            'refunds_90_cents' => $refunds90,
            'donations_12_cents' => $donations12,
            'donors_12' => count($donors),
        ];
    }

    /**
     * Encaissements par mois (12 derniers mois) et par nature ; remboursements en négatif.
     *
     * @param list<array<string, mixed>> $ledger
     * @return list<array{month: string, label: string, plan: int, heart: int, donation: int, refund: int, total: int}>
     */
    public static function monthly(array $ledger, int $months = 12, ?int $now = null): array
    {
        $now ??= time();
        $out = [];
        $names = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $first = strtotime(gmdate('Y-m-01', $now) . ' UTC');
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime('-' . $i . ' month', $first);
            $key = gmdate('Y-m', $ts);
            $out[$key] = ['month' => $key, 'label' => $names[(int) gmdate('n', $ts) - 1] . ' ' . gmdate('y', $ts), 'plan' => 0, 'heart' => 0, 'donation' => 0, 'refund' => 0, 'total' => 0];
        }
        foreach ($ledger as $e) {
            $amount = (int) ($e['amount_cents'] ?? 0);
            $status = (string) ($e['status'] ?? '');
            if (in_array($status, ['activated', 'refunded'], true) && ($p = self::ts($e['paid_at'] ?? null)) !== null) {
                $k = gmdate('Y-m', $p);
                if (isset($out[$k])) {
                    $bucket = match ((string) ($e['source'] ?? '')) {
                        'plan' => 'plan',
                        'heart' => 'heart',
                        default => 'donation',
                    };
                    $out[$k][$bucket] += $amount;
                }
            }
            if ($status === 'refunded' && ($r = self::ts($e['refunded_at'] ?? null) ?? self::ts($e['decided_at'] ?? null)) !== null) {
                $k = gmdate('Y-m', $r);
                if (isset($out[$k])) {
                    $out[$k]['refund'] -= $amount;
                }
            }
        }
        foreach ($out as &$m) {
            $m['total'] = $m['plan'] + $m['heart'] + $m['donation'] + $m['refund'];
        }
        unset($m);

        return array_values($out);
    }

    /**
     * Communautés dont la formule payée s'est terminée récemment sans renouvellement (perdues).
     *
     * @param list<array<string, mixed>> $ledger
     * @param list<int> $activeTenantIds
     * @return list<array{tenant_id: int, tenant_name: string, plan_slug: string, ended_at: string, reference: string, days_ago: int}>
     */
    public static function lapsed(array $ledger, array $activeTenantIds, int $days = 60, ?int $now = null): array
    {
        $now ??= time();
        $last = [];
        foreach ($ledger as $e) {
            if (($e['source'] ?? '') !== 'plan' || ($e['status'] ?? '') !== 'activated' || (int) ($e['tenant_id'] ?? 0) < 1) {
                continue;
            }
            $end = self::ts($e['period_ends_at'] ?? null);
            if ($end === null) {
                continue;
            }
            $tid = (int) $e['tenant_id'];
            if (!isset($last[$tid]) || $end > $last[$tid]['_end']) {
                $last[$tid] = ['_end' => $end, 'e' => $e];
            }
        }
        $out = [];
        foreach ($last as $tid => $x) {
            if (in_array($tid, $activeTenantIds, true) || $x['_end'] > $now || $x['_end'] < $now - $days * 86400) {
                continue;
            }
            $out[] = [
                'tenant_id' => $tid,
                'tenant_name' => (string) ($x['e']['tenant_name'] ?? ''),
                'plan_slug' => (string) ($x['e']['plan_slug'] ?? ''),
                'interval' => (string) ($x['e']['interval'] ?? 'monthly'),
                'ended_at' => gmdate('Y-m-d H:i:s', $x['_end']),
                'reference' => (string) ($x['e']['reference'] ?? ''),
                'payer_email' => (string) ($x['e']['payer_email'] ?? ''),
                'days_ago' => (int) floor(($now - $x['_end']) / 86400),
            ];
        }
        usort($out, static fn (array $a, array $b): int => $a['days_ago'] <=> $b['days_ago']);

        return $out;
    }

    /**
     * Historique de paiements d'une communauté (nombre de périodes payées, total, ancienneté).
     *
     * @param list<array<string, mixed>> $ledger
     * @return array<int, array{periods: int, total_cents: int, first_at: ?string}>
     */
    public static function tenantHistory(array $ledger): array
    {
        $out = [];
        foreach ($ledger as $e) {
            $tid = (int) ($e['tenant_id'] ?? 0);
            if ($tid < 1 || ($e['status'] ?? '') !== 'activated' || ($e['source'] ?? '') !== 'plan') {
                continue;
            }
            $out[$tid] ??= ['periods' => 0, 'total_cents' => 0, 'first_at' => null];
            $out[$tid]['periods']++;
            $out[$tid]['total_cents'] += (int) ($e['amount_cents'] ?? 0);
            $at = (string) ($e['paid_at'] ?? '');
            if ($at !== '' && ($out[$tid]['first_at'] === null || $at < $out[$tid]['first_at'])) {
                $out[$tid]['first_at'] = $at;
            }
        }

        return $out;
    }

    /**
     * Filtre du registre : nature, statut, recherche, période.
     *
     * @param list<array<string, mixed>> $ledger
     * @return list<array<string, mixed>>
     */
    public static function filter(array $ledger, string $source = '', string $status = '', string $q = '', int $days = 0, ?int $now = null): array
    {
        $now ??= time();
        $q = mb_strtolower(trim($q));

        return array_values(array_filter($ledger, static function (array $e) use ($source, $status, $q, $days, $now): bool {
            if ($source === 'donations' ? !in_array($e['source'] ?? '', self::DONATION_SOURCES, true) : ($source !== '' && ($e['source'] ?? '') !== $source)) {
                return false;
            }
            if ($status !== '' && ($e['status'] ?? '') !== $status) {
                return false;
            }
            if ($days > 0 && (self::ts($e['created_at'] ?? null) ?? 0) < $now - $days * 86400) {
                return false;
            }
            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [$e['reference'] ?? '', $e['tenant_name'] ?? '', $e['payer_name'] ?? '', $e['payer_email'] ?? '', $e['transaction_ref'] ?? '', $e['plan_slug'] ?? '']));
                if (!str_contains($hay, $q)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** @param list<array<string, mixed>> $ledger */
    public static function csv(array $ledger): string
    {
        $rows = [['Référence', 'Nature', 'Statut', 'Montant (€)', 'Communauté', 'Payeur', 'E-mail', 'Formule', 'Période', 'Canal', 'Transaction', 'Créé le', 'Payé le', 'Fin de période', 'Remboursé le', 'Note']];
        $src = ['plan' => 'Formule', 'heart' => 'Soutien du cœur', 'stripe_donation' => 'Don ATAK (Stripe)', 'donation' => 'Don'];
        $st = ['awaiting' => 'À payer', 'declared' => 'À vérifier', 'activated' => 'Encaissé', 'rejected' => 'Refusé', 'cancelled' => 'Annulé', 'refunded' => 'Remboursé'];
        foreach ($ledger as $e) {
            $rows[] = [
                (string) ($e['reference'] ?? ''), $src[$e['source'] ?? ''] ?? (string) ($e['source'] ?? ''), $st[$e['status'] ?? ''] ?? (string) ($e['status'] ?? ''),
                number_format(((int) ($e['amount_cents'] ?? 0)) / 100, 2, ',', ''), (string) ($e['tenant_name'] ?? ''), (string) ($e['payer_name'] ?? ''),
                (string) ($e['payer_email'] ?? ''), (string) ($e['plan_slug'] ?? ''), ['monthly' => 'Mensuel', 'yearly' => 'Annuel'][$e['interval'] ?? ''] ?? '',
                (string) ($e['channel'] ?? ''), (string) ($e['transaction_ref'] ?? ''), self::d($e['created_at'] ?? null), self::d($e['paid_at'] ?? null),
                self::d($e['period_ends_at'] ?? null), self::d($e['refunded_at'] ?? null), (string) ($e['admin_note'] ?? ''),
            ];
        }
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(';', array_map(static function (string $c): string {
                if ($c !== '' && str_contains('=+-@', $c[0]) && !is_numeric(str_replace(',', '.', $c))) {
                    $c = "'" . $c;
                }

                return '"' . str_replace('"', '""', $c) . '"';
            }, $row)) . "\r\n";
        }

        return $out;
    }

    private static function d(mixed $raw): string
    {
        $t = self::ts($raw);

        return $t !== null ? gmdate('d/m/Y H:i', $t) : '';
    }

    private static function ts(mixed $raw): ?int
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $t = strtotime($raw . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', $raw) ? '' : ' UTC'));

        return $t === false ? null : $t;
    }
}
