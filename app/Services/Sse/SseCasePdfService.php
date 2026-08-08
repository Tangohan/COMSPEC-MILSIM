<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Repositories\SseCaseRepository;
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
     */
    public function export(int $tenantId, int $caseId, ?string $releaseLevel = null): Response
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
            $generatedAt
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
            $filename = 'dossier-complet-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($case['reference_code'] ?? 'sse')) . '.pdf';

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
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
        $classLabel = $this->e((string) ($case['classification_label'] ?? ''));
        $ref = $this->e((string) ($case['reference_code'] ?? ''));
        $title = $this->e((string) ($case['title'] ?? ''));
        $summary = $this->e((string) ($case['summary'] ?? '—'));
        $status = $this->e((string) ($case['status_label'] ?? ''));
        $created = $this->e((string) ($case['created_at'] ?? '—'));
        $updated = $this->e((string) ($case['updated_at'] ?? '—'));

        $html = '<div style="background-color:#1a1a1a;color:#f5f5f5;padding:10px;text-align:center;font-size:11px;">'
            . '<strong>DIFFUSION RESTREINTE — DOSSIER SSE COMPLET</strong><br/>'
            . 'Classification : ' . $classLabel . ' · Usage opérationnel uniquement · Ne pas redistribuer'
            . '</div>';

        if ($redactedLabel !== '') {
            $html .= '<div style="background-color:#8f1d1d;color:#fff;padding:7px;text-align:center;font-size:10px;">'
                . $this->e($redactedLabel)
                . '</div>';
        }

        $html .= '<h1 style="font-size:18px;color:#111;margin-top:18px;">Dossier ' . $ref . '</h1>';
        $html .= '<p style="font-size:12px;"><strong>Intitulé :</strong> ' . $title . '<br/>'
            . '<strong>Statut :</strong> ' . $status . '<br/>'
            . '<strong>Ouverture :</strong> ' . $created . '<br/>'
            . '<strong>Dernière mise à jour :</strong> ' . $updated . '</p>';

        $html .= '<h2 style="font-size:13px;border-bottom:1px solid #ccc;">Synthèse</h2>'
            . '<p style="font-size:11px;">' . nl2br($summary) . '</p>';

        $html .= '<h2 style="font-size:13px;border-bottom:1px solid #ccc;">Contenu de cet export</h2>'
            . '<ul style="font-size:11px;">'
            . '<li>Flash opérationnel</li>'
            . '<li>Compte rendu initial</li>'
            . '<li>Personnes rattachées (' . count($people) . ')</li>'
            . '<li>Sites exploités (' . count($sites) . ')</li>'
            . '<li>Corrélations enregistrées</li>'
            . '<li>Notes classifiées (' . count($notes) . ')</li>'
            . '<li>Preuves recensées (' . count($evidence) . ')</li>'
            . '</ul>';

        $html .= '<p style="font-size:9px;color:#555;margin-top:24px;">Document généré le '
            . $this->e($generatedAt)
            . ' par Athena · Chaîne de possession SSE · Ne pas photocopier hors circuit contrôlé</p>';

        return $html;
    }

    /**
     * @param array<string,mixed> $case
     */
    private function sectionBanner(array $case, string $redactedLabel): string
    {
        $html = '<div style="background-color:#222;color:#eee;padding:5px;font-size:9px;">'
            . $this->e((string) ($case['reference_code'] ?? ''))
            . ' — ' . $this->e((string) ($case['title'] ?? ''))
            . ' — ' . $this->e((string) ($case['classification_label'] ?? ''))
            . '</div>';
        if ($redactedLabel !== '') {
            $html .= '<div style="background-color:#8f1d1d;color:#fff;padding:4px;font-size:8px;text-align:center;">'
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

        return ucfirst($type) . ' #' . $id;
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
