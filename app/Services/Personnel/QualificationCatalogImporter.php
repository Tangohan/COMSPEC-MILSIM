<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\QualificationCatalog\QualificationCatalog;

/**
 * Import de qualifications du catalogue de référence (config/qualification_catalog, aujourd’hui « OTAN ») dans le
 * référentiel d’une communauté. Chaque qualification choisie devient une fiche du référentiel (code OTAN-…, nom et
 * description dans la langue de la personne qui importe, mention « qualification de jeu inspirée du standard OTAN,
 * sans valeur réelle »), rattachée à l’émetteur « OTAN (référence) » (créé si absent, comme depuis la page
 * Émetteurs) et à la catégorie du catalogue. Sans doublon : un code déjà présent dans la communauté est ignoré, une
 * relance ne crée rien. La fiche importée se modifie ensuite librement comme toute autre qualification.
 */
final class QualificationCatalogImporter
{
    public function __construct(private QualificationReferenceWriter $writer)
    {
    }

    public function ready(): bool
    {
        return $this->writer->has('personnel_qualification_definitions');
    }

    /** @return array<string, true> codes du catalogue déjà présents dans la communauté */
    public function importedCodes(int $tenantId): array
    {
        return $this->ready() ? $this->writer->existingCodes($tenantId) : [];
    }

    /**
     * @param list<string> $ids identifiants d’entrées du catalogue
     * @return array{created: list<string>, skipped: list<string>, unknown: int, issuer_id: ?int}
     */
    public function import(int $tenantId, string $pack, array $ids, string $lang, ?int $actorId): array
    {
        $out = ['created' => [], 'skipped' => [], 'unknown' => 0, 'issuer_id' => null];
        $data = QualificationCatalog::pack($pack);
        if ($tenantId < 1 || $data === null || !$this->ready()) {
            return $out;
        }
        $lang = QualificationCatalog::lang($lang);
        $wanted = array_fill_keys(array_map('strval', $ids), true);
        $entries = [];
        foreach (QualificationCatalog::rawEntries($pack) as $entry) {
            if (isset($wanted[(string) ($entry['id'] ?? '')]) && QualificationCatalog::problems($entry) === []) {
                $entries[] = $entry;
                unset($wanted[(string) $entry['id']]);
            }
        }
        $out['unknown'] = count($wanted);
        if ($entries === []) {
            return $out;
        }
        $pdo = $this->writer->pdo();
        $pdo->beginTransaction();
        try {
            $existing = $this->writer->existingCodes($tenantId);
            $issuer = is_array($data['issuer'] ?? null) ? $data['issuer'] : [];
            $issuerId = null;
            $categories = [];
            foreach ($entries as $entry) {
                $code = strtoupper((string) $entry['code']);
                $name = QualificationCatalog::pick($entry['name'], $lang);
                if (isset($existing[$code])) {
                    $out['skipped'][] = $name;
                    continue;
                }
                $issuerId ??= $this->writer->ensureIssuer(
                    $tenantId,
                    trim((string) ($issuer['name'] ?? 'OTAN (référence)')),
                    trim((string) ($issuer['short_name'] ?? 'OTAN')),
                    'external',
                    QualificationCatalog::pick($issuer['description'] ?? '', $lang),
                    $actorId
                );
                $category = (string) $entry['category'];
                $categories[$category] ??= $this->writer->ensureCategory($tenantId, (QualificationCatalog::lang($lang) === 'en' ? 'NATO — ' : 'OTAN — ') . QualificationCatalog::categoryLabel($category, $lang));
                $months = $entry['validity_months'] ?? null;
                $this->writer->createDefinition($tenantId, [
                    'code' => $code,
                    'name' => $name,
                    'short_name' => (string) ($entry['short_name'] ?? ''),
                    'description' => QualificationCatalog::definitionDescription($entry, $lang),
                    'category_id' => $categories[$category],
                    'issuer_id' => $issuerId,
                    'validity_months' => is_int($months) && $months > 0 ? $months : null,
                ], $actorId);
                $existing[$code] = true;
                $out['created'][] = $name;
            }
            $out['issuer_id'] = $issuerId;
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $out;
    }
}
