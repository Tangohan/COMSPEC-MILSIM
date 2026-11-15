<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseWantedCodes;
use App\Support\SseWantedPhysical;
use PDO;

/**
 * Compléments d'un avis de recherche (table fille sse_wanted_notice_extras) : codes de motifs et de mesures choisis (avec leurs
 * libellés du jour de la pose) et signalement physique propre à l'avis. Un avis sans ligne ici est un avis sans codes ni
 * signalement : il fonctionne comme avant. Sans la table (étape de base de données en attente), tout renvoie « vide ».
 */
final class SseWantedExtrasRepository
{
    private Database $db;

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** La table existe-t-elle ? (résultat gardé par connexion) */
    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_wanted_notice_extras WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /**
     * Codes et signalement de plusieurs avis (une requête). Un avis sans ligne : codes vides, signalement vide.
     *
     * @param list<int> $noticeIds
     * @return array<int, array{codes: array{reasons: list<array{code: string, fr: string, en: string}>, measures: list<array{code: string, fr: string, en: string}>, template: ?string}, physical: array<string, mixed>}>
     */
    public function forNotices(int $tenantId, array $noticeIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $noticeIds), static fn (int $i): bool => $i > 0))), 0, 500);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = self::hydrate(null);
        }
        if ($tenantId < 1 || $ids === [] || !self::ready()) {
            return $out;
        }
        $params = ['t' => $tenantId];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':n' . $k;
            $params['n' . $k] = $id;
        }
        try {
            foreach ($this->db->fetchAll('SELECT * FROM sse_wanted_notice_extras WHERE tenant_id = :t AND notice_id IN (' . implode(', ', $marks) . ')', $params) as $row) {
                $out[(int) $row['notice_id']] = self::hydrate($row);
            }
        } catch (\Throwable $e) {
            error_log('Avis de recherche : codes illisibles : ' . $e->getMessage());
        }

        return $out;
    }

    /** @return array{codes: array<string, mixed>, physical: array<string, mixed>} */
    public function forNotice(int $tenantId, int $noticeId): array
    {
        return $this->forNotices($tenantId, [$noticeId])[$noticeId] ?? self::hydrate(null);
    }

    /**
     * Enregistre les compléments d'un avis (une ligne par avis). Rien à écrire : aucune ligne créée.
     *
     * @param array<string, mixed>|null $codes forme de {@see SseWantedCodes::snapshot()}
     * @param array<string, mixed>|null $physical forme de {@see SseWantedPhysical::normalize()}
     */
    public function save(int $tenantId, int $noticeId, ?array $codes, ?array $physical): bool
    {
        $hasCodes = $codes !== null && (($codes['reasons'] ?? []) !== [] || ($codes['measures'] ?? []) !== []);
        if ($tenantId < 1 || $noticeId < 1 || !self::ready() || (!$hasCodes && $physical === null)) {
            return false;
        }
        $params = [
            'c' => $hasCodes ? json_encode($codes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'p' => $physical !== null ? json_encode($physical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'now' => gmdate('Y-m-d H:i:s'), 'n' => $noticeId, 't' => $tenantId,
        ];
        if ($this->db->fetchOne('SELECT notice_id FROM sse_wanted_notice_extras WHERE notice_id = :n AND tenant_id = :t', ['n' => $noticeId, 't' => $tenantId]) !== null) {
            $this->db->execute('UPDATE sse_wanted_notice_extras SET codes_json = :c, physical_json = :p, updated_at = :now WHERE notice_id = :n AND tenant_id = :t', $params);

            return true;
        }
        $this->db->execute('INSERT INTO sse_wanted_notice_extras (notice_id, tenant_id, codes_json, physical_json, updated_at) VALUES (:n, :t, :c, :p, :now)', $params);

        return true;
    }

    /**
     * Clause SQL « l'avis porte l'un de ces codes », pour la liste des avis.
     *
     * @param list<string> $codes
     * @param array<string, mixed> $params paramètres de la requête, complétés
     */
    public static function codeClause(array $codes, int $tenantId, array &$params, string $notice = 'n', string $prefix = 'cx'): string
    {
        $likes = [];
        foreach (array_slice($codes, 0, 40) as $k => $code) {
            if (SseWantedCodes::kindOf($code) === null) {
                continue;
            }
            $likes[] = $prefix . 'x.codes_json LIKE :' . $prefix . $k;
            $params[$prefix . $k] = '%"code":"' . $code . '"%';
        }
        if ($likes === []) {
            return '1 = 0';
        }
        $params[$prefix . 't'] = $tenantId;

        return $notice . '.id IN (SELECT ' . $prefix . 'x.notice_id FROM sse_wanted_notice_extras ' . $prefix . 'x WHERE ' . $prefix . 'x.tenant_id = :' . $prefix . 't AND (' . implode(' OR ', $likes) . '))';
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array{codes: array{reasons: list<array{code: string, fr: string, en: string}>, measures: list<array{code: string, fr: string, en: string}>, template: ?string}, physical: array<string, mixed>}
     */
    public static function hydrate(?array $row): array
    {
        return [
            'codes' => SseWantedCodes::readSnapshot($row['codes_json'] ?? null),
            'physical' => SseWantedPhysical::read($row['physical_json'] ?? null),
        ];
    }
}
