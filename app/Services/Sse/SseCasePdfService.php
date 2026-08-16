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
            $inline
        ): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }

            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Athena COMSPEC');
            $pdf->SetAuthor('Portail SSE Athena');
            $pdf->SetTitle('Dossier complet ' . (string) ($case['reference_code'] ?? ''));
            $pdf->SetMargins(14, 16, 14);
            $pdf->SetAutoPageBreak(true, 18);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->setFooterMargin(10);
            $refFooter = (string) ($case['reference_code'] ?? '');
            $pdf->setFooterData([], [80, 80, 80]);
            // Footer custom via callback-like override is heavy; stamp page meta in HTML instead.

            // —— Page de garde ——
            $pdf->AddPage();
            $pdf->writeHTML($this->coverHtml($case, $redactedLabel, $generatedAt, $people, $sites, $notes, $evidence), true, false, true, false, '');

            // —— Flash ——
            $pdf->AddPage();
            $pdf->writeHTML($this->sectionBanner($case, $redactedLabel) . $this->preBlock('Flash opérationnel', $flash), true, false, true, false, '');

            // —— Compte rendu initial ——
            $pdf->AddPage();
            $pdf->writeHTML($this->sectionBanner($case, $redactedLabel) . $this->preBlock('Compte rendu initial', $initial), true, false, true, false, '');

            // —— Personnes ——
            $pdf->AddPage();
            $pdf->writeHTML($this->sectionBanner($case, $redactedLabel) . $this->peopleHtml($people), true, false, true, false, '');

            // —— Sites ——
            $pdf->AddPage();
            $pdf->writeHTML($this->sectionBanner($case, $redactedLabel) . $this->sitesHtml($sites), true, false, true, false, '');

            // —— Corrélations ——
            $pdf->AddPage();
            $pdf->writeHTML($this->sectionBanner($case, $redactedLabel) . $this->relationsHtml($relations, $people, $sites), true, false, true, false, '');

            // —— Notes ——
            $pdf->AddPage();
            $pdf->writeHTML($this->sectionBanner($case, $redactedLabel) . $this->notesHtml($notes), true, false, true, false, '');

            // —— Preuves (+ images) ——
            $pdf->AddPage();
            $pdf->writeHTML($this->sectionBanner($case, $redactedLabel) . $this->evidenceHtml($evidence), true, false, true, false, '');
            $this->appendEvidenceImages($pdf, $evidence);

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
        $classCode = SseCaseRepository::normalizeClassification((string) ($case['classification'] ?? 'encadrement'));
        $palette = $this->classPalette($classCode);
        $accent = $palette['accent'];
        $bannerBg = $palette['banner'];
        $sealColor = $palette['seal'];

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

        $html = '';
        if ($redactedLabel !== '') {
            $html .= '<div style="background-color:#8f1d1d;color:#ffffff;padding:6px;text-align:center;font-size:9px;margin-bottom:6px;">'
                . $e($redactedLabel)
                . '</div>';
        }

        // Bandeau classification (haut)
        $html .= '<table cellpadding="4" cellspacing="0" border="0" width="100%" style="background-color:'
            . $bannerBg . ';border:1px solid ' . $accent . ';">'
            . '<tr>'
            . '<td width="28%" style="font-size:7px;color:#64748b;text-align:left;">(CLASSIFICATION DE SÉCURITÉ)</td>'
            . '<td width="44%" style="font-size:14px;font-weight:bold;color:' . $accent . ';text-align:center;letter-spacing:2px;">'
            . $e($classUpper) . '</td>'
            . '<td width="28%" style="font-size:7px;color:#64748b;text-align:right;">EXEMPLAIRE '
            . (int) ($marks['copy_index'] ?? 1) . '/' . (int) ($marks['copy_total'] ?? 1) . '</td>'
            . '</tr></table>';

        // Registre + boîte de contrôle
        $html .= '<br/><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr>';
        $html .= '<td width="68%" valign="top">';
        $html .= '<table cellpadding="3" cellspacing="0" border="1" width="100%" style="border-color:#334155;font-size:8px;">'
            . '<tr style="background-color:#334155;color:#ffffff;">'
            . '<td colspan="4" style="font-size:8px;font-weight:bold;letter-spacing:1px;">REGISTRE DE CONSULTATION</td></tr>'
            . '<tr style="background-color:#f1f5f9;font-weight:bold;">'
            . '<td width="10%">N°</td><td width="48%">CONSULTANT</td><td width="22%">DATE</td><td width="20%">VISA</td></tr>';
        $routing = is_array($marks['routing'] ?? null) ? $marks['routing'] : [];
        foreach ($routing as $row) {
            if (!is_array($row)) {
                continue;
            }
            $html .= '<tr>'
                . '<td>' . (int) ($row['slot'] ?? 0) . '</td>'
                . '<td>' . $e((string) ($row['holder'] ?? '')) . '</td>'
                . '<td>' . $e((string) ($row['date'] ?? '')) . '</td>'
                . '<td style="font-family:courier;letter-spacing:1px;">' . $e((string) ($row['initials'] ?? '')) . '</td>'
                . '</tr>';
        }
        $html .= '</table></td>';
        $html .= '<td width="3%"></td>';
        $html .= '<td width="29%" valign="top">';
        $html .= '<table cellpadding="4" cellspacing="0" border="1" width="100%" style="border-color:#334155;font-size:7.5px;">'
            . '<tr><td><span style="color:#64748b;">CONTRÔLE N°</span><br/><strong>' . $e((string) ($marks['control_number'] ?? '')) . '</strong></td></tr>'
            . '<tr><td><span style="color:#64748b;">REGISTRE</span><br/><strong>' . $e((string) ($marks['registry_number'] ?? '')) . '</strong></td></tr>'
            . '<tr><td><span style="color:#64748b;">OUVERT LE</span><br/><strong>' . $e($openedFr) . '</strong></td></tr>'
            . '<tr><td><span style="color:#64748b;">MOUVEMENT</span><br/><strong>' . $e($updatedFr) . '</strong></td></tr>'
            . '</table></td>';
        $html .= '</tr></table>';

        // Canal protégé
        $caveats = is_array($marks['caveats'] ?? null) ? $marks['caveats'] : [];
        $html .= '<br/><table cellpadding="6" cellspacing="0" border="1" width="100%" style="border-color:'
            . $accent . ';background-color:' . $bannerBg . ';">'
            . '<tr><td>'
            . '<div style="font-size:11px;font-weight:bold;color:' . $accent . ';letter-spacing:1px;">'
            . $e((string) ($marks['channel'] ?? 'CANAL PROTÉGÉ')) . '</div>'
            . '<div style="font-size:8px;color:#334155;margin-top:3px;">'
            . 'Chemise à ne pas dissocier de ses pièces jointes. Toute sortie du local sécurisé est portée au registre de consultation.'
            . '</div>';
        if ($caveats !== []) {
            $html .= '<div style="margin-top:5px;font-size:7.5px;">';
            foreach ($caveats as $caveat) {
                $html .= '<span style="border:1px solid ' . $accent . ';color:' . $accent
                    . ';padding:2px 5px;margin-right:4px;font-weight:bold;">'
                    . $e((string) $caveat) . '</span> ';
            }
            $html .= '</div>';
        }
        $html .= '</td></tr></table>';

        // En-tête unité + sceau
        $html .= '<br/><table cellpadding="2" cellspacing="0" border="0" width="100%"><tr>';
        $html .= '<td width="55%" valign="top" style="font-size:9px;color:#0b1220;line-height:1.45;">'
            . '<div>ATHENA · COMPSEC</div>'
            . '<div style="font-weight:bold;text-decoration:underline;margin-top:2px;">UNITÉ : ' . $e($coverUnit) . '</div>'
            . '<div>SECTION : Bureau SSE — Renseignement</div>'
            . '<div style="margin-top:4px;font-weight:bold;">DOSSIER N° ' . $e($caseRef) . '</div>'
            . '</td>';
        $html .= '<td width="20%" align="center" valign="middle">';
        $html .= '<table cellpadding="6" cellspacing="0" border="2" width="100%" style="border-color:'
            . $sealColor . ';color:' . $sealColor . ';">'
            . '<tr><td align="center" style="font-size:6px;letter-spacing:1px;">BUREAU SSE</td></tr>'
            . '<tr><td align="center" style="font-size:16px;font-weight:bold;">' . $e((string) ($marks['seal_initials'] ?? 'UA')) . '</td></tr>'
            . '<tr><td align="center" style="font-size:6px;letter-spacing:1px;">DOSSIERS</td></tr>'
            . '</table></td>';
        $html .= '<td width="25%" align="right" valign="top" style="font-size:9px;color:#0b1220;">Le ' . $e($updatedFr) . '</td>';
        $html .= '</tr></table>';

        $html .= '<hr style="border:0;border-top:2px solid ' . $accent . ';margin:8px 0 6px 0;"/>';
        $html .= '<div style="text-align:center;font-size:13px;font-weight:bold;color:#0b1220;margin:4px 0 8px 0;">'
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
        $html .= '<table cellpadding="4" cellspacing="0" border="1" width="100%" style="border-color:#0b1220;font-size:8px;">';
        for ($i = 0; $i < 8; $i += 4) {
            $html .= '<tr>';
            for ($j = 0; $j < 4; $j++) {
                [$lab, $val] = $facts[$i + $j];
                $html .= '<td width="25%"><span style="font-size:6.5px;color:#64748b;letter-spacing:0.5px;text-transform:uppercase;">'
                    . $e($lab) . '</span><br/><strong style="font-size:9px;color:#0b1220;">' . $e($val) . '</strong></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        // Objet
        $html .= '<br/><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
            . '<td width="4" style="background-color:' . $accent . ';"></td>'
            . '<td style="padding-left:6px;font-size:10px;font-weight:bold;color:#0b1220;letter-spacing:1px;">OBJET DU DOSSIER</td>'
            . '</tr></table>';
        if ($summary !== '') {
            $html .= '<p style="font-size:9.5px;color:#1e293b;line-height:1.4;text-align:justify;">'
                . nl2br($e($summary)) . '</p>';
        } else {
            $html .= '<p style="font-size:9px;color:#64748b;font-style:italic;">'
                . 'Aucune synthèse n’a encore été portée à la chemise.</p>';
        }

        // Consignes (compactes pour tenir sur la page)
        $html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
            . '<td width="4" style="background-color:' . $accent . ';"></td>'
            . '<td style="padding-left:6px;font-size:10px;font-weight:bold;color:#0b1220;letter-spacing:1px;">CONSIGNES DE MANIPULATION</td>'
            . '</tr></table>';
        $html .= '<p style="font-size:8px;color:#334155;line-height:1.35;">'
            . 'Le dossier <strong>' . $e($caseRef) . '</strong> regroupe des pièces de niveaux de protection différents. '
            . 'Le niveau retenu pour l’ensemble est <strong>' . $e($classLabel) . '</strong>. '
            . 'Consultation au poste habilité, chemise complète ; restitution le jour même contre visa ; '
            . 'reproduction interdite sans accord écrit du chef de bureau.'
            . '</p>';
        $html .= '<p style="font-size:8px;color:#334155;">'
            . 'Révision de classification prévue le <strong>' . $e((string) ($marks['declassify_on'] ?? '—')) . '</strong>. '
            . 'Conservation <strong>' . $e((string) ($marks['destruction_delay'] ?? '—')) . '</strong> après clôture.'
            . '</p>';

        // Auth : QR + empreintes
        $html .= '<br/><table cellpadding="4" cellspacing="0" border="0" width="100%"><tr>';
        $html .= '<td width="28%" valign="top" align="center">';
        if ($wsQr !== '') {
            // TCPDF : data-URI PNG, taille réduite pour la chemise
            $html .= preg_replace(
                ['/\sclass="[^"]*"/', '/\swidth="\d+"/', '/\sheight="\d+"/'],
                ['', ' width="70"', ' height="70"'],
                $wsQr
            ) ?? $wsQr;
        }
        $html .= '<div style="font-size:7px;margin-top:3px;"><strong>' . $e($wsId) . '</strong><br/>'
            . $e($wsHost) . ' · ' . $e($wsIp) . '</div></td>';
        $html .= '<td width="72%" valign="top" style="font-size:7.5px;color:#0b1220;">'
            . '<div style="font-weight:bold;font-size:8px;margin-bottom:3px;">EMPREINTES D’INTÉGRITÉ</div>'
            . '<strong>Condensat</strong> : <span style="font-family:courier;">' . $e((string) ($marks['integrity_groups'] ?? '')) . '</span><br/>'
            . '<strong>Enveloppe</strong> : <span style="font-family:courier;">' . $e((string) ($marks['envelope_hash'] ?? '')) . '</span><br/>'
            . '<strong>Machine</strong> : <span style="font-family:courier;">' . $e($wsFp !== '' ? $wsFp : '—') . '</span><br/>'
            . '<strong>Contrôle</strong> : <span style="font-family:courier;">' . $e((string) ($marks['checksum'] ?? '')) . '</span>'
            . ' · ' . $e((string) ($marks['algorithm'] ?? ''))
            . '</td></tr></table>';

        // Tampons
        $html .= '<br/><table cellpadding="3" cellspacing="4" border="0"><tr>'
            . '<td style="border:2px solid ' . $accent . ';color:' . $accent . ';font-size:8px;font-weight:bold;letter-spacing:1px;">'
            . $e($classUpper) . '</td>'
            . '<td style="border:2px solid #15803d;color:#15803d;font-size:8px;font-weight:bold;letter-spacing:1px;">'
            . ($isClosed ? 'DOSSIER CLOS' : 'DOSSIER OUVERT') . '</td>'
            . '<td style="border:2px dashed #64748b;color:#64748b;font-size:8px;font-weight:bold;">'
            . 'EXEMPLAIRE ' . (int) ($marks['copy_index'] ?? 1) . ' / ' . (int) ($marks['copy_total'] ?? 1) . '</td>'
            . '</tr></table>';

        // Bandeau bas
        $html .= '<br/><table cellpadding="4" cellspacing="0" border="0" width="100%" style="background-color:'
            . $bannerBg . ';border:1px solid ' . $accent . ';">'
            . '<tr>'
            . '<td width="33%" style="font-size:7px;color:#64748b;">Contrôle ' . $e((string) ($marks['control_number'] ?? '')) . '</td>'
            . '<td width="34%" style="font-size:11px;font-weight:bold;color:' . $accent . ';text-align:center;">' . $e($classUpper) . '</td>'
            . '<td width="33%" style="font-size:7px;color:#64748b;text-align:right;">Chemise — page de garde · ' . $e($generatedAt) . '</td>'
            . '</tr></table>';

        return $html;
    }

    /**
     * @return array{accent:string,banner:string,seal:string}
     */
    private function classPalette(string $classCode): array
    {
        return match ($classCode) {
            'tres_restreint' => ['accent' => '#b91c1c', 'banner' => '#fef2f2', 'seal' => '#991b1b'],
            'confidentiel' => ['accent' => '#b45309', 'banner' => '#fffbeb', 'seal' => '#92400e'],
            'encadrement' => ['accent' => '#1d4ed8', 'banner' => '#eff6ff', 'seal' => '#1e3a8a'],
            default => ['accent' => '#475569', 'banner' => '#f8fafc', 'seal' => '#334155'],
        };
    }

    /**
     * @param array<string,mixed> $case
     */
    private function sectionBanner(array $case, string $redactedLabel): string
    {
        $classLabel = (string) ($case['classification_label'] ?? '');
        $classUpper = mb_strtoupper($classLabel !== '' ? $classLabel : 'CONFIDENTIEL', 'UTF-8');
        $classCode = SseCaseRepository::normalizeClassification((string) ($case['classification'] ?? 'encadrement'));
        $palette = $this->classPalette($classCode);
        $accent = $palette['accent'];
        $bannerBg = $palette['banner'];

        $html = '<table cellpadding="3" cellspacing="0" border="0" width="100%" style="background-color:'
            . $bannerBg . ';border:1px solid ' . $accent . ';margin-bottom:8px;">'
            . '<tr>'
            . '<td width="30%" style="font-size:7px;color:#64748b;">'
            . $this->e((string) ($case['reference_code'] ?? '')) . '</td>'
            . '<td width="40%" style="font-size:10px;font-weight:bold;color:' . $accent . ';text-align:center;letter-spacing:1px;">'
            . $this->e($classUpper) . '</td>'
            . '<td width="30%" style="font-size:7px;color:#64748b;text-align:right;">'
            . $this->e((string) ($case['title'] ?? '')) . '</td>'
            . '</tr></table>';
        if ($redactedLabel !== '') {
            $html .= '<div style="background-color:#8f1d1d;color:#ffffff;padding:4px;font-size:8px;text-align:center;margin-bottom:6px;">'
                . $this->e($redactedLabel) . '</div>';
        }

        return $html;
    }

    private function preBlock(string $title, string $body): string
    {
        $text = trim($body) !== '' ? $body : '(aucun contenu généré)';

        return '<h2 style="font-size:14px;">' . $this->e($title) . '</h2>'
            . '<pre style="font-size:9.5px;line-height:1.35;font-family:courier;white-space:pre-wrap;">'
            . $this->e($text)
            . '</pre>';
    }

    /**
     * @param list<array<string,mixed>> $people
     */
    private function peopleHtml(array $people): string
    {
        $html = '<h2 style="font-size:14px;">Personnes rattachées</h2>';
        if ($people === []) {
            return $html . '<p style="font-size:11px;">Aucune personne rattachée.</p>';
        }

        $html .= '<table cellpadding="4" border="1" style="font-size:9px;border-collapse:collapse;width:100%;">'
            . '<tr style="background-color:#eee;"><th width="12%">Réf.</th><th width="28%">Identité</th>'
            . '<th width="18%">Statut</th><th width="22%">Biométrie</th><th width="20%">Croisement</th></tr>';

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

            $html .= '<tr>'
                . '<td>' . $this->e($ref) . '</td>'
                . '<td><strong>' . $this->e((string) ($p['display_name'] ?? '—')) . '</strong>'
                . (!empty($p['alias']) ? '<br/><em>' . $this->e((string) $p['alias']) . '</em>' : '')
                . '</td>'
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
        $html = '<h2 style="font-size:14px;">Sites exploités</h2>';
        if ($sites === []) {
            return $html . '<p style="font-size:11px;">Aucun site rattaché.</p>';
        }

        foreach ($sites as $site) {
            $rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
            $seizures = is_array($site['seizures'] ?? null) ? $site['seizures'] : [];
            $done = count(array_filter($rooms, static fn (array $r): bool => !empty($r['checked'])));

            $html .= '<h3 style="font-size:12px;margin-bottom:2px;">'
                . $this->e((string) ($site['reference_code'] ?? ''))
                . ' — ' . $this->e((string) ($site['name'] ?? ''))
                . '</h3>';
            $html .= '<p style="font-size:10px;">'
                . $this->e((string) ($site['site_type_label'] ?? ''))
                . ' · ' . $this->e((string) ($site['status_label'] ?? ''))
                . ' · Fouille : ' . $done . '/' . count($rooms)
                . ' · Saisies : ' . count($seizures)
                . '</p>';

            if ($rooms !== []) {
                $html .= '<p style="font-size:9px;"><strong>Pièces :</strong> ';
                $bits = [];
                foreach ($rooms as $r) {
                    $mark = !empty($r['checked']) ? '✓' : '○';
                    $bits[] = $mark . ' ' . $this->e((string) ($r['label'] ?? 'Pièce'));
                }
                $html .= implode(' · ', $bits) . '</p>';
            }

            if ($seizures !== []) {
                $html .= '<ul style="font-size:9px;">';
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
        $html = '<h2 style="font-size:14px;">Corrélations</h2>';
        if ($relations === []) {
            return $html . '<p style="font-size:11px;">Aucune corrélation enregistrée sur ce dossier.</p>';
        }

        $personNames = [];
        foreach ($people as $p) {
            $personNames[(int) ($p['id'] ?? 0)] = (string) ($p['display_name'] ?? 'Personne');
        }
        $siteNames = [];
        foreach ($sites as $s) {
            $siteNames[(int) ($s['id'] ?? 0)] = (string) ($s['name'] ?? $s['reference_code'] ?? 'Site');
        }

        $html .= '<table cellpadding="3" border="1" style="font-size:9px;border-collapse:collapse;width:100%;">'
            . '<tr style="background-color:#eee;"><th>De</th><th>Lien</th><th>Vers</th><th>Fiabilité</th><th>Note</th></tr>';

        foreach ($relations as $rel) {
            $from = $this->entityLabel((string) ($rel['from_type'] ?? ''), (int) ($rel['from_id'] ?? 0), $personNames, $siteNames);
            $to = $this->entityLabel((string) ($rel['to_type'] ?? ''), (int) ($rel['to_id'] ?? 0), $personNames, $siteNames);
            $link = SseCorrelationService::relationLabel((string) ($rel['relation'] ?? ''));
            $relia = SseCorrelationService::reliabilityLabel((string) ($rel['reliability'] ?? ''));
            $html .= '<tr>'
                . '<td>' . $this->e($from) . '</td>'
                . '<td>' . $this->e($link) . '</td>'
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
        $html = '<h2 style="font-size:14px;">Notes classifiées</h2>';
        if ($notes === []) {
            return $html . '<p style="font-size:11px;">Aucune note.</p>';
        }

        foreach ($notes as $n) {
            $html .= '<div style="margin-bottom:8px;padding:6px;border:1px solid #ddd;">'
                . '<div style="font-size:9px;color:#555;">'
                . $this->e((string) ($n['classification_label'] ?? ''))
                . ' · ' . $this->e((string) ($n['author_label'] ?? 'Opérateur'))
                . (!empty($n['created_at']) ? ' · ' . $this->e((string) $n['created_at']) : '')
                . '</div>'
                . '<p style="font-size:10px;margin:4px 0 0;">'
                . nl2br($this->e((string) ($n['body'] ?? '')))
                . '</p></div>';
        }

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $evidence
     */
    private function evidenceHtml(array $evidence): string
    {
        $html = '<h2 style="font-size:14px;">Preuves recensées</h2>';
        if ($evidence === []) {
            return $html . '<p style="font-size:11px;">Aucune preuve jointe.</p>';
        }

        $html .= '<ol style="font-size:10px;">';
        foreach ($evidence as $e) {
            $html .= '<li><strong>' . $this->e((string) ($e['label'] ?? 'Preuve')) . '</strong>';
            if (!empty($e['caption'])) {
                $html .= ' — ' . $this->e((string) $e['caption']);
            }
            if (!empty($e['author_label'])) {
                $html .= ' <em>(' . $this->e((string) $e['author_label']) . ')</em>';
            }
            if (!empty($e['created_at'])) {
                $html .= ' · ' . $this->e((string) $e['created_at']);
            }
            if (!empty($e['image_path'])) {
                $html .= ' · image jointe';
            }
            $html .= '</li>';
        }
        $html .= '</ol>';
        $html .= '<p style="font-size:9px;color:#555;">Les images disponibles sont reproduites ci-après, dans la limite de l’espace du document.</p>';

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $evidence
     */
    private function appendEvidenceImages(\TCPDF $pdf, array $evidence): void
    {
        $shown = 0;
        foreach ($evidence as $e) {
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
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Write(6, (string) ($e['label'] ?? 'Preuve'), '', false, 'L', true);
                if (!empty($e['caption'])) {
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->Write(5, (string) $e['caption'], '', false, 'L', true);
                }
                $pdf->Ln(2);
                $pdf->Image($abs, 14, $pdf->GetY(), 180, 0, '', '', '', false, 150, '', false, false, 0, false, false, false);
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

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
