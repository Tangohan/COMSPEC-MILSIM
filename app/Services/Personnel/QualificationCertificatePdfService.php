<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Container;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Repositories\UserRepository;
use App\Support\QualificationAdminStatus;
use DateTimeImmutable;
use RuntimeException;

final class QualificationCertificatePdfService
{
    public function __construct(
        private QualificationAwardRepository $awards,
        private QualificationReferentielRepository $referentiel,
        private QualificationBadgeStorageService $badges,
        private QualificationTemporalStatusService $temporal,
        private ?UserRepository $users = null,
    ) {
    }

    /**
     * Pseudo de communauté (nom affiché, sinon indicatif). Jamais un numéro de compte.
     *
     * @param array<string, mixed> $row
     */
    public static function pickHolderName(array $row): string
    {
        foreach (['display_name', 'callsign'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Opérateur';
    }

    /**
     * @return array{path: string, relative: string, certificate_number: string, holder_name: string}
     */
    public function generate(int $tenantId, int $awardId, ?int $actorId = null): array
    {
        $award = $this->awards->find($awardId, $tenantId);
        if ($award === null) {
            throw new RuntimeException('Attribution introuvable.');
        }
        $admin = QualificationAdminStatus::normalize(
            (string) ($award['admin_status'] ?? $award['status'] ?? '')
        );
        if ($admin !== QualificationAdminStatus::OBTAINED) {
            throw new RuntimeException('Le brevet n’est disponible que pour une qualification obtenue.');
        }

        $number = trim((string) ($award['certificate_number'] ?? ''));
        if ($number === '') {
            $number = $this->allocateNumber($tenantId, $award);
            $this->awards->update($awardId, ['certificate_number' => $number], $actorId);
            $award['certificate_number'] = $number;
        }

        $template = null;
        $tplId = isset($award['certificate_template_id']) ? (int) $award['certificate_template_id'] : 0;
        if ($tplId > 0) {
            $template = $this->referentiel->findCertificateTemplate($tplId);
        }
        if ($template === null) {
            $template = $this->referentiel->defaultCertificateTemplate();
        }
        $layoutCode = 'classique';
        if ($template !== null) {
            $layoutJson = $template['layout_json'] ?? null;
            if (is_string($layoutJson)) {
                $decoded = json_decode($layoutJson, true);
                if (is_array($decoded) && isset($decoded['layout'])) {
                    $layoutCode = (string) $decoded['layout'];
                }
            } elseif (is_array($layoutJson) && isset($layoutJson['layout'])) {
                $layoutCode = (string) $layoutJson['layout'];
            }
            if (isset($template['code']) && in_array($template['code'], ['classique', 'moderne'], true)) {
                $layoutCode = (string) $template['code'];
            }
        }

        $holder = $this->resolveHolder((int) $award['user_id'], $tenantId);
        $tenantName = $this->resolveTenantName($tenantId);
        $isPermanent = !empty($award['is_permanent']) || empty($award['expires_at']);
        $badgePath = $award['level_badge_path'] ?? null;
        if ($badgePath === null || $badgePath === '') {
            $badgePath = $award['definition_badge_path'] ?? null;
        }
        $badgeAbs = $this->badges->absolutePath(is_string($badgePath) ? $badgePath : null);
        $temporal = $this->temporal->resolve($award);

        $viewData = [
            'award' => $award,
            'holder_name' => $holder['name'],
            'holder_grade' => $holder['grade'],
            'holder_callsign' => $holder['callsign'],
            'tenant_name' => $tenantName,
            'is_permanent' => $isPermanent,
            'certificate_number' => $number,
            'badge_path' => $badgeAbs,
            'category_name' => (string) ($award['category_name'] ?? ''),
            'qualification_name' => (string) ($award['definition_name'] ?? $award['qualification_name'] ?? ''),
            'level_name' => (string) ($award['level_name'] ?? $award['level'] ?? ''),
            'issuer_name' => trim((string) ($award['issuer_name'] ?? '')) !== '' ? (string) $award['issuer_name'] : $tenantName,
            'obtained_at' => $this->formatDate($award['obtained_at'] ?? null),
            'expires_at' => $this->formatDate($award['expires_at'] ?? null),
            'temporal_label' => $temporal['label'] !== '' ? $temporal['label'] : 'Valide',
            'generated_at' => (new DateTimeImmutable())->format('d/m/Y H:i'),
            'layout' => $layoutCode,
            'primary_hex' => $layoutCode === 'moderne' ? '#1f3a7a' : '#14213d',
            'accent_hex' => $layoutCode === 'moderne' ? '#1f3a7a' : '#a8843b',
        ];

        $html = $this->renderLayout($layoutCode, $viewData);
        $binary = $this->renderPdf($html);

        $relDir = 'qualifications/' . $tenantId . '/certificates/' . (int) $award['user_id'];
        $absDir = base_path('storage/uploads/' . $relDir);
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new RuntimeException('Impossible de préparer le stockage du brevet.');
        }
        $filename = 'brevet_' . $awardId . '_' . date('YmdHis') . '.pdf';
        $relative = $relDir . '/' . $filename;
        $absolute = $absDir . '/' . $filename;
        if (@file_put_contents($absolute, $binary) === false) {
            throw new RuntimeException('Écriture du brevet PDF impossible.');
        }

        // Conserve l'ancien chemin en historique via notes / history ; pointe vers le nouveau.
        $this->awards->update($awardId, ['certificate_document_path' => $relative], $actorId);
        $this->awards->addHistory(
            $tenantId,
            $awardId,
            'certificate_generated',
            $actorId,
            'Brevet PDF généré (' . $number . ')'
        );

        return [
            'path' => $absolute,
            'relative' => $relative,
            'certificate_number' => $number,
            'holder_name' => $holder['name'],
        ];
    }

    /**
     * @param list<int> $awardIds
     * @return list<array{award_id: int, ok: bool, relative?: string, error?: string}>
     */
    public function generateBatch(int $tenantId, array $awardIds, ?int $actorId = null): array
    {
        $out = [];
        foreach ($awardIds as $id) {
            $id = (int) $id;
            try {
                $res = $this->generate($tenantId, $id, $actorId);
                $out[] = ['award_id' => $id, 'ok' => true, 'relative' => $res['relative']];
            } catch (\Throwable $e) {
                $out[] = ['award_id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $out;
    }

    private function allocateNumber(int $tenantId, array $award): string
    {
        $format = trim((string) ($award['certificate_number_format'] ?? ''));
        if ($format === '') {
            $format = 'QUAL-{code}-{year}-{seq}';
        }
        $year = (int) (new DateTimeImmutable())->format('Y');
        $seq = $this->awards->nextCertificateSequence($tenantId, $year);
        $code = preg_replace('/[^A-Z0-9_\\-]/i', '', (string) ($award['definition_code'] ?? 'QUAL')) ?: 'QUAL';

        return str_replace(
            ['{code}', '{year}', '{seq}', '{année}', '{sequence}'],
            [strtoupper($code), (string) $year, str_pad((string) $seq, 4, '0', STR_PAD_LEFT), (string) $year, str_pad((string) $seq, 4, '0', STR_PAD_LEFT)],
            $format
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderLayout(string $layoutCode, array $data): string
    {
        $file = base_path(
            'views/admin/organization/qualifications/certificates/layout_'
            . ($layoutCode === 'moderne' ? 'moderne' : 'classique')
            . '.php'
        );
        if (!is_file($file)) {
            throw new RuntimeException('Gabarit de brevet introuvable.');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    private function renderPdf(string $html): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException(
                'La génération PDF n’est pas disponible sur ce serveur. Contactez l’équipe technique.'
            );
        }
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Identité affichée sur le brevet : pseudo de communauté, sinon indicatif.
     *
     * @return array{name: string, grade: string, callsign: string}
     */
    private function resolveHolder(int $userId, int $tenantId): array
    {
        $out = ['name' => 'Opérateur', 'grade' => '', 'callsign' => ''];
        try {
            $users = $this->users ?? Container::get(UserRepository::class);
            $row = $users->findById($userId);
            if (!is_array($row)) {
                return $out;
            }
            if ($tenantId > 0) {
                $row = $users->overlayCommunityProfile($row, $tenantId);
            }
            $callsign = trim((string) ($row['callsign'] ?? ''));
            $out['callsign'] = $callsign;
            $out['name'] = self::pickHolderName($row);
            if ($out['name'] === $callsign) {
                $out['callsign'] = '';
            }
            $out['grade'] = $this->resolveGradeLabel($row);
        } catch (\Throwable) {
            // Garde la valeur de repli.
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveGradeLabel(array $row): string
    {
        $gradeId = (int) ($row['grade_id'] ?? 0);
        if ($gradeId < 1) {
            return '';
        }
        try {
            $pdo = \App\Core\Database::getPdo();
            $st = $pdo->prepare(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades'
                   AND COLUMN_NAME IN ('label_long', 'name') LIMIT 2"
            );
            $st->execute();
            $cols = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            if (in_array('label_long', $cols, true)) {
                $q = $pdo->prepare('SELECT label_long FROM grades WHERE id = ? LIMIT 1');
            } elseif (in_array('name', $cols, true)) {
                $q = $pdo->prepare('SELECT name FROM grades WHERE id = ? LIMIT 1');
            } else {
                return '';
            }
            $q->execute([$gradeId]);

            return trim((string) ($q->fetchColumn() ?: ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveTenantName(int $tenantId): string
    {
        try {
            $st = \App\Core\Database::getPdo()->prepare('SELECT name FROM tenants WHERE id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $name = trim((string) ($st->fetchColumn() ?: ''));

            return $name !== '' ? $name : 'ATHENA';
        } catch (\Throwable) {
            return 'ATHENA';
        }
    }

    private function formatDate(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }
        try {
            return (new DateTimeImmutable(substr((string) $raw, 0, 10)))->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $raw;
        }
    }
}
