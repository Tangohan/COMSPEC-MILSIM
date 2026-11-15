<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier\Adapters;

use App\Services\Effectifs\Dossier\DossierCatalog;
use App\Services\Effectifs\Dossier\DossierStepAdapter;
use PDO;
use Throwable;

/**
 * Base des adaptateurs : lecture protégée (une table absente ou une colonne manquante donne « aucune étape », jamais
 * une page en erreur) et fabrication d'une étape complète à partir des seuls champs connus de la source.
 */
abstract class AbstractDossierAdapter implements DossierStepAdapter
{
    public function __construct(protected PDO $pdo)
    {
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function rows(string $sql, array $params): array
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param list<int> $ids */
    protected static function marks(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    /**
     * @param array<string, mixed> $f source_type, source_id, track, title, state requis ; le reste est facultatif
     * @return array<string, mixed>
     */
    protected function make(array $f): array
    {
        $type = (string) $f['source_type'];
        $id = (string) $f['source_id'];

        return [
            'key' => $type . ':' . $id,
            'source_type' => $type,
            'source_id' => $id,
            'track' => (string) $f['track'],
            'title' => (string) $f['title'],
            'description' => (string) ($f['description'] ?? ''),
            'state' => (string) $f['state'],
            'status' => '',
            'late' => false,
            'optional' => (bool) ($f['optional'] ?? false),
            'owner_kind' => DossierCatalog::owner((string) ($f['owner_kind'] ?? DossierCatalog::OWNER_OTHER)),
            'owner_user_id' => (int) ($f['owner_user_id'] ?? 0),
            'owner_label' => '',
            'opened_on' => DossierCatalog::day($f['opened_on'] ?? null),
            'due_on' => DossierCatalog::day($f['due_on'] ?? null),
            'done_at' => DossierCatalog::stamp($f['done_at'] ?? null),
            'updated_at' => DossierCatalog::stamp($f['updated_at'] ?? null),
            'source_label' => (string) ($f['source_label'] ?? ''),
            'source_url' => (string) ($f['source_url'] ?? ''),
            'note' => '',
            'overridden' => ['due' => false, 'owner' => false],
            'original_due_on' => null,
            'editable' => false,
            'actions' => [],
            'locked_reason' => '',
            'pos' => (int) ($f['pos'] ?? 0),
        ];
    }
}
