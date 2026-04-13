<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TenantEmailTemplateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableExists(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_email_templates' LIMIT 1");
            $ok = $st && (bool) $st->fetchColumn();
        }

        return $ok;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, ?string $kind = null): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        if ($kind !== null && $kind !== '') {
            $st = $this->pdo->prepare(
                'SELECT * FROM tenant_email_templates WHERE tenant_id = ? AND kind = ? ORDER BY is_prefab DESC, name ASC'
            );
            $st->execute([$tenantId, $kind]);
        } else {
            $st = $this->pdo->prepare(
                'SELECT * FROM tenant_email_templates WHERE tenant_id = ? ORDER BY kind ASC, is_prefab DESC, name ASC'
            );
            $st->execute([$tenantId]);
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM tenant_email_templates WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{name: string, kind: string, subject: string, body_html: string, body_text?: ?string, is_prefab?: bool} $data
     */
    public function create(int $tenantId, int $createdBy, array $data): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_email_templates (tenant_id, kind, name, subject, body_html, body_text, is_prefab, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([
            $tenantId,
            $data['kind'],
            $data['name'],
            $data['subject'],
            $data['body_html'],
            $data['body_text'] ?? null,
            !empty($data['is_prefab']) ? 1 : 0,
            $createdBy > 0 ? $createdBy : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{name?: string, subject?: string, body_html?: string, body_text?: ?string, kind?: string} $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $sets = [];
        $params = [];
        foreach (['name', 'subject', 'body_html', 'body_text', 'kind'] as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = '`' . $k . '` = ?';
                $params[] = $data[$k];
            }
        }
        if ($sets === []) {
            return true;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $sql = 'UPDATE tenant_email_templates SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ? LIMIT 1';

        return $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id, int $tenantId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM tenant_email_templates WHERE id = ? AND tenant_id = ? AND is_prefab = 0 LIMIT 1');

        return $st->execute([$id, $tenantId]) && $st->rowCount() > 0;
    }

    /** Textes d’aide fournis une fois par tenant (idempotent). */
    public function ensurePrefabsForTenant(int $tenantId, int $createdBy): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $chk = $this->pdo->prepare('SELECT 1 FROM tenant_email_templates WHERE tenant_id = ? AND is_prefab = 1 LIMIT 1');
        $chk->execute([$tenantId]);
        if ($chk->fetchColumn()) {
            return;
        }
        $samples = [
            [
                'kind' => 'orbat',
                'name' => 'Information structure',
                'subject' => 'Information — {{user.full_name}}',
                'body_html' => '<p>Bonjour {{user.first_name}},</p><p>Message concernant votre affectation ({{unit.name}}).</p><p>Cordialement,<br>L’encadrement</p>',
            ],
            [
                'kind' => 'mission',
                'name' => 'Point opérationnel',
                'subject' => 'Point opérationnel — {{unit.name}}',
                'body_html' => '<p>{{user.full_name}},</p><p>Voici les éléments importants pour la période à venir.</p><p>Merci de prendre connaissance et d’accuser réception si demandé.</p>',
            ],
            [
                'kind' => 'activity',
                'name' => 'Rappel activité',
                'subject' => 'Rappel — activité à venir',
                'body_html' => '<p>Bonjour {{user.first_name}},</p><p>Rappel concernant une activité à laquelle vous êtes attendu(e).</p><p>Consultez l’espace membre pour le détail.</p>',
            ],
            [
                'kind' => 'custom',
                'name' => 'Message général',
                'subject' => 'Message aux membres',
                'body_html' => '<p>Bonjour {{user.first_name}},</p><p></p><p>Cordialement</p>',
            ],
        ];
        foreach ($samples as $s) {
            $this->create($tenantId, $createdBy, [
                'kind' => $s['kind'],
                'name' => $s['name'],
                'subject' => $s['subject'],
                'body_html' => $s['body_html'],
                'is_prefab' => true,
            ]);
        }
    }
}
