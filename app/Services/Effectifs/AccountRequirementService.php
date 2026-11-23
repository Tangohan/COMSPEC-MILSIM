<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\TenantRepository;
use App\Support\RosterOnlyMember;
use DateTimeImmutable;

/**
 * « Compte obligatoire » : réglage par communauté qui fixe une échéance aux personnes sans compte.
 *
 * Ce que cela veut dire, et ne veut pas dire : rien n'est supprimé ni bloqué automatiquement. Passée l'échéance, la
 * fiche est marquée « en retard » (visible dans Effectifs › Sans compte) et le responsable décide. Un réglage séparé,
 * désactivé par défaut, permet en plus de ne plus résoudre le Steam d'une fiche en retard (liaison jeu/ATAK) tant que
 * le compte n'est pas créé ; la fiche et ses données restent intactes.
 *
 * Réglage stocké dans tenants.settings['account_required'] : enabled, days (1 à 90), since (début de l'obligation),
 * restrict. L'échéance d'une fiche = le plus tardif de sa création et du début de l'obligation, plus le délai : activer
 * l'obligation ne met jamais personne en retard le jour même.
 */
final class AccountRequirementService
{
    public const KEY = 'account_required';
    public const DEFAULT_DAYS = 14;
    public const MIN_DAYS = 1;
    public const MAX_DAYS = 90;
    /** Délai minimal entre deux rappels à la même personne (jours). */
    public const REMIND_EVERY_DAYS = 3;
    /** Rappels traités par action. */
    public const BULK_LIMIT = 50;

    public const STATE_OFF = 'off';
    public const STATE_PENDING = 'pending';
    public const STATE_LATE = 'late';

    public function __construct(private ?TenantRepository $tenants = null)
    {
        $this->tenants ??= new TenantRepository();
    }

    /**
     * Réglage normalisé depuis le JSON des réglages de la communauté. Calcul pur.
     *
     * @param array<string, mixed> $settings
     * @return array{enabled: bool, days: int, since: string, restrict: bool}
     */
    public static function config(array $settings): array
    {
        $raw = is_array($settings[self::KEY] ?? null) ? $settings[self::KEY] : [];
        $enabled = !empty($raw['enabled']);
        $since = (string) ($raw['since'] ?? '');

        return [
            'enabled' => $enabled,
            'days' => self::clampDays($raw['days'] ?? self::DEFAULT_DAYS),
            'since' => $enabled && $since !== '' && strtotime($since) !== false ? $since : '',
            'restrict' => $enabled && !empty($raw['restrict']),
        ];
    }

    public static function clampDays(mixed $days): int
    {
        $n = (int) $days;

        return $n < self::MIN_DAYS ? self::DEFAULT_DAYS : min($n, self::MAX_DAYS);
    }

    /** @return array{enabled: bool, days: int, since: string, restrict: bool} */
    public function load(int $tenantId): array
    {
        try {
            return self::config($this->tenants->getSettings($tenantId));
        } catch (\Throwable) {
            return self::config([]);
        }
    }

    /**
     * Enregistre le réglage. Le début de l'obligation est conservé tant qu'elle reste activée ; désactivée puis
     * réactivée, il repart de maintenant.
     *
     * @param array{enabled: bool, days: int, since: string, restrict: bool} $previous
     * @return array{enabled: bool, days: int, since: string, restrict: bool}
     */
    public function save(int $tenantId, bool $enabled, int $days, bool $restrict, array $previous, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $since = $enabled ? ($previous['enabled'] && $previous['since'] !== '' ? $previous['since'] : $now->format('Y-m-d H:i:s')) : '';
        $value = ['enabled' => $enabled, 'days' => self::clampDays($days), 'since' => $since, 'restrict' => $enabled && $restrict];
        $this->tenants->mergeSettings($tenantId, [self::KEY => $value]);

        return $value;
    }

    /**
     * Échéance d'une personne, ou null si l'obligation est désactivée. Calcul pur.
     *
     * @param array{enabled: bool, days: int, since: string, restrict: bool} $cfg
     */
    public static function deadline(array $cfg, string $createdAt): ?DateTimeImmutable
    {
        if (!$cfg['enabled']) {
            return null;
        }
        $from = max((int) (strtotime($createdAt) ?: 0), (int) (strtotime($cfg['since']) ?: 0));
        if ($from < 1) {
            return null;
        }

        return (new DateTimeImmutable('@' . $from))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->modify('+' . $cfg['days'] . ' days');
    }

    /**
     * @param array{enabled: bool, days: int, since: string, restrict: bool} $cfg
     * @return array{state: string, deadline: string, days_left: int}
     */
    public static function state(array $cfg, string $createdAt, DateTimeImmutable $now): array
    {
        $deadline = self::deadline($cfg, $createdAt);
        if ($deadline === null) {
            return ['state' => self::STATE_OFF, 'deadline' => '', 'days_left' => 0];
        }
        $left = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 86400);

        return [
            'state' => $deadline <= $now ? self::STATE_LATE : self::STATE_PENDING,
            'deadline' => $deadline->format('Y-m-d H:i:s'),
            'days_left' => $left,
        ];
    }

    /**
     * Ajoute l'échéance, l'état et le dernier rappel à chaque ligne de NoAccountDirectory::list(). Calcul pur.
     *
     * @param list<array<string, mixed>> $rows
     * @param array{enabled: bool, days: int, since: string, restrict: bool} $cfg
     * @param array<int, array{last_reminded_at: string, reminder_count: int}> $reminders
     * @return list<array<string, mixed>>
     */
    public static function decorate(array $rows, array $cfg, array $reminders, DateTimeImmutable $now): array
    {
        foreach ($rows as $i => $r) {
            $s = self::state($cfg, (string) ($r['created_at'] ?? ''), $now);
            $rem = $reminders[(int) ($r['id'] ?? 0)] ?? null;
            $rows[$i]['due_state'] = $s['state'];
            $rows[$i]['due_at'] = $s['deadline'];
            $rows[$i]['due_label'] = self::label($s['state'], $s['deadline']);
            $rows[$i]['reminded_at'] = $rem['last_reminded_at'] ?? '';
            $rows[$i]['reminder_count'] = $rem['reminder_count'] ?? 0;
        }

        return $rows;
    }

    public static function label(string $state, string $deadline): string
    {
        $day = $deadline !== '' && strtotime($deadline) !== false ? date('d/m/Y', (int) strtotime($deadline)) : '';

        return match ($state) {
            self::STATE_PENDING => 'À créer avant le ' . $day,
            self::STATE_LATE => 'En retard depuis le ' . $day,
            default => '',
        };
    }

    /**
     * Personnes à relancer : en retard (ou toutes), pas relancées depuis moins de REMIND_EVERY_DAYS jours, au plus
     * BULK_LIMIT, les plus en retard d'abord. Calcul pur.
     *
     * @param list<array<string, mixed>> $rows lignes déjà passées par decorate()
     * @return list<array<string, mixed>>
     */
    public static function remindable(array $rows, bool $onlyLate, DateTimeImmutable $now): array
    {
        $cut = $now->modify('-' . self::REMIND_EVERY_DAYS . ' days')->getTimestamp();
        $out = array_values(array_filter($rows, static function (array $r) use ($onlyLate, $cut): bool {
            if ($onlyLate && ($r['due_state'] ?? '') !== self::STATE_LATE) {
                return false;
            }
            $at = (string) ($r['reminded_at'] ?? '');

            return $at === '' || (int) strtotime($at) <= $cut;
        }));
        usort($out, static fn (array $a, array $b): int => strcmp((string) (($a['due_at'] ?? '') ?: '9999'), (string) (($b['due_at'] ?? '') ?: '9999')));

        return array_slice($out, 0, self::BULK_LIMIT);
    }

    /**
     * Vrai si la fiche (adresse réservée) est en retard dans une communauté qui a choisi la restriction.
     * Les vrais comptes ne sont jamais concernés. Calcul pur.
     *
     * @param array<string, mixed> $user ligne users
     * @param array{enabled: bool, days: int, since: string, restrict: bool} $cfg
     */
    public static function isRestricted(array $cfg, array $user, DateTimeImmutable $now): bool
    {
        if (!$cfg['enabled'] || !$cfg['restrict'] || !RosterOnlyMember::isRosterEmail((string) ($user['email'] ?? ''))) {
            return false;
        }

        return self::state($cfg, (string) ($user['created_at'] ?? ''), $now)['state'] === self::STATE_LATE;
    }

    /**
     * Appelable depuis un dépôt : lit le réglage de la communauté de la fiche. Ne coûte une requête que pour une
     * fiche à adresse réservée ; toute erreur laisse l'accès comme avant.
     *
     * @param array<string, mixed> $user ligne users
     */
    public static function restrictsUser(array $user): bool
    {
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1 || !RosterOnlyMember::isRosterEmail((string) ($user['email'] ?? ''))) {
            return false;
        }
        try {
            return self::isRestricted((new self())->load($tenantId), $user, new DateTimeImmutable('now'));
        } catch (\Throwable) {
            return false;
        }
    }
}
