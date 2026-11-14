<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Repositories\FileShareRepository;
use App\Support\FileShareAccess;
use App\Support\FileShareStorage;
use App\Support\FileShareWorkspace;

/**
 * Photos et documents d'une unité : les fichiers que le « Partage de fichiers » de la communauté a partagés avec cette
 * unité (dossier ou fichier visant l'unité). Aucun stockage ni droit propre à JNET : chaque fichier n'est listé que si
 * le lecteur y a déjà accès (FileShareWorkspace), et s'ouvre par /partage/fichiers/{id}, qui refait le contrôle.
 */
final class JnetUnitFiles
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(private ?FileShareRepository $repo = null)
    {
        $this->repo ??= new FileShareRepository();
    }

    public static function isImage(string $name): bool
    {
        return in_array(FileShareStorage::extension($name), self::IMAGE_EXTENSIONS, true);
    }

    /**
     * @return array{ready: bool, photos: list<array<string, mixed>>, documents: list<array<string, mixed>>, folders: list<array{id:int,name:string,canUpload:bool}>}
     */
    public function forUnit(int $tenantId, int $viewerUserId, int $unitId): array
    {
        $empty = ['ready' => false, 'photos' => [], 'documents' => [], 'folders' => []];
        try {
            if ($unitId <= 0 || !$this->repo->schemaReady()) {
                return $empty;
            }
            $folders = $this->repo->allFolders($tenantId);
            $grants = $this->repo->allGrants($tenantId);
            $who = [
                'user_id' => $viewerUserId,
                'role_ids' => $this->repo->roleIdsForUser($tenantId, $viewerUserId),
                'unit_ids' => FileShareAccess::withAncestorUnits($this->repo->unitIdsForUser($tenantId, $viewerUserId), $this->repo->unitParents($tenantId)),
            ];
            $ws = new FileShareWorkspace($folders, $grants, $who, time());
        } catch (\Throwable) {
            return $empty;
        }

        $now = time();
        $folderIds = [];
        $fileIds = [];
        foreach ($grants as $g) {
            if ((string) ($g['target_type'] ?? '') !== 'unit' || (int) ($g['target_id'] ?? 0) !== $unitId) {
                continue;
            }
            $expires = (string) ($g['expires_at'] ?? '');
            if ($expires !== '' && ($ts = strtotime($expires)) !== false && $ts <= $now) {
                continue;
            }
            if ((int) ($g['folder_id'] ?? 0) > 0) {
                foreach ($ws->subtreeIds((int) $g['folder_id']) as $fid) {
                    $folderIds[$fid] = true;
                }
            } elseif ((int) ($g['file_id'] ?? 0) > 0) {
                $fileIds[(int) $g['file_id']] = true;
            }
        }

        $files = [];
        $folderRows = [];
        foreach (array_keys($folderIds) as $fid) {
            $folder = $ws->folder($fid);
            if ($folder === null || !empty($folder['deleted_at']) || $ws->folderLevel($fid) < FileShareAccess::VIEW) {
                continue;
            }
            $folderRows[] = ['id' => $fid, 'name' => (string) $folder['name'], 'canUpload' => $ws->folderLevel($fid) >= FileShareAccess::UPLOAD];
            foreach ($this->repo->filesInFolder($tenantId, $fid) as $f) {
                $files[(int) $f['id']] = $f + ['album' => (string) $folder['name']];
            }
        }
        foreach ($this->repo->filesByIds($tenantId, array_keys($fileIds)) as $f) {
            if (empty($f['deleted_at']) && !isset($files[(int) $f['id']])) {
                $files[(int) $f['id']] = $f + ['album' => 'Fichiers partagés'];
            }
        }

        $authors = $this->repo->userLabels(array_map(static fn (array $f): int => (int) $f['uploaded_by'], $files));
        $photos = $documents = [];
        foreach ($files as $f) {
            if ($ws->fileLevel($f) < FileShareAccess::VIEW) {
                continue;
            }
            $name = (string) $f['name'];
            $item = [
                'id' => (int) $f['id'],
                'name' => $name,
                'title' => (string) preg_replace('/\.[^.]+$/', '', $name),
                'ext' => strtoupper(FileShareStorage::extension($name)),
                'album' => (string) $f['album'],
                'size' => self::sizeLabel((int) ($f['size_bytes'] ?? 0)),
                'date' => self::dateLabel((string) ($f['created_at'] ?? '')),
                'ts' => (int) strtotime((string) ($f['created_at'] ?? '')),
                'by' => (string) ($authors[(int) $f['uploaded_by']] ?? ''),
                'href' => url('partage/fichiers/' . (int) $f['id']),
                'preview' => url('partage/fichiers/' . (int) $f['id'] . '?apercu=1'),
            ];
            if (self::isImage($name)) {
                $photos[] = $item;
            } else {
                $documents[] = $item;
            }
        }
        $byDate = static fn (array $a, array $b): int => $b['ts'] <=> $a['ts'];
        usort($photos, $byDate);
        usort($documents, $byDate);
        usort($folderRows, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return ['ready' => true, 'photos' => $photos, 'documents' => $documents, 'folders' => $folderRows];
    }

    public static function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1_000_000) {
            return str_replace('.', ',', (string) round($bytes / 1_000_000, 1)) . ' Mo';
        }

        return max(1, (int) round($bytes / 1000)) . ' Ko';
    }

    public static function dateLabel(string $raw): string
    {
        $ts = strtotime($raw);

        return $ts ? date('d/m/Y', $ts) : '';
    }

    /** @param list<array<string, mixed>> $items @return array<string, int> album => nombre */
    public static function albums(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            $out[(string) $i['album']] = ($out[(string) $i['album']] ?? 0) + 1;
        }
        ksort($out);

        return $out;
    }
}
