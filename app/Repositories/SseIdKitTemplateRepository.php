<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseIdentityCatalog;
use App\Support\SseIdWorkshopCatalog;

/**
 * COMSPEC IDENTITY (Lot 6) — kits documentaires types : modèles réutilisables (types de papiers, pays, identité qui
 * les porte, champs pré-remplis, téléphone et adresse simulés, éléments de légende) affectés à un ou plusieurs membres.
 */
final class SseIdKitTemplateRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data, ?int $by = null): int
    {
        $r = self::normalize($data);

        return $this->db->insert(
            'INSERT INTO sse_id_kit_templates (tenant_id, name, category, description, country_code, doc_types, identity_mode, profession,
                layout, phone, address, biography, legend, contacts, instructions, print_json, created_by_user_id, created_at)
             VALUES (:t, :name, :cat, :descr, :country, :types, :mode, :prof, :layout, :phone, :address, :bio, :legend, :contacts,
                :instr, :print, :by, :now)',
            ['t' => $tenantId, 'by' => $by, 'now' => gmdate('Y-m-d H:i:s')] + self::params($r)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $r = self::normalize($data);

        return $this->db->execute(
            'UPDATE sse_id_kit_templates SET name = :name, category = :cat, description = :descr, country_code = :country, doc_types = :types,
                identity_mode = :mode, profession = :prof, layout = :layout, phone = :phone, address = :address, biography = :bio,
                legend = :legend, contacts = :contacts, instructions = :instr, print_json = :print, updated_at = :now
             WHERE id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId, 'now' => gmdate('Y-m-d H:i:s')] + self::params($r)
        ) > 0;
    }

    public function setArchived(int $id, int $tenantId, bool $archived): bool
    {
        return $this->db->execute(
            'UPDATE sse_id_kit_templates SET archived_at = :at, updated_at = :now WHERE id = :id AND tenant_id = :t',
            ['at' => $archived ? gmdate('Y-m-d H:i:s') : null, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_id_kit_templates WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, bool $withArchived = false): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_id_kit_templates WHERE tenant_id = :t' . ($withArchived ? '' : ' AND archived_at IS NULL')
            . ' ORDER BY CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END, name ASC, id ASC LIMIT 200',
            ['t' => $tenantId]
        );

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $str = static function (mixed $v, int $max): ?string {
            $s = trim(is_scalar($v) ? (string) $v : '');

            return $s === '' ? null : mb_substr($s, 0, $max);
        };
        $types = $data['doc_types'] ?? [];
        $types = is_array($types) ? $types : explode(',', (string) $types);
        $types = array_values(array_filter(array_keys(SseIdentityCatalog::DOC_TYPES), static fn (string $t): bool => in_array($t, array_map('strval', $types), true)));

        return [
            'name' => (string) ($str($data['name'] ?? null, 120) ?? ''),
            'category' => SseIdentityCatalog::pick(SseIdWorkshopCatalog::KIT_CATEGORIES, $data['category'] ?? null, 'other'),
            'description' => $str($data['description'] ?? null, 500),
            'country_code' => ($c = strtoupper((string) $str($data['country_code'] ?? null, 3))) !== '' ? $c : null,
            'doc_types' => $types !== [] ? $types : ['id_card'],
            'identity_mode' => SseIdentityCatalog::pick(SseIdWorkshopCatalog::IDENTITY_MODES, $data['identity_mode'] ?? null, 'primary'),
            'profession' => $str($data['profession'] ?? null, 120),
            'layout' => SseIdWorkshopCatalog::layout($data['layout'] ?? null),
            'phone' => $str($data['phone'] ?? null, 40),
            'address' => $str($data['address'] ?? null, 255),
            'biography' => $str($data['biography'] ?? null, 2000),
            'legend' => $str($data['legend'] ?? null, 2000),
            'contacts' => $str($data['contacts'] ?? null, 1000),
            'instructions' => $str($data['instructions'] ?? null, 2000),
            'print' => SseIdWorkshopCatalog::printFields($data['print'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $r normalize()
     * @return array<string, mixed>
     */
    private static function params(array $r): array
    {
        return [
            'name' => $r['name'],
            'cat' => $r['category'],
            'descr' => $r['description'],
            'country' => $r['country_code'],
            'types' => implode(',', $r['doc_types']),
            'mode' => $r['identity_mode'],
            'prof' => $r['profession'],
            'layout' => $r['layout'],
            'phone' => $r['phone'],
            'address' => $r['address'],
            'bio' => $r['biography'],
            'legend' => $r['legend'],
            'contacts' => $r['contacts'],
            'instr' => $r['instructions'],
            'print' => SseIdWorkshopCatalog::printJson($r['print']),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $r = self::normalize([
            'print' => $row['print_json'] ?? null,
            'doc_types' => (string) ($row['doc_types'] ?? ''),
        ] + $row);
        $r['id'] = (int) ($row['id'] ?? 0);
        $r['ref'] = 'KIT-' . str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT);
        $r['category_label'] = SseIdWorkshopCatalog::label(SseIdWorkshopCatalog::KIT_CATEGORIES, $r['category']);
        $r['identity_mode_label'] = SseIdWorkshopCatalog::label(SseIdWorkshopCatalog::IDENTITY_MODES, $r['identity_mode']);
        $r['archived'] = ($row['archived_at'] ?? null) !== null;
        $r['created_at'] = (string) ($row['created_at'] ?? '');
        $r['updated_at'] = $row['updated_at'] ?? null;

        return $r;
    }
}
