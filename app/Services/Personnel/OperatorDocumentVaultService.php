<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Support\PersonnelHrDocumentStorage;

/**
 * Agrège les pièces personnelles accessibles à l’opérateur dans un coffre unique.
 */
final class OperatorDocumentVaultService
{
    public function __construct(
        private ?PersonnelHrDocumentRepository $hrDocuments = null,
        private ?QualificationAwardRepository $awards = null,
        private ?TrainingCertificateRepository $trainingCertificates = null,
    ) {
        $this->hrDocuments ??= new PersonnelHrDocumentRepository();
        $this->awards ??= new QualificationAwardRepository();
        $this->trainingCertificates ??= new TrainingCertificateRepository();
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   counts: array{total:int, hr:int, brevet:int, training:int},
     *   sections: array{hr:list<array<string,mixed>>, brevet:list<array<string,mixed>>, training:list<array<string,mixed>>}
     * }
     */
    public function collect(int $tenantId, int $userId): array
    {
        $hr = $this->collectHr($tenantId, $userId);
        $brevets = $this->collectBrevets($tenantId, $userId);
        $training = $this->collectTraining($tenantId, $userId);
        $items = array_merge($hr, $brevets, $training);
        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['sort_at'] ?? ''), (string) ($a['sort_at'] ?? ''));
        });

        return [
            'items' => $items,
            'counts' => [
                'total' => count($items),
                'hr' => count($hr),
                'brevet' => count($brevets),
                'training' => count($training),
            ],
            'sections' => [
                'hr' => $hr,
                'brevet' => $brevets,
                'training' => $training,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectHr(int $tenantId, int $userId): array
    {
        $out = [];
        try {
            if (!$this->hrDocuments->tableExists()) {
                return [];
            }
            $rows = $this->hrDocuments->listForUser($tenantId, $userId, false, true);
        } catch (\Throwable) {
            return [];
        }
        $labels = PersonnelHrDocumentRepository::DOC_TYPE_LABELS;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $path = trim((string) ($row['file_path'] ?? ''));
            $downloadUrl = null;
            if ($path !== '' && PersonnelHrDocumentStorage::isStoredPath($path)) {
                $downloadUrl = url('personnel/mon-espace-rh/documents/' . (int) ($row['id'] ?? 0) . '/fichier');
            }
            $type = (string) ($row['doc_type'] ?? 'autre');
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $title = (string) ($labels[$type] ?? 'Pièce du dossier');
            }
            $issued = (string) ($row['created_at'] ?? '');
            $out[] = [
                'source' => 'hr',
                'source_label' => 'Dossier RH',
                'id' => (int) ($row['id'] ?? 0),
                'title' => $title,
                'subtitle' => (string) ($labels[$type] ?? $type),
                'detail' => trim((string) ($row['description'] ?? $row['original_name'] ?? '')),
                'issued_at' => $issued,
                'sort_at' => $issued,
                'download_url' => $downloadUrl,
                'download_label' => $downloadUrl !== null ? 'Ouvrir la pièce' : null,
                'status_label' => $downloadUrl !== null ? 'Disponible' : 'Mention au dossier',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectBrevets(int $tenantId, int $userId): array
    {
        $out = [];
        try {
            $rows = $this->awards->listForUser($userId, $tenantId);
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $path = trim((string) ($row['certificate_document_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $name = trim((string) ($row['definition_name'] ?? $row['qualification_name'] ?? $row['definition_short_name'] ?? ''));
            if ($name === '') {
                $name = 'Brevet de qualification';
            }
            $level = trim((string) ($row['level_name'] ?? $row['level_short_name'] ?? $row['level'] ?? ''));
            $issued = (string) ($row['obtained_at'] ?? $row['created_at'] ?? '');
            $out[] = [
                'source' => 'brevet',
                'source_label' => 'Qualification',
                'id' => $id,
                'title' => $name,
                'subtitle' => $level !== '' ? 'Niveau ' . $level : 'Brevet',
                'detail' => trim((string) ($row['certificate_number'] ?? $row['issuer_name'] ?? '')),
                'issued_at' => $issued,
                'sort_at' => $issued,
                'download_url' => url('back-office/ma-situation/qualifications/' . $id . '/brevet'),
                'download_label' => 'Télécharger le brevet',
                'status_label' => 'Brevet',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectTraining(int $tenantId, int $userId): array
    {
        $out = [];
        try {
            $rows = $this->trainingCertificates->listByUserId($userId, $tenantId);
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pdf = trim((string) ($row['pdf_path'] ?? ''));
            if ($pdf === '') {
                continue;
            }
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if ($status === 'revoked') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $course = trim((string) ($row['course_title'] ?? ''));
            if ($course === '') {
                $course = 'Attestation de formation';
            }
            $number = trim((string) ($row['certificate_number'] ?? ''));
            $issued = (string) ($row['issued_at'] ?? '');
            $out[] = [
                'source' => 'training',
                'source_label' => 'Formation',
                'id' => $id,
                'title' => $course,
                'subtitle' => $number !== '' ? 'N° ' . $number : 'Attestation',
                'detail' => $status === 'expired' ? 'Expirée' : 'Attestation de formation',
                'issued_at' => $issued,
                'sort_at' => $issued,
                'download_url' => url('api/training/certificates/' . $id . '/download'),
                'download_label' => 'Télécharger l’attestation',
                'status_label' => $status === 'expired' ? 'Expirée' : 'Valide',
            ];
        }

        return $out;
    }
}
