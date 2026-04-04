<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;

class ForumUploadController
{
    private const MAX_FILES = 5;
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 Mo
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function handle(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('user_id');
        if (!$tenantId || !$userId) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }
        if ($request->method() !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Méthode non autorisée'], 405);
        }
        $csrf = $request->input('_csrf_token') ?? '';
        if (!Csrf::validate($csrf)) {
            return Response::json(['success' => false, 'error' => 'Jeton CSRF invalide'], 403);
        }

        $raw = $_FILES['files'] ?? $_FILES['images'] ?? null;
        if (!$raw || empty($raw['name'])) {
            return Response::json(['success' => false, 'error' => 'Aucun fichier'], 400);
        }
        $files = [
            'name' => is_array($raw['name']) ? $raw['name'] : [$raw['name']],
            'type' => is_array($raw['type']) ? $raw['type'] : [$raw['type']],
            'tmp_name' => is_array($raw['tmp_name']) ? $raw['tmp_name'] : [$raw['tmp_name']],
            'error' => is_array($raw['error']) ? $raw['error'] : [$raw['error']],
            'size' => is_array($raw['size']) ? $raw['size'] : [$raw['size']],
        ];
        $count = count($files['name']);
        if ($count > self::MAX_FILES) {
            return Response::json(['success' => false, 'error' => 'Maximum ' . self::MAX_FILES . ' fichiers'], 400);
        }

        $webDir = base_path('public/uploads/forum');
        if (!is_dir($webDir)) {
            @mkdir($webDir, 0755, true);
        }
        $saved = [];
        for ($i = 0; $i < $count; $i++) {
            $name = $files['name'][$i] ?? '';
            $type = $files['type'][$i] ?? '';
            $tmp = $files['tmp_name'][$i] ?? '';
            $error = (int) ($files['error'][$i] ?? 0);
            $size = (int) ($files['size'][$i] ?? 0);
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                continue;
            }
            if ($size > self::MAX_SIZE) {
                return Response::json(['success' => false, 'error' => 'Un fichier dépasse 5 Mo'], 400);
            }
            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                return Response::json(['success' => false, 'error' => 'Type non autorisé (JPEG, PNG, GIF, WebP uniquement)'], 400);
            }
            $ext = match ($type) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'bin',
            };
            $id = uniqid('forum_', true) . '.' . $ext;
            $dest = $webDir . '/' . $id;
            if (move_uploaded_file($tmp, $dest)) {
                $saved[] = ['id' => $id, 'url' => url('uploads/forum/' . $id)];
            }
        }
        return Response::json(['success' => true, 'files' => $saved]);
    }
}
