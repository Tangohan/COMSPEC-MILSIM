<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingCertificateTemplateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return array<string, mixed> */
    public function findByTenantId(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_certificate_templates WHERE tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Met à jour uniquement les chemins logo / fond (nettoyage des références fichiers manquants).
     */
    public function updateAssetRelativePaths(int $tenantId, ?string $logoRelative, ?string $backgroundRelative): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE training_certificate_templates
             SET logo_relative_path = ?, background_relative_path = ?
             WHERE tenant_id = ?'
        );
        $stmt->execute([$logoRelative, $backgroundRelative, $tenantId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertForTenant(int $tenantId, array $data): void
    {
        $existing = $this->findByTenantId($tenantId);
        $name = (string) ($data['name'] ?? 'Modèle par défaut');
        $headline = (string) ($data['headline'] ?? 'Attestation de formation');
        $subtitle = isset($data['subtitle']) ? (string) $data['subtitle'] : null;
        $footer = isset($data['footer_legal']) ? (string) $data['footer_legal'] : null;
        $primary = (string) ($data['primary_hex'] ?? '#0f172a');
        $accent = (string) ($data['accent_hex'] ?? '#059669');
        $logo = isset($data['logo_relative_path']) ? (string) $data['logo_relative_path'] : null;
        $bg = isset($data['background_relative_path']) ? (string) $data['background_relative_path'] : null;
        $layoutJson = $data['layout_json'] ?? null;
        $layout = null;
        if (is_array($layoutJson)) {
            $layout = json_encode($layoutJson, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($layoutJson) && $layoutJson !== '') {
            $layout = $layoutJson;
        }

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE training_certificate_templates SET
                    name = ?, headline = ?, subtitle = ?, footer_legal = ?,
                    primary_hex = ?, accent_hex = ?,
                    logo_relative_path = ?, background_relative_path = ?, layout_json = ?
                 WHERE tenant_id = ?'
            );
            $stmt->execute([
                $name, $headline, $subtitle ?: null, $footer ?: null,
                $primary, $accent,
                $logo ?: null, $bg ?: null, $layout,
                $tenantId,
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO training_certificate_templates
                (tenant_id, name, headline, subtitle, footer_legal, primary_hex, accent_hex, logo_relative_path, background_relative_path, layout_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId, $name, $headline, $subtitle ?: null, $footer ?: null,
            $primary, $accent, $logo ?: null, $bg ?: null, $layout,
        ]);
    }
}
