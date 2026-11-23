<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Bloc « Identifiants » d'une fiche SSE : inclus en une ligne par la page de chaque donnée,
 * \App\Services\Sse\SseIdentifierWidget::render('person', $pid);
 * Ne casse jamais la page : sans table (étape 5020 pas encore passée) ou en cas d'erreur, rien n'est rendu.
 */
final class SseIdentifierWidget
{
    public static function render(string $type, int $entityId): void
    {
        $level = ob_get_level();
        try {
            $tenantId = SseIdentifierAccess::tenantId();
            $entity = SseIdentifierAccess::entityRow($tenantId, $type, $entityId);
            if ($entity === null || !SseIdentifierAccess::canRead($type, $entity)) {
                return;
            }
            $service = new SseIdentifierService();
            $ident = $service->assign($tenantId, $type, $entityId);
            if ($ident === []) {
                return;
            }
            $canManage = SseIdentifierAccess::canManage($type, $entityId);
            $ai = null;
            $profiles = [];
            if (!empty($ident['ai_profile_id'])) {
                $ai = $service->aiProfile($tenantId, (int) $ident['ai_profile_id']);
            }
            if ($canManage) {
                $profiles = $service->aiProfiles($tenantId);
            }
            $sseIdent = ['row' => $ident, 'type' => $type, 'entityId' => $entityId, 'canManage' => $canManage, 'ai' => $ai, 'profiles' => $profiles];
            ob_start();
            require \base_path('views/atak/sse/_identifiers.php');
            echo (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            error_log('[sse.identifiants] bloc indisponible : ' . $e->getMessage());
        }
    }
}
