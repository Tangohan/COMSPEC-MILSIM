<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Vue d'une personne sur le partage de fichiers de sa communauté : tous les dossiers et partages chargés une fois,
 * puis niveaux d'accès, arborescence visible et fil d'Ariane calculés en mémoire (FileShareAccess).
 */
final class FileShareWorkspace
{
    /** @var array<int, array<string, mixed>> */
    private array $folders = [];

    /** @var array<int, list<int>> parent (0 : racine) => enfants */
    private array $children = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $folderGrants = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $fileGrants = [];

    /** @var array<int, int> */
    private array $levelCache = [];

    /**
     * @param list<array<string, mixed>> $folders tous les dossiers de la communauté (corbeille comprise)
     * @param list<array<string, mixed>> $grants tous les partages de la communauté
     * @param array{user_id:int, role_ids:list<int>, unit_ids:list<int>} $who
     */
    public function __construct(array $folders, array $grants, private array $who, private int $now)
    {
        foreach ($folders as $f) {
            $this->folders[(int) $f['id']] = $f;
        }
        foreach ($this->folders as $id => $f) {
            $parent = (int) ($f['parent_id'] ?? 0);
            if ($parent > 0 && !isset($this->folders[$parent])) {
                $parent = -1; // parent inconnu (autre communauté ou effacé) : jamais rattaché à l'arbre
            }
            $this->children[$parent][] = $id;
        }
        foreach ($grants as $g) {
            if ((int) ($g['folder_id'] ?? 0) > 0) {
                $this->folderGrants[(int) $g['folder_id']][] = $g;
            } elseif ((int) ($g['file_id'] ?? 0) > 0) {
                $this->fileGrants[(int) $g['file_id']][] = $g;
            }
        }
    }

    public function userId(): int
    {
        return $this->who['user_id'];
    }

    /** @return array<string, mixed>|null */
    public function folder(int $id): ?array
    {
        return $this->folders[$id] ?? null;
    }

    /** @return list<array<string, mixed>> lignée racine → dossier ; vide si le dossier est inconnu ou mal rattaché */
    public function chain(int $id): array
    {
        $chain = [];
        $seen = [];
        while ($id > 0) {
            if (isset($seen[$id]) || !isset($this->folders[$id])) {
                return [];
            }
            $seen[$id] = true;
            $chain[] = $this->folders[$id];
            $id = (int) ($this->folders[$id]['parent_id'] ?? 0);
        }

        return array_reverse($chain);
    }

    public function folderLevel(int $id): int
    {
        return $this->levelCache[$id] ??= FileShareAccess::folderLevel($this->chain($id), $this->folderGrants, $this->who, $this->now);
    }

    /** @param array<string, mixed> $file */
    public function fileLevel(array $file): int
    {
        return FileShareAccess::fileLevel($file, $this->folderLevel((int) ($file['folder_id'] ?? 0)), $this->fileGrants[(int) ($file['id'] ?? 0)] ?? [], $this->who, $this->now);
    }

    /** @param array<string, mixed> $file */
    public function canDeleteFile(array $file): bool
    {
        return FileShareAccess::canDeleteFile($file, $this->fileLevel($file), $this->folderLevel((int) ($file['folder_id'] ?? 0)), $this->who['user_id']);
    }

    /** @return list<array<string, mixed>> sous-dossiers visibles (hors corbeille) */
    public function childFolders(int $parentId): array
    {
        $out = [];
        foreach ($this->children[$parentId] ?? [] as $id) {
            $f = $this->folders[$id];
            if (empty($f['deleted_at']) && $this->folderLevel($id) >= FileShareAccess::VIEW) {
                $out[] = $f;
            }
        }

        return $out;
    }

    /**
     * Points d'entrée : dossiers accessibles dont le parent ne l'est pas (dossiers racine à moi ou partagés avec moi).
     *
     * @return list<array<string, mixed>>
     */
    public function entryFolders(): array
    {
        $out = [];
        foreach ($this->folders as $id => $f) {
            if (!empty($f['deleted_at']) || $this->folderLevel($id) < FileShareAccess::VIEW) {
                continue;
            }
            $parent = (int) ($f['parent_id'] ?? 0);
            if ($parent > 0 && $this->folderLevel($parent) >= FileShareAccess::VIEW) {
                continue;
            }
            $out[] = $f;
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        return $out;
    }

    /** @return list<int> fichiers partagés seuls, dont le dossier ne m'est pas ouvert */
    public function directFileIds(): array
    {
        $out = [];
        foreach ($this->fileGrants as $fileId => $grants) {
            if (FileShareAccess::bestGrant($grants, $this->who, $this->now) >= FileShareAccess::VIEW) {
                $out[] = $fileId;
            }
        }

        return $out;
    }

    /**
     * Fil d'Ariane : la lignée à partir du premier dossier accessible (on ne révèle pas les dossiers parents fermés).
     *
     * @return list<array<string, mixed>>
     */
    public function breadcrumb(int $id): array
    {
        $chain = $this->chain($id);
        foreach ($chain as $i => $f) {
            if ($this->folderLevel((int) $f['id']) >= FileShareAccess::VIEW) {
                return array_slice($chain, $i);
            }
        }

        return [];
    }

    /** @return list<int> le dossier et tous ses descendants (corbeille comprise) */
    public function subtreeIds(int $id): array
    {
        $out = [];
        $stack = [$id];
        while ($stack !== []) {
            $cur = array_pop($stack);
            if (isset($out[$cur]) || !isset($this->folders[$cur])) {
                continue;
            }
            $out[$cur] = true;
            foreach ($this->children[$cur] ?? [] as $c) {
                $stack[] = $c;
            }
        }

        return array_keys($out);
    }

    /** Ce dossier est-il un descendant (ou le même) de $ancestorId ? */
    public function isWithin(int $id, int $ancestorId): bool
    {
        foreach ($this->chain($id) as $f) {
            if ((int) $f['id'] === $ancestorId) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public function grantsOnFolder(int $id): array
    {
        return $this->folderGrants[$id] ?? [];
    }

    /** @return list<array<string, mixed>> partages hérités des dossiers parents */
    public function inheritedGrants(int $id): array
    {
        $out = [];
        foreach ($this->chain($id) as $f) {
            if ((int) $f['id'] === $id) {
                break;
            }
            foreach ($this->folderGrants[(int) $f['id']] ?? [] as $g) {
                $g['from_folder'] = (string) $f['name'];
                $out[] = $g;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function grantsOnFile(int $id): array
    {
        return $this->fileGrants[$id] ?? [];
    }

    public function isExpired(array $grant): bool
    {
        $e = (string) ($grant['expires_at'] ?? '');

        return $e !== '' && ($ts = strtotime($e)) !== false && $ts <= $this->now;
    }

    /** Dossiers à la corbeille que je peux restaurer (je gère leur parent, ou eux-mêmes s'ils sont racine). */
    public function canRestoreFolder(int $id): bool
    {
        $f = $this->folders[$id] ?? null;
        if ($f === null || empty($f['deleted_at'])) {
            return false;
        }
        $parent = (int) ($f['parent_id'] ?? 0);
        if ($parent > 0) {
            return $this->folderLevel($parent) >= FileShareAccess::MANAGE;
        }
        $copy = $f;
        $copy['deleted_at'] = null;

        return FileShareAccess::folderLevel([$copy], $this->folderGrants, $this->who, $this->now) >= FileShareAccess::MANAGE;
    }

    /** @return list<array<string, mixed>> */
    public function trashedFolders(): array
    {
        $out = [];
        foreach ($this->folders as $id => $f) {
            if (!empty($f['deleted_at']) && $this->canRestoreFolder($id)) {
                $out[] = $f;
            }
        }

        return $out;
    }
}
