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
    }

    private function tenants(): TenantRepository
    {
        return $this->tenants ??= new TenantRepository();
    }

    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository();
    }

    private function grades(): GradeRepository
    {
        return $this->grades ??= new GradeRepository();
    }

    private function documents(): PersonnelHrDocumentRepository
    {
        return $this->documents ??= new PersonnelHrDocumentRepository();
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
        if (!$this->documents()->tableExists()) {
            return ['ok' => false, 'message' => 'Le coffre n’est pas encore disponible pour cette communauté.'];
        }
        $user = $this->users()->findById($userId, $tenantId);
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
        $id = $this->documents()->create(
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
        $community = trim((string) ($context['community'] ?? ''));
        if ($community === '') {
            $tenant = $this->tenants()->findById($tenantId) ?? [];
            $community = function_exists('community_display_name')
                ? community_display_name($tenant)
                : trim((string) ($tenant['name'] ?? 'Communauté'));
        }
        if ($community === '') {
            $community = 'Communauté';
        }

        $member = $this->memberDisplayName($user);
        $callsign = trim((string) ($user['callsign'] ?? ''));
        $gradeLabel = $this->gradeLabel($tenantId, $user, $context);
        $typeLabel = PersonnelHrDocumentRepository::DOC_TYPE_LABELS[$docType]
            ?? (string) ($context['title'] ?? 'Pièce du dossier');
        $title = trim((string) ($context['title'] ?? $typeLabel));
        if ($title === '') {
            $title = $typeLabel;
        }

        $detail = trim((string) ($context['detail'] ?? ''));
        $customBody = trim((string) ($context['body'] ?? ''));
        $sections = $customBody !== ''
            ? [$customBody]
            : $this->defaultSections($docType, $member, $community, $gradeLabel, $callsign, $detail, $context);

        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $issuedAt = new \DateTimeImmutable('now');
        $dateLong = $this->formatFrenchDate($issuedAt);
        $dateShort = $issuedAt->format('d/m/Y');
        $ref = $this->documentReference($docType, $tenantId, (int) ($user['id'] ?? 0), $issuedAt);
        $objectLine = $this->objectLine($docType, $member, $context);
        $authority = trim((string) ($context['authority'] ?? 'Le responsable du personnel'));
        if ($authority === '') {
            $authority = 'Le responsable du personnel';
        }
        $place = trim((string) ($context['place'] ?? ''));
        $placeBit = $place !== '' ? $esc($place) . ', le ' : 'Fait le ';

        $metaRows = [
            ['Communauté', $community],
            ['Intéressé(e)', $member],
        ];
        if ($callsign !== '' && strcasecmp($callsign, $member) !== 0) {
            $metaRows[] = ['Indicatif', $callsign];
        }
        if ($gradeLabel !== '') {
            $metaRows[] = ['Grade', $gradeLabel];
        }
        $metaRows[] = ['Référence', $ref];
        $metaRows[] = ['Date', $dateShort];

        $metaHtml = '';
        foreach ($metaRows as [$label, $value]) {
            $metaHtml .= '<tr><th>' . $esc($label) . '</th><td>' . $esc($value) . '</td></tr>';
        }

        $bodyHtml = '';
        foreach ($sections as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }
            $bodyHtml .= '<p class="para">' . nl2br($esc($paragraph)) . '</p>';
        }

        $typeBadge = $esc(mb_strtoupper($typeLabel, 'UTF-8'));

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>'
            . $esc($title)
            . '</title><style>
@page { margin: 10mm 10mm 10mm 10mm; }
body {
  font-family: DejaVu Sans, sans-serif;
  color: #14202a;
  margin: 0;
  padding: 0;
  font-size: 9.5pt;
  line-height: 1.4;
}
.sheet { border: 1.1pt solid #1b2a33; padding: 7mm 7mm 6mm; }
.brand-row { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
.brand-row td { vertical-align: top; }
.brand-mark {
  width: 12mm;
  height: 12mm;
  border: 1pt solid #1b2a33;
  text-align: center;
  line-height: 12mm;
  font-size: 7pt;
  font-weight: bold;
  letter-spacing: 0.06em;
}
.brand-text { padding-left: 3mm; }
.brand-kicker {
  margin: 0 0 0.8mm;
  font-size: 7pt;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #5b6a73;
}
.brand-name { margin: 0; font-size: 11.5pt; font-weight: bold; color: #0f1a22; }
.brand-sub { margin: 0.8mm 0 0; font-size: 8pt; color: #5b6a73; }
.ref-block { text-align: right; font-size: 8pt; color: #3d4c55; white-space: nowrap; }
.ref-block strong { display: block; color: #14202a; font-size: 9pt; margin-top: 0.5mm; }
.badge {
  display: inline-block;
  margin: 0 0 2mm;
  padding: 1mm 2.2mm;
  border: 0.65pt solid #1b2a33;
  font-size: 7pt;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #1b2a33;
}
h1 {
  margin: 0 0 1.8mm;
  font-size: 12.5pt;
  line-height: 1.2;
  font-weight: bold;
  color: #0f1a22;
}
.object {
  margin: 0 0 3mm;
  padding: 1.8mm 2.2mm;
  background: #f4f7f8;
  border-left: 2pt solid #1b2a33;
  font-size: 9pt;
}
.object strong { display: block; font-size: 7pt; letter-spacing: 0.1em; text-transform: uppercase; color: #5b6a73; margin-bottom: 0.6mm; }
.meta {
  width: 100%;
  border-collapse: collapse;
  margin: 0 0 3mm;
  font-size: 9pt;
}
.meta th, .meta td {
  border: 0.5pt solid #c9d3d8;
  padding: 1.2mm 2mm;
  text-align: left;
  vertical-align: top;
}
.meta th {
  width: 28%;
  background: #f7f9fa;
  color: #3d4c55;
  font-weight: bold;
}
.para { margin: 0 0 2mm; text-align: justify; }
.closing { margin: 3mm 0 0; }
.closing .place { margin: 0 0 2.5mm; }
.sig-table { width: 100%; border-collapse: collapse; }
.sig-table td { width: 48%; vertical-align: top; }
.sig-box {
  border-top: 0.65pt solid #1b2a33;
  padding-top: 1.6mm;
  margin-top: 3mm;
  width: 90%;
}
.sig-role { font-size: 7.5pt; color: #5b6a73; margin: 0 0 0.5mm; }
.sig-name { font-size: 9pt; font-weight: bold; margin: 0; }
.sig-hint { font-size: 7pt; color: #8aa0ab; margin: 2.5mm 0 0; font-style: italic; }
.foot {
  margin-top: 4mm;
  padding-top: 1.5mm;
  border-top: 0.5pt solid #c9d3d8;
  font-size: 7pt;
  color: #6d7a80;
  text-align: center;
}
</style></head><body>
<div class="sheet"><div class="inner">
  <table class="brand-row">
    <tr>
      <td style="width:18mm"><div class="brand-mark">RH</div></td>
      <td class="brand-text">
        <p class="brand-kicker">Dossier individuel · Ressources humaines</p>
        <p class="brand-name">' . $esc($community) . '</p>
        <p class="brand-sub">Pièce officielle versée au coffre du personnel</p>
      </td>
      <td class="ref-block">
        Réf.<br><strong>' . $esc($ref) . '</strong>
        <span style="display:block;margin-top:2mm">' . $esc($dateShort) . '</span>
      </td>
    </tr>
  </table>

  <div class="badge">' . $typeBadge . '</div>
  <h1>' . $esc($title) . '</h1>
  <div class="object"><strong>Objet</strong>' . $esc($objectLine) . '</div>

  <table class="meta">' . $metaHtml . '</table>

  ' . $bodyHtml . '

  <div class="closing">
    <p class="place">' . $placeBit . $esc($dateLong) . '.</p>
    <table class="sig-table">
      <tr>
        <td>
          <div class="sig-box">
            <p class="sig-role">Pour le dossier</p>
            <p class="sig-name">Intéressé(e)</p>
            <p class="sig-hint">Visa / prise de connaissance</p>
          </div>
        </td>
        <td align="right">
          <div class="sig-box" style="margin-left:auto">
            <p class="sig-role">Autorité émettrice</p>
            <p class="sig-name">' . $esc($authority) . '</p>
            <p class="sig-hint">Signature et cachet</p>
          </div>
        </td>
      </tr>
    </table>
  </div>

  <div class="foot">Document généré pour le dossier individuel — conservation dans le coffre RH de la communauté · ' . $esc($ref) . '</div>
</div></div>
</body></html>';
    }

    /**
     * @param array<string, mixed> $user
     */
    private function memberDisplayName(array $user): string
    {
        $first = trim((string) ($user['first_name'] ?? ''));
        $last = trim((string) ($user['last_name'] ?? ''));
        if ($first !== '' || $last !== '') {
            return trim($first . ' ' . $last);
        }
        $member = trim((string) ($user['display_name'] ?? ''));
        if ($member === '') {
            $member = trim((string) ($user['callsign'] ?? ''));
        }
        if ($member === '') {
            $member = trim((string) ($user['email'] ?? 'Membre'));
        }

        return $member !== '' ? $member : 'Membre';
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
            $grade = $this->grades()->findById($gradeId, $tenantId);
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
     * @return list<string>
     */
    private function defaultSections(
        string $docType,
        string $member,
        string $community,
        string $gradeLabel,
        string $callsign,
        string $detail,
        array $context
    ): array {
        $gradeBit = $gradeLabel !== ''
            ? ' Titulaire du grade « ' . $gradeLabel . ' ».'
            : '';
        $callBit = $callsign !== '' && strcasecmp($callsign, $member) !== 0
            ? ' (indicatif « ' . $callsign . ' »)'
            : '';
        $who = $member . $callBit;

        $sections = match ($docType) {
            'charte' => [
                'La présente pièce constate que ' . $who . ' a pris connaissance de la charte de '
                    . $community . ' et des principes qui en découlent pour le comportement, la discipline et la cohésion du personnel.'
                    . $gradeBit,
                'En signant ou en validant cette prise de connaissance, l’intéressé(e) s’engage à respecter les valeurs, '
                    . 'les règles de conduite et les obligations de réserve applicables au sein de la communauté.',
                'Tout manquement signalé pourra être porté au dossier individuel et donner lieu aux mesures prévues '
                    . 'par le règlement intérieur et les procédures disciplinaires en vigueur.',
            ],
            'reglement' => [
                'Il est attesté que ' . $who . ' a reçu communication du règlement intérieur de ' . $community
                    . ' et en a pris connaissance dans sa version applicable à la date de la présente pièce.'
                    . $gradeBit,
                'Le règlement couvre notamment l’organisation du service, les horaires et disponibilités, '
                    . 'les règles de sécurité, l’usage des moyens collectifs, ainsi que les voies de recours internes.',
                'L’intéressé(e) reconnaît pouvoir consulter à nouveau ce règlement auprès de l’état-major ou du responsable du personnel '
                    . 'et s’engage à en respecter les dispositions.',
            ],
            'certificat' => [
                'Nous, responsables du personnel de ' . $community . ', certifions que ' . $who
                    . ' appartient ou a appartenu aux effectifs de la communauté.'
                    . $gradeBit,
                'La présente attestation est établie pour faire valoir ce que de droit dans le cadre du dossier individuel, '
                    . 'd’une mobilité interne, d’une intégration ou de toute autre démarche administrative liée au parcours du membre.',
                'Elle ne se substitue pas aux brevets, diplômes ou habilitations spécifiques, qui font l’objet de pièces distinctes.',
            ],
            'affectation' => [
                'Décision d’affectation concernant ' . $who . ' au sein de ' . $community . '.'
                    . $gradeBit,
                'Après examen du dossier et des besoins de l’organisation, l’intéressé(e) est affecté(e) selon les mentions '
                    . 'portées ci-après. Cette décision prend effet à compter de sa notification, sauf indication contraire.',
                'L’affectation emporte les responsabilités, droits et obligations attachés au poste. '
                    . 'Elle peut être modifiée ultérieurement selon les procédures de mobilité en vigueur.',
            ],
            'evaluation' => [
                'La présente évaluation est portée au dossier individuel de ' . $who . ' pour ' . $community . '.'
                    . $gradeBit,
                'Elle synthétise l’appréciation portée sur la période considérée : tenue générale, implication, '
                    . 'compétences mises en œuvre, capacité à travailler en équipe et aptitude aux responsabilités confiées.',
                'Les observations complémentaires, objectifs de progression et suites éventuelles (formation, mobilité, vivier) '
                    . 'sont consignés dans les mentions ci-dessous et restent consultables selon la visibilité définie pour la pièce.',
            ],
            default => [
                'Pièce versée au dossier individuel de ' . $who . ' au sein de ' . $community . '.' . $gradeBit,
            ],
        };

        if ($detail !== '') {
            $sections[] = 'Mentions complémentaires : ' . $detail;
        }

        $extraNote = trim((string) ($context['description'] ?? ''));
        if ($extraNote !== '' && $extraNote !== $detail) {
            $sections[] = $extraNote;
        }

        return $sections;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function objectLine(string $docType, string $member, array $context): string
    {
        $custom = trim((string) ($context['object'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        return match ($docType) {
            'charte' => 'Prise de connaissance de la charte — ' . $member,
            'reglement' => 'Prise de connaissance du règlement intérieur — ' . $member,
            'certificat' => 'Attestation de parcours / appartenance — ' . $member,
            'affectation' => 'Décision d’affectation — ' . $member,
            'evaluation' => 'Évaluation portée au dossier — ' . $member,
            default => 'Pièce du dossier individuel — ' . $member,
        };
    }

    private function documentReference(string $docType, int $tenantId, int $userId, \DateTimeImmutable $issuedAt): string
    {
        $prefix = match ($docType) {
            'charte' => 'CHA',
            'reglement' => 'REG',
            'certificat' => 'CER',
            'affectation' => 'AFF',
            'evaluation' => 'EVA',
            default => 'RH',
        };

        return sprintf(
            'RH-%s-%s-%04d-%04d',
            $prefix,
            $issuedAt->format('Ymd'),
            max(0, $tenantId) % 10000,
            max(0, $userId) % 10000
        );
    }

    private function formatFrenchDate(\DateTimeImmutable $date): string
    {
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $month = $months[(int) $date->format('n')] ?? $date->format('m');

        return (int) $date->format('j') . ' ' . $month . ' ' . $date->format('Y');
    }

    private function renderPdf(string $html): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }
        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);
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
