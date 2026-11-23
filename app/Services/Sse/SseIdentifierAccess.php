<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Core\Session;
use App\Support\SseAccessCatalog;

/**
 * Droits de la session sur les identifiants d'une donnée : mêmes règles que la fiche elle-même (section du portail SSE
 * et périmètre de dossiers), jamais plus larges. Une donnée illisible se comporte comme une donnée inexistante.
 */
final class SseIdentifierAccess
{
    public static function tenantId(): int
    {
        $access = new SseAccessCodeService();
        $t = $access->tenantId();

        return $t > 0 ? $t : (int) Session::get('tenant_id');
    }

    public static function section(string $type, int $entityId = 0): ?string
    {
        $def = SseIdentifierService::TYPES[$type] ?? null;

        return $def === null ? null : SseAccessCatalog::sectionForPath(sprintf($def['href'], max(1, $entityId)));
    }

    /** @param array<string, mixed>|null $entity ligne de la donnée (pour le périmètre de dossier) */
    public static function canRead(string $type, ?array $entity = null): bool
    {
        $section = self::section($type, (int) ($entity['id'] ?? 1));
        $access = new SseAccessCodeService();
        $ctx = ['guest' => $access->isGuest(), 'tenant_id' => self::tenantId()];
        if ($section !== null && !SseAccessPolicy::sessionAllows($section, SseAccessCatalog::LEVEL_READ, $ctx)) {
            return false;
        }
        $caseId = (int) ($entity['case_id'] ?? 0);
        if ($caseId > 0) {
            $scope = $access->caseScope();
            if ($scope !== null && !in_array($caseId, $scope, true)) {
                return false;
            }
        }

        return true;
    }

    public static function canManage(string $type, int $entityId = 0): bool
    {
        $section = self::section($type, $entityId);
        if ($section === null) {
            return false;
        }
        $access = new SseAccessCodeService();

        return !$access->isGuest()
            && SseAccessPolicy::sessionAllows($section, SseAccessCatalog::LEVEL_MANAGE, ['guest' => false, 'tenant_id' => self::tenantId()]);
    }

    /**
     * Ligne de la donnée (id, case_id si la table en a) dans la communauté, ou null.
     *
     * @return array<string, mixed>|null
     */
    public static function entityRow(int $tenantId, string $type, int $entityId): ?array
    {
        $def = SseIdentifierService::TYPES[$type] ?? null;
        if ($def === null) {
            return null;
        }
        $db = Database::getInstance();
        if (in_array($type, ['field_note', 'document'], true)) {
            return $db->fetchOne('SELECT id, case_id FROM ' . $def['table'] . ' WHERE id = :id AND tenant_id = :t', ['id' => $entityId, 't' => $tenantId]);
        }

        return $db->fetchOne('SELECT id FROM ' . $def['table'] . ' WHERE id = :id AND tenant_id = :t', ['id' => $entityId, 't' => $tenantId]);
    }
}
