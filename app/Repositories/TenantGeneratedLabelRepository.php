<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Libellés générés remplacés par une communauté (table tenant_generated_labels, étape
 * database/steps/3290_tenant_generated_labels.php). Une ligne par (communauté, clé, langue).
 */
class TenantGeneratedLabelRepository
{
    private const TABLE = 'tenant_generated_labels';

    private ?bool $exists = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function tableExists(): bool
    {
        if ($this->exists !== null) {
            return $this->exists;
        }
        try {
            $this->pdo()->query('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');
            $this->exists = true;
        } catch (Throwable) {
            $this->exists = false;
        }

        return $this->exists;
    }

    /**
     * @return array<string, array<string, string>> clé => [langue => libellé]
     */
    public function allForTenant(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableExists()) {
            return [];
        }
        $st = $this->pdo()->prepare('SELECT label_key, lang, label FROM ' . self::TABLE . ' WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) $row['label_key']][(string) $row['lang']] = (string) $row['label'];
        }

        return $out;
    }

    /** Enregistre (ou remplace) un libellé ; un texte vide supprime la ligne (retour au libellé par défaut). */
    public function put(int $tenantId, string $key, string $lang, string $label, ?int $userId, string $now): void
    {
        if ($tenantId < 1 || !$this->tableExists()) {
            return;
        }
        $label = trim($label);
        if ($label === '') {
            $this->delete($tenantId, $key, $lang);

            return;
        }
        $st = $this->pdo()->prepare('SELECT id FROM ' . self::TABLE . ' WHERE tenant_id = ? AND label_key = ? AND lang = ?');
        $st->execute([$tenantId, $key, $lang]);
        $id = $st->fetchColumn();
        if ($id !== false) {
            $this->pdo()->prepare('UPDATE ' . self::TABLE . ' SET label = ?, updated_by = ?, updated_at = ? WHERE id = ?')
                ->execute([$label, $userId, $now, (int) $id]);

            return;
        }
        $this->pdo()->prepare(
            'INSERT INTO ' . self::TABLE . ' (tenant_id, label_key, lang, label, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $key, $lang, $label, $userId, $now]);
    }

    /**
     * Unités de la communauté (adresse courte => nom), pour reconnaître un nom de modèle affiché sans son adresse.
     *
     * @return array<string, string>|null null si la lecture est impossible
     */
    public function unitNamesBySlug(int $tenantId): ?array
    {
        try {
            $st = $this->pdo()->prepare('SELECT slug, name FROM units WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $out = [];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug !== '') {
                    $out[$slug] = trim((string) ($row['name'] ?? ''));
                }
            }

            return $out;
        } catch (Throwable) {
            return null;
        }
    }

    public function delete(int $tenantId, string $key, ?string $lang = null): void
    {
        if ($tenantId < 1 || !$this->tableExists()) {
            return;
        }
        if ($lang === null) {
            $this->pdo()->prepare('DELETE FROM ' . self::TABLE . ' WHERE tenant_id = ? AND label_key = ?')->execute([$tenantId, $key]);

            return;
        }
        $this->pdo()->prepare('DELETE FROM ' . self::TABLE . ' WHERE tenant_id = ? AND label_key = ? AND lang = ?')
            ->execute([$tenantId, $key, $lang]);
    }
}
