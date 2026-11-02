<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\QualificationCatalog\QualificationIssuerCatalog;

/**
 * Import d’émetteurs réels (config/qualification_issuers) dans le référentiel d’une communauté : page Émetteurs
 * (« Importer les émetteurs réels », par pays) et import d’une qualification du catalogue de référence (son émetteur
 * réel suit). Un émetteur déjà présent (même nom, alias ou sigle) est réutilisé tel quel, jamais modifié ; une relance
 * ne crée rien.
 */
final class QualificationIssuerCatalogImporter
{
    /** @var array<int, list<array<string, mixed>>> émetteurs de la communauté lus pendant l’import */
    private array $tenantIssuers = [];

    public function __construct(private QualificationReferenceWriter $writer)
    {
    }

    public function ready(): bool
    {
        return $this->writer->has('qualification_issuers');
    }

    /** @return list<array<string, mixed>> */
    private function tenantIssuers(int $tenantId): array
    {
        if (!isset($this->tenantIssuers[$tenantId])) {
            $key = $this->writer->has('qualification_issuers', 'issuer_key') ? 'issuer_key' : 'NULL AS issuer_key';
            $st = $this->writer->pdo()->prepare('SELECT id, name, short_name, ' . $key . ' FROM qualification_issuers WHERE tenant_id = ? ORDER BY id');
            $st->execute([$tenantId]);
            $this->tenantIssuers[$tenantId] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        return $this->tenantIssuers[$tenantId];
    }

    /** @return array<string, int> clé du catalogue => émetteur de la communauté déjà présent */
    public function matched(int $tenantId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $rows = $this->tenantIssuers($tenantId);
        $out = [];
        foreach (QualificationIssuerCatalog::all() as $key => $issuer) {
            $id = self::byKey((string) $key, $rows) ?: QualificationIssuerCatalog::matchTenant($issuer, $rows);
            if ($id > 0) {
                $out[$key] = $id;
            }
        }

        return $out;
    }

    /**
     * Émetteur de la communauté pour une clé du catalogue : celui qui correspond, sinon créé. Null : clé inconnue ou
     * pas de table des émetteurs.
     *
     * @param bool $created vrai si l’émetteur vient d’être créé
     */
    public function ensure(int $tenantId, string $key, string $lang, ?int $actorId, bool &$created = false): ?int
    {
        $created = false;
        $issuer = QualificationIssuerCatalog::find($key);
        if ($issuer === null || !$this->ready()) {
            return null;
        }
        $rows = $this->tenantIssuers($tenantId);
        $id = self::byKey($key, $rows) ?: QualificationIssuerCatalog::matchTenant($issuer, $rows);
        if ($id > 0) {
            return $id;
        }
        $fields = $this->writer->has('qualification_issuers', 'country_code');
        $l = QualificationIssuerCatalog::localize($issuer, $lang);
        $id = $this->writer->ensureIssuer(
            $tenantId,
            (string) $issuer['name'],
            (string) ($issuer['short_name'] ?? ''),
            QualificationIssuerCatalog::kindOf($issuer),
            QualificationIssuerCatalog::description($issuer, $lang, !$fields),
            $actorId,
            $fields ? [
                'issuer_key' => $key,
                'issuer_type' => (string) ($issuer['type'] ?? ''),
                'country_code' => (string) ($issuer['country'] ?? ''),
                'location' => mb_substr($l['location'], 0, 200),
                'website_url' => $l['website'],
                'description_en' => QualificationIssuerCatalog::localize($issuer, 'en')['description'],
            ] : []
        );
        if ($id !== null) {
            $created = true;
            $this->tenantIssuers[$tenantId][] = ['id' => $id, 'name' => (string) $issuer['name'], 'short_name' => (string) ($issuer['short_name'] ?? ''), 'issuer_key' => $key];
        }

        return $id;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function byKey(string $key, array $rows): int
    {
        foreach ($rows as $row) {
            if ((string) ($row['issuer_key'] ?? '') === $key) {
                return (int) $row['id'];
            }
        }

        return 0;
    }

    /**
     * @param list<string> $keys clés du catalogue d’émetteurs
     * @param int|null $maxCreate plafond d’émetteurs à créer (formule) ; null = sans limite ; au-delà : blocked
     * @return array{created: list<string>, skipped: list<string>, unknown: int, blocked: list<string>}
     */
    public function import(int $tenantId, array $keys, string $lang, ?int $actorId, ?int $maxCreate = null): array
    {
        $out = ['created' => [], 'skipped' => [], 'unknown' => 0, 'blocked' => []];
        if ($tenantId < 1 || !$this->ready()) {
            return $out;
        }
        $pdo = $this->writer->pdo();
        $pdo->beginTransaction();
        try {
            foreach (array_values(array_unique(array_map('strval', $keys))) as $key) {
                $issuer = QualificationIssuerCatalog::find($key);
                if ($issuer === null || QualificationIssuerCatalog::problems($issuer) !== []) {
                    $out['unknown']++;
                    continue;
                }
                if ($maxCreate !== null && count($out['created']) >= $maxCreate
                    && QualificationIssuerCatalog::matchTenant($issuer, $this->tenantIssuers($tenantId)) === 0) {
                    $out['blocked'][] = (string) $issuer['name'];
                    continue;
                }
                $created = false;
                $id = $this->ensure($tenantId, $key, $lang, $actorId, $created);
                if ($id === null) {
                    $out['unknown']++;
                } elseif ($created) {
                    $out['created'][] = (string) $issuer['name'];
                } else {
                    $out['skipped'][] = (string) $issuer['name'];
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            unset($this->tenantIssuers[$tenantId]);
            throw $e;
        }

        return $out;
    }
}
