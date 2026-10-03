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
    }

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
     * @return array{number: string, imei: string, mac: string, format: string}
     */
    public function forUser(int $tenantId, int $userId): array
    {
        $format = $this->formatForTenant($tenantId);
        $row = $this->db->fetchOne(
            'SELECT phone_number, imei, mac, phone_format FROM game_phone_identities WHERE tenant_id = :t AND user_id = :u LIMIT 1',
            ['t' => $tenantId, 'u' => $userId]
        );
        if ($row !== null && (string) $row['phone_format'] === $format) {
            return ['number' => (string) $row['phone_number'], 'imei' => (string) $row['imei'], 'mac' => (string) $row['mac'], 'format' => $format];
        }

        $number = $this->freeNumber($tenantId, $format);
        if ($row !== null) {
            $this->db->execute(
                'UPDATE game_phone_identities SET phone_number = :n, phone_format = :f WHERE tenant_id = :t AND user_id = :u',
                ['n' => $number, 'f' => $format, 't' => $tenantId, 'u' => $userId]
            );

            return ['number' => $number, 'imei' => (string) $row['imei'], 'mac' => (string) $row['mac'], 'format' => $format];
        }

        $imei = self::newImei();
        $mac = self::newMac();
        $this->db->execute(
            'INSERT IGNORE INTO game_phone_identities (tenant_id, user_id, phone_format, phone_number, imei, mac) VALUES (:t, :u, :f, :n, :i, :m)',
            ['t' => $tenantId, 'u' => $userId, 'f' => $format, 'n' => $number, 'i' => $imei, 'm' => $mac]
        );
        // Deux connexions simultanées : on relit la ligne gagnante.
        $saved = $this->db->fetchOne(
            'SELECT phone_number, imei, mac, phone_format FROM game_phone_identities WHERE tenant_id = :t AND user_id = :u LIMIT 1',
            ['t' => $tenantId, 'u' => $userId]
        );

        return $saved === null
            ? ['number' => $number, 'imei' => $imei, 'mac' => $mac, 'format' => $format]
            : ['number' => (string) $saved['phone_number'], 'imei' => (string) $saved['imei'], 'mac' => (string) $saved['mac'], 'format' => (string) $saved['phone_format']];
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

    /** FR : 06 / 07 xx xx xx xx. US : (NXX) NXX-XXXX, sans les codes de service (N11) ni l'indicatif 555. */
    public static function newNumber(string $format): string
    {
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
