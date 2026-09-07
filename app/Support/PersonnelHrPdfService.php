<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\PersonnelHrWorkspaceSettings;

/**
 * Établit une pièce PDF et la range dans le coffre du dossier.
 */
final class PersonnelHrPdfService
{
    /** @var list<string> */
    public const GENERATABLE_TYPES = [
        'charte',
        'reglement',
        'certificat',
        'affectation',
        'evaluation',
    ];

    public function __construct(
        private ?TenantRepository $tenants = null,
        private ?UserRepository $users = null,
        private ?GradeRepository $grades = null,
        private ?PersonnelHrDocumentRepository $documents = null,
    ) {
        $this->tenants ??= new TenantRepository();
        $this->users ??= new UserRepository();
        $this->grades ??= new GradeRepository();
        $this->documents ??= new PersonnelHrDocumentRepository();
    }

    /**
     * @param array<string, mixed> $context
     * @return array{ok: bool, message: string, id?: int}
     */
    public function issueAndStore(
        int $tenantId,
        int $userId,
        string $docType,
        ?int $actorId,
        string $visibility,
        array $context = []
    ): array {
        if ($tenantId < 1 || $userId < 1) {
            return ['ok' => false, 'message' => 'Dossier introuvable.'];
        }
        if (!in_array($docType, self::GENERATABLE_TYPES, true)) {
            $docType = 'certificat';
        }
        if (!$this->documents->tableExists()) {
            return ['ok' => false, 'message' => 'Le coffre n’est pas encore disponible pour cette communauté.'];
        }
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Membre introuvable.'];
        }
        $html = $this->buildHtml($tenantId, $user, $docType, $context);
        $binary = $this->renderPdf($html);
        if ($binary === null) {
            return ['ok' => false, 'message' => 'La pièce n’a pas pu être établie pour le moment. Réessayez plus tard.'];
        }
        $title = trim((string) ($context['title'] ?? ''));
        if ($title === '') {
            $title = PersonnelHrDocumentRepository::DOC_TYPE_LABELS[$docType] ?? 'Pièce du dossier';
        }
        $fileName = $this->safeFileName($title) . '.pdf';
        $stored = PersonnelHrDocumentStorage::storeFromBinary($tenantId, $userId, $binary, $fileName);
        if ($stored['error'] !== null || $stored['path'] === null) {
            return ['ok' => false, 'message' => $stored['error'] ?? 'Enregistrement impossible.'];
        }
        $visibility = $visibility === PersonnelHrWorkspaceSettings::VISIBILITY_MEMBER
            ? 'MEMBER'
            : 'STAFF';
        $description = trim((string) ($context['description'] ?? ''));
        $id = $this->documents->create(
            $tenantId,
            $userId,
            $docType,
            $title,
            $description !== '' ? $description : null,
            $stored['path'],
            $stored['original_name'],
            $visibility,
            $actorId
        );
        if ($id < 1) {
            return ['ok' => false, 'message' => 'Impossible d’enregistrer la pièce dans le dossier.'];
        }

        return [
            'ok' => true,
            'message' => 'La pièce a été établie et rangée dans le coffre.',
            'id' => $id,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function maybeAutoIssue(int $tenantId, int $userId, string $event, ?int $actorId, array $context = []): void
    {
        $hr = PersonnelHrWorkspaceSettings::forTenant($tenantId);
        $map = [
            'mobility' => ['flag' => 'auto_pdf_mobility', 'kind' => 'affectation'],
            'elevation' => ['flag' => 'auto_pdf_elevation', 'kind' => 'affectation'],
            'integration' => ['flag' => 'auto_pdf_integration', 'kind' => 'certificat'],
        ];
        $spec = $map[$event] ?? null;
        if ($spec === null || empty($hr[$spec['flag']])) {
            return;
        }
        $visibility = (string) ($hr['default_visibility'] ?? PersonnelHrWorkspaceSettings::VISIBILITY_STAFF);
        try {
            $this->issueAndStore($tenantId, $userId, $spec['kind'], $actorId, $visibility, $context);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $context
     */
    public function buildHtml(int $tenantId, array $user, string $docType, array $context = []): string
    {
        $tenant = $this->tenants->findById($tenantId) ?? [];
        $community = function_exists('community_display_name')
            ? community_display_name($tenant)
            : trim((string) ($tenant['name'] ?? 'Communauté'));
        $member = trim((string) ($user['display_name'] ?? ''));
        if ($member === '') {
            $member = trim((string) ($user['callsign'] ?? ''));
        }
        if ($member === '') {
            $member = trim((string) ($user['email'] ?? 'Membre'));
        }
        $gradeLabel = $this->gradeLabel($tenantId, $user, $context);
        $typeLabel = PersonnelHrDocumentRepository::DOC_TYPE_LABELS[$docType]
            ?? (string) ($context['title'] ?? 'Pièce du dossier');
        $title = trim((string) ($context['title'] ?? $typeLabel));
        $body = trim((string) ($context['body'] ?? $this->defaultBody($docType, $member, $community, $gradeLabel, $context)));
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $date = date('d/m/Y');

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>'
            . $esc($title)
            . '</title><style>
body{font-family:Georgia,"Times New Roman",serif;color:#14202a;margin:0;padding:36px 44px;font-size:13px;line-height:1.45}
header{border-bottom:2px solid #14202a;padding-bottom:12px;margin-bottom:22px}
.kicker{font-family:Arial,Helvetica,sans-serif;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#5b6a73;margin:0 0 6px}
h1{font-size:20px;margin:0;font-weight:700}
.meta{margin:18px 0 22px;padding:12px 14px;border:1px solid #d7dee2;background:#f7f9fa}
.meta p{margin:0 0 4px}
.meta p:last-child{margin:0}
.lead{white-space:pre-wrap;margin:0 0 28px}
footer{margin-top:36px;font-size:11px;color:#5b6a73;border-top:1px solid #d7dee2;padding-top:10px}
</style></head><body>
<header>
<p class="kicker">Dossier individuel</p>
<h1>' . $esc($title) . '</h1>
</header>
<div class="meta">
<p><strong>Communauté</strong> — ' . $esc($community) . '</p>
<p><strong>Membre</strong> — ' . $esc($member) . '</p>
' . ($gradeLabel !== '' ? '<p><strong>Grade</strong> — ' . $esc($gradeLabel) . '</p>' : '') . '
<p><strong>Date</strong> — ' . $esc($date) . '</p>
</div>
<p class="lead">' . nl2br($esc($body)) . '</p>
<footer>Pièce établie pour le dossier individuel. Conservée dans le coffre de la communauté.</footer>
</body></html>';
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $context
     */
    private function gradeLabel(int $tenantId, array $user, array $context): string
    {
        $override = trim((string) ($context['grade_label'] ?? ''));
        if ($override !== '') {
            return $override;
        }
        $gradeId = (int) ($user['grade_id'] ?? 0);
        if ($gradeId < 1) {
            return '';
        }
        try {
            $grade = $this->grades->findById($gradeId, $tenantId);
        } catch (\Throwable) {
            return '';
        }
        if (!is_array($grade)) {
            return '';
        }
        $short = trim((string) ($grade['label_short'] ?? ''));
        $long = trim((string) ($grade['label_long'] ?? ''));
        if ($short !== '' && $long !== '' && $short !== $long) {
            return $short . ' — ' . $long;
        }

        return $long !== '' ? $long : $short;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function defaultBody(string $docType, string $member, string $community, string $gradeLabel, array $context): string
    {
        $gradeBit = $gradeLabel !== '' ? ' Grade : ' . $gradeLabel . '.' : '';
        $extra = trim((string) ($context['detail'] ?? ''));
        $base = match ($docType) {
            'charte' => $member . ' est réputé avoir pris connaissance de la charte de ' . $community . '.' . $gradeBit,
            'reglement' => $member . ' est réputé avoir pris connaissance du règlement intérieur de ' . $community . '.' . $gradeBit,
            'certificat' => 'La présente atteste le parcours de ' . $member . ' au sein de ' . $community . '.' . $gradeBit,
            'affectation' => 'Décision d’affectation concernant ' . $member . ' au sein de ' . $community . '.' . $gradeBit,
            'evaluation' => 'Évaluation portée au dossier de ' . $member . ' pour ' . $community . '.' . $gradeBit,
            default => 'Pièce versée au dossier de ' . $member . '.',
        };
        if ($extra !== '') {
            $base .= "\n\n" . $extra;
        }

        return $base;
    }

    private function renderPdf(string $html): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        try {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();
            $out = $dompdf->output();

            return is_string($out) && $out !== '' ? $out : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeFileName(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            $title = 'piece-dossier';
        }
        $title = preg_replace('/[^\p{L}\p{N}\- ]+/u', '', $title) ?? 'piece-dossier';
        $title = trim(preg_replace('/\s+/', '-', $title) ?? 'piece-dossier', '-');
        if ($title === '') {
            $title = 'piece-dossier';
        }

        return mb_substr($title, 0, 80);
    }
}
