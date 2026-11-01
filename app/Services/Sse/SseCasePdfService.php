<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\Pdf\AthenaTcpdf;
use App\Support\SseDocumentMarkings;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF complet d’un dossier SSE (couverture, produits, personnes, sites,
 * corrélations, notes, preuves). Toujours produit au niveau d’habilitation demandé.
 *
 * Gabarit Athena (A4 portrait) : l’en-tête porte la communauté et la référence du dossier,
 * le bandeau noir la classification et la version (intégrale / expurgée), le pied la date
 * d’édition et la pagination.
 */
final class SseCasePdfService
{
    /** @var array<string,mixed> */
    private array $caseMeta = [];

    /** @var list<string> fichiers temporaires (QR SVG) supprimés après le rendu */
    private array $tempFiles = [];

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

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use (
            $tenantId,
            $case,
            $people,
            $sites,
            $notes,
            $evidence,
            $relations,
            $flash,
            $initial,
            $inline,
            $releaseLevel
        ): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }

            $binary = $this->buildPdfBinary($case, $people, $sites, $notes, $evidence, $relations, $flash, $initial, $releaseLevel, $tenantId);
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
     * Construit le binaire PDF à partir des données déjà rassemblées (et déjà expurgées au
     * niveau `$releaseLevel`). TCPDF doit être chargé.
     *
     * @param array<string,mixed> $case
     * @param list<array<string,mixed>> $people
     * @param list<array<string,mixed>> $sites
     * @param list<array<string,mixed>> $notes
     * @param list<array<string,mixed>> $evidence
     * @param list<array<string,mixed>> $relations
     */
    public function buildPdfBinary(
        array $case,
        array $people,
        array $sites,
        array $notes,
        array $evidence,
        array $relations,
        string $flash,
        string $initial,
        ?string $releaseLevel = null,
        int $tenantId = 0
    ): string {
        $redactedLabel = '';
        $hidden = [];
        if ($releaseLevel !== null) {
            $hidden = SseRedactionService::summarise($releaseLevel)['hidden'];
            $redactedLabel = $hidden === []
                ? 'Version intégrale — ' . SseRedactionService::levelLabel($releaseLevel)
                : 'VERSION EXPURGÉE — ' . SseRedactionService::levelLabel($releaseLevel)
                    . ' — au noir : ' . implode(', ', $hidden);
        }

        $generatedAt = (new \DateTimeImmutable('now'))->format('d/m/Y H:i');
        $this->caseMeta = $case;

        $ref = (string) ($case['reference_code'] ?? '');
        $classLabel = (string) ($case['classification_label'] ?? 'Confidentiel');
        $version = $releaseLevel === null
            ? 'Version intégrale'
            : ($hidden === [] ? 'Version intégrale' : 'Version expurgée') . ' · ' . SseRedactionService::levelLabel($releaseLevel);

        $community = '';
        if ($tenantId <= 0) {
            $community = trim((string) (Session::get('tenant_name') ?? ''));
        }

        $pdf = Theme::tcpdf([
            'kind' => 'Dossier SSE complet',
            'reference' => $ref !== '' ? $ref : 'Athena SSE',
            'tenant_id' => $tenantId,
            'community' => $community,
            'classification' => $classLabel . ' · besoin d’en connaître',
            'audience' => $version,
            'note' => 'Chemise à ne pas dissocier de ses pièces · reproduction interdite sans accord écrit du chef de bureau',
            'title' => 'Dossier complet ' . $ref,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }

        // —— Page de garde ——
        $pdf->AddPage();
        $pdf->writeAthenaHtml($this->coverHtml($case, $redactedLabel, $generatedAt, $people, $sites, $notes, $evidence));

        $sections = [
            ['01', 'Flash opérationnel', fn (): string => $this->reportSheet('Flash opérationnel', $flash)],
            ['02', 'Compte rendu initial', fn (): string => $this->reportSheet('Compte rendu initial', $initial)],
            ['03', 'Personnes rattachées', fn (): string => $this->peopleHtml($people)],
            ['04', 'Sites exploités', fn (): string => $this->sitesHtml($sites)],
            ['05', 'Corrélations', fn (): string => $this->relationsHtml($relations, $people, $sites)],
            ['06', 'Notes classifiées', fn (): string => $this->notesHtml($notes)],
            ['07', 'Preuves recensées', fn (): string => $this->evidenceHtml($evidence)],
        ];
        foreach ($sections as [$num, $label, $body]) {
            $pdf->AddPage();
            $pdf->writeAthenaHtml($this->sectionBanner($redactedLabel, $num, $label) . $body());
        }
        $this->appendEvidenceImages($pdf, $evidence, $redactedLabel);

        try {
            return (string) $pdf->Output('', 'S');
        } finally {
            foreach ($this->tempFiles as $tmp) {
                @unlink($tmp);
            }
            $this->tempFiles = [];
        }
    }

    /**
     * Ton du gabarit associé au niveau de classification (pastilles et tampons).
     */
    private function classTone(string $classCode): string
    {
        return match ($classCode) {
            'tres_restreint' => 'bad',
            'confidentiel' => 'warn',
            default => '',
        };
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
        $tone = $this->classTone($classCode);
        $toneColor = Theme::toneColor($tone);
        $muted = Theme::MUTED;
        $ink = Theme::INK;
        $line = Theme::LINE;
        $mono = Theme::mono();

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
        $copy = (int) ($marks['copy_index'] ?? 1) . ' / ' . (int) ($marks['copy_total'] ?? 1);

        $e = fn (string $v): string => $this->e($v);

        $html = '';
        if ($redactedLabel !== '') {
            $html .= $this->redactionCallout($redactedLabel);
        }

        // Bloc titre
        $html .= Theme::titleBlock(
            'Chemise de dossier · Bureau SSE',
            $caseTitle,
            [
                [$classUpper, $tone !== '' ? $tone : 'muted'],
                [$isClosed ? 'DOSSIER CLOS' : 'DOSSIER OUVERT', $isClosed ? 'muted' : 'ok'],
                ['EXEMPLAIRE ' . $copy, 'muted'],
            ],
            'Dossier n° ' . $caseRef . ' · Unité : ' . $coverUnit . ' · Section : Bureau SSE — Renseignement'
        );

        // Indicateurs
        $html .= Theme::stats([
            ['label' => 'Personnes rattachées', 'value' => (string) count($people)],
            ['label' => 'Notes classifiées', 'value' => (string) count($notes)],
            ['label' => 'Preuves versées', 'value' => (string) count($evidence)],
            ['label' => 'Sites exploités', 'value' => (string) count($sites)],
        ]);

        // Faits + contrôle
        $facts = Theme::keyValues([
            ['Statut', $statusLabel],
            ['Classification', $classLabel],
            ['Habilitation', $isClosed ? 'Consultation sur demande' : 'Besoin d’en connaître'],
            ['Code d’ouverture', !empty($case['has_unlock_code']) ? 'Exigé' : 'Non exigé'],
        ]);
        $control = Theme::keyValues([
            ['Contrôle n°', (string) ($marks['control_number'] ?? '')],
            ['Registre', (string) ($marks['registry_number'] ?? '')],
            ['Ouvert le', $openedFr],
            ['Mouvement', $updatedFr],
            ['Émis le', $updatedFr],
        ]);
        $html .= Theme::twoColumns(
            Theme::section('', 'Situation du dossier') . $facts,
            Theme::section('', 'Contrôle') . $control
        );

        // Objet
        $html .= Theme::section('', 'Objet du dossier');
        if ($summary !== '') {
            $html .= '<p style="font-size:9pt;line-height:1.45;text-align:justify;">' . nl2br($e($summary)) . '</p>';
        } else {
            $html .= '<p style="font-size:8.5pt;color:' . $muted . ';font-style:italic;">'
                . 'Aucune synthèse n’a encore été portée à la chemise.</p>';
        }

        // Canal protégé
        $caveats = is_array($marks['caveats'] ?? null) ? $marks['caveats'] : [];
        $canal = '<span style="font-size:9pt;font-weight:bold;letter-spacing:0.8px;">'
            . $e((string) ($marks['channel'] ?? 'CANAL PROTÉGÉ')) . '</span><br/>'
            . '<span style="font-size:8pt;color:' . $muted . ';">'
            . 'Chemise à ne pas dissocier de ses pièces jointes. Toute sortie du local sécurisé est portée au registre de consultation.'
            . '</span>';
        if ($caveats !== []) {
            $canal .= '<br/><span style="font-size:7.5pt;">' . implode('&nbsp;&nbsp;', array_map(
                static fn ($c): string => Theme::pill((string) $c, 'muted'),
                $caveats
            )) . '</span>';
        }
        $html .= Theme::spacer(2) . Theme::callout($canal, '', $tone !== '' ? $tone : 'ok');

        // Consignes
        $html .= Theme::section('', 'Consignes de manipulation');
        $html .= '<p style="font-size:8pt;line-height:1.4;">'
            . 'Le dossier <strong>' . $e($caseRef) . '</strong> regroupe des pièces de niveaux de protection différents. '
            . 'Le niveau retenu pour l’ensemble est <strong>' . $e($classLabel) . '</strong>. '
            . 'Consultation au poste habilité, chemise complète ; restitution le jour même contre visa ; '
            . 'reproduction interdite sans accord écrit du chef de bureau.'
            . '</p>' . Theme::spacer(1);
        $html .= '<p style="font-size:8pt;line-height:1.4;">'
            . 'Révision de classification prévue le <strong>' . $e((string) ($marks['declassify_on'] ?? '—')) . '</strong>. '
            . 'Conservation <strong>' . $e((string) ($marks['destruction_delay'] ?? '—')) . '</strong> après clôture.'
            . '</p>';

        // Registre de consultation
        $html .= Theme::section('', 'Registre de consultation');
        $rows = [];
        $routing = is_array($marks['routing'] ?? null) ? $marks['routing'] : [];
        foreach ($routing as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                (string) (int) ($row['slot'] ?? 0),
                trim((string) ($row['holder'] ?? '')),
                (string) ($row['date'] ?? ''),
                (string) ($row['initials'] ?? ''),
            ];
        }
        $html .= Theme::table([
            ['label' => 'N°', 'width' => 10, 'mono' => true],
            ['label' => 'Consultant', 'width' => 48],
            ['label' => 'Date', 'width' => 22],
            ['label' => 'Visa', 'width' => 20, 'mono' => true, 'bold' => true],
        ], $rows);

        // Authentification : QR + empreintes
        $html .= Theme::section('', 'Empreintes d’intégrité');
        $qr = '';
        if ($wsQr !== '') {
            $qr = preg_replace(
                ['/\sclass="[^"]*"/', '/\swidth="\d+"/', '/\sheight="\d+"/'],
                ['', ' width="64"', ' height="64"'],
                $wsQr
            ) ?? $wsQr;
            $qr = $this->tcpdfImageSrc($qr);
        }
        $monoSpan = static fn (string $v): string => '<span style="font-family:' . $mono . ';font-size:7pt;">' . $v . '</span>';
        $html .= '<table cellpadding="4" cellspacing="0" style="border:0.3px solid ' . $line . ';"><tr nobr="true">'
            . '<td width="20%" align="center">' . $qr
            . '<br/><span style="font-size:6.5pt;"><strong>' . $e($wsId) . '</strong><br/>' . $e($wsHost) . ' · ' . $e($wsIp) . '</span></td>'
            . '<td width="60%" style="font-size:7.5pt;line-height:1.5;border-left:0.3px solid ' . $line . ';">'
            . '<strong>Condensat</strong> : ' . $monoSpan($e((string) ($marks['integrity_groups'] ?? ''))) . '<br/>'
            . '<strong>Enveloppe</strong> : ' . $monoSpan($e((string) ($marks['envelope_hash'] ?? ''))) . '<br/>'
            . '<strong>Machine</strong> : ' . $monoSpan($e($wsFp !== '' ? $wsFp : '—')) . '<br/>'
            . '<strong>Contrôle</strong> : ' . $monoSpan($e((string) ($marks['checksum'] ?? '')))
            . ' · ' . $e((string) ($marks['algorithm'] ?? ''))
            . '</td>'
            // Sceau du bureau ; classification, état et exemplaire figurent en pastilles sous le titre.
            . '<td width="20%" align="center" style="border-left:0.3px solid ' . $line . ';">'
            . '<span style="font-size:5.5pt;letter-spacing:1px;color:' . $muted . ';">BUREAU SSE</span><br/>'
            . '<span style="font-size:15pt;font-weight:bold;letter-spacing:1px;color:' . $ink . ';">' . $e((string) ($marks['seal_initials'] ?? 'UA')) . '</span><br/>'
            . '<span style="font-size:5.5pt;letter-spacing:1px;color:' . $muted . ';">DOSSIERS</span><br/>'
            . '<span style="font-size:6.5pt;font-weight:bold;color:' . $toneColor . ';">' . $e($classUpper) . '</span></td>'
            . '</tr></table>';

        $html .= Theme::spacer(1.5) . '<p style="font-size:6.5pt;color:' . $muted . ';">Contrôle '
            . $e((string) ($marks['control_number'] ?? '')) . ' · ' . $e($classUpper)
            . ' · Chemise — page de garde · ' . $e($generatedAt) . '</p>';

        return $html;
    }

    /**
     * TCPDF ne lit pas les URI data: dans <img> : PNG/JPEG passent en src="@<base64>",
     * SVG par un fichier temporaire .svg (rendu vectoriel par TCPDF).
     */
    private function tcpdfImageSrc(string $html): string
    {
        return preg_replace_callback('#src="data:image/([a-z+]+);base64,([^"]*)"#i', function (array $m): string {
            if (stripos($m[1], 'svg') === false) {
                return 'src="@' . $m[2] . '"';
            }
            $svg = base64_decode($m[2], true);
            $base = $svg !== false ? tempnam(sys_get_temp_dir(), 'sseqr') : false;
            if ($base === false || $svg === false) {
                return 'src=""';
            }
            @unlink($base);
            $file = $base . '.svg';
            file_put_contents($file, $svg);
            $this->tempFiles[] = $file;

            return 'src="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '"';
        }, $html) ?? $html;
    }

    /** Encadré de caviardage (version expurgée / intégrale au niveau de diffusion). */
    private function redactionCallout(string $redactedLabel): string
    {
        $expurged = str_starts_with($redactedLabel, 'VERSION EXPURGÉE');

        return Theme::callout(
            '<span style="font-size:8pt;font-weight:bold;color:' . ($expurged ? Theme::DANGER : Theme::INK) . ';">' . $this->e($redactedLabel) . '</span>',
            '',
            $expurged ? 'bad' : 'muted'
        );
    }

    private function sectionBanner(string $redactedLabel, string $num, string $sectionLabel): string
    {
        $html = '';
        if ($redactedLabel !== '') {
            $html .= $this->redactionCallout($redactedLabel);
        }

        $title = trim((string) ($this->caseMeta['title'] ?? ''));

        return $html . Theme::section($num, $sectionLabel)
            . ($title !== '' ? '<p style="font-size:7.5pt;color:' . Theme::MUTED . ';">' . $this->e($title) . '</p>' . Theme::spacer(1.5) : '');
    }

    private function reportSheet(string $title, string $body): string
    {
        unset($title); // déjà porté par le titre de section
        return '<p style="font-size:6.5pt;font-weight:bold;color:' . Theme::MUTED . ';letter-spacing:0.6px;">'
            . 'PIÈCE RÉDACTIONNELLE · DIFFUSION CONTRÔLÉE</p>'
            . Theme::spacer(1.5)
            . $this->reportBodyHtml($body);
    }

    /**
     * TCPDF ignore les blocs pre et le padding CSS : une ligne métier = une rangée.
     */
    private function reportBodyHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $line = 'border-bottom:0.2px solid ' . Theme::LINE . ';';

        if ($text === '') {
            return $this->emptyState('(aucun contenu généré)');
        }

        $html = '<table cellpadding="3" cellspacing="0" width="100%">';
        foreach (explode("\n", $text) as $row) {
            $trim = trim($row);
            if ($trim === '') {
                $html .= '<tr><td colspan="2" width="100%" style="font-size:3pt;line-height:1;">&nbsp;</td></tr>';
                continue;
            }

            $colon = mb_strpos($trim, ' : ', 0, 'UTF-8');
            if ($colon !== false && $colon > 0 && $colon <= 42) {
                $label = mb_substr($trim, 0, $colon, 'UTF-8');
                $value = mb_substr($trim, $colon + 3, 400, 'UTF-8');
                $html .= '<tr nobr="true">'
                    . '<td width="38%" style="font-size:8.5pt;color:' . Theme::MUTED . ';' . $line . '">' . $this->e($label) . '</td>'
                    . '<td width="62%" style="font-size:9pt;font-weight:bold;' . $line . '">' . $this->e($value) . '</td>'
                    . '</tr>';
                continue;
            }

            if ($this->isReportHeading($trim)) {
                $html .= '<tr nobr="true"><td colspan="2" width="100%" style="font-size:7.5pt;font-weight:bold;letter-spacing:0.8px;border-bottom:0.6px solid ' . Theme::INK . ';">'
                    . '<br/>' . $this->e($trim) . '</td></tr>';
                continue;
            }

            $html .= '<tr><td colspan="2" width="100%" style="font-size:9pt;">' . $this->e($trim) . '</td></tr>';
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
        return Theme::callout('<span style="font-size:8.5pt;color:' . Theme::MUTED . ';font-style:italic;">' . $this->e($message) . '</span>');
    }

    /**
     * @param list<array<string,mixed>> $people
     */
    private function peopleHtml(array $people): string
    {
        if ($people === []) {
            return $this->emptyState('Aucune personne rattachée à ce dossier.');
        }

        $rows = [];
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

            $alias = !empty($p['alias'])
                ? '<br/><span style="font-size:7.5pt;color:' . Theme::MUTED . ';">« ' . $this->e((string) $p['alias']) . ' »</span>'
                : '';

            $rows[] = [
                '<b>' . $this->e($ref) . '</b>',
                '<b>' . $this->e((string) ($p['display_name'] ?? '—')) . '</b>' . $alias,
                $this->e((string) ($p['status_label'] ?? '')),
                $this->e($bio),
                $this->e($wlLabel),
            ];
        }

        return Theme::table([
            ['label' => 'Réf.', 'width' => 9, 'mono' => true],
            ['label' => 'Identité', 'width' => 27],
            ['label' => 'Statut', 'width' => 16],
            ['label' => 'Biométrie', 'width' => 22],
            ['label' => 'Croisement', 'width' => 26],
        ], $rows, false);
    }

    /**
     * @param list<array<string,mixed>> $sites
     */
    private function sitesHtml(array $sites): string
    {
        if ($sites === []) {
            return $this->emptyState('Aucun site rattaché.');
        }

        $muted = Theme::MUTED;
        $label = static fn (string $t): string => '<span style="font-size:6.5pt;font-weight:bold;color:' . $muted . ';letter-spacing:0.6px;">' . $t . '</span><br/>';
        $html = '';

        foreach ($sites as $site) {
            $rooms = is_array($site['rooms'] ?? null) ? $site['rooms'] : [];
            $seizures = is_array($site['seizures'] ?? null) ? $site['seizures'] : [];
            $done = count(array_filter($rooms, static fn (array $r): bool => !empty($r['checked'])));

            $body = '<span style="font-size:10pt;font-weight:bold;">'
                . $this->e((string) ($site['reference_code'] ?? ''))
                . ' — ' . $this->e((string) ($site['name'] ?? ''))
                . '</span><br/>'
                . '<span style="font-size:7.5pt;color:' . $muted . ';">'
                . $this->e((string) ($site['site_type_label'] ?? ''))
                . ' · ' . $this->e((string) ($site['status_label'] ?? ''))
                . ' · Fouille : ' . $done . '/' . count($rooms)
                . ' · Saisies : ' . count($seizures)
                . '</span><br/>';

            if ($rooms !== []) {
                $bits = [];
                foreach ($rooms as $r) {
                    $mark = !empty($r['checked']) ? '■' : '□';
                    $bits[] = $mark . ' ' . $this->e((string) ($r['label'] ?? 'Pièce'));
                }
                $body .= '<br/>' . $label('PIÈCES')
                    . '<span style="font-size:8.5pt;">' . implode(' · ', $bits) . '</span><br/>';
            }

            if ($seizures !== []) {
                $items = [];
                foreach (array_slice($seizures, 0, 40) as $sz) {
                    $item = $this->e((string) ($sz['label'] ?? $sz['item_label'] ?? 'Saisie'));
                    if (!empty($sz['quantity'])) {
                        $item .= ' × ' . $this->e((string) $sz['quantity']);
                    }
                    if (!empty($sz['location_label'])) {
                        $item .= ' — ' . $this->e((string) $sz['location_label']);
                    }
                    $items[] = '<span style="color:' . Theme::ACCENT . ';">■</span>&nbsp; ' . $item;
                }
                $body .= '<br/>' . $label('SAISIES')
                    . '<span style="font-size:8.5pt;">' . implode('<br/>', $items) . '</span>';
            }

            if ($rooms === [] && $seizures === []) {
                $body .= '<span style="font-size:8pt;color:' . $muted . ';font-style:italic;">'
                    . 'Aucun détail de fouille versé.</span>';
            }

            $html .= Theme::callout($body);
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
        if ($relations === []) {
            return $this->emptyState('Aucune corrélation enregistrée sur ce dossier.');
        }

        $personNames = [];
        foreach ($people as $p) {
            $personNames[(int) ($p['id'] ?? 0)] = (string) ($p['display_name'] ?? 'Personne');
        }
        $siteNames = [];
        foreach ($sites as $s) {
            $siteNames[(int) ($s['id'] ?? 0)] = (string) ($s['name'] ?? $s['reference_code'] ?? 'Site');
        }

        $rows = [];
        foreach ($relations as $rel) {
            $rows[] = [
                $this->entityLabel((string) ($rel['from_type'] ?? ''), (int) ($rel['from_id'] ?? 0), $personNames, $siteNames),
                SseCorrelationService::relationLabel((string) ($rel['relation'] ?? '')),
                $this->entityLabel((string) ($rel['to_type'] ?? ''), (int) ($rel['to_id'] ?? 0), $personNames, $siteNames),
                SseCorrelationService::reliabilityLabel((string) ($rel['reliability'] ?? '')),
                (string) ($rel['note'] ?? '—'),
            ];
        }

        return Theme::table([
            ['label' => 'De', 'width' => 22],
            ['label' => 'Lien', 'width' => 16, 'bold' => true],
            ['label' => 'Vers', 'width' => 22],
            ['label' => 'Fiabilité', 'width' => 14],
            ['label' => 'Note', 'width' => 26],
        ], $rows);
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
        if ($notes === []) {
            return $this->emptyState('Aucune note.');
        }

        $html = '';
        foreach ($notes as $n) {
            $meta = $this->e((string) ($n['classification_label'] ?? ''))
                . ' · ' . $this->e((string) ($n['author_label'] ?? 'Opérateur'))
                . (!empty($n['created_at']) ? ' · ' . $this->e((string) $n['created_at']) : '');
            $html .= Theme::callout(
                '<span style="font-size:6.5pt;font-weight:bold;color:' . Theme::MUTED . ';letter-spacing:0.5px;">' . mb_strtoupper($meta, 'UTF-8') . '</span><br/>'
                . '<span style="font-size:9pt;line-height:1.4;">' . nl2br($this->e((string) ($n['body'] ?? ''))) . '</span>',
                '',
                'muted'
            );
        }

        return $html;
    }

    /**
     * @param list<array<string,mixed>> $evidence
     */
    private function evidenceHtml(array $evidence): string
    {
        if ($evidence === []) {
            return $this->emptyState('Aucune preuve jointe.');
        }

        $rows = [];
        foreach ($evidence as $i => $ev) {
            $label = (string) ($ev['label'] ?? 'Preuve');
            if (!empty($ev['image_path'])) {
                $label .= ' ✦';
            }
            $rows[] = [
                sprintf('%02d', $i + 1),
                $label,
                (string) ($ev['caption'] ?? '—'),
                (string) ($ev['author_label'] ?? '—'),
                (string) ($ev['created_at'] ?? '—'),
            ];
        }

        return Theme::table([
            ['label' => 'N°', 'width' => 7, 'mono' => true],
            ['label' => 'Libellé', 'width' => 25, 'bold' => true],
            ['label' => 'Légende', 'width' => 30],
            ['label' => 'Versée par', 'width' => 20],
            ['label' => 'Date', 'width' => 18],
        ], $rows)
            . '<p style="font-size:7.5pt;color:' . Theme::MUTED . ';">'
            . '✦ Image jointe reproduite dans les pages suivantes, dans la limite de l’espace du document.'
            . '</p>';
    }

    /**
     * @param list<array<string,mixed>> $evidence
     */
    private function appendEvidenceImages(AthenaTcpdf $pdf, array $evidence, string $redactedLabel = ''): void
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
                $caption = trim((string) ($e['caption'] ?? ''));
                $pdf->writeAthenaHtml(
                    $this->sectionBanner($redactedLabel, sprintf('07.%02d', $i + 1), 'Pièce visuelle')
                    . '<p style="font-size:11pt;font-weight:bold;">' . $this->e((string) ($e['label'] ?? 'Preuve')) . '</p>'
                    . ($caption !== '' ? '<p style="font-size:9pt;color:' . Theme::MUTED . ';">' . $this->e($caption) . '</p>' : '')
                    . Theme::spacer(2)
                );
                $l = Theme::MARGIN;
                $w = $pdf->getPageWidth() - 2 * $l;
                $y = $pdf->GetY();
                $pdf->SetDrawColorArray(Theme::rgb(Theme::INK));
                $pdf->SetLineWidth(0.3);
                $pdf->Line($l, $y, $l + $w, $y);
                $y += 2;
                $h = max(20.0, $pdf->getPageHeight() - Theme::BOTTOM - 6 - $y);
                $pdf->Image($abs, $l, $y, $w, $h, '', '', '', false, 150, '', false, false, 0, 'CT', false, false);
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
