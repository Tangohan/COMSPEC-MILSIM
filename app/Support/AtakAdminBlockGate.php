<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Repositories\AtakAdminControlRepository;

/**
 * Blocages d'ATAK décidés par l'administration du site (/admin/parc-atak), appliqués par l'API :
 * connexion Athena du jeu, reprise de session, enregistrement du téléphone et tous les envois du jeu.
 */
final class AtakAdminBlockGate
{
    private static ?AtakAdminControlRepository $repo = null;

    public static function useRepository(?AtakAdminControlRepository $repo): void
    {
        self::$repo = $repo;
    }

    /** @return array<string, mixed>|null le blocage actif, s'il y en a un */
    public static function find(int $tenantId, ?int $userId, ?string $steamUid, ?string $terminalUid = null): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        try {
            return (self::$repo ??= new AtakAdminControlRepository())->activeBlockFor($tenantId, $userId, $steamUid, $terminalUid);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Réponse 403 à renvoyer au jeu, ou null si rien ne bloque. */
    public static function deny(int $tenantId, ?int $userId, ?string $steamUid, ?string $terminalUid = null): ?Response
    {
        $block = self::find($tenantId, $userId, $steamUid, $terminalUid);

        return $block === null ? null : Response::json(self::payload($block), 403);
    }

    /**
     * Corps de refus commun à l'API ATAK et à la connexion Athena du jeu.
     *
     * @param array<string, mixed> $block
     * @return array{error: string, message: string, blocked_until: ?string}
     */
    public static function payload(array $block): array
    {
        $until = trim((string) ($block['expires_at'] ?? ''));

        return [
            'error' => 'atak_blocked',
            'message' => self::playerMessage($block),
            'blocked_until' => $until !== '' ? $until : null,
        ];
    }

    /** @param array<string, mixed> $block */
    public static function playerMessage(array $block): string
    {
        $msg = trim((string) ($block['player_message'] ?? ''));

        return $msg !== '' ? $msg : 'Votre ATAK a été bloqué par l’administration ATHENA. Contactez le support pour en savoir plus.';
    }
}
