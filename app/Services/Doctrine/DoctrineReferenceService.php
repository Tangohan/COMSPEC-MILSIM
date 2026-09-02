<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\Doctrine\DocumentReferenceDomainRepository;
use App\Repositories\UnitRepository;

final class DoctrineReferenceService
{
    public function __construct(
        private DocumentReferenceDomainRepository $domainRepository,
        private DocumentDoctrineRepository $doctrineRepository,
        private UnitRepository $unitRepository,
    ) {}

    /**
     * Génère une référence structurée [SERVICE]/[DOMAINE]/[ANNÉE]-[NUMÉRO].
     */
    public function generateReference(
        int $tenantId,
        int $domainId,
        ?int $subdomainId,
        ?int $issuingUnitId,
        ?int $year = null,
    ): array {
        $year = $year ?? (int) date('Y');
        $domain = $this->domainRepository->findById($domainId, $tenantId);
        if ($domain === null) {
            throw new \InvalidArgumentException('Domaine documentaire invalide.');
        }

        $servicePrefix = (string) ($domain['doc_prefix'] ?? $domain['code'] ?? 'DOC');
        if ($issuingUnitId !== null && $issuingUnitId > 0) {
            $unit = $this->unitRepository->findById($issuingUnitId, $tenantId);
            if ($unit !== null) {
                $unitPrefix = trim((string) ($unit['doc_prefix'] ?? $unit['orbat_doc_prefix'] ?? ''));
                if ($unitPrefix !== '') {
                    $servicePrefix = strtoupper($unitPrefix);
                }
            }
        }

        $domainCode = 'GEN';
        if ($subdomainId !== null && $subdomainId > 0) {
            $subs = $this->domainRepository->listSubdomainsForDomain($tenantId, $domainId);
            foreach ($subs as $sub) {
                if ((int) ($sub['id'] ?? 0) === $subdomainId) {
                    $domainCode = (string) ($sub['code'] ?? 'GEN');
                    break;
                }
            }
        } else {
            $domainCode = (string) ($domain['code'] ?? 'GEN');
        }

        $seq = $this->doctrineRepository->nextSequenceNumber($tenantId, $servicePrefix, $domainCode, $year);
        $reference = sprintf('%s/%s/%d-%03d', $servicePrefix, $domainCode, $year, $seq);

        return [
            'reference_code' => $reference,
            'service_prefix' => $servicePrefix,
            'domain_code' => $domainCode,
            'seq_year' => $year,
            'seq_number' => $seq,
        ];
    }

    public function formatVersionLabel(?int $major, ?int $minor, ?string $fallback = null): string
    {
        if ($major !== null && $minor !== null) {
            return 'v' . $major . '.' . $minor;
        }
        if ($fallback !== null && trim($fallback) !== '') {
            return trim($fallback);
        }

        return 'v1.0';
    }
}
