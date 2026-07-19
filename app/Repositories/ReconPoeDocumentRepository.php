<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Plan d'Exécution (PoE) rédigé par le Mission Maker à partir d'une session de transmission
 * de renseignement — un document par session (5 rubriques façon ordre d'opération).
 */
class ReconPoeDocumentRepository
{
    private PDO $pdo;

    /** @var list<string> */
    public const SECTIONS = ['situation', 'mission', 'execution', 'soutien', 'commandement'];

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findBySessionId(int $sessionId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recon_poe_documents WHERE session_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$sessionId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Crée le PoE de la session s'il n'existe pas, sinon le met à jour.
     *
     * @param array<string, string> $sections clés parmi SECTIONS
     */
    public function upsert(int $tenantId, int $sessionId, string $title, array $sections, int $actorUserId): int
    {
        $existing = $this->findBySessionId($sessionId, $tenantId);
        $values = [];
        foreach (self::SECTIONS as $key) {
            $values[$key] = trim((string) ($sections[$key] ?? ''));
        }

        if ($existing === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO recon_poe_documents (
                    tenant_id, session_id, title,
                    section_situation, section_mission, section_execution, section_soutien, section_commandement,
                    status, created_by, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'draft\', ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                $sessionId,
                $title,
                $values['situation'],
                $values['mission'],
                $values['execution'],
                $values['soutien'],
                $values['commandement'],
                $actorUserId,
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        $id = (int) $existing['id'];
        $stmt = $this->pdo->prepare(
            'UPDATE recon_poe_documents
             SET title = ?, section_situation = ?, section_mission = ?, section_execution = ?,
                 section_soutien = ?, section_commandement = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            $title,
            $values['situation'],
            $values['mission'],
            $values['execution'],
            $values['soutien'],
            $values['commandement'],
            $actorUserId,
            $id,
            $tenantId,
        ]);

        return $id;
    }

    public function publish(int $sessionId, int $tenantId, int $actorUserId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE recon_poe_documents SET status = 'published', updated_by = ?, updated_at = NOW()
             WHERE session_id = ? AND tenant_id = ?"
        );
        $stmt->execute([$actorUserId, $sessionId, $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
