<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\FileShareRepository;
use App\Support\FileShareAccess;
use App\Support\FileShareStorage;
use App\Support\FileShareWorkspace;
use App\Support\NonDefaultTenantContextGuard;

/**
 * Partage de fichiers de la communauté : /partage (membres) et /back-office/partage (même page dans la coque du
 * back-office). Rien n'est visible sans partage : chaque lecture et chaque action repasse par FileShareWorkspace.
 * Un élément d'une autre communauté, ou non partagé, répond comme s'il n'existait pas.
 */
final class FileShareController
{
    private const NOT_FOUND = 'Cet élément n’existe pas ou ne vous est pas partagé.';

    public function __construct(private ?FileShareRepository $repo = null)
    {
        $this->repo ??= new FileShareRepository();
    }

    // ——— Lecture ———

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $bo = str_starts_with(ltrim($request->path(), '/'), 'back-office');
        $base = $bo ? 'back-office/partage' : 'partage';
        $view = [
            'content' => 'admin.partage.index',
            'title' => 'Fichiers partagés',
            'pageTitle' => 'Fichiers partagés',
            'fsBo' => $bo,
            'fsBase' => $base,
            'fsReady' => $this->repo->schemaReady(),
        ];
        if (!$view['fsReady']) {
            return Response::view('layout.main', $view);
        }

        $ws = $this->workspace($tenantId, $userId);
        $folderId = (int) $request->query('dossier', 0);
        $tab = (string) $request->query('vue', '');
        if ($folderId > 0 && $ws->folderLevel($folderId) < FileShareAccess::VIEW) {
            Session::flash('error', self::NOT_FOUND);

            return Response::redirect(url($base));
        }
        $canCreateRoot = $this->canCreateRoot();
        $stats = $this->repo->folderStats($tenantId);
        $level = $folderId > 0 ? $ws->folderLevel($folderId) : FileShareAccess::NONE;

        $view += [
            'fsWs' => $ws,
            'fsUserId' => $userId,
            'fsFolderId' => $folderId,
            'fsFolder' => $folderId > 0 ? $ws->folder($folderId) : null,
            'fsLevel' => $level,
            'fsTab' => in_array($tab, ['corbeille', 'journal'], true) ? $tab : '',
            'fsCanCreateRoot' => $canCreateRoot,
            'fsStats' => $stats,
            'fsEntries' => $ws->entryFolders(),
            'fsUsage' => $this->repo->tenantUsage($tenantId),
            'fsQuota' => FileShareStorage::TENANT_QUOTA_BYTES,
            'fsMaxBytes' => FileShareStorage::MAX_BYTES,
            'fsAccept' => FileShareStorage::acceptedList(),
        ];

        if ($folderId > 0) {
            $view['fsBreadcrumb'] = $ws->breadcrumb($folderId);
            $view['fsSubfolders'] = $ws->childFolders($folderId);
            $view['fsFiles'] = $this->repo->filesInFolder($tenantId, $folderId);
        } else {
            $direct = array_filter(
                $this->repo->filesByIds($tenantId, $ws->directFileIds()),
                static fn (array $f): bool => $ws->fileLevel($f) >= FileShareAccess::VIEW && $ws->folderLevel((int) $f['folder_id']) < FileShareAccess::VIEW
            );
            $view['fsFiles'] = array_values($direct);
            $view['fsSubfolders'] = [];
            $view['fsBreadcrumb'] = [];
        }

        $needsPeople = $level >= FileShareAccess::MANAGE || ($folderId === 0 && $canCreateRoot);
        foreach ($view['fsFiles'] as $f) {
            $needsPeople = $needsPeople || $ws->fileLevel($f) >= FileShareAccess::MANAGE;
        }
        if ($needsPeople) {
            $view['fsMembers'] = $this->repo->members($tenantId);
            $view['fsRoles'] = $this->repo->roles($tenantId);
            $view['fsUnits'] = $this->repo->units($tenantId);
            $view['fsTargetLabels'] = $this->repo->targetLabels($tenantId);
        }

        $userIds = [];
        foreach ($view['fsFiles'] as $f) {
            $userIds[] = (int) $f['uploaded_by'];
        }
        foreach (array_merge($view['fsSubfolders'], $view['fsEntries']) as $f) {
            $userIds[] = (int) $f['owner_user_id'];
        }

        if ($view['fsTab'] === 'journal' && $folderId > 0 && $level >= FileShareAccess::MANAGE) {
            $view['fsEvents'] = $this->repo->events($tenantId, $ws->subtreeIds($folderId));
            foreach ($view['fsEvents'] as $e) {
                $userIds[] = (int) $e['user_id'];
            }
        }
        if ($view['fsTab'] === 'corbeille') {
            $view['fsTrashFolders'] = $ws->trashedFolders();
            $view['fsTrashFiles'] = array_values(array_filter(
                $this->repo->trashedFiles($tenantId),
                fn (array $f): bool => $this->canRestoreFile($ws, $f)
            ));
            foreach ($view['fsTrashFiles'] as $f) {
                $userIds[] = (int) $f['deleted_by'];
            }
            foreach ($view['fsTrashFolders'] as $f) {
                $userIds[] = (int) $f['deleted_by'];
            }
        }
        if ($bo && $canCreateRoot && $folderId === 0) {
            $view['fsOrphans'] = $this->orphans($tenantId, $ws);
        }
        $view['fsUserLabels'] = $this->repo->userLabels($userIds);

        return Response::view('layout.main', $view);
    }

    public function download(Request $request, array $params = []): Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        if (!$this->repo->schemaReady()) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $file = $this->repo->file($tenantId, (int) ($params['id'] ?? 0));
        $ws = $this->workspace($tenantId, $userId);
        if ($file === null || $ws->fileLevel($file) < FileShareAccess::VIEW) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }
        $preview = (string) $request->query('apercu', '') === '1';
        $this->repo->log($tenantId, $userId, null, (int) $file['id'], $preview ? 'preview' : 'download', '', $request->ip());
        if (!$preview) {
            $this->repo->countDownload($tenantId, (int) $file['id']);
        }

        return FileShareStorage::response($file, $tenantId, $preview);
    }

    // ——— Dossiers ———

    public function createFolder(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $parentId = (int) $request->input('parent_id', 0);
        $name = FileShareAccess::cleanName((string) $request->input('name', ''), 120);
        $description = mb_substr(trim((string) $request->input('description', '')), 0, 500);
        if ($name === '') {
            return $this->fail($back($parentId), 'Donnez un nom au dossier.');
        }
        if ($parentId > 0) {
            if ($ws->folderLevel($parentId) < FileShareAccess::UPLOAD) {
                return $this->fail($back(0), self::NOT_FOUND);
            }
        } elseif (!$this->canCreateRoot()) {
            return $this->fail($back(0), 'Seule la gestion de la communauté crée des dossiers racine. Demandez un dossier partagé où déposer.');
        }
        $id = $this->repo->createFolder($tenantId, $parentId > 0 ? $parentId : null, $name, $description, $userId);
        $this->repo->log($tenantId, $userId, $id, null, 'create', $name, $request->ip());
        Session::flash('success', 'Dossier « ' . $name . ' » créé. Il n’est visible que par vous tant que vous ne l’avez pas partagé.');

        return Response::redirect($back($id));
    }

    public function updateFolder(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $id = (int) ($params['id'] ?? 0);
        if ($ws->folderLevel($id) < FileShareAccess::MANAGE) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $name = FileShareAccess::cleanName((string) $request->input('name', ''), 120);
        if ($name === '') {
            return $this->fail($back($id), 'Donnez un nom au dossier.');
        }
        $old = (string) ($ws->folder($id)['name'] ?? '');
        $this->repo->updateFolder($tenantId, $id, $name, mb_substr(trim((string) $request->input('description', '')), 0, 500));
        $this->repo->log($tenantId, $userId, $id, null, 'rename', $old !== $name ? $old . ' → ' . $name : 'description', $request->ip());
        Session::flash('success', 'Dossier enregistré.');

        return Response::redirect($back($id));
    }

    public function deleteFolder(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $id = (int) ($params['id'] ?? 0);
        $folder = $ws->folder($id);
        if ($folder === null || $ws->folderLevel($id) < FileShareAccess::MANAGE) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $this->repo->trashFolder($tenantId, $id, $userId);
        $this->repo->log($tenantId, $userId, $id, null, 'delete', (string) $folder['name'], $request->ip());
        Session::flash('success', 'Dossier « ' . $folder['name'] . ' » mis à la corbeille. Plus personne n’y accède ; il reste restaurable.');
        $parent = (int) ($folder['parent_id'] ?? 0);

        return Response::redirect($back($parent > 0 && $ws->folderLevel($parent) >= FileShareAccess::VIEW ? $parent : 0));
    }

    public function takeOver(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $id = (int) ($params['id'] ?? 0);
        $orphanIds = array_map(static fn (array $f): int => (int) $f['id'], $this->canCreateRoot() ? $this->orphans($tenantId, $ws) : []);
        if (!in_array($id, $orphanIds, true)) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $previous = (int) ($ws->folder($id)['owner_user_id'] ?? 0);
        $this->repo->setFolderOwner($tenantId, $id, $userId);
        $this->repo->log($tenantId, $userId, $id, null, 'takeover', 'ancien propriétaire #' . $previous, $request->ip());
        Session::flash('success', 'Vous êtes maintenant propriétaire de ce dossier. La reprise est notée dans son journal.');

        return Response::redirect($back($id));
    }

    // ——— Fichiers ———

    public function upload(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $folderId = (int) ($params['id'] ?? 0);
        if ($ws->folderLevel($folderId) < FileShareAccess::UPLOAD) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $files = self::normalizeFiles($_FILES['files'] ?? null);
        if ($files === []) {
            return $this->fail($back($folderId), 'Choisissez au moins un fichier.');
        }
        $used = $this->repo->tenantUsage($tenantId);
        $ok = 0;
        $errors = [];
        foreach (array_slice($files, 0, 50) as $f) {
            $name = FileShareAccess::cleanName((string) $f['name'], 255);
            if ((int) $f['error'] !== UPLOAD_ERR_OK) {
                $errors[] = '« ' . ($name !== '' ? $name : 'fichier') . ' » n’a pas été reçu' . (in_array((int) $f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' (trop lourd)' : '') . '.';
                continue;
            }
            if ($used + (int) $f['size'] > FileShareStorage::TENANT_QUOTA_BYTES) {
                $errors[] = 'Espace de la communauté plein : « ' . $name . ' » n’a pas été ajouté.';
                continue;
            }
            $stored = FileShareStorage::store($tenantId, (string) $f['tmp_name'], $name);
            if ($stored['error'] !== null || $stored['path'] === null) {
                $errors[] = (string) $stored['error'];
                continue;
            }
            $fileId = $this->repo->addFile($tenantId, $folderId, $name, $stored, $userId);
            $this->repo->log($tenantId, $userId, $folderId, $fileId, 'upload', $name . ' (' . FileShareAccess::humanSize($stored['size']) . ')', $request->ip());
            $used += $stored['size'];
            $ok++;
        }
        if ($ok > 0) {
            Session::flash('success', $ok === 1 ? '1 fichier ajouté.' : $ok . ' fichiers ajoutés.');
        }
        if ($errors !== []) {
            Session::flash('error', implode(' ', array_slice($errors, 0, 6)));
        }

        return Response::redirect($back($folderId));
    }

    public function renameFile(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $file = $this->repo->file($tenantId, (int) ($params['id'] ?? 0));
        if ($file === null || $ws->fileLevel($file) < FileShareAccess::MANAGE) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $name = FileShareAccess::cleanName((string) $request->input('name', ''), 255);
        // L'extension décide du type servi : on la garde quoi qu'il arrive.
        $ext = FileShareStorage::extension((string) $file['name']);
        if ($name !== '' && FileShareStorage::extension($name) !== $ext) {
            $name .= '.' . $ext;
        }
        if ($name === '' || $name === '.' . $ext) {
            return $this->fail($back((int) $file['folder_id']), 'Donnez un nom au fichier.');
        }
        $this->repo->renameFile($tenantId, (int) $file['id'], $name);
        $this->repo->log($tenantId, $userId, (int) $file['folder_id'], (int) $file['id'], 'rename', $file['name'] . ' → ' . $name, $request->ip());
        Session::flash('success', 'Fichier renommé.');

        return Response::redirect($back($this->landing($ws, (int) $file['folder_id'])));
    }

    public function deleteFile(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $file = $this->repo->file($tenantId, (int) ($params['id'] ?? 0));
        if ($file === null || !$ws->canDeleteFile($file)) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $this->repo->trashFile($tenantId, (int) $file['id'], $userId);
        $this->repo->log($tenantId, $userId, (int) $file['folder_id'], (int) $file['id'], 'delete', (string) $file['name'], $request->ip());
        Session::flash('success', '« ' . $file['name'] . ' » mis à la corbeille.');

        return Response::redirect($back($this->landing($ws, (int) $file['folder_id'])));
    }

    // ——— Corbeille ———

    public function restore(Request $request, array $params = []): Response
    {
        return $this->trashAction($request, $params, false);
    }

    public function purge(Request $request, array $params = []): Response
    {
        return $this->trashAction($request, $params, true);
    }

    private function trashAction(Request $request, array $params, bool $purge): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $trash = $back(0) . (str_contains($back(0), '?') ? '&' : '?') . 'vue=corbeille';
        if ($type === 'dossier') {
            $folder = $ws->folder($id);
            if ($folder === null || !$ws->canRestoreFolder($id)) {
                return $this->fail($trash, self::NOT_FOUND);
            }
            if ($purge) {
                foreach ($this->repo->purgeFolders($tenantId, $ws->subtreeIds($id)) as $path) {
                    FileShareStorage::remove($path, $tenantId);
                }
                $parent = (int) ($folder['parent_id'] ?? 0);
                $this->repo->log($tenantId, $userId, $parent > 0 ? $parent : null, null, 'purge', (string) $folder['name'], $request->ip());
                Session::flash('success', 'Dossier « ' . $folder['name'] . ' » supprimé définitivement.');
            } else {
                $this->repo->restoreFolder($tenantId, $id);
                $this->repo->log($tenantId, $userId, $id, null, 'restore', (string) $folder['name'], $request->ip());
                Session::flash('success', 'Dossier « ' . $folder['name'] . ' » restauré, avec ses partages.');
            }

            return Response::redirect($trash);
        }
        $file = $this->repo->file($tenantId, $id);
        if ($file === null || empty($file['deleted_at']) || !$this->canRestoreFile($ws, $file)) {
            return $this->fail($trash, self::NOT_FOUND);
        }
        if ($purge) {
            if ($ws->folderLevel((int) $file['folder_id']) < FileShareAccess::MANAGE) {
                return $this->fail($trash, 'Seuls ceux qui gèrent le dossier suppriment définitivement.');
            }
            $path = $this->repo->purgeFile($tenantId, $id);
            if ($path !== null) {
                FileShareStorage::remove($path, $tenantId);
            }
            $this->repo->log($tenantId, $userId, (int) $file['folder_id'], null, 'purge', (string) $file['name'], $request->ip());
            Session::flash('success', '« ' . $file['name'] . ' » supprimé définitivement.');
        } else {
            $this->repo->restoreFile($tenantId, $id);
            $this->repo->log($tenantId, $userId, (int) $file['folder_id'], $id, 'restore', (string) $file['name'], $request->ip());
            Session::flash('success', '« ' . $file['name'] . ' » restauré.');
        }

        return Response::redirect($trash);
    }

    // ——— Partages ———

    public function share(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $itemType = (string) $request->input('item_type', '');
        $itemId = (int) $request->input('item_id', 0);
        [$folderId, $fileId, $landing, $itemName] = $this->resolveItem($tenantId, $ws, $itemType, $itemId);
        if ($folderId === null && $fileId === null) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $targetType = (string) $request->input('target_type', '');
        $targetId = match ($targetType) {
            'user' => (int) $request->input('target_user', 0),
            'role' => (int) $request->input('target_role', 0),
            'unit' => (int) $request->input('target_unit', 0),
            default => 0,
        };
        $level = (string) $request->input('level', 'view');
        if (!isset(FileShareAccess::LEVELS[$level]) || ($fileId !== null && $level === 'upload')) {
            $level = 'view';
        }
        $valid = match ($targetType) {
            'all' => true,
            'user' => $targetId > 0 && $this->repo->isMember($tenantId, $targetId),
            'role' => $targetId > 0 && in_array($targetId, array_column($this->repo->roles($tenantId), 'id'), true),
            'unit' => $targetId > 0 && in_array($targetId, array_column($this->repo->units($tenantId), 'id'), true),
            default => false,
        };
        if (!$valid) {
            return $this->fail($back($landing), 'Choisissez avec qui partager : un membre, un rôle ou une unité de la communauté.');
        }
        if ($targetType === 'user' && $targetId === $userId) {
            return $this->fail($back($landing), 'Vous avez déjà accès à cet élément.');
        }
        $expires = trim((string) $request->input('expires', ''));
        $expiresAt = null;
        if ($expires !== '') {
            $ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) ? strtotime($expires . ' 23:59:59') : false;
            if ($ts === false || $ts <= time()) {
                return $this->fail($back($landing), 'La date de fin doit être dans le futur.');
            }
            $expiresAt = date('Y-m-d H:i:s', $ts);
        }
        $this->repo->addGrant($tenantId, $folderId, $fileId, $targetType, $targetId, $level, $expiresAt, $userId);
        $label = $this->targetLabel($tenantId, $targetType, $targetId);
        $this->repo->log($tenantId, $userId, $folderId, $fileId, 'share', $label . ' · ' . FileShareAccess::LEVEL_LABELS[$level] . ($expiresAt !== null ? ' · jusqu’au ' . date('d/m/Y', strtotime($expiresAt)) : ''), $request->ip());
        Session::flash('success', '« ' . $itemName . ' » partagé avec ' . $label . ' (' . FileShareAccess::LEVEL_LABELS[$level] . ').');

        return Response::redirect($back($landing) . ($fileId !== null ? '#fichier-' . $fileId : '#partage'));
    }

    public function unshare(Request $request, array $params = []): Response
    {
        $g = $this->guardPost($request);
        if ($g instanceof Response) {
            return $g;
        }
        [$tenantId, $userId, $ws, $back] = $g;
        $grant = $this->repo->grant($tenantId, (int) ($params['id'] ?? 0));
        if ($grant === null) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $folderId = (int) ($grant['folder_id'] ?? 0);
        $fileId = (int) ($grant['file_id'] ?? 0);
        [$fo, $fi, $landing] = $this->resolveItem($tenantId, $ws, $folderId > 0 ? 'folder' : 'file', $folderId > 0 ? $folderId : $fileId);
        if ($fo === null && $fi === null) {
            return $this->fail($back(0), self::NOT_FOUND);
        }
        $this->repo->deleteGrant($tenantId, (int) $grant['id']);
        $label = $this->targetLabel($tenantId, (string) $grant['target_type'], (int) $grant['target_id']);
        $this->repo->log($tenantId, $userId, $fo, $fi, 'unshare', $label, $request->ip());
        Session::flash('success', 'Accès retiré à ' . $label . '.');

        return Response::redirect($back($ws->folderLevel($landing) >= FileShareAccess::VIEW ? $landing : 0) . ($fi !== null ? '#fichier-' . $fi : '#partage'));
    }

    // ——— Outils ———

    /** @return array{0:int, 1:int}|Response */
    private function context(): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        $blocked = NonDefaultTenantContextGuard::redirectIfInvalid();
        if ($blocked !== null) {
            return $blocked;
        }

        return [$tenantId, $userId];
    }

    /** @return array{0:int, 1:int, 2:FileShareWorkspace, 3:callable(int):string}|Response */
    private function guardPost(Request $request): array|Response
    {
        $ctx = $this->context();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $base = (string) $request->input('_bo', '') === '1' ? 'back-office/partage' : 'partage';
        $back = static fn (int $folderId): string => url($base . ($folderId > 0 ? '?dossier=' . $folderId : ''));
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->fail($back(0), 'Session expirée. Réessayez.');
        }
        if (!$this->repo->schemaReady()) {
            return $this->fail($back(0), 'Le partage de fichiers n’est pas encore installé sur ce serveur.');
        }

        return [$tenantId, $userId, $this->workspace($tenantId, $userId), $back];
    }

    private function workspace(int $tenantId, int $userId): FileShareWorkspace
    {
        $who = [
            'user_id' => $userId,
            'role_ids' => $this->repo->roleIdsForUser($tenantId, $userId),
            'unit_ids' => FileShareAccess::withAncestorUnits($this->repo->unitIdsForUser($tenantId, $userId), $this->repo->unitParents($tenantId)),
        ];

        return new FileShareWorkspace($this->repo->allFolders($tenantId), $this->repo->allGrants($tenantId), $who, time());
    }

    /** Gestion de la communauté : crée les dossiers racine et reprend les dossiers orphelins. */
    private function canCreateRoot(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('admin.system');
    }

    /** @return list<array<string, mixed>> dossiers racine dont le propriétaire a quitté la communauté */
    private function orphans(int $tenantId, FileShareWorkspace $ws): array
    {
        $out = [];
        $checked = [];
        foreach ($this->repo->allFolders($tenantId) as $f) {
            if (!empty($f['deleted_at']) || (int) ($f['parent_id'] ?? 0) > 0) {
                continue;
            }
            $owner = (int) $f['owner_user_id'];
            $checked[$owner] ??= $this->repo->isMember($tenantId, $owner);
            if (!$checked[$owner] && $owner !== $ws->userId()) {
                $out[] = $f;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $file */
    private function canRestoreFile(FileShareWorkspace $ws, array $file): bool
    {
        $level = $ws->folderLevel((int) $file['folder_id']);

        return $level >= FileShareAccess::MANAGE
            || ($level >= FileShareAccess::UPLOAD && (int) $file['uploaded_by'] === $ws->userId());
    }

    /** Dossier où revenir après une action sur un fichier (sa racine si le dossier ne m'est pas ouvert). */
    private function landing(FileShareWorkspace $ws, int $folderId): int
    {
        return $ws->folderLevel($folderId) >= FileShareAccess::VIEW ? $folderId : 0;
    }

    /** @return array{0:?int, 1:?int, 2:int, 3:string} [dossier, fichier, dossier où revenir, nom] si je gère l'élément */
    private function resolveItem(int $tenantId, FileShareWorkspace $ws, string $type, int $id): array
    {
        if ($type === 'folder') {
            $f = $ws->folder($id);
            if ($f !== null && $ws->folderLevel($id) >= FileShareAccess::MANAGE) {
                return [$id, null, $id, (string) $f['name']];
            }
        } elseif ($type === 'file') {
            $file = $this->repo->file($tenantId, $id);
            if ($file !== null && $ws->fileLevel($file) >= FileShareAccess::MANAGE) {
                return [null, $id, $this->landing($ws, (int) $file['folder_id']), (string) $file['name']];
            }
        }

        return [null, null, 0, ''];
    }

    private function targetLabel(int $tenantId, string $type, int $id): string
    {
        if ($type === 'all') {
            return 'toute la communauté';
        }
        $labels = $this->repo->targetLabels($tenantId);
        if ($type === 'user') {
            $labels['user'] += $this->repo->userLabels([$id]);
        }
        $name = $labels[$type][$id] ?? ('#' . $id);

        return match ($type) {
            'role' => 'le rôle ' . $name,
            'unit' => 'l’unité ' . $name,
            default => $name,
        };
    }

    private function fail(string $to, string $message): Response
    {
        Session::flash('error', $message);

        return Response::redirect($to);
    }

    /**
     * $_FILES['files'] (champ multiple) remis à plat.
     *
     * @return list<array{name:string, tmp_name:string, size:int, error:int}>
     */
    public static function normalizeFiles(mixed $raw): array
    {
        if (!is_array($raw) || !isset($raw['name'])) {
            return [];
        }
        $out = [];
        $names = (array) $raw['name'];
        foreach ($names as $i => $name) {
            $err = (int) (((array) ($raw['error'] ?? []))[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) $name,
                'tmp_name' => (string) (((array) ($raw['tmp_name'] ?? []))[$i] ?? ''),
                'size' => (int) (((array) ($raw['size'] ?? []))[$i] ?? 0),
                'error' => $err,
            ];
        }

        return $out;
    }
}
