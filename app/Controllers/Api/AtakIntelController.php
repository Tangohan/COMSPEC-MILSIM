<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakIntelRepository;

class AtakIntelController
{
    public function __construct(
        private AtakIntelRepository $atakIntelRepository
    ) {
    }

    public function storeIntel(Request $request, array $params = []): Response
    {
        $key = getenv('X_COMSPEC_KEY') ?: (getenv('ATAK_INTEL_SECRET') ?: '');
        if ($key !== '') {
            $headerKey = $_SERVER['HTTP_X_COMSPEC_KEY'] ?? $_SERVER['HTTP_X_ATAK_TOKEN'] ?? $this->bearerToken($request);
            if ($headerKey !== $key) {
                return Response::json(['error' => 'Unauthorized', 'message' => 'Clé Arma manquante ou invalide.'], 401);
            }
        }

        $input = $this->getJsonInput($request);
        $path = $request->path();
        $type = $input['type'] ?? $request->input('type', '');
        if ($type === '' && str_contains($path, '/api/atak/pings')) {
            $type = 'PING';
        }
        if ($type === '' && str_contains($path, '/api/chat')) {
            $type = 'CHAT';
        }
        $author = $input['author'] ?? $request->input('author', 'Arma');

        if (!in_array($type, ['PING', 'CHAT', 'PHOTO'], true)) {
            return Response::json(['error' => 'Invalid type'], 400);
        }

        $posX = null;
        $posY = null;
        $content = null;
        $metadata = $input['metadata'] ?? null;

        if ($type === 'PING') {
            $posX = isset($input['pos_x']) ? (float) $input['pos_x'] : null;
            $posY = isset($input['pos_y']) ? (float) $input['pos_y'] : null;
            $content = $input['message'] ?? $input['body'] ?? 'Tactical Ping';
        }

        if ($type === 'CHAT') {
            $content = $input['body'] ?? $request->input('body', '');
        }

        if ($type === 'PHOTO') {
            $imageData = $input['data'] ?? $request->input('data', '');
            if ($imageData !== '') {
                $decoded = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $imageData), true);
                if ($decoded !== false) {
                    $dir = dirname(__DIR__, 2) . '/../storage/intel';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $fileName = 'ctab_' . time() . '.jpg';
                    $path = $dir . '/' . $fileName;
                    if (file_put_contents($path, $decoded) !== false) {
                        $content = 'intel/' . $fileName;
                    }
                }
            }
        }

        $this->atakIntelRepository->store($type, $author, $posX, $posY, $content, $metadata !== null ? (is_array($metadata) ? $metadata : []) : null);

        return Response::json(['status' => 'success']);
    }

    private function getJsonInput(Request $request): array
    {
        $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        return array_merge($_GET, $_POST);
    }

    private function bearerToken(Request $request): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }
        return null;
    }
}
