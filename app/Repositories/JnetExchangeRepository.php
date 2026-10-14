<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Échanges JNET entre espaces (organisation, unités, équipes) et accusés de lecture.
 * Tolère l'absence des tables (migration non passée) : lectures vides, écritures refusées.
 */
class JnetExchangeRepository
{
    private ?PDO $pdo = null;
    private ?bool $ready = null;
    private ?bool $sourceReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $this->pdo()->query('SELECT 1 FROM jnet_exchanges LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM jnet_exchange_targets LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM jnet_exchange_reads LIMIT 1');
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /** Colonnes d'origine (reco, photos, rapports du jeu) présentes ? */
    public function sourceColumnsReady(): bool
    {
        if ($this->sourceReady !== null) {
            return $this->sourceReady;
        }
        if (!$this->schemaReady()) {
            return $this->sourceReady = false;
        }
        try {
            $this->pdo()->query('SELECT source_kind, source_ref, image_url FROM jnet_exchanges LIMIT 1');
            $this->sourceReady = true;
        } catch (\Throwable) {
            $this->sourceReady = false;
        }

        return $this->sourceReady;
    }

    /**
     * Crée ou met à jour l'échange lié à une donnée de terrain (une reco modifiée en jeu garde le même échange).
     * Retourne l'identifiant de l'échange, ou 0 si les colonnes d'origine ne sont pas encore en place.
     *
     * @param array{from_unit_id:int, author_user_id:int, kind:string, title:string, body:string,
     *              link_url:?string, image_url:?string, requires_ack:bool, targets:list<int>} $data
     */
    public function upsertFromSource(int $tenantId, string $sourceKind, string $sourceRef, array $data): int
    {
        if (!$this->sourceColumnsReady() || $sourceKind === '' || $sourceRef === '') {
            return 0;
        }
        $pdo = $this->pdo();
        $st = $pdo->prepare('SELECT id FROM jnet_exchanges WHERE tenant_id = ? AND source_kind = ? AND source_ref = ? LIMIT 1');
        $st->execute([$tenantId, $sourceKind, $sourceRef]);
        $existing = (int) ($st->fetchColumn() ?: 0);
        if ($existing === 0) {
            $id = $this->create(
                $tenantId,
                $data['from_unit_id'],
                $data['author_user_id'],
                $data['kind'],
                $data['title'],
                $data['body'],
                $data['link_url'],
                $data['requires_ack'],
                $data['targets']
            );
            $pdo->prepare('UPDATE jnet_exchanges SET source_kind = ?, source_ref = ?, image_url = ? WHERE id = ?')
                ->execute([$sourceKind, $sourceRef, $data['image_url'], $id]);

            return $id;
        }
        // Mise à jour du contenu seulement : destinataires et accusés de lecture restent ceux du premier envoi.
        $pdo->prepare('UPDATE jnet_exchanges SET kind = ?, title = ?, body = ?, link_url = ?, image_url = ? WHERE id = ?')
            ->execute([
                $data['kind'],
                $data['title'],
                $data['body'] !== '' ? $data['body'] : null,
                $data['link_url'],
                $data['image_url'],
                $existing,
            ]);

        return $existing;
    }

    /**
     * @param list<int> $targetUnitIds
     */
    public function create(
        int $tenantId,
        int $fromUnitId,
        int $authorUserId,
        string $kind,
        string $title,
        string $body,
        ?string $linkUrl,
        bool $requiresAck,
        array $targetUnitIds
    ): int {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Échanges JNET indisponibles : migration jnet_exchanges non appliquée.');
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'INSERT INTO jnet_exchanges (tenant_id, from_unit_id, author_user_id, kind, title, body, link_url, requires_ack)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $tenantId,
                max(0, $fromUnitId),
                $authorUserId,
                $kind,
                $title,
                $body !== '' ? $body : null,
                $linkUrl,
                $requiresAck ? 1 : 0,
            ]);
            $id = (int) $pdo->lastInsertId();
            $tst = $pdo->prepare('INSERT INTO jnet_exchange_targets (exchange_id, unit_id) VALUES (?, ?)');
            foreach (array_values(array_unique(array_map('intval', $targetUnitIds))) as $unitId) {
                $tst->execute([$id, max(0, $unitId)]);
            }
            // L'auteur a forcément lu ce qu'il publie.
            $pdo->prepare('INSERT INTO jnet_exchange_reads (exchange_id, user_id) VALUES (?, ?)')->execute([$id, $authorUserId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    /**
     * Derniers échanges du tenant, avec leurs destinataires. Le filtrage par lecteur se fait en PHP
     * (règles d'arbre ORBAT), d'où une fenêtre volontairement plus large que l'affichage.
     *
     * @return list<array<string, mixed>>
     */
    public function recentForTenant(int $tenantId, int $limit = 200): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $st = $this->pdo()->prepare(
            'SELECT e.*, u.display_name AS author_display_name, u.callsign AS author_callsign
             FROM jnet_exchanges e
             LEFT JOIN users u ON u.id = e.author_user_id
             WHERE e.tenant_id = ? AND e.deleted_at IS NULL
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $targets = $this->targetsFor($ids);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['from_unit_id'] = (int) $r['from_unit_id'];
            $r['author_user_id'] = (int) $r['author_user_id'];
            $r['requires_ack'] = (int) $r['requires_ack'] === 1;
            $r['targets'] = $targets[$r['id']] ?? [];
        }
        unset($r);

        return $rows;
    }

    /** @param list<int> $exchangeIds @return array<int, list<int>> */
    private function targetsFor(array $exchangeIds): array
    {
        if ($exchangeIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($exchangeIds), '?'));
        $st = $this->pdo()->prepare("SELECT exchange_id, unit_id FROM jnet_exchange_targets WHERE exchange_id IN ($in)");
        $st->execute($exchangeIds);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['exchange_id']][] = (int) $row['unit_id'];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $exchangeId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT id, from_unit_id, author_user_id, kind, title, requires_ack FROM jnet_exchanges
             WHERE tenant_id = ? AND id = ? AND deleted_at IS NULL'
        );
        $st->execute([$tenantId, $exchangeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['from_unit_id'] = (int) $row['from_unit_id'];
        $row['targets'] = $this->targetsFor([(int) $row['id']])[(int) $row['id']] ?? [];

        return $row;
    }

    public function markRead(int $exchangeId, int $userId): void
    {
        if (!$this->schemaReady()) {
            return;
        }
        try {
            $this->pdo()->prepare('INSERT INTO jnet_exchange_reads (exchange_id, user_id) VALUES (?, ?)')->execute([$exchangeId, $userId]);
        } catch (\PDOException) {
            // Déjà lu (clé primaire) : rien à faire.
        }
    }

    /**
     * Lecteurs par échange.
     *
     * @param list<int> $exchangeIds
     * @return array<int, list<int>>
     */
    public function readersFor(array $exchangeIds): array
    {
        if ($exchangeIds === [] || !$this->schemaReady()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($exchangeIds), '?'));
        $st = $this->pdo()->prepare("SELECT exchange_id, user_id FROM jnet_exchange_reads WHERE exchange_id IN ($in)");
        $st->execute(array_values($exchangeIds));
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['exchange_id']][] = (int) $row['user_id'];
        }

        return $out;
    }
}
