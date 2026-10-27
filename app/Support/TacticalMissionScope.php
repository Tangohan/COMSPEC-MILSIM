<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Core\Session;

/**
 * Bac de mission des API C2 (zones de danger, logistique, SITREP, appui-feu, relecture et AAR) cloisonné par communauté.
 * Même règle que IffController : le tenant vient de la clé communauté / session jeu, sinon de la
 * session web ; un missionId fourni n’est retenu que s’il porte le préfixe mission_<tenant>_map_.
 */
final class TacticalMissionScope
{
    /** Longueur des colonnes mission_id (varchar 128). */
    public const MAX_MISSION_ID_LENGTH = 128;

    /** Communauté de l’appelant, ou null si aucune (plus de repli sur la communauté 1). */
    public static function tenantId(): ?int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = Session::get('tenant_id');
        $id = ($sid !== null && $sid !== '') ? (int) $sid : 0;

        return $id > 0 ? $id : null;
    }

    public static function tenantRequiredResponse(): Response
    {
        return Response::json([
            'error' => 'tenant_context_required',
            'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou ouvrez le poste depuis votre communauté.',
        ], 403);
    }

    public static function invalidMissionIdResponse(): Response
    {
        return Response::json([
            'error' => 'invalid_mission_id',
            'message' => 'Identifiant de mission invalide.',
        ], 422);
    }

    /** missionId d’une autre communauté demandé en lecture (relecture, AAR) : refus explicite. */
    public static function missionForbiddenResponse(): Response
    {
        return Response::json([
            'error' => 'mission_forbidden',
            'message' => 'Mission hors du périmètre de votre communauté.',
        ], 403);
    }

    /** Valeur brute acceptable : absente, ou scalaire de longueur bornée. */
    public static function isAcceptableRaw(mixed $raw): bool
    {
        if ($raw === null) {
            return true;
        }

        return is_scalar($raw) && strlen(trim((string) $raw)) <= self::MAX_MISSION_ID_LENGTH;
    }

    /**
     * missionId fourni s’il appartient à la communauté, sinon le bac par défaut de la communauté
     * (mapId fourni, ou carte 1). Un missionId vide ou étranger n’est jamais retenu tel quel.
     */
    public static function resolve(mixed $raw, mixed $mapId, int $tenantId, int $defaultMapId = 1): string
    {
        $missionId = is_scalar($raw) ? trim((string) $raw) : '';
        if ($missionId !== '' && self::belongsTo($missionId, $tenantId)) {
            return $missionId;
        }

        return self::defaultFor($tenantId, $mapId, $defaultMapId);
    }

    public static function belongsTo(string $missionId, int $tenantId): bool
    {
        return str_starts_with($missionId, 'mission_' . $tenantId . '_map_');
    }

    public static function defaultFor(int $tenantId, mixed $mapId, int $defaultMapId = 1): string
    {
        $mid = is_scalar($mapId) && $mapId !== '' ? (int) $mapId : $defaultMapId;
        if ($mid < 1) {
            $mid = $defaultMapId;
        }

        return 'mission_' . $tenantId . '_map_' . $mid;
    }
}
