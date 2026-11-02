<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\QualificationCatalog\QualificationCatalog;

/**
 * Import de qualifications du catalogue de référence (config/qualification_catalog : un pack par pays et l’OTAN)
 * dans le référentiel d’une communauté. Chaque qualification choisie devient une fiche du référentiel (code du
 * catalogue, nom et description dans la langue de la personne qui importe, intitulé officiel, mention du pack
 * « qualification de jeu inspirée de …, sans valeur réelle »), rattachée à son émetteur (l’émetteur réel du catalogue
 * des émetteurs si l’entrée a une clé issuer_key, sinon l’école citée par l’entrée, sinon l’émetteur de référence du
 * pack ; créé si absent, comme depuis la page Émetteurs) et à la catégorie du
 * catalogue. Sans doublon : un code déjà présent dans la communauté est ignoré, une relance ne crée rien. La fiche
 * importée se modifie ensuite librement comme toute autre qualification.
 */
final class QualificationCatalogImporter
{
    private QualificationIssuerCatalogImporter $issuers;

    public function __construct(private QualificationReferenceWriter $writer, ?QualificationIssuerCatalogImporter $issuers = null)
    {
        $this->issuers = $issuers ?? new QualificationIssuerCatalogImporter($writer);
    }

    public function ready(): bool
    {
        return $this->writer->has('personnel_qualification_definitions');
    }

    /** @return array<string, true> codes déjà présents dans le référentiel de la communauté */
    public function importedCodes(int $tenantId): array
    {
        return $this->ready() ? $this->writer->existingCodes($tenantId) : [];
    }

    /** @return array<string, int> code du catalogue (majuscules) => fiche du référentiel de la communauté */
    public function importedDefinitions(int $tenantId): array
    {
        return $this->ready() ? $this->writer->definitionIdsByCode($tenantId) : [];
    }

    /**
     * @param list<string> $ids identifiants d’entrées du catalogue (tous packs)
     * @param int|null $maxCreate plafond de fiches à créer (formule de la communauté) ; null = sans limite. Au-delà,
     *                            les entrées vont dans blocked (rien n’est créé pour elles).
     * @return array{created: list<string>, skipped: list<string>, unknown: int, issuer_ids: list<int>, definitions: array<string, int>, blocked: list<string>}
     *         definitions : id du catalogue => fiche du référentiel (créée ou déjà présente)
     */
    public function import(int $tenantId, array $ids, string $lang, ?int $actorId, ?int $maxCreate = null): array
    {
        $out = ['created' => [], 'skipped' => [], 'unknown' => 0, 'issuer_ids' => [], 'definitions' => [], 'blocked' => []];
        if ($tenantId < 1 || !$this->ready()) {
            return $out;
        }
        $lang = QualificationCatalog::lang($lang);
        $entries = [];
        foreach (array_values(array_unique(array_map('strval', $ids))) as $id) {
            $entry = QualificationCatalog::findRaw($id);
            if ($entry !== null && QualificationCatalog::problems($entry) === []) {
                $entries[] = $entry;
            } else {
                $out['unknown']++;
            }
        }
        if ($entries === []) {
            return $out;
        }
        $pdo = $this->writer->pdo();
        $pdo->beginTransaction();
        try {
            $existing = $this->writer->existingCodes($tenantId);
            $issuers = [];
            $categories = [];
            foreach ($entries as $entry) {
                $code = strtoupper((string) $entry['code']);
                $name = QualificationCatalog::pick($entry['name'], $lang);
                if (isset($existing[$code])) {
                    $out['skipped'][] = $name;
                    $out['definitions'][(string) $entry['id']] = $this->writer->definitionIdCaseless($tenantId, $code);
                    continue;
                }
                if ($maxCreate !== null && count($out['created']) >= $maxCreate) {
                    $out['blocked'][] = $name;
                    continue;
                }
                $issuer = QualificationCatalog::issuerOf($entry, $lang);
                // Émetteur réel du catalogue (clé issuer_key) : retrouvé par nom, alias ou sigle, sinon créé.
                $issuerId = $issuers[$issuer['name']] ??= isset($issuer['key'])
                    ? $this->issuers->ensure($tenantId, $issuer['key'], $lang, $actorId)
                    : $this->writer->ensureIssuer($tenantId, $issuer['name'], $issuer['short_name'], $issuer['kind'], $issuer['description'], $actorId);
                $country = (string) (QualificationCatalog::pack((string) $entry['pack'])['country'] ?? '');
                $category = (string) $entry['category'];
                $categoryName = QualificationCatalog::countryLabel($country, $lang) . ' — ' . QualificationCatalog::categoryLabel($category, $lang);
                $categories[$categoryName] ??= $this->writer->ensureCategory($tenantId, $categoryName);
                $months = $entry['validity_months'] ?? null;
                $out['definitions'][(string) $entry['id']] = $this->writer->createDefinition($tenantId, [
                    'code' => $code,
                    'name' => $name,
                    'short_name' => (string) ($entry['short_name'] ?? ''),
                    'description' => QualificationCatalog::definitionDescription($entry, $lang),
                    'category_id' => $categories[$categoryName],
                    'issuer_id' => $issuerId,
                    'validity_months' => is_int($months) && $months > 0 ? $months : null,
                ], $actorId);
                $existing[$code] = true;
                $out['created'][] = $name;
            }
            $out['issuer_ids'] = array_values(array_unique(array_filter(array_map('intval', $issuers))));
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $out;
    }
}
