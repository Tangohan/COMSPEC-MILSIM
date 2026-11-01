<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\PersonnelHrWorkspaceSettings;
use App\Support\Pdf\AthenaPdfTheme as Theme;

/**
 * Établit une pièce PDF (gabarit Athena, A4 portrait, Dompdf) et la range dans le coffre du dossier.
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

    /**
     * Métadonnées du gabarit Athena de la dernière pièce construite par buildHtml().
     *
     * @var array<string, mixed>
     */
    private array $lastMeta = [];

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
        $binary = $this->buildPdf($tenantId, $user, $docType, $context);
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
        // Les pièces auto-établies concernent le parcours de l’opérateur : elles rejoignent son coffre.
        $visibility = PersonnelHrWorkspaceSettings::VISIBILITY_MEMBER;
        try {
            $this->issueAndStore($tenantId, $userId, $spec['kind'], $actorId, $visibility, $context);
        } catch (\Throwable) {
        }
    }

    /**
     * Binaire PDF de la pièce (sans la ranger dans le coffre) ; null si Dompdf est indisponible.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $context
     */
    public function buildPdf(int $tenantId, array $user, string $docType, array $context = []): ?string
    {
        $html = $this->buildHtml($tenantId, $user, $docType, $context);

        return $this->renderPdf($html, $this->lastMeta);
    }

    /**
     * Document HTML complet (gabarit Athena pour Dompdf).
     *
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

        $bodyHtml = '';
        foreach ($sections as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }
            $bodyHtml .= '<p class="para">' . nl2br($esc($paragraph)) . '</p>';
        }

        $this->lastMeta = [
            'kind' => $typeLabel,
            'reference' => $ref,
            'community' => $community,
            'tenant_id' => $tenantId,
            'classification' => 'Diffusion restreinte · dossier personnel',
            'audience' => 'Staff & intéressé(e)',
            'title' => $title,
        ];

        $body = Theme::titleBlock('Dossier individuel · Ressources humaines', $title, [[$typeLabel, 'ok']], 'Pièce officielle versée au coffre du personnel')
            . Theme::callout(
                '<span style="font-size:6.5pt;font-weight:bold;color:' . Theme::MUTED . ';letter-spacing:0.6px;text-transform:uppercase;">Objet</span><br>'
                    . '<span style="font-size:9.5pt;font-weight:bold;">' . $esc($objectLine) . '</span>',
                '',
                'ok'
            )
            . Theme::section('01', 'Identification')
            . Theme::keyValues($metaRows, '35%')
            . Theme::section('02', 'Teneur de la pièce')
            . $bodyHtml
            . Theme::section('03', 'Visas')
            . '<p class="para">' . $placeBit . $esc($dateLong) . '.</p>'
            . Theme::signatures([
                ['Intéressé(e) · pour le dossier', 'Visa / prise de connaissance'],
                [$authority, 'Autorité émettrice · Signature et cachet'],
            ])
            . Theme::spacer(6)
            . '<p class="foot">Document généré pour le dossier individuel — conservation dans le coffre RH de la communauté · ' . $esc($ref) . '</p>';

        return Theme::dompdfDocument(
            $body,
            $this->lastMeta,
            '.para { margin: 0 0 2mm; text-align: justify; font-size: 9pt; line-height: 1.45; }
            .foot { font-size: 7pt; color: ' . Theme::MUTED . '; text-align: center; }'
        );
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

    /**
     * Rendu Dompdf A4 portrait (isHtml5ParserEnabled, DejaVu Sans) via le gabarit Athena.
     *
     * @param array<string, mixed> $meta
     */
    private function renderPdf(string $html, array $meta): ?string
    {
        try {
            return Theme::dompdfRender($html, $meta);
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
