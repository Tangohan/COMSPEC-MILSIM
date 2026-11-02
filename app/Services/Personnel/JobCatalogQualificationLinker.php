<?php

declare(strict_types=1);

namespace App\Services\Personnel;

/**
 * « Copier tout » depuis le catalogue d’emplois, case « importer les qualifications liées et les ajouter comme
 * prérequis » cochée : les qualifications du catalogue citées par l’emploi copié sont importées dans le référentiel
 * (QualificationCatalogImporter, sans doublon) puis ajoutées aux prérequis de l’emploi enregistré (critère
 * {type: qualification, definition_id}, étape 3440), à la suite des prérequis existants, sans en retirer ni dépasser
 * la limite. Rien n’est imposé : un responsable peut ensuite retirer un prérequis ou accorder une dérogation.
 */
final class JobCatalogQualificationLinker
{
    public function __construct(private QualificationCatalogImporter $importer, private JobPrerequisiteService $prerequisites)
    {
    }

    /**
     * @param list<string> $refs ids du catalogue de qualifications
     * @return array{imported: int, existing: int, added: int}
     */
    public function apply(int $tenantId, int $roleId, array $refs, string $lang, int $actorId): array
    {
        $out = ['imported' => 0, 'existing' => 0, 'added' => 0];
        $refs = array_slice(array_values(array_unique(array_filter(array_map(static fn ($r): string => is_scalar($r) ? trim((string) $r) : '', $refs), static fn (string $r): bool => preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,60}$/', $r) === 1))), 0, JobPrerequisiteService::MAX_ITEMS);
        if ($tenantId < 1 || $roleId < 1 || $refs === []) {
            return $out;
        }
        $result = $this->importer->import($tenantId, $refs, $lang, $actorId > 0 ? $actorId : null);
        $out['imported'] = count($result['created']);
        $out['existing'] = count($result['skipped']);
        if (!$this->prerequisites->schemaReady()) {
            return $out;
        }
        $items = $this->prerequisites->requirements($tenantId, $roleId);
        $held = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'qualification') {
                $held[(int) $item['definition_id']] = true;
            }
        }
        foreach ($refs as $ref) {
            $definitionId = (int) ($result['definitions'][$ref] ?? 0);
            if ($definitionId < 1 || isset($held[$definitionId]) || count($items) >= JobPrerequisiteService::MAX_ITEMS) {
                continue;
            }
            $items[] = ['type' => 'qualification', 'definition_id' => $definitionId];
            $held[$definitionId] = true;
            $out['added']++;
        }
        if ($out['added'] > 0) {
            $this->prerequisites->saveRequirements($tenantId, $roleId, $items, $actorId);
        }

        return $out;
    }
}
