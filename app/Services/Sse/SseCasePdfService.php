<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Support\SseDocumentMarkings;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF complet d’un dossier SSE (couverture, produits, personnes, sites,
 * corrélations, notes, preuves). Toujours produit au niveau d’habilitation demandé.
 */
final class SseCasePdfService
{
    /** @var array{accent:string,banner:string,seal:string,soft:string,ink:string,muted:string} */
    private array $palette = [
        'accent' => '#475569',
        'banner' => '#f8fafc',
        'seal' => '#334155',
        'soft' => '#e2e8f0',
        'ink' => '#0b1220',
        'muted' => '#64748b',
    ];

    /** @var array<string,mixed> */
    private array $caseMeta = [];

    public function __construct(
        private ?SseCaseRepository $cases = null,
        private ?SseReportService $reports = null,
        private ?SseCorrelationService $correlations = null,
        private ?SseRedactionService $redaction = null,
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->reports ??= new SseReportService();
        $this->correlations ??= new SseCorrelationService();
        $this->redaction ??= new SseRedactionService();
    }

    /**
     * Export PDF du dossier.
     *
     * `$releaseLevel` : niveau de diffusion. À `null`, version intégrale
     * (réservée aux appels déjà encadrés). Un PDF circule seul une fois transmis.
     *
     * `$inline` : lecture à l'écran plutôt que téléchargement.
     */
    public function export(int $tenantId, int $caseId, ?string $releaseLevel = null, bool $inline = false): Response
    {
        $data = $releaseLevel === null
            ? $this->reports->gather($caseId, $tenantId)
            : $this->reports->gatherForRelease($caseId, $tenantId, $releaseLevel);

        if ($data === null) {
            return (new Response())->setStatusCode(404)->setBody('<p>Dossier introuvable.</p>');
        }

        /** @var array<string,mixed> $case */
        $case = $data['case'];
        /** @var list<array<string,mixed>> $people */
        $people = is_array($data['people'] ?? null) ? $data['people'] : [];
        /** @var list<array<string,mixed>> $sites */
        $sites = is_array($data['sites'] ?? null) ? $data['sites'] : [];

        $notes = $this->cases->listNotes($caseId, $tenantId);
        $evidence = $this->cases->listEvidence($caseId, $tenantId);
        $relations = $this->correlations->listStored($caseId, $tenantId);
        $flash = $this->reports->buildFlashReport($caseId, $tenantId, $releaseLevel);
        $initial = $this->reports->buildInitialReport($caseId, $tenantId, $releaseLevel);

        $redactedLabel = '';
        if ($releaseLevel !== null) {
            $hidden = SseRedactionService::summarise($releaseLevel)['hidden'];
            $redactedLabel = $hidden === []
                ? 'Version intégrale — ' . SseRedactionService::levelLabel($releaseLevel)
                : 'VERSION EXPURGÉE — ' . SseRedactionService::levelLabel($releaseLevel)
                    . ' — au noir : ' . implode(', ', $hidden);
        }

        $generatedAt = (new \DateTimeImmutable('now'))->format('d/m/Y H:i');

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use (
            $case,
            $people,
            $sites,
            $notes,
            $evidence,
            $relations,
            $flash,
            $initial,
            $redactedLabel,
            $generatedAt,
            $inline,
            $releaseLevel
        ): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }

            $classCode = SseCaseRepository::normalizeClassification((string) ($case['classification'] ?? 'encadrement'));
            $this->palette = $this->classPalette($classCode);
            $this->caseMeta = $case;

            $footerRef = (string) ($case['reference_code'] ?? '');
            $footerClass = mb_strtoupper((string) ($case['classification_label'] ?? 'Confidentiel'), 'UTF-8');
            $footerAccent = $this->hexToRgb($this->palette['accent']);

            $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
                public string $sseFooterRef = '';
                public string $sseFooterClass = '';
                /** @var array{0:int,1:int,2:int} */
                public array $sseFooterAccent = [71, 85, 105];

                public function Footer(): void
                {
                    $leftM = $this->lMargin;
                    $rightM = $this->w - $this->rMargin;
                    $usable = max(1.0, $rightM - $leftM);
                    $this->SetY(-12);
                    $this->SetDrawColor($this->sseFooterAccent[0], $this->sseFooterAccent[1], $this->sseFooterAccent[2]);
                    $this->SetLineWidth(0.35);
                    $this->Line($leftM, $this->GetY(), $rightM, $this->GetY());
                    $this->SetY(-10);
                    $this->SetX($leftM);
                    $this->SetFont('helvetica', '', 7);
                    $this->SetTextColor(100, 116, 139);
                    $left = $this->sseFooterRef !== '' ? $this->sseFooterRef : 'Athena SSE';
                    $center = $this->sseFooterClass;
                    $right = 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages();
                    $col = $usable / 3;
                    $this->Cell($col, 5, $left, 0, 0, 'L');
                    $this->SetTextColor($this->sseFooterAccent[0], $this->sseFooterAccent[1], $this->sseFooterAccent[2]);
                    $this->SetFont('helvetica', 'B', 7);
                    $this->Cell($col, 5, $center, 0, 0, 'C');
                    $this->SetTextColor(100, 116, 139);
                    $this->SetFont('helvetica', '', 7);
                    $this->Cell($col, 5, $right, 0, 0, 'R');
                }
            };
            $pdf->sseFooterRef = $footerRef;
            $pdf->sseFooterClass = $footerClass;
            $pdf->sseFooterAccent = $footerAccent;

            $pdf->SetCreator('Athena COMSPEC');
            $pdf->SetAuthor('Portail SSE Athena');
            $pdf->SetTitle('Dossier complet ' . $footerRef);
            $pdf->SetMargins(12, 14, 12);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->setCellHeightRatio(1.35);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->setFooterMargin(12);
            // TCPDF moderne : pas d’AliasNbPages() (héritage FPDF). Les alias
            // getAliasNumPage() / getAliasNbPages() dans Footer() suffisent.

            // —— Page de garde ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->coverHtml($case, $redactedLabel, $generatedAt, $people, $sites, $notes, $evidence),
                true,
                false,
                true,
                false,
                ''
            );

            // —— Flash ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->sectionBanner($redactedLabel, '01 · Flash opérationnel')
                . $this->reportSheet('Flash opérationnel', $flash),
                true,
                false,
                true,
                false,
                ''
            );

            // —— Compte rendu initial ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->sectionBanner($redactedLabel, '02 · Compte rendu initial')
                . $this->reportSheet('Compte rendu initial', $initial),
                true,
                false,
                true,
                false,
                ''
            );

            // —— Personnes ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->sectionBanner($redactedLabel, '03 · Personnes rattachées')
                . $this->peopleHtml($people),
                true,
                false,
                true,
                false,
                ''
            );

            // —— Sites ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->sectionBanner($redactedLabel, '04 · Sites exploités')
                . $this->sitesHtml($sites),
                true,
                false,
                true,
                false,
                ''
            );

            // —— Corrélations ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->sectionBanner($redactedLabel, '05 · Corrélations')
                . $this->relationsHtml($relations, $people, $sites),
                true,
                false,
                true,
                false,
                ''
            );

            // —— Notes ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->sectionBanner($redactedLabel, '06 · Notes classifiées')
                . $this->notesHtml($notes),
                true,
                false,
                true,
                false,
                ''
            );

            // —— Preuves (+ images) ——
            $pdf->AddPage();
            $pdf->writeHTML(
                $this->sectionBanner($redactedLabel, '07 · Preuves recensées')
                . $this->evidenceHtml($evidence),
                true,
                false,
                true,
                false,
                ''
            );
            $this->appendEvidenceImages($pdf, $evidence, $redactedLabel);

            $binary = (string) $pdf->Output('', 'S');
            $refSlug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($case['reference_code'] ?? 'sse'));
            $levelSlug = $releaseLevel !== null
                ? preg_replace('/[^a-zA-Z0-9_-]+/', '-', $releaseLevel)
                : 'integral';
            $filename = $releaseLevel !== null
                ? sprintf('SSE-%s-expurge-%s.pdf', $refSlug, $levelSlug)
                : 'dossier-complet-' . $refSlug . '.pdf';

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /**
     * @param array<string,mixed> $case
     * @param list<array<string,mixed>> $people
     * @param list<array<string,mixed>> $sites
     * @param list<array<string,mixed>> $notes
     * @param list<array<string,mixed>> $evidence
     */
    private function coverHtml(
        array $case,
        string $redactedLabel,
        string $generatedAt,
        array $people,
        array $sites,
        array $notes,
        array $evidence
    ): string {
        $caseRef = (string) ($case['reference_code'] ?? '');
        $caseTitle = (string) ($case['title'] ?? 'Dossier sans intitulé');
        $classLabel = (string) ($case['classification_label'] ?? 'Confidentiel');
        $classUpper = mb_strtoupper($classLabel, 'UTF-8');
        $accent = $this->palette['accent'];
        $bannerBg = $this->palette['banner'];
        $sealColor = $this->palette['seal'];
        $soft = $this->palette['soft'];
        $ink = $this->palette['ink'];
        $muted = $this->palette['muted'];

        $statusLabel = (string) ($case['status_label'] ?? 'En cours');
        $statusKey = (string) ($case['status'] ?? '');
        $isClosed = in_array($statusKey, ['clos', 'archive', 'cloture'], true);
        $summary = trim((string) ($case['summary'] ?? ''));

        $openedSrc = (string) ($case['created_at'] ?? '');
        $openedFr = $openedSrc !== '' ? date('d/m/Y', strtotime($openedSrc) ?: time()) : '—';
        $updatedSrc = (string) ($case['updated_at'] ?? '');
        $updatedFr = $updatedSrc !== '' ? date('d/m/Y', strtotime($updatedSrc) ?: time()) : $openedFr;

        $coverUnit = trim((string) (Session::get('tenant_name') ?? ''));
        if ($coverUnit === '') {
            $coverUnit = 'Unité Athena';
        }

        $marks = SseDocumentMarkings::forDocument([
            'id' => (int) ($case['id'] ?? 0),
            'reference_code' => $caseRef,
            'title' => $caseTitle,
            'body' => $summary,
            'classification' => (string) ($case['classification'] ?? ''),
            'created_at' => $openedSrc,
            'updated_at' => $updatedSrc,
        ], $coverUnit);

        $ws = is_array($marks['workstation'] ?? null) ? $marks['workstation'] : [];
        $wsId = (string) ($ws['id'] ?? 'QR');
        $wsHost = (string) ($ws['host'] ?? 'SSE-WS');
        $wsIp = (string) ($ws['ip'] ?? '—');
        $wsFp = (string) ($ws['fingerprint'] ?? '—');
        $wsQr = (string) ($ws['qr_html'] ?? '');

        $e = fn (string $v): string => $this->e($v);

        // Cadre papier : filet gauche classification + bordure fine
        $html = '<table cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
            . '<td width="1.6%" style="background-color:' . $accent . ';"></td>'
            . '<td width="98.4%" style="border:1px solid #cbd5e1;padding:0;">';

        if ($redactedLabel !== '') {
            $html .= '<table cellpadding="5" cellspacing="0" border="0" width="100%" style="background-color:'
                . $accent . ';"><tr><td style="color:#ffffff;text-align:center;font-size:8.5px;font-weight:bold;letter-spacing:0.5px;">'
                . $e($redactedLabel)
                . '</td></tr></table>';
        }

        // Bandeau classification
        $html .= '<table cellpadding="5" cellspacing="0" border="0" width="100%" style="background-color:'
            . $bannerBg . ';border-bottom:1px solid ' . $accent . ';">'
            . '<tr>'
            . '<td width="26%" style="font-size:6.5px;color:' . $muted . ';text-align:left;letter-spacing:0.4px;">(CLASSIFICATION DE SÉCURITÉ)</td>'
            . '<td width="48%" style="font-size:13px;font-weight:bold;color:' . $accent . ';text-align:center;letter-spacing:1.5px;">'
            . $e($classUpper) . '</td>'
            . '<td width="26%" style="font-size:7px;color:' . $muted . ';text-align:right;">EXEMPLAIRE '
            . (int) ($marks['copy_index'] ?? 1) . '/' . (int) ($marks['copy_total'] ?? 1) . '</td>'
            . '</tr></table>';

        $html .= '<table cellpadding="8" cellspacing="0" border="0" width="100%"><tr><td>';

        // Registre + boîte de contrôle
        $html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%"><tr>';
        $html .= '<td width="67%" valign="top">';
        $html .= '<table cellpadding="3" cellspacing="0" border="1" width="100%" style="border-color:#334155;font-size:7.5px;">'
            . '<tr style="background-color:#1e293b;color:#ffffff;">'
            . '<td colspan="4" style="font-size:7.5px;font-weight:bold;letter-spacing:1.2px;">REGISTRE DE CONSULTATION</td></tr>'
            . '<tr style="background-color:' . $soft . ';font-weight:bold;color:' . $ink . ';">'
            . '<td width="10%">N°</td><td width="48%">CONSULTANT</td><td width="22%">DATE</td><td width="20%">VISA</td></tr>';
        $routing = is_array($marks['routing'] ?? null) ? $marks['routing'] : [];
        foreach ($routing as $row) {
            if (!is_array($row)) {
                continue;
            }
            $holder = trim((string) ($row['holder'] ?? ''));
            $rowBg = $holder === '' ? 'background-color:#fafafa;' : '';
            $html .= '<tr style="' . $rowBg . '">'
                . '<td style="color:' . $muted . ';">' . (int) ($row['slot'] ?? 0) . '</td>'
                . '<td>' . $e($holder) . '</td>'
                . '<td>' . $e((string) ($row['date'] ?? '')) . '</td>'
                . '<td style="font-family:courier;letter-spacing:1px;font-weight:bold;">'
                . $e((string) ($row['initials'] ?? '')) . '</td>'
                . '</tr>';
        }
        $html .= '</table></td>';
        $html .= '<td width="2%"></td>';
        $html .= '<td width="31%" valign="top">';
        $html .= '<table cellpadding="4" cellspacing="0" border="1" width="100%" style="border-color:#334155;font-size:7px;">'
            . '<tr style="background-color:' . $bannerBg . ';"><td>'
            . '<span style="color:' . $muted . ';letter-spacing:0.6px;">CONTRÔLE N°</span><br/>'
            . '<strong style="font-size:9px;color:' . $ink . ';">' . $e((string) ($marks['control_number'] ?? '')) . '</strong></td></tr>'
            . '<tr><td><span style="color:' . $muted . ';letter-spacing:0.6px;">REGISTRE</span><br/>'
            . '<strong style="font-size:8.5px;color:' . $ink . ';">' . $e((string) ($marks['registry_number'] ?? '')) . '</strong></td></tr>'
            . '<tr style="background-color:' . $bannerBg . ';"><td><span style="color:' . $muted . ';letter-spacing:0.6px;">OUVERT LE</span><br/>'
            . '<strong style="font-size:9px;color:' . $ink . ';">' . $e($openedFr) . '</strong></td></tr>'
            . '<tr><td><span style="color:' . $muted . ';letter-spacing:0.6px;">MOUVEMENT</span><br/>'
            . '<strong style="font-size:9px;color:' . $ink . ';">' . $e($updatedFr) . '</strong></td></tr>'
            . '</table></td>';
        $html .= '</tr></table>';

        // Canal protégé
        $caveats = is_array($marks['caveats'] ?? null) ? $marks['caveats'] : [];
        $html .= '<br/><table cellpadding="7" cellspacing="0" border="1" width="100%" style="border-color:'
            . $accent . ';background-color:' . $bannerBg . ';">'
            . '<tr><td>'
            . '<div style="font-size:10px;font-weight:bold;color:' . $accent . ';letter-spacing:1px;">'
            . $e((string) ($marks['channel'] ?? 'CANAL PROTÉGÉ')) . '</div>'
            . '<div style="font-size:7.5px;color:#334155;margin-top:3px;line-height:1.35;">'
            . 'Chemise à ne pas dissocier de ses pièces jointes. Toute sortie du local sécurisé est portée au registre de consultation.'
            . '</div>';
        if ($caveats !== []) {
            $html .= '<div style="margin-top:5px;font-size:7px;">';
            foreach ($caveats as $caveat) {
                $html .= '<span style="border:1px solid ' . $accent . ';color:' . $accent
                    . ';background-color:#ffffff;padding:2px 5px;margin-right:3px;font-weight:bold;letter-spacing:0.4px;">'
                    . $e((string) $caveat) . '</span> ';
            }
            $html .= '</div>';
        }
        $html .= '</td></tr></table>';

        // En-tête unité + sceau
        $html .= '<br/><table cellpadding="2" cellspacing="0" border="0" width="100%"><tr>';
        $html .= '<td width="52%" valign="top" style="font-size:8.5px;color:' . $ink . ';line-height:1.5;">'
            . '<div style="font-size:7px;letter-spacing:1.5px;color:' . $muted . ';">ATHENA · COMPSEC</div>'
            . '<div style="font-weight:bold;text-decoration:underline;margin-top:3px;font-size:9.5px;">UNITÉ : '
            . $e($coverUnit) . '</div>'
            . '<div style="margin-top:2px;">SECTION : Bureau SSE — Renseignement</div>'
            . '<div style="margin-top:5px;font-weight:bold;font-size:10px;color:' . $accent . ';">DOSSIER N° '
            . $e($caseRef) . '</div>'
            . '</td>';
        $html .= '<td width="22%" align="center" valign="middle">';
        $html .= '<table cellpadding="5" cellspacing="0" border="2" width="100%" style="border-color:'
            . $sealColor . ';color:' . $sealColor . ';background-color:#ffffff;">'
            . '<tr><td align="center" style="font-size:5.5px;letter-spacing:1.2px;border-bottom:1px solid '
            . $sealColor . ';">BUREAU SSE</td></tr>'
            . '<tr><td align="center" style="font-size:18px;font-weight:bold;letter-spacing:1px;">'
            . $e((string) ($marks['seal_initials'] ?? 'UA')) . '</td></tr>'
            . '<tr><td align="center" style="font-size:5.5px;letter-spacing:1.2px;border-top:1px solid '
            . $sealColor . ';">DOSSIERS</td></tr>'
            . '</table></td>';
        $html .= '<td width="26%" align="right" valign="top" style="font-size:9px;color:' . $ink . ';">'
            . '<span style="color:' . $muted . ';font-size:7px;">ÉMIS LE</span><br/>'
            . '<strong>' . $e($updatedFr) . '</strong></td>';
        $html .= '</tr></table>';

        $html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:6px;">'
            . '<tr><td style="border-top:2px solid ' . $accent . ';font-size:1px;line-height:1px;">&nbsp;</td></tr>'
            . '<tr><td style="border-top:1px solid ' . $accent . ';font-size:2px;line-height:2px;">&nbsp;</td></tr>'
            . '</table>';

        $html .= '<div style="text-align:center;font-size:7px;letter-spacing:2px;color:' . $muted
            . ';font-weight:bold;margin:6px 0 2px 0;">CHEMISE DE DOSSIER</div>';
        $html .= '<div style="text-align:center;font-size:14px;font-weight:bold;color:' . $ink
            . ';margin:0 0 8px 0;line-height:1.25;">'
            . $e($caseTitle) . '</div>';

        // Grille faits
        $facts = [
            ['Statut', $statusLabel],
            ['Classification', $classLabel],
            ['Habilitation', $isClosed ? 'Consultation sur demande' : 'Besoin d’en connaître'],
            ['Code d’ouverture', !empty($case['has_unlock_code']) ? 'Exigé' : 'Non exigé'],
            ['Personnes rattachées', (string) count($people)],
            ['Notes classifiées', (string) count($notes)],
            ['Preuves versées', (string) count($evidence)],
            ['Sites exploités', (string) count($sites)],
        ];
        $html .= '<table cellpadding="5" cellspacing="0" border="1" width="100%" style="border-color:'
            . $ink . ';font-size:8px;">';
        for ($i = 0; $i < 8; $i += 4) {
            $html .= '<tr>';
            for ($j = 0; $j < 4; $j++) {
                [$lab, $val] = $facts[$i + $j];
                $cellBg = (($i + $j) % 2 === 0) ? 'background-color:' . $bannerBg . ';' : '';
                $html .= '<td width="25%" style="' . $cellBg . '"><span style="font-size:6px;color:'
                    . $muted . ';letter-spacing:0.6px;text-transform:uppercase;">'
                    . $e($lab) . '</span><br/><strong style="font-size:9px;color:' . $ink . ';">'
                    . $e($val) . '</strong></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        // Objet
        $html .= $this->sectionTitle('Objet du dossier');
        if ($summary !== '') {
            $html .= '<p style="font-size:9px;color:#1e293b;line-height:1.45;text-align:justify;margin:2px 0 6px 0;">'
                . nl2br($e($summary)) . '</p>';
        } else {
            $html .= '<p style="font-size:8.5px;color:' . $muted . ';font-style:italic;margin:2px 0 6px 0;">'
                . 'Aucune synthèse n’a encore été portée à la chemise.</p>';
        }

        // Consignes
        $html .= $this->sectionTitle('Consignes de manipulation');
        $html .= '<p style="font-size:7.5px;color:#334155;line-height:1.4;margin:2px 0 4px 0;">'
            . 'Le dossier <strong>' . $e($caseRef) . '</strong> regroupe des pièces de niveaux de protection différents. '
            . 'Le niveau retenu pour l’ensemble est <strong>' . $e($classLabel) . '</strong>. '
            . 'Consultation au poste habilité, chemise complète ; restitution le jour même contre visa ; '
            . 'reproduction interdite sans accord écrit du chef de bureau.'
            . '</p>';
        $html .= '<p style="font-size:7.5px;color:#334155;margin:0 0 6px 0;">'
            . 'Révision de classification prévue le <strong>' . $e((string) ($marks['declassify_on'] ?? '—')) . '</strong>. '
            . 'Conservation <strong>' . $e((string) ($marks['destruction_delay'] ?? '—')) . '</strong> après clôture.'
            . '</p>';

        // Auth : QR + empreintes
        $html .= '<table cellpadding="5" cellspacing="0" border="1" width="100%" style="border-color:#cbd5e1;background-color:#fafafa;">'
            . '<tr>';
        $html .= '<td width="26%" valign="middle" align="center" style="background-color:#ffffff;">';
        if ($wsQr !== '') {
            $html .= preg_replace(
                ['/\sclass="[^"]*"/', '/\swidth="\d+"/', '/\sheight="\d+"/'],
                ['', ' width="64"', ' height="64"'],
                $wsQr
            ) ?? $wsQr;
        }
        $html .= '<div style="font-size:6.5px;margin-top:3px;color:' . $ink . ';"><strong>'
            . $e($wsId) . '</strong><br/>' . $e($wsHost) . ' · ' . $e($wsIp) . '</div></td>';
        $html .= '<td width="74%" valign="top" style="font-size:7px;color:' . $ink . ';line-height:1.45;">'
            . '<div style="font-weight:bold;font-size:8px;letter-spacing:0.8px;color:' . $accent
            . ';margin-bottom:4px;">EMPREINTES D’INTÉGRITÉ</div>'
            . '<strong>Condensat</strong> : <span style="font-family:courier;font-size:6.5px;">'
            . $e((string) ($marks['integrity_groups'] ?? '')) . '</span><br/>'
            . '<strong>Enveloppe</strong> : <span style="font-family:courier;font-size:6.5px;">'
            . $e((string) ($marks['envelope_hash'] ?? '')) . '</span><br/>'
            . '<strong>Machine</strong> : <span style="font-family:courier;font-size:6.5px;">'
            . $e($wsFp !== '' ? $wsFp : '—') . '</span><br/>'
            . '<strong>Contrôle</strong> : <span style="font-family:courier;font-size:6.5px;">'
            . $e((string) ($marks['checksum'] ?? '')) . '</span>'
            . ' · ' . $e((string) ($marks['algorithm'] ?? ''))
            . '</td></tr></table>';

        // Tampons
        $html .= '<br/><table cellpadding="4" cellspacing="5" border="0" width="100%"><tr>'
            . '<td align="center" style="border:2px solid ' . $accent . ';color:' . $accent
            . ';font-size:7.5px;font-weight:bold;letter-spacing:1px;">'
            . $e($classUpper) . '</td>'
            . '<td align="center" style="border:2px solid #15803d;color:#15803d;font-size:7.5px;font-weight:bold;letter-spacing:1px;">'
            . ($isClosed ? 'DOSSIER CLOS' : 'DOSSIER OUVERT') . '</td>'
            . '<td align="center" style="border:2px dashed #64748b;color:#64748b;font-size:7.5px;font-weight:bold;letter-spacing:0.6px;">'
            . 'EXEMPLAIRE ' . (int) ($marks['copy_index'] ?? 1) . ' / ' . (int) ($marks['copy_total'] ?? 1) . '</td>'
            . '</tr></table>';

        $html .= '</td></tr></table>'; // padding cell

        // Bandeau bas
        $html .= '<table cellpadding="4" cellspacing="0" border="0" width="100%" style="background-color:'
            . $bannerBg . ';border-top:1px solid ' . $accent . ';">'
            . '<tr>'
            . '<td width="33%" style="font-size:6.5px;color:' . $muted . ';">Contrôle '
            . $e((string) ($marks['control_number'] ?? '')) . '</td>'
            . '<td width="34%" style="font-size:10px;font-weight:bold;color:' . $accent
            . ';text-align:center;letter-spacing:1px;">' . $e($classUpper) . '</td>'
            . '<td width="33%" style="font-size:6.5px;color:' . $muted . ';text-align:right;">Chemise — page de garde · '
            . $e($generatedAt) . '</td>'
            . '</tr></table>';

        $html .= '</td></tr></table>'; // frame

        return $html;
    }

    /**
     * @return array{accent:string,banner:string,seal:string,soft:string,ink:string,muted:string}
     */
    private function classPalette(string $classCode): array
    {
        $base = ['ink' => '#0b1220', 'muted' => '#64748b'];

        return match ($classCode) {
            'tres_restreint' => $base + [
                'accent' => '#b91c1c',
                'banner' => '#fef2f2',
                'seal' => '#991b1b',
                'soft' => '#fecaca',
            ],
            'confidentiel' => $base + [
                'accent' => '#b45309',
                'banner' => '#fffbeb',
                'seal' => '#92400e',
                'soft' => '#fde68a',
            ],
            'encadrement' => $base + [
                'accent' => '#1d4ed8',
                'banner' => '#eff6ff',
                'seal' => '#1e3a8a',
                'soft' => '#bfdbfe',
            ],
            default => $base + [
                'accent' => '#475569',
                'banner' => '#f8fafc',
                'seal' => '#334155',
                'soft' => '#e2e8f0',
            ],
        };
    }

    private function sectionBanner(string $redactedLabel, string $sectionLabel = ''): string
    {
        $case = $this->caseMeta;
        $classLabel = (string) ($case['classification_label'] ?? '');
        $classUpper = mb_strtoupper($classLabel !== '' ? $classLabel : 'CONFIDENTIEL', 'UTF-8');
        $accent = $this->palette['accent'];
        $bannerBg = $this->palette['banner'];
        $muted = $this->palette['muted'];
        $ink = $this->palette['ink'];

        $html = '<table cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
            . '<td width="1.6%" style="background-color:' . $accent . ';"></td>'
            . '<td width="98.4%" style="border:1px solid ' . $accent . ';background-color:' . $bannerBg . ';">'
            . '<table cellpadding="4" cellspacing="0" border="0" width="100%">'
            . '<tr>'
            . '<td width="28%" style="font-size:7px;color:' . $muted . ';">'
            . $this->e((string) ($case['reference_code'] ?? '')) . '</td>'
            . '<td width="44%" style="font-size:10px;font-weight:bold;color:' . $accent
            . ';text-align:center;letter-spacing:1px;">'
            . $this->e($classUpper) . '</td>'
            . '<td width="28%" style="font-size:7px;color:' . $muted . ';text-align:right;">'
            . $this->e((string) ($case['title'] ?? '')) . '</td>'
            . '</tr>';
        if ($sectionLabel !== '') {
            $html .= '<tr><td colspan="3" style="font-size:8px;font-weight:bold;color:' . $ink
                . ';letter-spacing:0.8px;border-top:1px solid ' . $accent . ';background-color:#ffffff;">'
                . $this->e(mb_strtoupper($sectionLabel, 'UTF-8')) . '</td></tr>';
        }
        $html .= '</table></td></tr></table><br/>';

        if ($redactedLabel !== '') {
            $html .= '<table cellpadding="4" cellspacing="0" border="0" width="100%" style="background-color:'
                . $accent . ';margin-bottom:6px;"><tr><td style="color:#ffffff;font-size:7.5px;text-align:center;font-weight:bold;">'
                . $this->e($redactedLabel) . '</td></tr></table>';
        }

        return $html;
    }

    private function sectionTitle(string $title): string
    {
        $accent = $this->palette['accent'];
        $ink = $this->palette['ink'];

        return '<br/><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
            . '<td width="1.6%" style="background-color:' . $accent . ';"></td>'
            . '<td width="1.8%"></td>'
            . '<td width="96.6%" style="font-size:9.5px;font-weight:bold;color:' . $ink
            . ';letter-spacing:1px;">' . $this->e(mb_strtoupper($title, 'UTF-8')) . '</td>'
            . '</tr></table><br/>';
    }

    private function reportSheet(string $title, string $body): string
    {
        $accent = $this->palette['accent'];
        $bannerBg = $this->palette['banner'];
        $ink = $this->palette['ink'];
        $muted = $this->palette['muted'];

        return '<table cellpadding="0" cellspacing="0" border="1" width="100%" style="border-color:'
            . $accent . ';">'
            . '<tr style="background-color:' . $bannerBg . ';"><td>'
            . '<table cellpadding="6" cellspacing="0" border="0" width="100%">'
            . '<tr><td style="font-size:11px;font-weight:bold;color:' . $ink . ';letter-spacing:0.6px;">'
            . $this->e(mb_strtoupper($title, 'UTF-8')) . '</td></tr>'
            . '<tr><td style="font-size:7px;color:' . $muted . ';letter-spacing:0.5px;">'
            . 'PIÈCE RÉDACTIONNELLE · DIFFUSION CONTRÔLÉE</td></tr>'
            . '</table></td></tr>'
            . '<tr><td>' . $this->reportBodyHtml($body) . '</td></tr>'
            . '</table>';
    }

    /**
     * TCPDF ignore les blocs pre et le padding CSS : une ligne métier = une rangée.
     */
    private function reportBodyHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $ink = $this->palette['ink'];
        $accent = $this->palette['accent'];
        $muted = $this->palette['muted'];

        if ($text === '') {
            return '<table cellpadding="10" cellspacing="0" border="0" width="100%"><tr><td style="font-size:9px;font-style:italic;color:'
                . $muted . ';">(aucun contenu généré)</td></tr></table>';
        }

        $html = '<table cellpadding="6" cellspacing="0" border="0" width="100%">';
        foreach (explode("\n", $text) as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $html .= '<tr><td colspan="2" style="font-size:3px;line-height:3px;">&nbsp;</td></tr>';
                continue;
            }

            $colon = mb_strpos($trim, ' : ', 0, 'UTF-8');
            if ($colon !== false && $colon > 0 && $colon <= 42) {
                $label = mb_substr($trim, 0, $colon, 'UTF-8');
                $value = mb_substr($trim, $colon + 3, 400, 'UTF-8');
                $html .= '<tr>'
                    . '<td width="44%" style="font-size:9.5px;font-weight:bold;color:' . $ink
                    . ';border-bottom:0.4px solid #e2e8f0;">' . $this->e($label) . '</td>'
                    . '<td width="56%" style="font-size:9.5px;color:' . $ink
                    . ';border-bottom:0.4px solid #e2e8f0;">' . $this->e($value) . '</td>'
                    . '</tr>';
                continue;
            }

            if ($this->isReportHeading($trim)) {
                $html .= '<tr><td colspan="2" style="font-size:8.5px;font-weight:bold;color:' . $accent
                    . ';letter-spacing:0.8px;border-bottom:0.7px solid ' . $accent . ';">'
                    . $this->e($trim) . '</td></tr>';
                continue;
            }

            $html .= '<tr><td colspan="2" style="font-size:9.5px;color:' . $ink . ';">'
                . $this->e($trim) . '</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private function isReportHeading(string $line): bool
    {
        if ($line === '' || str_contains($line, ' : ') || str_ends_with($line, '.')) {
            return false;
        }
        $upper = mb_strtoupper($line, 'UTF-8');

        return $upper === $line && mb_strlen($line) <= 56;
    }

    private function emptyState(string $message): string
    {
        $muted = $this->palette['muted'];
        $soft = $this->palette['soft'];

        return '<table cellpadding="10" cellspacing="0" border="1" width="100%" style="border-color:#cbd5e1;background-color:'
            . $soft . ';"><tr><td style="text-align:center;font-size:9px;color:' . $muted
            . ';font-style:italic;">' . $this->e($message) . '</td></tr></table>';
    }

    /**
     * @param list<string> $headers
     * @param list<string> $widths percent widths as strings e.g. "12%"
     */
    private function openDataTable(array $headers, array $widths): string
    {
        $accent = $this->palette['accent'];
        $html = '<table cellpadding="4" cellspacing="0" border="1" width="100%" style="border-color:#334155;font-size:8.5px;border-collapse:collapse;">'
            . '<tr style="background-color:' . $accent . ';color:#ffffff;">';
        foreach ($headers as $i => $label) {
            $w = $widths[$i] ?? '';
            $html .= '<th' . ($w !== '' ? ' width="' . $w . '"' : '')
                . ' style="font-size:7px;font-weight:bold;letter-spacing:0.5px;">'
                . $this->e($label) . '</th>';
        }
        $html .= '</tr>';

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $people
     */
    private function peopleHtml(array $people): string
    {
        $html = $this->sectionTitle('Personnes rattachées');
        if ($people === []) {
            return $html . $this->emptyState('Aucune personne rattachée à ce dossier.');
        }

        $html .= $this->openDataTable(
            ['Réf.', 'Identité', 'Statut', 'Biométrie', 'Croisement'],
            ['10%', '28%', '16%', '22%', '24%']
        );

        foreach ($people as $i => $p) {
            $ref = sprintf('P%02d', $i + 1);
            $samples = is_array($p['biometric_samples'] ?? null) ? $p['biometric_samples'] : [];
            $bioBits = [];
            foreach ($samples as $s) {
                $kind = (string) ($s['kind_label'] ?? $s['sample_type_label'] ?? $s['kind'] ?? 'Relevé');
                $bioBits[] = $kind;
            }
            $bio = $bioBits === [] ? '—' : implode(', ', array_unique($bioBits));

            $wl = is_array($p['watchlist'] ?? null) ? $p['watchlist'] : [];
            $wlLabel = 'Aucun signalement';
            if ($wl !== [] && array_is_list($wl)) {
                $top = $wl[0];
                $score = (int) ($top['score'] ?? 0);
                $reason = (string) ($top['reason'] ?? 'correspondance');
                $entryName = (string) (($top['entry']['display_name'] ?? $top['entry']['name'] ?? '') ?: 'liste de surveillance');
                $wlLabel = sprintf('%s (%d%% — %s)', $entryName, $score, $reason);
            } elseif (!empty($wl['matched']) || !empty($wl['hit'])) {
                $wlLabel = (string) ($wl['label'] ?? $wl['verdict_label'] ?? 'Correspondance signalée');
            } elseif (!empty($wl['verdict_label'])) {
                $wlLabel = (string) $wl['verdict_label'];
            }

            $rowBg = ($i % 2 === 1) ? 'background-color:' . $this->palette['banner'] . ';' : '';
            $alias = !empty($p['alias'])
                ? '<br/><span style="font-size:7.5px;color:' . $this->palette['muted'] . ';">« '
                    . $this->e((string) $p['alias']) . ' »</span>'
                : '';

            $html .= '<tr style="' . $rowBg . '">'
                . '<td style="font-family:courier;font-weight:bold;color:' . $this->palette['accent'] . ';">'
                . $this->e($ref) . '</td>'
                . '<td><strong>' . $this->e((string) ($p['display_name'] ?? '—')) . '</strong>' . $alias . '</td>'
                . '<td>' . $this->e((string) ($p['status_label'] ?? '')) . '</td>'
                . '<td>' . $this->e($bio) . '</td>'
                . '<td>' . $this->e($wlLabel) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $sites
     */
    private function sitesHtml(array $sites): string
    {
        $html = $this->sectionTitle('Sites exploités');
        if ($sites === []) {
            return $html . $this->emptyState('Aucun site rattaché.');
        }

        $accent = $this->palette['accent'];
        $bannerBg = $this->palette['banner'];
        $ink = $this->palette['ink'];
        $muted = $this->palette['muted'];

        foreach ($sites as $site) {
            $rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
            $seizures = is_array($site['seizures'] ?? null) ? $site['seizures'] : [];
            $done = count(array_filter($rooms, static fn (array $r): bool => !empty($r['checked'])));

            $html .= '<table cellpadding="0" cellspacing="0" border="1" width="100%" style="border-color:'
                . $accent . ';margin-bottom:8px;">'
                . '<tr style="background-color:' . $bannerBg . ';"><td style="padding:5px;">'
                . '<span style="font-size:10px;font-weight:bold;color:' . $ink . ';">'
                . $this->e((string) ($site['reference_code'] ?? ''))
                . ' — ' . $this->e((string) ($site['name'] ?? ''))
                . '</span><br/>'
                . '<span style="font-size:7.5px;color:' . $muted . ';">'
                . $this->e((string) ($site['site_type_label'] ?? ''))
                . ' · ' . $this->e((string) ($site['status_label'] ?? ''))
                . ' · Fouille : ' . $done . '/' . count($rooms)
                . ' · Saisies : ' . count($seizures)
                . '</span></td></tr><tr><td style="padding:6px;background-color:#ffffff;">';

            if ($rooms !== []) {
                $html .= '<div style="font-size:7.5px;font-weight:bold;color:' . $accent
                    . ';letter-spacing:0.6px;margin-bottom:3px;">PIÈCES</div>'
                    . '<p style="font-size:8.5px;color:' . $ink . ';margin:0 0 6px 0;">';
                $bits = [];
                foreach ($rooms as $r) {
                    $mark = !empty($r['checked']) ? '■' : '□';
                    $bits[] = $mark . ' ' . $this->e((string) ($r['label'] ?? 'Pièce'));
                }
                $html .= implode(' · ', $bits) . '</p>';
            }

            if ($seizures !== []) {
                $html .= '<div style="font-size:7.5px;font-weight:bold;color:' . $accent
                    . ';letter-spacing:0.6px;margin-bottom:3px;">SAISIES</div><ul style="font-size:8.5px;margin:0;padding-left:14px;">';
                foreach (array_slice($seizures, 0, 40) as $sz) {
                    $html .= '<li>' . $this->e((string) ($sz['label'] ?? $sz['item_label'] ?? 'Saisie'));
                    if (!empty($sz['quantity'])) {
                        $html .= ' × ' . $this->e((string) $sz['quantity']);
                    }
                    if (!empty($sz['location_label'])) {
                        $html .= ' — ' . $this->e((string) $sz['location_label']);
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }

            if ($rooms === [] && $seizures === []) {
                $html .= '<p style="font-size:8px;color:' . $muted . ';font-style:italic;margin:0;">'
                    . 'Aucun détail de fouille versé.</p>';
            }

            $html .= '</td></tr></table>';
        }

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $relations
     * @param list<array<string,mixed>> $people
     * @param list<array<string,mixed>> $sites
     */
    private function relationsHtml(array $relations, array $people, array $sites): string
    {
        $html = $this->sectionTitle('Corrélations');
        if ($relations === []) {
            return $html . $this->emptyState('Aucune corrélation enregistrée sur ce dossier.');
        }

        $personNames = [];
        foreach ($people as $p) {
            $personNames[(int) ($p['id'] ?? 0)] = (string) ($p['display_name'] ?? 'Personne');
        }
        $siteNames = [];
        foreach ($sites as $s) {
            $siteNames[(int) ($s['id'] ?? 0)] = (string) ($s['name'] ?? $s['reference_code'] ?? 'Site');
        }

        $html .= $this->openDataTable(
            ['De', 'Lien', 'Vers', 'Fiabilité', 'Note'],
            ['22%', '16%', '22%', '14%', '26%']
        );

        foreach ($relations as $i => $rel) {
            $from = $this->entityLabel((string) ($rel['from_type'] ?? ''), (int) ($rel['from_id'] ?? 0), $personNames, $siteNames);
            $to = $this->entityLabel((string) ($rel['to_type'] ?? ''), (int) ($rel['to_id'] ?? 0), $personNames, $siteNames);
            $link = SseCorrelationService::relationLabel((string) ($rel['relation'] ?? ''));
            $relia = SseCorrelationService::reliabilityLabel((string) ($rel['reliability'] ?? ''));
            $rowBg = ($i % 2 === 1) ? 'background-color:' . $this->palette['banner'] . ';' : '';
            $html .= '<tr style="' . $rowBg . '">'
                . '<td>' . $this->e($from) . '</td>'
                . '<td><strong style="color:' . $this->palette['accent'] . ';">' . $this->e($link) . '</strong></td>'
                . '<td>' . $this->e($to) . '</td>'
                . '<td>' . $this->e($relia) . '</td>'
                . '<td>' . $this->e((string) ($rel['note'] ?? '—')) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @param array<int,string> $personNames
     * @param array<int,string> $siteNames
     */
    private function entityLabel(string $type, int $id, array $personNames, array $siteNames): string
    {
        $t = strtolower($type);
        if (in_array($t, ['person', 'personne', 'people'], true)) {
            return $personNames[$id] ?? ('Personne #' . $id);
        }
        if (in_array($t, ['site', 'lieu'], true)) {
            return $siteNames[$id] ?? ('Site #' . $id);
        }

        return SseCorrelationService::nodeTypeLabel($t) . ' n° ' . $id;
    }

    /**
     * @param list<array<string,mixed>> $notes
     */
    private function notesHtml(array $notes): string
    {
        $html = $this->sectionTitle('Notes classifiées');
        if ($notes === []) {
            return $html . $this->emptyState('Aucune note.');
        }

        $accent = $this->palette['accent'];
        $bannerBg = $this->palette['banner'];
        $ink = $this->palette['ink'];
        $muted = $this->palette['muted'];

        foreach ($notes as $n) {
            $html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:7px;">'
                . '<tr>'
                . '<td width="1.6%" style="background-color:' . $accent . ';"></td>'
                . '<td width="98.4%" style="border:1px solid #cbd5e1;border-left:0;">'
                . '<table cellpadding="5" cellspacing="0" border="0" width="100%">'
                . '<tr style="background-color:' . $bannerBg . ';"><td style="font-size:7px;color:'
                . $muted . ';">'
                . $this->e((string) ($n['classification_label'] ?? ''))
                . ' · ' . $this->e((string) ($n['author_label'] ?? 'Opérateur'))
                . (!empty($n['created_at']) ? ' · ' . $this->e((string) $n['created_at']) : '')
                . '</td></tr>'
                . '<tr><td style="font-size:9px;color:' . $ink . ';line-height:1.4;">'
                . nl2br($this->e((string) ($n['body'] ?? '')))
                . '</td></tr></table></td></tr></table>';
        }

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $evidence
     */
    private function evidenceHtml(array $evidence): string
    {
        $html = $this->sectionTitle('Preuves recensées');
        if ($evidence === []) {
            return $html . $this->emptyState('Aucune preuve jointe.');
        }

        $html .= $this->openDataTable(
            ['N°', 'Libellé', 'Légende', 'Versée par', 'Date'],
            ['8%', '24%', '30%', '20%', '18%']
        );

        foreach ($evidence as $i => $ev) {
            $rowBg = ($i % 2 === 1) ? 'background-color:' . $this->palette['banner'] . ';' : '';
            $label = (string) ($ev['label'] ?? 'Preuve');
            if (!empty($ev['image_path'])) {
                $label .= ' ✦';
            }
            $html .= '<tr style="' . $rowBg . '">'
                . '<td style="font-family:courier;font-weight:bold;color:' . $this->palette['accent'] . ';">'
                . sprintf('%02d', $i + 1) . '</td>'
                . '<td><strong>' . $this->e($label) . '</strong></td>'
                . '<td>' . $this->e((string) ($ev['caption'] ?? '—')) . '</td>'
                . '<td>' . $this->e((string) ($ev['author_label'] ?? '—')) . '</td>'
                . '<td>' . $this->e((string) ($ev['created_at'] ?? '—')) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        $html .= '<p style="font-size:7.5px;color:' . $this->palette['muted'] . ';margin-top:6px;">'
            . '✦ Image jointe reproduite dans les pages suivantes, dans la limite de l’espace du document.'
            . '</p>';

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $evidence
     */
    private function appendEvidenceImages(\TCPDF $pdf, array $evidence, string $redactedLabel = ''): void
    {
        $shown = 0;
        foreach ($evidence as $i => $e) {
            if ($shown >= 12) {
                break;
            }
            $path = (string) ($e['image_path'] ?? '');
            if ($path === '') {
                continue;
            }
            $abs = $this->resolvePublicFile($path);
            if ($abs === null) {
                continue;
            }

            try {
                $pdf->AddPage();
                $pdf->writeHTML(
                    $this->sectionBanner($redactedLabel, sprintf('07.%02d · Pièce visuelle', $i + 1)),
                    true,
                    false,
                    true,
                    false,
                    ''
                );
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(11, 18, 32);
                $pdf->Write(6, (string) ($e['label'] ?? 'Preuve'), '', false, 'L', true);
                if (!empty($e['caption'])) {
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetTextColor(100, 116, 139);
                    $pdf->Write(5, (string) $e['caption'], '', false, 'L', true);
                }
                $pdf->Ln(3);
                $pdf->SetDrawColor(...$this->hexToRgb($this->palette['accent']));
                $pdf->SetLineWidth(0.4);
                $y = $pdf->GetY();
                $pdf->Rect(12, $y, 186, 0.01);
                $pdf->Ln(2);
                $pdf->Image($abs, 14, $pdf->GetY(), 176, 0, '', '', '', false, 150, '', false, false, 0, false, false, false);
                $shown++;
            } catch (\Throwable) {
                // Image illisible : on continue sans faire échouer tout l'export.
            }
        }
    }

    private function resolvePublicFile(string $relative): ?string
    {
        $rel = ltrim(str_replace(['\\', '..'], ['/', ''], $relative), '/');
        $candidates = [
            base_path('public/' . $rel),
            base_path($rel),
        ];
        foreach ($candidates as $cand) {
            if (is_file($cand) && is_readable($cand)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return [71, 85, 105];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
