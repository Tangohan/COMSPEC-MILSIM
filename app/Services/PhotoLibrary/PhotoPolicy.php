<?php

declare(strict_types=1);

namespace App\Services\PhotoLibrary;

/**
 * Matrice des droits de la photothèque, sans base ni session (testée telle quelle). Chaque règle prend le lecteur
 * (PhotoViewer) et les faits de la photo (PhotoPolicy::facts) ; le service refait ces contrôles à chaque écriture, côté
 * serveur, jamais d'après ce que l'écran a montré.
 *
 *  - lire : même communauté ; un cliché confidentiel demande l'habilitation « confidentiel » ; une photo versée au dossier
 *    SSE demande la porte SSE. Sinon la photo est absente (jamais « interdit »).
 *  - voir sous les masques : l'auteur, le gestionnaire, la porte SSE. Les autres reçoivent un fichier déjà masqué.
 *  - étiqueter, commenter, étiqueter librement : tout membre qui voit la photo.
 *  - masquer : l'auteur de la photo ou le gestionnaire ; retirer un masque : eux ou l'auteur du masque.
 *  - une personne SSE n'existe (cadre, nom, commentaire ancré) que pour la porte SSE.
 *  - verser au dossier SSE : porte SSE ; retirer : porte SSE et droit de gestion SSE.
 */
final class PhotoPolicy
{
    /**
     * @param array<string, mixed> $row ligne recon_images
     * @return array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int}
     */
    public static function facts(array $row, ?int $authorUserId, bool $sseVersed): array
    {
        $path = trim((string) ($row['image_path'] ?? ''));

        return [
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'deleted' => !empty($row['deleted_at']),
            'confidential' => !empty($row['is_confidential']) || str_starts_with(ltrim(str_replace('\\', '/', $path), '/'), 'recon-private/'),
            'sse' => $sseVersed,
            'author_user_id' => (int) ($authorUserId ?? 0),
        ];
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canView(PhotoViewer $v, array $f): bool
    {
        if (!$v->isMember() || $f['tenant_id'] !== $v->tenantId || $f['deleted']) {
            return false;
        }
        // Photo versée au dossier SSE : la porte SSE suffit (c'est la règle du versement) ; sinon un cliché confidentiel
        // demande l'habilitation « confidentiel », comme sur le poste ATAK (GET /api/recon/images/{id}/file).
        if ($f['sse']) {
            return $v->sseDoor;
        }

        return !$f['confidential'] || $v->clearConfidential;
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function isAuthor(PhotoViewer $v, array $f): bool
    {
        return $v->userId > 0 && $f['author_user_id'] === $v->userId;
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canSeeUnderMask(PhotoViewer $v, array $f): bool
    {
        return self::canView($v, $f) && (self::isAuthor($v, $f) || $v->manager || $v->sseDoor);
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canTag(PhotoViewer $v, array $f): bool
    {
        return self::canView($v, $f);
    }

    /** Choisir une personne SSE comme cible d'un visage : la porte SSE en plus. */
    public static function canTagSse(PhotoViewer $v, array $f): bool
    {
        return self::canTag($v, $f) && $v->sseDoor;
    }

    /**
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @param array{target_type: string, author_user_id: int} $tag
     */
    public static function canSeeTag(PhotoViewer $v, array $f, array $tag): bool
    {
        if (!self::canView($v, $f)) {
            return false;
        }

        return $tag['target_type'] !== 'sse' || $v->sseDoor;
    }

    /**
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @param array{target_type: string, author_user_id: int} $tag
     */
    public static function canEditTag(PhotoViewer $v, array $f, array $tag): bool
    {
        if (!self::canSeeTag($v, $f, $tag)) {
            return false;
        }

        return $tag['author_user_id'] === $v->userId || $v->manager || ($tag['target_type'] === 'sse' && $v->sseManage);
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canMask(PhotoViewer $v, array $f): bool
    {
        return self::canView($v, $f) && (self::isAuthor($v, $f) || $v->manager);
    }

    /**
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @param array{author_user_id: int} $mask
     */
    public static function canRemoveMask(PhotoViewer $v, array $f, array $mask): bool
    {
        return self::canView($v, $f) && (self::isAuthor($v, $f) || $v->manager || $mask['author_user_id'] === $v->userId);
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canComment(PhotoViewer $v, array $f): bool
    {
        return self::canView($v, $f);
    }

    /**
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @param array{author_user_id: int} $comment
     */
    public static function canEditComment(PhotoViewer $v, array $f, array $comment): bool
    {
        return self::canView($v, $f) && $comment['author_user_id'] === $v->userId;
    }

    /**
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @param array{author_user_id: int} $comment
     */
    public static function canDeleteComment(PhotoViewer $v, array $f, array $comment): bool
    {
        return self::canView($v, $f) && ($comment['author_user_id'] === $v->userId || $v->manager);
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canLabel(PhotoViewer $v, array $f): bool
    {
        return self::canView($v, $f);
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canTransferToSse(PhotoViewer $v, array $f): bool
    {
        return self::canView($v, $f) && $v->mayTransferToSse() && !$f['sse'];
    }

    /** @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f */
    public static function canWithdrawFromSse(PhotoViewer $v, array $f): bool
    {
        return self::canView($v, $f) && $v->sseDoor && $v->sseManage && $f['sse'];
    }
}
