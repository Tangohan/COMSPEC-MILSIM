<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Partage de fichiers d'une communauté : qui peut faire quoi sur un dossier ou un fichier.
 *
 * Rien n'est ouvert par défaut. Le propriétaire d'un dossier (et de tout dossier parent) le gère ; sinon le niveau est le
 * plus haut des partages encore valides qui visent la personne, posés sur le dossier ou l'un de ses parents (héritage),
 * ou sur le fichier lui-même. Un dossier (ou un parent) à la corbeille coupe tout l'accès à son contenu.
 *
 * Niveaux : Consulter (voir et télécharger) < Déposer (ajouter fichiers et sous-dossiers, retirer ses propres dépôts)
 * < Gérer (renommer, supprimer, partager, lire le journal).
 */
final class FileShareAccess
{
    public const NONE = 0;
    public const VIEW = 1;
    public const UPLOAD = 2;
    public const MANAGE = 3;

    /** @var array<string, int> */
    public const LEVELS = ['view' => self::VIEW, 'upload' => self::UPLOAD, 'manage' => self::MANAGE];

    /** @var array<string, string> */
    public const LEVEL_LABELS = ['view' => 'Consulter', 'upload' => 'Déposer', 'manage' => 'Gérer'];

    /** @var array<string, string> */
    public const LEVEL_HINTS = [
        'view' => 'Voir et télécharger',
        'upload' => 'Voir, télécharger et ajouter des fichiers',
        'manage' => 'Tout, y compris renommer, supprimer et partager',
    ];

    /** @var array<string, string> */
    public const TARGET_LABELS = ['user' => 'Membre', 'role' => 'Rôle', 'unit' => 'Unité', 'all' => 'Toute la communauté'];

    public static function levelKey(int $level): string
    {
        return (string) (array_search($level, self::LEVELS, true) ?: '');
    }

    /**
     * Le partage vise-t-il cette personne, et est-il encore valide ?
     *
     * @param array<string, mixed> $grant
     * @param array{user_id:int, role_ids:list<int>, unit_ids:list<int>} $who unit_ids : ses unités et leurs unités parentes
     */
    public static function grantMatches(array $grant, array $who, int $now): bool
    {
        $expires = (string) ($grant['expires_at'] ?? '');
        if ($expires !== '' && ($ts = strtotime($expires)) !== false && $ts <= $now) {
            return false;
        }
        $targetId = (int) ($grant['target_id'] ?? 0);

        return match ((string) ($grant['target_type'] ?? '')) {
            'all' => true,
            'user' => $targetId > 0 && $targetId === $who['user_id'],
            'role' => $targetId > 0 && in_array($targetId, $who['role_ids'], true),
            'unit' => $targetId > 0 && in_array($targetId, $who['unit_ids'], true),
            default => false,
        };
    }

    /**
     * @param list<array<string, mixed>> $grants
     * @param array{user_id:int, role_ids:list<int>, unit_ids:list<int>} $who
     */
    public static function bestGrant(array $grants, array $who, int $now): int
    {
        $best = self::NONE;
        foreach ($grants as $g) {
            if (self::grantMatches($g, $who, $now)) {
                $best = max($best, self::LEVELS[(string) ($g['level'] ?? '')] ?? self::NONE);
            }
        }

        return $best;
    }

    /**
     * Niveau sur un dossier, d'après sa lignée (racine d'abord, le dossier en dernier).
     *
     * @param list<array<string, mixed>> $chain
     * @param array<int, list<array<string, mixed>>> $grantsByFolder
     * @param array{user_id:int, role_ids:list<int>, unit_ids:list<int>} $who
     */
    public static function folderLevel(array $chain, array $grantsByFolder, array $who, int $now): int
    {
        if ($chain === []) {
            return self::NONE;
        }
        $level = self::NONE;
        foreach ($chain as $folder) {
            if (!empty($folder['deleted_at'])) {
                return self::NONE;
            }
            if ($who['user_id'] > 0 && (int) ($folder['owner_user_id'] ?? 0) === $who['user_id']) {
                $level = self::MANAGE;
                continue;
            }
            $level = max($level, self::bestGrant($grantsByFolder[(int) ($folder['id'] ?? 0)] ?? [], $who, $now));
        }

        return $level;
    }

    /**
     * Niveau sur un fichier : celui de son dossier, ou un partage du fichier seul (Déposer n'a pas de sens sur un
     * fichier : ramené à Consulter).
     *
     * @param array<string, mixed> $file
     * @param list<array<string, mixed>> $fileGrants
     * @param array{user_id:int, role_ids:list<int>, unit_ids:list<int>} $who
     */
    public static function fileLevel(array $file, int $folderLevel, array $fileGrants, array $who, int $now): int
    {
        if (!empty($file['deleted_at'])) {
            return self::NONE;
        }
        $direct = self::bestGrant($fileGrants, $who, $now);
        if ($direct === self::UPLOAD) {
            $direct = self::VIEW;
        }

        return max($folderLevel, $direct);
    }

    /**
     * Retirer un fichier : Gérer, ou son auteur s'il peut encore déposer dans le dossier.
     *
     * @param array<string, mixed> $file
     */
    public static function canDeleteFile(array $file, int $fileLevel, int $folderLevel, int $userId): bool
    {
        if ($fileLevel >= self::MANAGE) {
            return true;
        }

        return $folderLevel >= self::UPLOAD && $userId > 0 && (int) ($file['uploaded_by'] ?? 0) === $userId;
    }

    /**
     * Unités de la personne et toutes leurs unités parentes (un partage à une compagnie couvre ses sections).
     *
     * @param list<int> $unitIds
     * @param array<int, int> $parentById id d'unité => id du parent (0 : racine)
     * @return list<int>
     */
    public static function withAncestorUnits(array $unitIds, array $parentById): array
    {
        $out = [];
        foreach ($unitIds as $id) {
            $guard = 0;
            while ($id > 0 && !isset($out[$id]) && $guard++ < 64) {
                $out[$id] = true;
                $id = (int) ($parentById[$id] ?? 0);
            }
        }

        return array_map('intval', array_keys($out));
    }

    /** Nom de dossier ou de fichier propre : sans séparateurs ni caractères de contrôle. */
    public static function cleanName(string $name, int $max): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = trim($name, '. ');

        return mb_substr($name, 0, $max);
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 Mo';
        }
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        $units = ['Ko', 'Mo', 'Go', 'To'];
        $v = $bytes / 1024;
        $i = 0;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return str_replace('.', ',', (string) round($v, $v < 10 ? 1 : 0)) . ' ' . $units[$i];
    }
}
