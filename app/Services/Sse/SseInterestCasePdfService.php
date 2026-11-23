<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Response;
use App\Support\Pdf\AthenaPdfTheme as Theme;
use App\Support\Pdf\SsePdfNotice;
use App\Support\SseInterestMarkup;
use App\Support\TrainingCertificatePdfEngine;

/**
 * Dossier PDF : synthèse, analyse, graphe vectoriel, fiches liées, discussion et historique complet.
 * Le dossier arrive déjà filtré par SseInterestCaseService::detail (classification, accès nominatifs, masquage des éléments).
 */
final class SseInterestCasePdfService
{
    /**
     * @param array<string, mixed> $detail résultat de SseInterestCaseService::detail
     */
    public function export(array $detail, string $communityLabel = '', bool $withNotice = true): Response
    {
        return TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(function () use ($detail, $communityLabel, $withNotice): Response {
            if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
                return (new Response())->setStatusCode(503)->setBody('<p>Export PDF indisponible pour le moment.</p>');
            }
            $binary = $this->binary($detail, $communityLabel, $withNotice);
            $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($detail['case']['reference_code'] ?? 'dossier'));

            return (new Response())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="dossier-interet-' . $slug . '.pdf"')
                ->header('Cache-Control', 'private, no-store')
                ->setBody($binary);
        });
    }

    /** @param array<string, mixed> $detail */
    public function binary(array $detail, string $communityLabel = '', bool $withNotice = true): string
    {
        $case = $detail['case'];
        $ref = (string) $case['reference_code'];
        $pdf = Theme::tcpdf([
            'kind' => 'Dossier d’intérêt',
            'reference' => $ref,
            'tenant_id' => (int) ($case['tenant_id'] ?? 0),
            'community' => $communityLabel,
            'classification' => \App\Support\SseUsClassification::label((string) ($detail['export_classification'] ?? $case['classification'])) . ' · besoin d’en connaître',
            'audience' => 'Édité le ' . date('d/m/Y H:i'),
            'note' => 'Hypothèse de travail : aucune identité n’est confirmée sans validation humaine · ne pas dissocier de ses pièces',
            'title' => 'Dossier d’intérêt ' . $ref,
        ]);
        if ($pdf === null) {
            throw new \RuntimeException('TCPDF indisponible');
        }
        $noticePage = 0;
        if ($withNotice) {
            $pdf->AddPage();
            $noticePage = $pdf->getPage();
        }
        $pdf->AddPage();
        $bodyPage = $pdf->getPage();
        $pdf->writeAthenaHtml($this->html($detail));
        $graphPage = $pdf->getNumPages();
        (new SseInterestCasePdfGraph())->draw($pdf, (array) ($detail['graph'] ?? []));
        foreach ((array) ($detail['media'] ?? []) as $media) {
            $path = $this->imagePath((string) ($media['path'] ?? ''), (int) ($case['tenant_id'] ?? 0));
            $info = $path !== null ? @getimagesize($path) : false;
            if ($path !== null && $info !== false && $info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
                $image = @imagecreatefromwebp($path);
                if ($image !== false) {
                    ob_start();
                    imagejpeg($image, null, 90);
                    $jpeg = ob_get_clean();
                    imagedestroy($image);
                    if (is_string($jpeg) && $jpeg !== '') {
                        $path = '@' . $jpeg;
                        $info[2] = IMAGETYPE_JPEG;
                    }
                }
            }
            if ($path === null || $info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                $pdf->AddPage();
                $pdf->writeAthenaHtml('<h2>Pièce photographique</h2><p>' . $this->escape($media['caption'] ?? '') . '</p><p>Image source absente ou format non incorporable. Consulter la pièce originale dans le SSE.</p>');
                continue;
            }
            $pdf->AddPage();
            $pdf->writeAthenaHtml('<h2>Pièce photographique</h2><p>' . $this->escape($media['subject'] ?? '') . ' · ' . $this->escape($media['caption'] ?? '') . '</p>');
            $pdf->Image($path, 16, max(65, $pdf->GetY() + 5), 178, 175, '', '', '', true, 150, '', false, false, 0, 'CM');
        }

        if ($noticePage > 0) {
            $toc = [['label' => Theme::t('Notice du document'), 'page' => $noticePage], ['label' => Theme::t('Synthèse'), 'page' => $bodyPage]];
            if ($pdf->getNumPages() > $graphPage) {
                $toc[] = ['label' => Theme::t('Graphe, pièces photographiques'), 'page' => $graphPage + 1];
            }
            SsePdfNotice::fillReserved($pdf, $noticePage, [
                'lang' => Theme::isEnglish() ? 'en' : 'fr',
                'type' => 'interest',
                'title' => (string) ($case['temporary_designation'] ?? $ref),
                'ref' => $ref,
                'level' => (string) ($detail['export_classification'] ?? $case['classification'] ?? ''),
                'generated_at' => date(Theme::isEnglish() ? 'm/d/Y H:i' : 'd/m/Y H:i'),
                'ids' => [[['Priorité', 'Priority'], (string) ($case['priority_code'] ?? '')]],
                'issuer' => $communityLabel,
                'period' => trim((string) ($case['created_at'] ?? '')) !== '' ? substr((string) $case['created_at'], 0, 10) . ' → ' . substr((string) ($case['updated_at'] ?? $case['created_at']), 0, 10) : '',
                'version' => (string) ($case['status_label'] ?? ''),
                'fingerprint' => SsePdfNotice::fingerprint([$case, $detail['field_notes'] ?? [], $detail['graph'] ?? []]),
                'redaction' => null,
                'toc' => $toc,
            ]);
        }

        return (string) $pdf->Output('', 'S');
    }

    /** @param array<string, mixed> $detail */
    public function html(array $detail): string
    {
        $case = $detail['case'];
        $muted = Theme::MUTED;
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $row = static fn (string $k, mixed $v): string => '<tr><td width="30%" style="color:' . Theme::MUTED . ';font-size:8pt;">' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</td><td width="70%" style="font-size:9pt;">'
            . htmlspecialchars(trim((string) $v) !== '' ? (string) $v : '—', ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $join = static fn (array $parts): string => implode(' · ', array_filter(array_map(static fn (mixed $v): string => trim((string) $v), $parts), static fn (string $v): bool => $v !== ''));
        $date = static fn (mixed $v): string => ($ts = strtotime((string) $v)) !== false && $v ? date('d/m/Y H:i', $ts) : '';

        $pilot = [
            'Référence' => $case['reference_code'], 'État' => $case['status_label'],
            'Priorité' => $case['priority_code'] . ' · ' . $case['interest_label'],
            'Classification' => $case['classification_label'], 'Type' => $case['type_label'] ?? '',
            'Responsable' => $case['assignee_label'] ?? '', 'Échéance' => $date($case['due_at'] ?? ''),
            'Étiquettes' => implode(', ', $case['tag_list'] ?? []),
            'Fiches / brouillons' => count((array) ($detail['field_notes'] ?? [])) . ' fiche(s) · ' . count((array) ($detail['drafts'] ?? [])) . ' en cours',
            'Photographies' => count((array) ($detail['media'] ?? [])) . ' pièce(s)',
        ];
        $signal = ['Alias supposé' => $case['suspected_alias'] ?? '', 'Sexe apparent' => $case['apparent_sex'] ?? '',
            'Âge estimé' => $case['estimated_age_range'] ?? '', 'Nationalité supposée' => $case['suspected_nationality'] ?? '',
            'Affiliation supposée' => $case['suspected_affiliation'] ?? ''];
        $pilotRows = array_map(null, array_keys($pilot), array_values($pilot));
        $signalRows = array_map(null, array_keys($signal), array_values($signal));
        $summary = '<table cellpadding="5"><tr><td width="50%" colspan="2" bgcolor="#e2e8f0"><b>PILOTAGE DU DOSSIER</b></td><td width="50%" colspan="2" bgcolor="#e2e8f0"><b>SIGNALEMENT PROVISOIRE</b></td></tr>';
        for ($i = 0; $i < max(count($pilotRows), count($signalRows)); ++$i) {
            $summary .= '<tr>';
            foreach ([$pilotRows[$i] ?? ['', ''], $signalRows[$i] ?? ['', '']] as [$label, $value]) {
                $summary .= '<td width="20%" bgcolor="#f1f5f9" style="font-size:8pt;color:' . $muted . ';">' . $e($label) . '</td><td width="30%" bgcolor="#f8fafc" style="font-size:9pt;">' . $e(trim((string) $value) !== '' ? $value : ($label !== '' ? '—' : '')) . '</td>';
            }
            $summary .= '</tr>';
        }
        $summary .= '</table>';

        $html = Theme::section('01', 'Synthèse')
            . '<h1 style="font-size:20pt;color:' . Theme::INK . ';">' . $e($case['temporary_designation']) . '</h1>'
            . '<p style="font-size:10pt;color:' . $muted . ';">' . $e($case['reference_code'] . ' · ' . $case['status_label'] . ' · ' . $case['priority_code']) . '</p>'
            . Theme::spacer(4)
            . $summary . Theme::spacer(3)
            . '<table cellpadding="3">'
            . $row('Confiance', $case['confidence_label'] ?? $case['confidence_level'] ?? '')
            . $row('Source / fiabilité', $join([$case['source_label'] ?? '', $case['source_reliability'] ?? '']))
            . $row('Mission / opérateur', $join([$case['mission_label'] ?? '', $case['origin_operator'] ?? '']))
            . $row('Acquis le', $date($case['acquisition_at'] ?? ''))
            . $row('Signé par', $join([$case['signed_by_label'] ?? '', $date($case['signed_at'] ?? '')]))
            . $row('Validé le', $date($case['validated_at'] ?? ''))
            . $row('Rédacteur', $detail['creator_label'] ?? '')
            . $row('Ouvert le', $date($case['created_at'] ?? ''))
            . $row('Dernière activité', $date($case['updated_at'] ?? ''))
            . '</table>'
            . Theme::spacer(2)
            . '<p style="font-size:8pt;font-weight:bold;color:' . $muted . ';">MOTIF D’OUVERTURE</p>'
            . '<p style="font-size:9pt;">' . nl2br($e(SseInterestMarkup::plain((string) ($case['opening_reason'] ?? '')))) . '</p>';
        if (trim((string) ($case['description'] ?? '')) !== '') {
            $html .= '<p style="font-size:8pt;font-weight:bold;color:' . $muted . ';">DESCRIPTION</p>'
                . '<p style="font-size:9pt;">' . nl2br($e(SseInterestMarkup::plain((string) $case['description']))) . '</p>';
        }

        $links = array_values(array_filter((array) ($detail['links'] ?? []), static fn (array $l): bool => empty($l['masked']) && empty($l['missing'])));
        $html .= Theme::section('02', 'Éléments liés') ;
        if ($links === []) {
            $html .= '<p style="font-size:9pt;color:' . $muted . ';">Aucun élément lié visible.</p>';
        } else {
            $html .= '<table cellpadding="3" border="0">';
            foreach ($links as $l) {
                $html .= '<tr><td width="24%" style="color:' . $muted . ';font-size:8pt;">' . $e($l['type_label']) . '</td><td width="76%" style="font-size:9pt;">' . $e($l['label']) . ($l['hint'] !== '' ? ' <span style="color:' . $muted . ';">(' . $e($l['hint']) . ')</span>' : '') . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= Theme::section('03', 'Analyse et conduite de l’enquête');
        foreach (['observed_elements' => 'Éléments observés', 'analysis_facts' => 'Faits établis', 'analysis_assumptions' => 'Hypothèses', 'analysis_contradictions' => 'Contradictions', 'analysis_questions' => 'Questions restantes', 'collection_needs' => 'Besoins de collecte', 'operational_risk' => 'Risque opérationnel', 'recommendations' => 'Recommandations'] as $key => $label) {
            $html .= '<table cellpadding="5" nobr="true"><tr><td bgcolor="#f1f5f9"><b>' . $e($label) . '</b></td></tr><tr><td style="font-size:9pt;">' . nl2br($e(SseInterestMarkup::plain(trim((string) ($case[$key] ?? '')) ?: 'Non renseigné'))) . '</td></tr></table>' . Theme::spacer(2);
        }
        $html .= Theme::section('04', 'Réseau et documents en cours');
        $graph = (array) ($detail['graph'] ?? []);
        $visible = array_filter((array) ($graph['nodes'] ?? []), static fn (array $n): bool => empty($n['restricted']));
        if ($visible === []) {
            $html .= '<p>Aucun réseau visible consigné.</p>';
        } else {
            $html .= '<p style="color:' . $muted . ';">Graphe en annexe. Répertoire des éléments et relations accessibles au lecteur.</p><table cellpadding="4">';
            foreach ($visible as $node) {
                $html .= $row((string) ($node['type_label'] ?? ''), trim((string) ($node['ref'] ?? '') . ' · ' . (string) ($node['title'] ?? ''))) . "\n" . $row('État / synthèse', $node['summary'] ?? '');
            }
            $html .= '</table><table cellpadding="3">';
            foreach ((array) ($graph['edges'] ?? []) as $edge) {
                if (!isset($visible[$edge['from']], $visible[$edge['to']])) { continue; }
                $html .= $row((string) ($edge['label'] ?? 'Lié à'), ($visible[$edge['from']]['title'] ?? '') . ' → ' . ($visible[$edge['to']]['title'] ?? '') . (($edge['reason'] ?? '') !== '' ? ' · ' . $edge['reason'] : ''));
            }
            $html .= '</table>';
            if (!empty($graph['truncated'])) { $html .= '<p>Le réseau dépasse la limite du graphe exporté ; consulter le bureau SSE pour la suite des liaisons.</p>'; }
        }
        $html .= Theme::section('05', 'Fiches de renseignement liées');
        foreach ((array) ($detail['drafts'] ?? []) as $draft) {
            $html .= '<h3>' . $e($draft['title'] ?? 'Fiche en cours') . '</h3><table cellpadding="3">'
                . $row('État', 'Brouillon en cours · non émis')
                . $row('Sujet', $draft['subject_label'] ?? '')
                . $row('Modèle', \App\Support\SseSheetCatalog::label((string) $draft['model'], 'fr'))
                . $row('Mis à jour le', $date($draft['updated_at'] ?? '')) . '</table>';
            foreach ((array) ($draft['texts'] ?? []) as $key => $text) {
                if (is_string($text) && trim($text) !== '') { $html .= '<p>' . nl2br($e($text)) . '</p>'; }
            }
        }
        if (empty($detail['field_notes']) && empty($detail['drafts'])) { $html .= '<p>Aucune fiche accessible rattachée.</p>'; }
        foreach ((array) ($detail['field_notes'] ?? []) as $note) {
            $html .= '<h3>' . $e($note['reference_code'] ?? '') . ' · ' . $e($note['title'] ?? '') . '</h3><table cellpadding="3">'
                . $row('Type / état', ($note['note_kind_label'] ?? '') . ' · ' . ($note['status_label'] ?? $note['status'] ?? ''))
                . $row('Auteur / date', ($note['author_label'] ?? '') . ' · ' . $date($note['observed_at'] ?? $note['created_at'] ?? ''))
                . $row('Lieu', ($note['place_label'] ?? '') . ' · ' . ($note['grid_reference'] ?? '')) . '</table>'
                . '<p style="font-size:9pt;">' . nl2br($e($note['body'] ?? '')) . '</p>';
        }
        $html .= Theme::section('06', 'Personnes et observations');
        foreach ((array) ($detail['persons'] ?? []) as $person) {
            $html .= '<h3>' . $e($person['display_name'] ?? '') . '</h3><table cellpadding="3">';
            foreach (\App\Support\SsePersonObservations::rows($person) as [$label, $value]) { $html .= $row($label, $value); }
            $html .= '</table>';
            foreach ((array) ($person['export_samples'] ?? []) as $sample) {
                $html .= '<table cellpadding="3">' . $row('Relevé biométrique', $sample['modality'] ?? '')
                    . $row('Qualité / acquisition', ($sample['quality'] ?? '—') . ' · ' . ($sample['capture_mode'] ?? ''))
                    . $row('Laboratoire', $sample['lab_reference'] ?? '')
                    . $row('Opérateur / date', ($sample['operator_callsign'] ?? '') . ' · ' . $date($sample['created_at'] ?? '')) . '</table>';
            }
        }
        if (empty($detail['persons'])) { $html .= '<p>Aucune fiche personne accessible associée.</p>'; }
        foreach ((array) ($detail['seek_records'] ?? []) as $record) {
            $modalities = [];
            foreach (['fingers' => 'Empreintes', 'iris' => 'Iris', 'face' => 'Visage'] as $key => $label) {
                if (!empty($record['modalities'][$key])) { $modalities[] = $label; }
            }
            $html .= '<h3>Fiche SEEK II ' . $e($record['rec']) . '</h3><table cellpadding="3">'
                . $row('Identité relevée', $record['display_name'] ?? '')
                . $row('Modalités relevées', implode(' · ', $modalities))
                . $row('Papier constaté', $join([$record['doc_type'] ?? '', $record['doc_country'] ?? '', $record['doc_number'] ?? '']))
                . $row('Opération', $record['op_key'] ?? '')
                . $row('Résultat', $record['verdict_label'] ?? '')
                . $row('Motif / lieu', ($record['reason'] ?? '') . ' · ' . ($record['grid'] ?? ''))
                . $row('Contrôleur / date', ($record['controller_callsign'] ?? '') . ' · ' . $date($record['created_at'] ?? '')) . '</table>';
            foreach ((array) ($record['capture'] ?? []) as $modality => $capture) {
                $notes = is_array($capture) ? implode(' · ', (array) ($capture['notes'] ?? [])) : '';
                $html .= '<p>' . $e($modality) . ' · ' . $e($notes) . '</p>';
            }
        }
        $html .= '<p style="font-size:8pt;color:' . $muted . ';">Les relevés biométriques décrivent les données reçues. Une empreinte n’est illustrée que si une image source a été transmise ; aucune empreinte n’est reconstituée.</p>';
        $html .= Theme::section('07', 'Discussion et notes du dossier');
        foreach ((array) ($detail['updates'] ?? []) as $update) {
            $html .= '<h4>' . $e($update['author_label'] ?? 'Analyste') . ' · ' . $e($date($update['created_at'] ?? '')) . '</h4><p>' . nl2br($e(SseInterestMarkup::plain((string) ($update['body'] ?? '')))) . '</p>';
        }
        if (empty($detail['updates'])) { $html .= '<p>Aucune note consignée.</p>'; }
        $html .= Theme::section('08', 'Historique du dossier');
        $hist = (array) ($detail['history'] ?? []);
        if ($hist === []) {
            $html .= '<p style="font-size:9pt;color:' . $muted . ';">Aucun événement consigné.</p>';
        } else {
            $html .= '<table cellpadding="3">';
            foreach ($hist as $h) {
                $change = $h['field_label'] !== '' ? $h['field_label'] . ' : ' . ($h['old'] ?? '—') . ' → ' . ($h['new'] ?? '—') : $h['action_label'] . ($h['new'] ? ' : ' . $h['new'] : '');
                $html .= '<tr><td width="22%" style="color:' . $muted . ';font-size:8pt;">' . $e($date($h['at'])) . '</td><td width="78%" style="font-size:8.5pt;">'
                    . '<b>' . $e($h['actor']) . '</b> — ' . $e($change) . ($h['reason'] ? ' <span style="color:' . $muted . ';">(' . $e($h['reason']) . ')</span>' : '') . '</td></tr>';
            }
            $html .= '</table>';
        }

        return $html;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Images locales du SSE uniquement : aucun accès réseau ni chemin étranger à ses espaces de stockage. */
    private function imagePath(string $path, int $tenantId): ?string
    {
        if (\App\Support\SseImageStorage::isStoredPath($path)) {
            return \App\Support\SseImageStorage::absolute($path);
        }
        if (SseSeekPhotoService::isPrivatePath($path)) {
            return (new SseSeekPhotoService())->absolutePath(['path' => $path], $tenantId);
        }
        if (!str_starts_with($path, 'uploads/sse/') || str_contains($path, '..')) { return null; }
        $root = realpath(base_path('public/uploads/sse'));
        $file = realpath(base_path('public/' . $path));

        return $root !== false && $file !== false && is_readable($file) && is_file($file) && str_starts_with($file, $root . DIRECTORY_SEPARATOR) ? $file : null;
    }
}
