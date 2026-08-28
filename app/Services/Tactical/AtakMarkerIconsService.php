<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Core\Database;

/**
 * Bibliothèque d’icônes carte ATAK, mémorisée par communauté.
 */
final class AtakMarkerIconsService
{
    public const KINDS = [
        'player' => 'Opérateurs (joueurs)',
        'ai_friend' => 'IA alliées',
        'ai_hostile' => 'IA adverses',
        'phone' => 'Géolocalisation téléphone',
        'vehicle' => 'Véhicules suivis',
        'air' => 'Aéronefs',
    ];

    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * @return array{library: list<array<string, mixed>>, assignments: array<string, string>}
     */
    public function get(int $tenantId): array
    {
        $raw = $this->readRaw($tenantId) ?? [];
        $library = [];
        foreach ($raw['library'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($id === '' || $url === '') {
                continue;
            }
            $library[] = [
                'id' => $id,
                'label' => trim((string) ($row['label'] ?? 'Icône')) ?: 'Icône',
                'url' => $url,
            ];
        }
        $assignments = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $val = trim((string) (($raw['assignments'][$kind] ?? 'nato')));
            $assignments[$kind] = $val !== '' ? $val : 'nato';
        }

        return ['library' => $library, 'assignments' => $assignments];
    }

    /**
     * Payload carte (URLs publiques).
     *
     * @return array{assignments: array<string, string>, library: list<array{id:string,label:string,url:string}>}
     */
    public function publicPayload(int $tenantId): array
    {
        $pack = $this->get($tenantId);
        $outLib = [];
        foreach ($pack['library'] as $row) {
            $url = function_exists('user_media_public_url')
                ? (user_media_public_url($row['url']) ?? $row['url'])
                : $row['url'];
            $outLib[] = [
                'id' => $row['id'],
                'label' => $row['label'],
                'url' => $url,
            ];
        }
        $assignments = [];
        foreach ($pack['assignments'] as $kind => $val) {
            if (str_starts_with($val, 'upload:')) {
                $id = substr($val, 7);
                $found = null;
                foreach ($outLib as $row) {
                    if ($row['id'] === $id) {
                        $found = $row['url'];
                        break;
                    }
                }
                $assignments[$kind] = $found ?? 'nato';
            } else {
                $assignments[$kind] = $val;
            }
        }

        return ['assignments' => $assignments, 'library' => $outLib];
    }

    /**
     * @param array<string, string> $assignments
     */
    public function saveAssignments(int $tenantId, array $assignments): void
    {
        $pack = $this->get($tenantId);
        foreach (array_keys(self::KINDS) as $kind) {
            $val = trim((string) ($assignments[$kind] ?? 'nato'));
            $pack['assignments'][$kind] = $this->sanitizeAssignment($val, $pack['library']);
        }
        $this->writeRaw($tenantId, $pack);
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{ok:bool, message:string}
     */
    public function addUpload(int $tenantId, array $file, string $label): array
    {
        if ($tenantId < 1) {
            return ['ok' => false, 'message' => 'Communauté introuvable.'];
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'message' => $err === UPLOAD_ERR_NO_FILE
                ? 'Choisissez un fichier image.'
                : 'L’image est trop lourde (maximum 2 Mo).'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'L’envoi de l’image a échoué. Réessayez.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 32 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'message' => 'L’image doit faire moins de 2 Mo.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'message' => 'Fichier invalide.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => '',
        };
        if ($ext === '') {
            return ['ok' => false, 'message' => 'Formats acceptés : PNG, JPG ou WebP.'];
        }
        $dirRel = 'uploads/tenants/' . $tenantId . '/atak-icons';
        $absDir = base_path('public/' . $dirRel);
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['ok' => false, 'message' => 'Impossible d’enregistrer l’image pour le moment.'];
        }
        $id = 'u' . bin2hex(random_bytes(6));
        $name = $id . '.' . $ext;
        $abs = $absDir . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmp, $abs) && !@copy($tmp, $abs)) {
            return ['ok' => false, 'message' => 'Impossible d’enregistrer l’image pour le moment.'];
        }
        $pack = $this->get($tenantId);
        $label = trim($label);
        if ($label === '') {
            $label = 'Icône personnalisée';
        }
        $pack['library'][] = [
            'id' => $id,
            'label' => substr($label, 0, 80),
            'url' => $dirRel . '/' . $name,
        ];
        $this->writeRaw($tenantId, $pack);

        return ['ok' => true, 'message' => 'Icône ajoutée à la bibliothèque.'];
    }

    public function deleteLibraryItem(int $tenantId, string $id): void
    {
        $id = trim($id);
        if ($id === '') {
            return;
        }
        $pack = $this->get($tenantId);
        $kept = [];
        foreach ($pack['library'] as $row) {
            if (($row['id'] ?? '') === $id) {
                continue;
            }
            $kept[] = $row;
        }
        $pack['library'] = $kept;
        foreach ($pack['assignments'] as $kind => $val) {
            if ($val === 'upload:' . $id || $val === $id) {
                $pack['assignments'][$kind] = 'nato';
            }
        }
        $this->writeRaw($tenantId, $pack);
    }

    /**
     * @param list<array<string, mixed>> $library
     */
    private function sanitizeAssignment(string $val, array $library): string
    {
        if ($val === '' || $val === 'nato') {
            return 'nato';
        }
        if (str_starts_with($val, 'arma:')) {
            $png = strtolower(str_replace('\\', '/', substr($val, 5)));
            $png = ltrim($png, '/');
            if ($png !== '' && !str_contains($png, '..') && preg_match('#^[a-z0-9/_\-.]+\\.png$#', $png) === 1) {
                return 'arma:' . $png;
            }

            return 'nato';
        }
        if (str_starts_with($val, 'upload:')) {
            $id = substr($val, 7);
            foreach ($library as $row) {
                if (($row['id'] ?? '') === $id) {
                    return 'upload:' . $id;
                }
            }

            return 'nato';
        }

        return 'nato';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRaw(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        $this->ensureSchema();
        if (!$this->hasColumn()) {
            return null;
        }
        try {
            $st = Database::getPdo()->prepare(
                'SELECT marker_icons_config FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1'
            );
            $st->execute([$tenantId]);
            $raw = $st->fetchColumn();
            if (!is_string($raw) || trim($raw) === '') {
                return null;
            }
            $data = json_decode($raw, true);

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeRaw(int $tenantId, array $config): void
    {
        if ($tenantId < 1) {
            return;
        }
        $this->ensureSchema();
        if (!$this->hasColumn()) {
            return;
        }
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        $pdo = Database::getPdo();
        try {
            $st = $pdo->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            if ($st->fetchColumn()) {
                $upd = $pdo->prepare(
                    'UPDATE tenant_atak_config SET marker_icons_config = ?, updated_at = NOW() WHERE tenant_id = ?'
                );
                $upd->execute([$json, $tenantId]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO tenant_atak_config (tenant_id, marker_icons_config, default_map_slug, created_at, updated_at)
                     VALUES (?, ?, \'altis\', NOW(), NOW())'
                );
                $ins->execute([$tenantId, $json]);
            }
        } catch (\Throwable) {
        }
    }

    private function ensureSchema(): void
    {
        static $attempted = false;
        if ($attempted) {
            return;
        }
        $attempted = true;
        if ($this->hasColumn()) {
            return;
        }
        try {
            Database::getPdo()->exec(
                'ALTER TABLE tenant_atak_config ADD COLUMN marker_icons_config JSON DEFAULT NULL'
            );
        } catch (\Throwable) {
        }
    }

    private function hasColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $st = Database::getPdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config'
                   AND COLUMN_NAME = 'marker_icons_config' LIMIT 1"
            );
            $cached = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $cached = false;
        }

        return $cached;
    }
}
