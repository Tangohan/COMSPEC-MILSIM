<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Support\TrainingCertificatePdfEngine;

final class SseCasePdfService
{
    public function __construct(
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
    }

    /**
     * Export PDF du dossier.
     *
     * `$releaseLevel` : niveau de diffusion auquel produire le document. À `null`,
     * le PDF est intégral — à ne laisser que pour un appel interne dont on sait
     * qu'il est déjà encadré. Un PDF circule seul une fois transmis : un caviardage
     * manquant ne se rattrape plus.
     */
    public function export(int $tenantId, int $caseId, ?string $releaseLevel = null): Response
    {
        $case = $this->cases->findById($caseId, $tenantId);
        if ($case === null) {
            return (new Response())->setStatusCode(404)->setBody('<p>Dossier introuvable.</p>');
        }

        $links = $this->cases->listLinkedPersonIds($caseId, $tenantId);
        $people = [];
        foreach ($links as $link) {
            $p = $this->persons->findById((int) $link['person_id'], $tenantId);
            if ($p) {
                $people[] = $p;
            }
        }
        $notes = $this->cases->listNotes($caseId, $tenantId);
        $evidence = $this->cases->listEvidence($caseId, $tenantId);

        $redactedLabel = '';
        if ($releaseLevel !== null) {
            $redaction = new SseRedactionService();
            $expurged = $redaction->apply(
                ['case' => $case, 'people' => $people, 'sites' => []],
                $releaseLevel,
                $redaction->listForCase($caseId, $tenantId)
            );
            $people = $expurged['people'];

            // Le document doit dire de lui-même à quel niveau il a été produit :
            // une fois imprimé, plus personne ne sait d'où il sort.
            $hidden = SseRedactionService::summarise($releaseLevel)['hidden'];
            $redactedLabel = $hidden === []
                ? 'Version intégrale — ' . SseRedactionService::levelLabel($releaseLevel)
                : 'VERSION EXPURGÉE — ' . SseRedactionService::levelLabel($releaseLevel)
                    . ' — au noir : ' . implode(', ', $hidden);
        }

        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($case, $people, $notes, $evidence, $redactedLabel): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }

            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Athena COMSPEC');
            $pdf->SetAuthor('Renseignement interpersonnel');
            $pdf->SetTitle('Dossier ' . $case['reference_code']);
            $pdf->SetMargins(14, 18, 14);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();

            $classLabel = htmlspecialchars((string) $case['classification_label'], ENT_QUOTES, 'UTF-8');
            $ref = htmlspecialchars((string) $case['reference_code'], ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) $case['title'], ENT_QUOTES, 'UTF-8');
            $summary = htmlspecialchars((string) ($case['summary'] ?? '—'), ENT_QUOTES, 'UTF-8');

            $html = '<div style="background-color:#1a1a1a;color:#f5f5f5;padding:8px;text-align:center;font-size:11px;">'
                . '<strong>DIFFUSION RESTREINTE — RENSEIGNEMENT INTERPERSONNEL</strong><br/>'
                . 'Classification : ' . $classLabel . ' · Usage opérationnel uniquement · Ne pas redistribuer'
                . '</div>';

            // Un PDF circule seul : il doit porter son propre niveau de production.
            // Sans ce bandeau, une version expurgée est indiscernable d'une version
            // intégrale une fois imprimée, et se retrouve traitée comme complète.
            if ($redactedLabel !== '') {
                $html .= '<div style="background-color:#8f1d1d;color:#fff;padding:6px;text-align:center;font-size:10px;">'
                    . htmlspecialchars($redactedLabel, ENT_QUOTES, 'UTF-8')
                    . '</div>';
            }
            $html .= '<h1 style="font-size:16px;color:#111;">Dossier ' . $ref . '</h1>';
            $html .= '<p><strong>Intitulé :</strong> ' . $title . '<br/>'
                . '<strong>Statut :</strong> ' . htmlspecialchars((string) $case['status_label'], ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '<h2 style="font-size:13px;">Synthèse</h2><p>' . nl2br($summary) . '</p>';

            $html .= '<h2 style="font-size:13px;">Personnes rattachées</h2>';
            if ($people === []) {
                $html .= '<p>Aucune personne rattachée.</p>';
            } else {
                $html .= '<ul>';
                foreach ($people as $p) {
                    $html .= '<li>' . htmlspecialchars((string) ($p['display_name'] ?? ''), ENT_QUOTES, 'UTF-8')
                        . ' — ' . htmlspecialchars((string) ($p['status_label'] ?? ''), ENT_QUOTES, 'UTF-8')
                        . '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '<h2 style="font-size:13px;">Notes</h2>';
            if ($notes === []) {
                $html .= '<p>Aucune note.</p>';
            } else {
                foreach ($notes as $n) {
                    $html .= '<p><em>' . htmlspecialchars((string) ($n['classification_label'] ?? ''), ENT_QUOTES, 'UTF-8')
                        . '</em> — ' . htmlspecialchars((string) ($n['author_label'] ?? 'Opérateur'), ENT_QUOTES, 'UTF-8')
                        . '<br/>' . nl2br(htmlspecialchars((string) $n['body'], ENT_QUOTES, 'UTF-8')) . '</p>';
                }
            }

            $html .= '<h2 style="font-size:13px;">Preuves recensées</h2>';
            if ($evidence === []) {
                $html .= '<p>Aucune preuve jointe.</p>';
            } else {
                $html .= '<ul>';
                foreach ($evidence as $e) {
                    $html .= '<li>' . htmlspecialchars((string) $e['label'], ENT_QUOTES, 'UTF-8');
                    if (!empty($e['caption'])) {
                        $html .= ' — ' . htmlspecialchars((string) $e['caption'], ENT_QUOTES, 'UTF-8');
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '<hr/><p style="font-size:9px;color:#444;text-align:center;">'
                . 'Document généré par Athena · Diffusion restreinte · Ne pas photocopier hors chaîne de possession'
                . '</p>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $binary = (string) $pdf->Output('', 'S');
            $filename = 'dossier-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $case['reference_code']) . '.pdf';

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($binary);
        });
    }
}
