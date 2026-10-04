<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Téléphone en jeu d'un opérateur (roleplay ATAK) : un numéro, un IMEI et une adresse MAC
 * attribués à la première connexion du jeu puis conservés. Le format du numéro suit le tenant :
 * modèle US (grade_system_code US_*) ou modèle FR (par défaut).
 */
final class GamePhoneIdentityRepository
{
    public function __construct(private ?Database $db = null, private ?TenantRepository $tenants = null)
    {
        $this->db ??= Database::getInstance();
        $this->tenants ??= new TenantRepository();
        SilentSchemaMigration::run(base_path('bootstrap/game_phone_identity_migration.php'));
        SilentSchemaMigration::run(base_path('bootstrap/game_phone_terminal_migration.php'));
    }

    /** Formats de numéro proposés au back-office (code → libellé). */
    public const FORMATS = [
        'FR' => 'France · mobile 06 / 07',
        'US' => 'États-Unis · (NXX) NXX-XXXX',
        'UK' => 'Royaume-Uni · mobile 07',
        'DE' => 'Allemagne · mobile 015 / 016 / 017',
        'BE' => 'Belgique · mobile 04',
    ];

    /** États matériels remontés par le téléphone (fn_deviceHealth) et libellés français. */
    public const DEVICE_STATES = [
        'OK' => 'En service',
        'CRACKED' => 'Écran fêlé',
        'OFF' => 'Éteint',
        'BROKEN' => 'Hors d’usage',
        'ABSENT' => 'Pas d’appareil sur l’opérateur',
    ];

    /** FR ou US selon le référentiel de grades du tenant. */
    public function formatForTenant(int $tenantId): string
    {
        try {
            $settings = $this->tenants->getSettings($tenantId);
        } catch (\Throwable) {
            $settings = [];
        }
        $code = strtoupper(trim((string) ($settings['grade_system_code'] ?? '')));

        return str_starts_with($code, 'US') ? 'US' : 'FR';
    }

    /**
     * Identité du téléphone, créée si l'opérateur n'en a pas encore. Si le tenant change de modèle,
     * seul le numéro est réattribué dans le nouveau format ; IMEI et MAC suivent l'appareil.
     *
     * Un format choisi au back-office (format_locked) est conservé tel quel.
     * revision : horodatage de la dernière modification d'identité, repris dans la révision du profil jeu.
     *
     * @return array{number: string, imei: string, mac: string, format: string, revision: int}
     */
    public function forUser(int $tenantId, int $userId): array
    {
        $format = $this->formatForTenant($tenantId);
        $row = $this->findRow($tenantId, $userId);
        if ($row !== null && ((int) ($row['format_locked'] ?? 0) === 1 || (string) $row['phone_format'] === $format)) {
            return self::identityOf($row);
        }

        $number = $this->freeNumber($tenantId, $format);
        if ($row !== null) {
            $rev = time();
            $this->db->execute(
                'UPDATE game_phone_identities SET phone_number = :n, phone_format = :f, identity_revision = :r WHERE tenant_id = :t AND user_id = :u',
                ['n' => $number, 'f' => $format, 'r' => $rev, 't' => $tenantId, 'u' => $userId]
            );

            return ['number' => $number, 'imei' => (string) $row['imei'], 'mac' => (string) $row['mac'], 'format' => $format, 'revision' => $rev];
        }

        $imei = self::newImei();
        $mac = self::newMac();
        $this->db->execute(
            'INSERT IGNORE INTO game_phone_identities (tenant_id, user_id, phone_format, phone_number, imei, mac) VALUES (:t, :u, :f, :n, :i, :m)',
            ['t' => $tenantId, 'u' => $userId, 'f' => $format, 'n' => $number, 'i' => $imei, 'm' => $mac]
        );
        // Deux connexions simultanées : on relit la ligne gagnante.
        $saved = $this->findRow($tenantId, $userId);

        return $saved === null
            ? ['number' => $number, 'imei' => $imei, 'mac' => $mac, 'format' => $format, 'revision' => 0]
            : self::identityOf($saved);
    }

    /** @return array<string, mixed>|null */
    public function findRow(int $tenantId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM game_phone_identities WHERE tenant_id = :t AND user_id = :u LIMIT 1',
            ['t' => $tenantId, 'u' => $userId]
        );
    }

    /**
     * Terminaux (téléphones en jeu) de la communauté, avec l'opérateur et le dernier état remonté.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT g.*, u.display_name, u.callsign
             FROM game_phone_identities g
             LEFT JOIN users u ON u.id = g.user_id
             WHERE g.tenant_id = :t
             ORDER BY (g.device_seen_at IS NULL), g.device_seen_at DESC, u.display_name ASC, g.id ASC',
            ['t' => $tenantId]
        );
    }

    /**
     * Réglages saisis au back-office. Changer de format sans toucher au numéro en attribue un nouveau
     * dans ce format. Le format devient fixe (il ne suit plus le référentiel de grades du tenant).
     *
     * @return array{ok: bool, error?: string, identity?: array<string, mixed>}
     */
    public function saveSettings(int $tenantId, int $userId, string $format, string $number, string $imei, string $mac): array
    {
        $row = $this->findRow($tenantId, $userId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Ce terminal est introuvable dans la communauté.'];
        }
        $format = strtoupper(trim($format));
        if (!isset(self::FORMATS[$format])) {
            return ['ok' => false, 'error' => 'Type de numéro inconnu.'];
        }
        $number = trim(preg_replace('/\s+/', ' ', $number) ?? '');
        if ($number === '' || ($format !== (string) $row['phone_format'] && $number === (string) $row['phone_number'])) {
            $number = $this->freeNumber($tenantId, $format);
        } elseif (!self::isPlausibleNumber($number)) {
            return ['ok' => false, 'error' => 'Numéro invalide : chiffres, espaces, +, (, ), - et points seulement, 6 chiffres au moins.'];
        }
        $imeiNorm = trim($imei) === '' ? (string) $row['imei'] : self::normalizeImei($imei);
        if ($imeiNorm === null) {
            return ['ok' => false, 'error' => 'IMEI invalide : 15 chiffres avec une clé de contrôle (Luhn) correcte.'];
        }
        $macNorm = trim($mac) === '' ? (string) $row['mac'] : self::normalizeMac($mac);
        if ($macNorm === null) {
            return ['ok' => false, 'error' => 'Adresse MAC invalide : 6 octets hexadécimaux, premier octet pair (unicast).'];
        }
        $taken = $this->db->fetchOne(
            'SELECT user_id FROM game_phone_identities WHERE tenant_id = :t AND phone_number = :n AND user_id <> :u LIMIT 1',
            ['t' => $tenantId, 'n' => $number, 'u' => $userId]
        );
        if ($taken !== null) {
            return ['ok' => false, 'error' => 'Ce numéro est déjà attribué à un autre opérateur.'];
        }
        $changed = $number !== (string) $row['phone_number']
            || $format !== (string) $row['phone_format']
            || $imeiNorm !== (string) $row['imei']
            || $macNorm !== (string) $row['mac'];
        $this->db->execute(
            'UPDATE game_phone_identities
             SET phone_format = :f, phone_number = :n, imei = :i, mac = :m, format_locked = 1,
                 identity_revision = IF(:c = 1, :r, identity_revision)
             WHERE tenant_id = :t AND user_id = :u',
            ['f' => $format, 'n' => $number, 'i' => $imeiNorm, 'm' => $macNorm, 'c' => $changed ? 1 : 0, 'r' => time(), 't' => $tenantId, 'u' => $userId]
        );
        $saved = $this->findRow($tenantId, $userId);

        return ['ok' => true, 'identity' => $saved === null ? [] : self::identityOf($saved)];
    }

    /**
     * Nouveau numéro dans le format du terminal (ou celui demandé). IMEI et MAC ne changent pas.
     *
     * @return array{ok: bool, error?: string, number?: string}
     */
    public function regenerateNumber(int $tenantId, int $userId, ?string $format = null): array
    {
        $row = $this->findRow($tenantId, $userId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Ce terminal est introuvable dans la communauté.'];
        }
        $format = strtoupper(trim((string) ($format ?? $row['phone_format'])));
        if (!isset(self::FORMATS[$format])) {
            $format = (string) $row['phone_format'];
        }
        $number = $this->freeNumber($tenantId, $format);
        $locked = $format !== (string) $row['phone_format'] ? 1 : (int) ($row['format_locked'] ?? 0);
        $this->db->execute(
            'UPDATE game_phone_identities SET phone_number = :n, phone_format = :f, format_locked = :l, identity_revision = :r WHERE tenant_id = :t AND user_id = :u',
            ['n' => $number, 'f' => $format, 'l' => $locked, 'r' => time(), 't' => $tenantId, 'u' => $userId]
        );

        return ['ok' => true, 'number' => $number];
    }

    /**
     * État remonté par le jeu (télémétrie « phone ») : batterie, état matériel, modèle, valeurs affichées.
     * Après « Changer de téléphone » en jeu (gen > 0), l'IMEI et la MAC du nouvel appareil deviennent
     * ceux d'Athena : le changement survit à la reconnexion. Le numéro (carte SIM) ne change jamais ici.
     *
     * @param array<string, mixed> $report
     */
    public function recordDeviceReport(int $tenantId, int $userId, array $report): void
    {
        $this->forUser($tenantId, $userId);
        $row = $this->findRow($tenantId, $userId);
        if ($row === null) {
            return;
        }
        $battery = null;
        if (isset($report['battery']) && is_numeric($report['battery'])) {
            $battery = max(0, min(100, (int) round((float) $report['battery'])));
        }
        $state = strtoupper(trim((string) ($report['state'] ?? '')));
        if (!isset(self::DEVICE_STATES[$state])) {
            $state = null;
        }
        $bars = isset($report['bars']) && is_numeric($report['bars']) ? max(0, min(4, (int) $report['bars'])) : null;
        $cut = static fn (mixed $v, int $len): ?string => ($t = trim(mb_substr((string) $v, 0, $len))) === '' ? null : $t;
        $liveImei = self::normalizeImei((string) ($report['imei'] ?? ''));
        $liveMac = self::normalizeMac((string) ($report['mac'] ?? ''));

        $this->db->execute(
            'UPDATE game_phone_identities
             SET battery_pct = :b, device_state = :s, device_reason = :why, signal_bars = :bars, device_model = :model,
                 live_number = :ln, live_imei = :li, live_mac = :lm, device_seen_at = NOW()
             WHERE tenant_id = :t AND user_id = :u',
            [
                'b' => $battery, 's' => $state, 'why' => $cut($report['reason'] ?? '', 80), 'bars' => $bars,
                'model' => $cut($report['model'] ?? '', 80), 'ln' => $cut($report['number'] ?? '', 32),
                'li' => $liveImei, 'lm' => $liveMac, 't' => $tenantId, 'u' => $userId,
            ]
        );

        $gen = isset($report['gen']) && is_numeric($report['gen']) ? (int) $report['gen'] : 0;
        if ($gen > 0 && $liveImei !== null && $liveMac !== null
            && ($liveImei !== (string) $row['imei'] || $liveMac !== (string) $row['mac'])) {
            $this->db->execute(
                'UPDATE game_phone_identities SET imei = :i, mac = :m, identity_revision = :r WHERE tenant_id = :t AND user_id = :u',
                ['i' => $liveImei, 'm' => $liveMac, 'r' => time(), 't' => $tenantId, 'u' => $userId]
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array{number: string, imei: string, mac: string, format: string, revision: int}
     */
    private static function identityOf(array $row): array
    {
        return [
            'number' => (string) $row['phone_number'],
            'imei' => (string) $row['imei'],
            'mac' => (string) $row['mac'],
            'format' => (string) $row['phone_format'],
            'revision' => (int) ($row['identity_revision'] ?? 0),
        ];
    }

    public static function isPlausibleNumber(string $number): bool
    {
        if (strlen($number) > 24 || preg_match('/^[0-9 +().\-]+$/', $number) !== 1) {
            return false;
        }

        return strlen(preg_replace('/\D/', '', $number) ?? '') >= 6;
    }

    /** IMEI normalisé (35-XXXXXX-XXXXXX-X) si 15 chiffres et clé de Luhn correcte, sinon null. */
    public static function normalizeImei(string $imei): ?string
    {
        $s = preg_replace('/\D/', '', $imei) ?? '';
        if (strlen($s) !== 15) {
            return null;
        }
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $v = (int) $s[$i];
            if ($i % 2 === 1) {
                $v *= 2;
                if ($v > 9) {
                    $v -= 9;
                }
            }
            $sum += $v;
        }
        if ($sum % 10 !== 0) {
            return null;
        }

        return substr($s, 0, 2) . '-' . substr($s, 2, 6) . '-' . substr($s, 8, 6) . '-' . substr($s, 14, 1);
    }

    /** Adresse MAC normalisée (AA:BB:CC:DD:EE:FF) si unicast valide, sinon null. */
    public static function normalizeMac(string $mac): ?string
    {
        $s = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');
        if (strlen($s) !== 12 || hexdec(substr($s, 0, 2)) % 2 !== 0) {
            return null;
        }

        return implode(':', str_split($s, 2));
    }

    private function freeNumber(int $tenantId, string $format): string
    {
        for ($try = 0; $try < 20; $try++) {
            $n = self::newNumber($format);
            $taken = $this->db->fetchOne(
                'SELECT 1 AS x FROM game_phone_identities WHERE tenant_id = :t AND phone_number = :n LIMIT 1',
                ['t' => $tenantId, 'n' => $n]
            );
            if ($taken === null) {
                return $n;
            }
        }

        return self::newNumber($format);
    }

    /**
     * FR : 06 / 07 xx xx xx xx. US : (NXX) NXX-XXXX, sans les codes de service (N11) ni l'indicatif 555.
     * UK : 07xxx xxxxxx (hors 076, réservé aux bipeurs). DE : 015x / 016x / 017x + 7 chiffres. BE : 046 à 049 xx xx xx.
     */
    public static function newNumber(string $format): string
    {
        if ($format === 'UK') {
            $second = [1, 2, 3, 4, 5, 7, 8, 9][random_int(0, 7)];

            return sprintf('07%d%02d %06d', $second, random_int(0, 99), random_int(0, 999999));
        }
        if ($format === 'DE') {
            $prefixes = ['151', '152', '157', '159', '160', '162', '163', '170', '171', '172', '173', '174', '175', '176', '177', '178', '179'];

            return sprintf('0%s %07d', $prefixes[random_int(0, count($prefixes) - 1)], random_int(0, 9999999));
        }
        if ($format === 'BE') {
            return sprintf('04%d%d %02d %02d %02d', random_int(6, 9), random_int(0, 9), random_int(0, 99), random_int(0, 99), random_int(0, 99));
        }
        if ($format === 'US') {
            $area = random_int(2, 9) . random_int(0, 9) . random_int(0, 9);
            while (in_array($area[1] . $area[2], ['11'], true) || $area === '555') {
                $area = random_int(2, 9) . random_int(0, 9) . random_int(0, 9);
            }
            $exchange = random_int(2, 9) . random_int(0, 9) . random_int(0, 9);
            while ($exchange[1] . $exchange[2] === '11') {
                $exchange = random_int(2, 9) . random_int(0, 9) . random_int(0, 9);
            }

            return sprintf('(%s) %s-%04d', $area, $exchange, random_int(0, 9999));
        }

        return sprintf('0%d %02d %02d %02d %02d', random_int(6, 7), random_int(0, 99), random_int(0, 99), random_int(0, 99), random_int(0, 99));
    }

    /** IMEI 15 chiffres (TAC 35…) avec clé de Luhn, au format 35-XXXXXX-XXXXXX-X. */
    public static function newImei(): string
    {
        $d = [3, 5];
        for ($i = 0; $i < 12; $i++) {
            $d[] = random_int(0, 9);
        }
        $sum = 0;
        foreach ($d as $i => $v) {
            if ($i % 2 === 1) {
                $v *= 2;
                if ($v > 9) {
                    $v -= 9;
                }
            }
            $sum += $v;
        }
        $d[] = (10 - ($sum % 10)) % 10;
        $s = implode('', $d);

        return substr($s, 0, 2) . '-' . substr($s, 2, 6) . '-' . substr($s, 8, 6) . '-' . substr($s, 14, 1);
    }

    /** Adresse MAC unicast (premier octet pair). */
    public static function newMac(): string
    {
        $bytes = [random_int(0, 127) * 2];
        for ($i = 0; $i < 5; $i++) {
            $bytes[] = random_int(0, 255);
        }

        return implode(':', array_map(static fn (int $b): string => sprintf('%02X', $b), $bytes));
    }
}
