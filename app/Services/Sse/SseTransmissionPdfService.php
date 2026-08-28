<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Export PDF du journal des transmissions et d’une fiche unitaire.
 */
final class SseTransmissionPdfService
{
    /**
     * @param list<array<string, mixed>> $events
     */
    public function exportJournal(array $events): Response
    {
        $generatedAt = (new \DateTimeImmutable('now'))->format('d/m/Y H:i');
        $html = $this->journalHtml($events, $generatedAt);

        return $this->renderPdf(
            $html,
            'Journal des transmissions terrain',
            'journal-transmissions-' . date('Ymd-Hi') . '.pdf',
            'L'
        );
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, list<array{section:string,label:string,value:string}>> $sections
     */
    public function exportEvent(array $event, array $sections = []): Response
    {
        $id = (int) ($event['id'] ?? 0);
        $generatedAt = (new \DateTimeImmutable('now'))->format('d/m/Y H:i');
        $html = $this->eventHtml($event, $sections, $generatedAt);

        return $this->renderPdf(
            $html,
            (string) ($event['event_type_label'] ?? 'Transmission'),
            sprintf('transmission-TX-%d.pdf', $id > 0 ? $id : 0),
            'P'
        );
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    public function journalHtml(array $events, string $generatedAt): string
    {
        $rows = '';
        foreach ($events as $event) {
            $rows .= '<tr>'
                . '<td>' . $this->esc(substr((string) ($event['event_time'] ?? ''), 0, 16)) . '</td>'
                . '<td>' . $this->esc((string) ($event['event_type_label'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($event['source_system_label'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($event['summary'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) (($event['author_label'] ?? '') !== '' ? $event['author_label'] : '—')) . '</td>'
                . '<td>' . $this->esc((string) ($event['confidence_code'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6">Aucune transmission pour ces filtres.</td></tr>';
        }

        return $this->shell(
            'Journal des transmissions terrain',
            $generatedAt,
            '<p style="font-size:10px;color:#475569;margin:0 0 10px 0;">'
            . count($events) . ' entrée' . (count($events) > 1 ? 's' : '')
            . ' — extraits du bureau SSE.</p>'
            . '<table cellpadding="4" cellspacing="0" border="1" width="100%">'
            . '<thead><tr style="background-color:#0f172a;color:#ecfdf5;">'
            . '<th width="14%">Horodatage</th><th width="14%">Nature</th><th width="14%">Origine</th>'
            . '<th width="36%">Résumé</th><th width="14%">Opérateur</th><th width="8%">Cotation</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        );
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, list<array{section:string,label:string,value:string}>> $sections
     */
    public function eventHtml(array $event, array $sections, string $generatedAt): string
    {
        $id = (int) ($event['id'] ?? 0);
        $body = '<h2 style="font-size:13px;margin:0 0 8px 0;">En-tête</h2>'
            . '<table cellpadding="4" cellspacing="0" border="1" width="100%">'
            . $this->kv('Référence', 'TX-' . $id)
            . $this->kv('Horodatage', substr((string) ($event['event_time'] ?? ''), 0, 19))
            . $this->kv('Nature', (string) ($event['event_type_label'] ?? ''))
            . $this->kv('Origine', (string) ($event['source_system_label'] ?? ''))
            . $this->kv('Opérateur', (string) (($event['author_label'] ?? '') !== '' ? $event['author_label'] : 'Non renseigné'))
            . $this->kv('Unité', (string) (($event['unit_label'] ?? '') !== '' ? $event['unit_label'] : 'Non renseignée'))
            . $this->kv('Cotation', (string) ($event['confidence_code'] ?? ''))
            . $this->kv('Logiciel', (string) (($event['client_label'] ?? '') !== '' ? $event['client_label'] : '—'))
            . '</table>'
            . '<h2 style="font-size:13px;margin:14px 0 8px 0;">Résumé</h2>'
            . '<p>' . $this->esc((string) ($event['summary'] ?? 'Sans résumé')) . '</p>';

        foreach ($sections as $title => $rows) {
            if ($rows === []) {
                continue;
            }
            $body .= '<h2 style="font-size:13px;margin:14px 0 8px 0;">' . $this->esc((string) $title) . '</h2>'
                . '<table cellpadding="4" cellspacing="0" border="1" width="100%">';
            foreach ($rows as $row) {
                $body .= $this->kv((string) ($row['label'] ?? ''), (string) ($row['value'] ?? ''));
            }
            $body .= '</table>';
        }

        return $this->shell(
            (string) ($event['event_type_label'] ?? 'Transmission'),
            $generatedAt,
            $body
        );
    }

    private function kv(string $label, string $value): string
    {
        return '<tr><th width="32%" align="left" style="background-color:#f1f5f9;">'
            . $this->esc($label)
            . '</th><td>' . $this->esc($value) . '</td></tr>';
    }

    private function shell(string $title, string $generatedAt, string $inner): string
    {
        return '<div style="font-family:dejavusans,freesans,helvetica,sans-serif;color:#0f172a;">'
            . '<h1 style="font-size:16px;margin:0 0 4px 0;color:#064e3b;">' . $this->esc($title) . '</h1>'
            . '<p style="font-size:9px;color:#64748b;margin:0 0 12px 0;">Athena SSE · généré le '
            . $this->esc($generatedAt) . '</p>'
            . $inner
            . '</div>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderPdf(string $html, string $title, string $filename, string $orientation): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use (
            $html,
            $title,
            $filename,
            $orientation
        ): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }

            $pdf = new \TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('Athena COMSPEC');
            $pdf->SetAuthor('Portail SSE Athena');
            $pdf->SetTitle($title);
            $pdf->SetMargins(12, 14, 12);
            $pdf->SetAutoPageBreak(true, 16);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            $binary = (string) $pdf->Output('', 'S');

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }
}
