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

    /**
     * Bouton « Rattacher à la fonction » d’un brevet, d’une formation ou d’une doctrine citée par un emploi du catalogue
     * (clés « qualification:<id> » et « doctrine:<clé> », JobCatalog::REFERENCE_KEY) : qualifications importées à la volée
     * et ajoutées aux prérequis comme apply() ; publications du référentiel doctrinal ajoutées en critère doctrine_ref
     * (rien à importer : le référentiel du site est commun à toutes les communautés).
     *
     * @param list<string> $keys
     * @return array{imported: int, existing: int, added: int}
     */
    public function applyReferences(int $tenantId, int $roleId, array $keys, string $lang, int $actorId): array
    {
        $qualifications = [];
        $doctrines = [];
        foreach ($keys as $key) {
            $key = is_scalar($key) ? trim((string) $key) : '';
            if (preg_match(\App\Support\JobCatalog\JobCatalog::REFERENCE_KEY, $key) !== 1) {
                continue;
            }
            if (str_starts_with($key, 'qualification:')) {
                $qualifications[] = substr($key, 14);
            } elseif (\App\Services\Doctrine\DoctrineLibraryCatalog::find(substr($key, 9)) !== null) {
                $doctrines[] = substr($key, 9);
            }
        }
        $out = $qualifications !== [] ? $this->apply($tenantId, $roleId, $qualifications, $lang, $actorId) : ['imported' => 0, 'existing' => 0, 'added' => 0];
        if ($doctrines === [] || $tenantId < 1 || $roleId < 1 || !$this->prerequisites->schemaReady()) {
            return $out;
        }
        $items = $this->prerequisites->requirements($tenantId, $roleId);
        $held = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'doctrine_ref') {
                $held[(string) $item['key']] = true;
            }
        }
        $added = 0;
        foreach (array_unique($doctrines) as $key) {
            if (isset($held[$key]) || count($items) >= JobPrerequisiteService::MAX_ITEMS) {
                continue;
            }
            $items[] = ['type' => 'doctrine_ref', 'key' => $key, 'mode' => 'read'];
            $added++;
        }
        if ($added > 0) {
            $this->prerequisites->saveRequirements($tenantId, $roleId, $items, $actorId);
            $out['added'] += $added;
        }

        return $out;
    }
}
