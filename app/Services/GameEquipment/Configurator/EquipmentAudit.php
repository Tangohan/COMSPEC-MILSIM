<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

use App\Services\Audit\AuditService;

/**
 * Journal d'audit de l'équipement (kits, tenues du configurateur, import du catalogue) : même journal que le reste du
 * back-office (audit_logs, AuditService). Ne lève jamais : un journal indisponible ne bloque pas l'action.
 */
final class EquipmentAudit
{
    /** @var (callable(string, int, int, string, int, array<string, mixed>): void)|null remplace l'écriture (tests) */
    private static $sink = null;

    /**
     * @param array<string, mixed> $details
     */
    public static function log(string $action, int $tenantId, int $userId, int $entityId, array $details): void
    {
        $entity = str_starts_with($action, 'equipment.kit') ? 'game_role_kit'
            : (str_starts_with($action, 'equipment.catalog') ? 'game_item_specs' : 'arsenal_wardrobe');
        if (self::$sink !== null) {
            (self::$sink)($action, $tenantId, $userId, $entity, $entityId, $details);

            return;
        }
        try {
            (new AuditService())->logChange($action, $tenantId > 0 ? $tenantId : null, $userId > 0 ? $userId : null, $entity, $entityId > 0 ? $entityId : null, [], $details);
        } catch (\Throwable $e) {
            error_log('[equipment-audit] ' . $action . ' : ' . $e->getMessage());
        }
    }

    /** Tests : capte les écritures du journal (null pour revenir au journal réel). */
    public static function useSink(?callable $sink): void
    {
        self::$sink = $sink;
    }
}
